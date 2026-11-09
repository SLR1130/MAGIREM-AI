<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SelfAssessment extends Model
{
    protected $fillable = [
        'user_id',
        'assessment_type',
        'questions',
        'answers',
        'score',
        'interpretation'
    ];

    protected $casts = [
        'questions' => 'array',
        'answers' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
