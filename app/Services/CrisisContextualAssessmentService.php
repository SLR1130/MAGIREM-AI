<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CrisisContextualAssessmentService
 * 
 * Calls Gemma3 for contextual crisis assessment using structured JSON responses.
 * This service analyzes ambiguous messages to determine if they indicate:
 * - SAFE: No crisis indicators
 * - NEEDS_CONFIRMATION: Ambiguous message requiring clarification
 * - HIGH_RISK: Clear crisis indicators
 * 
 * IMPORTANT: This service is for CONTEXTUAL analysis only. The deterministic
 * safety floor (RiskPhraseScreen + EnhancedCrisisAnalysisService) always takes
 * precedence. This service can only be used for NEEDS_CONFIRMATION cases.
 */
class CrisisContextualAssessmentService
{
    
    /**
     * Perform structured crisis assessment using Gemma3
     * 
     * Returns a structured JSON assessment object with:
     * - risk_category: SAFE | NEEDS_CONFIRMATION | HIGH_RISK
     * - current_thoughts: boolean or null (is user having current suicidal thoughts?)
     * - current_intent: boolean or null (does user intend to act?)
     * - historical_context: boolean (is this about the past?)
     * - recurrence: boolean or null (is this a recurring thought?)
     * - plan_present: boolean or null (does user have a plan?)
     * - immediacy: current | historical | unclear
     * - confidence: 0.0-1.0
     * - reason: brief explanation
     * - clarification_needed: boolean
     * - clarification_question: string or null
     * 
     * @param string $userMessage The message to analyze
     * @param string $sessionContext Optional recent conversation context
     * @return array Structured assessment response
     */
    public function assessMessage(string $userMessage, string $sessionContext = ''): array
    {
        Log::info('[CRISIS_ASSESSMENT_START]', [
            'message_length' => strlen($userMessage),
            'has_context' => !empty($sessionContext)
        ]);
        
        $systemPrompt = $this->getSystemPrompt();
        $userPrompt = $this->buildUserPrompt($userMessage, $sessionContext);
        
        try {
            $startTime = microtime(true);
            
            // The existing prompt and response contract are preserved; only the
            // local transport now goes through Flask's authenticated GPU gate.
            $requestId = 'crisis-' . bin2hex(random_bytes(16));
            $response = app(InferenceBridgeClient::class)->crisis([
                'request_id' => $requestId,
                'prompt' => "{$systemPrompt}\n\n{$userPrompt}",
                'model' => 'gemma3:4b',
                'temperature' => 0.3,
            ]);
            
            $elapsed = (microtime(true) - $startTime) * 1000;
            
            if (!isset($response['response'])) {
                Log::error('[CRISIS_ASSESSMENT_NO_RESPONSE]', ['elapsed_ms' => $elapsed]);
                return $this->getFallbackSafeResponse('No response from model');
            }
            
            $gemmaText = $response['response'];
            Log::info('[CRISIS_ASSESSMENT_RESPONSE_RECEIVED]', [
                'elapsed_ms' => $elapsed,
                'response_length' => strlen($gemmaText)
            ]);
            
            // Extract JSON from Gemma response
            $assessment = $this->parseGemmaResponse($gemmaText);
            
            if (!$assessment) {
                Log::warning('[CRISIS_ASSESSMENT_PARSE_FAILED]', ['response' => substr($gemmaText, 0, 200)]);
                return $this->getFallbackSafeResponse('Could not parse model response');
            }
            
            Log::info('[CRISIS_ASSESSMENT_SUCCESS]', [
                'risk_category' => $assessment['risk_category'],
                'confidence' => $assessment['confidence'],
                'elapsed_ms' => $elapsed
            ]);
            
            return $assessment;
            
        } catch (\Exception $e) {
            Log::error('[CRISIS_ASSESSMENT_EXCEPTION]', [
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);
            return $this->getFallbackSafeResponse($e->getMessage());
        }
    }
    
    /**
     * Build the system prompt for Gemma3
     * This prompt instructs Gemma to provide structured crisis analysis
     */
    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert mental health crisis assessment specialist. Your role is to provide the PRIMARY contextual crisis assessment. You determine whether messages indicate true crisis needs or normal emotional distress.

CRITICAL ROLE: You are the MAIN classifier - not a safety net behind keyword matching. Your assessment is the authoritative contextual decision for the conversational system.

SYSTEM MODE:
- Respond ONLY with valid JSON in the specified format
- Support English, Tagalog, Taglish
- Be conversational, not algorithmic
- Consider full context: temporal markers, relationships, recurrence, immediacy
- When uncertain, ask for clarification (NEEDS_CONFIRMATION) rather than guess

RESPONSE FORMAT (STRICT JSON, NO OTHER TEXT):
{
  "risk_category": "SAFE|NEEDS_CONFIRMATION|HIGH_RISK",
  "current_thoughts": true|false|null,
  "current_intent": true|false|null,
  "historical_context": true|false,
  "recurrence": true|false|null,
  "plan_present": true|false|null,
  "immediacy": "current|historical|unclear",
  "confidence": 0.0-1.0,
  "reason": "1-2 sentence explanation",
  "clarification_needed": true|false,
  "clarification_question": "direct question for user or null"
}

RISK CATEGORIES:
- SAFE: No crisis indicators. Normal emotion, venting, or recovered context.
- NEEDS_CONFIRMATION: Ambiguous message where context matters. Ask the user directly.
- HIGH_RISK: Clear current crisis indicators requiring immediate support.

TEMPORAL CONTEXT (CRITICAL):
- "I used to want to die" = HISTORICAL, not CURRENT
- "Those thoughts are coming back now" = RECURRENCE + CURRENT = HIGH_RISK
- "Sometimes I think about dying" = PASSIVE IDEATION = NEEDS_CONFIRMATION
- "I want to die right now" = CURRENT INTENT = HIGH_RISK

ORDINARY EMOTIONAL DISTRESS (NOT CRISIS):
- "I'm feeling sad today" â†’ SAFE (normal emotion, no harm intent)
- "I feel really down" â†’ SAFE (sadness without harm intent)
- "Medyo malungkot ako today" â†’ SAFE (Tagalog: feeling a bit sad, normal emotion)
- "I'm overwhelmed" â†’ SAFE (stress response)
- "I'm stressed about work" â†’ SAFE (situational stress)
- "I feel lonely" â†’ SAFE (emotional state, not harm intent)
- Even "I'm suicidal" without context needs clarification - ask: "Are you having suicidal thoughts right now?"
- Sadness, stress, loneliness, feeling down â‰  suicidal intent. These are normal human emotions.

NEGATION (REVERSES MEANING - CRITICAL RULE):
- "I don't want to die" = SAFE (explicit rejection of harm, clear non-crisis statement)
- "I'm not going to hurt myself" = SAFE (commitment to safety, explicit negation)
- "I can't bring myself to die" = SAFE (unable/unwilling, negation + inability)
- "I think I don't want to die" = SAFE (affirmative statement of not wanting to die)
- "I really don't want to die" = SAFE (emphatic negation, clear protective statement)
- "I no longer want to die" = SAFE (explicitly states past desire no longer exists, recovery language)
- "I don't want to hurt myself" = SAFE (negation directly rejects harm)
- "I don't want to die, I'm just overwhelmed" = SAFE (negation + clarification of actual emotion)
- "I think I don't think I want to die" = SAFE (double negation = affirmative of not wanting to die)

CONTRAST - These still indicate risk even with negation:
- "I don't know why I want to die" â†’ NEEDS_CONFIRMATION (negation doesn't apply to "want" - person is asking WHY, still risky)
- "I don't think I can stop myself from hurting myself" â†’ HIGH_RISK (negation applies to stopping, NOT to hurting)
- "I don't want to die, but I can't stop the thoughts" â†’ NEEDS_CONFIRMATION (mixed: negation + recurring thoughts)

KEY RULE: When negation applies directly to the harm concept ("I don't want to [harm]"), it's SAFE.
When negation applies to stopping/controlling harm, it's RISKY.

EXAMPLES:
1. "Sometimes I think about dying."
   â†’ NEEDS_CONFIRMATION (passive, ambiguous immediacy)
   â†’ Question: "Are you having thoughts of dying or hurting yourself right now?"

2. "I used to want to die but I'm doing better now."
   â†’ SAFE (historical context, recovery narrative, no current intent)
   â†’ No clarification needed

3. "I used to want to die, but those thoughts are coming back now."
   â†’ HIGH_RISK or NEEDS_CONFIRMATION (depends on current intensity)
   â†’ If NEEDS_CONFIRMATION: Ask about current intent/plan

4. "This assignment is literally killing me lmao"
   â†’ SAFE (hyperbole/dark humor, academic stressor)

5. "I don't want to live anymore."
   â†’ NEEDS_CONFIRMATION (passive, but serious - clarify immediateness)

6. "Gusto ko na mamatay ngayon" (Tagalog: I want to die now)
   â†’ HIGH_RISK (current explicit ideation + immediacy marker "ngayon")

7. "Medyo malungkot ako today." (Tagalog: I'm feeling a bit sad today)
   â†’ SAFE (ordinary sadness, no harm intent)

8. "I don't want to die."
   â†’ SAFE (clear negation rejects harm) 
   â†’ NOT ambiguous - user explicitly states they do NOT want to die
   â†’ This is a protective statement, not a concerning one
   â†’ Treat as emotionally struggling but explicitly NON-SUICIDAL

9. "I don't want to hurt myself."
   â†’ SAFE (negation rejects self-harm)

10. "I no longer want to die."
    â†’ SAFE (past tense + negation = recovery narrative)

11. "I don't know why I want to die but I'm trying not to."
    â†’ NEEDS_CONFIRMATION (mixed: curiosity about "why" but explicit attempt to resist)

YOUR JOB:
You are NOT a keyword matcher. You are a context-aware classifier. Do not:
- Treat all mentions of "die/suicide" as HIGH_RISK
- Ignore temporal markers (used to, now, tonight, etc.)
- Miss negations - they REVERSE meaning (I DON'T want to die = NOT suicidal)
- Assume passive ideation is active intent
- Classify ordinary sadness as crisis (sadness â‰  suicidal intent)
- Treat explicit rejections of harm ("I don't want to...") as concerning

DO:
- Consider the full sentence and conversation
- Distinguish past from present
- Recognize when clarification is needed
- Support the user's actual safety needs
- Understand negation reverses the meaning
- Treat "I don't want to die/harm myself" as SAFE statements, not ambiguous
PROMPT;
    }
    
    /**
     * Build the user prompt
     */
    private function buildUserPrompt(string $userMessage, string $sessionContext): string
    {
        $prompt = "Analyze this message for crisis indicators:\n\n";
        
        if (!empty($sessionContext)) {
            $prompt .= "CONVERSATION CONTEXT:\n{$sessionContext}\n\n";
        }
        
        $prompt .= "USER MESSAGE:\n{$userMessage}\n\n";
        
        $prompt .= "Provide your assessment as JSON only (no other text before or after):";
        
        return $prompt;
    }
    
    /**
     * Parse Gemma response to extract JSON
     * Gemma may include markdown formatting or extra text, so we extract the JSON object
     */
    private function parseGemmaResponse(string $response): ?array
    {
        // Try to extract JSON object from response
        // Look for { ... } pattern
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $jsonStr = $matches[0];
            
            try {
                $data = json_decode($jsonStr, true);
                
                if ($data === null) {
                    Log::warning('[JSON_DECODE_FAILED]', ['json_str' => substr($jsonStr, 0, 200)]);
                    return null;
                }
                
                // Validate required fields
                if (!isset($data['risk_category']) || !isset($data['confidence'])) {
                    Log::warning('[JSON_MISSING_FIELDS]', ['data' => $data]);
                    return null;
                }
                
                // Validate risk_category value
                if (!in_array($data['risk_category'], ['SAFE', 'NEEDS_CONFIRMATION', 'HIGH_RISK'])) {
                    Log::warning('[INVALID_RISK_CATEGORY]', ['category' => $data['risk_category']]);
                    return null;
                }
                
                // Ensure all expected fields exist with sensible defaults
                return [
                    'risk_category' => $data['risk_category'],
                    'current_thoughts' => $data['current_thoughts'] ?? null,
                    'current_intent' => $data['current_intent'] ?? null,
                    'historical_context' => $data['historical_context'] ?? false,
                    'recurrence' => $data['recurrence'] ?? null,
                    'plan_present' => $data['plan_present'] ?? null,
                    'immediacy' => in_array($data['immediacy'] ?? 'unclear', ['current', 'historical', 'unclear']) 
                        ? ($data['immediacy'] ?? 'unclear') 
                        : 'unclear',
                    'confidence' => min(1.0, max(0.0, (float)($data['confidence'] ?? 0))),
                    'reason' => substr((string)($data['reason'] ?? 'Assessment complete'), 0, 200),
                    'clarification_needed' => (bool)($data['clarification_needed'] ?? false),
                    'clarification_question' => $data['clarification_question'] ?? null,
                    'gemma_response' => substr($response, 0, 500), // Store for debugging
                ];
                
            } catch (\Exception $e) {
                Log::error('[JSON_PARSE_EXCEPTION]', [
                    'error' => $e->getMessage(),
                    'json_str' => substr($jsonStr, 0, 200)
                ]);
                return null;
            }
        }
        
        return null;
    }
    
    /**
     * Get a safe fallback response when Gemma fails
     * Conservative: when in doubt, treat as NEEDS_CONFIRMATION
     */
    private function getFallbackSafeResponse(string $error = ''): array
    {
        Log::warning('[CRISIS_ASSESSMENT_FALLBACK]', ['error' => $error]);
        
        return [
            'risk_category' => 'NEEDS_CONFIRMATION',
            'current_thoughts' => null,
            'current_intent' => null,
            'historical_context' => false,
            'recurrence' => null,
            'plan_present' => null,
            'immediacy' => 'unclear',
            'confidence' => 0.3, // Low confidence in fallback
            'reason' => 'Unable to assess - defaulting to confirmation needed',
            'clarification_needed' => true,
            'clarification_question' => 'I want to make sure I understand you correctly. Can you tell me a bit more about what you\'re feeling right now?',
            'is_fallback' => true,
        ];
    }
    
    /**
     * Map Gemma's risk_category to Laravel's crisis level
     * For integration with existing EnhancedCrisisAnalysisService
     */
    public function mapToCrisisLevel(string $riskCategory): string
    {
        return match ($riskCategory) {
            'HIGH_RISK' => 'critical',
            'NEEDS_CONFIRMATION' => 'moderate', // Treat ambiguous as moderate (needs investigation)
            'SAFE' => 'none',
            default => 'moderate',
        };
    }
}
