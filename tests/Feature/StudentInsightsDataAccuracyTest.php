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
            ->assertDontSee('Another student private reflection');
    }
}
