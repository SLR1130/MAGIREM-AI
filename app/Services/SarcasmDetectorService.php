<?php

namespace App\Services;

/**
 * Sarcasm Detection Service
 * 
 * Detects sarcasm, irony, exaggeration, and hyperbole in user text
 * before emotion classification to prevent false classification
 * 
 * Examples:
 * - "Oh great, just what I needed" (negative sarcasm)
 * - "I'm so happy I could die" (exaggerated hyperbole)
 * - "Yeah because that always works" (ironic agreement)
 * - "Just wonderful, absolutely perfect" (heavy sarcasm)
 */
class SarcasmDetectorService
{
    /**
     * Detect sarcasm/irony in text
     * 
     * @param string $text User input text
     * @return array ['is_sarcasm' => bool, 'confidence' => 0-1, 'type' => string, 'markers' => array]
     */
    public function detectSarcasm(string $text): array
    {
        $text = strtolower(trim($text));
        
        $markers = [];
        $confidence = 0;
        $type = 'none';
        
        // Check each sarcasm pattern type
        $patterns = $this->getSarcasmPatterns();
        
        foreach ($patterns as $patternType => $patternList) {
            $result = $this->checkPattern($text, $patternList);
            
            if ($result['found']) {
                $markers[] = $result['marker'];
                $confidence += $result['strength'];
                
                // Set type to most confident pattern
                if ($result['strength'] > 0.6 && empty($type) || $type === 'none') {
                    $type = $patternType;
                }
            }
        }
        
        // Normalize confidence to 0-1
        $confidence = min(1.0, $confidence / 2);
        
        // Sarcasm detected if confidence >= 0.4
        $isSarcasm = $confidence >= 0.4;
        
        return [
            'is_sarcasm' => $isSarcasm,
            'confidence' => round($confidence, 2),
            'type' => $type,
            'markers' => $markers,
        ];
    }
    
