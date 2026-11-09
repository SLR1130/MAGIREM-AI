<?php

namespace Tests\Feature;

use App\Models\AIConversationSession;
use App\Models\EmotionalEntry;
use App\Models\PrivacySetting;
use App\Models\Resource;
use App\Models\User;
use App\Services\MagiResourceRecommendationService;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MagiResourceRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.mindcare_ml.inference_bridge.url', 'http://127.0.0.1:5000');
        config()->set('services.mindcare_ml.inference_bridge.secret', str_repeat('x', 40));
        Http::preventStrayRequests();
    }

    private function studentWithMessage(string $message): User
    {
        $student = User::factory()->create(['role' => 'student']);
        PrivacySetting::getOrCreateForUser($student->id)->forceFill([
            'allow_ai_recommendations' => true,
            'ai_recommendations_consented_at' => now(),
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ])->save();
        $session = AIConversationSession::create(['user_id' => $student->id, 'title' => 'Synthetic', 'last_message_at' => now()]);
        EmotionalEntry::create(['user_id' => $student->id, 'session_id' => $session->id, 'entry_text' => $message, 'detected_emotion' => 'neutral']);
        return $student;
    }

    private function resource(string $title): Resource
    {
        return Resource::create(['title' => $title, 'description' => 'A practical guide', 'category' => 'general', 'content' => 'Guide content', 'is_active' => true]);
    }

    private function modelResult(int $id, string $topic, string $evidence): array
    {
        return ['success' => true, 'recommendations' => [
            'introduction' => 'Here are some resources you might find useful.',
            'items' => [['resource_id' => $id, 'topic' => $topic, 'evidence' => $evidence, 'reason' => 'This guide offers practical ideas related to your topic.']],
        ]];
    }

    public function test_consent_scoped_academic_and_relationship_results_are_catalog_validated_and_cached_per_student(): void
    {
        $academic = $this->studentWithMessage('I am finding exam deadlines difficult to manage.');
        $relationship = $this->studentWithMessage('I am having a disagreement with my friend.');
        $studyResource = $this->resource('Managing academic deadlines');
        $relationshipResource = $this->resource('Communication with friends');
        $inactive = $this->resource('Inactive guide');
        $inactive->update(['is_active' => false]);
        $hidden = AIConversationSession::create(['user_id' => $academic->id, 'title' => 'Hidden', 'is_hidden' => true]);
        EmotionalEntry::create(['user_id' => $academic->id, 'session_id' => $hidden->id, 'entry_text' => 'Hidden private message', 'detected_emotion' => 'neutral']);
        EmotionalEntry::create(['user_id' => $academic->id, 'entry_text' => 'Private journal only', 'detected_emotion' => 'neutral']);

        Http::fake(['*/internal/magi-resource-recommendations' => function (Request $request) use ($studyResource, $relationshipResource) {
            $messages = $request->data()['messages'];
            $isStudy = str_contains($messages[0], 'exam deadlines');
            return Http::response($isStudy
                ? $this->modelResult($studyResource->id, 'Academic planning', 'exam deadlines')
                : $this->modelResult($relationshipResource->id, 'Friendship', 'my friend'));
        }]);

        $first = $this->actingAs($academic)->postJson(route('student.insights.recommendations'), ['consent_to_history_use' => true])->assertOk()
            ->assertJsonPath('state', 'ready')->assertJsonPath('topics.0', 'Academic planning')
            ->assertJsonPath('items.0.title', 'Managing academic deadlines')
            ->assertJsonPath('items.0.url', route('student.resources.show', $studyResource->id));
        $first->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->postJson(route('student.insights.recommendations'), ['consent_to_history_use' => true])->assertOk()->assertJsonPath('items.0.title', $studyResource->title);
        Http::assertSentCount(1);

        $this->actingAs($relationship)->postJson(route('student.insights.recommendations'), ['consent_to_history_use' => true])->assertOk()
            ->assertJsonPath('topics.0', 'Friendship')->assertJsonPath('items.0.title', $relationshipResource->title)
            ->assertJsonMissing(['title' => $studyResource->title]);
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) use ($inactive) {
            $data = $request->data();
            return str_contains($data['messages'][0], 'exam deadlines')
                && count($data['messages']) === 1
                && !str_contains(json_encode($data), 'Private journal only')
                && !str_contains(json_encode($data), 'Hidden private message')
                && !in_array($inactive->id, array_column($data['resources'], 'id'), true);
        });
        $rawCache = Cache::get(MagiResourceRecommendationService::cacheKey($academic->id));
        $this->assertIsString($rawCache);
        $this->assertStringNotContainsString('exam deadlines', $rawCache);
        $this->assertNotEmpty(Crypt::decryptString($rawCache));
    }

    public function test_empty_and_revoked_consent_never_invoke_the_model_or_expose_cached_output(): void
    {
        $student = $this->studentWithMessage('The exam deadlines are difficult.');
        $resource = $this->resource('Study planning');
        Http::fake(['*/internal/magi-resource-recommendations' => Http::response($this->modelResult($resource->id, 'Study', 'exam deadlines'))]);
        $new = User::factory()->create(['role' => 'student']);
        $this->actingAs($new)->postJson(route('student.insights.recommendations'), ['consent_to_history_use' => true])->assertJsonPath('state', 'consent_required');
        PrivacySetting::getOrCreateForUser($new->id)->forceFill([
            'allow_ai_recommendations' => true, 'ai_recommendations_consented_at' => now(),
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ])->save();
        $this->postJson(route('student.insights.recommendations'), ['consent_to_history_use' => true])->assertJsonPath('state', 'getting_to_know_you');
        Http::assertNothingSent();

        $this->actingAs($student)->postJson(route('student.insights.recommendations'), ['consent_to_history_use' => true])->assertJsonPath('state', 'ready');
        $this->assertTrue(Cache::has(MagiResourceRecommendationService::cacheKey($student->id)));
        app(PrivacyService::class)->updateSettings($student->id, [
            'allow_ai_recommendations' => false, 'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);
        $this->assertFalse(Cache::has(MagiResourceRecommendationService::cacheKey($student->id)));
        $this->postJson(route('student.insights.recommendations'), ['consent_to_history_use' => true])->assertJsonPath('state', 'consent_required')
            ->assertJsonMissing(['title' => $resource->title]);
        Http::assertSentCount(1);
    }

    public function test_invented_ids_ungrounded_topics_and_unavailable_model_fail_closed(): void
    {
        $student = $this->studentWithMessage('I have a lot of exam deadlines.');
        $resource = $this->resource('Study planning');
        Http::fakeSequence()
            ->push($this->modelResult(999999, 'Study', 'exam deadlines'))
            ->push($this->modelResult($resource->id, 'Study', 'not in the message'))
            ->push(['error' => 'unavailable'], 503);
        $this->actingAs($student)->postJson(route('student.insights.recommendations'), ['consent_to_history_use' => true])->assertJsonPath('state', 'unavailable')
            ->assertJsonMissing(['title' => $resource->title]);
        $this->postJson(route('student.insights.recommendations'), ['refresh' => true, 'consent_to_history_use' => true])->assertJsonPath('state', 'unavailable');
        $this->postJson(route('student.insights.recommendations'), ['refresh' => true, 'consent_to_history_use' => true])->assertJsonPath('state', 'unavailable');
        Http::assertSentCount(3);
    }

    public function test_revocation_during_inference_discards_the_result(): void
    {
        $student = $this->studentWithMessage('I have many exam deadlines.');
        $resource = $this->resource('Study planning');
        Http::fake(['*/internal/magi-resource-recommendations' => function () use ($student, $resource) {
            PrivacySetting::where('user_id', $student->id)->update(['allow_ai_recommendations' => false]);
            return Http::response($this->modelResult($resource->id, 'Study', 'exam deadlines'));
        }]);
        $this->actingAs($student)->postJson(route('student.insights.recommendations'), ['consent_to_history_use' => true])->assertJsonPath('state', 'consent_required')
            ->assertJsonMissing(['title' => $resource->title]);
        $this->assertFalse(Cache::has(MagiResourceRecommendationService::cacheKey($student->id)));
    }

    public function test_resource_unpublished_during_inference_is_not_returned(): void
    {
        $student = $this->studentWithMessage('I have many exam deadlines.');
        $resource = $this->resource('Study planning');
        Http::fake(['*/internal/magi-resource-recommendations' => function () use ($resource) {
            $resource->update(['is_active' => false]);
            return Http::response($this->modelResult($resource->id, 'Study', 'exam deadlines'));
        }]);
        $this->actingAs($student)->postJson(route('student.insights.recommendations'), ['consent_to_history_use' => true])->assertJsonPath('state', 'unavailable')
            ->assertJsonMissing(['title' => $resource->title]);
    }

    public function test_guests_and_non_students_cannot_request_recommendations(): void
    {
        Http::fake();
        $this->postJson(route('student.insights.recommendations'))->assertUnauthorized();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->postJson(route('student.insights.recommendations'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_history_is_not_analyzed_without_the_separate_student_action(): void
    {
        Http::fake();
        $student = $this->studentWithMessage('I am struggling with exam deadlines.');
        $this->resource('Study planning');
        $this->actingAs($student)->postJson(route('student.insights.recommendations'))
            ->assertUnprocessable()->assertJsonValidationErrors('consent_to_history_use');
        Http::assertNothingSent();
    }

    public function test_consent_can_be_revoked_even_if_cache_is_unavailable(): void
    {
        $student = $this->studentWithMessage('I am struggling with exam deadlines.');
        Cache::shouldReceive('forget')->once()->andThrow(new \RuntimeException('Synthetic cache failure'));
        $settings = app(PrivacyService::class)->updateSettings($student->id, [
            'allow_ai_recommendations' => false,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);
        $this->assertFalse($settings->canUseAI());
    }

    public function test_live_bridge_with_synthetic_student_and_catalog_when_explicitly_enabled(): void
    {
        if (getenv('MAGI_RECOMMENDATIONS_LIVE_SYNTHETIC') !== '1') {
            $this->markTestSkipped('Optional local synthetic MAGI inference test.');
        }

        // Only isolated SQLite records are created. Read the local bridge
        // secret without printing it or modifying the application's .env.
        $this->assertSame('sqlite', \DB::connection()->getDriverName());
        $this->assertSame(':memory:', \DB::connection()->getDatabaseName());
        $environment = \Dotenv\Dotenv::parse(file_get_contents(base_path('.env')));
        config()->set('services.mindcare_ml.inference_bridge.secret', $environment['MINDCARE_INFERENCE_BRIDGE_SECRET'] ?? '');
        Http::preventStrayRequests(false);
        $student = $this->studentWithMessage('I am struggling with exam deadlines this week.');
        $resource = $this->resource('Study planning for exam deadlines');
        $resource->update(['description' => 'Practical steps for planning academic work and meeting deadlines.']);

        $this->actingAs($student)->postJson(route('student.insights.recommendations'), ['consent_to_history_use' => true])
            ->assertOk()
            ->assertJsonPath('state', 'ready')
            ->assertJsonPath('items.0.title', $resource->title)
            ->assertJsonPath('items.0.url', route('student.resources.show', $resource->id));

        // Exercise the existing chat transport with synthetic text too. The
        // long-term-memory authority is isolated from live data for this test.
        $health = Http::get('http://127.0.0.1:5000/health')->json();
        $this->assertFalse((bool) ($health['persistent_memory_enabled'] ?? true));
        config()->set('services.mindcare_ml.phase4a.enabled', filter_var($environment['MINDCARE_PHASE4A_AUTH_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN));
        config()->set('services.mindcare_ml.phase4a.secret', $environment['MINDCARE_INTERNAL_HMAC_SECRET'] ?? '');
        foreach (['key_id' => 'KEY_ID', 'issuer' => 'ISSUER', 'audience' => 'AUDIENCE'] as $key => $suffix) {
            if (!empty($environment['MINDCARE_INTERNAL_HMAC_' . $suffix])) {
                config()->set('services.mindcare_ml.phase4a.' . $key, $environment['MINDCARE_INTERNAL_HMAC_' . $suffix]);
            }
        }
        config()->set('services.mindcare_ml.phase4b.consent_db', ':memory:');
        $chat = app(\App\Services\InternalMemoryAuthClient::class)->post('memory.chat', $student->id, [
            'user_id' => $student->id,
            'message' => 'Please give me one short study planning tip.',
            'conversation_history' => [],
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
        ], false);
        $this->assertNotEmpty($chat['response'] ?? null);
    }

}
