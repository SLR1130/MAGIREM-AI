<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountVerificationAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'application_user_id', 'actor_user_id', 'action', 'from_status', 'to_status', 'internal_note', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function applicant()
    {
        return $this->belongsTo(User::class, 'application_user_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
