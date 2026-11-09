<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmotionalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'entry_text',
        'detected_emotion',
        'confidence_score',
        'crisis_level',
        'crisis_category',
        'needs_counselor',
        'detected_contexts',
        'conversation_context',
        'ai_response',
        'is_anonymous',
        'feedback_rating',
        'feedback_text',
        'emotion_score',
        'emotion_confidence',
        'original_score',
        'weighted_score',
        'detailed_emotion_key',
        'emotion_category_key',
        'emotion_classification_source',
        'emotion_taxonomy_version',
        'emotion_uncertainty',
        'detailed_emotion_status',
        'detailed_emotion_secondary_keys',
        'detailed_emotion_other_keys',
        'detailed_emotion_category_keys',
        'detailed_emotion_attribution_scope',
        'detailed_emotion_uncertainty_reason',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'needs_counselor' => 'boolean',
        'confidence_score' => 'decimal:2',
        'detected_contexts' => 'array',
        'conversation_context' => 'array',
        'emotion_score' => 'float',
        'emotion_confidence' => 'float',
        'original_score' => 'float',
        'weighted_score' => 'float',
        'emotion_uncertainty' => 'float',
        'detailed_emotion_secondary_keys' => 'array',
        'detailed_emotion_other_keys' => 'array',
        'detailed_emotion_category_keys' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
