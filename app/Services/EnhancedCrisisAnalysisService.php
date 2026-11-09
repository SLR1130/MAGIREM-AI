<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Enhanced Crisis Analysis Service
 * 
 * Provides comprehensive crisis detection with support for:
 * - Sarcasm and satire detection
 * - Hyperbole and dark humor recognition
 * - Temporal urgency analysis
 * - Intent/Plan/Method/Access indicators
 * - Confidence scoring
 * - Recurrence detection
 * 
 * This service complements EmotionDetectionService::detectCrisis()
 * by providing more nuanced analysis with confidence scores.
 */
class EnhancedCrisisAnalysisService
{
    private const CONFIDENCE_NONE = 0.25;
    private const CONFIDENCE_DISTRESS = 0.65;
    private const CONFIDENCE_SEVERE = 0.78;
    private const CONFIDENCE_CRITICAL = 0.82;
    private const CONFIDENCE_INTENT = 0.85;
    private const CONFIDENCE_PLAN = 0.92;
    private const CONFIDENCE_IMMEDIATE = 0.97;
    private const CONFIDENCE_COMPOUND = 0.99;

    /**
     * Perform comprehensive crisis analysis on user text
     * Returns structured result with multiple analysis layers
     * PHASE 4 FIX: Sarcasm is a LABEL, not a bypass for crisis analysis
     *
     * @param string $text The user message to analyze
     * @return array Comprehensive crisis analysis
     */
    public function analyzeMessage(string $text): array
    {
        // Run all analysis in sequence, sharing context
        $sarcasmAnalysis = $this->analyzeSarcasmAndSatire($text);
        
        // PHASE 4 FIX: NEVER early-return based on sarcasm alone
        // Sarcasm is contextual - always run full analysis
        // Example: "I'm so tired of everything 🙃 nobody would even notice if I disappeared, right?"
        //          HAS sarcasm emoji BUT expresses real suicidal ideation
        
        $temporalAnalysis = $this->analyzeTemporalContext($text);
        $riskFactors = $this->detectIntentPlanMethodAccess($text);
        $hyperboleAnalysis = $this->detectHyperbole($text);
        
        // Integrate all analyses into final crisis assessment
        // Sarcasm will be considered but NOT override strong crisis signals
        $crisisAssessment = $this->synthesizeCrisisLevel(
            $text,
            $sarcasmAnalysis,
            $temporalAnalysis,
            $riskFactors,
            $hyperboleAnalysis
        );
        
        return [
            'crisis_level' => $crisisAssessment['level'],
            'crisis_confidence' => $crisisAssessment['confidence'],
            'sarcasm_detected' => $sarcasmAnalysis['is_sarcasm'],
            'satire_detected' => $sarcasmAnalysis['is_satire'],
            'hyperbole_detected' => $hyperboleAnalysis['is_hyperbole'],
            'temporal_urgency' => $temporalAnalysis['temporal_urgency'],
            'intent_detected' => $riskFactors['intent_detected'],
            'plan_detected' => $riskFactors['plan_detected'],
            'method_detected' => $riskFactors['method_detected'],
            'access_detected' => $riskFactors['access_detected'],
            'recurrence_detected' => $riskFactors['recurrence_detected'],
            'reason' => $crisisAssessment['reason'],
            'analysis_layers' => [
                'sarcasm' => $sarcasmAnalysis,
                'temporal' => $temporalAnalysis,
                'intent_plan_method' => $riskFactors,
                'hyperbole' => $hyperboleAnalysis,
            ]
        ];
    }
    
