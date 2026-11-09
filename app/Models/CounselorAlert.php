<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CounselorAlert extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'student_id',
        'counselor_id',
        'alert_type',
        'severity',
        'message',
        'status',
        'created_at',
        'resolved_at'
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'created_at' => 'datetime'
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }
}
