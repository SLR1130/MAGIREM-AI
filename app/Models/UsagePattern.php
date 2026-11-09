<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsagePattern extends Model
{
    protected $fillable = [
        'student_id',
        'message_date',
        'message_time',
        'message_count',
        'time_of_day',
        'emotion_detected',
        'crisis_level',
        'is_late_night'
    ];

    protected $casts = [
        'message_date' => 'date',
        'is_late_night' => 'boolean'
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
