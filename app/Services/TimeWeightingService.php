<?php

namespace App\Services;

use Carbon\Carbon;

class TimeWeightingService
{
    /**
     * Calculate time multiplier for emotion/entry scoring
     * 
     * Factors:
     * - Late-night boost (22:00-05:00): 1.5x
     * - Age decay (older entries weighted less): 0.75-1.0x
     * 
     * @param Carbon $timestamp - When the entry was created
     * @param bool $applyAgeDecay - Whether to apply age decay (default true)
     * @return float - Multiplier between 0.75 and 1.5
     */
    public function calculateMultiplier(Carbon $timestamp, bool $applyAgeDecay = true): float
    {
        $hour = $timestamp->hour;
        
        // Late-night multiplier (22:00-05:00 = 1.5x boost)
        $lateNightMultiplier = ($hour >= 22 || $hour < 5) ? 1.5 : 1.0;
        
        // Age decay multiplier (only if requested - for depression risk)
        $ageDecayMultiplier = 1.0;
        
        if ($applyAgeDecay) {
            $daysAgo = Carbon::now()->diffInDays($timestamp);
            
            if ($daysAgo <= 1) {
                $ageDecayMultiplier = 1.0;    // Today/yesterday: full weight
            } elseif ($daysAgo <= 3) {
                $ageDecayMultiplier = 0.9;    // 2-3 days old: 90%
            } else {
                $ageDecayMultiplier = 0.75;   // 4+ days old: 75%
            }
        }
        
        // Combine multipliers
        $totalMultiplier = $lateNightMultiplier * $ageDecayMultiplier;
        
        return round($totalMultiplier, 2);
    }
    
    /**
     * Get just the late-night multiplier (no age decay)
     */
    public function getLateNightMultiplier(Carbon $timestamp): float
    {
        return $this->calculateMultiplier($timestamp, false);
    }
    
    /**
     * Get full multiplier with age decay (used by risk calculations)
     */
    public function getFullMultiplier(Carbon $timestamp): float
    {
        return $this->calculateMultiplier($timestamp, true);
    }
    
    /**
     * Check if a timestamp is in late-night hours (22:00-05:00)
     */
    public function isLateNight(Carbon $timestamp): bool
    {
        $hour = $timestamp->hour;
        return ($hour >= 22 || $hour < 5);
    }
    
    /**
     * Get hours until next alert timeout (used for crisis detection)
     * Late-night entries trigger more frequent checks
     */
    public function getAlertCheckInterval(Carbon $timestamp): int
    {
        return $this->isLateNight($timestamp) ? 2 : 4;  // Check every 2 hours late-night, 4 hours daytime
    }
}
