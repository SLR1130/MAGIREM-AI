<?php

namespace App\Services;

use App\Models\CopingStrategy;

class CopingToolkitService
{
    /**
     * Get recommended coping strategies for an emotion
     */
    public function getRecommendedStrategies($emotion, $difficulty = null)
    {
        $strategies = CopingStrategy::getRecommendedForEmotion($emotion);

        if ($difficulty) {
            $strategies = $strategies->filter(function ($strategy) use ($difficulty) {
                return $strategy->difficulty === $difficulty;
            });
        }

        return $strategies;
    }

    /**
     * Get all coping strategies by category
     */
    public function getStrategiesByCategory($category)
    {
        return CopingStrategy::getByCategory($category);
    }

    /**
     * Get strategies by difficulty level
     */
    public function getStrategiesByDifficulty($difficulty)
    {
        return CopingStrategy::getByDifficulty($difficulty);
    }

    /**
     * Get all active strategies
     */
    public function getAllStrategies()
    {
        return CopingStrategy::getActive();
    }

    /**
     * Search active coping strategies without storing the student's query.
     */
    public function searchStrategies(string $query, ?iterable $activities = null, ?string $category = null)
    {
        $terms = collect(preg_split('/\s+/u', mb_strtolower(trim(strip_tags($query)), 'UTF-8')) ?: [])
            ->map(fn ($term) => preg_replace('/[^\p{L}\p{N}_-]/u', '', $term) ?? '')
            ->reject(fn ($term) => in_array($term, ['a', 'an', 'and', 'for', 'in', 'is', 'my', 'of', 'the', 'to', 'with'], true))
            ->filter(fn ($term) => mb_strlen($term, 'UTF-8') >= 2)
            ->unique()
            ->values();

        if ($terms->isEmpty() && !$category) {
            return collect();
        }

        $categoryNeedle = mb_strtolower(trim(str_replace(['_', '-'], ' ', (string) $category)), 'UTF-8');
        $aliases = [
            'box breathing' => 'breath breathing breathwork paced breathing square breathing focus reset',
            'progressive muscle relaxation' => 'relaxation relaxing muscle tension pmr release stress unwind body',
            'cognitive reframing' => 'thoughts reflection perspective worry reframing focus academic stress',
            'grounding technique (5-4-3-2-1)' => 'senses sensory present moment grounding overwhelm focus anxiety',
            'expressive journaling' => 'journal journaling writing reflection thoughts feelings stress',
            'physical activity' => 'movement walk stretching exercise energy focus stress',
        ];

        return collect($activities ?? $this->getAllStrategies())
            ->filter(function ($activity) use ($categoryNeedle, $terms, $aliases) {
                $activityCategory = mb_strtolower(trim(str_replace(['_', '-'], ' ', (string) data_get($activity, 'category', ''))), 'UTF-8');
                if ($categoryNeedle !== '' && $activityCategory !== $categoryNeedle) {
                    return false;
                }

                if ($terms->isEmpty()) {
                    return true;
                }

                $name = mb_strtolower(trim((string) data_get($activity, 'name', '')), 'UTF-8');
                $searchableText = mb_strtolower(implode(' ', [
                    $name,
                    (string) data_get($activity, 'description', ''),
                    $activityCategory,
                    (string) data_get($activity, 'instructions', ''),
                    (string) data_get($activity, 'search_keywords', ''),
                    $aliases[$name] ?? '',
                ]), 'UTF-8');

                return $terms->contains(fn ($term) => mb_strpos($searchableText, $term, 0, 'UTF-8') !== false);
            })
            ->unique(fn ($activity) => mb_strtolower(trim((string) data_get($activity, 'name', '')), 'UTF-8'))
            ->sortBy(fn ($activity) => mb_strtolower(trim((string) data_get($activity, 'name', '')), 'UTF-8'))
            ->values();
    }

    /**
     * Get strategy categories
     */
    public function getCategories()
    {
        return [
            'breathing' => 'Breathing Exercises',
            'mindfulness' => 'Mindfulness & Meditation',
            'grounding' => 'Grounding Techniques',
            'stress-management' => 'Stress Management',
            'physical-activity' => 'Physical Activity',
            'self-care' => 'Self-Care',
            'journaling' => 'Journaling',
            'social-support' => 'Social Support',
        ];
    }

    /**
     * Get difficulty levels
     */
    public function getDifficultyLevels()
    {
        return ['easy', 'medium', 'hard'];
    }

    /**
     * Get strategy details
     */
    public function getStrategyDetails($strategyId)
    {
        return CopingStrategy::find($strategyId);
    }

    /**
     * Get quick strategies (< 5 minutes)
     */
    public function getQuickStrategies()
    {
        return CopingStrategy::where('is_active', true)
            ->where(function ($query) {
                $query->where('duration_minutes', '<', 5)
                    ->orWhereNull('duration_minutes');
            })
            ->get();
    }

    /**
     * Get extended strategies (> 15 minutes)
     */
    public function getExtendedStrategies()
    {
        return CopingStrategy::where('is_active', true)
            ->where('duration_minutes', '>', 15)
            ->get();
    }
}
