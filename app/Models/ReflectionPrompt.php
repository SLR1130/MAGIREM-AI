<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReflectionPrompt extends Model
{
    protected $fillable = [
        'prompt',
        'emotion_category',
        'difficulty',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get active prompts
     */
    public static function getActive()
    {
        return self::where('is_active', true)->get();
    }

    /**
     * Get prompts for an emotion
     */
    public static function getForEmotion($emotion)
    {
        return self::where('emotion_category', $emotion)
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();
    }

    /**
     * Get random prompt
     */
    public static function getRandomPrompt()
    {
        return self::where('is_active', true)
            ->inRandomOrder()
            ->first();
    }

    /**
     * Get prompts by difficulty
     */
    public static function getByDifficulty($difficulty)
    {
        return self::where('difficulty', $difficulty)
            ->where('is_active', true)
            ->inRandomOrder()
            ->get();
    }
}
