<?php

namespace App\Services;

use App\Models\EmotionalEntry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;

class ChatHistoryService
{
    /**
     * Store chat message with full context for ML training
     */
    public function storeMessage($studentId, $userMessage, $aiResponse, $emotionData, $sessionId = null, $conversationHistory = [])
    {
        // Legacy compatibility: if the final argument is an array and sessionId is not provided,
        // that array may be the old conversation history parameter.
        if (is_array($sessionId) && empty($conversationHistory)) {
            $conversationHistory = $sessionId;
            $sessionId = null;
        }

        $taxonomyMetadata = app(EmotionTaxonomy::class)->metadata(is_array($emotionData) ? $emotionData : []);

        $entry = EmotionalEntry::create(array_merge([
            'user_id' => $studentId,
            'session_id' => $sessionId,
            'entry_text' => $userMessage,
            'ai_response' => $aiResponse,
            'detected_emotion' => $emotionData['emotion'] ?? 'neutral',
            'confidence_score' => ($emotionData['confidence'] ?? 0.5) * 100, // Convert 0-1 to 0-100 for storage
            'crisis_level' => $emotionData['crisis_level'] ?? 'none',
            'needs_counselor' => $emotionData['needs_counselor'] ?? false,
            'crisis_category' => $emotionData['crisis_level'] ?? 'none',
            'detected_contexts' => $emotionData['contexts'] ?? $emotionData['crisis_indicators'] ?? [],
            'conversation_context' => $conversationHistory ?? [],
            'is_anonymous' => false
        ], $taxonomyMetadata));

        return $entry;
    }

    /**
     * Get conversation history for a specific session
     */
    public function getConversationHistoryBySession($sessionId, $limit = 10)
    {
        $entries = EmotionalEntry::where('session_id', $sessionId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        $history = [];
        foreach ($entries as $entry) {
            $history[] = [
                'user' => $entry->entry_text,
                'ai' => $entry->ai_response,
                'emotion' => $entry->detected_emotion,
                'timestamp' => $entry->created_at
            ];
        }

        return $history;
    }

    /**
     * Get general conversation history for a user (across all sessions)
     * NOTE: This should NOT be used for AI chat context - use getConversationHistoryBySession instead
     */
    public function getConversationHistory($studentId, $limit = 10)
    {
        $entries = EmotionalEntry::where('user_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        $history = [];
        foreach ($entries as $entry) {
            $history[] = [
                'user' => $entry->entry_text,
                'ai' => $entry->ai_response,
                'emotion' => $entry->detected_emotion,
                'timestamp' => $entry->created_at
            ];
        }

        return $history;
    }

    /**
     * Raw conversations are not exportable under general analytics consent.
     * A separately governed model-training authorization must be implemented
     * before this legacy pathway can be re-enabled.
     */
    public function getTrainingBatch($limit = 100)
    {
        throw new AuthorizationException(
            'Raw conversation export requires separate model-training authorization.'
        );
    }

    /**
     * Get conversation summary
     */
    public function getConversationSummary($userId)
    {
        $entries = EmotionalEntry::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $emotions = [];
        $totalMessages = $entries->count();
        
        foreach ($entries as $entry) {
            $emotion = $entry->detected_emotion;
            if (!isset($emotions[$emotion])) {
                $emotions[$emotion] = 0;
            }
            $emotions[$emotion]++;
        }

        return [
            'total_messages' => $totalMessages,
            'emotions' => $emotions,
            'last_conversation' => $entries->first() ? $entries->first()->created_at : null
        ];
    }

    /**
     * Clear conversation history
     */
    public function clearConversation($userId)
    {
        EmotionalEntry::where('user_id', $userId)->delete();
    }

    /**
     * Save feedback on AI response
     */
    public function saveFeedback($messageId, $rating, $feedback = null)
    {
        $entry = EmotionalEntry::where('id', $messageId)->where('user_id', auth()->id())->first();
        if ($entry) {
            $entry->feedback_rating = $rating;
            $entry->feedback_text = $feedback;
            $entry->save();
        }
    }

    /**
     * Log crisis event
     */
    public function logCrisisEvent($userId, $message, $crisisLevel)
    {
        Log::critical("CRISIS EVENT - User {$userId}: {$crisisLevel}", [
            'user_id' => $userId,
            'message_length' => strlen($message),
            'crisis_level' => $crisisLevel,
            'timestamp' => now()
        ]);
    }
}
