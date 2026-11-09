<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoodCheckin extends Model
{
    protected $fillable = [
        'user_id',
        'mood',
        'intensity',
        'notes',
        'checked_in_at',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get today's mood checkins for a user
     */
    public static function getTodayCheckins($userId)
    {
        return self::where('user_id', $userId)
            ->whereDate('checked_in_at', today())
            ->orderBy('checked_in_at', 'desc')
            ->get();
    }

    /**
     * Get latest mood checkin
     */
    public static function getLatestCheckin($userId)
    {
        return self::where('user_id', $userId)
            ->orderBy('checked_in_at', 'desc')
            ->first();
    }

    /**
     * Get mood distribution for a period
     */
    public static function getMoodDistribution($userId, $days = 30)
    {
        return self::where('user_id', $userId)
            ->where('checked_in_at', '>=', now()->subDays($days))
            ->selectRaw('mood, COUNT(*) as count')
            ->groupBy('mood')
            ->get();
    }
}
