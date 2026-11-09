<?php

namespace App\Services;

use App\Models\WellbeingProgress;

class WellbeingService
{
    /**
     * Get wellbeing progress (alias for getTodayProgress)
     */
    public function getWellbeingProgress($userId)
    {
        return $this->getTodayProgress($userId);
    }

    /**
     * Get today's wellbeing progress
     */
    public function getTodayProgress($userId)
    {
        return WellbeingProgress::getOrCreateToday($userId);
    }

    /**
     * Get wellbeing progress for a period
     */
    public function getPeriodProgress($userId, $days = 30)
    {
        return WellbeingProgress::getPeriodProgress($userId, $days);
    }

    /**
     * Get average wellbeing score
     */
    public function getAverageWellbeing($userId, $days = 30)
    {
        return WellbeingProgress::getAverageWellbeing($userId, $days);
    }

    /**
     * Get wellbeing trend
     */
    public function getTrend($userId, $days = 30)
    {
        return WellbeingProgress::getTrend($userId, $days);
    }

    /**
     * Update wellbeing scores
     */
    public function updateScores($userId, $emotionalStability, $stressLevel, $wellbeingScore)
    {
        $progress = WellbeingProgress::getOrCreateToday($userId);
        $progress->update([
            'emotional_stability' => $emotionalStability,
            'stress_level' => $stressLevel,
            'wellbeing_score' => $wellbeingScore,
        ]);
        return $progress;
    }

    /**
     * Increment entry count
     */
    public function incrementEntryCount($userId)
    {
        $progress = WellbeingProgress::getOrCreateToday($userId);
        $progress->increment('entries_count');
        return $progress;
    }

    /**
     * Increment counselor interaction count
     */
    public function incrementCounselorInteraction($userId)
    {
        $progress = WellbeingProgress::getOrCreateToday($userId);
        $progress->increment('counselor_interactions');
        return $progress;
    }

    /**
     * Get wellbeing statistics
     */
    public function getStatistics($userId, $days = 30)
    {
        $progress = $this->getPeriodProgress($userId, $days);
        
        return [
            'average_wellbeing' => round($this->getAverageWellbeing($userId, $days), 1),
            'trend' => $this->getTrend($userId, $days),
            'total_entries' => $progress->sum('entries_count'),
            'total_interactions' => $progress->sum('counselor_interactions'),
            'days_tracked' => $progress->count(),
            'highest_wellbeing' => $progress->max('wellbeing_score'),
            'lowest_wellbeing' => $progress->min('wellbeing_score'),
        ];
    }

    /**
     * Get wellbeing insights
     */
    public function getInsights($userId, $days = 30)
    {
        $stats = $this->getStatistics($userId, $days);
        $trend = $this->getTrend($userId, $days);

        $insights = [];

        if ($trend === 'improving') {
            $insights[] = '✨ Your wellbeing is improving! Keep up the great work.';
        } elseif ($trend === 'declining') {
            $insights[] = '⚠️ Your wellbeing has been declining. Consider reaching out to a counselor.';
        } else {
            $insights[] = '📊 Your wellbeing is stable. Continue your current practices.';
        }

        if ($stats['average_wellbeing'] >= 70) {
            $insights[] = '🌟 You\'re maintaining a high level of wellbeing!';
        } elseif ($stats['average_wellbeing'] <= 40) {
            $insights[] = '💙 You might benefit from additional support. Consider talking to a counselor.';
        }

        if ($stats['total_entries'] >= 20) {
            $insights[] = '📝 Great job staying consistent with your journaling!';
        }

        return $insights;
    }
}
