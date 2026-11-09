<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\CounselorMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AppointmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_confirmed_appointment_is_needs_review_without_automatic_mutation(): void
    {
        $counselor = User::factory()->create(['role' => 'counselor']);
        $student = User::factory()->create(['role' => 'student']);
        $appointment = $this->appointment($student, $counselor, now()->subHours(3));

        $this->actingAs($counselor)->get(route('counselor.appointments'))
            ->assertOk()->assertSee('Needs review');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
            'session_outcome' => null,
        ]);
        $this->assertDatabaseCount('appointment_status_histories', 0);
    }

    public function test_completion_requires_verified_evidence_and_records_history(): void
    {
        $counselor = User::factory()->create(['role' => 'counselor']);
        $student = User::factory()->create(['role' => 'student']);
        $appointment = $this->appointment($student, $counselor, now()->subHour());

        $this->actingAs($counselor)->post(route('counselor.appointments.complete', $appointment), [
            'attendance_evidence' => 'manual_verification',
        ])->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'confirmed']);

        $this->actingAs($counselor)->post(route('counselor.appointments.complete', $appointment), [
            'attendance_evidence' => 'manual_verification',
            'counselor_notes' => 'Verified through an authorized external attendance record.',
        ])->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
            'session_outcome' => 'completed',
            'attendance_evidence' => 'manual_verification',
        ]);
        $this->assertDatabaseHas('appointment_status_histories', [
            'appointment_id' => $appointment->id,
            'from_status' => 'confirmed',
            'to_status' => 'completed',
            'to_outcome' => 'completed',
        ]);
    }

    public function test_failure_outcome_requires_overdue_reason_and_keeps_status_history(): void
    {
        $counselor = User::factory()->create(['role' => 'counselor']);
        $student = User::factory()->create(['role' => 'student']);
        $appointment = $this->appointment($student, $counselor, now()->subHours(3));

        $this->actingAs($counselor)->post(route('counselor.appointments.outcome', $appointment), [
            'outcome' => 'student_no_show',
        ])->assertSessionHasErrors('reason');

        $this->actingAs($counselor)->post(route('counselor.appointments.outcome', $appointment), [
            'outcome' => 'student_no_show',
            'reason' => 'Counselor verified the scheduled window and attendance record.',
        ])->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
            'session_outcome' => 'student_no_show',
        ]);
        $this->assertDatabaseHas('appointment_status_histories', [
            'appointment_id' => $appointment->id,
            'to_outcome' => 'student_no_show',
        ]);
    }

    public function test_messaging_is_read_only_after_outcome_and_cross_account_is_denied(): void
    {
        $counselor = User::factory()->create(['role' => 'counselor']);
        $otherCounselor = User::factory()->create(['role' => 'counselor']);
        $student = User::factory()->create(['role' => 'student']);
        $appointment = $this->appointment($student, $counselor, now()->subHours(3), ['session_outcome' => 'needs_review', 'outcome_reason' => 'Synthetic review']);
        CounselorMessage::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'sender_id' => $counselor->id,
            'message' => 'Synthetic historical message',
        ]);

        $this->actingAs($student)->getJson(route('student.counselor.messages', $counselor->id))
            ->assertOk()->assertJsonPath('messages.0.message', 'Synthetic historical message');
        $this->actingAs($student)->postJson(route('student.send-message'), [
            'counselor_id' => $counselor->id,
            'message' => 'Should be blocked after outcome',
        ])->assertForbidden();
        $this->actingAs($otherCounselor)->getJson(route('counselor.api.student.messages', $student->id))
            ->assertForbidden();
    }

    public function test_overdue_unresolved_appointment_keeps_history_readable_but_denies_new_messages(): void
    {
        $counselor = User::factory()->create(['role' => 'counselor']);
        $student = User::factory()->create(['role' => 'student']);
        $this->appointment($student, $counselor, now()->subHours(3));
        CounselorMessage::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'sender_id' => $student->id,
            'message' => 'Synthetic historical message for overdue review.',
        ]);

        $this->actingAs($counselor)->get(route('counselor.chat', $student->id))
            ->assertOk()
            ->assertSee('read-only')
            ->assertDontSee('name="message"');

        $this->actingAs($counselor)->getJson(route('counselor.api.student.messages', $student->id))
            ->assertOk()
            ->assertJsonPath('messages.0.message', 'Synthetic historical message for overdue review.');

        $this->actingAs($counselor)->postJson(route('counselor.send-message', $student->id), [
            'message' => 'This must remain blocked after the grace period.',
        ])->assertForbidden();
    }
    public function test_video_call_is_limited_to_current_scheduled_window(): void
    {
        $counselor = User::factory()->create(['role' => 'counselor']);
        $student = User::factory()->create(['role' => 'student']);
        $appointment = $this->appointment($student, $counselor, now()->addDay());

        $this->actingAs($counselor)->postJson(route('video-calls.create'), [
            'callee_id' => $student->id,
        ])->assertForbidden();
        $this->assertDatabaseCount('video_calls', 0);

        $appointment->update(['appointment_date' => now()->subMinutes(5)]);
        $response = $this->actingAs($counselor)->postJson(route('video-calls.create'), [
            'callee_id' => $student->id,
        ])->assertCreated();
        $this->assertNotNull($response->json('call.appointment_id'));
        $this->assertDatabaseHas('video_calls', ['appointment_id' => $appointment->id]);
    }

    public function test_overdue_confirmed_appointment_cannot_start_video_call(): void
    {
        $counselor = User::factory()->create(['role' => 'counselor']);
        $student = User::factory()->create(['role' => 'student']);
        $this->appointment($student, $counselor, now()->subHours(3));

        $this->actingAs($counselor)->postJson(route('video-calls.create'), [
            'callee_id' => $student->id,
        ])->assertForbidden();
        $this->assertDatabaseCount('video_calls', 0);
    }
    private function appointment(User $student, User $counselor, Carbon $date, array $extra = []): Appointment
    {
        return Appointment::create(array_merge([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'appointment_date' => $date,
            'status' => 'confirmed',
        ], $extra));
    }
}