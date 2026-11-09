<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentLifecycleService
{
    public const OUTCOMES = [
        'completed', 'student_no_show', 'counselor_no_show',
        'technical_problem', 'other', 'needs_review',
    ];

    public const FAILURE_OUTCOMES = [
        'student_no_show', 'counselor_no_show', 'technical_problem', 'other', 'needs_review',
    ];

    public function window(Appointment $appointment, ?Carbon $now = null): array
    {
        $timezone = config('appointments.timezone', config('app.timezone', 'Asia/Manila'));
        $start = Carbon::parse($appointment->appointment_date)->setTimezone($timezone);
        $end = $start->copy()->addMinutes((int) config('appointments.session_duration_minutes', 60));
        $graceEnd = $end->copy()->addMinutes((int) config('appointments.grace_period_minutes', 15));
        $current = ($now ?: now())->copy()->setTimezone($timezone);

        return [
            'start' => $start,
            'end' => $end,
            'grace_end' => $graceEnd,
            'now' => $current,
            'is_open' => $current->betweenIncluded($start, $graceEnd),
            'is_overdue' => $current->greaterThan($graceEnd),
        ];
    }

    public function isOverdueWithoutOutcome(Appointment $appointment, ?Carbon $now = null): bool
    {
        return $appointment->status === 'confirmed'
            && $appointment->session_outcome === null
            && $this->window($appointment, $now)['is_overdue'];
    }
    /**
     * Resolve the student-facing lifecycle state without changing the
     * persisted appointment status. Both student and counselor surfaces use
     * this interpretation so an overdue unresolved appointment is presented
     * as Needs Review rather than being silently mutated.
     */
    public function displayStatus(Appointment $appointment, ?Carbon $now = null): string
    {
        if ($this->isOverdueWithoutOutcome($appointment, $now)) {
            return 'needs_review';
        }

        if ($appointment->status === 'confirmed' && $appointment->session_outcome !== null) {
            if ($appointment->session_outcome === 'needs_review') {
                return 'needs_review';
            }

            return in_array($appointment->session_outcome, self::FAILURE_OUTCOMES, true)
                ? 'failed'
                : ($appointment->session_outcome === 'completed' ? 'completed' : $appointment->session_outcome);
        }

        return match ($appointment->status) {
            'pending' => 'pending',
            'confirmed' => 'confirmed',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => (string) $appointment->status,
        };
    }

    public function displayStatusLabel(Appointment $appointment, ?Carbon $now = null): string
    {
        return match ($this->displayStatus($appointment, $now)) {
            'pending' => 'Pending approval',
            'confirmed' => 'Confirmed',
            'needs_review' => 'Needs review',
            'completed' => 'Completed',
            'failed' => 'Not held',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', (string) $this->displayStatus($appointment, $now))),
        };
    }

    public function studentCanMessage(Appointment $appointment, ?Carbon $now = null): bool
    {
        return $appointment->status === 'confirmed'
            && $appointment->session_outcome === null
            && !$this->isOverdueWithoutOutcome($appointment, $now)
            && $appointment->counselor_id !== null;
    }

    public function studentCanCall(Appointment $appointment, ?Carbon $now = null): bool
    {
        return $appointment->status === 'confirmed'
            && $appointment->session_outcome === null
            && $appointment->counselor_id !== null
            && $this->window($appointment, $now)['is_open'];
    }

    /**
     * Messaging remains available for an unresolved appointment until its
     * configured grace period expires. It is read-only after an outcome or
     * when the appointment is overdue and awaiting review.
     */
    public function hasMessagingAppointment(int $counselorId, int $studentId, ?Carbon $now = null): bool
    {
        return Appointment::where('counselor_id', $counselorId)
            ->where('student_id', $studentId)
            ->where('status', 'confirmed')
            ->whereNull('session_outcome')
            ->get()
            ->contains(fn (Appointment $appointment) => !$this->isOverdueWithoutOutcome($appointment, $now));
    }
    public function findCallableAppointment(int $counselorId, int $studentId, ?Carbon $now = null): ?Appointment
    {
        $appointments = Appointment::where('counselor_id', $counselorId)
            ->where('student_id', $studentId)
            ->where('status', 'confirmed')
            ->whereNull('session_outcome')
            ->with(['student', 'counselor'])
            ->orderBy('appointment_date')
            ->get();

        return $appointments->first(fn (Appointment $appointment) => $this->window($appointment, $now)['is_open']);
    }

    public function recordOutcome(
        Appointment $appointment,
        string $outcome,
        ?string $reason,
        ?string $evidence,
        int $actorId
    ): Appointment {
        if (!in_array($outcome, self::OUTCOMES, true)) {
            throw ValidationException::withMessages(['outcome' => 'Unsupported appointment outcome.']);
        }

        return DB::transaction(function () use ($appointment, $outcome, $reason, $evidence, $actorId) {
            $locked = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->counselor_id !== $actorId || $locked->status !== 'confirmed' || $locked->session_outcome !== null) {
                throw ValidationException::withMessages(['appointment' => 'Only an unresolved confirmed appointment assigned to you can be finalized.']);
            }

            $window = $this->window($locked);
            if (in_array($outcome, self::FAILURE_OUTCOMES, true) && !$window['is_overdue']) {
                throw ValidationException::withMessages(['outcome' => 'Failure outcomes can only be recorded after the scheduled grace period.']);
            }

            if ($outcome === 'completed') {
                if (!in_array($evidence, ['online_call', 'manual_verification', 'external_verification'], true)) {
                    throw ValidationException::withMessages(['attendance_evidence' => 'Completion requires verified attendance evidence.']);
                }
                if ($evidence !== 'online_call' && trim((string) $reason) === '') {
                    throw ValidationException::withMessages(['reason' => 'A verification note is required for non-call attendance.']);
                }
                if ($evidence === 'online_call') {
                    $call = DB::table('video_calls')->where('appointment_id', $locked->id)
                        ->whereNotNull('accepted_at')->whereNotNull('ended_at')->latest('id')->first();
                    if (!$call) {
                        throw ValidationException::withMessages(['attendance_evidence' => 'No completed linked video call is available for this appointment.']);
                    }
                    $locked->session_started_at = $call->accepted_at;
                    $locked->session_ended_at = $call->ended_at;
                }
            } elseif (trim((string) $reason) === '') {
                throw ValidationException::withMessages(['reason' => 'A reason is required for an unresolved or failed appointment.']);
            }

            $oldStatus = $locked->status;
            $oldOutcome = $locked->session_outcome;
            $locked->session_outcome = $outcome;
            $locked->outcome_reason = trim((string) $reason) ?: null;
            $locked->attendance_evidence = $evidence ?: null;
            $locked->outcome_verified_at = now();
            $locked->outcome_verified_by = $actorId;
            if ($outcome === 'completed') {
                $locked->status = 'completed';
            }
            $locked->save();

            AppointmentStatusHistory::create([
                'appointment_id' => $locked->id,
                'from_status' => $oldStatus,
                'to_status' => $locked->status,
                'from_outcome' => $oldOutcome,
                'to_outcome' => $outcome,
                'reason' => $locked->outcome_reason,
                'attendance_evidence' => $locked->attendance_evidence,
                'changed_by' => $actorId,
            ]);

            return $locked->fresh(['student', 'counselor']);
        });
    }
}