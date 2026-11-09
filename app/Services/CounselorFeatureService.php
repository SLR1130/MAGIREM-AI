<?php

namespace App\Services;

use App\Models\User;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CounselorFeatureService
{
    /**
     * Get all available counselors
     */
    public function getAvailableCounselors()
    {
        return User::where('role', 'counselor')
            ->select('id', 'name', 'email')
            ->get();
    }

    /**
     * Submit a counseling request
     */
    public function submitCounselingRequest(array $data)
    {
        return Appointment::create([
            'student_id' => Auth::id(),
            'counselor_id' => $data['counselor_id'],
            'category' => $data['category'],
            'description' => $data['description'],
            'preferred_date' => $data['preferred_date'] ?? null,
            'appointment_date' => $data['preferred_date'] ?? now()->addDays(3),
            'status' => 'pending',
        ]);
    }

    /**
     * Get student's counseling requests
     */
    public function getStudentRequests($studentId = null)
    {
        $studentId = $studentId ?? Auth::id();
        
        return Appointment::where('student_id', $studentId)
            ->with('counselor')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get student's confirmed appointments
     */
    public function getStudentAppointments($studentId = null)
    {
        $studentId = $studentId ?? Auth::id();
        
        return Appointment::where('student_id', $studentId)
            ->where('status', 'confirmed')
            ->with('counselor')
            ->orderBy('appointment_date', 'asc')
            ->get();
    }

    /**
     * Schedule an appointment
     */
    public function scheduleAppointment($appointmentId, array $data)
    {
        $appointment = Appointment::findOrFail($appointmentId);
        
        if ($appointment->student_id !== Auth::id() || !in_array($appointment->status, ['pending', 'confirmed'], true) || $appointment->session_outcome !== null) {
            throw new \Exception('Unauthorized');
        }
        $oldStatus = $appointment->status;
        $appointment->update([
            'appointment_date' => $data['appointment_date'],
            'time_slot' => $data['time_slot'],
            'status' => 'confirmed',
        ]);
        AppointmentStatusHistory::create([
            'appointment_id' => $appointment->id, 'from_status' => $oldStatus, 'to_status' => 'confirmed', 'changed_by' => Auth::id(),
        ]);

        return $appointment;
    }

    /**
     * Get pending requests count
     */
    public function getPendingRequestsCount($studentId = null)
    {
        $studentId = $studentId ?? Auth::id();
        
        return Appointment::where('student_id', $studentId)
            ->where('status', 'pending')
            ->count();
    }

    /**
     * Get confirmed appointments count
     */
    public function getConfirmedAppointmentsCount($studentId = null)
    {
        $studentId = $studentId ?? Auth::id();
        
        return Appointment::where('student_id', $studentId)
            ->where('status', 'confirmed')
            ->count();
    }

    /**
     * Get total requests count
     */
    public function getTotalRequestsCount($studentId = null)
    {
        $studentId = $studentId ?? Auth::id();
        
        return Appointment::where('student_id', $studentId)->count();
    }

    /**
     * Cancel an appointment
     */
    public function cancelAppointment($appointmentId)
    {
        $appointment = Appointment::findOrFail($appointmentId);
        
        if ($appointment->student_id !== Auth::id()) {
            throw new \Exception('Unauthorized');
        }

        $oldStatus = $appointment->status;
        if (!in_array($appointment->status, ['pending', 'confirmed'], true) || $appointment->session_outcome !== null) {
            throw new \Exception('This appointment is no longer cancellable.');
        }

        $appointment->update(['status' => 'cancelled']);
        AppointmentStatusHistory::create([
            'appointment_id' => $appointment->id, 'from_status' => $oldStatus, 'to_status' => 'cancelled', 'changed_by' => Auth::id(),
        ]);
        return $appointment;
    }

    /**
     * Reschedule an appointment
     */
    public function rescheduleAppointment($appointmentId, array $data)
    {
        $result = DB::transaction(function () use ($appointmentId, $data) {
            $appointment = Appointment::whereKey($appointmentId)
                ->where('student_id', Auth::id())
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = $appointment->status;
            $oldDate = optional($appointment->appointment_date)->format('c');
            $oldTimeSlot = $appointment->time_slot;
            $appointment->update([
                'appointment_date' => $data['appointment_date'],
                'time_slot' => $data['time_slot'] ?? $appointment->time_slot,
                'status' => 'pending',
                'session_outcome' => null,
            ]);

            AppointmentStatusHistory::create([
                'appointment_id' => $appointment->id,
                'from_status' => $oldStatus,
                'to_status' => 'pending',
                'changed_by' => Auth::id(),
            ]);

            $fresh = $appointment->fresh(['student', 'counselor']);
            $changed = $oldDate !== optional($fresh->appointment_date)->format('c')
                || $oldTimeSlot !== $fresh->time_slot;

            return [$fresh, $changed];
        });

        [$appointment, $changed] = $result;
        if ($changed && $appointment->counselor_id) {
            NotificationService::notifyStudentAppointmentTransition($appointment, 'rescheduled');
        }

        return $appointment;
    }
}
