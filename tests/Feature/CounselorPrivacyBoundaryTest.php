<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CounselorAlert;
use App\Models\EmotionalEntry;
use App\Models\User;
use App\Models\WellbeingProgress;
use App\Services\PatternDetectorService;
use App\Services\PrivacyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounselorPrivacyBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_profile_requires_assignment_and_current_counselor_sharing_consent(): void
    {
        [$counselor, $student] = $this->usersWithConfirmedAppointment();

        $this->actingAs($counselor)
            ->get(route('counselor.student-profile', $student->id))
            ->assertForbidden();

        $this->grantCounselorSharing($student);

        $this->actingAs($counselor)
            ->get(route('counselor.student-profile', $student->id))
            ->assertOk();
    }

    public function test_counselor_message_api_rejects_unassigned_student(): void
    {
        $counselor = User::factory()->create(['role' => 'counselor']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($counselor)
            ->getJson(route('counselor.api.student.messages', $student->id))
            ->assertForbidden();
    }

    public function test_counselor_student_directory_only_returns_confirmed_assignments(): void
    {
        [$counselor, $assigned] = $this->usersWithConfirmedAppointment();
        $unassigned = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($counselor)
            ->getJson(route('counselor.api.students'))
            ->assertOk();

        $ids = collect($response->json('students'))->pluck('id');
        $this->assertTrue($ids->contains($assigned->id));
        $this->assertFalse($ids->contains($unassigned->id));
    }

    public function test_raw_training_endpoint_is_forbidden_and_does_not_export_records(): void
    {
        $counselor = User::factory()->create(['role' => 'counselor']);
        $student = User::factory()->create(['role' => 'student']);
        EmotionalEntry::create([
            'user_id' => $student->id,
            'entry_text' => 'synthetic private record',
            'ai_response' => 'synthetic response',
            'detected_emotion' => 'neutral',
            'confidence_score' => 50,
            'crisis_level' => 'none',
        ]);

        $this->actingAs($counselor)
            ->postJson(route('counselor.batch-retrain-ml'))
            ->assertForbidden();

        $this->assertDatabaseHas('emotional_entries', [
            'user_id' => $student->id,
            'entry_text' => 'synthetic private record',
        ]);
    }

    public function test_wellbeing_pattern_alerts_require_current_counselor_sharing_consent(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $today = Carbon::today();

        foreach ([2, 1, 0] as $daysAgo) {
            WellbeingProgress::create([
                'user_id' => $student->id,
                'date' => $today->copy()->subDays($daysAgo),
                'emotional_stability' => 30,
                'stress_level' => 70,
                'wellbeing_score' => 30,
            ]);
        }

        $service = app(PatternDetectorService::class);
        $service->analyzeStudentPatterns($student->id, $today);
        $this->assertSame(0, CounselorAlert::where('student_id', $student->id)->count());

        $this->grantCounselorSharing($student);
        $this->assertTrue(app(PrivacyService::class)->canShareWithCounselor($student->id));
        $patterns = $service->analyzeStudentPatterns($student->id, $today);
        $this->assertNotNull($patterns['consecutive_days'], json_encode($patterns));
        $this->assertSame(1, CounselorAlert::where('student_id', $student->id)->count());
    }

    private function usersWithConfirmedAppointment(): array
    {
        $counselor = User::factory()->create(['role' => 'counselor']);
        $student = User::factory()->create(['role' => 'student']);

        Appointment::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'appointment_date' => now()->addDay(),
            'status' => 'confirmed',
        ]);

        return [$counselor, $student];
    }

    private function grantCounselorSharing(User $student): void
    {
        app(PrivacyService::class)->updateSettings($student->id, [
            'share_with_counselor' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);
    }
}
