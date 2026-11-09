<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WellnessReminder extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'scheduled_time',
        'frequency',
        'is_enabled',
        'last_sent_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get enabled reminders for a user
     */
    public static function getEnabledReminders($userId)
    {
        return self::where('user_id', $userId)
            ->where('is_enabled', true)
            ->get();
    }

    /**
     * Get reminders due to send
     */
    public static function getDueReminders()
    {
        return self::where('is_enabled', true)
            ->where(function ($query) {
                $query->whereNull('last_sent_at')
                    ->orWhere('last_sent_at', '<', now()->subHours(23));
            })
            ->get();
    }

    /**
     * Get reminders by type
     */
    public static function getByType($userId, $type)
    {
        return self::where('user_id', $userId)
            ->where('type', $type)
            ->where('is_enabled', true)
            ->get();
    }
}
