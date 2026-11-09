<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Appointment;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class CrisisAppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected $student;
    protected $counselor;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a counselor
        $this->counselor = User::factory()->create([
            'role' => 'counselor',
            'is_active' => true
        ]);

        config()->set('privacy.crisis_safeguard_policy_version', 'synthetic-test-policy');
        config()->set('privacy.institutional_escalation.authorized_counselor_ids', [$this->counselor->id]);
        
        // Create a student
        $this->student = User::factory()->create([
            'role' => 'student',
            'email' => 'test-student-' . time() . '@test.com',
            'password' => bcrypt('password123')
        ]);

        app(PrivacyService::class)->updateSettings($this->student->id, [
            'allow_ai_recommendations' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);
    }

    /**
     * Test that crisis appointment is created even when non-crisis appointments exist
     */
    public function test_crisis_appointment_created_despite_existing_anxiety_appointment()
    {
        // Step 1: Create an existing 'anxiety' appointment for the student
        Appointment::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'status' => 'confirmed',
            'category' => 'anxiety',
            'reason' => 'Routine anxiety counseling',
            'appointment_date' => now()->addDay(),
            'preferred_date' => now()->addDay(),
        ]);

        // Verify the anxiety appointment exists
        $this->assertDatabaseHas('appointments', [
            'student_id' => $this->student->id,
            'category' => 'anxiety',
            'status' => 'confirmed'
        ]);

        // Verify NO crisis appointment exists yet
        $this->assertDatabaseMissing('appointments', [
            'student_id' => $this->student->id,
            'category' => 'crisis'
        ]);

        // Step 2: Send critical crisis message to AI
        $this->actingAs($this->student)
            ->postJson('/api/ai/chat', [
                'message' => 'I want to die.',
                'user_id' => $this->student->id,
                'session_id' => null
            ])
            ->assertStatus(200)
            ->assertJsonPath('crisis_analysis.level', 'critical');

        // Step 3: Verify crisis alert was created
        $this->assertDatabaseHas('crisis_alerts', [
            'student_id' => $this->student->id,
            'crisis_level' => 'critical',
            'is_resolved' => false
        ]);

        // Step 4: CRITICAL TEST - Verify crisis appointment WAS created
        // This should succeed because the fix made the check category-specific
        $this->assertDatabaseHas('appointments', [
            'student_id' => $this->student->id,
            'category' => 'crisis',
            'status' => 'pending',
            'created_from_crisis' => true
        ]);

        // Step 5: Verify we still have BOTH appointments
        $appointments = Appointment::where('student_id', $this->student->id)->get();
        $this->assertCount(2, $appointments, 'Should have both anxiety and crisis appointments');

        // Step 6: Verify counselor notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->counselor->id,
            'type' => 'crisis_alert',
            'priority' => 'urgent'
        ]);
    }

    /**
     * Test that duplicate crisis appointments are still prevented
     */
    public function test_duplicate_crisis_appointments_prevented()
    {
        // Step 1: Send first critical crisis message
        $this->actingAs($this->student)
            ->postJson('/api/ai/chat', [
                'message' => 'I want to die.',
                'user_id' => $this->student->id,
                'session_id' => null
            ])
            ->assertStatus(200)
            ->assertJsonPath('crisis_analysis.level', 'critical');

        // Verify first crisis appointment was created
        $crisis_appointments_1 = Appointment::where('student_id', $this->student->id)
            ->where('category', 'crisis')
            ->get();
        $this->assertCount(1, $crisis_appointments_1, 'First crisis appointment should be created');

        // Step 2: Send another critical crisis message
        $this->actingAs($this->student)
            ->postJson('/api/ai/chat', [
                'message' => 'I plan to hurt myself.',
                'user_id' => $this->student->id,
                'session_id' => null
            ])
            ->assertStatus(200)
            ->assertJsonPath('crisis_analysis.level', 'critical');

        // Verify duplicate crisis appointment was NOT created
        $crisis_appointments_2 = Appointment::where('student_id', $this->student->id)
            ->where('category', 'crisis')
            ->get();
        $this->assertCount(1, $crisis_appointments_2, 'Should prevent duplicate crisis appointment');
    }

    /**
     * Test the exact scenario from Issue 1: 
     * Student with existing anxiety appointments should still get crisis appointment
     */
    public function test_issue_1_scenario_crisis_appointment_despite_two_anxiety_appointments()
    {
        // Scenario: Student has 2 existing 'anxiety' appointments (like user_id=2 in test data)
        Appointment::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'status' => 'confirmed',
            'category' => 'anxiety',
            'reason' => 'Regular anxiety session',
            'appointment_date' => now()->addDay(),
            'preferred_date' => now()->addDay(),
            'created_from_crisis' => false
        ]);

        Appointment::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'status' => 'confirmed',
            'category' => 'anxiety',
            'reason' => 'Follow-up anxiety session',
            'appointment_date' => now()->addDays(3),
            'preferred_date' => now()->addDays(3),
            'created_from_crisis' => false
        ]);

        // Count before
        $appts_before = Appointment::where('student_id', $this->student->id)->count();
        $this->assertSame(2, $appts_before);

        // Send critical crisis message
        $this->actingAs($this->student)
            ->postJson('/api/ai/chat', [
                'message' => 'I have all the pills I need. Tonight is the night.',
                'user_id' => $this->student->id,
                'session_id' => null
            ])
            ->assertStatus(200)
            ->assertJsonPath('crisis_analysis.level', 'critical')
            ->assertJsonPath('crisis_analysis.plan_detected', true)
            ->assertJsonPath('crisis_analysis.method_detected', true);

        // Count after - should be 3 (2 anxiety + 1 crisis)
        $appts_after = Appointment::where('student_id', $this->student->id)->count();
        $this->assertSame(3, $appts_after, 'Should have created crisis appointment despite 2 existing anxiety appointments');

        // Verify the crisis appointment specifically
        $crisis_appt = Appointment::where('student_id', $this->student->id)
            ->where('category', 'crisis')
            ->first();
        
        $this->assertNotNull($crisis_appt, 'Crisis appointment must exist');
        $this->assertTrue($crisis_appt->created_from_crisis, 'Should be marked as created from crisis');
        $this->assertEquals('pending', $crisis_appt->status, 'Should be pending');
    }
}
