<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CrisisContextualAssessmentService;
use App\Services\EnhancedCrisisAnalysisService;
use App\Services\EmotionDetectionService;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmationResponseProvenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_response_exposes_its_laravel_provenance(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        app(PrivacyService::class)->updateSettings($student->id, [
            'allow_ai_recommendations' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        $this->mock(EmotionDetectionService::class, function ($mock): void {
            $mock->shouldReceive('detectEmotion')->once()->andReturn([
                'emotion' => 'sadness',
                'intensity' => 0.5,
                'confidence' => 0.8,
            ]);
        });
        $this->mock(EnhancedCrisisAnalysisService::class, function ($mock): void {
            $mock->shouldReceive('analyzeMessage')->once()->andReturn([
                'crisis_level' => 'moderate',
                'crisis_confidence' => 0.7,
                'sarcasm_detected' => false,
                'satire_detected' => false,
                'hyperbole_detected' => false,
                'temporal_urgency' => 0.2,
                'intent_detected' => false,
                'plan_detected' => false,
                'method_detected' => false,
                'access_detected' => false,
                'recurrence_detected' => false,
                'reason' => 'synthetic ambiguous candidate',
            ]);
        });
        $this->mock(CrisisContextualAssessmentService::class, function ($mock): void {
            $mock->shouldReceive('assessMessage')->once()->andReturn([
                'risk_category' => 'NEEDS_CONFIRMATION',
                'confidence' => 0.7,
                'reason' => 'synthetic ambiguity',
                'clarification_question' => 'Are you in immediate danger right now?',
            ]);
        });

        $response = $this->actingAs($student)->postJson('/api/ai/chat', [
            'message' => 'Sometimes I am thinking about dying.',
            'user_id' => $student->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('crisis_analysis.level', 'moderate');
        $response->assertJsonPath('response_source', 'crisis_confirmation_handler');
        $response->assertJsonPath('ml_system', 'crisis_confirmation');
    }
}
