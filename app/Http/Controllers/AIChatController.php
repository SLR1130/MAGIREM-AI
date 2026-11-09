<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\EmotionDetectionService;
use App\Services\ChatHistoryService;
use App\Services\ConversationContextService;
use Illuminate\Support\Facades\Cache;
use App\Services\MentalHealthMonitoringService;
use App\Services\SituationWeightService;
use App\Services\EnhancedCrisisAnalysisService;
use App\Services\WellbeingScoreCalculator;
use App\Services\AuthoritativeEmotionService;
use App\Services\InternalMemoryAuthClient;
use App\Services\MlServiceBusyException;
use App\Services\CrisisContextualAssessmentService;
use App\Services\CrisisConfirmationFlowService;
use App\Models\PrivacySetting;
use App\Models\AIConversationSession;
use App\Services\InferenceBridgeClient;
use Illuminate\Support\Str;

class AIChatController extends Controller
{
    protected $emotionService;
    protected $chatService;
    protected $mentalHealthService;
    protected $enhancedCrisisService;
    protected $wellbeingCalculator;
    protected $crisisContextualAssessmentService;
    protected $crisisConfirmationFlowService;
    protected $authoritativeEmotionService;

    public function __construct(
        EmotionDetectionService $emotionService, 
        ChatHistoryService $chatService,
        MentalHealthMonitoringService $mentalHealthService,
        EnhancedCrisisAnalysisService $enhancedCrisisService,
        WellbeingScoreCalculator $wellbeingCalculator,
        CrisisContextualAssessmentService $crisisContextualAssessmentService,
        CrisisConfirmationFlowService $crisisConfirmationFlowService,
        AuthoritativeEmotionService $authoritativeEmotionService
    )
    {
        $this->emotionService        = $emotionService;
        $this->chatService           = $chatService;
        $this->mentalHealthService   = $mentalHealthService;
        $this->enhancedCrisisService = $enhancedCrisisService;
        $this->wellbeingCalculator   = $wellbeingCalculator;
        $this->crisisContextualAssessmentService = $crisisContextualAssessmentService;
        $this->crisisConfirmationFlowService = $crisisConfirmationFlowService;
        $this->authoritativeEmotionService = $authoritativeEmotionService;
    }

    /**
     * Initialize new chat session with AI greeting
     * This is called when user creates a new chat - AI greets first
     * SECURITY: Uses authenticated user only - never trusts request user_id for authorization
     */
    public function initializeChatSession(Request $request)
    {
        try {
            // PHASE 1 SECURITY FIX: Always use authenticated user, never fallback to request
            $user_id = auth()->id();
            if (!$user_id) {
                \Log::warning('[INIT_SESSION_UNAUTHENTICATED] Request attempted without auth');
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthenticated'
                ], 401);
            }

            $privacySetting = PrivacySetting::getOrCreateForUser($user_id);
            if (!$privacySetting->canUseAI()) {
                return response()->json([
                    'success' => false,
                    'code' => 'AI_CONSENT_REQUIRED',
                    'error' => 'AI recommendations are disabled in your privacy settings.',
                ], 403);
            }
            
            $user_name = auth()->user()->name ?? 'User';
            $provided_session_id = $request->get('session_id');
            
            // ========= REAL MEMORY SYSTEM =========
            // Get user's ACTUAL previous messages (not sessions, but REAL MESSAGES)
            $previous_messages = \App\Models\EmotionalEntry::where('user_id', $user_id)
                ->orderBy('created_at', 'desc')
                ->take(20)  // Get last 20 messages for analysis
                ->get();
            
            // Extract actual conversation topics from previous messages (EXCLUDING distressing ones for greeting)
            $topics = [];
            $emotions_history = [];
            $distress_emotions = ['sadness', 'anxiety', 'angry', 'grief'];
            
            foreach ($previous_messages as $msg) {
                // Get the user's actual message ONLY if not distressing (for friendly greeting context)
                if (!empty($msg->entry_text) && !in_array($msg->detected_emotion, $distress_emotions)) {
                    $topics[] = substr($msg->entry_text, 0, 100); // First 100 chars of message
                }
                // Track emotions user has expressed (for analytics, not greeting)
                if (!empty($msg->detected_emotion)) {
                    $emotions_history[] = $msg->detected_emotion;
                }
            }
            
            $has_previous_conversations = count($topics) > 0;
            
            // Build SMART context with real information
            $previous_topics_str = '';
            if (!empty($topics)) {
                // Show actual topics they've talked about
                $previous_topics_str = "Topics we've discussed: " . implode(", ", array_slice($topics, 0, 3));
            }
            
            // Analyze emotion patterns
            $emotion_counts = array_count_values($emotions_history);
            $dominant_emotion = !empty($emotion_counts) ? array_key_first($emotion_counts) : 'neutral';
            
            $context = [
                'user_id' => $user_id,
                'user_name' => $user_name,
                'has_previous_conversations' => $has_previous_conversations,
                'previous_topics' => $previous_topics_str,
                'dominant_emotion' => $dominant_emotion,
                'message_count' => count($previous_messages)
            ];
            
            // Try to call ML API with REAL context
            $greeting = null;
            try {
                $authClient = app(InternalMemoryAuthClient::class);
                $ml_response = $authClient->post('memory.initial_greeting', $user_id, $context, $authClient->memoryAuthorizedFor($user_id));
                
                if (is_array($ml_response)) {
                    $greeting = $ml_response['greeting'] ?? null;
                    \Log::info("Got greeting from ML API for user {$user_id}");
                }
            } catch (\Exception $ml_error) {
                \Log::warning('ML API not available for greeting: ' . $ml_error->getMessage());
            }
            
            // Use SMART fallback greetings with REAL memory
            if (!$greeting) {
                if ($has_previous_conversations) {
                    // RETURNING USER - USE REAL INFORMATION
                    $topic_mention = !empty($topics) ? "I remember you mentioned: '" . substr($topics[0], 0, 50) . "...'" : "I remember our past conversations";
                    
                    $greetings_returning = [
                        "Welcome back, {$user_name}! {$topic_mention}. How are things going now?",
                        "Hey {$user_name}! It's good to see you again. Last time we talked about something important. What's on your mind today?",
                        "Welcome back! I remember our conversations. You've been feeling {$dominant_emotion} mostly. What's new?",
                        "{$user_name}, welcome back! We've had {$request->input('message_count', 10)}+ conversations. What would you like to talk about?",
                        "Good to have you back, {$user_name}! {$topic_mention}. Let's continue supporting you.",
                    ];
                    $greeting = $greetings_returning[array_rand($greetings_returning)];
                } else {
                    // NEW USER - WARM WELCOME
                    $greetings_new = [
                        "Hi! I'm MAGI, your wellness companion.",
                    ];
                    $greeting = $greetings_new[array_rand($greetings_new)];
                }
            }
            
            // Use provided session ID if available, otherwise create new one
            if ($provided_session_id) {
                // PHASE 1 SECURITY FIX: Verify session belongs to authenticated user
                $session = \App\Models\AIConversationSession::where('id', $provided_session_id)
                    ->where('user_id', $user_id)
                    ->first();
                    
                if (!$session) {
                    \Log::warning('[SESSION_OWNERSHIP_VIOLATION]', [
                        'provided_session_id' => $provided_session_id,
                        'auth_user_id' => $user_id
                    ]);
                    return response()->json([
                        'success' => false,
                        'error' => 'Session not found or access denied'
                    ], 403);
                }
            } else {
                // Create new conversation session
                $session = \App\Models\AIConversationSession::create([
                    'user_id' => $user_id,
                    'session_token' => uniqid('ai_session_', true),
                    'title' => 'Chat - ' . now()->format('M j, g:i A'),
                    'last_message_at' => now(),
                ]);
            }
            
            // Log for debugging - SHOW IF MEMORY IS WORKING
            \Log::info("Chat session initialized for user {$user_id}", [
                'session_id' => $session->id,
                'is_returning' => $has_previous_conversations,
                'greeting' => substr($greeting, 0, 100),
                'previous_messages_found' => count($previous_messages),
                'topics_extracted' => count($topics),
                'dominant_emotion' => $dominant_emotion
            ]);
            
