<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'is_active',
        'last_message_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_message_at' => 'datetime'
    ];

    /**
     * Get the user that owns the session
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the journal entries for this session
     */
    public function entries()
    {
        return $this->hasMany(JournalEntry::class, 'session_id');
    }

    /**
     * Generate a title from the first message
     */
    public function generateTitle()
    {
        $firstEntry = $this->entries()->first();
        
        if ($firstEntry) {
            $text = $firstEntry->entry_text;
            // Take first 50 characters or first sentence
            $title = strlen($text) > 50 ? substr($text, 0, 47) . '...' : $text;
            $this->update(['title' => $title]);
        }
    }

    /**
     * Update last message timestamp
     */
    public function updateLastMessage()
    {
        $this->update(['last_message_at' => now()]);
    }

    /**
     * End this session
     */
    public function end()
    {
        $this->update(['is_active' => false]);
    }
}
