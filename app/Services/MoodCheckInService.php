<?php

namespace App\Services;

use App\Models\CrisisAlert;
use App\Models\MoodCheckin;

class MoodCheckInService
{
    public function __construct(private WellbeingScoreCalculator $wellbeingCalculator)
    {
    }

    /**
     * Store the check-in, then update the shared authoritative 0-100 daily wellbeing record.
     */
    public function recordCheckin($userId, $mood, $intensity, $notes = null)
    {
        $intensity = max(1, min(10, (int) $intensity));
        $checkin = MoodCheckin::create([
            'user_id' => $userId,
            'mood' => $mood,
            'intensity' => $intensity,
            'notes' => $notes,
            'checked_in_at' => now(),
        ]);

        $recentCrisis = CrisisAlert::where('student_id', $userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->latest()
            ->first();

        $metrics = $this->wellbeingCalculator->calculateWellbeing(
            $userId,
            [
                'emotion' => $mood,
                'intensity' => $intensity / 10,
                'confidence' => 1.0,
            ],
            [
                'crisis_level' => $recentCrisis?->crisis_level ?? 'none',
                'crisis_confidence' => $recentCrisis ? 1.0 : 0.0,
            ]
        );
        $this->wellbeingCalculator->saveMetrics($userId, $metrics);

        return $checkin;
    }

    public function getMoodStats($userId, $days = 30): array
    {
        $checkins = MoodCheckin::where('user_id', $userId)
            ->where('checked_in_at', '>=', now()->subDays($days))
            ->get();
        $distribution = $checkins->groupBy('mood')->map->count();

        return [
            'total_checkins' => $checkins->count(),
            'distribution' => $distribution,
            'average_intensity' => round((float) $checkins->avg('intensity'), 1),
            'most_common_mood' => $distribution->sortDesc()->keys()->first(),
        ];
    }

    public function getMoodTrend($userId, $days = 7)
    {
        return MoodCheckin::where('user_id', $userId)
            ->where('checked_in_at', '>=', now()->subDays($days))
            ->orderBy('checked_in_at')
            ->get()
            ->map(fn ($checkin) => [
                'date' => $checkin->checked_in_at->format('Y-m-d'),
                'mood' => $checkin->mood,
                'intensity' => $checkin->intensity,
            ]);
    }

    public function getTodayCheckins($userId)
    {
        return MoodCheckin::getTodayCheckins($userId);
    }

    public function getLatestCheckin($userId)
    {
        return MoodCheckin::getLatestCheckin($userId);
    }
}