    /**
     * Define sarcasm detection patterns
     */
    private function getSarcasmPatterns(): array
    {
        return [
            // Negative Sarcasm: Positive words used to describe negative situations
            'negative_sarcasm' => [
                // English patterns
                ['pattern' => 'oh great', 'keywords' => ['great', 'perfect', 'wonderful', 'amazing', 'fantastic', 'excellent']],
                ['pattern' => 'just what i needed', 'keywords' => ['needed', 'want', 'wanted']],
                ['pattern' => 'just wonderful', 'keywords' => ['wonderful', 'perfect', 'great']],
                ['pattern' => 'absolutely perfect', 'keywords' => ['absolutely', 'perfect', 'wonderful']],
                ['pattern' => 'so wonderful', 'keywords' => ['wonderful', 'perfect', 'great', 'amazing']],
                // Filipino patterns
                ['pattern' => 'napakaganda', 'keywords' => ['ganda', 'perpekto', 'perpektong', 'napakasarap']],
                ['pattern' => 'napakagaling', 'keywords' => ['galing', 'perpekto']],
                ['pattern' => 'ang sarap ng', 'keywords' => ['sarap', 'ganda']],
            ],
            
            // Exaggeration/Hyperbole: Extreme statements that are obviously not literal
            'hyperbole' => [
                // Death-related hyperbole (not actual suicide risk)
                ['pattern' => 'so happy i could die', 'keywords' => ['happy', 'die', 'dying']],
                ['pattern' => 'dying of', 'keywords' => ['dying', 'die']],  // dying of laughter, boredom, etc
                ['pattern' => 'killing me', 'keywords' => ['kill', 'killing']],  // this is killing me = frustrating
                ['pattern' => 'literally dying', 'keywords' => ['literally', 'dying', 'die']],
                ['pattern' => 'about to die', 'keywords' => ['about', 'die', 'dying']],
                // Other hyperboles
                ['pattern' => 'a million times', 'keywords' => ['million', 'thousand', 'billion']],
                ['pattern' => 'forever ago', 'keywords' => ['forever', 'ages', 'million years']],
                ['pattern' => 'starving', 'keywords' => ['starving', 'dying of hunger']],  // Not literal starvation
                ['pattern' => 'freezing', 'keywords' => ['freezing', 'cold', 'ice']],  // Not literal freezing
                ['pattern' => 'boiling', 'keywords' => ['boiling', 'hot', 'melting']],  // Not literal heat death
            ],
            
            // Ironic Agreement: Agreement that implies opposite meaning
            'ironic_agreement' => [
                ['pattern' => 'yeah because', 'keywords' => ['yeah', 'yeah right', 'because', 'that always']],
                ['pattern' => 'sure that works', 'keywords' => ['sure', 'right', 'works', 'always']],
                ['pattern' => 'yep totally', 'keywords' => ['yep', 'totally', 'definitely']],
                ['pattern' => 'right sure', 'keywords' => ['right', 'sure']],
                ['pattern' => 'oh absolutely', 'keywords' => ['oh', 'absolutely', 'definitely']],
                ['pattern' => 'of course', 'keywords' => ['of course', 'naturally', 'obviously']],  // In negative context
                // Filipino
                ['pattern' => 'oo sobra', 'keywords' => ['oo', 'sobra', 'talaga']],
                ['pattern' => 'alam mo naman', 'keywords' => ['alam', 'naman']],
            ],
            
            // Exaggerated Intensity: Adding extreme adjectives to mundane situations
            'overstatement' => [
                ['pattern' => 'absolutely terrible', 'keywords' => ['absolutely', 'terrible', 'horrible', 'awful']],
                ['pattern' => 'literally the worst', 'keywords' => ['literally', 'worst', 'terrible']],
                ['pattern' => 'completely ruined', 'keywords' => ['completely', 'ruined', 'destroyed']],
                ['pattern' => 'worst day ever', 'keywords' => ['worst', 'ever', 'day']],
                ['pattern' => 'can\'t even', 'keywords' => ['can\'t', 'even', 'literally']],  // I can't even = overwhelmed (often sarcastic)
                // Filipino
                ['pattern' => 'pinakamasamang', 'keywords' => ['pinakamasama', 'worst', 'laging masama']],
                ['pattern' => 'lubos na nakakaengganyo', 'keywords' => ['lubos', 'nakakaengganyo', 'sobrang']],
            ],
            
            // Dismissive Exaggeration: Minimizing real problems with exaggeration
            'dismissive' => [
                ['pattern' => 'oh whatever', 'keywords' => ['whatever', 'who cares', 'who even cares']],
                ['pattern' => 'like i care', 'keywords' => ['like i care', 'as if', 'right']],
                ['pattern' => 'i\'m sure', 'keywords' => ['i\'m sure', 'right like', 'because that']],
                // Filipino
                ['pattern' => 'parang mahalaga', 'keywords' => ['parang', 'mahalaga', 'malalim']],
            ],
            
            // Wishful Sarcasm: Expressing desires sarcastically
            'wishful' => [
                ['pattern' => 'if only', 'keywords' => ['if only', 'i wish', 'i so wish', 'god i wish']],
                ['pattern' => 'lucky me', 'keywords' => ['lucky me', 'lucky us', 'how lucky']],
                ['pattern' => 'great for me', 'keywords' => ['great for me', 'perfect for me']],
                // Filipino
                ['pattern' => 'siyempre talaga', 'keywords' => ['siyempre', 'talaga', 'ng may puso']],
            ]
        ];
    }
    
    /**
     * Check if text matches a sarcasm pattern
     */
    private function checkPattern(string $text, array $patterns): array
    {
        foreach ($patterns as $item) {
            $pattern = $item['pattern'] ?? '';
            $keywords = $item['keywords'] ?? [];
            
            if (empty($pattern)) {
                continue;
            }
            
            // Check if primary pattern exists
            if (strpos($text, $pattern) === false) {
                continue;
            }
            
            // Found the pattern! Now measure strength
            $strength = 0.5; // Base strength for pattern match
            
            // Boost strength if multiple keywords from pattern are found
            $keywordCount = 0;
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $keywordCount++;
                }
            }
            
