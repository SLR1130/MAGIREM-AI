<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupActivity extends Model
{
    protected $fillable = ['title', 'description', 'type', 'facilitator_id', 'scheduled_at', 'max_participants', 'status'];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ActivityParticipant::class, 'activity_id');
    }

    public function getParticipantCount()
    {
        return $this->participants()->where('status', 'registered')->count();
    }

    public function getAvailableSpots()
    {
        return $this->max_participants - $this->getParticipantCount();
    }

    public function isUserRegistered($userId)
    {
        return $this->participants()->where('user_id', $userId)->where('status', 'registered')->exists();
    }
}
