<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIConversationSession extends Model
{
    protected $table = 'ai_conversation_sessions';
    
    protected $fillable = [
        'user_id',
        'session_token',
        'title',
        'last_message_at',
        'is_hidden',
        'crisis_assessment_status',
        'session_metadata',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'crisis_flags' => 'array',
        'session_metadata' => 'array',
        'crisis_assessment_status' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(EmotionalEntry::class, 'session_id');
    }

    /**
     * Generate title from first message
     */
    public function generateTitle(): void
    {
        $firstEntry = $this->entries()->oldest()->first();
        
        if ($firstEntry) {
            $text = $firstEntry->entry_text;
            // Take first 50 characters or first sentence
            $title = strlen($text) > 50 ? substr($text, 0, 47) . '...' : $text;
            $this->update(['title' => $title]);
        }
    }

    /**
     * Generate unique session token
     */
    public static function generateSessionToken(): string
    {
        return 'sess_' . time() . '_' . uniqid();
    }

    /**
     * Boot method to auto-generate session_token
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (empty($session->session_token)) {
                $session->session_token = self::generateSessionToken();
            }
        });
    }
}
