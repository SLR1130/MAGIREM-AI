<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CounselorMessage;
use App\Models\CrisisAlert;
use App\Models\EmotionalEntry;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounselorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_dashboard_renders_operational_data_without_depression_risk_ui(): void
    {
        [$counselor, $student] = $this->assignedStudent();
        $this->grantCounselorSharing($student);

        EmotionalEntry::create([
            'user_id' => $student->id,
            'entry_text' => 'Synthetic dashboard entry.',
            'ai_response' => 'Synthetic supportive response.',
            'detected_emotion' => 'sadness',
            'confidence_score' => 80,
            'crisis_level' => 'critical',
            'needs_counselor' => true,
        ]);

        CrisisAlert::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'crisis_level' => 'critical',
            'crisis_category' => 'synthetic_test',
            'message_content' => 'Synthetic crisis alert.',
            'ai_response' => 'Synthetic safe response.',
            'is_resolved' => false,
        ]);

        CounselorMessage::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'sender_id' => $student->id,
            'message' => 'Synthetic unread counselor message.',
            'is_read' => false,
        ]);

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertOk()
            ->assertSee('Active crisis alerts')
            ->assertSee('Critical entries')
            ->assertSee('Unread messages')
            ->assertSee('Student #' . $student->student_id)
            ->assertDontSee('Depression Risk Score')
            ->assertDontSee('At-Risk Students');
    }

    public function test_empty_dashboard_renders_operational_empty_states(): void
    {
        $counselor = User::factory()->create(['role' => 'counselor']);

        $this->actingAs($counselor)
            ->get(route('counselor.dashboard'))
            ->assertOk()
            ->assertSee('No active crisis alerts')
            ->assertSee('No critical entries')
            ->assertSee('No entries needing attention');
    }

    public function test_dashboard_does_not_expose_another_counselors_alert_or_private_entry(): void
    {
        $viewer = User::factory()->create(['role' => 'counselor']);
        $assignedCounselor = User::factory()->create(['role' => 'counselor']);
        $student = User::factory()->create(['role' => 'student']);

        $this->grantCounselorSharing($student);
        Appointment::create([
            'student_id' => $student->id,
            'counselor_id' => $assignedCounselor->id,
            'appointment_date' => now()->addDay(),
            'status' => 'confirmed',
        ]);
        EmotionalEntry::create([
            'user_id' => $student->id,
            'entry_text' => 'Private dashboard entry for another counselor.',
            'ai_response' => 'Synthetic response.',
            'detected_emotion' => 'sadness',
            'confidence_score' => 70,
            'crisis_level' => 'critical',
            'needs_counselor' => true,
        ]);
        CrisisAlert::create([
            'student_id' => $student->id,
            'counselor_id' => $assignedCounselor->id,
            'crisis_level' => 'critical',
            'crisis_category' => 'synthetic_test',
            'message_content' => 'Private alert.',
            'ai_response' => 'Synthetic response.',
            'is_resolved' => false,
        ]);

        $this->actingAs($viewer)
            ->get(route('counselor.dashboard'))
            ->assertOk()
            ->assertDontSee('Student #' . $student->student_id)
            ->assertDontSee('Private dashboard entry for another counselor.');
    }

    private function assignedStudent(): array
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
