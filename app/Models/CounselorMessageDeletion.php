<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * CounselorMessageDeletion
 *
 * Records when a user deletes a message "for me" (private deletion).
 * The message remains visible to the other participant.
 */
class CounselorMessageDeletion extends Model
{
    use HasFactory;

    protected $table = 'counselor_message_deletions';

    protected $fillable = [
        'message_id',
        'user_id',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public $timestamps = false;

    public function message()
    {
        return $this->belongsTo(CounselorMessage::class, 'message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
