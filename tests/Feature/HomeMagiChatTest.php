<?php

namespace Tests\Feature;

use App\Models\AIConversationSession;
use App\Models\EmotionalEntry;
use App\Models\User;
use App\Services\CrisisContextualAssessmentService;
use App\Services\EmotionDetectionService;
use App\Services\EnhancedCrisisAnalysisService;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomeMagiChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_greeting_does_not_create_a_session_or_request_ai_without_student_action(): void
    {
        Http::fake();
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('home'))->assertOk()
            ->assertSee('data-magi-nudge', false)
            ->assertSee('data-ai-enabled="false"', false)
            ->assertSee('data-magi-input', false)
            ->assertSee('Privacy Settings')
            ->assertSee('MAGI Recommends');

        $this->assertDatabaseCount('ai_conversation_sessions', 0);
        Http::assertNothingSent();
    }

    public function test_consented_student_gets_the_mini_chat_and_existing_recommendations(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        app(PrivacyService::class)->updateSettings($student->id, [
            'allow_ai_recommendations' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        $this->actingAs($student)->get(route('home'))->assertOk()
            ->assertSee('data-ai-enabled="true"', false)
            ->assertSee('data-chat-url="/api/ai/chat"', false)
            ->assertSee('data-session-url="/api/ai/sessions"', false)
            ->assertSee('data-full-chat-url="'.route('student.ai-chat', [], false).'"', false)
            ->assertSee('data-magi-form', false)
            ->assertSee('data-magi-retry', false)
            ->assertSee('home-magi-chat.js', false)
            ->assertSee('MAGI Recommends');

        $this->assertDatabaseCount('ai_conversation_sessions', 0);
    }

    public function test_chat_endpoint_still_rejects_requests_without_current_consent(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->postJson('/api/ai/chat', [
            'message' => 'Synthetic greeting for a privacy test.',
            'request_id' => 'home-consent-test',
        ])->assertForbidden()->assertJsonPath('code', 'AI_CONSENT_REQUIRED');

        $this->assertDatabaseCount('ai_conversation_sessions', 0);
        $this->assertDatabaseCount('emotional_entries', 0);
    }

    public function test_same_home_chat_request_can_be_retried_without_duplicate_entries(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        app(PrivacyService::class)->updateSettings($student->id, [
            'allow_ai_recommendations' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        $this->mock(EmotionDetectionService::class, fn ($mock) => $mock->shouldReceive('detectEmotion')->once()->andReturn([
            'emotion' => 'sadness', 'intensity' => 0.5, 'confidence' => 0.8,
        ]));
        $this->mock(EnhancedCrisisAnalysisService::class, fn ($mock) => $mock->shouldReceive('analyzeMessage')->once()->andReturn([
            'crisis_level' => 'moderate', 'crisis_confidence' => 0.7, 'sarcasm_detected' => false,
            'satire_detected' => false, 'hyperbole_detected' => false, 'temporal_urgency' => 0.2,
            'intent_detected' => false, 'plan_detected' => false, 'method_detected' => false,
            'access_detected' => false, 'recurrence_detected' => false,
        ]));
        $this->mock(CrisisContextualAssessmentService::class, fn ($mock) => $mock->shouldReceive('assessMessage')->once()->andReturn([
            'risk_category' => 'NEEDS_CONFIRMATION', 'confidence' => 0.7,
            'reason' => 'synthetic ambiguity', 'clarification_question' => 'Are you in immediate danger right now?',
        ]));

        $sessionId = $this->actingAs($student)->postJson('/api/ai/sessions')->assertOk()->json('session_id');
        $payload = ['message' => 'Sometimes I am thinking about dying.', 'session_id' => $sessionId, 'request_id' => 'home-retry-test'];
        $first = $this->postJson('/api/ai/chat', $payload)->assertOk()
            ->assertJsonPath('session_id', $sessionId)
            ->assertJsonPath('response_source', 'crisis_confirmation_handler');
        $second = $this->postJson('/api/ai/chat', $payload)->assertOk();

        $this->assertSame($first->json('response'), $second->json('response'));
        $this->assertSame(1, AIConversationSession::where('user_id', $student->id)->count());
        $this->assertSame(1, EmotionalEntry::where('user_id', $student->id)->count());
    }
}
