<?php

namespace App\Services;

use App\Models\CounselorMessage;
use App\Models\CounselorMessageDeletion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class MessageDeletionService
{
    /**
     * Delete a message for the current user only (private deletion)
     *
     * @param CounselorMessage $message
     * @param int $userId Authenticated user ID
     * @return bool
     */
    public function deleteForMe(CounselorMessage $message, int $userId): bool
    {
        // Check if user is authorized (is a participant in the conversation)
        if (!$this->isUserParticipant($message, $userId)) {
            return false;
        }

        // Check if already deleted for this user
        if ($message->isDeletedForUser($userId)) {
            return true; // Already deleted, consider it success
        }

        // Create deletion record
        CounselorMessageDeletion::firstOrCreate([
            'message_id' => $message->id,
            'user_id' => $userId,
        ]);

        return true;
    }

    /**
     * Unsend a message for everyone (global deletion)
     *
     * @param CounselorMessage $message
     * @param int $userId Authenticated user ID (must be sender)
     * @return bool
     */
    public function unsendForEveryone(CounselorMessage $message, int $userId): bool
    {
        // Check if user is the sender
        if ($message->sender_id !== $userId) {
            return false;
        }

        // Check if already unsent
        if ($message->isUnsent()) {
            return true; // Already unsent
        }

        // Delete physical attachment file if exists
        if ($message->attachment_path) {
            try {
                if (Storage::disk('public')->exists($message->attachment_path)) {
                    Storage::disk('public')->delete($message->attachment_path);
                }
            } catch (\Exception $e) {
                // Log but don't fail the unsend
                \Log::warning("Failed to delete attachment for message {$message->id}: " . $e->getMessage());
            }
        }

        // Mark message as unsent
        $message->update([
            'unsent_at' => now(),
            'unsent_by' => $userId,
            'message' => null,
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_type' => null,
        ]);

        // Clear any delete-for-me records (message is now unsent for everyone)
        CounselorMessageDeletion::where('message_id', $message->id)->delete();

        return true;
    }

    /**
     * Check if a user is a participant in the conversation
     *
     * @param CounselorMessage $message
     * @param int $userId
     * @return bool
     */
    private function isUserParticipant(CounselorMessage $message, int $userId): bool
    {
        return $message->student_id == $userId || $message->counselor_id == $userId || $message->sender_id == $userId;
    }

    /**
     * Get timeline with deletions and unsends filtered
     *
     * @param array $timeline
     * @param int $userId Authenticated user ID
     * @return array
     */
    public function filterTimeline(array $timeline, int $userId): array
    {
        return array_map(function ($item) use ($userId) {
            // Only filter message type items, not video calls
            if ($item['type'] !== 'message') {
                return $item;
            }

            // Check if message is deleted for current user
            if (isset($item['id']) && CounselorMessageDeletion::where('message_id', $item['id'])
                ->where('user_id', $userId)
                ->exists()) {
                // Mark as hidden for this user
                $item['hidden_for_user'] = true;
                return $item;
            }

            // If unsent, return tombstone version
            if (isset($item['is_unsent']) && $item['is_unsent']) {
                return [
                    'type' => 'message',
                    'id' => $item['id'] ?? null,
                    'is_unsent' => true,
                    'sender_id' => $item['sender_id'] ?? null,
                    'message' => null,
                    'attachment_path' => null,
                    'attachment_url' => null,
                    'attachment_name' => null,
                    'attachment_type' => null,
                    'created_at' => $item['created_at'] ?? null,
                ];
            }

            return $item;
        }, $timeline);
    }
}
