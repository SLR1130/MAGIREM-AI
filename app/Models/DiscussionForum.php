<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscussionForum extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'icon',
        'threads_count',
        'replies_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function threads()
    {
        return $this->hasMany(ForumThread::class, 'forum_id');
    }

    public function activeThreads()
    {
        return $this->hasMany(ForumThread::class, 'forum_id')->where('is_locked', false);
    }

    public function pinnedThreads()
    {
        return $this->hasMany(ForumThread::class, 'forum_id')->where('is_pinned', true);
    }

    public function getThreadsCountAttribute()
    {
        return $this->threads()->count();
    }

    public function getLatestThread()
    {
        return $this->threads()->latest('last_reply_at')->first();
    }
}
