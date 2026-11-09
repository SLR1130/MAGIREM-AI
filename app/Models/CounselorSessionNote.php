<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CounselorSessionNote extends Model
{
    protected $fillable = [
        'counselor_id',
        'student_id',
        'note',
        'session_type',
        'mood_assessment',
        'session_date'
    ];

    protected $casts = [
        'session_date' => 'datetime'
    ];

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
