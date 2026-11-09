<?php

namespace Tests\Feature;

use App\Models\CommunityMessage;
use App\Models\PrivacySetting;
use App\Models\User;
use App\Services\ChatHistoryService;
use App\Services\PrivacyService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_optional_permissions_default_to_opt_in_while_crisis_safeguards_remain_enabled(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $settings = PrivacySetting::getOrCreateForUser($user->id);

        $this->assertFalse($settings->share_with_counselor);
        $this->assertFalse($settings->share_analytics);
        $this->assertFalse($settings->allow_anonymous_community);
        $this->assertFalse($settings->allow_ai_recommendations);
        $this->assertNull($settings->counselor_sharing_consented_at);
        $this->assertNull($settings->analytics_consented_at);
        $this->assertNull($settings->community_consented_at);
        $this->assertNull($settings->ai_recommendations_consented_at);
        $this->assertTrue($settings->allow_crisis_alerts);
    }

    public function test_legacy_enabled_preferences_are_preserved_but_inactive_until_reconfirmed(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $settings = PrivacySetting::create([
            'user_id' => $user->id,
            'share_with_counselor' => true,
            'share_analytics' => true,
            'allow_anonymous_community' => true,
            'allow_ai_recommendations' => true,
            'allow_crisis_alerts' => true,
        ]);

        $this->assertTrue($settings->share_with_counselor);
        $this->assertFalse($settings->hasCurrentConsent('share_with_counselor'));
        $this->assertFalse($settings->hasCurrentConsent('share_analytics'));
        $this->assertFalse($settings->hasCurrentConsent('allow_anonymous_community'));
        $this->assertFalse($settings->canUseAI());
    }

    public function test_affirmative_consent_records_purpose_timestamps_and_policy_version_and_revocation_clears_it(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $service = app(PrivacyService::class);

        $settings = $service->updateSettings($user->id, [
            'share_with_counselor' => true,
            'share_analytics' => true,
            'allow_anonymous_community' => true,
            'allow_ai_recommendations' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        $this->assertTrue($settings->hasCurrentConsent('share_with_counselor'));
        $this->assertTrue($settings->hasCurrentConsent('share_analytics'));
        $this->assertTrue($settings->hasCurrentConsent('allow_anonymous_community'));
        $this->assertTrue($settings->canUseAI());
        $this->assertSame(config('privacy.consent_policy_version'), $settings->consent_policy_version);
        $this->assertSame('self_service_privacy_page', $settings->consent_source);

        $settings = $service->updateSettings($user->id, [
            'share_with_counselor' => false,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        $this->assertFalse($settings->share_with_counselor);
        $this->assertNull($settings->counselor_sharing_consented_at);
    }

    public function test_ai_processing_is_rejected_without_current_ai_consent(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)
            ->postJson('/api/ai/initialize-session')
            ->assertForbidden()
            ->assertJsonPath('code', 'AI_CONSENT_REQUIRED');
    }

    public function test_ai_preference_endpoint_cannot_modify_another_user(): void
    {
        $actor = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $otherSettings = PrivacySetting::getOrCreateForUser($other->id);

        $this->actingAs($actor)
            ->postJson('/api/ai/preference', [
                'mode' => 'counselor',
                'user_id' => $other->id,
            ])
            ->assertForbidden();

        $this->assertSame('companion', $otherSettings->fresh()->ai_preference_mode);
    }

    public function test_community_mutation_requires_current_community_consent(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)
            ->post(route('student.community.post'), [
                'message' => 'A synthetic community test post',
                'category' => 'general',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('community_messages', 0);

        app(PrivacyService::class)->updateSettings($user->id, [
            'allow_anonymous_community' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        $this->actingAs($user)
            ->post(route('student.community.post'), [
                'message' => 'A synthetic community test post',
                'category' => 'general',
            ])
            ->assertRedirect();

        $this->assertSame(1, CommunityMessage::where('user_id', $user->id)->count());
    }

    public function test_raw_conversation_training_export_is_disabled(): void
    {
        $this->expectException(AuthorizationException::class);

        app(ChatHistoryService::class)->getTrainingBatch();
    }
}
