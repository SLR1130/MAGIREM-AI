<?php

namespace App\Services;

use App\Models\AIConversationSession;
use Illuminate\Support\Facades\Log;

/**
 * CrisisConfirmationFlowService
 * 
 * Manages the confirmation conversation state for ambiguous crisis messages.
 * When Gemma flags a message as NEEDS_CONFIRMATION, this service:
 * 
 * 1. Stores the session state (awaiting_confirmation)
 * 2. Returns a clarification question to the user
 * 3. On next user message, re-assesses based on clarification
 * 4. Transitions state to confirmed_safe or confirmed_high_risk
 * 
 * State machine:
 * - none (default) → awaiting_confirmation (when ambiguous)
 * - awaiting_confirmation → confirmed_safe (when clarification says it's safe)
 * - awaiting_confirmation → confirmed_high_risk (when clarification confirms crisis)
 * - confirmed_safe/confirmed_high_risk → handled by crisis handlers
 */
class CrisisConfirmationFlowService
{
    /**
     * Assessment states for AI conversation sessions
     */
    private const STATE_NONE = 'none';
    private const STATE_AWAITING_CONFIRMATION = 'awaiting_confirmation';
    private const STATE_CONFIRMED_SAFE = 'confirmed_safe';
    private const STATE_CONFIRMED_HIGH_RISK = 'confirmed_high_risk';
    
    /**
     * Initialize confirmation flow for an ambiguous message
     * 
     * @param AIConversationSession $session
     * @param array $gemmaAssessment From CrisisContextualAssessmentService
     * @return void
     */
    public function initializeConfirmation(AIConversationSession $session, array $gemmaAssessment): void
    {
        // Store the original assessment in session metadata for reference
        $metadata = $session->session_metadata ?? [];
        $metadata['last_assessment'] = [
            'timestamp' => now()->toIso8601String(),
            'risk_category' => $gemmaAssessment['risk_category'] ?? 'NEEDS_CONFIRMATION',
            'confidence' => $gemmaAssessment['confidence'] ?? 0.5,
            'reason' => $gemmaAssessment['reason'] ?? 'Assessment pending',
            'clarification_question' => $gemmaAssessment['clarification_question'] ?? null,
        ];
        
        $session->update([
            'crisis_assessment_status' => self::STATE_AWAITING_CONFIRMATION,
            'session_metadata' => $metadata,
        ]);
        
        Log::info('[CONFIRMATION_INITIALIZED]', [
            'session_id' => $session->id,
            'question' => $gemmaAssessment['clarification_question'] ?? 'Awaiting confirmation',
        ]);
    }
    
    /**
     * Get current confirmation state for a session
     */
    public function getState(AIConversationSession $session): string
    {
        return $session->crisis_assessment_status ?? self::STATE_NONE;
    }
    
    /**
     * Check if session is awaiting a confirmation response
     */
    public function isAwaitingConfirmation(AIConversationSession $session): bool
    {
        return $this->getState($session) === self::STATE_AWAITING_CONFIRMATION;
    }
    
    /**
     * Get the clarification question that was asked
     */
    public function getClarificationQuestion(AIConversationSession $session): ?string
    {
        $metadata = $session->session_metadata ?? [];
        return $metadata['last_assessment']['clarification_question'] ?? null;
    }
    
    /**
     * Process the confirmation response (user's answer to clarification question)
     * 
     * @param AIConversationSession $session
     * @param string $confirmationMessage User's response to clarification
     * @param array $reassessment From CrisisContextualAssessmentService (after re-analysis)
     * @return string The resolved crisis level ('none', 'moderate', 'severe', 'critical')
     */
    public function processConfirmation(AIConversationSession $session, string $confirmationMessage, array $reassessment): string
    {
        $previousAssessment = $session->session_metadata['last_assessment'] ?? [];
        
        // Map Gemma's risk_category to crisis level
        $crisisContextualService = app(CrisisContextualAssessmentService::class);
        $resolvedCrisisLevel = $crisisContextualService->mapToCrisisLevel($reassessment['risk_category'] ?? 'SAFE');
        
        // Determine state based on reassessment
        if (($reassessment['risk_category'] ?? 'SAFE') === 'HIGH_RISK') {
            $newState = self::STATE_CONFIRMED_HIGH_RISK;
        } else {
            $newState = self::STATE_CONFIRMED_SAFE;
        }
        
        // Store confirmation details in session metadata
        $metadata = $session->session_metadata ?? [];
        $metadata['confirmation_flow'] = [
            'original_question' => $previousAssessment['clarification_question'] ?? null,
            'user_response' => $confirmationMessage,
            'reassessment' => [
                'risk_category' => $reassessment['risk_category'] ?? 'SAFE',
                'confidence' => $reassessment['confidence'] ?? 0.5,
                'reason' => $reassessment['reason'] ?? 'Reassessment complete',
            ],
            'resolved_at' => now()->toIso8601String(),
            'resolved_to' => $newState,
            'resolved_crisis_level' => $resolvedCrisisLevel,
        ];
        
        $session->update([
            'crisis_assessment_status' => $newState,
            'session_metadata' => $metadata,
        ]);
        
        Log::info('[CONFIRMATION_PROCESSED]', [
            'session_id' => $session->id,
            'original_category' => $previousAssessment['risk_category'] ?? 'unknown',
            'resolved_to' => $newState,
            'resolved_crisis_level' => $resolvedCrisisLevel,
            'confidence' => $reassessment['confidence'] ?? 0.5,
        ]);
        
        return $resolvedCrisisLevel;
    }
    
    /**
     * Clear confirmation state for a session
     * Used after handling the confirmed crisis or safe message
     */
    public function clearState(AIConversationSession $session): void
    {
        $session->update([
            'crisis_assessment_status' => self::STATE_NONE,
        ]);
        
        Log::info('[CONFIRMATION_CLEARED]', ['session_id' => $session->id]);
    }
    
    /**
     * Generate a response for the confirmation question
     * This is shown to the user when we need clarification
     */
    public function generateConfirmationResponse(string $clarificationQuestion): string
    {
        return trim($clarificationQuestion);
    }
}
