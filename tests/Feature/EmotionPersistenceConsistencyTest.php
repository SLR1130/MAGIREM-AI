<?php

namespace Tests\Feature;

use App\Models\AIConversationSession;
use App\Models\EmotionalEntry;
use App\Models\User;
use App\Services\AuthoritativeEmotionService;
use App\Services\ChatHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmotionPersistenceConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_routed_emotion_is_persisted_and_returned_by_history(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $session = AIConversationSession::create([
            'user_id' => $student->id,
            'title' => 'Emotion consistency',
            'last_message_at' => now(),
        ]);

        $final = app(AuthoritativeEmotionService::class)->resolve(
            ['emotion' => 'calm', 'confidence' => 0.43, 'intensity' => 0.2],
            [
                'emotion' => 'neutral',
                'analysis' => [
                    'emotion' => 'neutral',
                    'confidence' => 0.60,
                    'method' => 'semantic_fallback',
                    'crisis_level' => 'none',
                ],
            ]
        );

        $entry = app(ChatHistoryService::class)->storeMessage(
            $student->id,
            'Synthetic low-confidence routing example.',
            'Synthetic response.',
            array_merge($final, ['crisis_level' => 'none']),
            $session->id
        );

        $this->assertSame('neutral', $entry->detected_emotion);
        $this->assertSame('neutral', $entry->detailed_emotion_key);
        $this->assertSame('neutral', $entry->emotion_category_key);
        $this->assertSame('60.00', $entry->confidence_score);
        $this->assertSame(1, EmotionalEntry::where('session_id', $session->id)->count());

        $this->actingAs($student)
            ->getJson("/api/ai/sessions/{$session->id}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.emotion', 'neutral')
            ->assertJsonPath('messages.0.confidence', 60);
    }
}