            if ($keywordCount > 0) {
                $strength = 0.5 + ($keywordCount / count($keywords)) * 0.5;
            }
            
            return [
                'found' => true,
                'marker' => $pattern,
                'strength' => min(1.0, $strength),
            ];
        }
        
        return [
            'found' => false,
            'marker' => '',
            'strength' => 0,
        ];
    }
    
    /**
     * Check for sarcasm context modifiers
     * Some statements are sarcastic but don't match patterns
     */
    public function detectSarcasmByContext(string $text, array $previousMessages = []): array
    {
        $text = strtolower(trim($text));
        
        $contextMarkers = [];
        $confidence = 0;
        
        // Check for explicit sarcasm markers
        if (preg_match('/\b(just kidding|jk|just messing|lol|haha|😂|😏|🙄)\b/i', $text)) {
            $contextMarkers[] = 'explicit_marker';
            $confidence += 0.6;
        }
        
        // Check for contradiction with previous message
        if (!empty($previousMessages)) {
            $lastMessage = array_pop($previousMessages);
            if (!empty($lastMessage)) {
                $lastText = strtolower($lastMessage);
                
                // If current statement contradicts last, might be sarcasm
                // E.g., "I'm so depressed" followed by "Actually I'm fine"
                if (strpos($text, 'actually') !== false || strpos($text, 'nevermind') !== false) {
                    $contextMarkers[] = 'contradiction';
                    $confidence += 0.3;
                }
            }
        }
        
        // Check for venting disclaimers (not sarcasm but important context)
        if (preg_match('/\b(just venting|just ranting|not serious|for real though|seriously though)\b/i', $text)) {
            $contextMarkers[] = 'venting_disclaimer';
            $confidence = 0; // This is NOT sarcasm, it's clarification
        }
        
        return [
            'is_sarcasm' => $confidence >= 0.5,
            'confidence' => round(min(1.0, $confidence), 2),
            'markers' => $contextMarkers,
        ];
    }
    
    /**
     * Get sarcasm indicator for emoji/tone
     */
    public function detectSarcasmByTone(string $text): array
    {
        $sarcasmEmojis = ['😏', '🙄', '😒', '🤦', '🤪', '😜', '😑'];
        $foundEmojis = [];
        
        foreach ($sarcasmEmojis as $emoji) {
            if (strpos($text, $emoji) !== false) {
                $foundEmojis[] = $emoji;
            }
        }
        
        return [
            'found_sarcasm_emojis' => !empty($foundEmojis),
            'emojis' => $foundEmojis,
            'confidence' => empty($foundEmojis) ? 0 : 0.7,
        ];
    }
    
    /**
     * Flip emotion based on detected sarcasm
     * If sarcasm detected, reverse the emotion polarity
     */
    public function applySarcasmToEmotion(array $emotionScores, array $sarcasmResult): array
    {
        if (!$sarcasmResult['is_sarcasm'] || $sarcasmResult['confidence'] < 0.6) {
            return $emotionScores;
        }
        
        // Reverse happy/sad and other emotional polarity
        $emotionScores['happy'] = ($emotionScores['happy'] ?? 0) * 0.1;
        $emotionScores['sad'] = ($emotionScores['sad'] ?? 0) * 0.8;
        $emotionScores['frustrated'] = ($emotionScores['frustrated'] ?? 0) + 0.3;
        $emotionScores['neutral'] = max(0.1, $emotionScores['neutral'] ?? 0);
        
        // Add sarcasm indicator
        $emotionScores['sarcasm_detected'] = true;
        $emotionScores['sarcasm_confidence'] = $sarcasmResult['confidence'];
        
        return $emotionScores;
    }
}
