<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MLModelVersion extends Model
{
    use HasFactory;

    protected $table = 'ml_model_versions';

    protected $fillable = [
        'version',
        'model_type',
        'model_path',
        'training_samples',
        'accuracy',
        'precision',
        'recall',
        'f1_score',
        'training_config',
        'is_active',
        'notes',
        'trained_at',
        'trained_by',
    ];

    protected $casts = [
        'training_samples' => 'integer',
        'accuracy' => 'decimal:2',
        'precision' => 'decimal:2',
        'recall' => 'decimal:2',
        'f1_score' => 'decimal:2',
        'training_config' => 'array',
        'is_active' => 'boolean',
        'trained_at' => 'datetime',
    ];

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trained_by');
    }

    /**
     * Get the active model for a specific type
     */
    public static function getActiveModel(string $type): ?self
    {
        return self::where('model_type', $type)
            ->where('is_active', true)
            ->latest('trained_at')
            ->first();
    }

    /**
     * Activate this model version
     */
    public function activate(): void
    {
        // Deactivate all other models of the same type
        self::where('model_type', $this->model_type)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);
        
        // Activate this model
        $this->update(['is_active' => true]);
    }
}
