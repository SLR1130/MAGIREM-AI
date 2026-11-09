<?php

namespace Tests\Feature;

use App\Models\AIConversationSession;
use App\Models\CounselorMessage;
use App\Models\EmotionalEntry;
use App\Models\User;
use App\Models\WellbeingProgress;
use App\Services\EmotionScorerService;
use App\Services\EmotionDetectionService;
use App\Services\EnhancedCrisisAnalysisService;
use App\Services\MentalHealthMonitoringService;
use App\Services\MoodCheckInService;
use App\Services\PrivacyService;
use App\Services\WellbeingScoreCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WellbeingArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_wellbeing_records_use_one_row_per_user_and_date(): void
    {
        $previousNow = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::parse('2026-10-18 12:00:00'));

        try {
            $student = User::factory()->create(['role' => 'student']);

            WellbeingProgress::create([
                'user_id' => $student->id,
                'date' => '2026-10-17',
                'emotional_stability' => 60,
                'stress_level' => 40,
                'wellbeing_score' => 62,
            ]);
            WellbeingProgress::create([
                'user_id' => $student->id,
                'date' => '2026-10-18',
                'emotional_stability' => 70,
                'stress_level' => 30,
                'wellbeing_score' => 72,
            ]);

            $this->assertSame(2, WellbeingProgress::where('user_id', $student->id)->count());
            $this->assertSame(72, WellbeingProgress::getOrCreateToday($student->id)->wellbeing_score);

            Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00'));
            $this->assertSame(50, WellbeingProgress::getOrCreateToday($student->id)->wellbeing_score);
        } finally {
            Carbon::setTestNow($previousNow);
        }
    }

    public function test_monitoring_records_usage_without_recreating_competing_score_tables(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        WellbeingProgress::create([
            'user_id' => $student->id,
            'date' => '2026-10-18',
            'emotional_stability' => 44,
            'stress_level' => 56,
            'wellbeing_score' => 44,
        ]);

        $score = app(MentalHealthMonitoringService::class)->recordUsage(
            $student->id,
            'anxiety',
            'none',
            Carbon::parse('2026-10-18 10:15:00')
        );

        $this->assertSame(44, $score);
        $this->assertDatabaseHas('usage_patterns', [
            'student_id' => $student->id,
            'emotion_detected' => 'anxiety',
            'time_of_day' => 'morning',
        ]);
        $this->assertFalse(Schema::hasTable('mental_status_scores'));
        $this->assertFalse(Schema::hasTable('mental_health_trends'));
        $this->assertFalse(Schema::hasTable('emotional_scores'));
    }

    public function test_emotion_scores_are_consolidated_on_their_source_records(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $counselor = User::factory()->create(['role' => 'counselor']);
        $entry = EmotionalEntry::create([
            'user_id' => $student->id,
            'entry_text' => 'I feel hopeful today.',
            'is_anonymous' => false,
        ]);
        $message = CounselorMessage::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'sender_id' => $student->id,
            'message' => 'I feel hopeful today.',
        ]);

        $scorer = app(EmotionScorerService::class);
        $scorer->scoreJournalEntry($entry->id, $entry->entry_text, $student->id, [
            'emotion' => 'joy',
            'confidence' => 0.9,
        ]);
        $scorer->scoreChatMessage($message->id, $message->message, $student->id, [
            'emotion' => 'joy',
            'confidence' => 0.9,
        ]);

        $this->assertDatabaseHas('emotional_entries', [
            'id' => $entry->id,
            'detected_emotion' => 'joy',
            'original_score' => 3,
        ]);
        $this->assertDatabaseHas('counselor_messages', [
            'id' => $message->id,
            'detected_emotion' => 'joy',
            'original_score' => 3,
        ]);
    }

    public function test_mood_checkin_updates_the_authoritative_zero_to_one_hundred_score(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        app(MoodCheckInService::class)->recordCheckin($student->id, 'happy', 8, 'A good day');

        $progress = WellbeingProgress::where('user_id', $student->id)
            ->whereDate('date', today())
            ->firstOrFail();

        $this->assertGreaterThanOrEqual(0, $progress->wellbeing_score);
        $this->assertLessThanOrEqual(100, $progress->wellbeing_score);
        $this->assertDatabaseHas('mood_checkins', [
            'user_id' => $student->id,
            'notes' => 'A good day',
        ]);
    }

    public function test_student_cannot_write_to_another_students_ai_session(): void
    {
        $owner = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $session = AIConversationSession::create([
            'user_id' => $owner->id,
            'title' => 'Private session',
            'last_message_at' => now(),
        ]);

        $this->actingAs($otherStudent)
            ->postJson('/api/ai/chat', [
                'message' => 'This must not enter the other student session.',
                'session_id' => $session->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('emotional_entries', [
            'session_id' => $session->id,
            'user_id' => $otherStudent->id,
        ]);
    }

    public function test_optional_analytics_failures_do_not_discard_a_persisted_safe_response(): void
    {
        User::factory()->create(['role' => 'counselor', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student']);
        app(PrivacyService::class)->updateSettings($student->id, [
            'allow_ai_recommendations' => true,
            'share_analytics' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        $this->mock(EmotionDetectionService::class, function ($mock) {
            $mock->shouldReceive('detectEmotion')->once()->andReturn([
                'emotion' => 'sadness',
                'confidence' => 0.95,
                'intensity' => 1.0,
            ]);
        });
        $this->mock(EnhancedCrisisAnalysisService::class, function ($mock) {
            $mock->shouldReceive('analyzeMessage')->once()->andReturn([
                'crisis_level' => 'critical',
                'crisis_confidence' => 0.95,
                'intent_detected' => true,
                'plan_detected' => true,
                'method_detected' => true,
                'access_detected' => true,
                'temporal_urgency' => 1.0,
                'recurrence_detected' => false,
                'sarcasm_detected' => false,
                'satire_detected' => false,
                'hyperbole_detected' => false,
            ]);
        });
        $this->mock(WellbeingScoreCalculator::class, function ($mock) {
            $mock->shouldReceive('calculateWellbeing')->once()
                ->andThrow(new \RuntimeException('synthetic wellbeing analytics failure'));
        });
        $this->mock(MentalHealthMonitoringService::class, function ($mock) {
            $mock->shouldReceive('recordUsage')->once()
                ->andThrow(new \RuntimeException('synthetic usage analytics failure'));
        });

        $response = $this->actingAs($student)
            ->postJson('/api/ai/chat', [
                'message' => 'I will kill myself with pills right now.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('crisis_analysis.level', 'critical')
            ->assertJsonPath('wellbeing.wellbeing_score', 50);
        $this->assertSame(1, EmotionalEntry::where('user_id', $student->id)->count());
    }
}
