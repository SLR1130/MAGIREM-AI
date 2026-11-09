<?php

namespace App\Services;

use App\Models\CounselorAlert;
use App\Models\WellbeingProgress;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PatternDetectorService
{
    public function analyzePatterns(Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();
        $studentIds = WellbeingProgress::whereDate('date', '>=', $date->copy()->subDays(4)->toDateString())
            ->whereDate('date', '<=', $date->toDateString())
            ->distinct()
            ->pluck('user_id');
        $alertsGenerated = 0;

        foreach ($studentIds as $studentId) {
            $patterns = $this->analyzeStudentPatterns((int) $studentId, $date);
            $alertsGenerated += collect($patterns)->filter()->count();
        }

        Log::info('Wellbeing pattern detection completed', [
            'date' => $date->toDateString(),
            'students_analyzed' => $studentIds->count(),
            'alerts_generated' => $alertsGenerated,
        ]);

        return [
            'students_analyzed' => $studentIds->count(),
            'alerts_generated' => $alertsGenerated,
        ];
    }

    public function analyzeStudentPatterns(int $userId, Carbon $date): array
    {
        if (!app(PrivacyService::class)->canShareWithCounselor($userId)) {
            return [
                'consecutive_days' => null,
                'sudden_drop' => null,
            ];
        }

        $patterns = [
            'consecutive_days' => $this->detectConsecutiveDays($userId, $date),
            'sudden_drop' => $this->detectSuddenDrop($userId, $date),
        ];

        foreach ($patterns as $key => $pattern) {
            if ($pattern === null || $this->shouldSuppressAlert($userId, $pattern['alert_type'])) {
                $patterns[$key] = null;
                continue;
            }

            CounselorAlert::create([
                'student_id' => $userId,
                'alert_type' => $pattern['alert_type'],
                'severity' => strtolower($pattern['severity']),
                'message' => $pattern['message'],
                'status' => 'pending',
            ]);
        }

        return $patterns;
    }

    private function detectConsecutiveDays(int $userId, Carbon $date): ?array
    {
        $rows = WellbeingProgress::where('user_id', $userId)
            ->whereDate('date', '>=', $date->copy()->subDays(4)->toDateString())
            ->whereDate('date', '<=', $date->toDateString())
            ->orderByDesc('date')
            ->get();

        $consecutiveLow = 0;
        foreach ($rows as $row) {
            if ((int) $row->wellbeing_score > 40) {
                break;
            }
            $consecutiveLow++;
        }

        if ($consecutiveLow < 3) {
            return null;
        }

        return [
            'alert_type' => 'consecutive_low_wellbeing',
            'severity' => 'High',
            'message' => "Non-clinical wellbeing indicator remained at or below 40 for {$consecutiveLow} recorded days",
        ];
    }

    private function detectSuddenDrop(int $userId, Carbon $date): ?array
    {
        $rows = WellbeingProgress::where('user_id', $userId)
            ->whereDate('date', '>=', $date->copy()->subDays(3)->toDateString())
            ->whereDate('date', '<=', $date->toDateString())
            ->orderBy('date')
            ->get();

        if ($rows->count() < 4) {
            return null;
        }

        $previousAverage = (float) $rows->take(3)->avg('wellbeing_score');
        $current = (float) $rows->last()->wellbeing_score;
        $drop = $previousAverage - $current;

        if ($drop < 20) {
            return null;
        }

        return [
            'alert_type' => 'sudden_wellbeing_drop',
            'severity' => 'High',
            'message' => sprintf('Non-clinical wellbeing indicator dropped %.1f points (%.1f to %.1f)', $drop, $previousAverage, $current),
        ];
    }

    private function shouldSuppressAlert(int $userId, string $alertType): bool
    {
        return CounselorAlert::where('student_id', $userId)
            ->where('alert_type', $alertType)
            ->whereIn('status', ['pending', 'acknowledged'])
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();
    }
}
