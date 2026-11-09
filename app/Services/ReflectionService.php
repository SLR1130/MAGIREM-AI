<?php

namespace App\Services;

use App\Models\ReflectionPrompt;

class ReflectionService
{
    /**
     * Get a reflection prompt for an emotion
     */
    public function getPromptForEmotion($emotion)
    {
        return ReflectionPrompt::getForEmotion($emotion);
    }

    /**
     * Get a random reflection prompt
     */
    public function getRandomPrompt()
    {
        return ReflectionPrompt::getRandomPrompt();
    }

    /**
     * Get prompts by difficulty
     */
    public function getPromptsByDifficulty($difficulty)
    {
        return ReflectionPrompt::getByDifficulty($difficulty);
    }

    /**
     * Get all active prompts
     */
    public function getAllPrompts()
    {
        return ReflectionPrompt::getActive();
    }

    /**
     * Get multiple prompts for deeper reflection
     */
    public function getDeepReflectionPrompts($emotion, $count = 3)
    {
        return ReflectionPrompt::where('emotion_category', $emotion)
            ->where('difficulty', 'deep')
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit($count)
            ->get();
    }

    /**
     * Get easy prompts for quick reflection
     */
    public function getQuickReflectionPrompts($count = 2)
    {
        return ReflectionPrompt::where('difficulty', 'easy')
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit($count)
            ->get();
    }

    /**
     * Get reflection prompts for a user based on their recent emotion
     */
    public function getReflectionPrompts($userId)
    {
        try {
            // Get the user's most recent mood
            $recentMood = \App\Models\MoodCheckin::where('user_id', $userId)
                ->orderBy('checked_in_at', 'desc')
                ->first();

            if ($recentMood) {
                // Map mood to emotion category for prompts
                $emotionMap = [
                    'happy' => 'positive',
                    'sad' => 'sadness',
                    'anxious' => 'anxiety',
                    'stressed' => 'stress',
                    'neutral' => 'neutral',
                    'angry' => 'anger',
                    'overwhelmed' => 'overwhelm',
                ];

                $emotionCategory = $emotionMap[$recentMood->mood] ?? 'neutral';

                return ReflectionPrompt::where('emotion_category', $emotionCategory)
                    ->where('is_active', true)
                    ->inRandomOrder()
                    ->limit(3)
                    ->get();
            }

            // Fall back to general prompts if no recent mood
            return $this->getQuickReflectionPrompts(3);
        } catch (\Exception $e) {
            // If mood_checkins table doesn't exist, just return general prompts
            \Log::warning("ReflectionService::getReflectionPrompts failed: " . $e->getMessage());
            return $this->getQuickReflectionPrompts(3);
        }
    }
}
