<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountVerificationEmailDelivery extends Model
{
    protected $fillable = [
        'user_id', 'kind', 'recipient_email', 'status', 'attempts', 'last_error_code', 'queued_at', 'accepted_at',
    ];

    protected $casts = ['queued_at' => 'datetime', 'accepted_at' => 'datetime'];

    protected $hidden = ['recipient_email'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
