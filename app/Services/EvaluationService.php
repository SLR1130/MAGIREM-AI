<?php

namespace App\Services;

use App\Models\SystemEvaluation;
use Illuminate\Support\Facades\Log;

class EvaluationService
{
    /**
     * Store system evaluation
     */
    public function storeEvaluation($userId, $data)
    {
        $evaluation = SystemEvaluation::create([
            'user_id' => $userId,
            'usability_rating' => $data['usability_rating'] ?? null,
            'ai_helpfulness_rating' => $data['ai_helpfulness_rating'] ?? null,
            'counselor_quality_rating' => $data['counselor_quality_rating'] ?? null,
            'overall_satisfaction_rating' => $data['overall_satisfaction_rating'] ?? null,
            'usefulness_rating' => $data['usefulness_rating'] ?? null,
            'comments' => $data['comments'] ?? null
        ]);

        Log::info("System evaluation submitted by user {$userId}");

        return $evaluation;
    }

    /**
     * Get evaluation statistics
     */
    public function getEvaluationStats()
    {
        $evaluations = SystemEvaluation::all();

        if ($evaluations->isEmpty()) {
            return null;
        }

        return [
            'total_evaluations' => $evaluations->count(),
            'average_usability' => $evaluations->avg('usability_rating'),
            'average_ai_helpfulness' => $evaluations->avg('ai_helpfulness_rating'),
            'average_counselor_quality' => $evaluations->avg('counselor_quality_rating'),
            'average_overall_satisfaction' => $evaluations->avg('overall_satisfaction_rating'),
            'average_usefulness' => $evaluations->avg('usefulness_rating'),
            'overall_average' => $evaluations->map(fn($e) => $e->getAverageRating())->avg()
        ];
    }

    /**
     * Get rating distribution
     */
    public function getRatingDistribution($ratingType)
    {
        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = SystemEvaluation::where($ratingType, $i)->count();
        }
        return $distribution;
    }

    /**
     * Get user evaluation
     */
    public function getUserEvaluation($userId)
    {
        return SystemEvaluation::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
