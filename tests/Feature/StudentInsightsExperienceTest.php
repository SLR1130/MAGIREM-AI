<?php

namespace Tests\Feature;

use App\Models\AIConversationSession;
use App\Models\EmotionalEntry;
use App\Models\PrivacySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentInsightsExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_and_observations_are_scoped_to_the_student_and_hidden_sessions_are_excluded(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $visible = AIConversationSession::create(['user_id' => $student->id, 'title' => 'My study session', 'last_message_at' => now()]);
        $hidden = AIConversationSession::create(['user_id' => $student->id, 'title' => 'Hidden session', 'last_message_at' => now(), 'is_hidden' => true]);
        $foreign = AIConversationSession::create(['user_id' => $other->id, 'title' => 'Other student session', 'last_message_at' => now()]);
        EmotionalEntry::create(['user_id' => $student->id, 'session_id' => $visible->id, 'entry_text' => 'Studying for an exam', 'detected_emotion' => 'joy']);
        EmotionalEntry::create(['user_id' => $student->id, 'session_id' => $hidden->id, 'entry_text' => 'Hidden message', 'detected_emotion' => 'neutral']);
        EmotionalEntry::create(['user_id' => $other->id, 'session_id' => $foreign->id, 'entry_text' => 'Other private message', 'detected_emotion' => 'anger']);

        $this->actingAs($student)->get(route('student.insights'))->assertOk()
            ->assertSee('My study session')
            ->assertSee('Studying for an exam')
            ->assertSee(route('student.ai-chat', ['session' => $visible->id]), false)
            ->assertDontSee('Hidden session')
            ->assertDontSee('Other student session')
            ->assertDontSee('Other private message')
            ->assertSee('data-trend-period="month"', false);
    }

    public function test_home_and_insights_share_truthful_consent_and_ready_states(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('home'))->assertOk()
            ->assertSee('MAGI is getting to know you.')
            ->assertSee('Personalized recommendations require your current permission.');
        $this->get(route('student.insights'))->assertOk()
            ->assertSee('Personalized recommendations require your current permission.');

        PrivacySetting::getOrCreateForUser($student->id)->forceFill([
            'allow_ai_recommendations' => true,
            'ai_recommendations_consented_at' => now(),
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ])->save();
        $session = AIConversationSession::create(['user_id' => $student->id, 'title' => 'A session', 'last_message_at' => now()]);
        EmotionalEntry::create(['user_id' => $student->id, 'session_id' => $session->id, 'entry_text' => 'Synthetic exam pressure example.', 'detected_emotion' => 'anxiety']);

        $this->get(route('home'))->assertOk()->assertSee('Ready when you are')
            ->assertSee('Find resources from my chats')->assertSee('Journal-only entries are not used.');
        $this->get(route('student.insights'))->assertOk()->assertSee('Ready when you are')
            ->assertSee('Find resources from my chats')->assertSee('Journal-only entries are not used.');
    }

    public function test_trend_observations_are_safely_serialized_for_javascript(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        EmotionalEntry::create([
            'user_id' => $student->id,
            'entry_text' => 'A synthetic example',
            'detected_emotion' => '</script><script>alert(1)</script>',
        ]);

        $this->actingAs($student)->get(route('student.insights'))->assertOk()
            ->assertDontSee('</script><script>alert(1)</script>', false);
    }
}
