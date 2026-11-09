<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountInvitation extends Model
{
    protected $fillable = [
        'user_id', 'created_by', 'delivery_method', 'token_hash', 'recipient_email',
        'expires_at', 'accepted_at', 'revoked_at', 'email_delivery_id',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function emailDelivery()
    {
        return $this->belongsTo(AccountVerificationEmailDelivery::class, 'email_delivery_id');
    }
}
