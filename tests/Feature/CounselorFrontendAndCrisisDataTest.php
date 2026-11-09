<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CounselorAlert;
use App\Models\CrisisAlert;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounselorFrontendAndCrisisDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_messages_workspace_has_no_legacy_student_id_panel(): void
    {
        [$counselor, $student] = $this->assignedStudent();

        $response = $this->actingAs($counselor)->get(route('counselor.messages'));

        $response->assertOk()
            ->assertSee('Messages')
            ->assertSee('Student #' . $student->student_id)
            ->assertDontSee('sidebarStudentId')
            ->assertDontSee('Student ID:');

        $this->actingAs($counselor)
            ->get(route('counselor.chat', $student->id))
            ->assertOk()
            ->assertSee('messageForm')
            ->assertSee('messagesArea');
    }

    public function test_unassigned_counselor_cannot_open_private_conversation(): void
    {
        $viewer = User::factory()->create(['role' => 'counselor']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($viewer)
            ->get(route('counselor.chat', $student->id))
            ->assertRedirect();
    }

    public function test_dashboard_renders_trusted_crisis_level_and_message_content(): void
    {
        [$counselor, $student] = $this->assignedStudent();

        CrisisAlert::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'crisis_level' => 'critical',
            'crisis_category' => 'synthetic_test',
            'message_content' => 'Synthetic crisis record for dashboard rendering.',
            'ai_response' => 'Synthetic safe response.',
            'is_resolved' => false,
        ]);

        $html = $this->actingAs($counselor)
            ->get(route('counselor.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('CRITICAL', $html);
        $this->assertStringContainsString('Synthetic crisis record for dashboard rendering.', $html);
        $this->assertStringNotContainsString('>Urgent<', $html);
        $this->assertSame(1, substr_count($html, 'data-alert-id='), 'Each crisis incident must render once in the dashboard DOM.');
    }

    public function test_dashboard_renders_distinct_incidents_once_for_the_same_student(): void
    {
        [$counselor, $student] = $this->assignedStudent();

        foreach (['incident-one', 'incident-two'] as $category) {
            CrisisAlert::create([
                'student_id' => $student->id,
                'counselor_id' => $counselor->id,
                'crisis_level' => 'critical',
                'crisis_category' => $category,
                'message_content' => 'Synthetic distinct incident ' . $category,
                'ai_response' => 'Synthetic safe response.',
                'is_resolved' => false,
            ]);
        }

        $html = $this->actingAs($counselor)
            ->get(route('counselor.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($html, 'data-alert-id='));
        $this->assertStringContainsString('Synthetic distinct incident incident-one', $html);
        $this->assertStringContainsString('Synthetic distinct incident incident-two', $html);
    }

    public function test_dashboard_excludes_alerts_for_non_student_records(): void
    {
        [$counselor, $student] = $this->assignedStudent();
        $nonStudent = User::factory()->create(['role' => 'counselor']);

        CrisisAlert::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'crisis_level' => 'critical',
            'crisis_category' => 'authorized_student',
            'message_content' => 'Authorized student incident.',
            'ai_response' => 'Synthetic safe response.',
            'is_resolved' => false,
        ]);
        CrisisAlert::create([
            'student_id' => $nonStudent->id,
            'counselor_id' => $counselor->id,
            'crisis_level' => 'critical',
            'crisis_category' => 'non_student_record',
            'message_content' => 'Should not be exposed on a student crisis dashboard.',
            'ai_response' => 'Synthetic safe response.',
            'is_resolved' => false,
        ]);

        $this->actingAs($counselor)
            ->get(route('counselor.dashboard'))
            ->assertOk()
            ->assertSee('Authorized student incident.')
            ->assertDontSee('Should not be exposed on a student crisis dashboard.');
    }

    public function test_monitoring_queue_excludes_another_counselors_consented_student(): void
    {
        [$counselor, $student] = $this->assignedStudent();
        $otherCounselor = User::factory()->create(['role' => 'counselor']);
        $otherStudent = User::factory()->create(['role' => 'student', 'student_id' => '00999']);
        Appointment::create([
            'student_id' => $otherStudent->id,
            'counselor_id' => $otherCounselor->id,
            'appointment_date' => now()->addDay(),
            'status' => 'confirmed',
        ]);
        app(PrivacyService::class)->updateSettings($otherStudent->id, [
            'share_with_counselor' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);
        CounselorAlert::create([
            'student_id' => $otherStudent->id,
            'alert_type' => 'wellbeing_concern',
            'severity' => 'high',
            'message' => 'Private alert for another counselor.',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->actingAs($counselor)
            ->get(route('counselor.mental-health-monitoring'))
            ->assertOk()
            ->assertDontSee('Private alert for another counselor.');
    }

    public function test_monitoring_queue_honors_explicit_alert_counselor_scope(): void
    {
        [$counselor, $student] = $this->assignedStudent();
        $otherCounselor = User::factory()->create(['role' => 'counselor']);
        Appointment::create([
            'student_id' => $student->id,
            'counselor_id' => $otherCounselor->id,
            'appointment_date' => now()->addDays(2),
            'status' => 'confirmed',
        ]);
        app(PrivacyService::class)->updateSettings($student->id, [
            'share_with_counselor' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);
        CounselorAlert::create([
            'student_id' => $student->id,
            'counselor_id' => $otherCounselor->id,
            'alert_type' => 'wellbeing_concern',
            'severity' => 'high',
            'message' => 'Explicitly assigned to another counselor.',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->actingAs($counselor)
            ->get(route('counselor.mental-health-monitoring'))
            ->assertOk()
            ->assertDontSee('Explicitly assigned to another counselor.');
    }

    public function test_monitoring_queue_is_authorized_and_empty_states_are_safe(): void
    {
        [$counselor, $student] = $this->assignedStudent();
        app(PrivacyService::class)->updateSettings($student->id, [
            'share_with_counselor' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        CounselorAlert::create([
            'student_id' => $student->id,
            'alert_type' => 'wellbeing_concern',
            'severity' => 'high',
            'message' => 'Synthetic non-clinical wellbeing indicator.',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->actingAs($counselor)
            ->get(route('counselor.mental-health-monitoring'))
            ->assertOk()
            ->assertSee('HIGH')
            ->assertSee('Synthetic non-clinical wellbeing indicator.')
            ->assertSee('Student #' . $student->student_id)
            ->assertDontSee('Depression Risk Score');
    }

    private function assignedStudent(): array
    {
        $counselor = User::factory()->create(['role' => 'counselor']);
        $student = User::factory()->create(['role' => 'student', 'student_id' => '00665']);

        Appointment::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'appointment_date' => now()->addDay(),
            'status' => 'confirmed',
        ]);

        return [$counselor, $student];
    }
}