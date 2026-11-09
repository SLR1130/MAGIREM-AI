<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\User;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        // Get a student user (assuming role 'student' exists)
        $student = User::where('role', 'student')->first();
        
        if (!$student) {
            $this->command->warn('No student user found. Skipping notification seeding.');
            return;
        }

        $notifications = [
            [
                'user_id' => $student->id,
                'type' => 'appointment',
                'title' => 'Appointment Confirmed',
                'message' => 'Your counseling session is scheduled for tomorrow at 2:00 PM with Dr. Smith',
                'link' => '/student/appointments',
                'is_read' => false,
                'priority' => 'high',
                'created_at' => now()->subMinutes(5),
            ],
            [
                'user_id' => $student->id,
                'type' => 'message',
                'title' => 'New Message from Counselor',
                'message' => 'Dr. Johnson has sent you a message regarding your recent session',
                'link' => '/student/counselor-chat',
                'is_read' => false,
                'priority' => 'normal',
                'created_at' => now()->subHours(2),
            ],
            [
                'user_id' => $student->id,
                'type' => 'reminder',
                'title' => 'Daily Mood Check-in',
                'message' => 'Don\'t forget to complete your mood check-in for today',
                'link' => '/student/wellbeing',
                'is_read' => false,
                'priority' => 'normal',
                'created_at' => now()->subHours(4),
            ],
            [
                'user_id' => $student->id,
                'type' => 'achievement',
                'title' => '7-Day Streak! 🔥',
                'message' => 'Congratulations! You\'ve maintained a 7-day journaling streak',
                'link' => '/student/dashboard',
                'is_read' => true,
                'priority' => 'low',
                'created_at' => now()->subDay(),
            ],
            [
                'user_id' => $student->id,
                'type' => 'system',
                'title' => 'New Resource Available',
                'message' => 'Check out our new guide on managing exam stress and anxiety',
                'link' => '/student/resources',
                'is_read' => true,
                'priority' => 'low',
                'created_at' => now()->subDays(2),
            ],
            [
                'user_id' => $student->id,
                'type' => 'reminder',
                'title' => 'Wellness Reminder',
                'message' => 'Time for your scheduled breathing exercise',
                'link' => '/student/coping-toolkit',
                'is_read' => true,
                'priority' => 'normal',
                'created_at' => now()->subDays(3),
            ],
        ];

        foreach ($notifications as $notification) {
            Notification::create($notification);
        }

        $this->command->info('Sample notifications created successfully!');
    }
}
