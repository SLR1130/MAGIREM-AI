<?php

namespace App\Services;

use App\Models\EmotionalEntry;
use App\Models\User;
use App\Models\WellbeingProgress;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DailyAggregatorService
{
    /**
     * Reconcile per-day counts into wellbeing_progress without creating a competing score.
     */
    public function aggregateDaily(Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();
        $studentIds = EmotionalEntry::whereDate('created_at', $date)
            ->distinct()
            ->pluck('user_id');
        $processed = 0;
        $summariesCreated = 0;

        foreach ($studentIds as $studentId) {
            if (!User::whereKey($studentId)->where('role', 'student')->exists()) {
                continue;
            }

            $result = $this->aggregateForStudent((int) $studentId, $date);
            $processed++;
            if ($result !== null) {
                $summariesCreated++;
            }
        }

        Log::info('Daily wellbeing reconciliation completed', [
            'date' => $date->toDateString(),
            'processed' => $processed,
            'records_reconciled' => $summariesCreated,
        ]);

        return [
            'processed' => $processed,
            'summaries_created' => $summariesCreated,
        ];
    }

    public function aggregateForStudent(int $userId, Carbon $date): ?array
    {
        $entryCount = EmotionalEntry::where('user_id', $userId)
            ->whereDate('created_at', $date)
            ->count();

        if ($entryCount === 0) {
            return null;
        }

        $progress = WellbeingProgress::firstOrCreate(
            ['user_id' => $userId, 'date' => $date->toDateString()],
            ['emotional_stability' => 50, 'stress_level' => 50, 'wellbeing_score' => 50]
        );
        $progress->update(['entries_count' => $entryCount]);

        return [
            'user_id' => $userId,
            'date' => $date->toDateString(),
            'wellbeing_score' => (int) $progress->wellbeing_score,
            'entry_count' => $entryCount,
            'status' => $this->classifyWellbeing((int) $progress->wellbeing_score),
        ];
    }

    private function classifyWellbeing(int $score): string
    {
        return match (true) {
            $score >= 70 => 'High',
            $score >= 50 => 'Stable',
            $score >= 30 => 'Low',
            default => 'Critical',
        };
    }
}
