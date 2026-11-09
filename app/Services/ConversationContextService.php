<?php

namespace App\Services;

use App\Models\AIConversationSession;
use App\Models\EmotionalEntry;
use Illuminate\Support\Str;

/**
 * Builds bounded, session-scoped AI context from the durable chat history.
 *
 * This is not cross-session long-term memory. The digest remains on the owning
 * AIConversationSession and is removed with that session. Cross-session memory
 * continues to require the separate Phase 4B consent boundary in Flask.
 */
final class ConversationContextService
{
    public function __construct(private readonly ChatHistoryService $history)
    {
    }

    /** @return array{history: array<int, array<string, mixed>>, session_digest: ?string} */
    public function forAuthorizedSession(AIConversationSession $session, int $userId): array
    {
        if ((int) $session->user_id !== $userId) {
            throw new \RuntimeException('Conversation session access denied.');
        }

        $turnLimit = max(1, min(24, (int) config('services.mindcare_ml.chat.recent_history_turns', 12)));
        $metadata = is_array($session->session_metadata) ? $session->session_metadata : [];
        $digest = isset($metadata['continuity_digest']) && is_string($metadata['continuity_digest'])
            ? trim($metadata['continuity_digest'])
            : null;

        return [
            'history' => $this->history->getConversationHistoryBySession($session->id, $turnLimit),
            'session_digest' => $digest !== '' ? $digest : null,
        ];
    }

    /** Refresh a bounded digest only after an interaction is fully saved. */
    public function refreshSessionDigest(AIConversationSession $session, int $userId): void
    {
        if ((int) $session->user_id !== $userId) {
            throw new \RuntimeException('Conversation session access denied.');
        }

        $recentTurns = max(1, min(24, (int) config('services.mindcare_ml.chat.recent_history_turns', 12)));
        $sourceTurns = max($recentTurns, min(80, (int) config('services.mindcare_ml.chat.digest_source_turns', 36)));
        $maxChars = max(400, min(6000, (int) config('services.mindcare_ml.chat.digest_max_chars', 1800)));

        // The current recent window is sent verbatim. Only older entries feed
        // the digest, keeping session continuity bounded without cross-session retrieval.
        $olderEntries = EmotionalEntry::query()
            ->where('user_id', $userId)
            ->where('session_id', $session->id)
            ->latest('created_at')
            ->skip($recentTurns)
            ->take($sourceTurns)
            ->get()
            ->reverse()
            ->values();

        $metadata = is_array($session->session_metadata) ? $session->session_metadata : [];
        if ($olderEntries->isEmpty()) {
            unset($metadata['continuity_digest'], $metadata['continuity_digest_updated_at'], $metadata['continuity_digest_entry_count']);
            $session->session_metadata = $metadata;
            $session->save();
            return;
        }

        $parts = [];
        $remaining = $maxChars;
        foreach ($olderEntries as $entry) {
            $user = $this->excerpt((string) $entry->entry_text, 180);
            $assistant = $this->excerpt((string) $entry->ai_response, 180);
            if ($user === '' && $assistant === '') {
                continue;
            }

            $part = trim(($user !== '' ? 'Student: ' . $user : '')
                . ($user !== '' && $assistant !== '' ? ' | ' : '')
                . ($assistant !== '' ? 'Assistant: ' . $assistant : ''));
            if ($part === '') {
                continue;
            }
            if (mb_strlen($part) > $remaining) {
                $part = rtrim(mb_substr($part, 0, max(0, $remaining - 1))) . '…';
            }
            $parts[] = $part;
            $remaining -= mb_strlen($part) + 1;
            if ($remaining <= 0) {
                break;
            }
        }

        $metadata['continuity_digest'] = implode("\n", $parts);
        $metadata['continuity_digest_updated_at'] = now()->toIso8601String();
        $metadata['continuity_digest_entry_count'] = $olderEntries->count();
        $session->session_metadata = $metadata;
        $session->save();
    }

    private function excerpt(string $value, int $limit): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        return $value === '' ? '' : Str::limit($value, $limit, '…');
    }
}