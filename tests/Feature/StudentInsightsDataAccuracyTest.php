<?php

namespace Tests\Feature;

use App\Models\EmotionalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentInsightsDataAccuracyTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_student_insights_do_not_show_fabricated_baselines(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get(route('student.insights'));

        $response->assertOk()
            ->assertSee('Total Conversations')
            ->assertSee('Not enough data')
            ->assertSee('No emotional signals yet today.')
            ->assertSee('0 conversations');

        $this->assertSame(0, EmotionalEntry::where('user_id', $student->id)->count());
    }

    public function test_my_entries_reads_consolidated_emotional_entry_scores_and_isolates_ownership(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);

        EmotionalEntry::create([
            'user_id' => $student->id,
            'entry_text' => 'My private reflection',
            'detected_emotion' => 'neutral',
            'weighted_score' => 0.4,
            'ai_response' => 'A supportive response',
        ]);
        EmotionalEntry::create([
            'user_id' => $otherStudent->id,
            'entry_text' => 'Another student private reflection',
            'detected_emotion' => 'sadness',
            'weighted_score' => -0.4,
        ]);

        $response = $this->actingAs($student)->get(route('student.entries'));

        $response->assertOk()
            ->assertSee('My private reflection')
            ->assertSee('Score: 0.4')
            ->assertSee('<details class="ai-response-section">', false)
            ->assertSee('A supportive response')
            ->assertDontSee('Another student private reflection');
    }

    public function test_my_entries_archive_has_single_accessible_pagination_controls_per_position(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        foreach (range(1, 11) as $number) {
            EmotionalEntry::create([
                'user_id' => $student->id,
                'entry_text' => "Private saved entry {$number}",
                'detected_emotion' => 'neutral',
                'ai_response' => 'A supportive response.',
            ]);
        }

        $response = $this->actingAs($student)->get(route('student.entries', ['page' => 2]));
        $response->assertOk()
            ->assertSee('My Entries')
            ->assertSee('Your private history of saved reflections and MAGI conversations.')
            ->assertSee('aria-current="page" aria-label="Page 2"', false);

        $html = (string) $response->getContent();
        $this->assertSame(2, substr_count($html, 'aria-label="Saved entries pagination"'));
        $this->assertSame(2, substr_count($html, 'aria-label="Go to previous page"'));
        $this->assertSame(0, substr_count($html, 'aria-label="Go to next page"'));
        $this->assertSame(2, substr_count($html, 'aria-label="No next page"'));
        $this->assertSame(2, substr_count($html, 'class="pagination-wrapper"'));
        $this->assertStringNotContainsString('Â«', $html);
        $this->assertStringNotContainsString('Â»', $html);
    }

    public function test_my_entries_escapes_stored_ai_response_markup(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        EmotionalEntry::create([
            'user_id' => $student->id,
            'entry_text' => 'A private test entry',
            'detected_emotion' => 'neutral',
            'ai_response' => '<script>alert("stored markup")</script>',
        ]);

        $response = $this->actingAs($student)->get(route('student.entries'));

        $response->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;stored markup&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("stored markup")</script>', false);
    }
}
