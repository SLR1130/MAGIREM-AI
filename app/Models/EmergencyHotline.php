<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyHotline extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'type',
        'description',
        'availability',
        'country',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get hotlines by type
     */
    public static function getByType($type)
    {
        return self::where('type', $type)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get all active hotlines
     */
    public static function getActive()
    {
        return self::where('is_active', true)
            ->orderBy('type')
            ->get();
    }

    /**
     * Get crisis hotlines (suicide, self-harm, abuse)
     */
    public static function getCrisisHotlines()
    {
        return self::whereIn('type', ['suicide', 'self-harm', 'abuse', 'crisis'])
            ->where('is_active', true)
            ->get();
    }
}
