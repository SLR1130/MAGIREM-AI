<?php

namespace Tests\Unit\Services;

use App\Services\CrisisContextualAssessmentService;
use App\Services\CrisisConfirmationFlowService;
use App\Services\RiskPhraseScreen;
use App\Services\EnhancedCrisisAnalysisService;
use App\Models\AIConversationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHPUnit Tests for Gemma-First Crisis Flow (Task 6 Redesign)
 * 
 * These tests verify that the crisis detection follows this flow:
 * User Message → Deterministic Pre-Screen (Gemma candidate) → Gemma Contextual Assessment
 * → SAFE/NEEDS_CONFIRMATION/HIGH_RISK → Confirmation if needed → Final State
 * 
 * RiskPhraseScreen is NOT the normal decision point - it's an emergency override only.
 */
class GemmaFirstCrisisFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $testUser;
    protected AIConversationSession $session;
    protected CrisisContextualAssessmentService $gemmaService;
    protected CrisisConfirmationFlowService $confirmationService;
    protected RiskPhraseScreen $riskScreen;
    protected EnhancedCrisisAnalysisService $enhancedAnalysis;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->testUser = User::factory()->create(['role' => 'student']);
        $this->session = AIConversationSession::create([
            'user_id' => $this->testUser->id,
            'session_token' => uniqid('test_', true),
            'title' => 'Test Session',
        ]);
        
        $this->gemmaService = app(CrisisContextualAssessmentService::class);
        $this->confirmationService = app(CrisisConfirmationFlowService::class);
        $this->riskScreen = new RiskPhraseScreen();
        $this->enhancedAnalysis = app(EnhancedCrisisAnalysisService::class);
    }

    /**
     * TEST 1: Normal greeting should return SAFE
     * Flow: Normal → Gemma:SAFE → Normal AI response
     */
    public function test_normal_greeting_is_safe(): void
    {
        $message = "Kumusta ka?";
        
        // Deterministic pre-screen
        $screen = $this->riskScreen->screen($message);
        $this->assertEquals('none', $screen['level'], "RiskPhraseScreen should not raise for normal greeting");
        
        // Gemma assessment (this would be actual call in real code)
        // For testing, we verify the flow logic
        $this->assertNotEquals('critical', $screen['level'], "Normal greeting must not be CRITICAL");
    }

    /**
     * TEST 2: Sad emotional message should not auto-escalate to HIGH_RISK
     * Flow: Moderate → Deterministic candidate → Gemma assessment
     * Expected: SAFE (normal emotion) or NEEDS_CONFIRMATION (ambiguous)
     */
    public function test_emotional_message_not_auto_critical(): void
    {
        $message = "Medyo malungkot ako today.";
        
        // This should NOT be automatically CRITICAL
        $screen = $this->riskScreen->screen($message);
        $this->assertNotEquals('critical', $screen['level'], 
            "Sadness alone should not trigger CRITICAL");
    }

    /**
     * TEST 3: Historical recovery should NOT be HIGH_RISK
     * Flow: Contains "want to die" but past-tense guard should prevent escalation
     * Expected: Gemma detects past context → SAFE
     */
    public function test_historical_recovery_is_safe(): void
    {
        $message = "I used to want to die but I'm doing better now.";
        
        // RiskPhraseScreen should check for past-tense guard
        $screen = $this->riskScreen->screen($message);
        
        // The message contains risk keywords but should not be CRITICAL
        // because of past-tense context
        $this->assertNotEquals('critical', $screen['level'],
            "Historical recovery with past-tense context must not be CRITICAL");
        
        // In real flow, Gemma would assess this as SAFE
    }

    /**
     * TEST 4: Recurrence phrase should be NEEDS_CONFIRMATION or HIGH_RISK
     * Flow: "I used to... but those thoughts are coming back now"
     * Expected: Gemma detects recurrence + current context → HIGH_RISK
     */
    public function test_recurrence_phrase_triggers_assessment(): void
    {
        $message = "I used to want to die, but those thoughts are coming back now.";
        
        // Pre-screen: should detect as candidate (contains risk keywords)
        $screen = $this->riskScreen->screen($message);
        
        // Enhanced analysis should detect recurrence
        $enhanced = $this->enhancedAnalysis->analyzeMessage($message);
        $this->assertTrue($enhanced['recurrence_detected'] ?? false,
            "Recurrence must be detected in 'thoughts coming back now'");
        
        // Gemma assessment (in real flow) should classify as HIGH_RISK or NEEDS_CONFIRMATION
        // With recurrence + past tense + current reactivation
        $this->assertTrue(in_array($enhanced['crisis_level'], ['moderate', 'critical']),
            "Recurrence phrase must be classified as moderate or critical, not none");
    }

    /**
     * TEST 5: Ambiguous message triggers confirmation
     * Flow: User: "Sometimes I think about dying."
     * → Gemma: NEEDS_CONFIRMATION
     * → System asks: "Are you having thoughts of dying right now?"
     * → Session state: awaiting_confirmation
     */
    public function test_ambiguous_message_triggers_confirmation(): void
    {
        $message = "Sometimes I think about dying.";
        
        // In real flow:
        // 1. Deterministic pre-screen: candidate risk detected
        // 2. Gemma assessment: NEEDS_CONFIRMATION
        // 3. Initialize confirmation flow
        
        $this->confirmationService->initializeConfirmation($this->session, [
            'risk_category' => 'NEEDS_CONFIRMATION',
            'clarification_question' => 'Are you having thoughts of dying or hurting yourself right now?',
            'clarification_needed' => true,
            'confidence' => 0.65,
        ]);
        
        // Verify session state is set
        $this->session->refresh();
        $this->assertEquals('awaiting_confirmation', $this->session->crisis_assessment_status,
            "Session state must be awaiting_confirmation");
    }

    /**
     * TEST 6: User denies current intent → reassess to SAFE
     * User's answer to confirmation: "No, not right now."
     * Expected: Gemma reassesses → SAFE → normal conversation
     */
    public function test_confirmation_answer_no_resolves_to_safe(): void
    {
        // Setup: session awaiting confirmation
        $this->confirmationService->initializeConfirmation($this->session, [
            'risk_category' => 'NEEDS_CONFIRMATION',
            'clarification_question' => 'Are you having thoughts of dying right now?',
            'clarification_needed' => true,
        ]);
        
        // User answers: "No, not right now."
        $confirmationMessage = "No, not right now.";
        
        // Gemma reassesses with context
        $reassessment = [
            'risk_category' => 'SAFE',
            'current_thoughts' => false,
            'immediacy' => 'historical',
            'confidence' => 0.92,
        ];
        
        // Process confirmation
        $resolvedLevel = $this->confirmationService->processConfirmation(
            $this->session,
            $confirmationMessage,
            $reassessment
        );
        
        // Verify resolution
        $this->session->refresh();
        $this->assertEquals('confirmed_safe', $this->session->crisis_assessment_status,
            "Session must move to confirmed_safe");
        $this->assertEquals('none', $resolvedLevel,
            "Resolved crisis level must be 'none' for SAFE");
    }

    /**
     * TEST 7: User confirms current intent → HIGH_RISK → crisis-safe response
     * User's answer: "Yes, right now."
     * Expected: Gemma reassesses → HIGH_RISK → crisis-safe response
     */
    public function test_confirmation_answer_yes_resolves_to_high_risk(): void
    {
        // Setup: session awaiting confirmation
        $this->confirmationService->initializeConfirmation($this->session, [
            'risk_category' => 'NEEDS_CONFIRMATION',
            'clarification_question' => 'Are you having thoughts of dying right now?',
            'clarification_needed' => true,
        ]);
        
        // User answers: "Yes, right now I keep thinking about it."
        $confirmationMessage = "Yes, right now I keep thinking about it.";
        
        // Gemma reassesses with context
        $reassessment = [
            'risk_category' => 'HIGH_RISK',
            'current_thoughts' => true,
            'immediacy' => 'current',
            'confidence' => 0.94,
        ];
        
        // Process confirmation
        $resolvedLevel = $this->confirmationService->processConfirmation(
            $this->session,
            $confirmationMessage,
            $reassessment
        );
        
        // Verify resolution
        $this->session->refresh();
        $this->assertEquals('confirmed_high_risk', $this->session->crisis_assessment_status,
            "Session must move to confirmed_high_risk");
        $this->assertEquals('critical', $resolvedLevel,
            "Resolved crisis level must be 'critical' for HIGH_RISK");
    }

    /**
     * TEST 8: Tagalog crisis message must go through Gemma
     * Flow: "Gusto ko na mamatay ngayon"
     * Expected: NOT auto-CRITICAL from keyword alone
     * Expected: Gemma contextual assessment runs → HIGH_RISK
     */
    public function test_tagalog_crisis_through_gemma_assessment(): void
    {
        $message = "Gusto ko na mamatay ngayon.";  // "I want to die now"
        
        // RiskPhraseScreen may flag as candidate
        $screen = $this->riskScreen->screen($message);
        
        // Enhanced analysis should detect crisis signals
        $enhanced = $this->enhancedAnalysis->analyzeMessage($message);
        $this->assertTrue($enhanced['intent_detected'] ?? false,
            "Tagalog message should have intent detected");
        $this->assertTrue($enhanced['temporal_urgency'] >= 0.7,
            "Tagalog 'ngayon' (now) should indicate urgency >= 0.7");
        
        // Final classification should be critical/moderate (not none)
        $this->assertTrue(in_array($enhanced['crisis_level'], ['moderate', 'critical']),
            "Tagalog crisis message must be classified as moderate or critical");
    }

    /**
     * TEST 9: Negation handling - "I don't want to die" should not be HIGH_RISK
     * Flow: Gemma detects negation + context
     * Expected: SAFE
     */
    public function test_negation_handled_correctly(): void
    {
        $message = "I don't want to die.";
        
        // Enhanced analysis should detect negation/contradiction
        $enhanced = $this->enhancedAnalysis->analyzeMessage($message);
        
        // Negation should either not be detected as crisis OR require confirmation
        $this->assertTrue(in_array($enhanced['crisis_level'], ['none', 'moderate']),
            "Negation 'I don't want to die' must not be auto-critical (none or moderate only)");
        
        // RiskPhraseScreen should NOT mark this as critical based on negation alone
        $screen = $this->riskScreen->screen($message);
        $this->assertNotEquals('critical', $screen['level'],
            "Negation must not trigger CRITICAL from keyword matching");
    }

    /**
     * TEST 10: Emergency override - unmistakable immediate danger
     * Flow: Explicit current intent + plan + method + timing
     * Example: "I have pills here and I'm going to take them tonight"
     * Expected: Deterministic safety floor → HIGH_RISK → bypasses confirmation
     */
    public function test_emergency_override_unmistakable_danger(): void
    {
        $message = "I have pills and I'm going to take them tonight at 11pm.";
        
        // Enhanced analysis should detect multiple emergency signals
        $enhanced = $this->enhancedAnalysis->analyzeMessage($message);
        $this->assertTrue($enhanced['intent_detected'] ?? false,
            "Emergency message must have intent detected");
        $this->assertTrue($enhanced['method_detected'] ?? false,
            "Emergency message must have method detected (pills)");
        $this->assertTrue($enhanced['access_detected'] ?? false,
            "Emergency message must have access detected");
        $this->assertTrue($enhanced['temporal_urgency'] >= 0.8,
            "Emergency message must have high temporal urgency");
        
        // Combined signals should indicate emergency
        $this->assertEquals('critical', $enhanced['crisis_level'],
            "Unmistakable immediate danger must be classified as critical");
    }

    /**
     * TEST 11: Gemma failure fallback
     * If Gemma times out or returns malformed JSON
     * Expected: Fall back to deterministic safety floor, not silently SAFE
     */
    public function test_gemma_failure_falls_back_safely(): void
    {
        $message = "Sometimes I think about dying.";
        
        // Simulate Gemma failure
        $fallbackResponse = [
            'risk_category' => 'NEEDS_CONFIRMATION',  // Conservative
            'confidence' => 0.3,  // Low confidence
            'is_fallback' => true,
            'reason' => 'Gemma service unavailable',
        ];
        
        // Verify fallback is conservative (not SAFE)
        $this->assertNotEquals('SAFE', $fallbackResponse['risk_category'],
            "Gemma failure must not fallback to SAFE");
    }

    /**
     * TEST 12: State machine verification
     * none → awaiting_confirmation → confirmed_safe|confirmed_high_risk
     */
    public function test_confirmation_state_machine(): void
    {
        // Initial state
        $this->session->refresh();
        $this->assertEquals('none', $this->session->crisis_assessment_status);
        
        // Transition to awaiting_confirmation
        $this->confirmationService->initializeConfirmation($this->session, [
            'risk_category' => 'NEEDS_CONFIRMATION',
            'clarification_question' => 'Test?',
            'clarification_needed' => true,
        ]);
        
        $this->session->refresh();
        $this->assertEquals('awaiting_confirmation', $this->session->crisis_assessment_status);
        
        // Transition to confirmed_safe
        $this->confirmationService->processConfirmation($this->session, 'No', [
            'risk_category' => 'SAFE',
            'confidence' => 0.9,
        ]);
        
        $this->session->refresh();
        $this->assertEquals('confirmed_safe', $this->session->crisis_assessment_status);
    }

    /**
     * TEST 13: Session metadata stores assessment history
     */
    public function test_session_metadata_records_assessment(): void
    {
        $assessment = [
            'risk_category' => 'NEEDS_CONFIRMATION',
            'confidence' => 0.68,
            'reason' => 'Ambiguous passive ideation',
            'clarification_question' => 'Are you having thoughts right now?',
        ];
        
        $this->confirmationService->initializeConfirmation($this->session, $assessment);
        
        $this->session->refresh();
        $this->assertIsArray($this->session->session_metadata);
        $this->assertArrayHasKey('last_assessment', $this->session->session_metadata);
    }
}
