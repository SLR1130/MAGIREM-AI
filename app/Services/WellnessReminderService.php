<?php

namespace App\Services;

use App\Models\WellnessReminder;

class WellnessReminderService
{
    /**
     * Create a wellness reminder
     */
    public function createReminder($userId, $type, $title, $message, $scheduledTime = null, $frequency = 'daily')
    {
        return WellnessReminder::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'scheduled_time' => $scheduledTime,
            'frequency' => $frequency,
            'is_enabled' => true,
        ]);
    }

    /**
     * Get enabled reminders for a user
     */
    public function getEnabledReminders($userId)
    {
        return WellnessReminder::getEnabledReminders($userId);
    }

    /**
     * Get reminders by type
     */
    public function getRemindersByType($userId, $type)
    {
        return WellnessReminder::getByType($userId, $type);
    }

    /**
     * Enable a reminder
     */
    public function enableReminder($reminderId)
    {
        $reminder = WellnessReminder::find($reminderId);
        if ($reminder) {
            $reminder->update(['is_enabled' => true]);
        }
        return $reminder;
    }

    /**
     * Disable a reminder
     */
    public function disableReminder($reminderId)
    {
        $reminder = WellnessReminder::find($reminderId);
        if ($reminder) {
            $reminder->update(['is_enabled' => false]);
        }
        return $reminder;
    }

    /**
     * Update reminder
     */
    public function updateReminder($reminderId, $data)
    {
        $reminder = WellnessReminder::find($reminderId);
        if ($reminder) {
            $reminder->update($data);
        }
        return $reminder;
    }

    /**
     * Delete reminder
     */
    public function deleteReminder($reminderId)
    {
        return WellnessReminder::destroy($reminderId);
    }

    /**
     * Mark reminder as sent
     */
    public function markAsSent($reminderId)
    {
        $reminder = WellnessReminder::find($reminderId);
        if ($reminder) {
            $reminder->update(['last_sent_at' => now()]);
        }
        return $reminder;
    }

    /**
     * Get default reminders for new user
     */
    public function createDefaultReminders($userId)
    {
        $reminders = [
            [
                'type' => 'mood-check',
                'title' => 'Daily Mood Check-In',
                'message' => 'How are you feeling today? Take a moment to check in with yourself.',
                'scheduled_time' => '09:00',
                'frequency' => 'daily',
            ],
            [
                'type' => 'journal-entry',
                'title' => 'Journal Reflection',
                'message' => 'Take a few minutes to reflect on your day and write in your journal.',
                'scheduled_time' => '20:00',
                'frequency' => 'daily',
            ],
            [
                'type' => 'self-care',
                'title' => 'Self-Care Reminder',
                'message' => 'Remember to take care of yourself today. Practice a coping strategy or do something you enjoy.',
                'scheduled_time' => '14:00',
                'frequency' => 'daily',
            ],
        ];

        foreach ($reminders as $reminder) {
            $this->createReminder(
                $userId,
                $reminder['type'],
                $reminder['title'],
                $reminder['message'],
                $reminder['scheduled_time'],
                $reminder['frequency']
            );
        }
    }

    /**
     * Get reminders due to send
     */
    public function getDueReminders()
    {
        return WellnessReminder::getDueReminders();
    }
}
