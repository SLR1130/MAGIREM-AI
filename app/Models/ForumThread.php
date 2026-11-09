<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumThread extends Model
{
    protected $fillable = [
        'forum_id',
        'user_id',
        'title',
        'content',
        'views_count',
        'replies_count',
        'is_pinned',
        'is_locked',
        'last_reply_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
        'last_reply_at' => 'datetime',
    ];

    public function forum()
    {
        return $this->belongsTo(DiscussionForum::class, 'forum_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replies()
    {
        return $this->hasMany(ForumReply::class, 'thread_id');
    }

    public function incrementViews()
    {
        $this->increment('views_count');
    }

    public function getLatestReply()
    {
        return $this->replies()->latest('created_at')->first();
    }
}
