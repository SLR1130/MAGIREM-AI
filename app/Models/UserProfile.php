<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'bio',
        'avatar_url',
        'interests',
        'location',
        'is_public',
        'followers_count',
        'following_count',
        'posts_count',
        'last_seen_at',
    ];

    protected $casts = [
        'interests' => 'json',
        'is_public' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
