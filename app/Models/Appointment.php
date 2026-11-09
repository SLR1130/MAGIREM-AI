<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'student_id',
        'counselor_id',
        'appointment_date',
        'status',
        'notes',
        'counselor_notes',
        'category',
        'description',
        'time_slot',
        'preferred_date',
        'reason',
        'created_from_crisis',
        'session_outcome',
        'outcome_reason',
        'attendance_evidence',
        'outcome_verified_at',
        'outcome_verified_by',
        'session_started_at',
        'session_ended_at'
    ];

    protected $casts = [
        'appointment_date' => 'datetime',
        'preferred_date' => 'datetime',
        'created_from_crisis' => 'boolean',
        'outcome_verified_at' => 'datetime',
        'session_started_at' => 'datetime',
        'session_ended_at' => 'datetime'
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(AppointmentStatusHistory::class)->orderBy('created_at');
    }
}
