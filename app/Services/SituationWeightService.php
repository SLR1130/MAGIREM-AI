<?php

namespace App\Services;

class SituationWeightService
{
    /**
     * Critical keywords indicating severe situations
     */
    private const CRITICAL_KEYWORDS = [
        'suicide', 'kill myself', 'want to die', 'end my life', 'take my life',
        'self harm', 'cut myself', 'hurt myself', 'harm myself',
        'overdose', 'poison', 'jump', 'hanging',
        'abuse', 'assault', 'rape', 'molest',
        'severe panic', 'can\'t breathe', 'heart attack',
    ];

    /**
     * High severity keywords
     */
    private const HIGH_KEYWORDS = [
        'hopeless', 'worthless', 'no reason to live', 'burden',
        'severe depression', 'major depressive', 'deep sadness',
        'violent thoughts', 'violent urges', 'hurting others',
        'substance abuse', 'drug', 'alcohol addiction',
        'extreme anxiety', 'panic attack', 'breakdown',
        'trauma', 'ptsd', 'flashback',
    ];

    /**
     * Moderate severity keywords
     */
    private const MODERATE_KEYWORDS = [
        'sad', 'depressed', 'anxious', 'stressed', 'worried',
        'can\'t sleep', 'insomnia', 'can\'t eat', 'lost appetite',
        'overwhelmed', 'tired', 'exhausted', 'burnt out',
        'relationship problems', 'break up', 'lonely', 'isolated',
        'failing', 'struggling', 'confused', 'lost',
    ];

    /**
     * Calculate situation weight/severity (0-100)
     * 
     * @param string $message User message text
     * @param array $analysisData Crisis detection analysis from Python
     * @param array $emotionData Emotion detection data
     * @param array $enhancedCrisisAnalysis Authoritative Laravel enhanced crisis analysis (replaces Python crisis_level)
     * @return array ['weight' => int, 'level' => string, 'shouldSuggestCounselor' => bool]
     */
    public function calculateWeight($message, $analysisData = [], $emotionData = [], $enhancedCrisisAnalysis = []): array
    {
        $weight = 0;
        $level = 'none';
        $shouldSuggestCounselor = false;

        // 1. FIX: Use authoritative Laravel enhanced crisis analysis if provided
        // This ensures consistency with AIChatController's crisis decision
        $crisisLevelToUse = null;
        if (!empty($enhancedCrisisAnalysis['crisis_level'])) {
            $crisisLevelToUse = strtolower($enhancedCrisisAnalysis['crisis_level']);
            \Log::info('[SITUATION_WEIGHT_USING_ENHANCED]', [
                'enhanced_crisis_level' => $crisisLevelToUse,
                'enhanced_confidence' => $enhancedCrisisAnalysis['crisis_confidence'] ?? 0
            ]);
        } elseif (!empty($analysisData['crisis_level'])) {
            // Fallback to Python analysis if enhanced not available
            $crisisLevelToUse = strtolower($analysisData['crisis_level']);
        }
        
        if ($crisisLevelToUse) {
            if ($crisisLevelToUse === 'critical') {
                $weight = 100;
                $level = 'critical';
                $shouldSuggestCounselor = true;
            } elseif ($crisisLevelToUse === 'high') {
                $weight = 80;
                $level = 'high';
                $shouldSuggestCounselor = true;
            } elseif ($crisisLevelToUse === 'severe') {
                $weight = 60;
                $level = 'severe';
                // Only suggest counselor for severe if intent/plan detected (checked by controller)
                $shouldSuggestCounselor = true;
            } elseif ($crisisLevelToUse === 'moderate') {
                $weight = 50;
                $level = 'moderate';
                $shouldSuggestCounselor = false;
            } elseif ($crisisLevelToUse === 'low') {
                $weight = 25;
                $level = 'low';
                $shouldSuggestCounselor = false;
            }
        }

        // 2. Factor in the current interaction emotion intensity
        if (!empty($emotionData['emotion'])) {
            $emotion = strtolower($emotionData['emotion']);
            $emotionIntensity = $emotionData['intensity'] ?? 0.5;
            
            // Negative emotions with high intensity increase weight
            $negativeEmotions = ['sadness', 'anger', 'fear', 'anxiety', 'depression', 'hopelessness'];
            if (in_array($emotion, $negativeEmotions) && $emotionIntensity > 0.7) {
                $weight = max($weight, 70);
                if ($level === 'none') $level = 'high';
                $shouldSuggestCounselor = true;
            } elseif (in_array($emotion, $negativeEmotions) && $emotionIntensity > 0.5) {
                $weight = max($weight, 45);
                if ($level === 'none') $level = 'moderate';
            }
        }

        // 3. Additional keyword-based scoring if weight not determined
        if ($weight === 0) {
            $lowerMessage = strtolower($message);

            // Check for critical keywords
            foreach (self::CRITICAL_KEYWORDS as $keyword) {
                if (stripos($lowerMessage, $keyword) !== false) {
                    $weight = max($weight, 95);
                    $level = 'critical';
                    $shouldSuggestCounselor = true;
                    break;
                }
            }

            // Check for high keywords
            if ($weight < 80) {
                foreach (self::HIGH_KEYWORDS as $keyword) {
                    if (stripos($lowerMessage, $keyword) !== false) {
                        $weight = max($weight, 75);
                        $level = 'high';
                        $shouldSuggestCounselor = true;
                        break;
                    }
                }
            }

            // Check for moderate keywords
            if ($weight < 50) {
                foreach (self::MODERATE_KEYWORDS as $keyword) {
                    if (stripos($lowerMessage, $keyword) !== false) {
                        $weight = max($weight, 40);
                        $level = 'moderate';
                        break;
                    }
                }
            }
        }

        // Ensure consistency: critical and high always suggest counselor
        if (in_array($level, ['critical', 'high'])) {
            $shouldSuggestCounselor = true;
        }

        return [
            'weight' => $weight,
            'level' => $level,
            'shouldSuggestCounselor' => $shouldSuggestCounselor,
            'factors' => [
                'crisis_level' => $analysisData['crisis_level'] ?? 'none',
                'emotion' => $emotionData['emotion'] ?? 'neutral',
                'emotion_intensity' => $emotionData['intensity'] ?? 0.5,
            ]
        ];
    }

