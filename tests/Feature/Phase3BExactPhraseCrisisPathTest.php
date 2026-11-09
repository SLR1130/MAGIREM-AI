<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CrisisAlert;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3BExactPhraseCrisisPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_missed_flask_phrase_uses_laravel_safety_response_without_escalation(): void
    {
        config()->set('privacy.crisis_safeguard_policy_version', null);
        config()->set('privacy.institutional_escalation.authorized_counselor_ids', []);

        $student = User::factory()->create(['role' => 'student']);
        app(PrivacyService::class)->updateSettings($student->id, [
            'allow_ai_recommendations' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        $response = $this->actingAs($student)->postJson('/api/ai/chat', [
            'message' => 'I may hurt myself tonight and I am not safe.',
            'user_id' => $student->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('crisis_analysis.level', 'critical');
        $response->assertJsonPath('response_source', 'crisis_response_handler');
        $response->assertJsonPath('situation.auto_appointment_created', false);
        $crisisResponse = strtolower((string) $response->json('response'));
        $this->assertStringContainsString('emergency', $crisisResponse);
        $this->assertStringNotContainsString('flagging this for a counselor', $crisisResponse);
        $this->assertDatabaseCount('crisis_alerts', 0);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseMissing('notifications', ['type' => 'crisis_alert']);
    }
}
