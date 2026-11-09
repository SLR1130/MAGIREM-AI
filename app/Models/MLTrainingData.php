<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MLTrainingData extends Model
{
    use HasFactory;

    protected $table = 'ml_training_data';

    protected $fillable = [
        'emotional_entry_id',
        'text_input',
        'predicted_emotion',
        'prediction_confidence',
        'actual_emotion',
        'predicted_crisis_level',
        'actual_crisis_level',
        'predicted_contexts',
        'actual_contexts',
        'is_validated',
        'validated_by',
        'validated_at',
        'used_for_training',
        'model_version',
    ];

    protected $casts = [
        'prediction_confidence' => 'decimal:2',
        'predicted_contexts' => 'array',
        'actual_contexts' => 'array',
        'is_validated' => 'boolean',
        'used_for_training' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function emotionalEntry()
    {
        return $this->belongsTo(EmotionalEntry::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Check if prediction was correct
     */
    public function isPredictionCorrect(): bool
    {
        if (!$this->is_validated || !$this->actual_emotion) {
            return false;
        }
        
        return $this->predicted_emotion === $this->actual_emotion;
    }

    /**
     * Get accuracy percentage
     */
    public static function getAccuracy(): float
    {
        $validated = self::where('is_validated', true)->count();
        
        if ($validated === 0) {
            return 0.0;
        }
        
        $correct = self::where('is_validated', true)
            ->whereColumn('predicted_emotion', 'actual_emotion')
            ->count();
        
        return round(($correct / $validated) * 100, 2);
    }
}
