<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Notification;
use App\Models\User;
use App\Services\AppointmentLifecycleService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class StudentCounselingRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_requests_page_uses_central_lifecycle_states_and_scopes_records(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $counselor = User::factory()->create(['role' => 'counselor', 'name' => 'Synthetic Counselor']);

        $this->appointment($student, $counselor, 'pending', now()->addDays(2));
        $this->appointment($student, $counselor, 'confirmed', now()->addDay());
        $this->appointment($student, $counselor, 'confirmed', now()->subHours(3));
        $this->appointment($student, $counselor, 'completed', now()->subDays(2), [
            'session_outcome' => 'completed',
        ]);
        $this->appointment($student, $counselor, 'confirmed', now()->subDays(2), [
            'session_outcome' => 'student_no_show',
            'outcome_reason' => 'Synthetic authorized reason',
        ]);
        $this->appointment($student, $counselor, 'cancelled', now()->addDays(3));
        $this->appointment($otherStudent, $counselor, 'pending', now()->addDays(2), [
            'description' => 'Must not appear for another student.',
        ]);

        $response = $this->actingAs($student)->get(route('student.counseling-requests'));

        $response->assertOk()
            ->assertSee('Pending approval')
            ->assertSee('Confirmed')
            ->assertSee('Needs review')
            ->assertSee('Completed')
            ->assertSee('Not held')
            ->assertSee('Cancelled')
            ->assertDontSee('Must not appear for another student.');

        $response->assertSee('Messaging paused for review')
            ->assertSee('History is read-only')
            ->assertSee('Request another');
    }

    public function test_directory_request_populates_required_pending_appointment_date(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $counselor = User::factory()->create(['role' => 'counselor', 'is_active' => true, 'status' => 'active']);

        $this->actingAs($student)->post(route('student.counseling-request'), [
            'counselor_id' => $counselor->id,
            'category' => 'academic',
            'description' => 'Synthetic request without a preferred date.',
        ])->assertRedirect();

        $appointment = Appointment::where('student_id', $student->id)->latest('id')->first();
        $this->assertNotNull($appointment);
        $this->assertNotNull($appointment->appointment_date);
        $this->assertSame('pending', $appointment->status);
    }
    public function test_status_filter_returns_only_requested_display_state(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $counselor = User::factory()->create(['role' => 'counselor']);

        $this->appointment($student, $counselor, 'confirmed', now()->subHours(3));
        $this->appointment($student, $counselor, 'pending', now()->addDay());

        $response = $this->actingAs($student)->get(route('student.counseling-requests', ['status' => 'needs_review']));

        $response->assertOk()
            ->assertSee('Needs review')
            ->assertDontSee('Awaiting approval');
    }

    public function test_persisted_needs_review_outcome_remains_needs_review(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $counselor = User::factory()->create(['role' => 'counselor']);
        $this->appointment($student, $counselor, 'confirmed', now()->subDays(2), [
            'session_outcome' => 'needs_review',
            'outcome_reason' => 'Synthetic review hold',
        ]);

        $response = $this->actingAs($student)->get(route('student.counseling-requests'));

        $response->assertOk()->assertSee('Needs review')->assertSee('Messaging paused for review');
    }
    public function test_confirmation_notifies_only_owner_once_with_safe_schedule_details(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $counselor = User::factory()->create(['role' => 'counselor', 'name' => 'Maria Santos']);
        $appointment = $this->appointment($student, $counselor, 'pending', now()->addDay());

        $this->actingAs($counselor)->post(route('counselor.appointments.confirm', $appointment), [
            'appointment_date' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $studentQuery = Notification::where('user_id', $student->id)
            ->where('title', 'Appointment confirmed');

        $this->assertSame(1, $studentQuery->count());
        $notification = $studentQuery->first();
        $this->assertStringContainsString('Maria Santos', $notification->message);
        $this->assertStringNotContainsString('Synthetic private concern', $notification->message);
        $this->assertStringContainsString('appointment_id='.$appointment->id, $notification->link);

        $this->assertSame(0, Notification::where('user_id', $otherStudent->id)
            ->where('title', 'Appointment confirmed')->count());

        NotificationService::appointmentConfirmed($appointment->fresh(['student', 'counselor']));

        $this->assertSame(1, $studentQuery->count());
    }

    public function test_verified_failure_notifies_owner_once_without_reason_disclosure(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $counselor = User::factory()->create(['role' => 'counselor']);
        $appointment = $this->appointment($student, $counselor, 'confirmed', now()->subHours(3), [
            'session_outcome' => 'student_no_show',
            'outcome_reason' => 'Private internal verification note',
        ]);

        NotificationService::appointmentFailed($appointment->fresh(['student', 'counselor']));
        NotificationService::appointmentFailed($appointment->fresh(['student', 'counselor']));

        $query = Notification::where('user_id', $student->id)
            ->where('title', 'Appointment not held');

        $this->assertSame(1, $query->count());
        $this->assertStringNotContainsString('Private internal verification note', $query->first()->message);
        $this->assertSame(0, Notification::where('user_id', $otherStudent->id)
            ->where('title', 'Appointment not held')->count());
    }

    public function test_failed_transition_does_not_create_notification_when_transaction_rolls_back(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $counselor = User::factory()->create(['role' => 'counselor']);
        $appointment = $this->appointment($student, $counselor, 'pending', now()->addDay());
        $before = Notification::count();

        try {
            DB::transaction(function () use ($appointment): void {
                $appointment->update(['status' => 'confirmed']);
                throw new RuntimeException('Synthetic transition rollback');
            });
        } catch (RuntimeException) {
            // Expected synthetic rollback.
        }

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'pending',
        ]);
        $this->assertSame($before, Notification::count());
    }

    private function appointment(User $student, User $counselor, string $status, $date, array $extra = []): Appointment
    {
        return Appointment::create(array_merge([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'category' => 'academic',
            'description' => 'Synthetic appointment request.',
            'appointment_date' => $date,
            'preferred_date' => $date,
            'status' => $status,
        ], $extra));
    }
}