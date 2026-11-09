<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopingStrategy extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',
        'instructions',
        'duration_minutes',
        'difficulty',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get active strategies
     */
    public static function getActive()
    {
        return self::where('is_active', true)->get();
    }

    /**
     * Get strategies by category
     */
    public static function getByCategory($category)
    {
        return self::where('category', $category)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get strategies by difficulty
     */
    public static function getByDifficulty($difficulty)
    {
        return self::where('difficulty', $difficulty)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get recommended strategies for an emotion
     */
    public static function getRecommendedForEmotion($emotion)
    {
        $categoryMap = [
            'anxious' => ['breathing', 'grounding', 'mindfulness'],
            'stressed' => ['stress-management', 'breathing', 'mindfulness'],
            'sad' => ['mindfulness', 'grounding', 'self-care'],
            'overwhelmed' => ['breathing', 'grounding', 'stress-management'],
            'angry' => ['breathing', 'grounding', 'physical-activity'],
        ];

        $categories = $categoryMap[$emotion] ?? ['mindfulness', 'breathing'];

        // Legacy seed data uses underscores while the service taxonomy uses
        // hyphens. Query both spellings at this boundary without changing
        // stored records.
        $queryCategories = collect($categories)
            ->flatMap(fn ($category) => [$category, str_replace('-', '_', $category)])
            ->unique()
            ->values()
            ->all();

        return self::whereIn('category', $queryCategories)
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit(3)
            ->get();
    }
}