    /**
     * Detect strong crisis indicators that override sarcasm/hyperbole
     */
    private function hasStrongCrisisIndicators(string $text): bool
    {
        $text = strtolower($text);
        
        $strongIndicators = [
            'suicide', 'kill myself', 'end my life', 'want to die',
            'self harm', 'cut myself', 'hurt myself',
            'i have a plan', 'figured out how', 'know exactly how',
            'tonight', 'today', 'right now', 'this minute',
        ];
        
        foreach ($strongIndicators as $indicator) {
            if (strpos($text, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Analyze sarcasm and satire
     */
    private function analyzeSarcasmAndSatire(string $text): array
    {
        $sarcasmScore = 0;
        $satireScore = 0;
        $markers = [];
        
        $lowerText = strtolower($text);
        
        // Sarcasm emojis
        $sarcasmEmojis = ['🙄', '😒', '😏', '😑', '🤨', '🙃'];
        foreach ($sarcasmEmojis as $emoji) {
            if (strpos($text, $emoji) !== false) {
                $sarcasmScore += 2;
                $markers[] = 'sarcasm_emoji';
                break;
            }
        }
        
        // Joking emojis
        $jokingEmojis = ['😂', '😆', '😅', '🤣', '😹', 'lol', 'lmao', 'haha', 'rofl'];
        foreach ($jokingEmojis as $emoji) {
            if (strpos($lowerText, $emoji) !== false) {
                $satireScore += 1.5;
                $markers[] = 'joking_indicator';
                break;
            }
        }
        
        // Sarcasm patterns
        $sarcasmPatterns = [
            '/\b(yeah sure|oh sure|right|yep|sure sure|oh yeah)\b/i',
            '/\b(like i care|who cares|yeah right|oh please)\b/i',
            '/(lol|lmao|haha).*?(kill|hurt|die|suicide|self.*harm)/i',
            '/\b(kill|hurt|die|suicide|self.*harm).*?(lol|lmao|haha)\b/i',
            '/\b(not|no way|never)\s+(happening|going to|will|kill myself|hurt myself)\b/i',
        ];
        
        foreach ($sarcasmPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $sarcasmScore += 1.5;
                $markers[] = 'sarcasm_phrase';
                break;
            }
        }
        
        // Positive + negative contradiction
        $positiveWords = ['love', 'best', 'amazing', 'great', 'awesome', 'perfect', 'good'];
        $negativeWords = [
            'kill myself', 'hurt myself', 'want to die', 'suicidal',
            'destroying my soul', 'destroy my soul', 'slowly destroy',
            'dying inside', 'falling apart', 'can\'t take it',
            'everything is falling apart', 'stressed about',
            'deadlines are killing me', 'deadlines.*destroy'
        ];
        
        foreach ($positiveWords as $positive) {
            if (strpos($lowerText, $positive) !== false) {
                foreach ($negativeWords as $negative) {
                    if (preg_match('/' . preg_quote($negative, '/') . '/i', $text)) {
                        $sarcasmScore += 1;
                        $markers[] = 'contradiction';
                        break 2;
                    }
                }
            }
        }
        
        $isSarcasm = $sarcasmScore >= 1.0;  // Lowered from 1.5 to catch more sarcasm
        $isSatire = $satireScore >= 1.0;
        $confidence = min(1.0, ($sarcasmScore + $satireScore) / 3.0);
        
        return [
            'is_sarcasm' => $isSarcasm,
            'is_satire' => $isSatire,
            'sarcasm_score' => $sarcasmScore,
            'satire_score' => $satireScore,
            'confidence' => $confidence,
            'markers' => $markers
        ];
    }
    
    /**
     * Analyze temporal context and urgency
     */
    private function analyzeTemporalContext(string $text): array
    {
        $text = strtolower($text);
        
        $pastMarkers = ['used to', 'before', 'years ago', 'last week', 'yesterday', 'was thinking', 'had', 'dati', 'noon'];
        // PHASE 13C FIX: Distinguish crisis-indicating "now" (immediate threat) from recovery "now" (better now)
        // Include Tagalog temporal markers
        $crisisNowIndicators = ['right now', 'die now', 'kill now', 'kill myself now', 'end it now', 'tonight', 'tonight\'s', "i'm going to", 'ngayon', 'ngayon na', 'mamayang gabi'];
        $otherPresentMarkers = ['today', 'currently', 'i am', 'i want to', 'feeling'];
        $futureMarkers = ['tomorrow', 'tonight', 'soon', 'will', 'planning to', 'mamayang gabi'];
        $immediateMarkers = ['tonight', 'today', 'right now', 'die now', 'kill now', 'ngayon', 'mamayang gabi'];
        
        $hasPast = $this->hasAnyKeyword($text, $pastMarkers);
        $hasRecoveryNow = (bool) preg_match('/\b(?:okay|maayos|mabuti|ligtas)\s+(?:na\s+)?ako\s+ngayon\b/iu', $text);
        $hasCrisisNow = $this->hasAnyKeywordPhrase($text, $crisisNowIndicators) && !$hasRecoveryNow;
        $hasOtherPresent = $this->hasAnyKeyword($text, $otherPresentMarkers);
        $hasPresent = $hasCrisisNow || $hasOtherPresent;
        $hasFuture = $this->hasAnyKeyword($text, $futureMarkers);
        $hasImmediate = $this->hasAnyKeywordPhrase($text, $immediateMarkers) && $hasPresent;
        
        // Calculate temporal urgency (0-1)
        $urgency = 0;
        if ($hasImmediate && $hasCrisisNow) {
            $urgency = 1.0; // Immediate crisis
        } elseif ($hasCrisisNow && $hasFuture) {
            $urgency = 0.9; // Near-term crisis plan
        } elseif ($hasFuture) {
            $urgency = 0.7; // Future planning
        } elseif ($hasCrisisNow) {
            $urgency = 0.95; // Right now
        } elseif ($hasOtherPresent && !$hasPast) {
            $urgency = 0.5; // Present but non-immediate
        } elseif ($hasPast && !$hasCrisisNow && !$hasFuture) {
            $urgency = 0.2; // Historical - recovery or past event
        }
        
        return [
            'temporal_urgency' => $urgency,
            'is_immediate' => $hasImmediate && $hasCrisisNow,
            'is_present' => $hasPresent,
            'is_future' => $hasFuture,
            'is_historical' => $hasPast && !$hasCrisisNow && !$hasFuture,
            'is_remote_past' => $this->hasAnyKeyword($text, ['used to', 'years ago', 'months ago', 'when i was', 'dati', 'noon']),
            'markers' => ['past' => $hasPast, 'present' => $hasPresent, 'future' => $hasFuture]
        ];
    }
    
    /**
     * Detect intent, plan, method, access
     */
    private function detectIntentPlanMethodAccess(string $text): array
    {
        $textLower = mb_strtolower($text, 'UTF-8');

        $methodKeywords = ['gun', 'knife', 'pills', 'rope', 'poison', 'overdose', 'cut myself', 'cutting myself', 'cut my wrist', 'cut my wrists', 'cut my arm', 'cut my arms', 'blade', 'razor'];
        $accessKeywords = ['i have', 'have access', 'can get', 'available', 'in my room', 'my dad\'s', 'found'];
        $urgencyKeywords = ['tonight', 'today', 'right now', 'this minute', 'tonight\'s the night', 'the night', 'now', 'ngayon', 'tonight at'];
        $recurrenceKeywords = ['again', 'coming back', 'thought i was past', 'back to', 'coming back now'];

        $intentDetected = false;
        $planDetected = false;
        $targetedPlanDetected = false;
        $hasNegation = false;
        $hasFigurativeClause = false;
        $unnegatedRiskEvidence = false;
        $matchedEvidence = [];
        $hasCrisisTargetInText = $this->hasCrisisTarget($textLower);

        foreach ($this->splitRiskClauses($text) as $clause) {
            $clauseLower = mb_strtolower($clause, 'UTF-8');
            $isDenied = $this->hasExplicitCrisisDenial($clauseLower);
            $isFigurative = $this->isBoundedFigurativeClause($clauseLower);
            $hasNegation = $hasNegation || $isDenied;
            $hasFigurativeClause = $hasFigurativeClause || $isFigurative;

            if ($isDenied || $isFigurative || $this->isHistoricalRiskClause($clauseLower)) {
                continue;
            }

            $hasTarget = $this->hasCrisisTarget($clauseLower);
            $hasIntentCue = $this->hasIntentCueForTarget($clauseLower);
            $hasAmbiguousEndIntent = preg_match('/\b(?:i\s+)?(?:want|need|intend|plan|decid(?:e|ed))\s+to\s+end\s+this\b/iu', $clauseLower)
                && $this->containsElevatedDistress($textLower);

            if (($hasTarget && $hasIntentCue) || $hasAmbiguousEndIntent) {
                $intentDetected = true;
                $unnegatedRiskEvidence = true;
                $matchedEvidence[] = $hasAmbiguousEndIntent ? 'ambiguous_end_with_distress' : 'explicit_intent';
            }

            if ($this->hasExplicitPlan($clauseLower)) {
                $planDetected = true;
                $unnegatedRiskEvidence = true;
                $matchedEvidence[] = 'explicit_plan';

                if ($hasTarget) {
                    $intentDetected = true;
                    $targetedPlanDetected = true;
                    $matchedEvidence[] = 'plan_with_target';
                } elseif ($hasCrisisTargetInText) {
                    // A plan disclosed after a separate self-harm clause is
                    // still a targeted plan; do not let sentence splitting
                    // hide the connection.
                    $targetedPlanDetected = true;
                    $matchedEvidence[] = 'plan_after_crisis_target';
                }
            }
        }

        $methodDetected = $this->hasAnyKeyword($textLower, $methodKeywords);
        $accessDetected = $methodDetected && $this->hasAnyKeyword($textLower, $accessKeywords);
        $recurrenceDetected = $this->hasAnyKeyword($textLower, $recurrenceKeywords);

        $methodActionIntent = $methodDetected && $accessDetected && (bool) preg_match(
            '/\b(?:i[\'’]?m\s+going\s+to|i\s+am\s+going\s+to|i\s+will|i\s+plan\s+to)\s+(?:take|use|swallow|drink|inject|cut|shoot|hang)\b/iu',
            $textLower
        );
        if ($methodActionIntent) {
            $intentDetected = true;
            $unnegatedRiskEvidence = true;
            $matchedEvidence[] = 'method_action_intent';
        }
        
        // IMPROVED: Detect plan through context-aware combinations
        // If method + access + urgency are all present, it's a strong plan indicator
        // Example: "I have all the pills I need. Tonight's the night."
        //   - method: pills
        //   - access: "I have"
        //   - urgency: "tonight's the night"
        $contextAwarePlanDetected = $methodDetected && $accessDetected && 
                                    $this->hasAnyKeyword($textLower, $urgencyKeywords);
        
        // Plan is detected if explicit plan keywords OR context-aware combination
        $planDetected = $planDetected || $contextAwarePlanDetected;

        if ($contextAwarePlanDetected) {
            $unnegatedRiskEvidence = true;
            $targetedPlanDetected = true;
            $matchedEvidence[] = 'method_access_urgency_plan';
        }

        if ($methodDetected && $accessDetected) {
            $unnegatedRiskEvidence = true;
        }
        
        $escalationScore = 0;
        if ($intentDetected) $escalationScore += 1;
        if ($planDetected) $escalationScore += 2;
        if ($methodDetected) $escalationScore += 2;
        if ($accessDetected) $escalationScore += 2;
        
        return [
            'intent_detected' => $intentDetected,
            'plan_detected' => $planDetected,
            'targeted_plan_detected' => $targetedPlanDetected,
            'method_detected' => $methodDetected,
            'access_detected' => $accessDetected,
            'recurrence_detected' => $recurrenceDetected,
            'escalation_score' => $escalationScore,
            'has_negation' => $hasNegation,
            'denial_only' => $hasNegation && !$unnegatedRiskEvidence,
            'figurative_only' => $hasFigurativeClause && !$unnegatedRiskEvidence,
            'matched_evidence' => array_values(array_unique($matchedEvidence)),
        ];
    }

    /** Split punctuation and contrast boundaries so one denial cannot suppress another clause. */
    private function splitRiskClauses(string $text): array
    {
        $text = preg_replace('/\R/u', '. ', $text) ?? $text;
        $clauses = preg_split(
            '/(?<=[.!?;])\s+|\s+\b(?:but|however|though|pero|ngunit|subalit)\b\s+/iu',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return array_values(array_filter(array_map('trim', $clauses ?: [$text])));
    }

    /** Match explicit denial of a crisis target, not inability/distress such as "can't take it". */
    private function hasExplicitCrisisDenial(string $clause): bool
    {
        $patterns = [
            '/\b(?:i\s+)?(?:do\s+not|don[\'’]?t|never)\s+(?:really\s+)?(?:want|intend|plan|mean|need)\s+to\s+(?:die|kill\s+myself|hurt\s+myself|harm\s+myself|end\s+(?:my\s+life|it\s+all|everything))\b/iu',
            '/\b(?:i[\'’]?m|i\s+am)\s+(?:not|never)\s+(?:going|planning|trying|intending)\s+to\s+(?:die|kill\s+myself|hurt\s+myself|harm\s+myself|end\s+(?:my\s+life|it\s+all|everything))\b/iu',
            '/\b(?:i\s+)?(?:have|made)\s+no\s+plan\s+to\s+(?:die|kill|hurt|harm|end)\b/iu',
            '/\bhindi\s+ko\s+(?:gustong|gusto(?:ng)?|balak|nais)\s+(?:na\s+|nang\s+)?(?:mamatay|magpakamatay|saktan\s+ang\s+sarili)\b/iu',
            '/\b(?:ayoko|ayaw\s+ko)\s+(?:na\s+|nang\s+)?(?:mamatay|magpakamatay|saktan\s+ang\s+sarili)\b/iu',
            '/\bwala\s+akong\s+balak\s+(?:na\s+|nang\s+)?(?:mamatay|magpakamatay|saktan\s+ang\s+sarili)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $clause)) {
                return true;
            }
        }

        return false;
    }

    private function isHistoricalRiskClause(string $clause): bool
    {
        return (bool) preg_match('/\b(?:used\s+to|once|previously|dati|noon)\b.{0,35}\b(?:want|think|plan|die|kill|hurt|mamatay|magpakamatay)\b/iu', $clause);
    }

    private function hasCrisisTarget(string $clause): bool
    {
        return (bool) preg_match(
            '/\b(?:kill(?:ing)?\s+myself|end(?:ing)?\s+(?:my\s+life|it\s+all|everything)|take\s+my\s+own\s+life|die|hurt(?:ing)?\s+myself|harm(?:ing)?\s+myself|cut(?:ting)?\s+myself|mamatay|mag(?:pa)?pakamatay|magpapakamatay|patayin\s+(?:ang\s+)?sarili\s+ko|wakasan\s+(?:ang\s+)?buhay\s+ko|tapusin\s+(?:ang\s+)?buhay\s+ko|mawala\s+na\s+lang)\b/iu',
            $clause
        );
    }

    private function hasIntentCueForTarget(string $clause): bool
    {
        // An explicit first-person "may hurt/harm myself" statement becomes
        // critical when paired with immediate danger or an inability to stay
        // safe. Keep this as a cue, not a standalone crisis decision.
        if (preg_match('/\bi\s+may\s+(?:hurt|harm|cut)\s+myself\b/iu', $clause)) {
            return true;
        }

        $english = '/\b(?:i\s+(?:want|wanna|need|intend|plan)\s+to|i[\'’]?m\s+going\s+to|i\s+am\s+going\s+to|i\s+(?:will|might)|i\s+think\s+i[\'’]?m\s+going\s+to|thinking\s+about|considering|decid(?:e|ed)\s+to|resolved\s+to|chosen\s+to)\b/iu';
        $tagalog = '/\b(?:gusto\s+ko(?:ng|\s+na|\s+nang)?|nais\s+ko(?:ng)?|balak\s+ko(?:ng)?|iniisip\s+ko(?:ng)?|napagpasyahan\s+ko(?:ng)?|desidido\s+ako(?:ng)?)\b/iu';

        return (bool) (preg_match($english, $clause) || preg_match($tagalog, $clause));
    }

    private function hasExplicitPlan(string $clause): bool
    {
        if (preg_match('/\b(?:i\s+)?plan\s+to\s+(?:kill(?:ing)?\s+myself|die|end\s+(?:my\s+life|it\s+all|everything)|take\s+my\s+own\s+life|hurt(?:ing)?\s+myself|harm(?:ing)?\s+myself|cut(?:ting)?\s+myself)\b/iu', $clause)) {
            return true;
        }

        $patterns = [
            '/\b(?:i(?:[\'’]?ve|\s+have)?\s+)?(?:already\s+)?(?:made|formed|prepared|have)\s+(?:a\s+)?plan\b/iu',
            '/\b(?:i\s+)?(?:figured\s+out|know)\s+(?:exactly\s+)?how\b/iu',
            '/\b(?:i(?:[\'’]?ve|\s+have)?\s+)?(?:finally\s+)?(?:decided|resolved|chosen)\s+to\b.{0,50}\b(?:kill(?:ing)?\s+myself|die|end\s+(?:my\s+life|it\s+all|everything)|take\s+my\s+own\s+life|hurt\s+myself|harm\s+myself)\b/iu',
            '/\b(?:may\s+plano\s+(?:na\s+)?ako(?:ng)?|plano\s+ko(?:ng)?|nakaplano\s+na|napagpasyahan\s+ko(?:ng)?|desidido\s+ako(?:ng)?)\b.{0,50}\b(?:mamatay|mag(?:pa)?pakamatay|magpapakamatay|patayin\s+(?:ang\s+)?sarili|wakasan\s+(?:ang\s+)?buhay|tapusin\s+(?:ang\s+)?buhay)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $clause)) {
                return true;
            }
        }

        return false;
    }

    private function containsElevatedDistress(string $text): bool
    {
        return (bool) preg_match('/\b(?:can[\'’]?t\s+take|cannot\s+take|hopeless|worthless|falling\s+apart|ayoko\s+na|hindi\s+ko\s+na\s+kaya|suko\s+na|wala\s+na\s+akong\s+lakas)\b/iu', $text);
    }

    /** A joke label is usable only for a bounded harmless subject, never as a global safety bypass. */
    private function isBoundedFigurativeClause(string $clause): bool
    {
        $hasJokeMarker = (bool) preg_match('/\b(?:lol|lmao|haha|rofl|joking|just\s+kidding)\b|[😅😂🤣🙃]/u', $clause);
        $hasBoundedSubject = (bool) preg_match('/\b(?:assignment|homework|exam|deadline|project|workload|class|movie|film|show|book|game|romantic\s+comedy|kilig|hiya|tuwa|saya)\b/iu', $clause);
        $hasDeathIdiom = (bool) preg_match('/\b(?:kill(?:ing)?\s+myself|killing\s+me|die|dying|dead|patay|mamatay)\b/iu', $clause);
        $explicitJokeAboutRisk = (bool) preg_match('/\b(?:joking|kidding)\s+about\b.{0,30}\b(?:kill(?:ing)?\s+myself|die|suicide|self[ -]?harm)\b/iu', $clause);

        return ($hasJokeMarker && $hasBoundedSubject && $hasDeathIdiom)
            || ($hasJokeMarker && $explicitJokeAboutRisk)
            || (bool) preg_match('/\b(?:patay\s+ako\s+sa|mamatay\s+(?:ako\s+)?sa)\s+(?:kilig|hiya|tuwa|saya|init|lamig|antok|gutom|puyat|kaba|takot)\b/iu', $clause);
    }
    
    /**
     * Detect hyperbole and exaggeration
     */
    private function detectHyperbole(string $text): array
    {
        $text = strtolower($text);
        
        $hyperbolePatterns = [
            '/\b(dying|gonna die|killing me|kill me|dead)\s+(of|from|laugh|bore|hunger)/i',
            '/\b(dying|killed) to /i',
            '/\b(to death|to pieces|to hell)\b/i',
            '/absolute(ly)? (die|kill|suicide)/i',
            '/this.*?(movie|game|show|book).*?(kill|die|suicide|murder)/i',
            '/\b(so|such|really|extremely)\s+(dying|killing me|dead)\b/i',
            '/\b(?:this|my|the)\s+(?:assignment|homework|exam|deadline|project|workload|class)\b.{0,45}\b(?:literally\s+)?(?:is\s+|are\s+)?killing\s+me\b/iu',
            '/\bif\s+i\s+(?:see|watch|read|play)\s+one\s+more\b.{0,50}\b(?:kill\s+myself|die)\b.{0,15}\b(?:lol|lmao|haha|rofl)\b/iu',
            '/\b(?:patay\s+ako\s+sa|mamatay\s+(?:ako\s+)?sa)\s+(?:kilig|hiya|tuwa|saya|init|lamig|antok|gutom|puyat|kaba|takot|dami\s+ng\s+homework)\b/iu',
        ];
        
        $isHyperbole = false;
        foreach ($hyperbolePatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $isHyperbole = true;
                break;
            }
        }
        
        return [
            'is_hyperbole' => $isHyperbole,
            'confidence' => $isHyperbole ? 0.8 : 0,
            'is_bounded_context' => $isHyperbole && $this->isBoundedFigurativeClause($text),
        ];
    }
    
    /**
     * Helper: check if text contains any keyword from array
     */
    private function hasAnyKeyword(string $text, array $keywords): bool
    {
        $text = strtolower($text);
        foreach ($keywords as $keyword) {
            if (preg_match('/(?<![a-z])' . preg_quote($keyword, '/') . '(?![a-z])/', $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check for multi-word phrase matches with boundary awareness
     * More precise than hasAnyKeyword for phrases
     */
    private function hasAnyKeywordPhrase(string $text, array $phrases): bool
    {
        return $this->hasAnyKeyword($text, $phrases);
    }
    
    /**
     * Synthesize all analyses into final crisis level and confidence
     */
    private function synthesizeCrisisLevel(
        string $text,
        array $sarcasm,
        array $temporal,
        array $riskFactors,
        array $hyperbole
    ): array {
        $text = strtolower($text);
        
        // Start with keyword-based crisis level
        $baseLevel = $this->getBaselineCrisisLevel($text);
        $confidence = match ($baseLevel) {
            'critical' => self::CONFIDENCE_CRITICAL,
            'severe' => self::CONFIDENCE_SEVERE,
            'moderate' => self::CONFIDENCE_DISTRESS,
            default => self::CONFIDENCE_NONE,
        };

        
        // IMPROVED: Detect harmless context that negates crisis escalation
        // These phrases indicate the method/access keywords are NOT for self-harm
        $harmlessContextKeywords = [
            'for my headache',
            'for headache',
            'for my pain',
            'for pain',
            'for medication',
            'for my mom',
            'for my dad',
            'for my family',
            'for my friend',
            'for the doctor',
            'for my doctor',
            'as prescribed',
            'prescription',
        ];
        $hasHarmlessContext = $this->hasAnyKeywordPhrase($text, $harmlessContextKeywords);
        
        // Only an explicit denial with no independent evidence can lower the result.
        if (($riskFactors['denial_only'] ?? false)) {
            $baseLevel = 'none';
            $confidence = 0.2;
        }

        // Figurative context is local and cannot override separately established evidence.
        if (($hyperbole['is_bounded_context'] ?? false)
            && ($riskFactors['figurative_only'] ?? false)
            && !$riskFactors['intent_detected']
            && !$riskFactors['plan_detected']
            && !$riskFactors['access_detected']) {
            $baseLevel = 'none';
            $confidence = self::CONFIDENCE_NONE;
        } elseif (($sarcasm['is_sarcasm'] || $sarcasm['is_satire'])
            && !$riskFactors['intent_detected']
            && !$riskFactors['plan_detected']
            && !$riskFactors['method_detected']
            && !$riskFactors['access_detected']
            && !$temporal['is_immediate']) {
            $baseLevel = 'none';
            $confidence = 0.3;
        }
        
        // Adjust based on temporal: only DISTANT past ("used to", "years ago") lowers the level.
        // Recent past ("last week", "yesterday") keeps the baseline, and a message with no risk keywords stays "none".
        if ($temporal['is_historical'] && ($temporal['is_remote_past'] ?? false)
            && $baseLevel !== 'none'
            && !$riskFactors['intent_detected'] && !$riskFactors['plan_detected']) {
            $baseLevel = 'moderate';
            $confidence *= 0.3;
        }
        
        // Escalate based on temporal urgency
        if ($temporal['is_immediate']
            && ($riskFactors['intent_detected'] || $riskFactors['plan_detected'] || $riskFactors['access_detected'])
            && in_array($baseLevel, ['none', 'severe', 'moderate', 'critical'], true)) {
            $baseLevel = 'critical';
            $confidence = max(self::CONFIDENCE_IMMEDIATE, $confidence);
        }
        
        // Escalate based on intent/plan/method/access
        // BUT: Only escalate if there's NO harmless context and there's explicit crisis intent
        // AND no negation
        if (!$hasHarmlessContext && !($riskFactors['denial_only'] ?? false) && $riskFactors['escalation_score'] >= 4) {
            $baseLevel = 'critical';
            $confidence = self::CONFIDENCE_COMPOUND;
        } elseif (!$hasHarmlessContext && !($riskFactors['denial_only'] ?? false) && ($riskFactors['targeted_plan_detected'] ?? false)) {
            // A confirmed first-person plan to carry out a self-harm target is
            // critical even when no clock word ("tonight", "now") is present.
            // Immediacy changes response urgency, not whether the disclosed
            // plan is a critical safety signal.
            $baseLevel = 'critical';
            $confidence = max($temporal['is_immediate'] ? self::CONFIDENCE_IMMEDIATE : self::CONFIDENCE_PLAN, $confidence);
        } elseif (!$hasHarmlessContext && !($riskFactors['denial_only'] ?? false) && $riskFactors['intent_detected']) {
            $baseLevel = $temporal['is_immediate'] || $baseLevel === 'critical' ? 'critical' : 'severe';
            $confidence = max($temporal['is_immediate'] ? self::CONFIDENCE_IMMEDIATE : self::CONFIDENCE_INTENT, $confidence);
        } elseif ($hasHarmlessContext && $baseLevel === 'none') {
            // If harmless context found and no crisis baseline, stay at none
            $baseLevel = 'none';
            $confidence *= 0.2;
        }
        
        // Recurrence increases concern (but not if harmless context present or negation)
        if ($riskFactors['recurrence_detected'] && in_array($baseLevel, ['severe', 'moderate']) && !$hasHarmlessContext && !($riskFactors['denial_only'] ?? false)) {
            $baseLevel = $baseLevel === 'severe' ? 'critical' : 'moderate';
            $confidence = max(0.85, $confidence);
        }
        
        $reason = $this->generateReason($baseLevel, $riskFactors, $temporal, $hyperbole);
        
        return [
            'level' => $baseLevel,
            'confidence' => min(1.0, max(0, $confidence)),
            'reason' => $reason
        ];
    }
    
    /**
     * Get baseline crisis level from keywords
     */
    private function getBaselineCrisisLevel(string $text): string
    {
        if (preg_match('/\b(?:suicide|kill(?:ing)?\s+myself|end(?:ing)?\s+(?:my\s+life|it\s+all|everything)|take\s+my\s+own\s+life|want(?:ing)?\s+to\s+die|better\s+off\s+dead)\b/iu', $text)) {
            return 'critical';
        }

        if (preg_match('/\b(?:gusto\s+ko(?:ng|\s+na|\s+nang)?|nais\s+ko(?:ng)?|balak\s+ko(?:ng)?|plano\s+ko(?:ng)?|napagpasyahan\s+ko(?:ng)?)\b.{0,35}\b(?:mamatay|mag(?:pa)?pakamatay|magpapakamatay|mawala\s+na\s+lang|wakasan\s+(?:ang\s+)?buhay|tapusin\s+(?:ang\s+)?buhay)\b/iu', $text)
            || preg_match('/\b(?:ayoko|ayaw\s+ko)\s+nang\s+mabuhay\b/iu', $text)) {
            return 'critical';
        }

        // English crisis keywords
        $critical = ['suicide', 'kill myself', 'end my life', 'want to die', 'better off dead'];
        $severe = ['self harm', 'hurt myself', 'hopeless', 'worthless'];
        $moderate = ['depressed', 'very sad', 'cant take it', 'too much', 'parent died', 'friend died', 'suicidal thoughts'];
        
        // PHASE 5: TAGALOG/TAGLISH Crisis Keywords
        // Critical: Direct expression of wanting to die or end life
        $critical_tagalog = [
            'gusto ko nang mawala',      // I want to disappear
            'ayoko nang mabuhay',        // I don't want to live
            'gusto ko ng suicide',       // I want suicide
            'gusto ko nang matay',       // I want to die
            'gusto ko na mamatay',       // I want to die (variant with na)
            'gusto ko nang mamatay',      // I want to die
            'nais ko nang mawala',       // I wish I would disappear
            'tuluyin na lang',           // just end it
            'mabuti na lang kung mamatay', // it would be better if I died
        ];
        
        // Severe: Serious distress with self-harm or despair
        $severe_tagalog = [
            'gusto ko mag-gasgas',       // I want to cut myself
            'gusto kong magsama',        // I want to hurt myself
            'walang pag-asa',            // hopeless
            'walang silbi',              // worthless
            'walang halaga',             // worthless/no value
            'walang saysay',             // meaningless/pointless
            'sarili ko lang masakit',    // I want to hurt myself
        ];
        
        // Moderate: Significant distress, not immediate crisis but elevated concern
        $moderate_tagalog = [
            'pagod na pagod ako',        // very exhausted/worn down
            'ayoko na',                  // I don't want to (anymore)
            'ayoko na ayoko na',         // repeated "I don't want to"
            'sobrang drained na ako',    // very drained
            'tapos na ako',              // I'm done/finished
            'wala na akong lakas',       // I have no strength left
            'parang walang kwenta',      // like I don't matter
            'napakasama ng pakiramdam',  // I feel very bad
            'nakakaapekto talaga',       // it really affects me
        ];
        
        // Elevated distress - natural variants (English + Filipino)
        // These expressions indicate elevated emotional distress even without explicit crisis words
        $elevatedDistress = [
            "can't take this anymore",
            "cannot take this anymore",
            "can't take it anymore",
            "cannot take it anymore",
            "i can't take",
            "i cannot take",
            "everything is falling apart",
            "everything's falling apart",
            "falling apart",
            // Filipino
            "kaya ko pa ba",             // can I still handle this
            "hindi ko na kayang",        // I can no longer (handle)
            "ang hirap ng",              // it's so hard
            "sobrang hirap",             // very hard
            "stressed na stressed",      // very stressed
        ];
        
        $text_lower = strtolower($text);
        
        // Check critical keywords (both English and Tagalog)
        if ($this->hasAnyKeyword($text_lower, array_merge($critical, $critical_tagalog))) {
            return 'critical';
        } 
        // Check severe keywords (both English and Tagalog)
        elseif ($this->hasAnyKeyword($text_lower, array_merge($severe, $severe_tagalog))) {
            return 'severe';
        } 
        // Check elevated distress (phrase-based)
        elseif ($this->hasAnyKeywordPhrase($text_lower, $elevatedDistress)) {
            return 'moderate';
        } 
        // Check moderate keywords (both English and Tagalog)
        elseif ($this->hasAnyKeyword($text_lower, array_merge($moderate, $moderate_tagalog))) {
            return 'moderate';
        }
        
        return 'none';
    }
    
    /**
     * Generate human-readable reason for crisis assessment
     */
    private function generateReason(string $level, array $riskFactors, array $temporal, array $hyperbole): string
    {
        if ($level === 'none') {
            if ($hyperbole['is_hyperbole']) {
                return 'Hyperbole detected - not actual crisis';
            }
            return 'No crisis indicators detected';
        }
        
        $reason = "Crisis detected ";
        
        if ($riskFactors['plan_detected']) {
            $reason .= "with planning";
        } elseif ($riskFactors['intent_detected']) {
            $reason .= "with intent";
        } elseif ($temporal['is_immediate']) {
            $reason .= "with immediate urgency";
        } elseif ($riskFactors['recurrence_detected']) {
            $reason .= "with recurrence";
        }
        
        return $reason;
    }
}
