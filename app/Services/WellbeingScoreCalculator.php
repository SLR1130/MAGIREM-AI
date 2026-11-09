<?php

namespace App\Services;

use App\Models\WellbeingProgress;
use Illuminate\Support\Facades\Log;

/**
 * Wellbeing Score Calculator
 * 
 * Calculates wellbeing scores based on:
 * - Emotional state (emotion type + intensity)
 * - Crisis severity
 * - Emotion persistence over time
 * - Stress levels
 * - Emotional stability
 */
class WellbeingScoreCalculator
{
    /**
     * Calculate comprehensive wellbeing score (0-100 scale)
     * 
     * @param int $userId
     * @param array $emotionData - Current emotion detection result
     * @param array $crisisData - Current crisis detection result
     * @return array Wellbeing metrics
     */
    public function calculateWellbeing(int $userId, array $emotionData, array $crisisData): array
    {
        // Start at neutral (50)
        $baseScore = 50;
        
        // Get recent history for trend analysis
        $recentProgress = WellbeingProgress::where('user_id', $userId)
            ->where('date', '>=', today()->subDays(7))
            ->orderBy('date')
            ->get();
        
        // ===== LAYER 1: Current Emotion Impact =====
        $emotionImpact = $this->calculateEmotionImpact($emotionData);
        
        // ===== LAYER 2: Crisis Penalty =====
        $crisisPenalty = $this->calculateCrisisPenalty($crisisData);
        
        // ===== LAYER 3: Trend Impact =====
        $trendImpact = $this->calculateTrendImpact($recentProgress);
        
        // ===== LAYER 4: Persistence Impact (repeated negative emotions) =====
        $persistenceImpact = $this->calculatePersistenceImpact($recentProgress, $emotionData);
        
        // Synthesize final score
        $wellbeingScore = $baseScore + $emotionImpact + $trendImpact + $persistenceImpact - $crisisPenalty;
        $wellbeingScore = max(1, min(100, $wellbeingScore)); // Bound 1-100
        
        // Calculate related metrics
        $stressLevel = $this->calculateStressLevel($emotionData, $crisisData);
        $emotionalStability = $this->calculateEmotionalStability($recentProgress, $emotionData);
        
        return [
            'wellbeing_score' => (int)$wellbeingScore,
            'stress_level' => (int)$stressLevel,
            'emotional_stability' => (int)$emotionalStability,
            'emotion_impact' => $emotionImpact,
            'crisis_penalty' => $crisisPenalty,
            'trend_impact' => $trendImpact,
            'persistence_impact' => $persistenceImpact,
        ];
    }
    
    /**
     * Calculate impact of current emotion on wellbeing
     * Considers emotion type and intensity
     */
    private function calculateEmotionImpact(array $emotionData): float
    {
        $impact = 0;
        $intensity = $emotionData['intensity'] ?? 0.5;
        $emotion = $this->normalizeEmotion($emotionData['emotion'] ?? 'neutral');
        $confidence = $emotionData['confidence'] ?? 0.8;
        
        // Normalize intensity to -30 to +30 scale
        $normalizedIntensity = ($intensity - 0.5) * 60; // -30 to +30
        
        // Apply emotion multiplier
        $emotionMultiplier = 1.0;
        
        switch ($emotion) {
            case 'happy':
            case 'content':
            case 'calm':
                $emotionMultiplier = 1.5; // Positive emotions boost
                break;
            case 'sad':
            case 'depressed':
                $emotionMultiplier = -1.5; // Negative emotions reduce
                break;
            case 'anxious':
            case 'frustrated':
                $emotionMultiplier = -1.0; // Moderate negative
                break;
            case 'neutral':
                $emotionMultiplier = 0; // No change
                break;
        }
        
        $impact = ($normalizedIntensity * $emotionMultiplier * $confidence);
        
        return $impact;
    }
    
    /**
     * Calculate penalty from crisis severity
     */
    private function calculateCrisisPenalty(array $crisisData): float
    {
        $penalty = 0;
        $level = strtolower((string) ($crisisData['level'] ?? $crisisData['crisis_level'] ?? 'none'));
        $confidence = (float) ($crisisData['confidence'] ?? $crisisData['crisis_confidence'] ?? 0);
        
        // Bounded penalties per crisis level
        $penalties = [
            'critical' => 50,  // Critical crisis: -50 points
            'severe' => 30,    // Severe crisis: -30 points
            'moderate' => 15,  // Moderate crisis: -15 points
            'none' => 0        // No crisis: no penalty
        ];
        
        $penalty = $penalties[$level] ?? 0;
        
        // Apply confidence weighting
        $penalty = $penalty * $confidence;
        
        return $penalty;
    }
    
    /**
     * Calculate trend impact (improving vs declining)
     */
    private function calculateTrendImpact($recentProgress): float
    {
        if ($recentProgress->count() < 2) {
            return 0; // Not enough data
        }
        
        $first = $recentProgress->first()->wellbeing_score ?? 50;
        $last = $recentProgress->last()->wellbeing_score ?? 50;
        
        // Calculate trend slope
        $trend = $last - $first;
        
        // Normalize trend to -10 to +10 impact
        // 50-point improvement = +10, 50-point decline = -10
        $trendImpact = ($trend / 50) * 10;
        
        // Bound to ±10
        return max(-10, min(10, $trendImpact));
    }
    
