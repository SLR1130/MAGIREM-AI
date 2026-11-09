<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CrisisAlert;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CrisisDetectionService
{
    public function handleCrisisDetection($studentId, $emotionData, $messageContent, $aiResponse)
    {
        $crisisLevel = $emotionData['crisis_level'] ?? 'none';
        if (!in_array($crisisLevel, ['critical', 'severe'], true)) {
            return null;
        }

        // This method governs disclosure only. Student-facing safety support is
        // generated earlier in AIChatController and never depends on this gate.
        $counselor = $this->assignCounselor($studentId);
        if (!$counselor) {
            Log::warning('Institutional crisis escalation withheld', [
                'student_id' => $studentId,
                'reason' => 'no_active_counselor',
            ]);
            return null;
        }

        $authorization = app(InstitutionalCrisisEscalationGate::class)->authorize($counselor);
        if (!$authorization['authorized']) {
            Log::warning('Institutional crisis escalation withheld', [
                'student_id' => $studentId,
                'counselor_id' => $counselor->id,
                'reason' => $authorization['reason'],
            ]);
            return null;
        }

        $alert = CrisisAlert::create([
            'student_id' => $studentId,
            'counselor_id' => $counselor->id,
            'crisis_level' => $crisisLevel,
            'crisis_category' => $emotionData['crisis_category'] ?? 'unknown',
            'message_content' => $messageContent,
            'ai_response' => $aiResponse,
            'is_resolved' => false,
        ]);

        $appointment = $this->createCrisisAppointment($studentId, $counselor->id, $crisisLevel, $messageContent);
        $appointmentCreated = $appointment instanceof Appointment
            && $appointment->exists
            && Appointment::whereKey($appointment->getKey())->exists();
        $alert->setAttribute('appointment_created', $appointmentCreated);
        $notificationSent = $this->notifyCounselor($counselor, $studentId, $crisisLevel, $messageContent);

        Log::critical('Crisis alert created', [
            'alert_id' => $alert->id,
            'student_id' => $studentId,
            'counselor_id' => $counselor->id,
            'level' => $crisisLevel,
            'notification_sent' => $notificationSent,
        ]);

        return $alert;
    }

    private function assignCounselor($studentId)
    {
        return User::where('role', 'counselor')
            ->where('is_active', true)
            ->get()
            ->sortBy(fn ($counselor) => CrisisAlert::where('counselor_id', $counselor->id)->where('is_resolved', false)->count())
            ->first();
    }

    private function notifyCounselor($counselor, $studentId, $crisisLevel, $messageContent): bool
    {
        $student = User::find($studentId);
        try {
            \DB::table('notifications')->insert([
                'user_id' => $counselor->id,
                'type' => 'crisis_alert',
                'title' => "Crisis Alert - Student {$student->name}",
                'message' => substr($messageContent, 0, 200),
                'icon' => 'alert',
                'link' => route('counselor.chat', $studentId),
                'priority' => 'urgent',
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        } catch (\Exception $exception) {
            Log::error('Counselor crisis notification failed', [
                'counselor_id' => $counselor->id,
                'student_id' => $studentId,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    private function createCrisisAppointment($studentId, $counselorId, $crisisLevel, $messageContent)
    {
        if (Appointment::where('student_id', $studentId)->where('category', 'crisis')->whereIn('status', ['pending', 'confirmed'])->exists()) {
            return null;
        }

        return Appointment::create([
            'student_id' => $studentId,
            'counselor_id' => $counselorId,
            'status' => 'pending',
            'category' => 'crisis',
            'reason' => "CRISIS ALERT ({$crisisLevel}): " . substr($messageContent, 0, 100),
            'preferred_date' => now()->addDay(),
            'appointment_date' => now()->addDay(),
            'created_from_crisis' => true,
            'notes' => "Automatically created from authorized AI crisis detection. Level: {$crisisLevel}",
        ]);
    }

    public function resolveCrisis($alertId, $counselorId)
    {
        $alert = CrisisAlert::find($alertId);
        if (!$alert) {
            return false;
        }
        $alert->update(['is_resolved' => true, 'resolved_at' => now(), 'resolved_by' => $counselorId]);
        return true;
    }
}
