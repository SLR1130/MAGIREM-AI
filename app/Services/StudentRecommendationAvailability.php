<?php

namespace App\Services;

use App\Models\EmotionalEntry;
use App\Models\AIConversationSession;
use App\Models\PrivacySetting;
use App\Models\User;

/**
 * Shared, fail-closed availability state. It deliberately does not interpret
 * private content or claim that rule-based suggestions came from MAGI.
 */
final class StudentRecommendationAvailability
{
    public function forStudent(User $student): array
    {
        $consent = PrivacySetting::where('user_id', $student->id)->first();
        if (!$consent?->canUseAI()) {
            return ['state' => 'consent_required'];
        }

        $hasActivity = EmotionalEntry::where('user_id', $student->id)
            ->whereIn('session_id', AIConversationSession::query()->select('id')
                ->where('user_id', $student->id)
                ->where(fn ($q) => $q->whereNull('is_hidden')->orWhere('is_hidden', false)))
            ->exists();

        return ['state' => $hasActivity ? 'idle' : 'getting_to_know_you'];
    }
}