    /**
     * Calculate impact of emotion persistence (repeated negative emotions = lower score)
     */
    private function calculatePersistenceImpact($recentProgress, array $emotionData): float
    {
        if ($recentProgress->count() < 2) {
            return 0;
        }
        
        $currentEmotion = $this->normalizeEmotion($emotionData['emotion'] ?? 'neutral');
        $negativeEmotions = ['sad', 'depressed', 'anxious', 'frustrated'];
        
        // Count consecutive days with negative emotions
        $consecutiveNegativeDays = 0;
        foreach ($recentProgress->reverse() as $progress) {
            $dailyEmotion = \App\Models\EmotionalEntry::where('user_id', $progress->user_id)
                ->whereDate('created_at', $progress->date)
                ->latest()
                ->value('detected_emotion') ?? 'neutral';
            
            if (in_array($this->normalizeEmotion($dailyEmotion), $negativeEmotions, true)) {
                $consecutiveNegativeDays++;
            } else {
                break;
            }
        }
        
        // Persistent negative emotion reduces wellbeing
        // 1 day: no penalty, 3 days: -5, 5 days: -10, 7+ days: -15
        $persistencePenalty = 0;
        if ($consecutiveNegativeDays >= 5) {
            $persistencePenalty = -15;
        } elseif ($consecutiveNegativeDays >= 3) {
            $persistencePenalty = -5;
        }
        
        return $persistencePenalty;
    }
    
    /**
     * Calculate stress level (1-100 scale)
     */
    private function calculateStressLevel(array $emotionData, array $crisisData): float
    {
        $baseStress = 50;
        
        // Crisis adds stress
        $level = strtolower((string) ($crisisData['level'] ?? $crisisData['crisis_level'] ?? 'none'));
        $stressFromCrisis = [
            'critical' => 50,
            'severe' => 35,
            'moderate' => 15,
            'none' => 0
        ];
        
        $stress = $baseStress + ($stressFromCrisis[$level] ?? 0);
        
        // Emotions affect stress
        $emotion = $this->normalizeEmotion($emotionData['emotion'] ?? 'neutral');
        $intensity = $emotionData['intensity'] ?? 0.5;
        
        if ($emotion === 'anxious') {
            $stress += 20 * $intensity;
        } elseif ($emotion === 'frustrated') {
            $stress += 15 * $intensity;
        } elseif ($emotion === 'happy') {
            $stress -= 20 * $intensity;
        } elseif ($emotion === 'calm') {
            $stress -= 15 * $intensity;
        }
        
        return max(1, min(100, $stress));
    }
    
    /**
     * Calculate emotional stability (how balanced/unbalanced the person is)
     * Higher = more stable
     */
    private function calculateEmotionalStability(
        $recentProgress,
        array $emotionData
    ): float {
        $baseStability = 50;
        
        if ($recentProgress->count() < 2) {
            return $baseStability;
        }
        
        // Collect all scores from recent progress
        $scores = $recentProgress->pluck('wellbeing_score')->toArray();
        
        // Calculate standard deviation (lower = more stable)
        $mean = array_sum($scores) / count($scores);
        $variance = array_sum(array_map(
            fn($x) => pow($x - $mean, 2),
            $scores
        )) / count($scores);
        $stdDev = sqrt($variance);
        
        // Normalize std dev to stability score
        // 0 std dev = 100 stable, 50+ std dev = 0 stable
        $stabilityAdjustment = max(-40, -($stdDev / 1.25));
        $stability = $baseStability + $stabilityAdjustment;
        
        // Current extreme emotions reduce stability
        $intensity = $emotionData['intensity'] ?? 0.5;
        if ($intensity > 0.8) {
            $stability -= 15; // Highly intense emotion = less stable
        }
        
        return max(1, min(100, $stability));
    }

    private function normalizeEmotion(string $emotion): string
    {
        return match (strtolower($emotion)) {
            'joy', 'happiness', 'excited' => 'happy',
            'sadness', 'grief', 'down' => 'sad',
            'anxiety', 'fear', 'worried' => 'anxious',
            'anger' => 'frustrated',
            default => strtolower($emotion),
        };
    }
    
    /**
     * Save calculated metrics to database
     */
    public function saveMetrics(int $userId, array $metrics): WellbeingProgress
    {
        $progress = WellbeingProgress::getOrCreateToday($userId);
        
        $progress->update([
            'wellbeing_score' => $metrics['wellbeing_score'],
            'stress_level' => $metrics['stress_level'],
            'emotional_stability' => $metrics['emotional_stability'],
        ]);
        
        Log::info("Wellbeing metrics updated for user {$userId}", [
            'wellbeing_score' => $metrics['wellbeing_score'],
            'stress_level' => $metrics['stress_level'],
            'emotional_stability' => $metrics['emotional_stability'],
        ]);
        
        return $progress;
    }
}
