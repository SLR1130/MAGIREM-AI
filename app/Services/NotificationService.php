<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    /** Notify only explicitly authorized reviewers without exposing applicant details. */
    public static function accountApplicationSubmitted(User $applicant): void
    {
        if (!config('services.account_verification.reviewer_authorization_approved', false)) {
            return;
        }

        $reviewerIds = array_unique(array_map('intval', (array) config('services.account_verification.reviewer_user_ids', [])));
        $link = route('admin.account-verification.show', $applicant, false);
        foreach (User::query()->whereIn('id', $reviewerIds)->get() as $reviewer) {
            if (!$reviewer->canReviewAccountVerification()) {
                continue;
            }
            Notification::firstOrCreate([
                'user_id' => $reviewer->id,
                'type' => 'account_verification',
                'title' => 'New student application',
                'link' => $link,
            ], [
                'message' => 'A student application is awaiting Guidance Office review.',
                'is_read' => false,
                'priority' => 'normal',
            ]);
        }
    }

    /** Notify eligible counselors about a pending request without sharing its contents. */
    public static function appointmentRequested($appointment): void
    {
        $counselors = User::query()->where('role', 'counselor')->where('is_active', true);
        if ($appointment->counselor_id) {
            $counselors->whereKey($appointment->counselor_id);
        }

        $link = route('counselor.appointments', ['request' => $appointment->id], false);
        foreach ($counselors->get() as $counselor) {
            if (!$counselor->isApproved()) {
                continue;
            }
            Notification::firstOrCreate([
                'user_id' => $counselor->id,
                'type' => 'appointment',
                'title' => 'New appointment request',
                'link' => $link,
            ], [
                'message' => 'A counseling appointment request is awaiting review.',
                'is_read' => false,
                'priority' => 'normal',
            ]);
        }
    }

    /**
     * Create a notification for a specific user
     */
    public static function notify(
        $userId,
        string $type,
        string $title,
        string $message,
        string $priority = 'normal',
        string $link = null,
        string $icon = null
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'priority' => $priority,
            'link' => $link,
            'icon' => $icon,
        ]);
    }

    /**
     * Create one student-scoped notification for a persisted appointment
     * transition. The link identifies the appointment without exposing any
     * concern text, crisis details, diagnosis, or transcript.
     */
    public static function notifyStudentAppointmentTransition($appointment, string $event): ?Notification
    {
        $appointment->loadMissing(['counselor']);
        if (!$appointment->student_id) {
            return null;
        }

        $timezone = config('appointments.timezone', config('app.timezone', 'Asia/Manila'));
        $date = $appointment->appointment_date
            ? $appointment->appointment_date->copy()->setTimezone($timezone)->format('F j, Y \a\t g:i A')
            : 'the approved session time';
        $counselorName = optional($appointment->counselor)->name ?: 'your counselor';
        $link = route('student.counseling-requests', ['appointment_id' => $appointment->id])
            . '#appointment-' . $appointment->id;

        [$title, $message, $priority] = match ($event) {
            'confirmed' => [
                'Appointment confirmed',
                "Your counseling appointment with {$counselorName} has been confirmed for {$date}.",
                'high',
            ],
            'rescheduled' => [
                'Appointment time updated',
                "Your counseling appointment with {$counselorName} has been rescheduled to {$date}.",
                'high',
            ],
            'cancelled' => [
                'Appointment cancelled',
                "Your counseling appointment with {$counselorName} has been cancelled.",
                'high',
            ],
            'completed' => [
                'Appointment completed',
                "Your counseling appointment with {$counselorName} has been marked completed after verification.",
                'normal',
            ],
            'failed' => [
                'Appointment not held',
                "Your counseling appointment with {$counselorName} was recorded as not held. You can request another appointment if needed.",
                'normal',
            ],
            default => throw new \InvalidArgumentException('Unsupported appointment notification event.'),
        };

        return Notification::firstOrCreate(
            [
                'user_id' => $appointment->student_id,
                'type' => 'appointment',
                'title' => $title,
                'message' => $message,
                'link' => $link,
            ],
            [
                'priority' => $priority,
                'icon' => 'calendar',
            ]
        );
    }
    /**
     * Notify multiple users
     */
    public static function notifyMultiple(
        array $userIds,
        string $type,
        string $title,
        string $message,
        string $priority = 'normal',
        string $link = null,
        string $icon = null
    ): Collection {
        $notifications = collect();
        
        foreach ($userIds as $userId) {
            $notification = self::notify($userId, $type, $title, $message, $priority, $link, $icon);
            $notifications->push($notification);
        }
        
        return $notifications;
    }

    /**
     * Notify all counselors
     */
    public static function notifyAllCounselors(
        string $type,
        string $title,
        string $message,
        string $priority = 'normal',
        string $link = null,
        string $icon = null
    ): Collection {
        $counselorIds = User::where('role', 'counselor')
            ->pluck('id')
            ->toArray();
        
        return self::notifyMultiple($counselorIds, $type, $title, $message, $priority, $link, $icon);
    }

    /**
     * Notify all admins
     */
    public static function notifyAllAdmins(
        string $type,
        string $title,
        string $message,
        string $priority = 'normal',
        string $link = null,
        string $icon = null
    ): Collection {
        $adminIds = User::where('role', 'admin')
            ->pluck('id')
            ->toArray();
        
        return self::notifyMultiple($adminIds, $type, $title, $message, $priority, $link, $icon);
    }

    /**
     * Notify all students
     */
    public static function notifyAllStudents(
        string $type,
        string $title,
        string $message,
        string $priority = 'normal',
        string $link = null,
        string $icon = null
    ): Collection {
        $studentIds = User::where('role', 'student')
            ->pluck('id')
            ->toArray();
        
        return self::notifyMultiple($studentIds, $type, $title, $message, $priority, $link, $icon);
    }

    /**
     * Appointment notification - notify student and counselor
     */
    public static function appointmentScheduled($appointment): void
    {
        // Notify student
        self::notify(
            $appointment->student_id,
            'appointment',
            'Appointment Scheduled',
            "Your appointment with {$appointment->counselor->name} has been scheduled for " . 
            $appointment->appointment_date->format('F d, Y at g:i A'),
            'high',
            route('student.appointments'),
            '📅'
        );

        // Notify counselor
        self::notify(
            $appointment->counselor_id,
            'appointment',
            'New Appointment',
            "You have a new appointment with {$appointment->student->name} on " . 
            $appointment->appointment_date->format('F d, Y at g:i A'),
            'high',
            route('counselor.appointments'),
            '📅'
        );

        // Notify admins
        self::notifyAllAdmins(
            'appointment',
            'Appointment Scheduled',
            "{$appointment->student->name} has scheduled an appointment with {$appointment->counselor->name}",
            'normal',
            route('admin.dashboard'),
            '📅'
        );
    }

    /**
     * Appointment confirmed notification
     */
    public static function appointmentConfirmed($appointment): void
    {
        self::notifyStudentAppointmentTransition($appointment, 'confirmed');

        // Notify counselor
        self::notify(
            $appointment->counselor_id,
            'appointment',
            'Appointment Confirmed',
            "Your appointment with {$appointment->student->name} has been confirmed",
            'normal',
            route('counselor.appointments'),
            '✅'
        );
    }

    /**
     * Appointment completed notification
     */
    public static function appointmentCompleted($appointment): void
    {
        self::notifyStudentAppointmentTransition($appointment, 'completed');

        // Notify counselor
        self::notify(
            $appointment->counselor_id,
            'appointment',
            'Appointment Completed',
            "Your appointment with {$appointment->student->name} has been marked as complete",
            'normal',
            route('counselor.appointments'),
            '✅'
        );
    }

    /**
     * Appointment cancelled notification
     */
    public static function appointmentCancelled($appointment, $reason = null): void
    {
        self::notifyStudentAppointmentTransition($appointment, 'cancelled');

        // Notify the counselor without disclosing the student's cancellation reason.
        self::notify(
            $appointment->counselor_id,
            'appointment',
            'Appointment Cancelled',
            "Your appointment with {$appointment->student->name} has been cancelled.",
            'high',
            route('counselor.appointments'),
            'calendar'
        );
    }

    /**
     * Verified failed/not-held appointment notification.
     */
    public static function appointmentFailed($appointment): void
    {
        self::notifyStudentAppointmentTransition($appointment, 'failed');

        if ($appointment->counselor_id) {
            self::notify(
                $appointment->counselor_id,
                'appointment',
                'Appointment outcome recorded',
                "The appointment with {$appointment->student->name} was recorded as not held.",
                'normal',
                route('counselor.appointments'),
                'calendar'
            );
        }
    }
    /**
     * New message notification
     */
    public static function newMessage($senderId, $recipientId, $senderName, $messagePreview): void
    {
        $sender = User::find($senderId);
        $recipient = User::find($recipientId);

        if (!$sender || !$recipient) return;

        $link = $sender->role === 'counselor' 
            ? route('student.counselor-chat') 
            : route('counselor.chat', ['studentId' => $senderId]);

        self::notify(
            $recipientId,
            'message',
            "New message from {$senderName}",
            substr($messagePreview, 0, 100) . (strlen($messagePreview) > 100 ? '...' : ''),
            'normal',
            $link,
            '💬'
        );
    }

    /**
     * Crisis alert notification - notify counselors and admins
     */
    public static function crisisDetected($student, $riskLevel): void
    {
        $riskText = ucfirst($riskLevel);

        // Notify student (informational)
        self::notify(
            $student->id,
            'crisis',
            'Crisis Support Available',
            'We detected signs of distress. Please reach out to our emergency resources or contact a counselor immediately.',
            'urgent',
            route('student.emergency-resources'),
            '🆘'
        );

        // Notify all counselors
        self::notifyAllCounselors(
            'crisis',
            "Crisis Alert - {$riskText} Risk",
            "{$student->name} may need immediate support. Please review their mental health status.",
            'urgent',
            route('counselor.mental-health-monitoring'),
            '🆘'
        );

        // Notify all admins
        self::notifyAllAdmins(
            'crisis',
            "Crisis Alert - {$riskText} Risk",
            "Student {$student->name} has been flagged for {$riskLevel} risk level.",
            'urgent',
            route('admin.dashboard'),
            '🆘'
        );
    }

    /**
     * Mental health alert notification
     */
    public static function mentalHealthAlert($student, string $alertType, string $details): void
    {
        // Notify student
        self::notify(
            $student->id,
            'health-alert',
            'Mental Health Alert',
            $details,
            'high',
            route('student.mental-status'),
            '⚠️'
        );

        // Notify assigned counselors
        $counselors = $student->assignedCounselors ?? [];
        foreach ($counselors as $counselor) {
            self::notify(
                $counselor->id,
                'health-alert',
                "Student Alert: {$alertType}",
                "{$student->name}: {$details}",
                'high',
                route('counselor.mental-health-monitoring'),
                '⚠️'
            );
        }

        // Notify admins
        self::notifyAllAdmins(
            'health-alert',
            "Student Mental Health Alert",
            "{$student->name}: {$details}",
            'high',
            route('admin.dashboard'),
            '⚠️'
        );
    }

    /**
     * Mood check-in reminder
     */
    public static function moodCheckInReminder($student): void
    {
        self::notify(
            $student->id,
            'reminder',
            'Daily Mood Check-in',
            "Don't forget to complete your daily mood check-in!",
            'normal',
            route('student.wellbeing'),
            '😊'
        );
    }

    /**
     * Achievement/milestone notification
     */
    public static function achievementUnlocked($student, string $achievementName, string $description): void
    {
        self::notify(
            $student->id,
            'achievement',
            "🎉 {$achievementName}",
            $description,
            'low',
            route('student.insights'),
            '🏆'
        );
    }

    /**
     * System announcement
     */
    public static function systemAnnouncement(string $title, string $message, array $roles = ['student', 'counselor', 'admin']): Collection
    {
        $notifications = collect();

        foreach ($roles as $role) {
            if ($role === 'student') {
                $notifications = $notifications->merge(self::notifyAllStudents('system', $title, $message, 'normal', null, '📢'));
            } elseif ($role === 'counselor') {
                $notifications = $notifications->merge(self::notifyAllCounselors('system', $title, $message, 'normal', null, '📢'));
            } elseif ($role === 'admin') {
                $notifications = $notifications->merge(self::notifyAllAdmins('system', $title, $message, 'normal', null, '📢'));
            }
        }

        return $notifications;
    }

    /**
     * User registration confirmation
     */
    public static function registrationConfirmation($user): void
    {
        self::notify(
            $user->id,
            'system',
            'Welcome to MAGIREM AI!',
            'Your account has been successfully created. Welcome to our mental wellness community!',
            'low',
            route($user->role . '.dashboard'),
            '👋'
        );

        // Notify admins about new registration
        self::notifyAllAdmins(
            'system',
            'New User Registration',
            "A new {$user->role} account has been created: {$user->name}",
            'normal',
            route('admin.users'),
            '👤'
        );
    }

    /**
     * Get unread count for a user
     */
    public static function getUnreadCount($userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get recent notifications for a user
     */
    public static function getRecentNotifications($userId, $limit = 20): Collection
    {
        return Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Get unread notifications for a user
     */
    public static function getUnreadNotifications($userId): Collection
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Mark notification as read
     */
    public static function markAsRead($notificationId, $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) return false;

        $notification->update(['is_read' => true]);
        return true;
    }

    /**
     * Mark all as read for a user
     */
    public static function markAllAsRead($userId): bool
    {
        Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return true;
    }

    /**
     * Delete notification
     */
    public static function deleteNotification($notificationId, $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) return false;

        $notification->delete();
        return true;
    }

    /**
     * Clear old notifications (older than 30 days)
     */
    public static function clearOldNotifications($daysOld = 30): int
    {
        $deleted = Notification::where('created_at', '<', now()->subDays($daysOld))
            ->delete();

        return $deleted;
    }
}
