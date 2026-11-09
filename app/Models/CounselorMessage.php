<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CounselorMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'counselor_id',
        'sender_id',
        'message',
        'is_read',
        'attachment_path',
        'attachment_type',
        'attachment_name',
        'unsent_at',
        'unsent_by',
        'detected_emotion',
        'emotion_confidence',
        'original_score',
        'weighted_score',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'unsent_at' => 'datetime',
        'emotion_confidence' => 'float',
        'original_score' => 'float',
        'weighted_score' => 'float',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function unsentBy()
    {
        return $this->belongsTo(User::class, 'unsent_by');
    }

    /**
     * Check if this message is unsent (deleted for everyone)
     */
    public function isUnsent(): bool
    {
        return $this->unsent_at !== null;
    }

    /**
     * Get users who have deleted this message for themselves
     */
    public function deletedByUsers()
    {
        return $this->belongsToMany(
            User::class,
            'counselor_message_deletions',
            'message_id',
            'user_id'
        );
    }

    /**
     * Check if this message is deleted for a specific user
     */
    public function isDeletedForUser($userId): bool
    {
        return CounselorMessageDeletion::where('message_id', $this->id)
            ->where('user_id', $userId)
            ->exists();
    }
}
