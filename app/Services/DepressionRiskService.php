<?php

namespace App\Services;

use App\Models\EmotionalEntry;
use App\Models\User;
use App\Models\CrisisAlert;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DepressionRiskService
{
    private const MAX_EMOTION_WEIGHT = 30;
    private const MAX_TIME_MULTIPLIER = 1.5;

    // Emotion weights: how much each emotion contributes to the risk score
    // Positive = increases risk, Negative = decreases risk
    private const EMOTION_WEIGHTS = [
        'suicidal'   => 30,
        'depressed'  => 22,
        'hopeless'   => 20,
        'helpless'   => 18,
        'sad'        => 12,
        'sadness'    => 12,
        'anxious'    => 11,
        'anxiety'    => 11,
        'stressed'   => 10,
        'angry'      => 8,
        'anger'      => 8,
        'frustrated' => 7,
        'disappointed' => 6,
        'lonely'     => 6,
        'overwhelmed'=> 9,
        'tired'      => 2,
        'down'       => 2,
        'bored'      => 1,
        'neutral'    => 0,
        'okay'       => 0,
        'meh'        => 0,
        'content'    => -5,
        'calm'       => -6,
        'relaxed'    => -6,
        'grateful'   => -8,
        'happy'      => -10,
        'joy'        => -12,
        'excited'    => -13,
    ];

    // Crisis level bonus points on top of emotion weight
    private const CRISIS_BONUS = [
        'none'     => 0,
        'mild'     => 5,
        'moderate' => 15,
        'severe'   => 30,
        'critical' => 50,
    ];

    // Risk thresholds
    public const THRESHOLD_WATCH    = 30;  // Yellow — counselor can see but no alert
    public const THRESHOLD_AT_RISK  = 55;  // Orange — AI changes tone, counselor notified
    public const THRESHOLD_CRITICAL = 75;  // Red — urgent counselor alert

    /**
     * Calculate the current depression risk score for a student (0–100).
     * Based on the last 7 days of emotional entries.
     * Results are cached for 10 minutes per student.
     */
    public function calculateRiskScore(int $userId): array
    {
        $cacheKey = "depression_risk_{$userId}";

        return \Cache::remember($cacheKey, 600, function () use ($userId) {
            return $this->computeRiskScore($userId);
        });
    }

    /**
     * Bust the cache for a student after a new entry is saved.
     */
    public function bustCache(int $userId): void
    {
        \Cache::forget("depression_risk_{$userId}");
    }

    private function computeRiskScore(int $userId): array
    {
        $entries = EmotionalEntry::where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->get();

        if ($entries->isEmpty()) {
            return $this->buildResult(0, 0, 'ok', []);
        }

        $totalPoints    = 0;
        $maxPossible    = 0;
        $dailyBreakdown = [];

        foreach ($entries as $entry) {
            $emotion     = strtolower($entry->detected_emotion ?? 'neutral');
            $crisisLevel = strtolower($entry->crisis_level ?? 'none');

            $emotionWeight = self::EMOTION_WEIGHTS[$emotion] ?? 0;
            $crisisBonus   = self::CRISIS_BONUS[$crisisLevel] ?? 0;

            $entryTime = Carbon::parse($entry->created_at);
            $timeMultiplier = $this->getTimeMultiplier($entryTime);

            $entryScore = ($emotionWeight + $crisisBonus) * $timeMultiplier;
            $totalPoints += $entryScore;

            // Track max possible for normalization
            $maxPossible += (self::MAX_EMOTION_WEIGHT + max(self::CRISIS_BONUS)) * self::MAX_TIME_MULTIPLIER;

            // Daily breakdown
            $day = $entryTime->format('M d');
            if (!isset($dailyBreakdown[$day])) {
                $dailyBreakdown[$day] = ['score' => 0, 'count' => 0, 'emotions' => []];
            }
            $dailyBreakdown[$day]['score']      += $entryScore;
            $dailyBreakdown[$day]['count']++;
            $dailyBreakdown[$day]['emotions'][] = $emotion;
        }

        $expectedMax = max($maxPossible, 1);
        $rawScore    = max(0, $totalPoints);
        $score       = min(100, round(($rawScore / $expectedMax) * 100));

        $level = $this->getLevel($score);

        return $this->buildResult($score, $entries->count(), $level, $dailyBreakdown);
    }

    private function getTimeMultiplier(Carbon $entryTime): float
    {
        $timeWeighting = new TimeWeightingService();
        return $timeWeighting->getFullMultiplier($entryTime);
    }

    /**
     * Get the risk level label from a score.
     */
    public function getLevel(int $score): string
    {
        if ($score >= self::THRESHOLD_CRITICAL) return 'critical';
        if ($score >= self::THRESHOLD_AT_RISK)  return 'at_risk';
        if ($score >= self::THRESHOLD_WATCH)    return 'watch';
        return 'ok';
    }

    /**
     * Compatibility-only calculation. This historical aggregate is not an escalation
     * signal and must not create alerts, notifications, or appointments.
     */
    public function evaluateAfterEntry(int $userId): array
    {
        $this->bustCache($userId);
        $risk = $this->calculateRiskScore($userId);

        return array_merge($risk, [
            'alert_triggered' => false,
            'alert_message' => null,
        ]);
    }

    /**
     * Create a counselor alert only if one hasn't been created in the last 24 hours
     * for this student at this level (prevents spam).
     */
    private function maybeCreateAlert(int $userId, int $score, string $level): bool
    {
        // Check if we already alerted in the last 24 hours
        $recentAlert = CrisisAlert::where('student_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->where('is_resolved', false)
            ->exists();

        if ($recentAlert) {
            return false; // Already alerted, don't spam
        }

        try {
            // Find any available counselor to assign
            $counselor = User::where('role', 'counselor')->first();

            CrisisAlert::create([
                'student_id'      => $userId,
                'counselor_id'    => $counselor?->id ?? 1,
                'crisis_level'    => $level === 'critical' ? 'critical' : 'severe',
                'crisis_category' => 'depression_risk',
                'message_content' => $this->buildAlertDescription($userId, $score, $level),
                'ai_response'     => "Automatically detected by Depression Risk Monitor. Risk score: {$score}/100.",
                'is_resolved'     => false,
            ]);

            Log::info("Depression risk alert created for user {$userId} — score: {$score}, level: {$level}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create depression risk alert: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build a human-readable alert description for the counselor.
     */
    private function buildAlertDescription(int $userId, int $score, string $level): string
    {
        $student = User::find($userId);
        $name    = $student->name ?? 'Unknown Student';

        $recentEmotions = EmotionalEntry::where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('detected_emotion, count(*) as cnt')
            ->groupBy('detected_emotion')
            ->orderByDesc('cnt')
            ->limit(3)
            ->pluck('cnt', 'detected_emotion')
            ->map(fn($cnt, $e) => "{$e} ({$cnt}x)")
            ->implode(', ');

        $levelLabel = $level === 'critical' ? 'CRITICAL' : 'AT RISK';

        return "{$name}'s depression risk score has reached {$score}/100 ({$levelLabel}) over the past 7 days. "
             . "Dominant emotions: {$recentEmotions}. "
             . "This was automatically detected from their daily AI chat entries. Please review their profile.";
    }

    /**
     * Get at-risk students for the counselor dashboard.
     * Returns students sorted by risk score descending.
     */
    public function getAtRiskStudents(int $limit = 20, ?array $studentIds = null): array
    {
        $scopeKey = $studentIds === null ? 'all' : sha1(implode(',', $studentIds));

        return \Cache::remember("at_risk_students:{$scopeKey}", 300, function () use ($limit, $studentIds) {
            $students = User::where('role', 'student')
                ->when($studentIds !== null, fn ($query) => $query->whereIn('id', $studentIds))
                ->whereHas('privacySetting', fn ($query) =>
                    $query->withCurrentConsent('share_with_counselor'))
                ->get();
            $results  = [];

            foreach ($students as $student) {
                $risk = $this->calculateRiskScore($student->id);
                if ($risk['score'] >= self::THRESHOLD_WATCH) {
                    $results[] = [
                        'student'      => $student,
                        'score'        => $risk['score'],
                        'level'        => $risk['level'],
                        'entry_count'  => $risk['entry_count'],
                        'daily'        => $risk['daily_breakdown'],
                    ];
                }
            }

            usort($results, fn($a, $b) => $b['score'] - $a['score']);
            return array_slice($results, 0, $limit);
        });
    }

    /**
     * Build the result array.
     */
    private function buildResult(int $score, int $entryCount, string $level, array $daily): array
    {
        return [
            'score'           => $score,
            'level'           => $level,
            'entry_count'     => $entryCount,
            'daily_breakdown' => $daily,
            'bar_color'       => $this->getBarColor($level),
            'bar_label'       => $this->getBarLabel($level),
            'description'     => $this->getLevelDescription($level, $score),
        ];
    }

    private function getBarColor(string $level): string
    {
        return match($level) {
            'critical' => '#ef4444',
            'at_risk'  => '#f59e0b',
            'watch'    => '#3b82f6',
            default    => '#10b981',
        };
    }

    private function getBarLabel(string $level): string
    {
        return match($level) {
            'critical' => 'Needs Immediate Support',
            'at_risk'  => 'At Risk',
            'watch'    => 'Worth Watching',
            default    => 'Doing Well',
        };
    }

    private function getLevelDescription(string $level, int $score): string
    {
        return match($level) {
            'critical' => "Your wellness score is low ({$score}/100). You've been going through a lot lately. A counselor has been notified and is ready to help.",
            'at_risk'  => "Your wellness score ({$score}/100) suggests you've been struggling. Consider talking to a counselor.",
            'watch'    => "Your wellness score ({$score}/100) shows some stress. Keep checking in — you're doing the right thing by talking.",
            default    => "Your wellness score ({$score}/100) looks good. Keep it up!",
        };
    }
}
