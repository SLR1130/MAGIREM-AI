<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemEvaluation extends Model
{
    protected $fillable = [
        'user_id',
        'usability_rating',
        'ai_helpfulness_rating',
        'counselor_quality_rating',
        'overall_satisfaction_rating',
        'usefulness_rating',
        'comments'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getAverageRating()
    {
        $ratings = [
            $this->usability_rating,
            $this->ai_helpfulness_rating,
            $this->counselor_quality_rating,
            $this->overall_satisfaction_rating,
            $this->usefulness_rating
        ];

        $validRatings = array_filter($ratings, fn($r) => $r !== null);
        return count($validRatings) > 0 ? array_sum($validRatings) / count($validRatings) : 0;
    }
}
