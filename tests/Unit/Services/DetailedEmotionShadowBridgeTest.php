<?php

namespace Tests\Unit\Services;

use App\Models\PrivacySetting;
use App\Models\EmotionalEntry;
use App\Models\User;
use App\Http\Controllers\AIChatController;
use App\Services\InferenceBridgeClient;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DetailedEmotionShadowBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function enablePilot(): void
    {
        config()->set('services.mindcare_ml.detailed_emotion_shadow.enabled', true);
        config()->set('services.mindcare_ml.detailed_emotion_shadow.approved', true);
        config()->set('services.mindcare_ml.inference_bridge.url', 'http://127.0.0.1:5000');
        config()->set('services.mindcare_ml.inference_bridge.secret', str_repeat('a', 64));
    }

    public function test_shadow_requires_server_side_ai_and_analytics_consent(): void
    {
        $this->enablePilot();
        $user = User::factory()->create(['role' => 'student']);
        Http::fake();

        $result = app(InferenceBridgeClient::class)->detailedEmotionShadow(
            $user->id,
            'Excited ako pero kinakabahan din ako.',
            'shadow-consent-001'
        );

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_shadow_uses_current_server_side_consent_and_returns_validated_result(): void
    {
        $this->enablePilot();
        $user = User::factory()->create(['role' => 'student']);
        app(PrivacyService::class)->updateSettings($user->id, [
            'allow_ai_recommendations' => true,
            'share_analytics' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        Http::fake([
            'http://127.0.0.1:5000/internal/detailed-emotion-shadow' => Http::response([
                'success' => true,
                'shadow' => [
                    'status' => 'mixed',
                    'attribution_scope' => 'student',
                    'primary_emotion' => 'excitement',
                    'secondary_emotions' => ['anxiety'],
                    'other_emotions' => [],
                    'uncertainty_reason' => null,
                    'classification_source' => 'gemma_shadow',
                    'taxonomy_version' => '1.0.0',
                    'category_key' => 'positive_wellbeing',
                    'category_keys' => ['positive_wellbeing', 'anxiety_stress'],
                    'other_category_keys' => [],
                    'schema_valid' => true,
                    'shadow_only' => true,
                    'stored' => false,
                    'latency_ms' => 120.4,
                ],
            ], 200),
        ]);

        $result = app(InferenceBridgeClient::class)->detailedEmotionShadow(
            $user->id,
            'Excited ako pero kinakabahan din ako.',
            'shadow-consent-002'
        );

        $this->assertSame('mixed', $result['shadow']['status']);
        $this->assertSame(['positive_wellbeing', 'anxiety_stress'], $result['shadow']['category_keys']);
        Http::assertSent(function ($request) use ($user) {
            $payload = $request->data();

            return $request->url() === 'http://127.0.0.1:5000/internal/detailed-emotion-shadow'
                && $payload['user_id'] === (string) $user->id
                && $payload['consent_granted'] === true
                && $payload['consent_purpose'] === 'emotion_shadow_evaluation'
                && (int) $payload['consent_revision'] > 0
                && $request->hasHeader('X-MindCare-Inference-Signature');
        });
    }

    public function test_persisted_shadow_metadata_is_taxonomy_derived_and_keeps_mixed_state(): void
    {
        config()->set('services.mindcare_ml.detailed_emotion_shadow.persist', true);
        $entry = EmotionalEntry::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'entry_text' => 'synthetic pilot message',
            'detected_emotion' => 'joy',
            'confidence_score' => 80,
        ]);

        $controller = (new \ReflectionClass(AIChatController::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass(AIChatController::class))->getMethod('persistDetailedEmotionShadow');
        $method->setAccessible(true);
        $method->invoke($controller, $entry, [
            'status' => 'mixed',
            'attribution_scope' => 'student',
            'primary_emotion' => 'excitement',
            'secondary_emotions' => ['anxiety'],
            'other_emotions' => [],
            'uncertainty_reason' => null,
        ]);

        $entry->refresh();
        $this->assertSame('mixed', $entry->detailed_emotion_status);
        $this->assertSame('excitement', $entry->detailed_emotion_key);
        $this->assertSame('positive_wellbeing', $entry->emotion_category_key);
        $this->assertSame(['positive_wellbeing', 'anxiety_stress'], $entry->detailed_emotion_category_keys);
        $this->assertSame(['anxiety'], $entry->detailed_emotion_secondary_keys);
        $this->assertSame('gemma_shadow', $entry->emotion_classification_source);
        $this->assertNull($entry->emotion_uncertainty);
    }

    public function test_response_pilot_only_exposes_student_attributed_mixed_context(): void
    {
        $controller = (new \ReflectionClass(AIChatController::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass(AIChatController::class))->getMethod('buildEmotionResponseContext');
        $method->setAccessible(true);

        $mixed = $method->invoke($controller, [
            'schema_valid' => true,
            'status' => 'mixed',
            'attribution_scope' => 'both',
            'felt_by_student' => true,
            'primary_emotion' => 'excitement',
            'secondary_emotions' => ['anxiety'],
            'other_emotions' => ['sadness'],
            'classification_source' => 'gemma_shadow',
            'taxonomy_version' => '1.0.0',
        ]);
        $this->assertSame(['excitement', 'anxiety'], $mixed['emotions']);
        $this->assertSame('gemma_shadow', $mixed['classification_source']);
        $this->assertTrue($mixed['schema_valid']);
        $this->assertTrue($mixed['felt_by_student']);
        $this->assertNotContains('sadness', $mixed['emotions']);

        $other = $method->invoke($controller, [
            'schema_valid' => true,
            'status' => 'attributed_other',
            'attribution_scope' => 'other',
            'felt_by_student' => false,
            'primary_emotion' => null,
            'secondary_emotions' => [],
        ]);
        $this->assertNull($other);
    }
    public function test_chat_forwards_only_an_accepted_response_cue_to_flask(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $this->actingAs($user);
        config()->set('services.mindcare_ml.url', 'http://127.0.0.1:5000');
        config()->set('services.mindcare_ml.phase4a.enabled', false);
        Http::fake([
            'http://127.0.0.1:5000/chat' => Http::response([
                'success' => true,
                'response' => 'Synthetic response',
                'ml_system' => 'hybrid_active',
            ], 200),
        ]);

        $controller = (new \ReflectionClass(AIChatController::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass(AIChatController::class))->getMethod('callMLAPIServer');
        $method->setAccessible(true);
        $accepted = [
            'status' => 'mixed',
            'attribution_scope' => 'student',
            'emotions' => ['excitement', 'anxiety'],
            'category_keys' => ['positive_wellbeing', 'anxiety_stress'],
            'taxonomy_version' => '1.0.0',
        ];

        $result = $method->invoke($controller, 'synthetic pilot message', $user->id, 'none', false, null, [], 'pilot-trace-accepted', [], 'pilot-request-accepted', $accepted);

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) use ($accepted) {
            $payload = $request->data();

            return $request->url() === 'http://127.0.0.1:5000/chat'
                && ($payload['emotion_response_context'] ?? null) === $accepted;
        });

        Http::fake([
            'http://127.0.0.1:5000/chat' => Http::response([
                'success' => true,
                'response' => 'Synthetic response',
                'ml_system' => 'hybrid_active',
            ], 200),
        ]);
        $method->invoke($controller, 'synthetic rejected cue', $user->id, 'none', false, null, [], 'pilot-trace-rejected', [], 'pilot-request-rejected', null);
        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'http://127.0.0.1:5000/chat'
                && !array_key_exists('emotion_response_context', $payload);
        });
    }
}
