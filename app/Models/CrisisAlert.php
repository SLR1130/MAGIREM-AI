<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrisisAlert extends Model
{
    protected $fillable = [
        'student_id',
        'counselor_id',
        'crisis_level',
        'crisis_category',
        'message_content',
        'ai_response',
        'is_resolved',
        'resolved_at',
        'resolved_by'
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime'
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function emotionalEntry()
    {
        return $this->belongsTo(EmotionalEntry::class);
    }
}
