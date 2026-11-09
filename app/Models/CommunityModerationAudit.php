<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityModerationAudit extends Model
{
    protected $fillable = [
        'community_message_id', 'actor_user_id', 'moderator_id', 'action', 'from_status', 'to_status',
        'from_pinned', 'to_pinned', 'notes',
    ];

    protected $casts = [
        'from_pinned' => 'boolean',
        'to_pinned' => 'boolean',
    ];

    public function message()
    {
        return $this->belongsTo(CommunityMessage::class, 'community_message_id');
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
