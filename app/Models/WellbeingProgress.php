<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WellbeingProgress extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'emotional_stability',
        'stress_level',
        'wellbeing_score',
        'entries_count',
        'counselor_interactions',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get today's progress
     */
    public static function getTodayProgress($userId)
    {
        return self::where('user_id', $userId)
            ->where('date', today())
            ->first();
    }

    /**
     * Get or create today's progress
     */
    public static function getOrCreateToday($userId)
    {
        return self::firstOrCreate(
            [
                'user_id' => $userId,
                'date' => today(),
            ],
            [
                'emotional_stability' => 50,
                'stress_level' => 50,
                'wellbeing_score' => 50,
            ]
        );
    }

    /**
     * Get progress for a period
     */
    public static function getPeriodProgress($userId, $days = 30)
    {
        return self::where('user_id', $userId)
            ->where('date', '>=', today()->subDays($days))
            ->orderBy('date')
            ->get();
    }

    /**
     * Calculate average wellbeing
     */
    public static function getAverageWellbeing($userId, $days = 30)
    {
        return self::where('user_id', $userId)
            ->where('date', '>=', today()->subDays($days))
            ->avg('wellbeing_score');
    }

    /**
     * Get trend (improving, stable, declining)
     */
    public static function getTrend($userId, $days = 30)
    {
        $progress = self::getPeriodProgress($userId, $days);
        
        if ($progress->count() < 2) {
            return 'stable';
        }

        $first = $progress->first()->wellbeing_score;
        $last = $progress->last()->wellbeing_score;

        if ($last > $first + 1) {
            return 'improving';
        } elseif ($last < $first - 1) {
            return 'declining';
        }

        return 'stable';
    }
}