            return response()->json([
                'success' => true,
                'greeting' => $greeting,
                'is_ai_greeting' => true,
                'session_id' => $session->id,
                'is_returning_user' => $has_previous_conversations,
                'memory_status' => [
                    'has_previous_conversations' => $has_previous_conversations,
                    'messages_found' => count($previous_messages),
                    'topics_identified' => $topics,
                    'dominant_emotion' => $dominant_emotion
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Chat initialization error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Unable to initialize the chat session.',
                'greeting' => 'Hi there! How can I help you today?'
            ], 500);
        }
    }

    /**
     * Send message to AI and get response
     */
    public function sendMessage(Request $request)
    {
        \Log::info('[CHAT_REQUEST_START] Received chat request', [
            'user_id' => auth()->id(),
            'session_id' => $request->input('session_id'),
            'message_length' => strlen((string) $request->input('message', ''))
        ]);

        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'user_id' => 'nullable|integer',
            'session_id' => 'nullable|integer',
            // Generated by the client for one logical send; safe reconnect retries reuse it.
            'request_id' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.:-]*$/'],
        ]);
        
        // Generate correlation ID for tracing
        $correlationId = $validated['request_id'] ?? Str::uuid()->toString();
        $requestStarted = \microtime(true);
        $chatLock = null;
        \Log::info('[TRACE_START]', ['correlation_id' => $correlationId]);

        try {
            // Get user message and session
            $userMessage = $validated['message'];
            $userId = auth()->id();
            $sessionId = $validated['session_id'] ?? null;
            $requestId = $validated['request_id'] ?? $correlationId;
            $detailedEmotionShadow = null;
            $emotionResponseContext = null;
            $pilotShadowAttempted = false;

            if (!$userId) {
                return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
            }

            $privacySetting = PrivacySetting::getOrCreateForUser($userId);
            if (!$privacySetting->canUseAI()) {
                return response()->json([
                    'success' => false,
                    'code' => 'AI_CONSENT_REQUIRED',
                    'error' => 'AI recommendations are disabled in your privacy settings.',
                ], 403);
            }
            
            if (isset($validated['user_id']) && (int) $validated['user_id'] !== (int) $userId) {
                \Log::warning('[USER_ID_MISMATCH]', ['auth_id' => $userId, 'body_id' => $validated['user_id']]);
            }
            // Return a completed result for the same logical browser send before
            // taking the per-session generation lock. This prevents duplicate DB
            // records and Ollama jobs after a dropped browser response.
            $idempotencyKey = 'mindcare:chat-response:' . $userId . ':' . $requestId;
            $completedResponse = Cache::get($idempotencyKey);
            if (is_array($completedResponse)) {
                \Log::info('[CHAT_IDEMPOTENT_REPLAY]', [
                    'request_id' => $requestId,
                    'user_id' => $userId,
                ]);
                return response()->json($completedResponse);
            }

            if ($sessionId !== null && !AIConversationSession::whereKey($sessionId)->where('user_id', $userId)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Session not found or access denied',
                ], 403);
            }
            
            \Log::info('[CHAT_VALIDATION_PASS] Message validated', [
                'message_length' => strlen($userMessage),
                'user_id' => $userId
            ]);

            // Get or create AI conversation session
            \Log::info('[SESSION_GET_START]', ['user_id' => $userId, 'session_id' => $sessionId]);
            $session = $this->getOrCreateSession($userId, $sessionId);
            $sessionId = $session->id;
            \Log::info('[SESSION_GET_SUCCESS]', ['session_id' => $sessionId]);

            // One active generation per authenticated student/session prevents a second
            // browser retry from launching duplicate Ollama work while the first request
            // is still running. The lock is released on every exit path below.
            $lockSeconds = max(30, (int) config('services.mindcare_ml.chat.lock_seconds', 150));
            $chatLock = Cache::lock('mindcare:chat:' . $userId . ':' . $sessionId, $lockSeconds);
            if (!$chatLock->get()) {
                return response()->json([
                    'success' => false,
                    'available' => true,
                    'code' => 'CHAT_IN_PROGRESS',
                    'error' => 'Your previous message is still being processed. Please wait for it to finish before retrying.',
                ], 409);
            }

            // ========== NEW: CRISIS CONFIRMATION FLOW ==========
            // Check if this session is awaiting a confirmation response
            $isConfirmationResponse = false;
            if ($this->crisisConfirmationFlowService->isAwaitingConfirmation($session)) {
                $isConfirmationResponse = true;
                \Log::info('[CONFIRMATION_RESPONSE_DETECTED]', [
                    'session_id' => $sessionId,
                    'message_length' => strlen($userMessage)
                ]);
                
                // Get session context for Gemma reassessment
                $sessionContext = $this->buildSessionContext($session, 5);
                
                // A clear first-person denial, without independent danger
                // evidence, resolves a prior clarification safely. Do not ask
                // Gemma to reinterpret the same denied phrase as fresh intent.
                $denialAnalysis = $this->enhancedCrisisService->analyzeMessage($userMessage);
                if ($this->isExplicitCrisisDenial($userMessage, $denialAnalysis)) {
                    $reassessmentResult = [
                        'risk_category' => 'SAFE',
                        'confidence' => 0.9,
                        'reason' => 'explicit_denial_without_new_danger_evidence',
                    ];
                } else {
                    $reassessmentResult = $this->crisisContextualAssessmentService->assessMessage(
                        $userMessage,
                        $sessionContext
                    );
                }
                
                // Process the confirmation and resolve to final crisis level
                $resolvedCrisisLevel = $this->crisisConfirmationFlowService->processConfirmation(
                    $session,
                    $userMessage,
                    $reassessmentResult
                );
                
                // Clear the awaiting state
                $this->crisisConfirmationFlowService->clearState($session);
                
                \Log::info('[CONFIRMATION_RESOLVED]', [
                    'from_risk_category' => $reassessmentResult['risk_category'],
                    'to_crisis_level' => $resolvedCrisisLevel,
                    'confidence' => $reassessmentResult['confidence']
                ]);
                
                // For confirmation responses, use the resolved crisis level
                // This will be used later in the crisis decision logic
                $forcedCrisisLevel = $resolvedCrisisLevel;
            } else {
                $forcedCrisisLevel = null; // Not a confirmation response
            }

            // Detect emotion
            \Log::info('[EMOTION_DETECT_START]');
            $emotionData = $this->emotionService->detectEmotion($userMessage);
            $emotion = $emotionData['emotion'] ?? 'neutral';
            \Log::info('[EMOTION_DETECT_SUCCESS]', [
                'emotion' => $emotion, 
                'intensity' => $emotionData['intensity'] ?? 0,
                'confidence' => $emotionData['confidence'] ?? 0,
                'raw_emotion_data' => $emotionData
            ]);
            
            // ========== GEMMA-FIRST CRISIS ASSESSMENT ==========
            // Step 1: Run deterministic pre-screen to identify crisis candidates
            $isMetalinguisticSafetyText = $this->isMetalinguisticSafetyText($userMessage);
            $riskScreen = $isMetalinguisticSafetyText
                ? ['level' => 'none', 'rules' => []]
                : app(\App\Services\RiskPhraseScreen::class)->screen($userMessage);
            // Run EnhancedCrisisAnalysis for additional context
            $enhancedCrisisAnalysis = $isMetalinguisticSafetyText
                ? $this->noCrisisAnalysis()
                : $this->enhancedCrisisService->analyzeMessage($userMessage);

            // The deterministic layer is a safety floor. Contextual Gemma
            // output may add nuance, but it must never downgrade an explicit
            // critical signal when the bridge is unavailable or uncertain.
            $enhancedLevel = $enhancedCrisisAnalysis['crisis_level'] ?? 'none';
            $deterministicSafetyLevel = \App\Services\RiskPhraseScreen::higher(
                $riskScreen['level'] ?? 'none',
                $enhancedLevel
            );
            $isDeterministicCandidate = in_array($deterministicSafetyLevel, ['severe', 'critical'], true);
            
            \Log::info('[CRISIS_ASSESSMENT_STAGE_1_DETERMINISTIC]', [
                'risk_phrase_level' => $riskScreen['level'],
                'is_candidate' => $isDeterministicCandidate,
                'enhanced_level' => $enhancedCrisisAnalysis['crisis_level'] ?? 'none',
                'assessment_stage' => 'deterministic_prescreen',
            ]);
            
            // Step 2: Check if this is an emergency override (unmistakable immediate danger)
            // This is the ONLY time deterministic assessment can skip Gemma
            $emergencyOverride = false;
            $crisisLevel = 'none';
            $crisisConfidence = 0;
            $gemmaAssessment = null;
            $shouldBypassNormalGeneration = false;
            
            // Emergency override: explicitly deterministic HIGH_RISK from multi-factor analysis
            // Uses EnhancedCrisisAnalysisService signals to detect unmistakable immediate danger
            // NOT: brittle conjunction of all four factors (plan && intent && method && timing)
            // Instead: weighted evidence of immediate high risk
            $emergencySignals = [
                'has_intent' => $enhancedCrisisAnalysis['intent_detected'] ? 1 : 0,
                'has_plan' => $enhancedCrisisAnalysis['plan_detected'] ? 1 : 0,
                'has_method' => $enhancedCrisisAnalysis['method_detected'] ? 1 : 0,
                'has_access' => $enhancedCrisisAnalysis['access_detected'] ? 1 : 0,
                'is_immediate' => ($enhancedCrisisAnalysis['temporal_urgency'] ?? 0) >= 0.8 ? 1 : 0,
                'is_recurrent' => $enhancedCrisisAnalysis['recurrence_detected'] ? 1 : 0,
            ];
            $emergencySignalCount = array_sum($emergencySignals);
            
            // Emergency override if:
            // - Intent + method + immediate = 3+ signals (unmistakable danger: "I'm taking pills right now")
            // - Intent + plan + method + access = 4+ signals (unmistakable danger with preparation)
            // - Intent + immediate + access (strong immediate risk)
            // Do NOT require all four - that's too brittle
            $hasUnmistakableDanger = (
                ($emergencySignals['has_intent'] && $emergencySignals['has_method'] && $emergencySignals['is_immediate']) ||
                ($emergencySignals['has_intent'] && $emergencySignals['has_plan'] && $emergencySignals['has_method'] && $emergencySignals['has_access']) ||
                ($emergencySignals['has_intent'] && $emergencySignals['is_immediate'] && $emergencySignals['has_access'])
            );
            
            if ($hasUnmistakableDanger && $emergencySignalCount >= 3) {
                $emergencyOverride = true;
                $crisisLevel = 'critical';
                $crisisConfidence = 0.95;
                \Log::warning('[EMERGENCY_OVERRIDE_TRIGGERED]', [
                    'reason' => 'unmistakable_immediate_danger',
                    'emergency_signals' => $emergencySignals,
                    'signal_count' => $emergencySignalCount,
                    'assessment_stage' => 'emergency_deterministic_floor',
                ]);
            }
            // Step 3: Contextual review is reserved for a safety candidate.
            // Previously every ordinary message was sent to this assessor, which
            // allowed sadness or presentation anxiety to trigger confirmation.
            else if ($isConfirmationResponse && $forcedCrisisLevel !== null) {
                // Handle confirmation response: use forced crisis level from previous confirmation
                $crisisLevel = $forcedCrisisLevel;
                $crisisConfidence = 0.85;
                \Log::info('[CRISIS_ASSESSMENT_STAGE_3_CONFIRMATION_RESOLVED]', [
                    'from_confirmation' => true,
                    'crisis_level' => $crisisLevel,
                    'assessment_stage' => 'confirmation_resolved',
                ]);
            } else if ($this->requiresContextualCrisisAssessment($userMessage, $riskScreen, $enhancedCrisisAnalysis)) {
                // GEMMA-FIRST: Run contextual assessment for primary crisis classification
                $sessionContext = $this->buildSessionContext($session, 5);
                $gemmaAssessment = $this->crisisContextualAssessmentService->assessMessage(
                    $userMessage,
                    $sessionContext
                );
                
                \Log::info('[CRISIS_ASSESSMENT_STAGE_2_GEMMA]', [
                    'gemma_called' => true,
                    'gemma_result' => $gemmaAssessment['risk_category'],
                    'gemma_confidence' => $gemmaAssessment['confidence'],
                    'assessment_stage' => 'gemma_primary',
                ]);
                
                // Map Gemma's risk category to crisis level
                $crisisLevel = match($gemmaAssessment['risk_category']) {
                    'HIGH_RISK' => 'critical',
                    'NEEDS_CONFIRMATION' => 'moderate',
                    'SAFE' => 'none',
                    default => 'moderate',
                };
                $crisisConfidence = $gemmaAssessment['confidence'];

                // A contextual NEEDS_CONFIRMATION result cannot lower a
                // critical deterministic result. This is intentionally based
                // on the independent safety analyzers, not on a phrase list
                // or on the model's category.
                if ($deterministicSafetyLevel === 'critical' && $crisisLevel !== 'critical') {
                    $crisisLevel = 'critical';
                    $crisisConfidence = max($crisisConfidence, 0.8);
                    \Log::warning('[DETERMINISTIC_CRITICAL_FLOOR_PRESERVED]', [
                        'risk_phrase_level' => $riskScreen['level'] ?? 'none',
                        'enhanced_level' => $enhancedLevel,
                        'contextual_category' => $gemmaAssessment['risk_category'] ?? null,
                    ]);
                }
                
                // Handle NEEDS_CONFIRMATION: Initialize confirmation flow
                if ($gemmaAssessment['risk_category'] === 'NEEDS_CONFIRMATION' && $crisisLevel !== 'critical') {
                    $this->crisisConfirmationFlowService->initializeConfirmation($session, $gemmaAssessment);
                    
                    \Log::info('[CONFIRMATION_FLOW_INITIALIZED]', [
                        'session_id' => $sessionId,
                        'question' => substr($gemmaAssessment['clarification_question'], 0, 80),
                        'assessment_stage' => 'confirmation_initialized',
                    ]);
                    
                    $aiResponse = [
                        'success' => true,
                        'response' => $gemmaAssessment['clarification_question'],
                        'analysis' => [
                            'emotion' => $emotion,
                            'crisis_level' => 'moderate',
                            'response_mode' => 'confirmation_needed',
                            'contextual_assessment' => $gemmaAssessment
                        ],
                        // Preserve provenance for the Laravel confirmation response
                        // without changing its safety decision or confirmation flow.
                        'ml_system' => 'crisis_confirmation',
                        'response_source' => 'crisis_confirmation_handler',
                    ];
                    
                    $shouldBypassNormalGeneration = true; // Return clarification, don't call Ollama
                    $skipToResponse = true; // IMPORTANT: Skip the normal decision logic below
                } else {
                    $skipToResponse = false;
                }
            } else {
                $skipToResponse = false;
                $gemmaAssessment = [
                    'risk_category' => 'SAFE',
                    'confidence' => 1.0,
                    'reason' => 'no_safety_candidate',
                ];
                \Log::info('[CRISIS_CONTEXTUAL_ASSESSMENT_SKIPPED]', [
                    'reason' => 'no_existing_safety_candidate',
                    'risk_phrase_level' => $riskScreen['level'],
                    'enhanced_level' => $enhancedCrisisAnalysis['crisis_level'] ?? 'none',
                ]);
            }
            
            // Step 4: Check deterministic safety floor as final override
            // If deterministic check says CRITICAL but Gemma said otherwise, apply floor
            if (!$emergencyOverride && $isDeterministicCandidate && $crisisLevel !== 'critical') {
                // Deterministic check wants to escalate
                // But don't override NEEDS_CONFIRMATION - that's valid from Gemma
                if ($crisisLevel !== 'moderate' || $deterministicSafetyLevel === 'critical') {
                    $crisisLevel = 'critical';
                    $crisisConfidence = max($crisisConfidence, 0.8);
                    \Log::info('[DETERMINISTIC_FLOOR_APPLIED]', [
                        'reason' => 'safety_floor_escalation',
                        'assessment_stage' => 'deterministic_floor_applied',
                    ]);
                }
            }
            
            // If we already have a response ready (e.g., NEEDS_CONFIRMATION), skip to final processing
            if (isset($skipToResponse) && $skipToResponse === true) {
                \Log::info('[SKIPPING_NORMAL_DECISION_LOGIC]', ['reason' => 'confirmation_question_ready']);
                // Jump to final response processing below
            } else {
                $enhancedCrisisAnalysis += ['intent_detected' => false, 'plan_detected' => false,
                'method_detected' => false, 'access_detected' => false,
                ];
                
                // ========== DECISION: CALL FLASK/OLLAMA OR RETURN CRISIS-SAFE ==========
                $isCriticalCrisis = ($crisisLevel === 'critical');
                $shouldBypassNormalGeneration = $shouldBypassNormalGeneration || $isCriticalCrisis;
                
                \Log::info('[ML_GENERATION_DECISION]', [
                    'is_critical_crisis' => $isCriticalCrisis,
                    'bypass_normal_generation' => $shouldBypassNormalGeneration,
                    'response_mode' => $shouldBypassNormalGeneration ? 'crisis_safe' : 'normal'
                ]);
                
                    // Call Python AI only for non-crisis or lower-crisis cases
                    if (!$shouldBypassNormalGeneration) {
                // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ FIX 2: Call Python AI system with bounded error recovery
                // This ensures Laravel ALWAYS returns a response, preventing React promise from hanging
                \Log::info('[ML_REQUEST_START]', [
                    'message_length' => strlen($userMessage), 
                    'crisis_mode' => 'normal',
                    'crisis_level' => $crisisLevel,
                    'correlation_id' => $correlationId
                ]);
                $startTime = microtime(true);
                
                try {
                    // TASK 6: Pass crisis level to Flask so it doesn't over-escalate
                    $recentMessages = $this->chatService->getConversationHistoryBySession($sessionId, max(1, min(24, (int) config('services.mindcare_ml.chat.recent_history_turns', 12))));
                    $explicitCounselorRequest = $this->isExplicitCounselorRequest($userMessage);
                    $counselorAcceptance = $this->isCounselorReferralAcceptance($userMessage, $recentMessages);
                    $counselorContext = app(\App\Services\CounselorDirectoryService::class)->aiContext();
                    $counselorContext['explicit_request'] = $explicitCounselorRequest;
                    $counselorContext['accepted_previous_offer'] = $counselorAcceptance;
                    // Optional local-only conversational pilot. The shadow
                    // result is obtained before the one normal Gemma response
                    // so it can enrich that same generation without launching
                    // a second response generation. Crisis paths never reach
                    // this block.
                    if ($this->emotionResponsePilotEnabled((int) $userId)) {
                        $pilotShadowAttempted = true;
                        try {
                            $shadowResponse = app(InferenceBridgeClient::class)->detailedEmotionShadow(
                                (int) $userId,
                                $userMessage,
                                $requestId . '-shadow'
                            );
                            $shadow = is_array($shadowResponse['shadow'] ?? null)
                                ? $shadowResponse['shadow']
                                : null;
                            $detailedEmotionShadow = $this->sanitizeDetailedEmotionShadow($shadow);
                            $emotionResponseContext = $this->buildEmotionResponseContext($detailedEmotionShadow);
                            \Log::info('[DETAILED_EMOTION_RESPONSE_PILOT_CUE]', [
                                'request_id' => $requestId,
                                'accepted' => is_array($emotionResponseContext),
                            ]);
                        } catch (\Throwable $shadowError) {
                            // A pilot failure must be invisible to the student
                            // and must leave the ordinary message as the only
                            // conversational source of truth.
                            \Log::warning('[DETAILED_EMOTION_RESPONSE_PILOT_UNAVAILABLE]', [
                                'request_id' => $requestId,
                                'code' => $shadowError->getMessage(),
                            ]);
                        }
                    }
                    $aiResponse = $this->callPythonAI(
                        $userMessage,
                        $userId,
                        $crisisLevel,
                        $emergencyOverride,
                        $sessionId,
                        $emotionData,
                        $correlationId,
                        $counselorContext,
                        $requestId,
                        $emotionResponseContext
                    );
                    $elapsed = (microtime(true) - $startTime) * 1000;
                    \Log::info('[ML_REQUEST_SUCCESS]', [
                        'elapsed_ms' => $elapsed, 
                        'has_response' => isset($aiResponse['response']),
                        'correlation_id' => $correlationId
                    ]);
                    
                } catch (MlServiceBusyException $e) {
                    $elapsed = (microtime(true) - $startTime) * 1000;
                    \Log::info('[AI_GPU_QUEUE_BUSY]', [
                        'code' => $e->queueCode(),
                        'elapsed_ms' => $elapsed,
                        'correlation_id' => $correlationId,
                    ]);
                    return response()->json([
                        'success' => false,
                        'available' => true,
                        'code' => $e->queueCode(),
                        'error' => 'The local AI is busy. Please wait briefly and retry.',
                        'retry_after_seconds' => $e->retryAfterSeconds(),
                    ], $e->status());
                } catch (\Throwable $e) {
                    // Catch ANY exception in ML chain (timeout, process error, database error, etc)
                    $elapsed = (microtime(true) - $startTime) * 1000;
                    \Log::error('[AI_RUNTIME_UNAVAILABLE]', [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'elapsed_ms' => $elapsed,
                        'correlation_id' => $correlationId
                    ]);
                    
                    // Return HTTP 503 for unavailable AI - fail visibly
                    return response()->json([
                        'success' => false,
                        'available' => false,
                        'code' => 'AI_UNAVAILABLE',
                        'error' => 'AI Companion is currently unavailable. Please start the ML/LLM service and try again.'
                    ], 503);
                }
            } else {
                // CRISIS MODE: Use controlled crisis-safe response
                \Log::info('[CRISIS_RESPONSE_MODE] Bypassing unrestricted Ollama generation for crisis message');
                
                // Use existing crisis response mechanism (hotlines + resources)
                $crisisResponse = $this->getCrisisSafeResponse($userMessage, $enhancedCrisisAnalysis);
                
                $aiResponse = [
                    'success' => true,
                    'response' => $crisisResponse,
                    'analysis' => [
                        'emotion' => $emotion,
                        'crisis_level' => $crisisLevel,
                        'response_mode' => 'crisis_safe',
                        'authoritative_crisis_analysis' => $enhancedCrisisAnalysis
                    ],
                    'ml_system' => 'crisis_safe',
                    'response_source' => 'crisis_response_handler'
                ];
                
                \Log::info('[CRISIS_RESPONSE_GENERATED]', [
                    'crisis_level' => $crisisLevel,
                    'response_length' => strlen($crisisResponse)
                ]);
            }
            } // End of skipToResponse else block
            
            // Get user preference mode (needed for all response types)
            if (!isset($aiPreferenceMode)) {
                $privacySetting = PrivacySetting::where('user_id', $userId)->first();
                $aiPreferenceMode = $privacySetting ? $privacySetting->ai_preference_mode : 'companion';
            }

            // Get conversation history for THIS SESSION only (needed for all response types)
            if (!isset($recentMessages)) {
                $recentMessages = $this->chatService->getConversationHistoryBySession($sessionId, max(1, min(24, (int) config('services.mindcare_ml.chat.recent_history_turns', 12))));
            }
            
            // Clean response: Preserve newlines for paragraph formatting
            if (isset($aiResponse['response'])) {
                // Convert trusted deterministic <br> separators before stripping markup.
                // The browser escapes API text, then renders these newlines safely.
                $aiResponse['response'] = $this->sanitizeResponseText($aiResponse['response']);
            }
            
            // Get counselor suggestion data from ML API if available
            $counselorSuggestion = $aiResponse['counselor_suggestion'] ?? [
                'should_suggest' => false,
                'reason' => '',
                'message' => null,
                'connection_prompt' => null,
            ];
            
            // If fallback response, ensure no repetition
            if (($aiResponse['ml_system'] ?? '') === 'fallback') {
                $aiResponse['response'] = $this->ensureNoRepetition($aiResponse['response'], $recentMessages);
            }

            // Flask performs the final confidence-aware routing (trained model or
            // semantic fallback). Use that final value everywhere user-visible and
            // persisted, while retaining Laravel's preliminary signal separately.
            $finalEmotionData = $this->authoritativeEmotionService->resolve($emotionData, $aiResponse);
            $emotion = $finalEmotionData['emotion'];
            $aiResponse['analysis'] = array_merge($aiResponse['analysis'] ?? [], [
                'emotion' => $finalEmotionData['emotion'],
                'confidence' => $finalEmotionData['confidence'],
                'method' => $finalEmotionData['source'],
                'confidence_type' => $finalEmotionData['confidence_type'],
                'confidence_is_calibrated' => $finalEmotionData['confidence_is_calibrated'],
                'uncertain' => $finalEmotionData['uncertain'],
                'preliminary_emotion' => $finalEmotionData['preliminary_emotion'],
                'preliminary_confidence' => $finalEmotionData['preliminary_confidence'],
            ]);

            // Optional, non-persisting 35-emotion local pilot. It is deliberately
            // separate from the authoritative six-class result and never runs for
            // crisis-safe/confirmation responses.
            if (!$pilotShadowAttempted && !$shouldBypassNormalGeneration
                && config('services.mindcare_ml.detailed_emotion_shadow.enabled', false)
                && config('services.mindcare_ml.detailed_emotion_shadow.approved', false)) {
                try {
                    $shadowResponse = app(InferenceBridgeClient::class)->detailedEmotionShadow(
                        (int) $userId,
                        $userMessage,
                        $requestId . '-shadow'
                    );
                    $shadow = is_array($shadowResponse['shadow'] ?? null)
                        ? $shadowResponse['shadow']
                        : null;
                    if ($shadow) {
                        // Keep only validated, non-sensitive structured fields in
                        // the API response. The raw message and evidence are never
                        // persisted or echoed by this pilot path.
                        $detailedEmotionShadow = [
                            'status' => $shadow['status'] ?? null,
                            'attribution_scope' => $shadow['attribution_scope'] ?? null,
                            'primary_emotion' => $shadow['primary_emotion'] ?? null,
                            'secondary_emotions' => $shadow['secondary_emotions'] ?? [],
                            'other_emotions' => $shadow['other_emotions'] ?? [],
                            'uncertainty_reason' => $shadow['uncertainty_reason'] ?? null,
                            'classification_source' => $shadow['classification_source'] ?? 'gemma_shadow',
                            'taxonomy_version' => $shadow['taxonomy_version'] ?? null,
                            'category_key' => $shadow['category_key'] ?? null,
                            'category_keys' => $shadow['category_keys'] ?? [],
                            'other_category_keys' => $shadow['other_category_keys'] ?? [],
                            'felt_by_student' => $shadow['felt_by_student'] ?? null,
                            'schema_valid' => (bool) ($shadow['schema_valid'] ?? false),
                            'shadow_only' => true,
                            'stored' => false,
                            'latency_ms' => $shadow['latency_ms'] ?? null,
                        ];
                        // Re-derive categories from Laravel's controlled
                        // taxonomy before exposing or persisting the result.
                        [$derivedCategories, $derivedPrimaryCategory] = $this->deriveDetailedShadowCategories(
                            $detailedEmotionShadow['primary_emotion'],
                            $detailedEmotionShadow['secondary_emotions']
                        );
                        $detailedEmotionShadow['category_keys'] = $derivedCategories;
                        $detailedEmotionShadow['category_key'] = $derivedPrimaryCategory;
                    }
                } catch (\Throwable $shadowError) {
                    // Shadow failures never fail or alter the student response.
                    \Log::warning('[DETAILED_EMOTION_SHADOW_UNAVAILABLE]', [
                        'request_id' => $requestId,
                        'code' => $shadowError->getMessage(),
                    ]);
                }
            }

            // Save the interaction and refresh the non-clinical wellbeing indicator.
            $storedEntry = $this->chatService->storeMessage(
                $userId,
                $userMessage,
                $aiResponse['response'],
                $aiResponse['analysis'] ?? [],
                $sessionId
            );

            // Persistence is a separate opt-in gate from shadow execution. The
            // default local pilot remains non-persisting; when explicitly
            // enabled, only validated structured fields are written after the
            // normal message pair has already been saved.
            $this->persistDetailedEmotionShadow($storedEntry, $detailedEmotionShadow);

            // Refresh only session-bound context after a complete, authorized
            // message pair exists. A failed or cancelled generation is never added.
            try {
                app(ConversationContextService::class)->refreshSessionDigest($session, (int) $userId);
            } catch (\Throwable $contextError) {
                \Log::warning('[SESSION_CONTEXT_REFRESH_FAILED]', [
                    'session_id' => $sessionId,
                    'error' => $contextError->getMessage(),
                ]);
            }

            // Calculate the non-clinical wellbeing indicator from this interaction.
            $wellbeingMetrics = [
                'wellbeing_score' => 50,
                'stress_level' => 50,
                'emotional_stability' => 50,
            ];
            try {
                $wellbeingMetrics = $this->wellbeingCalculator->calculateWellbeing(
                    $userId,
                    $finalEmotionData,
                    array_merge($enhancedCrisisAnalysis, [
                        'crisis_level' => $crisisLevel,
                        'crisis_confidence' => $crisisConfidence,
                    ])
                );
                $progress = $this->wellbeingCalculator->saveMetrics($userId, $wellbeingMetrics);
                $progress->increment('entries_count');
            } catch (\Throwable $analyticsError) {
                \Log::warning('[OPTIONAL_WELLBEING_ANALYTICS_FAILED]', [
                    'user_id' => $userId,
                    'entry_id' => $storedEntry->id,
                    'error' => $analyticsError->getMessage(),
                ]);
            }

            // Calculate non-diagnostic referral context from the current interaction.
            $situationWeightService = new SituationWeightService();
            $weightData = $situationWeightService->calculateWeight(
                $userMessage,
                $aiResponse['analysis'] ?? [],
                $finalEmotionData,
                $enhancedCrisisAnalysis  // ÃƒÂ¢Ã¢â‚¬Â Ã‚Â NEW: Use authoritative Laravel crisis analysis
            );
            
            \Log::info('[SITUATION_WEIGHT_CALCULATED]', [
                'weight' => $weightData['weight'],
                'level' => $weightData['level'],
                'input_crisis_level' => $enhancedCrisisAnalysis['crisis_level'],
                'consistent_with_crisis_decision' => true
            ]);
            
            $finalResponse = $aiResponse['response'];

            // Determine if counselor should be suggested
            $shouldSuggestCounselor = $situationWeightService->shouldSuggestCounselor(
                $weightData['weight'],
                $weightData['level'],
                $aiPreferenceMode
            );
            $explicitCounselorRequest ??= $this->isExplicitCounselorRequest($userMessage);
            $counselorAcceptance ??= $this->isCounselorReferralAcceptance($userMessage, $recentMessages);
            $counselorReferral = app(\App\Services\CounselorDirectoryService::class)->referral(
                $crisisLevel !== 'critical' && ($shouldSuggestCounselor || $explicitCounselorRequest || $counselorAcceptance)
            );

            // If situation warrants counselor but not already in response, mark for suggestion
            if ($shouldSuggestCounselor && stripos($finalResponse, 'counselor') === false) {
                $counselorSuggestion['should_suggest'] = true;
            }
            
            // Only direct current-message crisis evidence can initiate escalation.
            $shouldAutoCreateAppointment = false;
            $appointmentCreated = false;
            
            // Critical crisis always triggers appointment (with high confidence)
            if ($crisisLevel === 'critical' && $crisisConfidence >= 0.7) {
                $shouldAutoCreateAppointment = true;
            }
            
            // Severe crisis with intent/plan triggers appointment
            if ($crisisLevel === 'severe' && 
                ($enhancedCrisisAnalysis['intent_detected'] || $enhancedCrisisAnalysis['plan_detected'])) {
                $shouldAutoCreateAppointment = true;
            }
            
            if ($shouldAutoCreateAppointment) {
                $appointmentCreated = $this->handleCrisis(
                    $userId, 
                    $userMessage, 
                    $crisisLevel
                );
            }
            
            // Optional secondary analytics require current, purpose-specific consent.
            if ($privacySetting->hasCurrentConsent('share_analytics')) {
                try {
                    $this->mentalHealthService->recordUsage($userId, $emotion, $crisisLevel);
                } catch (\Throwable $analyticsError) {
                    \Log::warning('[OPTIONAL_USAGE_ANALYTICS_FAILED]', [
                        'user_id' => $userId,
                        'entry_id' => $storedEntry->id,
                        'error' => $analyticsError->getMessage(),
                    ]);
                }
            }
            // ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬ÃƒÂ¢Ã¢â‚¬ÂÃ¢â€šÂ¬

            // Build final response
            $responseData = [
                'success'   => true,
                'response'  => $finalResponse,
                'emotion'   => $emotion,
                'session_id' => $sessionId,
                // CRITICAL: Preserve Flask metadata for tracing response source
                'ml_system' => $aiResponse['ml_system'] ?? 'unknown',
                'response_source' => $aiResponse['response_source'] ?? 'unknown',
                'analysis'  => $aiResponse['analysis'] ?? [],
                'actions'   => $aiResponse['actions'] ?? [],
                'resources' => $aiResponse['resources'] ?? [],
                'situation' => [
                    'weight' => $weightData['weight'],
                    'level' => $weightData['level'],
                    'shouldSuggestCounselor' => $shouldSuggestCounselor,
                    'factors' => $weightData['factors'] ?? [],
                    'auto_appointment_created' => $appointmentCreated,
                ],
                'emotion_data' => [
                    'emotion' => $finalEmotionData['emotion'],
                    'intensity' => $finalEmotionData['intensity'],
                    'confidence' => $finalEmotionData['confidence'],
                    'source' => $finalEmotionData['source'],
                    'confidence_type' => $finalEmotionData['confidence_type'],
                    'confidence_is_calibrated' => $finalEmotionData['confidence_is_calibrated'],
                    'uncertain' => $finalEmotionData['uncertain'],
                    'preliminary_emotion' => $finalEmotionData['preliminary_emotion'],
                    'preliminary_confidence' => $finalEmotionData['preliminary_confidence'],
                ],
                'crisis_analysis' => [
                    'level' => $crisisLevel,
                    'confidence' => $crisisConfidence,
                    'sarcasm_detected' => $enhancedCrisisAnalysis['sarcasm_detected'] ?? false,
                    'satire_detected' => $enhancedCrisisAnalysis['satire_detected'] ?? false,
                    'hyperbole_detected' => $enhancedCrisisAnalysis['hyperbole_detected'] ?? false,
                    'temporal_urgency' => $enhancedCrisisAnalysis['temporal_urgency'] ?? 0,
                    'intent_detected' => $enhancedCrisisAnalysis['intent_detected'] ?? false,
                    'plan_detected' => $enhancedCrisisAnalysis['plan_detected'] ?? false,
                    'method_detected' => $enhancedCrisisAnalysis['method_detected'] ?? false,
                    'access_detected' => $enhancedCrisisAnalysis['access_detected'] ?? false,
                    'recurrence_detected' => $enhancedCrisisAnalysis['recurrence_detected'] ?? false,
                ],
                'wellbeing' => [
                    'wellbeing_score' => $wellbeingMetrics['wellbeing_score'],
                    'stress_level' => $wellbeingMetrics['stress_level'],
                    'emotional_stability' => $wellbeingMetrics['emotional_stability'],
                ],
                'counselor_suggestion' => [
                    'should_suggest' => $counselorSuggestion['should_suggest'] ?? false,
                    'reason' => $counselorSuggestion['reason'] ?? '',
                    'severity' => $counselorSuggestion['reason'] ? ($aiResponse['analysis']['severity'] ?? 0) : 0,
                    'message' => $counselorSuggestion['message'] ?? null,
                    'connection_prompt' => $counselorSuggestion['connection_prompt'] ?? null,
                ],
                'counselor_referral' => $counselorReferral,
            ];

            Cache::put($idempotencyKey, $responseData, now()->addSeconds(max(
                60,
                (int) config('services.mindcare_ml.chat.idempotency_ttl_seconds', 900)
            )));


            
            // Attempt to encode the response to check for serialization issues
            try {
                $encoded = json_encode($responseData);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    \Log::error('[JSON_ENCODE_ERROR]', [
                        'error' => json_last_error_msg(),
                        'code' => json_last_error()
                    ]);
                }
                \Log::info('[JSON_ENCODE_SUCCESS]', ['json_length' => strlen($encoded)]);
            } catch (\Exception $e) {
                \Log::error('[JSON_ENCODE_EXCEPTION]', ['error' => $e->getMessage()]);
            }
            
            \Log::info('[RESPONSE_RETURN_START]');
            
            // Metadata-only diagnostic event; never include message, memory, or identity data.
            \Log::info('[MINDCARE_DIAGNOSTIC]', [
                'component' => 'laravel',
                'event' => 'chat_complete',
                'correlation_id' => $correlationId,
                'duration_ms' => round((\microtime(true) - $requestStarted) * 1000, 2),
                'response_source' => $responseData['response_source'] ?? 'unknown',
                // Internal reason code only; never returned in the student payload.
                'fallback_reason' => $aiResponse['fallback_reason'] ?? 'none',
                'ml_system' => $responseData['ml_system'] ?? 'unknown',
                'emotion' => $responseData['emotion_data']['emotion'] ?? 'unknown',
                'emotion_confidence' => $responseData['emotion_data']['confidence'] ?? null,
                // Report the exact pre-write history count sent to Flask.
                'history_turns' => (int) ($aiResponse['_history_turns_sent'] ?? count($recentMessages ?? [])),
                'crisis_level' => $crisisLevel,
                // Do not put contextual-assessment prose in diagnostics: it may
                // contain a fragment of a private message. Keep metadata-only.
                'crisis_routing' => ($gemmaAssessment['reason'] ?? null) === 'no_safety_candidate'
                    ? 'no_safety_candidate'
                    : ($emergencyOverride ? 'emergency_deterministic_floor' : 'contextual_assessment'),
                'counselor_lookup_outcome' => $counselorReferral['lookup_outcome'],
                'counselor_referral_offered' => $counselorReferral['offered'],
            ]);
            \Log::info('[TRACE_END]', ['correlation_id' => $correlationId]);
            
            return response()->json($responseData);

        } catch (\Exception $e) {
            \Log::error('[CHAT_REQUEST_EXCEPTION]', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Unable to process your message. Please try again.',
            ], 500);
        } finally {
            if ($chatLock !== null) {
                $chatLock->release();
            }
        }
    }

    /**
     * Get or create an AI conversation session
     */
    private function getOrCreateSession($userId, $sessionId = null)
    {
        if ($sessionId) {
            // Try to find existing session
            $session = AIConversationSession::find($sessionId);
            if ($session && $session->user_id == $userId) {
                // Update last message timestamp
                $session->update(['last_message_at' => now()]);
                return $session;
            }
        }

        // Create new session
        return AIConversationSession::create([
            'user_id' => $userId,
            'session_token' => uniqid('ai_session_', true),
            'title' => 'AI Conversation',
            'last_message_at' => now(),
        ]);
    }

    /**
     * Ensure response doesn't repeat recent messages
     */
    private function ensureNoRepetition($response, $recentMessages)
    {
        // Get last AI response
        $lastAIResponse = null;
        foreach ($recentMessages as $msg) {
            // Handle both old format (role/content) and new format (user/ai)
            if (isset($msg['role']) && $msg['role'] === 'assistant' && isset($msg['content'])) {
                $lastAIResponse = $msg['content'];
                break;
            } elseif (isset($msg['ai'])) {
                $lastAIResponse = $msg['ai'];
                break;
            }
        }

        // If response is too similar to last one, generate alternative
        if ($lastAIResponse && $this->isSimilar($response, $lastAIResponse)) {
            // Generate alternative response
            return $this->generateAlternativeResponse($response);
        }

        return $response;
    }

    /**
     * Check if two responses are too similar
     */
    private function isSimilar($text1, $text2)
    {
        $words1 = array_filter(explode(' ', strtolower($text1)));
        $words2 = array_filter(explode(' ', strtolower($text2)));
        
        $common = count(array_intersect($words1, $words2));
        $total = max(count($words1), count($words2));
        
        $similarity = $total > 0 ? ($common / $total) : 0;
        
        return $similarity > 0.6; // More than 60% similar
    }

    /**
     * Generate alternative response to avoid repetition
     */
    private function generateAlternativeResponse($originalResponse)
    {
        $alternatives = [
            "I appreciate you sharing that. Let's dig a bit deeper - what do you think is driving these feelings?",
            "That's important to acknowledge. I'm wondering - what would help you feel better right now?",
            "I hear you. Sometimes it helps to take a step back. What's one thing that might make a difference today?",
            "Thank you for being open with me. What do you think would be a good first step forward?",
            "I'm listening. What's the most pressing thing on your mind right now?",
            "That sounds really challenging. Have you talked to anyone else about this?",
            "I can sense how much this is affecting you. What support do you need most right now?",
            "It takes courage to share this. What would make you feel less alone in this?",
            "I'm here with you. What's one thing you need to hear right now?",
            "That's a lot to carry. What's been the hardest part for you?"
        ];

        return $alternatives[array_rand($alternatives)];
    }

    /**
     * Get conversation history
     */
    public function getHistory(Request $request)
    {
        $userId = auth()->id();
        $limit = $request->get('limit', 50);

        $messages = $this->chatService->getConversationHistory($userId, $limit);

        return response()->json([
            'success' => true,
            'messages' => $messages
        ]);
    }

    /**
     * Get conversation summary
     */
    public function getSummary(Request $request)
    {
        $userId = auth()->id();

        $summary = $this->chatService->getConversationSummary($userId);

        return response()->json([
            'success' => true,
            'summary' => $summary
        ]);
    }

    /**
     * Clear conversation
     */
    public function clearConversation(Request $request)
    {
        $userId = auth()->id();

        $this->chatService->clearConversation($userId);

        return response()->json([
            'success' => true,
            'message' => 'Conversation cleared'
        ]);
    }

    /**
     * Submit feedback on response
     */
    public function submitFeedback(Request $request)
    {
        $validated = $request->validate([
            'message_id' => 'required|integer',
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:500'
        ]);

        try {
            $this->chatService->saveFeedback(
                $validated['message_id'],
                $validated['rating'],
                $validated['feedback'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your feedback!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user's AI preference mode (companion vs counselor)
     */
    public function updateAIPreference(Request $request)
    {
        $validated = $request->validate([
            'mode' => 'required|in:companion,counselor',
            'user_id' => 'nullable|integer',
        ]);

        try {
            $userId = (int) auth()->id();
            if (isset($validated['user_id']) && (int) $validated['user_id'] !== $userId) {
                return response()->json([
                    'success' => false,
                    'error' => 'You cannot update another user\'s AI preference.',
                ], 403);
            }

            $mode = $validated['mode'];

            $privacySetting = PrivacySetting::getOrCreateForUser($userId);

            // Update preference mode
            $privacySetting->update(['ai_preference_mode' => $mode]);

            return response()->json([
                'success' => true,
                'message' => "AI preference updated to {$mode} mode",
                'mode' => $mode
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's AI preference mode
     */
    public function getAIPreference(Request $request)
    {
        try {
            $userId = (int) auth()->id();

            $privacySetting = PrivacySetting::getOrCreateForUser($userId);
            $mode = $privacySetting->ai_preference_mode;

            return response()->json([
                'success' => true,
                'mode' => $mode,
                'description' => $mode === 'companion' 
                    ? 'Comfort-focused with tips and advice'
                    : 'Professional support recommendations'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Call Python AI system (UPGRADED with Unified ML API)
     */
    private function callPythonAI($message, $userId, $crisisLevel = 'none', $emergencyOverride = false, $sessionId = null, array $emotionData = [], $correlationId = null, array $counselorContext = [], ?string $requestId = null, ?array $emotionResponseContext = null)
    {
        \Log::info('[PYTHON_AI_START]', ['attempt' => 'ML_API_SERVER_ONLY']);
        
        try {
            $startTime = microtime(true);
            $response = $this->callMLAPIServer($message, $userId, $crisisLevel, $emergencyOverride, $sessionId, $emotionData, $correlationId, $counselorContext, $requestId, $emotionResponseContext);
            $elapsed = (microtime(true) - $startTime) * 1000;
            
            \Log::info('[ML_API_SERVER_RESPONSE]', [
                'elapsed_ms' => $elapsed,
                'success' => $response['success'] ?? null,
                'has_response' => !empty($response['response'] ?? null),
                'ml_system' => $response['ml_system'] ?? null,
            ]);
            
            // Validate that we received an ACTIVE AI response (not fallback)
            $mlSystem = strtolower((string) ($response['ml_system'] ?? ''));
            
            if (!isset($response['success']) ||
                $response['success'] !== true ||
                empty(trim((string) ($response['response'] ?? ''))) ||
                in_array($mlSystem, ['fallback', 'rule_based', 'rule-based', 'generic_fallback'], true)) {
                
                throw new \RuntimeException('ML/LLM service did not return an active AI response.');
            }
            
            \Log::info('[PYTHON_AI_SUCCESS]', ['source' => 'ML_API_SERVER', 'ml_system' => $mlSystem]);
            return $response;
        } catch (\Throwable $e) {
            \Log::error('[AI_RUNTIME_UNAVAILABLE]', [
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);
            throw new \RuntimeException('AI Companion is currently unavailable.', 0, $e);
        }
    }

    private function callMLAPIServer($message, $userId, $crisisLevel = 'none', $emergencyOverride = false, $sessionId = null, array $emotionData = [], $correlationId = null, array $counselorContext = [], ?string $requestId = null, ?array $emotionResponseContext = null)
    {
        // The authenticated session is the sole source of conversation context.
        // Browser history is deliberately not accepted as an authority.
        $context = ['history' => [], 'session_digest' => null];
        if ($sessionId !== null) {
            $session = AIConversationSession::query()
                ->whereKey($sessionId)
                ->where('user_id', $userId)
                ->firstOrFail();
            $context = app(ConversationContextService::class)
                ->forAuthorizedSession($session, (int) $userId);
        }

        $formattedHistory = [];
        foreach ($context['history'] as $msg) {
            $formattedHistory[] = [
                'user' => $msg['user'] ?? $msg['user_message'] ?? '',
                'ai' => $msg['ai'] ?? $msg['ai_response'] ?? '',
                'emotion' => $msg['emotion'] ?? 'neutral',
                'timestamp' => $msg['timestamp'] ?? $msg['created_at'] ?? '',
            ];
        }
        
        // Get user's AI preference mode
        $privacySetting = PrivacySetting::where('user_id', $userId)->first();
        $aiPreferenceMode = $privacySetting ? $privacySetting->ai_preference_mode : 'companion';
        
        $data = [
            'message' => $message,
            'user_id' => $userId,
            'conversation_history' => $formattedHistory,
            // Bounded, session-only extractive context from prior completed turns.
            // It is never cross-user or cross-session memory.
            'session_context' => $context['session_digest'],
            'ai_preference_mode' => $aiPreferenceMode, // NEW: User's companion vs counselor preference
            // Reuse the trained /analyze result so Flask does not run the classifier twice.
            'laravel_emotion_analysis' => $emotionData,
            // TASK 6: Pass Laravel's crisis assessment to Flask to prevent over-escalation
            'laravel_crisis_assessment' => [
                'crisis_level' => $crisisLevel,
                'emergency_override' => $emergencyOverride,
            ],
            'correlation_id' => $correlationId,
            'request_id' => $requestId ?? $correlationId,
            'counselor_context' => $counselorContext,
        ];
        if (is_array($emotionResponseContext) && $emotionResponseContext !== []) {
            // This is an internal, sanitized cue only. Flask validates it
            // again and allow-lists the synthetic pilot account; it is never
            // returned in the student-facing response.
            $data['emotion_response_context'] = $emotionResponseContext;
        }

        $subject = auth()->id();
        if ($subject === null || (string) $subject !== (string) $userId) {
            throw new \RuntimeException('Authenticated subject mismatch.');
        }
        $authClient = app(InternalMemoryAuthClient::class);
        $response = $authClient->post(
            'memory.chat',
            $subject,
            $data,
            $authClient->memoryAuthorizedFor($subject)
        );
        if (is_array($response)) {
            // Keep this internal metadata out of the public response while allowing
            // the Laravel diagnostic event to reflect the exact Flask input.
            $response['_history_turns_sent'] = count($formattedHistory);
        }
        return $response;
    }

    /**
     * Handle crisis situation
     * Uses only an independently assessed direct crisis level.
     */
    private function handleCrisis($userId, $message, $crisisLevel): bool
    {
        // Log the direct crisis decision without a clinical-style score.
        \Log::warning("Crisis detected for user {$userId}: {$crisisLevel}", [
            'crisis_level' => $crisisLevel,
        ]);


        // Determine if automatic appointment should be created
        $shouldAutoCreateAppointment = false;
        $appointmentReason = '';

        // Critical crisis always triggers appointment
        if ($crisisLevel === 'critical') {
            $shouldAutoCreateAppointment = true;
            $appointmentReason = 'Critical crisis detected - immediate intervention required';
        } elseif ($crisisLevel === 'high') {
            $shouldAutoCreateAppointment = true;
            $appointmentReason = 'High-risk crisis detected - urgent professional support needed';
        }

        // Auto-create appointment if conditions met
        // createAutomaticAppointment() invokes CrisisDetectionService::handleCrisisDetection()
        // which sends alerts, creates notifications, and triggers the full crisis pipeline.
        $appointmentCreated = $shouldAutoCreateAppointment
            ? $this->createAutomaticAppointment($userId, $appointmentReason, $crisisLevel)
            : false;

        $this->chatService->logCrisisEvent($userId, $message, $crisisLevel);

        return $appointmentCreated;
    }

    /**
     * Create an institutionally authorized crisis appointment from direct crisis evidence.
     */
    private function createAutomaticAppointment($userId, $reason, $crisisLevel): bool
    {
        try {
            // Use CrisisDetectionService to create automatic appointment
            $crisisService = app(\App\Services\CrisisDetectionService::class);
            
            // Pass only independently assessed crisis evidence downstream.
            $crisisData = [
                'crisis_level' => $crisisLevel,
                'needs_counselor' => true,
                'crisis_category' => $this->determineCrisisCategory($crisisLevel),
            ];

            
            // Create crisis alert and automatic appointment
            $alert = $crisisService->handleCrisisDetection(
                $userId,
                $crisisData,
                'Automatic crisis escalation requested: ' . $reason,
                "AI detected critical mental health indicators requiring immediate professional support."
            );
            
            $appointmentCreated = false;
            if ($alert) {
                $appointmentCreated = (bool) $alert->getAttribute('appointment_created');
                if (!$appointmentCreated) {
                    return false;
                }
                \Log::info("Automatic appointment created successfully: Alert ID {$alert->id}, Reason: {$reason}");
                
                // Send notification to user about automatic appointment
                $this->notifyUserOfAutoAppointment($userId, $alert, $reason);
            } else {
                \Log::warning("Automatic appointment not created - no available counselor or conditions not met");
            }
            
            return $appointmentCreated;

        } catch (\Exception $e) {
            \Log::error("Failed to create automatic appointment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Determine crisis category based on all factors
     */
    private function determineCrisisCategory($crisisLevel)
    {
        // Primary categorization based on crisis level
        if ($crisisLevel === 'critical') {
            return 'suicidal_ideation';
        } elseif ($crisisLevel === 'high') {
            return 'severe_distress';
        }

        return 'general_crisis';
    }

    /**
     * Notify user about automatic appointment creation
     */
    private function notifyUserOfAutoAppointment($userId, $alert, $reason)
    {
        try {
            // You could implement notification here (email, in-app notification, etc.)
            // For now, just log it
            \Log::info("User {$userId} notified of automatic appointment creation: {$reason}");
            
            // TODO: Implement actual user notification (email, push notification, etc.)
            
        } catch (\Exception $e) {
            \Log::error("Failed to notify user of automatic appointment: " . $e->getMessage());
        }
    }

    /**
     * Get all chat sessions for the current user
     */
    public function getSessions(Request $request)
    {
        $userId = auth()->id();
        
        // A conversation is defined by its durable session identifier, never by
        // the calendar date. This preserves multiple chats on the same day and
        // prevents a long-running session from being split at midnight.
        $preview = \App\Models\EmotionalEntry::query()
            ->select('entry_text')
            ->whereColumn('session_id', 'ai_conversation_sessions.id')
            ->orderBy('created_at')
            ->limit(1);

        $sessions = AIConversationSession::query()
            ->where('user_id', $userId)
            ->where('is_hidden', false)
            ->whereHas('entries', fn ($query) => $query->where('user_id', $userId))
            ->select(['id', 'title', 'last_message_at', 'created_at'])
            ->selectSub($preview, 'preview')
            ->withCount('entries')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (AIConversationSession $session) => [
                'id' => $session->id,
                'session_id' => $session->id,
                'title' => $session->title,
                'session_date' => optional($session->last_message_at ?? $session->created_at)->toDateString(),
                'last_message_at' => optional($session->last_message_at)->toIso8601String(),
                'message_count' => (int) $session->entries_count,
                'preview' => \Illuminate\Support\Str::limit((string) ($session->preview ?? ''), 50),
            ]);
        
        return response()->json([
            'success' => true,
            'sessions' => $sessions,
        ]);
    }
    
    /**
     * Create a new chat session
     */
    public function createSession(Request $request)
    {
        try {
            $user_id = auth()->id();
            
            // Create new session
            $session = AIConversationSession::create([
                'user_id' => $user_id,
                'session_token' => uniqid('ai_session_', true),
                'title' => 'New Chat',
                'last_message_at' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'session_id' => $session->id,
                'message' => 'New session created'
            ]);
        } catch (\Exception $e) {
            \Log::error('Create session error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get messages for a specific session (by date)
     */
    public function getSessionMessages(Request $request, $sessionId)
    {
        $userId = auth()->id();
        
        // Verify the session belongs to the user
        $session = \App\Models\AIConversationSession::where('id', $sessionId)
            ->where('user_id', $userId)
            ->first();
        
        if (!$session) {
            return response()->json([
                'success' => false,
                'error' => 'Session not found'
            ], 404);
        }
        
        $messages = \DB::table('emotional_entries')
            ->where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($entry) {
                return [
                    'id' => $entry->id,
                    'user_message' => $entry->entry_text,
                    'ai_response' => $entry->ai_response,
                    'emotion' => $entry->detected_emotion,
                    // MySQL DECIMAL columns are returned as strings; normalize
                    // the public history contract to a JSON number across DBs.
                    'confidence' => (float) $entry->confidence_score,
                    'created_at' => $entry->created_at
                ];
            });
        
        // BUG 2 FIX: Log what emotions are being loaded from database
        \Log::info('[LOAD_SESSION_MESSAGES] Loading from database', [
            'session_id' => $sessionId,
            'message_count' => count($messages),
            'emotions_found' => $messages->pluck('emotion')->unique()->values()->toArray()
        ]);
        
        return response()->json([
            'success' => true,
            'messages' => $messages
        ]);
    }
    
    /**
     * Delete a chat session
     */
    public function deleteSession(Request $request, $sessionId)
    {
        $userId = auth()->id();

        $session = AIConversationSession::whereKey($sessionId)
            ->where('user_id', $userId)
            ->first();

        if (!$session) {
            return response()->json(['success' => false, 'error' => 'Session not found'], 404);
        }

        \DB::transaction(function () use ($userId, $sessionId, $session) {
            \DB::table('emotional_entries')
                ->where('user_id', $userId)
                ->where('session_id', $sessionId)
                ->delete();
            $session->delete();
        });
        
        return response()->json([
            'success' => true,
            'message' => 'Session deleted successfully'
        ]);
    }

    /**
     * Generate counselor suggestion based on situation level and user preference
     */
    private function generateCounselorSuggestion($level, $userPreference)
    {
        $suggestions = [];

        if ($level === 'critical') {
            // EMERGENCY situation - strong counselor recommendation
            $suggestions = [
                "ðŸš¨ **This sounds like an emergency.** Please reach out to a counselor immediately or contact the crisis hotline:\n- NCMH Crisis Hotline: 1-800-4-CRISIS (1-800-427-4747)\n- Crisis Text Line: Text HOME to 741741",
                "âš ï¸ **I'm very concerned about what you're sharing.** This requires immediate professional support. Please contact our counseling services or a crisis helpline right away.",
                "**Your safety is the priority.** What you're describing needs immediate professional attention. Please reach out to a counselor or emergency services now.",
            ];
        } elseif ($level === 'high') {
            // HIGH severity - recommend counselor
            $suggestions = [
                "I think talking with a counselor could really help with what you're going through. They have specialized training to support you through this. Would you like help scheduling an appointment?",
                "What you're experiencing sounds really serious, and I want to make sure you get the right support. Speaking with a professional counselor could be really valuable for you right now.",
                "I'm here to listen, but I also want to make sure you have access to a counselor who can provide deeper support. Would you be open to connecting with one?",
            ];
        } elseif ($level === 'moderate' && $userPreference === 'counselor') {
            // MODERATE severity + user prefers counselor mode
            $suggestions = [
                "Since you prefer working with a counselor, this might be a good time to connect with one for more personalized guidance.",
                "You mentioned you prefer counselor support - this situation might benefit from that kind of professional perspective. Shall we set up an appointment?",
                "Given what you're sharing, speaking with a counselor could provide helpful professional insight and support.",
            ];
        } else {
            // LOW severity or companion mode - light mention
            $suggestions = [
                "If things feel overwhelming, remember that counselor support is always available. We also have tips and resources in the app.",
                "You're doing well sharing this. If you ever feel like you need more support, our counselors are always available.",
                "Thanks for opening up. If this becomes more difficult to manage on your own, professional support is just a click away.",
            ];
        }

        return $suggestions[array_rand($suggestions)];
    }

    /**
     * Get crisis-safe response for critical/severe crisis messages
     * Reuses existing PHP crisis response infrastructure with configured hotlines
     * 
     * @param string $userMessage The user's message (context only)
     * @param array $enhancedCrisisAnalysis The enhanced crisis analysis from Laravel
     * @return string Crisis-safe response with resources and support pathways
     */
    private function sanitizeResponseText(string $response): string
    {
        $response = preg_replace('/<br\s*\/?\s*>/i', "\n", $response) ?? $response;

        return trim(strip_tags($response));
    }
    private function getCrisisSafeResponse(string $userMessage, array $enhancedCrisisAnalysis): string
    {
        $crisisLevel = $enhancedCrisisAnalysis['crisis_level'] ?? 'severe';
        
        // For CRITICAL crisis: Use urgent crisis response
        if ($crisisLevel === 'critical') {
            $response = "ðŸš¨ <strong>Hey, stop for a second. I need you to hear me.</strong><br><br>";
            $response .= "What you just told me... I'm really, really worried about you right now. Like, seriously worried.<br><br>";
            
            $response .= "I know everything feels overwhelming and maybe even hopeless right now. But here's the truth: <strong>your life matters.</strong> You matter.<br><br>";
            
            $response .= "<strong>I need you to reach out for help RIGHT NOW:</strong><br>";
            $response .= "1. <strong>Talk to a counselor immediately</strong> - Click 'Chat with Counselor' or call <strong>1553</strong> (NCMH, free, confidential, 24/7)<br>";
            $response .= "2. <strong>Call NCMH Crisis Line:</strong> <strong>0917-899-8727</strong> or <strong>989-8727</strong> - Available 24/7<br>";
            $response .= "3. <strong>Text Hopeline</strong> - Text <strong>2919</strong> (DSWD Crisis Line, available on Globe/TM)<br>";
            $response .= "4. <strong>Or go to the nearest hospital emergency room</strong> if you're in immediate danger<br><br>";
            
            $response .= "Is there someone near you right now? A roommate, friend, family member? Please don't be alone with this. ðŸ’™<br><br>";
            
            $response .= "You don't have to go through this by yourself. There are people trained to help with exactly what you're feeling. Please reach out to them right now.<br><br>";
            
            $response .= "<em>Please don't wait for an in-app response - reach out for immediate support first.</em>";
            
            return $this->withVerifiedCrisisContacts($response);
        }
        
        // For SEVERE crisis with intent/plan: Use severe concern response
        if ($crisisLevel === 'severe') {
            $response = "âš ï¸ <strong>Okay, I need to be real with you right now.</strong><br><br>";
            $response .= "What you just shared... that's concerning. Really concerning. I'm worried about you. ðŸ’™<br><br>";
            
            if ($enhancedCrisisAnalysis['intent_detected'] || $enhancedCrisisAnalysis['plan_detected']) {
                $response .= "I hear that you've been thinking about this and may have thought about how. That tells me you need <strong>professional support right now</strong> - not later, now.<br><br>";
            }
            
            $response .= "<strong>Please reach out for help:</strong><br>";
            $response .= "1. <strong>Talk to a counselor</strong> - Click 'Chat with Counselor' or call <strong>1553</strong> (NCMH, free, 24/7)<br>";
            $response .= "2. <strong>Call NCMH Crisis Line:</strong> <strong>0917-899-8727</strong> or <strong>989-8727</strong><br>";
            $response .= "3. <strong>Reach out to someone you trust</strong> - Family, friend, teacher - anyone who cares about you<br>";
            $response .= "4. <strong>If things get worse, go to the hospital emergency room</strong><br><br>";
            
            $response .= "I know you're in a lot of pain right now. That pain is real, and it's valid. But you don't have to carry it alone. ";
            $response .= "Professional counselors are trained to help people work through exactly what you're feeling.<br><br>";
            
            $response .= "ðŸ’œ <em>Please, reach out to one of these resources. You matter, and you deserve support.</em>";
            
            return $this->withVerifiedCrisisContacts($response);
        }
        
        // Fallback for other crisis levels (shouldn't reach here, but safe default)
        $response = "âš ï¸ <strong>I want to make sure you're safe and supported.</strong><br><br>";
        $response .= "What you're sharing sounds difficult, and I want to help connect you with someone who can provide the support you need.<br><br>";
        
        $response .= "<strong>Here are resources available to you:</strong><br>";
        $response .= "â€¢ <strong>Chat with a Counselor</strong> - Click 'Chat with Counselor' for professional support<br>";
        $response .= "â€¢ <strong>NCMH Mental Health Hotline:</strong> <strong>1553</strong> (free, 24/7)<br>";
        $response .= "â€¢ <strong>NCMH Crisis Line:</strong> <strong>0917-899-8727</strong> or <strong>989-8727</strong><br>";
        $response .= "â€¢ <strong>Hopeline (DSWD):</strong> Text <strong>2919</strong><br>";
        $response .= "â€¢ <strong>Emergency:</strong> Go to the nearest hospital if you're in immediate danger<br><br>";
        
        $response .= "You deserve support. Please reach out. ðŸ’™";
        
        return $this->withVerifiedCrisisContacts($response);
    }

    private function withVerifiedCrisisContacts(string $response): string
    {
        $response = preg_replace('/<strong>Text Hopeline<\/strong> - Text <strong>2919<\/strong> \(DSWD Crisis Line, available on Globe\/TM\)<br>/', '<strong>Hopeline Philippines:</strong> Call <strong>2919</strong> (toll-free for Globe/TM subscribers)<br>', $response) ?? $response;
        $response = str_replace(' or <strong>989-8727</strong>', '', $response);
        $response = str_replace('<strong>Hopeline (DSWD):</strong> Text <strong>2919</strong>', '<strong>Hopeline Philippines:</strong> Call <strong>2919</strong> from Globe or TM (toll-free)', $response);

        return $response;
    }

    /**
     * Build session context for Gemma assessment
     * Extracts recent conversation messages to provide context
     */
    private function buildSessionContext(AIConversationSession $session, int $count = 5): string
    {
        $recentEntries = \App\Models\EmotionalEntry::where('session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->take($count * 2) // Get more than needed to filter user/AI
            ->get();
        
        $contextLines = [];
        $processedCount = 0;
        
        foreach ($recentEntries->reverse() as $entry) {
            if ($processedCount >= $count) {
                break;
            }
            
            // Only include user messages (not AI responses) for context
            if (!str_contains($entry->entry_text, 'http') && strlen($entry->entry_text) < 500) {
                $contextLines[] = "User: " . substr($entry->entry_text, 0, 200);
                $processedCount++;
            }
        }
        
        if (empty($contextLines)) {
            return "No previous context available.";
        }
        
        return "Recent conversation:\n" . implode("\n", $contextLines);
    }

    private function requiresContextualCrisisAssessment(string $message, array $riskScreen, array $enhanced): bool
    {
        if (in_array($riskScreen['level'] ?? 'none', ['moderate', 'severe', 'critical'], true)) {
            return true;
        }
        foreach (['intent_detected', 'plan_detected', 'method_detected', 'access_detected', 'recurrence_detected'] as $signal) {
            if (!empty($enhanced[$signal])) {
                return true;
            }
        }
        $message = mb_strtolower($message);
        if (str_contains($message, 'hindi ako') || str_contains($message, 'ayoko') || str_contains($message, 'do not want to die')) {
            return false;
        }
        return str_contains($message, 'thinking about suicide')
            || str_contains($message, 'thoughts of suicide')
            || str_contains($message, 'thinking about dying')
            || str_contains($message, 'thoughts of dying')
            || str_contains($message, 'want to die')
            || str_contains($message, 'hurt myself')
            || str_contains($message, 'self harm')
            || str_contains($message, 'iniisip') && str_contains($message, 'mamatay')
            || str_contains($message, 'magpakamatay');
    }

    private function isCounselorReferralAcceptance(string $message, array $history): bool
    {
        $confirmation = trim(mb_strtolower($message));
        if (!preg_match('/^(?:yes|yeah|yep|sure|ok(?:ay)?|go ahead|do it now|yes[,! ]+do it now|oo|sige)(?:[.! ]*)$/u', $confirmation)) return false;
        foreach (array_reverse($history) as $turn) {
            if (preg_match('/\b(?:counselor|counselling|counseling|appointment|professional support)\b/u', mb_strtolower((string) ($turn['ai'] ?? '')))) return true;
        }
        return false;
    }
    private function isExplicitCounselorRequest(string $message): bool
    {
        return (bool) preg_match('/\b(?:connect(?: me)? with|help me (?:connect|find)|find|talk to|see|need|want)\b.{0,35}\b(?:a )?(?:counselor|therapist|guidance counselor)\b|\b(?:counselor|appointment)\b.{0,25}\b(?:please|ngayon|now|kailangan|gusto)\b/ui', $message);
    }

    private function isMetalinguisticSafetyText(string $message): bool
    {
        $text = trim(mb_strtolower($message));
        if (preg_match('/^(?:test(?:[ -]?case)?|scenario|example|prompt|label|classification|input)\s*[:\-]/u', $text)) {
            return true;
        }

        return (bool) preg_match('/^(?:explicit|credible|current|indirect|hypothetical)\s+(?:current\s+)?(?:self[- ]?harm|suicid(?:e|al)|crisis)\s+(?:intent|risk|statement)$/u', $text);
    }

    private function isExplicitCrisisDenial(string $message, array $analysis): bool
    {
        if (!empty($analysis['intent_detected']) || !empty($analysis['plan_detected']) || !empty($analysis['access_detected'])) {
            return false;
        }

        return (bool) preg_match('/\b(?:i do not want to|i don\'t want to|i am not going to|hindi ko gusto(?:ng)?|ayoko(?:ng)?)\b.{0,35}\b(?:hurt myself|harm myself|self[- ]?harm|die|suicid(?:e|al)|saktan ang sarili|magpakamatay|mamatay)\b/ui', $message);
    }

    private function noCrisisAnalysis(): array
    {
        return ['crisis_level' => 'none', 'crisis_confidence' => 1.0, 'sarcasm_detected' => false,
            'satire_detected' => false, 'hyperbole_detected' => false, 'temporal_urgency' => 0,
            'intent_detected' => false, 'plan_detected' => false, 'method_detected' => false,
            'access_detected' => false, 'recurrence_detected' => false, 'reason' => 'metalinguistic_safety_text'];
    }

    private function emotionResponsePilotEnabled(int $userId): bool
    {
        $configuredAccount = trim((string) config('services.mindcare_ml.detailed_emotion_response_pilot.account_id', ''));

        return app()->environment('local')
            && (bool) config('services.mindcare_ml.detailed_emotion_response_pilot.enabled', false)
            && (bool) config('services.mindcare_ml.detailed_emotion_response_pilot.approved', false)
            && $configuredAccount !== ''
            && hash_equals($configuredAccount, (string) $userId)
            && (bool) config('services.mindcare_ml.detailed_emotion_shadow.enabled', false)
            && (bool) config('services.mindcare_ml.detailed_emotion_shadow.approved', false);
    }

    /**
     * Keep only the already validated shadow fields needed by the pilot. Raw
     * evidence and user text never enter the response prompt or logs.
     */
    private function sanitizeDetailedEmotionShadow(?array $shadow): ?array
    {
        if (!$shadow) {
            return null;
        }

        $result = [
            'status' => $shadow['status'] ?? null,
            'attribution_scope' => $shadow['attribution_scope'] ?? null,
            'primary_emotion' => $shadow['primary_emotion'] ?? null,
            'secondary_emotions' => $shadow['secondary_emotions'] ?? [],
            'other_emotions' => $shadow['other_emotions'] ?? [],
            'uncertainty_reason' => $shadow['uncertainty_reason'] ?? null,
            'classification_source' => $shadow['classification_source'] ?? 'gemma_shadow',
            'taxonomy_version' => $shadow['taxonomy_version'] ?? null,
            'category_key' => $shadow['category_key'] ?? null,
            'category_keys' => $shadow['category_keys'] ?? [],
            'other_category_keys' => $shadow['other_category_keys'] ?? [],
            'felt_by_student' => $shadow['felt_by_student'] ?? null,
            'schema_valid' => (bool) ($shadow['schema_valid'] ?? false),
            'shadow_only' => true,
            'stored' => false,
            'latency_ms' => $shadow['latency_ms'] ?? null,
        ];
        [$derivedCategories, $derivedPrimaryCategory] = $this->deriveDetailedShadowCategories(
            is_string($result['primary_emotion']) ? $result['primary_emotion'] : null,
            is_array($result['secondary_emotions']) ? $result['secondary_emotions'] : []
        );
        $result['category_keys'] = $derivedCategories;
        $result['category_key'] = $derivedPrimaryCategory;

        return $result;
    }

    /**
     * Build a prompt cue only for clear/mixed feelings that the validated
     * result attributes to the student. Other-person, hypothetical, uncertain,
     * no-emotion, and malformed results deliberately return null.
     */
    private function buildEmotionResponseContext(?array $shadow): ?array
    {
        if (!$shadow || ($shadow['schema_valid'] ?? false) !== true) {
            return null;
        }
        if (($shadow['classification_source'] ?? null) !== 'gemma_shadow') {
            return null;
        }

        $status = is_string($shadow['status'] ?? null) ? $shadow['status'] : null;
        $scope = is_string($shadow['attribution_scope'] ?? null) ? $shadow['attribution_scope'] : null;
        if (!in_array($status, ['clear', 'mixed'], true)
            || !in_array($scope, ['student', 'both'], true)
            || ($shadow['felt_by_student'] ?? null) !== true) {
            return null;
        }

        $taxonomy = app(\App\Services\EmotionTaxonomy::class);
        if ((string) ($shadow['taxonomy_version'] ?? '') !== (string) $taxonomy->version()) {
            return null;
        }
        $primaryDefinition = $taxonomy->definitionFor(
            is_string($shadow['primary_emotion'] ?? null) ? $shadow['primary_emotion'] : null
        );
        $primary = $primaryDefinition['key'] ?? null;
        if ($primary === null) {
            return null;
        }

        $secondary = [];
        foreach ((array) ($shadow['secondary_emotions'] ?? []) as $candidate) {
            $definition = $taxonomy->definitionFor(is_string($candidate) ? $candidate : null);
            $key = $definition['key'] ?? null;
            if ($key === null) {
                return null;
            }
            if ($key !== $primary && !in_array($key, $secondary, true)) {
                $secondary[] = $key;
            }
        }
        if ($status === 'mixed' && $secondary === []) {
            return null;
        }
        if ($status === 'clear' && $secondary !== []) {
            $status = 'mixed';
        }

        [$categoryKeys] = $this->deriveDetailedShadowCategories($primary, $secondary);

        // Keep the provenance fields in this internal bridge payload so Flask
        // can independently revalidate the already-sanitized cue. These fields
        // never enter the student-facing response or persistence path.
        return [
            'status' => $status,
            'attribution_scope' => $scope,
            'primary_emotion' => $primary,
            'secondary_emotions' => array_values($secondary),
            'emotions' => array_values(array_slice(array_merge([$primary], $secondary), 0, 5)),
            'category_keys' => $categoryKeys,
            'taxonomy_version' => $shadow['taxonomy_version'] ?? $taxonomy->version(),
            'classification_source' => 'gemma_shadow',
            'schema_valid' => true,
            'felt_by_student' => true,
        ];
    }

    /**
     * Persist only validated Gemma shadow metadata when a separate local pilot
     * gate is enabled. This never changes the authoritative six-class fields or
     * stores raw evidence, confidence, or the student's message twice.
     */
    private function persistDetailedEmotionShadow($entry, ?array $shadow): void
    {
        if (!$shadow || !config('services.mindcare_ml.detailed_emotion_shadow.persist', false)) {
            return;
        }

        $validStatuses = ['clear', 'mixed', 'uncertain', 'no_emotion', 'attributed_other'];
        $status = is_string($shadow['status'] ?? null) ? $shadow['status'] : null;
        $scope = is_string($shadow['attribution_scope'] ?? null) ? $shadow['attribution_scope'] : null;
        if (!in_array($status, $validStatuses, true)
            || !in_array($scope, ['student', 'other', 'both', 'hypothetical', 'none'], true)) {
            return;
        }

        $taxonomy = app(\App\Services\EmotionTaxonomy::class);
        $primaryDefinition = $taxonomy->definitionFor($shadow['primary_emotion'] ?? null);
        $primary = $primaryDefinition['key'] ?? null;
        $secondary = [];
        foreach ((array) ($shadow['secondary_emotions'] ?? []) as $candidate) {
            $definition = $taxonomy->definitionFor(is_string($candidate) ? $candidate : null);
            $key = $definition['key'] ?? null;
            if ($key !== null && $key !== $primary && !in_array($key, $secondary, true)) {
                $secondary[] = $key;
            }
        }
        $other = [];
        foreach ((array) ($shadow['other_emotions'] ?? []) as $candidate) {
            $definition = $taxonomy->definitionFor(is_string($candidate) ? $candidate : null);
            $key = $definition['key'] ?? null;
            if ($key !== null && !in_array($key, $other, true)) {
                $other[] = $key;
            }
        }

        if (in_array($scope, ['other', 'hypothetical', 'none'], true)
            || $status === 'attributed_other'
            || $status === 'no_emotion') {
            $primary = null;
            $secondary = [];
        }
        if ($status === 'mixed' && ($primary === null || $secondary === [])) {
            $status = 'uncertain';
        }

        $categoryKeys = [];
        foreach (array_filter(array_merge([$primary], $secondary)) as $emotionKey) {
            $definition = $taxonomy->definitionFor($emotionKey);
            $categoryKey = $definition['category_key'] ?? null;
            if ($categoryKey !== null && !in_array($categoryKey, $categoryKeys, true)) {
                $categoryKeys[] = $categoryKey;
            }
        }

        try {
            $entry->forceFill([
                'detailed_emotion_status' => $status,
                'detailed_emotion_key' => $primary,
                'emotion_category_key' => $categoryKeys[0] ?? null,
                'emotion_classification_source' => 'gemma_shadow',
                'emotion_taxonomy_version' => $taxonomy->version(),
                'detailed_emotion_secondary_keys' => $secondary,
                'detailed_emotion_other_keys' => $other,
                'detailed_emotion_category_keys' => $categoryKeys,
                'detailed_emotion_attribution_scope' => $scope,
                'detailed_emotion_uncertainty_reason' => is_string($shadow['uncertainty_reason'] ?? null)
                    ? mb_substr($shadow['uncertainty_reason'], 0, 160)
                    : null,
                // Gemma does not provide calibrated numeric confidence.
                'emotion_uncertainty' => null,
            ])->save();
        } catch (\Throwable $error) {
            // Optional shadow persistence must never fail the completed chat.
            \Log::warning('[DETAILED_EMOTION_SHADOW_PERSISTENCE_FAILED]', [
                'entry_id' => $entry->id ?? null,
                'error' => $error->getMessage(),
            ]);
        }
    }

    private function deriveDetailedShadowCategories(?string $primary, array $secondary): array
    {
        $taxonomy = app(\App\Services\EmotionTaxonomy::class);
        $categories = [];
        $primaryCategory = null;
        foreach (array_filter(array_merge([$primary], $secondary)) as $emotionKey) {
            $definition = $taxonomy->definitionFor(is_string($emotionKey) ? $emotionKey : null);
            $categoryKey = $definition['category_key'] ?? null;
            if ($primary !== null && $emotionKey === $primary) {
                $primaryCategory = $categoryKey;
            }
            if ($categoryKey !== null && !in_array($categoryKey, $categories, true)) {
                $categories[] = $categoryKey;
            }
        }

        return [$categories, $primaryCategory];
    }
}