    /**
     * Determine if counselor should be suggested based on weight and user preference
     * 
     * @param int $weight Situation weight (0-100)
     * @param string $level Situation level (critical|high|moderate|low|none)
     * @param string $userPreference User AI preference (companion|counselor)
     * @return bool
     */
    public function shouldSuggestCounselor($weight, $level, $userPreference = 'companion'): bool
    {
        // Always suggest for critical situations regardless of preference
        if (in_array($level, ['critical', 'high'])) {
            return true;
        }

        // If user prefers counselor mode, suggest counselor for moderate situations too
        if ($userPreference === 'counselor' && $level === 'moderate') {
            return true;
        }

        // For companion mode, only suggest for high/critical
        return false;
    }

    /**
     * Determine response tone based on weight and preference
     * 
     * @param int $weight Situation weight
     * @param string $level Situation level
     * @param string $userPreference User preference (companion|counselor)
     * @return string Tone: 'supportive', 'gentle-advice', 'crisis', 'emergency'
     */
    public function getResponseTone($weight, $level, $userPreference = 'companion'): string
    {
        if ($level === 'critical') {
            return 'emergency';
        }

        if ($level === 'high') {
            return 'crisis';
        }

        if ($userPreference === 'companion') {
            // For companion mode: gentle advice and tips for comfort
            return 'gentle-advice';
        }

        // For counselor mode or moderate situations
        return 'supportive';
    }

    /**
     * Get guidance for AI response based on situation and preference
     * 
     * @param array $weightData Result from calculateWeight()
     * @param string $userPreference User preference
     * @return array Guidance for response generation
     */
    public function getResponseGuidance($weightData, $userPreference = 'companion'): array
    {
        $shouldSuggestCounselor = $this->shouldSuggestCounselor(
            $weightData['weight'],
            $weightData['level'],
            $userPreference
        );

        $tone = $this->getResponseTone(
            $weightData['weight'],
            $weightData['level'],
            $userPreference
        );

        return [
            'tone' => $tone,
            'shouldSuggestCounselor' => $shouldSuggestCounselor,
            'focusOnComfort' => $userPreference === 'companion' && !in_array($weightData['level'], ['critical', 'high']),
            'focusOnAdvice' => $userPreference === 'companion' && $tone === 'gentle-advice',
            'focusOnCounseling' => $userPreference === 'counselor' || in_array($weightData['level'], ['critical', 'high']),
            'emergencyMode' => $tone === 'emergency',
            'crisisMode' => $tone === 'crisis',
        ];
    }
}
