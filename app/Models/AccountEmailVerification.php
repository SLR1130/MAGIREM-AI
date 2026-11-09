<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountEmailVerification extends Model
{
    protected $fillable = ['user_id', 'code_hash', 'expires_at', 'attempts', 'send_count', 'last_sent_at', 'verified_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'last_sent_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
