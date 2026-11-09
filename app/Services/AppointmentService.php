<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AppointmentService
{
    /**
     * Create appointment request
     */
    public function createAppointmentRequest($studentId, $appointmentDate, $notes = null)
    {
        $appointment = Appointment::create([
            'student_id' => $studentId,
            'appointment_date' => $appointmentDate,
            'status' => 'pending',
            'notes' => $notes
        ]);

        Log::info("Appointment request created for student {$studentId}");

        return $appointment;
    }

    /**
     * Assign counselor to appointment
     */
    public function assignCounselor($appointmentId, $counselorId)
    {
        $appointment = Appointment::find($appointmentId);
        $appointment->update([
            'counselor_id' => $counselorId,
            'status' => 'confirmed'
        ]);

        Log::info("Counselor {$counselorId} assigned to appointment {$appointmentId}");

        return $appointment;
    }

    /**
     * Get available counselors
     */
    public function getAvailableCounselors()
    {
        return User::where('role', 'counselor')
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get counselor's appointments
     */
    public function getCounselorAppointments($counselorId)
    {
        return Appointment::where('counselor_id', $counselorId)
            ->orderBy('appointment_date', 'asc')
            ->get();
    }

    /**
     * Get student's appointments
     */
    public function getStudentAppointments($studentId)
    {
        return Appointment::where('student_id', $studentId)
            ->orderBy('appointment_date', 'desc')
            ->get();
    }

    /**
     * Complete appointment
     */
    public function completeAppointment($appointmentId, $counselorNotes = null, $attendanceEvidence = null, $actorId = null)
    {
        if (!$actorId) {
            throw new \InvalidArgumentException('Verified actor and attendance evidence are required to complete an appointment.');
        }

        return app(AppointmentLifecycleService::class)->recordOutcome(
            Appointment::findOrFail($appointmentId),
            'completed',
            $counselorNotes,
            $attendanceEvidence,
            (int) $actorId
        );
    }
    /**
     * Cancel appointment
     */
    public function cancelAppointment($appointmentId)
    {
        $appointment = Appointment::find($appointmentId);
        $appointment->update(['status' => 'cancelled']);

        Log::info("Appointment {$appointmentId} cancelled");

        return $appointment;
    }

    /**
     * Get pending appointments
     */
    public function getPendingAppointments()
    {
        return Appointment::where('status', 'pending')
            ->orderBy('appointment_date', 'asc')
            ->get();
    }
}
