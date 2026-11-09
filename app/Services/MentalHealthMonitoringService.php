<?php

namespace App\Services;

use App\Models\CounselorAlert;
use App\Models\UsagePattern;
use App\Models\WellbeingProgress;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MentalHealthMonitoringService
{
    /**
     * Record the distinct usage signal and evaluate non-clinical monitoring alerts.
     * The authoritative daily score is stored in wellbeing_progress.
     */
    public function recordUsage($studentId, $emotion, $crisisLevel, $timestamp = null): int
    {
        $timestamp = $timestamp ?? now();
        $hour = $timestamp->hour;
        $isLateNight = $hour >= 0 && $hour < 6;

        UsagePattern::create([
            'student_id' => $studentId,
            'message_date' => $timestamp->toDateString(),
            'message_time' => $timestamp->toTimeString(),
            'time_of_day' => $this->getTimeOfDay($hour),
            'emotion_detected' => $emotion,
            'crisis_level' => $crisisLevel,
            'is_late_night' => $isLateNight,
        ]);

        $progress = WellbeingProgress::where('user_id', $studentId)
            ->whereDate('date', $timestamp->toDateString())
            ->first();
        $score = (int) ($progress?->wellbeing_score ?? 50);

        $this->checkAndCreateAlert($studentId, $score, $isLateNight, $crisisLevel);

        Log::info('Student wellbeing usage signal recorded', [
            'student_id' => $studentId,
            'wellbeing_score' => $score,
            'crisis_level' => $crisisLevel,
            'late_night' => $isLateNight,
        ]);

        return $score;
    }

    private function getTimeOfDay($hour): string
    {
        if ($hour >= 6 && $hour < 12) {
            return 'morning';
        }
        if ($hour >= 12 && $hour < 18) {
            return 'afternoon';
        }
        if ($hour >= 18 && $hour < 24) {
            return 'evening';
        }

        return 'night';
    }

    private function checkAndCreateAlert($studentId, int $score, bool $isLateNight, string $crisisLevel): void
    {
        $alerts = [];

        if ($score < 30) {
            $alerts[] = [
                'type' => 'low_wellbeing_indicator',
                'severity' => 'critical',
                'message' => "Student's non-clinical wellbeing indicator is critically low ({$score}/100)",
            ];
        } elseif ($score < 40) {
            $alerts[] = [
                'type' => 'wellbeing_concern',
                'severity' => 'high',
                'message' => "Student's non-clinical wellbeing indicator is concerning ({$score}/100)",
            ];
        }

        if ($isLateNight) {
            $lateNightCount = UsagePattern::where('student_id', $studentId)
                ->where('message_date', '>=', now()->subDays(7)->toDateString())
                ->where('is_late_night', true)
                ->count();

            if ($lateNightCount >= 3) {
                $alerts[] = [
                    'type' => 'late_night_usage',
                    'severity' => 'high',
                    'message' => "Student has used the AI {$lateNightCount} times late at night this week",
                ];
            }
        }

        if (in_array($crisisLevel, ['critical', 'severe'], true)) {
            $alerts[] = [
                'type' => 'crisis_detected',
                'severity' => 'critical',
                'message' => "Crisis level {$crisisLevel} detected in a student message",
            ];
        }

        foreach ($alerts as $alert) {
            $alreadyOpen = CounselorAlert::where('student_id', $studentId)
                ->where('alert_type', $alert['type'])
                ->whereIn('status', ['pending', 'acknowledged'])
                ->where('created_at', '>=', now()->subDay())
                ->exists();

            if ($alreadyOpen) {
                continue;
            }

            CounselorAlert::create([
                'student_id' => $studentId,
                'alert_type' => $alert['type'],
                'severity' => $alert['severity'],
                'message' => $alert['message'],
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Return the latest authoritative wellbeing indicator with the latest distinct usage signal.
     */
    public function getMentalStatus($studentId): array
    {
        $progress = WellbeingProgress::where('user_id', $studentId)
            ->orderByDesc('date')
            ->first();
        $latestUsage = UsagePattern::where('student_id', $studentId)
            ->latest('created_at')
            ->first();

        if (!$progress) {
            return [
                'score' => 50,
                'emotion' => $latestUsage?->emotion_detected ?? 'neutral',
                'crisis_level' => $latestUsage?->crisis_level ?? 'none',
                'status' => 'No wellbeing data yet',
                'late_night_usage' => (bool) ($latestUsage?->is_late_night ?? false),
                'score_type' => 'non_clinical_wellbeing_indicator',
            ];
        }

        return [
            'score' => (int) $progress->wellbeing_score,
            'emotion' => $latestUsage?->emotion_detected ?? 'neutral',
            'crisis_level' => $latestUsage?->crisis_level ?? 'none',
            'late_night_usage' => (bool) ($latestUsage?->is_late_night ?? false),
            'status' => $this->getStatusText((int) $progress->wellbeing_score),
            'last_updated' => $progress->updated_at,
            'score_type' => 'non_clinical_wellbeing_indicator',
        ];
    }

    public function getUsagePatterns($studentId, $days = 7)
    {
        return UsagePattern::where('student_id', $studentId)
            ->where('message_date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('message_date')
            ->get()
            ->groupBy('message_date');
    }

    /**
     * Build weekly trend projections from authoritative daily wellbeing and usage records.
     */
    public function getMentalHealthTrend($studentId, $weeks = 4): Collection
    {
        $start = now()->subWeeks($weeks - 1)->startOfWeek();
        $progress = WellbeingProgress::where('user_id', $studentId)
            ->where('date', '>=', $start->toDateString())
            ->orderBy('date')
            ->get();
        $usage = UsagePattern::where('student_id', $studentId)
            ->where('message_date', '>=', $start->toDateString())
            ->get();

        $trends = collect();
        $previousAverage = null;

        for ($offset = 0; $offset < $weeks; $offset++) {
            $weekStart = $start->copy()->addWeeks($offset);
            $weekEnd = $weekStart->copy()->endOfWeek();
            $weekProgress = $progress->filter(fn ($row) => $row->date->between($weekStart, $weekEnd));
            $weekUsage = $usage->filter(function ($row) use ($weekStart, $weekEnd) {
                $date = Carbon::parse($row->message_date);
                return $date->between($weekStart, $weekEnd);
            });

            if ($weekProgress->isEmpty() && $weekUsage->isEmpty()) {
                continue;
            }

            $average = $weekProgress->isEmpty() ? 50 : (int) round($weekProgress->avg('wellbeing_score'));
            $direction = 'stable';
            if ($previousAverage !== null && $average > $previousAverage + 5) {
                $direction = 'improving';
            } elseif ($previousAverage !== null && $average < $previousAverage - 5) {
                $direction = 'declining';
            }

            $trends->push((object) [
                'week_start' => $weekStart->toDateString(),
                'avg_score' => $average,
                'message_count' => $weekUsage->sum('message_count'),
                'late_night_count' => $weekUsage->where('is_late_night', true)->count(),
                'crisis_count' => $weekUsage->whereIn('crisis_level', ['critical', 'severe', 'moderate'])->count(),
                'trend_direction' => $direction,
            ]);
            $previousAverage = $average;
        }

        return $trends;
    }

    private function getStatusText($score): string
    {
        if ($score >= 80) {
            return 'High wellbeing';
        }
        if ($score >= 60) {
            return 'Generally positive';
        }
        if ($score >= 40) {
            return 'Monitor wellbeing';
        }
        if ($score >= 20) {
            return 'Additional support suggested';
        }

        return 'Urgent support suggested';
    }

    public function getPendingAlerts($limit = 20)
    {
        return CounselorAlert::where('status', 'pending')
            ->with('student')
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function acknowledgeAlert($alertId, $counselorId): bool
    {
        $alert = CounselorAlert::find($alertId);
        if (!$alert) {
            return false;
        }

        $alert->update([
            'status' => 'acknowledged',
            'counselor_id' => $counselorId,
        ]);

        return true;
    }

    public function resolveAlert($alertId): bool
    {
        $alert = CounselorAlert::find($alertId);
        if (!$alert) {
            return false;
        }

        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return true;
    }
}
