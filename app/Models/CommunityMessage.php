<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityMessage extends Model
{
    protected $fillable = [
        'user_id',
        'message',
        'rich_content',
        'post_style',
        'post_format',
        'visual_text',
        'visual_layout',
        'visual_media_path',
        'title',
        'category',
        'support_label',
        'image_path',
        'image_hash',
        'image_width',
        'image_height',
        'is_anonymous',
        'is_approved',
        'moderation_status',
        'moderated_at',
        'moderated_by',
        'is_pinned',
        'likes_count',
        'replies_count',
        'reports_count',
        'is_featured',
        'helpful_votes',
        'not_helpful_votes',
        'parent_id',
        'moderation_notes',
        'approved_at',
        'shared_from_id',
        'resource_id',
        'last_activity_at',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'is_anonymous' => 'boolean',
        'visual_layout' => 'array',
        'rich_content' => 'array',
        'post_style' => 'array',
        'is_pinned' => 'boolean',
        'approved_at' => 'datetime',
        'moderated_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class, 'post_id');
    }

    public function reactions()
    {
        return $this->hasMany(CommunityReaction::class);
    }

    public function moderationAudits()
    {
        return $this->hasMany(CommunityModerationAudit::class, 'community_message_id')->latest();
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function sharedFrom()
    {
        return $this->belongsTo(CommunityMessage::class, 'shared_from_id');
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * Get category badge color
     */
    public function getCategoryColor()
    {
        $colors = [
            'academic' => '#3b82f6',      // blue
            'mental-health' => '#ec4899', // pink
            'relationships' => '#f59e0b', // amber
            'motivation' => '#22c55e',    // green
            'general' => '#8b5cf6',       // purple
        ];
        return $colors[$this->category] ?? '#8b5cf6';
    }

    /**
     * Get category icon
     */
    public function getCategoryIcon()
    {
        $icons = [
            'academic' => '📚',
            'mental-health' => '💭',
            'relationships' => '💛',
            'motivation' => '🌱',
            'general' => '💬',
        ];
        return $icons[$this->category] ?? '💬';
    }
}
