<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacySetting extends Model
{
    public const OPTIONAL_CONSENTS = [
        'share_with_counselor' => 'counselor_sharing_consented_at',
        'share_analytics' => 'analytics_consented_at',
        'allow_anonymous_community' => 'community_consented_at',
        'allow_ai_recommendations' => 'ai_recommendations_consented_at',
    ];

    protected $fillable = [
        'user_id',
        'share_with_counselor',
        'share_analytics',
        'allow_anonymous_community',
        'allow_ai_recommendations',
        'allow_crisis_alerts',
        'allow_reminders',
        'data_retention_days',
        'ai_preference_mode',
        'share_emotion_data',
        'auto_journal_entry',
        'wellness_reminders_enabled',
        'reminder_frequency_hours',
        'notes',
        'counselor_sharing_consented_at',
        'analytics_consented_at',
        'community_consented_at',
        'ai_recommendations_consented_at',
        'consent_policy_version',
        'consent_source',
    ];

    protected $casts = [
        'share_with_counselor' => 'boolean',
        'share_analytics' => 'boolean',
        'allow_anonymous_community' => 'boolean',
        'allow_ai_recommendations' => 'boolean',
        'allow_crisis_alerts' => 'boolean',
        'allow_reminders' => 'boolean',
        'share_emotion_data' => 'boolean',
        'auto_journal_entry' => 'boolean',
        'wellness_reminders_enabled' => 'boolean',
        'counselor_sharing_consented_at' => 'datetime',
        'analytics_consented_at' => 'datetime',
        'community_consented_at' => 'datetime',
        'ai_recommendations_consented_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create privacy settings for a user
     */
    public static function getOrCreateForUser($userId)
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'share_with_counselor' => false,
                'share_analytics' => false,
                'allow_anonymous_community' => false,
                'allow_ai_recommendations' => false,
                'allow_crisis_alerts' => true,
                'allow_reminders' => true,
                'data_retention_days' => 365,
                'ai_preference_mode' => 'companion',
                'share_emotion_data' => false,
                'auto_journal_entry' => true,
                'wellness_reminders_enabled' => true,
                'reminder_frequency_hours' => 24,
            ]
        );
    }

    /**
     * Check if data sharing is allowed
     */
    public function canShareData()
    {
        return $this->hasCurrentConsent('share_with_counselor')
            || $this->hasCurrentConsent('share_analytics');
    }

    /**
     * Check if AI recommendations are allowed
     */
    public function canUseAI()
    {
        return $this->hasCurrentConsent('allow_ai_recommendations');
    }

    public function hasCurrentConsent(string $permission): bool
    {
        $timestamp = self::OPTIONAL_CONSENTS[$permission] ?? null;
        if (!$timestamp || !$this->{$permission} || !$this->{$timestamp}) {
            return false;
        }

        return (string) $this->consent_policy_version === (string) config('privacy.consent_policy_version');
    }

    public function scopeWithCurrentConsent($query, string $permission)
    {
        $timestamp = self::OPTIONAL_CONSENTS[$permission] ?? null;
        if (!$timestamp) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where($permission, true)
            ->whereNotNull($timestamp)
            ->where('consent_policy_version', config('privacy.consent_policy_version'));
    }

    public function hasDocumentedCrisisPolicy(): bool
    {
        return filled(config('privacy.crisis_safeguard_policy_version'));
    }
}
