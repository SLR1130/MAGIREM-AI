<?php

namespace App\Services;

use App\Models\WellbeingProgress;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MentalStatusService
{
    /**
     * Get user's daily mental status
     */
    public function getDailyStatus($userId)
    {
        $today = Carbon::today();
        
        // Get today's emotional entries
        $entries = DB::table('emotional_entries')
            ->where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->get();
        
        if ($entries->isEmpty()) {
            return [
                'status' => 'no_data',
                'message' => 'No data yet today. Start chatting with MAGI to track your emotions!',
                'overall_mood' => 'neutral',
                'mood_score' => 50,
                'emotions' => [],
                'message_count' => 0,
                'crisis_level' => 'none'
            ];
        }
        
        // Calculate emotion distribution
        $emotions = [];
        $crisisCount = 0;
        $totalConfidence = 0;
        
        foreach ($entries as $entry) {
            $emotion = $entry->detected_emotion;
            if (!isset($emotions[$emotion])) {
                $emotions[$emotion] = 0;
            }
            $emotions[$emotion]++;
            
            if ($entry->crisis_level !== 'none') {
                $crisisCount++;
            }
            
            $confidence = (float) $entry->confidence_score;
            $totalConfidence += $confidence <= 1 ? $confidence * 100 : $confidence;
        }
        
        // Calculate overall mood
        $moodScore = (int) (WellbeingProgress::where('user_id', $userId)
            ->whereDate('date', $today)
            ->value('wellbeing_score') ?? 50);
        $overallMood = $this->determineOverallMood($emotions);
        $crisisLevel = $this->determineCrisisLevel($entries);
        
        // Get recommendations
        $recommendations = $this->getRecommendations($overallMood, $crisisLevel);
        
        return [
            'status' => 'active',
            'overall_mood' => $overallMood,
            'mood_score' => $moodScore,
            'emotions' => $emotions,
            'message_count' => $entries->count(),
            'crisis_level' => $crisisLevel,
            'crisis_count' => $crisisCount,
            'average_confidence' => round($totalConfidence / $entries->count(), 1),
            'recommendations' => $recommendations,
            'last_updated' => now()->format('g:i A')
        ];
    }
    
    /**
     * Get weekly mental status trend
     */
    public function getWeeklyTrend($userId)
    {
        return WellbeingProgress::where('user_id', $userId)
            ->where('date', '>=', Carbon::now()->subDays(7)->toDateString())
            ->orderBy('date')
            ->get()
            ->map(fn ($progress) => [
                'date' => $progress->date->format('M d'),
                'mood_score' => (int) $progress->wellbeing_score,
                'message_count' => (int) $progress->entries_count,
            ])
            ->values()
            ->all();
    }
    
    /**
     * Determine overall mood
     */
    private function determineOverallMood($emotions)
    {
        arsort($emotions);
        $dominant = array_key_first($emotions);
        
        // Map to simplified moods
        $moodMap = [
            'joy' => 'positive',
            'happiness' => 'positive',
            'happy' => 'positive',
            'neutral' => 'neutral',
            'sadness' => 'negative',
            'sad' => 'negative',
            'anxiety' => 'concerned',
            'anxious' => 'concerned',
            'anger' => 'negative',
            'angry' => 'negative',
            'fear' => 'concerned'
        ];
        
        return $moodMap[$dominant] ?? 'neutral';
    }
    
    /**
     * Determine crisis level
     */
    private function determineCrisisLevel($entries)
    {
        $crisisLevels = ['none' => 0, 'low' => 1, 'moderate' => 2, 'high' => 3, 'critical' => 4];
        $maxLevel = 0;
        
        foreach ($entries as $entry) {
            $level = $crisisLevels[$entry->crisis_level] ?? 0;
            if ($level > $maxLevel) {
                $maxLevel = $level;
            }
        }
        
        return array_search($maxLevel, $crisisLevels);
    }
    
    /**
     * Get personalized recommendations
     */
    private function getRecommendations($mood, $crisisLevel)
    {
        $recommendations = [];
        
        if ($crisisLevel === 'high' || $crisisLevel === 'critical') {
            $recommendations[] = [
                'type' => 'urgent',
                'icon' => 'exclamation-triangle',
                'title' => 'Talk to a Counselor',
                'description' => 'Your emotional state indicates you may benefit from professional support.',
                'action' => 'Book Appointment',
                'url' => '/student/appointments'
            ];
        }
        
        if ($mood === 'negative') {
            $recommendations[] = [
                'type' => 'support',
                'icon' => 'heart',
                'title' => 'Self-Care Activities',
                'description' => 'Try some coping strategies to improve your mood.',
                'action' => 'View Toolkit',
                'url' => '/student/coping-toolkit'
            ];
            
            $recommendations[] = [
                'type' => 'journal',
                'icon' => 'pencil',
                'title' => 'Write in Your Journal',
                'description' => 'Expressing your feelings can help process emotions.',
                'action' => 'Start Writing',
                'url' => '/student/journal'
            ];
        }
        
        if ($mood === 'concerned') {
            $recommendations[] = [
                'type' => 'breathing',
                'icon' => 'wind',
                'title' => 'Breathing Exercises',
                'description' => 'Take a moment to practice mindfulness and relaxation.',
                'action' => 'Try Now',
                'url' => '/student/coping-toolkit'
            ];
        }
        
        if ($mood === 'positive') {
            $recommendations[] = [
                'type' => 'maintain',
                'icon' => 'star',
                'title' => 'Keep It Up!',
                'description' => 'You\'re doing great! Continue your positive habits.',
                'action' => 'View Progress',
                'url' => '/student/wellbeing'
            ];
        }
        
        // Always suggest peer support
        $recommendations[] = [
            'type' => 'community',
            'icon' => 'users',
            'title' => 'Connect with Peers',
            'description' => 'Join group activities and share experiences.',
            'action' => 'Explore Community',
            'url' => '/student/community'
        ];
        
        return array_slice($recommendations, 0, 3); // Return top 3
    }
}
