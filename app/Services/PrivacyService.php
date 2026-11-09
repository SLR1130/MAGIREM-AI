<?php

namespace App\Services;

use App\Models\PrivacySetting;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PrivacyService
{
    /**
     * Get privacy settings for a user
     */
    public function getSettings($userId)
    {
        return PrivacySetting::getOrCreateForUser($userId);
    }

    /**
     * Update privacy settings
     */
    public function updateSettings($userId, $data)
    {
        return DB::transaction(function () use ($userId, $data) {
            PrivacySetting::getOrCreateForUser($userId);
            $settings = PrivacySetting::where('user_id', $userId)->lockForUpdate()->firstOrFail();
            $currentPolicy = (string) config('privacy.consent_policy_version');
            $hasConsentSubmission = collect(array_keys(PrivacySetting::OPTIONAL_CONSENTS))
                ->contains(fn ($permission) => array_key_exists($permission, $data));

            if ($hasConsentSubmission && (string) ($data['consent_policy_version'] ?? '') !== $currentPolicy) {
                throw new InvalidArgumentException('The privacy notice changed. Please review it and submit your choices again.');
            }

            $updates = collect($data)->only([
                'allow_reminders',
                'data_retention_days',
                'ai_preference_mode',
                'share_emotion_data',
                'auto_journal_entry',
                'wellness_reminders_enabled',
                'reminder_frequency_hours',
            ])->all();

            foreach (PrivacySetting::OPTIONAL_CONSENTS as $permission => $timestamp) {
                if (!array_key_exists($permission, $data)) {
                    continue;
                }

                $enabled = filter_var($data[$permission], FILTER_VALIDATE_BOOLEAN);
                $updates[$permission] = $enabled;
                $updates[$timestamp] = $enabled ? now() : null;
            }

            if (array_key_exists('share_analytics', $updates) && !$updates['share_analytics']) {
                $updates['share_emotion_data'] = false;
            }

            if ($hasConsentSubmission) {
                $updates['consent_policy_version'] = $currentPolicy;
                $updates['consent_source'] = 'self_service_privacy_page';
            }

            // Crisis safeguards are independent of optional sharing preferences.
            $updates['allow_crisis_alerts'] = true;
            $settings->update($updates);
            if (array_key_exists('allow_ai_recommendations', $updates) && !$updates['allow_ai_recommendations']) {
                MagiResourceRecommendationService::forgetForUser((int) $userId);
            }

            return $settings->refresh();
        });
    }

    /**
     * Check if data can be shared with counselor
     */
    public function canShareWithCounselor($userId)
    {
        $settings = $this->getSettings($userId);
        return $settings->hasCurrentConsent('share_with_counselor');
    }

    /**
     * Check if analytics can be shared
     */
    public function canShareAnalytics($userId)
    {
        $settings = $this->getSettings($userId);
        return $settings->hasCurrentConsent('share_analytics');
    }

    /**
     * Check if community participation is allowed
     */
    public function canUseCommunity($userId)
    {
        $settings = $this->getSettings($userId);
        return $settings->hasCurrentConsent('allow_anonymous_community');
    }

    /**
     * Check if AI recommendations are allowed
     */
    public function canUseAI($userId)
    {
        $settings = $this->getSettings($userId);
        return $settings->hasCurrentConsent('allow_ai_recommendations');
    }

    /**
     * Check if crisis alerts are allowed
     */
    public function canReceiveCrisisAlerts($userId)
    {
        $settings = $this->getSettings($userId);
        return $settings->allow_crisis_alerts;
    }

    /**
     * Check if reminders are allowed
     */
    public function canReceiveReminders($userId)
    {
        $settings = $this->getSettings($userId);
        return $settings->allow_reminders;
    }

    /**
     * Get data retention period
     */
    public function getDataRetentionDays($userId)
    {
        $settings = $this->getSettings($userId);
        return $settings->data_retention_days;
    }

    /**
     * Reset privacy settings to defaults
     */
    public function resetToDefaults($userId)
    {
        MagiResourceRecommendationService::forgetForUser((int) $userId);
        return PrivacySetting::updateOrCreate(
            ['user_id' => $userId],
            [
                'share_with_counselor' => false,
                'share_analytics' => false,
                'allow_anonymous_community' => false,
                'allow_ai_recommendations' => false,
                'allow_crisis_alerts' => true,
                'allow_reminders' => true,
                'data_retention_days' => 365,
                'share_emotion_data' => false,
                'counselor_sharing_consented_at' => null,
                'analytics_consented_at' => null,
                'community_consented_at' => null,
                'ai_recommendations_consented_at' => null,
                'consent_policy_version' => null,
                'consent_source' => null,
            ]
        );
    }
}
