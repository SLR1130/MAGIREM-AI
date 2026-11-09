<?php

namespace Tests\Feature;

use App\Models\EmotionalEntry;
use App\Models\PrivacySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEmotionAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reports_show_category_aggregates_only_for_current_analytics_consent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consentingStudent = User::factory()->create(['role' => 'student']);
        $privateStudent = User::factory()->create(['role' => 'student']);

        $this->grantAnalyticsConsent($consentingStudent);

        EmotionalEntry::create([
            'user_id' => $consentingStudent->id,
            'entry_text' => 'synthetic consenting entry',
            'detected_emotion' => 'joy',
            'confidence_score' => 80,
            'detailed_emotion_key' => 'happiness_joy',
            'emotion_category_key' => 'positive_wellbeing',
            'emotion_classification_source' => 'trained_model',
            'emotion_taxonomy_version' => '1.0.0',
        ]);
        EmotionalEntry::create([
            'user_id' => $privateStudent->id,
            'entry_text' => 'synthetic private entry',
            'detected_emotion' => 'sadness',
            'confidence_score' => 90,
            'detailed_emotion_key' => 'sadness',
            'emotion_category_key' => 'sadness_disconnection',
            'emotion_classification_source' => 'trained_model',
            'emotion_taxonomy_version' => '1.0.0',
        ]);

        config()->set('privacy.analytics_min_group_size', 1);

        $this->actingAs($admin)
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('Positive &amp; Well-being', false)
            ->assertSee('1 of 1 consented entries have taxonomy metadata', false)
            ->assertSee('Denominator: consenting classified messages', false);
    }

    public function test_small_consenting_groups_are_suppressed_from_category_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $this->grantAnalyticsConsent($student);

        EmotionalEntry::create([
            'user_id' => $student->id,
            'entry_text' => 'synthetic small group entry',
            'detected_emotion' => 'joy',
            'confidence_score' => 80,
            'detailed_emotion_key' => 'happiness_joy',
            'emotion_category_key' => 'positive_wellbeing',
        ]);

        config()->set('privacy.analytics_min_group_size', 2);

        $this->actingAs($admin)
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('Suppressed for privacy', false)
            ->assertSee('Categories with fewer than 2 consenting students are suppressed.', false);
    }

    public function test_gemma_shadow_records_stay_out_of_admin_until_explicit_gate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $this->grantAnalyticsConsent($student);

        EmotionalEntry::create([
            'user_id' => $student->id,
            'entry_text' => 'synthetic trained entry',
            'detected_emotion' => 'joy',
            'confidence_score' => 80,
            'detailed_emotion_key' => 'happiness_joy',
            'emotion_category_key' => 'positive_wellbeing',
            'emotion_classification_source' => 'trained_model',
        ]);
        EmotionalEntry::create([
            'user_id' => $student->id,
            'entry_text' => 'synthetic shadow entry',
            'detected_emotion' => 'joy',
            'confidence_score' => 80,
            'detailed_emotion_key' => 'excitement',
            'emotion_category_key' => 'positive_wellbeing',
            'emotion_classification_source' => 'gemma_shadow',
            'detailed_emotion_status' => 'clear',
        ]);

        config()->set('privacy.analytics_min_group_size', 1);
        config()->set('services.mindcare_ml.detailed_emotion_shadow.admin_enabled', false);
        $this->actingAs($admin)
            ->get(route('admin.reports'))
            ->assertSee('1 of 1 consented entries have taxonomy metadata', false);

        config()->set('services.mindcare_ml.detailed_emotion_shadow.admin_enabled', true);
        $this->actingAs($admin)
            ->get(route('admin.reports'))
            ->assertSee('2 of 2 consented entries have taxonomy metadata', false);
    }

    private function grantAnalyticsConsent(User $student): void
    {
        PrivacySetting::getOrCreateForUser($student->id)->update([
            'share_analytics' => true,
            'share_emotion_data' => true,
            'analytics_consented_at' => now(),
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);
    }
}
