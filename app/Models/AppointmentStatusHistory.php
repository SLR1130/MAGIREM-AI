<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentStatusHistory extends Model
{
    protected $fillable = [
        'appointment_id', 'from_status', 'to_status', 'from_outcome', 'to_outcome',
        'reason', 'attendance_evidence', 'changed_by',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}