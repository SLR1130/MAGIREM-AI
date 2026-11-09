<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\EmotionalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentHeaderNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_student_pages_render_the_shared_header_without_the_legacy_sidebar(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        foreach ([
            'student.journal',
            'student.appointments',
            'student.coping-toolkit',
            'student.resources',
            'student.community',
        ] as $route) {
            $this->actingAs($student)->get(route($route))
                ->assertOk()
                ->assertSee('student-topbar', false)
                ->assertSee('@view-transition', false)
                ->assertSee('mindcare-card-in', false)
                ->assertDontSee('id="appSidebar"', false);
        }
    }

    public function test_insights_retains_the_legacy_student_sidebar(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('student.insights'))
            ->assertOk()
            ->assertSee('id="appSidebar"', false)
            ->assertDontSee('student-topbar', false);
    }

    public function test_ai_chat_uses_its_own_workspace_with_history_drawer_and_back_link(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('student.ai-chat'))
            ->assertOk()
            ->assertSee('chat-wrapper msc-chat', false)
            ->assertSee('ai-back-link', false)
            ->assertSee('Open conversation history')
            ->assertSee('MAGI')
            ->assertSee('Private reflection, not emergency support.')
            ->assertSee('@view-transition', false)
            ->assertSee('mindcare-card-out', false)
            ->assertDontSee('student-topbar', false)
            ->assertDontSee('id="appSidebar"', false);
    }

    public function test_student_home_uses_the_shared_header_without_a_sidebar(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('home'))
            ->assertOk()
            ->assertSee('student-topbar', false)
            ->assertDontSee('id="appSidebar"', false)
            ->assertDontSee('<nav class="home-nav"', false)
            ->assertSee('AI Companion');
    }
    public function test_header_uses_five_primary_links_and_an_explore_dropdown(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('student.appointments'))
            ->assertOk()
            ->assertSee('AI Companion')
            ->assertSee('Journal')
            ->assertSee('Appointments')
            ->assertSee('Community')
            ->assertSee('Explore')
            ->assertSee('Insights')
            ->assertSee('Coping Toolkit')
            ->assertSee('Wellness Resources')
            ->assertDontSee('data-secondary-nav', false)
            ->assertDontSee('>More ', false);
    }

    public function test_appointments_prioritizes_the_schedule_and_compact_empty_state(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('student.appointments'))
            ->assertOk()
            ->assertSee('Manage your counseling sessions in one place.')
            ->assertSee('Find Counselor')
            ->assertSee('Upcoming appointments')
            ->assertSee('No upcoming appointments')
            ->assertSee('Support information')
            ->assertDontSee('A counseling session can be one part of your wellness routine.');
    }
    public function test_appointments_separates_upcoming_sessions_from_history(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $counselor = User::factory()->create(['role' => 'counselor', 'name' => 'Counselor Rivera']);

        Appointment::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'appointment_date' => now()->addDays(3),
            'time_slot' => '10:00 AM',
            'category' => 'personal',
            'status' => 'confirmed',
        ]);
        Appointment::create([
            'student_id' => $student->id,
            'appointment_date' => now()->addDay(),
            'category' => 'academic',
            'status' => 'pending',
        ]);

        $this->actingAs($student)->get(route('student.appointments'))
            ->assertOk()
            ->assertSee('Counselor Rivera')
            ->assertSee('Confirmed sessions are shown here first.')
            ->assertSee('Appointment history')
            ->assertSee('Pending')
            ->assertSee('Counselor request');
    }
    public function test_journal_is_writing_first_and_preserves_existing_entry_contract(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('student.journal'))
            ->assertOk()
            ->assertSee('Your private space')
            ->assertSee('Put your thoughts into words. Every reflection starts with a moment.')
            ->assertSee('name="entry_text"', false)
            ->assertSee('name="entry_title"', false)
            ->assertSee('/student/store-entry', false)
            ->assertSee('data-reflection-prompt', false)
            ->assertSee('Free Write')
            ->assertSee('Guided Reflection')
            ->assertSee('Little Good Things')
            ->assertSee('data-journal-mood="happy"', false)
            ->assertSee('data-journal-next-prompt', false)
            ->assertSee('data-journal-edit', false)
            ->assertSee('Recent reflections')
            ->assertSee('data-journal-search', false)
            ->assertSee('data-journal-sort', false)
            ->assertDontSee('Total entries')
            ->assertDontSee('Emotion distribution');
    }
    public function test_journal_renders_real_entries_as_compact_reflection_cards(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        EmotionalEntry::create([
            'user_id' => $student->id,
            'entry_text' => "Finished a difficult reading today.\nI feel more settled now.",
            'detected_emotion' => 'calm',
            'confidence_score' => 82,
            'ai_response' => 'Supportive response',
            'conversation_context' => ['title' => 'Reading day'],
            'is_anonymous' => true,
        ]);

        $this->actingAs($student)->get(route('student.journal'))
            ->assertOk()
            ->assertSee('Reading day')
            ->assertSee('Calm')
            ->assertSee('Private entry');
    }
    public function test_student_can_update_an_owned_journal_entry_without_new_ai_analysis(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $entry = EmotionalEntry::create([
            'user_id' => $student->id,
            'entry_text' => 'Original reflection',
            'detected_emotion' => 'neutral',
            'confidence_score' => 70,
            'ai_response' => 'Existing response remains unchanged',
            'conversation_context' => ['title' => 'Before', 'mood_hint' => 'neutral'],
            'is_anonymous' => true,
        ]);

        $this->actingAs($student)
            ->put(route('student.journal.update', $entry), [
                'entry_text' => 'Updated reflection',
                'entry_title' => 'After',
                'mood_hint' => 'happy',
            ])
            ->assertRedirect(route('student.journal'));

        $entry->refresh();
        $this->assertSame('Updated reflection', $entry->entry_text);
        $this->assertSame('After', $entry->conversation_context['title']);
        $this->assertSame('happy', $entry->conversation_context['mood_hint']);
        $this->assertSame('Existing response remains unchanged', $entry->ai_response);
    }

    public function test_student_cannot_update_another_students_journal_entry(): void
    {
        $owner = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $entry = EmotionalEntry::create([
            'user_id' => $owner->id,
            'entry_text' => 'Private reflection',
            'detected_emotion' => 'neutral',
            'confidence_score' => 70,
            'is_anonymous' => true,
        ]);

        $this->actingAs($otherStudent)
            ->put(route('student.journal.update', $entry), ['entry_text' => 'Attempted access'])
            ->assertNotFound();

        $this->assertSame('Private reflection', $entry->fresh()->entry_text);
    }
}
