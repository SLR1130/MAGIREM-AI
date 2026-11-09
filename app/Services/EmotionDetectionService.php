<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\MLTrainingData;

class EmotionDetectionService
{
    protected $trainingService;

    public function __construct()
    {
        // Initialize training service if needed
        $this->trainingService = app(MLTrainingService::class);
    }
    
    /**
     * Enhanced detection using learned patterns from training data
     */
    private function detectWithLearnedPatterns(string $text): array
    {
        $text = strtolower($text);
        
        // Try to load learned patterns from cache
        $learnedPatterns = cache('learned_emotion_patterns');
        
        if (!$learnedPatterns) {
            // Load from database (first 1000 samples for speed)
            $trainingData = \App\Models\MLTrainingData::where('is_validated', true)
                ->select('text_input', 'actual_emotion')
                ->limit(1000)
                ->get();
            
            if ($trainingData->isEmpty()) {
                return $this->detectWithRules($text);
            }
            
            $learnedPatterns = [];
            
            foreach ($trainingData as $sample) {
                $emotion = $sample->actual_emotion;
                $sampleText = strtolower($sample->text_input);
                
                // Extract significant words
                $words = preg_split('/\s+/', $sampleText);
                
                foreach ($words as $word) {
                    $word = preg_replace('/[^a-z0-9]/', '', $word);
                    
                    if (strlen($word) > 3) {
                        if (!isset($learnedPatterns[$emotion])) {
                            $learnedPatterns[$emotion] = [];
                        }
                        
                        if (!isset($learnedPatterns[$emotion][$word])) {
                            $learnedPatterns[$emotion][$word] = 0;
                        }
                        
                        $learnedPatterns[$emotion][$word]++;
                    }
                }
            }
            
            // Keep top 50 patterns per emotion
            foreach ($learnedPatterns as $emotion => $patterns) {
                arsort($patterns);
                $learnedPatterns[$emotion] = array_slice($patterns, 0, 50, true);
            }
            
            // Cache for 1 hour
            cache(['learned_emotion_patterns' => $learnedPatterns], 3600);
        }
        
        // Score emotions based on learned patterns
        $scores = [];
        
        foreach ($learnedPatterns as $emotion => $patterns) {
            $score = 0;
            
            foreach ($patterns as $pattern => $frequency) {
                if (strpos($text, $pattern) !== false) {
                    $weight = min($frequency / 5, 3);
                    $score += $weight;
                }
            }
            
            $scores[$emotion] = $score;
        }
        
        $maxScore = max($scores);
        
        if ($maxScore > 0) {
            $detectedEmotion = array_search($maxScore, $scores);
            
            // Normalize emotion labels to standard format
            $emotionMap = [
                'joy' => 'happy',
                'happiness' => 'happy',
                'sadness' => 'sad',
                'fear' => 'anxious',
                'anxiety' => 'anxious',
                'anger' => 'angry',
                'disgust' => 'angry',
                'surprise' => 'neutral'
            ];
            
            if (isset($emotionMap[$detectedEmotion])) {
                $detectedEmotion = $emotionMap[$detectedEmotion];
            }
            
            if ($maxScore >= 8) {
                $confidence = 95;
            } elseif ($maxScore >= 5) {
                $confidence = 85;
            } elseif ($maxScore >= 3) {
                $confidence = 75;
            } else {
                $confidence = 65;
            }
        } else {
            $detectedEmotion = 'neutral';
            $confidence = 50;
        }
        
        return [
            'emotion' => $detectedEmotion,
            'confidence' => $confidence / 100,
            'method' => 'learned',
            'raw_label' => null,
            'all_scores' => $scores
        ];
    }

    /**
     * Load learned patterns from training data
     */
    private function loadLearnedPatterns(): array
    {
        // Check if we have cached patterns
        $cacheKey = 'learned_emotion_patterns';
        $cached = cache($cacheKey);

        if ($cached) {
            return $cached;
        }

        // Load from database
        $trainingData = \App\Models\MLTrainingData::where('is_validated', true)
            ->select('text_input', 'actual_emotion')
            ->get();

        if ($trainingData->isEmpty()) {
            return [];
        }

        $patterns = [];

        foreach ($trainingData as $sample) {
            $emotion = $sample->actual_emotion;
            $text = strtolower($sample->text_input);

            // Extract words
            $words = preg_split('/\s+/', $text);

            foreach ($words as $word) {
                // Clean word
                $word = preg_replace('/[^a-z0-9]/', '', $word);

                // Skip short words and common words
                if (strlen($word) <= 3) continue;

                $commonWords = ['the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'her', 'was', 'one', 'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how', 'man', 'new', 'now', 'old', 'see', 'two', 'way', 'who', 'boy', 'did', 'its', 'let', 'put', 'say', 'she', 'too', 'use'];
                if (in_array($word, $commonWords)) continue;

                if (!isset($patterns[$emotion])) {
                    $patterns[$emotion] = [];
                }

                if (!isset($patterns[$emotion][$word])) {
                    $patterns[$emotion][$word] = 0;
                }

                $patterns[$emotion][$word]++;
            }
        }

        // Keep only top patterns per emotion
        foreach ($patterns as $emotion => $words) {
            arsort($words);
            $patterns[$emotion] = array_slice($words, 0, 100, true);
        }

        // Cache for 1 hour
        cache([$cacheKey => $patterns], 3600);

        return $patterns;
    }

    /**
     * Detect emotion from text using ML model
     *
     * @param string $text
     * @return array
     */
    public function detectEmotion(string $text): array
    {
        // ===== STEP 1: SARCASM DETECTION (Preprocessing) =====
        // Detect sarcasm BEFORE emotion classification to prevent false emotions
        $sarcasmDetector = new SarcasmDetectorService();
        $sarcasmResult = $sarcasmDetector->detectSarcasm($text);
        $sarcasmByContext = $sarcasmDetector->detectSarcasmByContext($text);
        $sarcasmByTone = $sarcasmDetector->detectSarcasmByTone($text);
        
        // Combine all sarcasm indicators
        $isSarcasm = $sarcasmResult['is_sarcasm'] || 
                    $sarcasmByContext['is_sarcasm'] || 
                    $sarcasmByTone['found_sarcasm_emojis'];
        
        // Check if ML detection is enabled
        $mlEnabled = env('ML_DETECTION_ENABLED', true);
        
        // Check if ML service is available
        if ($mlEnabled && $this->isMLServiceAvailable()) {
            $result = $this->detectWithML($text);
            
            // If ML provided comprehensive analysis, return it
            if (isset($result['crisis_level'])) {
                // Apply sarcasm to emotion if detected
                if ($isSarcasm && $sarcasmResult['confidence'] >= 0.6) {
                    $result = array_merge($result, [
                        'sarcasm_detected' => true,
                        'sarcasm_confidence' => $sarcasmResult['confidence'],
                        'sarcasm_type' => $sarcasmResult['type'],
                    ]);
                    // Flip emotions for sarcasm
                    $result['emotion_scores'] = $sarcasmDetector->applySarcasmToEmotion(
                        $result['emotion_scores'] ?? [],
                        $sarcasmResult
                    );
                }
                
                return $result;
            }
            
            // Otherwise add rule-based crisis/context detection
            $crisis = $this->detectCrisis($text);
            $context = $this->analyzeContext($text, $result['emotion']);
            
            $finalResult = array_merge($result, [
                'crisis_level' => $crisis['level'],
                'needs_counselor' => $crisis['needs_counselor'],
                'contexts' => $context['contexts'] ?? [],
            ]);
            
            // Apply sarcasm if detected
            if ($isSarcasm && $sarcasmResult['confidence'] >= 0.6) {
                $finalResult['sarcasm_detected'] = true;
                $finalResult['sarcasm_confidence'] = $sarcasmResult['confidence'];
                $finalResult['sarcasm_type'] = $sarcasmResult['type'];
            }
            
            return $finalResult;
        }

        // Use rule-based detection (fast and reliable)
        Log::info('Using rule-based emotion detection');
        $result = $this->detectWithRules($text);
        
        // Apply sarcasm if detected
        if ($isSarcasm && $sarcasmResult['confidence'] >= 0.6) {
            // If sarcasm detected with high confidence, flip emotions
            $result = $sarcasmDetector->applySarcasmToEmotion($result, $sarcasmResult);
        }
        
        $crisis = $this->detectCrisis($text);
        $context = $this->analyzeContext($text, $result['emotion']);
        
        return array_merge($result, [
            'crisis_level' => $crisis['level'],
            'needs_counselor' => $crisis['needs_counselor'],
            'contexts' => $context['contexts'] ?? [],
            'sarcasm_detected' => $isSarcasm,
            'sarcasm_confidence' => $sarcasmResult['confidence'],
            'sarcasm_type' => $sarcasmResult['type'] ?? 'none',
        ]);
    }

    /**
     * Detect emotion using Python ML model
     *
     * @param string $text
     * @return array
     */
    private function detectWithML(string $text): array
    {
        try {
            // First, try ML server (fast if running)
            if ($this->isMLServerRunning()) {
                return $this->detectWithMLServer($text);
            }
            
            // Fallback to direct Python script (slow)
            return $this->detectWithMLScript($text);
            
        } catch (\Exception $e) {
            Log::error('ML emotion detection failed: ' . $e->getMessage());
            return $this->detectWithRules($text);
        }
    }
    
    /**
     * Check if ML server is running (with caching)
     */
    private function isMLServerRunning(): bool
    {
        // Cache the server status for 30 seconds, scoped to the configured endpoint.
        $healthUrl = $this->mlApiUrl('health');
        $cacheKey = 'ml_server_status:' . sha1($healthUrl);
        $cached = cache($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $ch = curl_init($healthUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $isRunning = $httpCode === 200;
            
            // Cache for 30 seconds
            cache([$cacheKey => $isRunning], 30);
            
            return $isRunning;
        } catch (\Exception $e) {
            // Cache negative result for shorter time (10 seconds)
            cache([$cacheKey => false], 10);
            return false;
        }
    }

    /**
     * Resolve the configured Flask instance for all service calls.
     */
    private function mlApiUrl(string $path = ''): string
    {
        $baseUrl = rtrim((string) config('services.mindcare_ml.url'), '/');

        return $baseUrl . '/' . ltrim($path, '/');
    }
    
    /**
     * Use ML server for ULTRA-FAST predictions (100-200ms)
     */
    private function detectWithMLServer(string $text): array
    {
        $startTime = microtime(true);
        
        $ch = curl_init($this->mlApiUrl('analyze'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['text' => $text]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Reduced timeout for faster failure
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1); // Fast connection timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $elapsedTime = microtime(true) - $startTime;
        Log::info("ML server detection took " . round($elapsedTime * 1000) . "ms");
        
        if ($httpCode !== 200) {
            throw new \Exception('ML server returned error: ' . $httpCode);
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON from ML server');
        }
        
        Log::info('ML server detection successful: ' . $result['emotion'] . ' (' . $result['confidence'] . '%) in ' . round($elapsedTime * 1000) . 'ms');
        
        // ML server returns confidence as percentage (0-100), convert to decimal (0-1)
        $confidence = ($result['confidence'] ?? 50.0) / 100.0;
        
        $serverMethod = (string) ($result['method'] ?? 'ml_server_fast');
        $confidenceType = str_contains($serverMethod, 'trained')
            ? 'model_softmax_max'
            : 'semantic_heuristic_score';

        return [
            'emotion' => $result['emotion'] ?? 'neutral',
            'confidence' => $confidence,
            'method' => $serverMethod,
            'confidence_type' => $result['confidence_type'] ?? $confidenceType,
            'raw_label' => $result['raw_label'] ?? null,
            'all_scores' => $result['all_scores'] ?? [],
            'crisis_level' => $result['crisis_level'] ?? 'none',
            'needs_counselor' => $result['needs_counselor'] ?? false,
            'crisis_confidence' => $result['crisis_confidence'] ?? 0,
            'crisis_category' => $result['crisis_category'] ?? null,
            'contexts' => $result['contexts'] ?? [],
            'context_details' => $result['context_details'] ?? [],
            'processing_time_ms' => $result['processing_time_ms'] ?? round($elapsedTime * 1000)
        ];
    }
    
    /**
     * Fallback: Use Python script directly (slow)
     */
    private function detectWithMLScript(string $text): array
    {
        try {
            // Set a timeout for ML detection (5 seconds max)
            $startTime = microtime(true);
            
            // Escape text for command line
            $escapedText = escapeshellarg($text);
            
            // Path to Python script
            $pythonScript = base_path('ml_service/emotion_detector.py');
            
            // Check if Python is available
            $pythonCommand = $this->getPythonCommand();
            
            if (!$pythonCommand) {
                Log::warning('Python command not found');
                throw new \Exception('Python not found');
            }

            // Execute Python script with timeout
            $command = "$pythonCommand \"$pythonScript\" $escapedText 2>&1";
            
            Log::info('Executing ML command: ' . $command);
            
            // Use proc_open for better timeout control
            $descriptorspec = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ];
            
            $process = proc_open($command, $descriptorspec, $pipes);
            
            if (is_resource($process)) {
                // Set timeout on output stream
                stream_set_timeout($pipes[1], 5);
                
                // Read output
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                
                $return_value = proc_close($process);
                
                $elapsedTime = microtime(true) - $startTime;
                Log::info("ML detection took {$elapsedTime} seconds");
                
                // If took too long, throw timeout
                if ($elapsedTime > 10) {
                    Log::warning('ML detection timeout after ' . $elapsedTime . ' seconds');
                    throw new \Exception('ML detection timeout');
                }
            } else {
                throw new \Exception('Failed to start ML process');
            }

            if (!$output) {
                Log::error('No output from ML service. Errors: ' . $errors);
                throw new \Exception('No output from ML service');
            }
            
            Log::info('ML output received: ' . substr($output, 0, 200));

            // Find JSON in output (might have loading messages before it)
            $jsonStart = strrpos($output, '{');
            if ($jsonStart !== false) {
                $jsonOutput = substr($output, $jsonStart);
            } else {
                $jsonOutput = $output;
            }

            // Parse JSON output
            $result = json_decode($jsonOutput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON from ML service. Output: ' . $output);
                throw new \Exception('Invalid JSON from ML service: ' . json_last_error_msg());
            }

            if (isset($result['error'])) {
                Log::error('ML service returned error: ' . $result['error']);
                throw new \Exception($result['error']);
            }
            
            Log::info('ML detection successful: ' . $result['emotion'] . ' (' . $result['confidence'] . '%)');

            // ML now returns comprehensive analysis
            return [
                'emotion' => $result['emotion'] ?? 'neutral',
                'confidence' => $result['confidence'] ?? 50.0,
                'method' => $result['method'] ?? 'ml',
                'raw_label' => $result['raw_label'] ?? null,
                'all_scores' => $result['all_scores'] ?? [],
                'crisis_level' => $result['crisis_level'] ?? 'none',
                'needs_counselor' => $result['needs_counselor'] ?? false,
                'crisis_confidence' => $result['crisis_confidence'] ?? 0,
                'crisis_category' => $result['crisis_category'] ?? null,
                'contexts' => $result['contexts'] ?? [],
                'context_details' => $result['context_details'] ?? []
            ];

        } catch (\Exception $e) {
            Log::error('ML emotion detection failed: ' . $e->getMessage());
            return $this->detectWithRules($text);
        }
    }

    /**
     * Apply context modifiers to adjust emotion scores
     * Words like "but", "however", "still" can change emotional meaning
     *
     * @param string $text
     * @param array $scores
     * @return array
     */
    private function applyContextModifiers(string $text, array $scores): array
    {
        $text = strtolower($text);
        
        // Context modifiers that reduce negative emotion scores
        $contextModifiers = [
            'reframing' => ['but', 'but it', 'however', 'though', 'although', 'yet', 'still', 'anyway', 'at least', 'nalang', 'na lang', 'pa rin', 'anyway', 'kaya lang'],
            'acceptance' => ['okay', 'ok', 'its ok', "it's ok", 'im okay', "i'm okay", 'chill', 'relax', 'nvm', 'nevermind', 'never mind', 'ayos lang', 'okay na', 'fine na'],
            'minimizing' => ['kinda', 'sort of', 'a bit', 'a little', 'slightly', 'not that', 'not really', 'not too', 'hindi talaga', 'medyo lang', 'konti lang'],
            'hopeful' => ['better', 'improve', 'getting better', 'will be better', 'improve later', 'hopefully', 'maybe', 'soon', 'eventually', 'magiging okay', 'magiging better'],
        ];
        
        // Check for negative-to-positive transitions (e.g., "not good but it's ok")
        // Pattern: "NOT_GOOD_WORD but/however POSITIVE_WORD"
        $transitionPatterns = [
            'not_good_but_positive' => [
                'not good but it ok',
                'not good but ok',
                'not good but im ok',
                'not okay but im chill',
                'not okay but its fine',
                'not feeling good but im okay',
                'hindi okay pero okay lang',
                'hindi okay pero fine na',
                'not good but at least',
            ],
            'struggling_but_managing' => [
                'struggling but managing',
                'struggling but okay',
                'struggling but coping',
                'hard but im okay',
                'difficult but alright',
                'napakahirap pero okay',
                'mahirap pero kaya',
            ],
        ];
        
        // If text matches a balanced/neutral transition pattern, dramatically reduce sad/anxious scores
        foreach ($transitionPatterns as $pattern_type => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($text, $pattern) !== false) {
                    // User is explicitly stating they're handling it well despite challenges
                    $scores['sad'] = max(0, ($scores['sad'] ?? 0) * 0.3); // Reduce by 70%
                    $scores['depressed'] = 0; // Remove depression interpretation
                    $scores['anxious'] = max(0, ($scores['anxious'] ?? 0) * 0.4); // Reduce anxiety by 60%
                    
                    // Boost neutral/happy if not already present
                    if (!isset($scores['neutral']) || $scores['neutral'] < 2) {
                        $scores['neutral'] = 3;
                    }
                    
                    return $scores;
                }
            }
        }
        
        // Check for reframing/acceptance modifiers
        if ($this->hasPattern($text, $contextModifiers['reframing'])) {
            // Split on reframing words and check what comes after
            foreach (['but', 'however', 'though', 'yet'] as $modifier) {
                if (strpos($text, $modifier) !== false) {
                    $parts = explode($modifier, $text);
                    if (count($parts) > 1) {
                        $after = strtolower($parts[1]);
                        
                        // If what comes after is positive, reduce negative scores significantly
                        $positiveWords = ['ok', 'okay', 'fine', 'alright', 'good', 'better', 'improving', 'improving na', 'okay na', 'ayos na'];
                        foreach ($positiveWords as $word) {
                            if (strpos($after, $word) !== false) {
                                $scores['sad'] = max(0, ($scores['sad'] ?? 0) * 0.4);
                                $scores['anxious'] = max(0, ($scores['anxious'] ?? 0) * 0.5);
                                $scores['depressed'] = 0;
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        // If text shows acceptance despite difficulties
        if ($this->hasPattern($text, $contextModifiers['acceptance'])) {
            $scores['sad'] = max(0, ($scores['sad'] ?? 0) * 0.5); // Cut in half
            $scores['anxious'] = max(0, ($scores['anxious'] ?? 0) * 0.6);
            
            // Boost neutral
            if (!isset($scores['neutral']) || $scores['neutral'] < 2) {
                $scores['neutral'] = 3;
            }
        }
        
        // If user is minimizing (slight negative, not serious)
        if ($this->hasPattern($text, $contextModifiers['minimizing'])) {
            $scores['sad'] = max(0, ($scores['sad'] ?? 0) * 0.6);
            $scores['anxious'] = max(0, ($scores['anxious'] ?? 0) * 0.7);
        }
        
        return $scores;
    }
    
    /**
     * Analyze sarcasm and satire to prevent false crisis alerts
     * Checks for linguistic markers, emoji context, and contradiction patterns
     *
     * @param string $text
     * @return array
     */
    private function analyzeSarcasmAndSatire(string $text): array
    {
        $sarcasmScore = 0;
        $satireScore = 0;
        $markers = [];
        
        $lowerText = strtolower($text);
        
        // ===== EMOJI ANALYSIS =====
        // Sarcastic/dismissive emojis
        $sarcasmEmojis = ['🙄', '😒', '😏', '😑', '🤨', '🙃'];
        // Laughing/joking emojis
        $jokingEmojis = ['😂', '😆', '😅', '🤣', '😹', 'lol', 'lmao', 'haha', 'rofl'];
        
        foreach ($sarcasmEmojis as $emoji) {
            if (strpos($text, $emoji) !== false) {
                $sarcasmScore += 2;
                $markers[] = 'sarcasm_emoji';
                break;
            }
        }
        
        foreach ($jokingEmojis as $emoji) {
            if (strpos($lowerText, $emoji) !== false) {
                $satireScore += 1.5;
                $markers[] = 'joking_indicator';
                break;
            }
        }
        
        // ===== SARCASM TEXT PATTERNS =====
        $sarcasmPatterns = [
            // Classic sarcasm openers
            '/\b(yeah sure|oh sure|right|yep|sure sure|oh yeah)\b/i',
            // Sarcastic disagreement
            '/\b(like i care|who cares|yeah right|oh please)\b/i',
            // Joking about serious topics
            '/(lol|lmao|haha).*?(kill|hurt|die|suicide|self.*harm)/i',
            '/\b(kill|hurt|die|suicide|self.*harm).*?(lol|lmao|haha)\b/i',
            // Sarcastic negation ("NOT!")
            '/\b(not|no way|never)\s+(happening|going to|will|kill myself|hurt myself)\b/i',
        ];
        
        foreach ($sarcasmPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $sarcasmScore += 1.5;
                $markers[] = 'sarcasm_phrase';
                break;
            }
        }
        
        // ===== CONTRADICTION DETECTION =====
        // Positive statement + negative content = sarcasm
        $positiveWords = ['love', 'best', 'amazing', 'great', 'awesome', 'perfect', 'wonderful', 'glad', 'happy'];
        $negativeWords = ['kill myself', 'hurt myself', 'want to die', 'suicidal', 'self-harm', 'harm myself'];
        
        foreach ($positiveWords as $positive) {
            if (strpos($lowerText, $positive) !== false) {
                foreach ($negativeWords as $negative) {
                    if (strpos($lowerText, $negative) !== false) {
                        $sarcasmScore += 1;
                        $markers[] = 'positive_negative_contradiction';
                        break 2;
                    }
                }
            }
        }
        
        // ===== CONFIDENCE CALCULATION =====
        // Even if sarcasm detected, override only if score is high enough
        $isSarcasm = $sarcasmScore >= 1.5;
        $isSatire = $satireScore >= 1.0;
        $overallScore = ($sarcasmScore + $satireScore) / 2;
        $confidence = min(1.0, $overallScore / 3.0); // Normalize to 0-1
        
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
     * Determines if crisis is historical, hypothetical, current, or immediate
     *
     * @param string $text
     * @return array
     */
    private function analyzeTemporalContext(string $text): array
    {
        $text = strtolower($text);
        
        // ===== PAST TENSE MARKERS =====
        $pastMarkers = [
            'used to', 'i used', 'before', 'years ago', 'back then',
            'when i was', 'previously', 'yesterday', 'last week', 'last month', 'last year',
            'i did', 'i had', 'had suicidal', 'was thinking', 'was feeling'
        ];
        
        // ===== PRESENT TENSE MARKERS =====
        $presentMarkers = [
            'right now', 'now', 'today', 'at this moment', 'currently',
            'i am', 'i want to', 'i\'m', 'im thinking', 'feeling like', 'is happening'
        ];
        
        // ===== FUTURE/IMMEDIATE MARKERS =====
        $futureMarkers = [
            'tomorrow', 'tonight', 'this week', 'soon', 'will',
            'planning to', 'going to', 'i\'ll', 'ill', 'when i get home',
            'after', 'once', 'if i ever', 'when i can'
        ];
        
        // ===== IMMEDIATE/TODAY MARKERS =====
        $immediateMarkers = [
            'tonight', 'today', 'right now', 'this minute', 'immediately',
            'now', 'in a few hours', 'any minute'
        ];
        
        $hasPastTense = false;
        $hasPresentTense = false;
        $hasFutureTense = false;
        $hasImmediateTense = false;
        
        foreach ($pastMarkers as $marker) {
            if (strpos($text, $marker) !== false) {
                $hasPastTense = true;
                break;
            }
        }
        
        foreach ($presentMarkers as $marker) {
            if (strpos($text, $marker) !== false) {
                $hasPresentTense = true;
                break;
            }
        }
        
        foreach ($futureMarkers as $marker) {
            if (strpos($text, $marker) !== false) {
                $hasFutureTense = true;
                break;
            }
        }
        
        foreach ($immediateMarkers as $marker) {
            if (strpos($text, $marker) !== false) {
                $hasImmediateTense = true;
                break;
            }
        }
        
        // Determine temporal urgency (0-1 scale)
        $temporalUrgency = 0;
        if ($hasImmediateTense && $hasPresentTense) {
            $temporalUrgency = 1.0; // Immediate threat
        } elseif ($hasPresentTense && $hasFutureTense) {
            $temporalUrgency = 0.9; // Near-term planning + present ideation
        } elseif ($hasFutureTense) {
            $temporalUrgency = 0.7; // Future planning
        } elseif ($hasPresentTense) {
            $temporalUrgency = 0.8; // Current active ideation
        } elseif ($hasPastTense && !$hasPresentTense && !$hasFutureTense) {
            $temporalUrgency = 0.2; // Historical only
        }
        
        return [
            'temporal_urgency' => $temporalUrgency,
            'is_immediate' => $hasImmediateTense && $hasPresentTense,
            'is_present' => $hasPresentTense,
            'is_future' => $hasFutureTense,
            'is_historical' => $hasPastTense && !$hasPresentTense && !$hasFutureTense,
            'markers' => [
                'past' => $hasPastTense,
                'present' => $hasPresentTense,
                'future' => $hasFutureTense,
                'immediate' => $hasImmediateTense
            ]
        ];
    }
    
    /**
     * Detect intent, plan, method, and access indicators
     * These are major escalation factors for crisis severity
     *
     * @param string $text
     * @return array
     */
    private function detectIntentPlanMethodAccess(string $text): array
    {
        $text = strtolower($text);
        
        $intentDetected = false;
        $planDetected = false;
        $methodDetected = false;
        $accessDetected = false;
        $recurrenceDetected = false;
        
        $escalationScore = 0;
        $indicators = [];
        
        // ===== INTENT INDICATORS =====
        $intentKeywords = [
            'i think i', 'i want to', 'i\'m going to', 'i will', 'i might',
            'i might kill', 'i might hurt', 'thinking about', 'contemplating',
            'considering', 'i\'ve decided', 'i\'ve made up my mind'
        ];
        
        foreach ($intentKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $intentDetected = true;
                $escalationScore += 1;
                $indicators[] = 'intent_expressed';
                break;
            }
        }
        
        // ===== PLAN INDICATORS (concrete planning) =====
        $planKeywords = [
            'figured out how', 'worked it out', 'thought it through',
            'planned', 'going to do it', 'decided how', 'know exactly how',
            'have a way', 'know what to use', 'calculated', 'timed it'
        ];
        
        foreach ($planKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $planDetected = true;
                $escalationScore += 2;
                $indicators[] = 'plan_detected';
                break;
            }
        }
        
        // ===== METHOD INDICATORS (specific means) =====
        $methodKeywords = [
            'gun', 'rifle', 'pistol', 'revolver', 'knife', 'blade', 'rope',
            'hanging', 'pills', 'overdose', 'od', 'poison', 'carbon monoxide',
            'car exhaust', 'bridge', 'cliff', 'jumping', 'drowning', 'cut',
            'slash', 'stab', 'wrist', 'vein', 'razorblade', 'blade', 'medication'
        ];
        
        foreach ($methodKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $methodDetected = true;
                $escalationScore += 2;
                $indicators[] = 'method_mentioned';
                break;
            }
        }
        
        // ===== ACCESS INDICATORS =====
        $accessKeywords = [
            'i have', 'have access', 'can get', 'available', 'in my room',
            'under my bed', 'in the garage', 'at home', 'my dad\'s', 'my mom\'s',
            'my parents\'', 'found', 'took', 'stole', 'kept', 'hidden',
            'dad\'s gun', 'mom\'s pills', 'parent\'s', 'in the house'
        ];
        
        foreach ($accessKeywords as $keyword) {
            if (strpos($text, $keyword) !== false && $methodDetected) {
                // Only count as access if a method was already mentioned
                $accessDetected = true;
                $escalationScore += 2;
                $indicators[] = 'access_to_means';
                break;
            }
        }
        
        // ===== RECURRENCE INDICATORS (relapse) =====
        $recurrenceKeywords = [
            'again', 'still', 'coming back', 'returning', 'happening again',
            'back to', 'those thoughts again', 'i\'m back', 'here we go again',
            'thought i was past this', 'i\'ve been doing so well but', 'starting again'
        ];
        
        foreach ($recurrenceKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $recurrenceDetected = true;
                $indicators[] = 'recurrence_detected';
                break;
            }
        }
        
        return [
            'intent_detected' => $intentDetected,
            'plan_detected' => $planDetected,
            'method_detected' => $methodDetected,
            'access_detected' => $accessDetected,
            'recurrence_detected' => $recurrenceDetected,
            'escalation_score' => $escalationScore,
            'indicators' => $indicators
        ];
    }
    
    private function hasPattern(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fallback rule-based emotion detection
     *
     * @param string $text
     * @return array
     */
    private function detectWithRules(string $text): array
    {
        $text = strtolower($text);
        
        // Enhanced emotion keywords with Filipino words and better patterns
        $emotions = [
            'content' => [
                'content', 'satisfied', 'okay', 'fine', 'steady', 'balanced', 'good enough', 'pretty good',
                'not bad', 'all right', 'alright', 'feeling okay', 'feeling fine', 'feeling stable'
            ],
            'calm' => [
                'calm', 'relaxed', 'peaceful', 'settled', 'chill', 'at ease', 'not stressed', 'low key',
                'quiet', 'slow', 'rested'
            ],
            'happy' => [
                'happy', 'joy', 'joyful', 'excited', 'great', 'wonderful', 'amazing', 'good', 'excellent', 
                'fantastic', 'love', 'blessed', 'grateful', 'thankful', 'proud', 'accomplished', 'celebrating',
                'thrilled', 'delighted', 'cheerful', 'glad', 'awesome', 'perfect', 'best day',
                'feeling good', 'so happy', 'really happy', 'super happy',
                'masaya', 'saya', 'tuwa', 'ligaya', 'natutuwa', 'masayang', 'sobrang saya', 'ang saya',
                'napakasaya', 'nakakatuwa', 'nakakagaan', 'blessed ako', 'salamat', 'grateful ako'
            ],
            'depressed' => [
                // CRITICAL: Depression-specific keywords (HIGHEST PRIORITY)
                'depressed', 'depression', 'clinical depression', 'major depression', 'depressive episode',
                'depressive disorder', 'im depressed', "i'm depressed", 'i am depressed', 'feeling depressed',
                'been depressed', 'depressed for weeks', 'depressed for months', 'depressed for a long time',
                // Loss of interest/joy
                'nothing makes me happy', 'lost interest in everything', 'dont enjoy anything', 
                "don't enjoy anything", 'cant find joy', "can't find joy", 'everything feels pointless',
                'no point in anything', 'no point in living', 'whats the point', "what's the point",
                'lost all interest', 'no interest in anything', 'stopped enjoying things',
                // Emotional numbness
                'feel empty', 'feel numb', 'feel hollow', 'feel dead inside', 'feel nothing',
                'emotionally numb', 'cant feel anything', "can't feel anything", 'numb inside',
                // Hopelessness
                'hopeless', 'hopelessness', 'no hope', 'cant see a future', "can't see a future",
                'no future', 'everything is hopeless', 'nothing will get better', 'wont get better',
                "won't get better", 'never get better', 'always be like this', 'always feel this way',
                // Worthlessness
                'worthless', 'useless', 'waste of space', 'nobody cares', 'nobody needs me',
                'im a burden', "i'm a burden", 'im a failure', "i'm a failure", 'im not good enough',
                "i'm not good enough", 'not worth it', 'not worth anything', 'im worthless',
                "i'm worthless", 'im useless', "i'm useless",
                // Behavioral changes
                'cant get out of bed', "can't get out of bed", 'dont want to do anything',
                "don't want to do anything", 'stopped doing things i enjoy', 'isolating myself',
                'withdrawing from friends', 'dont want to see anyone', "don't want to see anyone",
                'staying in bed all day', 'sleeping all day', 'cant sleep', "can't sleep",
                // Filipino depression keywords
                'depressed ako', 'depressed na', 'depression', 'malungkot na malungkot',
                'walang pag-asa', 'walang kwenta', 'walang halaga', 'walang silbi', 'walang punto',
                 'wala nang punto', 'walang dahilan', 'walang dahilan na mabuhay', 'ayaw ko na',
                'suko na ako', 'hindi ko na kaya', 'pagod na pagod na', 'nag-iisa na',
                'walang nakakaintindi', 'walang pakialam', 'walang mahal sa akin',
                'depressed na ako ng matagal', 'depressed na ng ilang linggo', 'depressed na ng ilang buwan'
            ],
            'down' => [
                'not good', 'not very good', 'not really good', 'not okay', 'not ok', 'not great', 'could be better', "it's okay", 'its okay', 'but it ok', 'but it is ok',
                'but it is okay', 'but it’s okay', 'but it’s ok', 'but im okay', 'but im fine', 'but i am okay', 'but i am fine',
                'just okay', 'just fine', 'feeling off', 'off today', 'a little down', 'a bit down', 'so-so', 'so so', 'kind of off', 'kinda off',
                'feeling meh', 'meh', 'fine i guess', 'not too bad', 'not too great', 'could be worse',
            ],
            'sad' => [
                'sad', 'unhappy', 'miserable', 'crying', 'hurt', 'pain', 
                'heartbroken', 'devastated', 'empty', 'numb', 'broken', 'lost', 'worthless',
                'failure', 'disappointed', 'rejected', 'abandoned', 'alone', 'isolated', 'tears', 'sobbing',
                'grief', 'mourning', 'miss', 'regret', 'sorry', 'wish i', 'if only', 'nobody cares',
                'no one understands', 'feel like giving up', 'want to disappear', 'tired of life',
                // Help-seeking and negative transition phrases
                'any advice', 'any suggestion', 'do you have any suggestion', 'do you have any advice', 'help me', 'need help', 'need advice',
                'not feeling good', 'not feeling well', 'not so good', 'not too good', 'not very good',
                // Motivation loss
                'lost motivation', 'no motivation', 'unmotivated', 'cant find motivation', 'losing motivation',
                'lost interest', 'no energy', 'burned out', 'burnout', 'exhausted', 'drained',
                'nawalan ng gana', 'walang gana', 'tamad', 'pagod na', 'burned out na',
                // Filipino
                'malungkot', 'lungkot', 'nalulungkot', 'sad ako', 'iyak', 'umiiyak', 'nasasaktan',
                'sakit', 'masakit', 'sira', 'wasak', 'wala na', 'pagod na', 'ayaw ko na', 'suko na',
                'walang kwenta', 'walang pag-asa', 'nag-iisa', 'mag-isa', 'walang nakakaintindi',
                'walang pakialam', 'hindi ko na kaya', 'gusto ko na sumuko',
            ],
            'depressed' => [
                'depressed', 'depression', 'clinical depression', 'major depression', 'depressive episode',
                'depressive disorder', 'im depressed', "i'm depressed", 'i am depressed', 'feeling depressed',
                'been depressed', 'depressed for weeks', 'depressed for months', 'depressed for a long time',
                'feel empty', 'feel numb', 'feel hollow', 'feel dead inside', 'feel nothing',
                'emotionally numb', 'cant feel anything', "can't feel anything", 'numb inside',
                'hopeless', 'hopelessness', 'no hope', 'cant see a future', "can't see a future",
                'no future', 'everything is hopeless', 'nothing will get better', 'wont get better',
                "won't get better", 'never get better', 'always be like this', 'always feel this way',
                'worthless', 'useless', 'waste of space', 'nobody cares', 'nobody needs me',
                'im a burden', "i'm a burden", 'im a failure', "i'm a failure", 'im not good enough',
                "i'm not good enough", 'not worth it', 'not worth anything', 'im worthless',
                "i'm worthless", 'im useless', "i'm useless",
                'cant get out of bed', "can't get out of bed", 'dont want to do anything',
                "don't want to do anything", 'stopped doing things i enjoy', 'isolating myself',
                'withdrawing from friends', 'dont want to see anyone', "don't want to see anyone",
                'staying in bed all day', 'sleeping all day', 'cant sleep', "can't sleep",
                // Filipino depression keywords
                'depressed ako', 'depressed na', 'depression', 'malungkot na malungkot',
                'walang pag-asa', 'walang kwenta', 'walang halaga', 'walang silbi', 'walang punto',
                'wala nang punto', 'walang dahilan', 'walang dahilan na mabuhay', 'ayaw ko na',
                'suko na ako', 'hindi ko na kaya', 'pagod na pagod na', 'nag-iisa na',
                'walang nakakaintindi', 'walang pakialam', 'walang mahal sa akin',
                'depressed na ako ng matagal', 'depressed na ng ilang linggo', 'depressed na ng ilang buwan'
            ],
            'frustrated' => [
                'frustrated', 'annoyed', 'irritated', 'fed up', 'sick of', 'tired of', "can't stand",
                'so annoyed', 'really annoyed', 'so frustrating', 'this is frustrating', 'nagagalit', 'inis',
                'naiinis', 'badtrip', 'bad trip', 'nakakainis', 'nakakagalit', 'gigil', 'bwisit',
            ],
            'lonely' => [
                'lonely', 'alone', 'isolated', 'no friends', 'nag-iisa', 'walang kaibigan', 'by myself',
                'feeling alone', 'left out', 'nobody understands', 'not understood',
            ],
            'overwhelmed' => [
                'overwhelmed', 'too much', "can't handle", 'cant handle', 'unable to cope', 'struggling',
                'having trouble', 'having a hard time', 'hard time', 'panic', 'overloaded', 'swamped',
                'everything is too much', 'too much to do', 'too much on my plate',
            ],
            'anxious' => [
                'anxious', 'worried', 'nervous', 'scared', 'afraid', 'tense', 'restless', 'uneasy', 'concerned',
                'troubled', 'distressed', 'frantic', 'terrified', 'paranoid', 'insecure', 'uncertain', 'doubt',
                'what if', 'racing thoughts', 'heart racing', 'cant breathe', 'shaking', 'sweating', 'nauseous',
                'exam', 'deadline', 'presentation', 'interview', 'grades', 'fail', 'behind', 'late',
                'kinakabahan', 'kabahan', 'takot', 'natatakot', 'nerbiyos', 'stress', 'stressed',
                'pressured', 'pressure', 'kaba', 'hindi makatulog', 'puyat', 'overthinking', 'nag-iisip',
                'paano kung', 'baka', 'exam ko', 'deadline', 'requirements', 'kulang', 'di ko kaya',
                'sobrang dami', 'ang dami', 'hindi ko alam', 'confused', 'nalilito'
            ],
            'angry' => [
                'angry', 'mad', 'frustrated', 'annoyed', 'irritated', 'furious', 'rage', 'hate', 'upset',
                'pissed', 'livid', 'outraged', 'resentful', 'bitter', 'hostile', 'aggressive', 'violent',
                'disgusted', 'fed up', 'sick of', 'cant stand', 'unfair', 'injustice', 'betrayed',
                'lied to', 'cheated', 'used', 'disrespected', 'insulted', 'bullied', 'attacked',
                'galit', 'nagagalit', 'inis', 'naiinis', 'badtrip', 'bad trip', 'nakakainis', 'nakakagalit',
                'gigil', 'bwisit', 'nakakainis talaga', 'sobrang inis', 'ang sama', 'masama', 'unfair',
                'hindi patas', 'sinungaling', 'niloko', 'ginamit', 'bully', 'binully', 'walang respeto',
                'bastos', 'walang hiya'
            ],
        ];

        // Calculate scores with weighted patterns
        $scores = [];
        foreach ($emotions as $emotion => $keywords) {
            $count = 0;
            
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    // Multi-word phrases get more weight
                    $wordCount = str_word_count($keyword);
                    $weight = $wordCount > 1 ? 3 : 1; // Increased weight for phrases
                    
                    // CRITICAL keywords get extra weight
                    $criticalKeywords = ['died', 'dead', 'death', 'suicide', 'kill', 'namatay', 'patay', 'pumanaw'];
                    foreach ($criticalKeywords as $critical) {
                        if (strpos($keyword, $critical) !== false) {
                            $weight = 5; // Very high weight for death/grief
                            break;
                        }
                    }
                    
                    $count += $weight;
                }
            }
            $scores[$emotion] = $count;
        }

        // Apply context modifiers to handle balanced/neutral statements like "not good but it ok"
        $scores = $this->applyContextModifiers($text, $scores);

        // Find emotion with highest score
        $maxScore = max($scores);
        
        if ($maxScore > 0) {
            $detectedEmotion = array_search($maxScore, $scores);
            
            if ($detectedEmotion === 'down' && $maxScore <= 2) {
                $confidence = 55;
            } elseif (in_array($detectedEmotion, ['content', 'calm', 'neutral', 'okay', 'meh'])) {
                $confidence = 60;
            } elseif ($maxScore >= 5) {
                $confidence = 95;
            } elseif ($maxScore >= 3) {
                $confidence = 85;
            } elseif ($maxScore >= 2) {
                $confidence = 75;
            } else {
                $confidence = 65;
            }
        } else {
            $detectedEmotion = 'neutral';
            $confidence = 50;
        }

        return [
            'emotion' => $detectedEmotion,
            'confidence' => $confidence / 100, // Convert to decimal
            'method' => 'rules',
            'raw_label' => null,
            'all_scores' => $scores
        ];
    }

    /**
     * Check if ML service is available
     *
     * @return bool
     */
    private function isMLServiceAvailable(): bool
    {
        // The deployable classifier is served by the configured Flask API; the historical local emotion_detector.py script is not part of the current runtime.
        return $this->isMLServerRunning();
    }

    /**
     * Get Python command (python or python3)
     *
     * @return string|null
     */
    private function getPythonCommand(): ?string
    {
        // Check if Python command is set in .env
        $envPython = env('PYTHON_COMMAND');
        if ($envPython) {
            $output = shell_exec("$envPython --version 2>&1");
            if ($output && strpos($output, 'Python') !== false) {
                Log::info('Using Python from .env: ' . $envPython);
                return $envPython;
            }
        }
        
        // Try python3 first
        $output = shell_exec('python3 --version 2>&1');
        if ($output && strpos($output, 'Python') !== false) {
            Log::info('Using python3 command');
            return 'python3';
        }

        // Try python
        $output = shell_exec('python --version 2>&1');
        if ($output && strpos($output, 'Python') !== false) {
            Log::info('Using python command');
            return 'python';
        }
        
        // Try py (Windows Python Launcher)
        $output = shell_exec('py --version 2>&1');
        if ($output && strpos($output, 'Python') !== false) {
            Log::info('Using py command (Windows launcher)');
            return 'py';
        }

        Log::error('No Python command found. Tried: python3, python, py');
        return null;
    }

    /**
     * Detect crisis keywords in text with enhanced accuracy
     * Now includes sarcasm, satire, hyperbole, and confidence scoring
     *
     * @param string $text
     * @return array
     */
    public function detectCrisis(string $text): array
    {
        $text = strtolower($text);
        
        // ===== CRITICAL FIRST STEP: Sarcasm/Satire/Dark Humor Detection =====
        // Check for sarcasm BEFORE crisis assessment to avoid false positives
        $sarcasmAnalysis = $this->analyzeSarcasmAndSatire($text);
        if ($sarcasmAnalysis['is_sarcasm'] || $sarcasmAnalysis['is_satire']) {
            Log::info("Sarcasm/satire detected, returning low crisis level", [
                'sarcasm_score' => $sarcasmAnalysis['sarcasm_score'],
                'satire_score' => $sarcasmAnalysis['satire_score']
            ]);
            return [
                'level' => 'none',
                'keywords' => [],
                'needs_counselor' => false,
                'confidence' => $sarcasmAnalysis['confidence'],
                'reason' => 'Sarcasm/satire detected - not actual crisis',
                'sarcasm_detected' => true,
                'context_analysis' => ['sarcasm', 'satire'],
            ];
        }
        
        // ===== STEP 1: Check for Negation with Proximity Analysis =====
        // Detect if user is negating crisis statements (proximity-aware)
        $negationMarkers = ['not', 'no', 'don\'t', 'dont', 'won\'t', 'wont', 'isn\'t', 'isnt', 'can\'t', 'cant', 'never', 'wouldn\'t', 'wouldnt'];
        $hasNegation = false;
        
        foreach ($negationMarkers as $marker) {
            if (preg_match('/\b' . preg_quote($marker) . '\b/i', $text)) {
                $hasNegation = true;
                break;
            }
        }
        
        // ===== STEP 2: Check for Temporal Markers =====
        // Detect if crisis statement is about past, not present
        $pastMarkers = ['used to', 'used_to', 'i used', 'used', 'before', 'past', 'yesterday', 'last week', 'last month', 'i did', 'i had', 'had suicidal'];
        $presentMarkers = ['right now', 'now', 'today', 'at this moment', 'currently', 'i am', 'i want to'];
        
        $hasPastTense = false;
        $hasPresentTense = false;
        
        foreach ($pastMarkers as $marker) {
            if (strpos($text, $marker) !== false) {
                $hasPastTense = true;
                break;
            }
        }
        
        foreach ($presentMarkers as $marker) {
            if (strpos($text, $marker) !== false) {
                $hasPresentTense = true;
                break;
            }
        }
        
        // ===== STEP 3: Pet/Object Loss Filter =====
        // IMPORTANT: Exclude pet/object loss from crisis detection
        $nonCrisisKeywords = [
            // Pets
            'dog', 'cat', 'pet', 'puppy', 'kitten', 'aso', 'pusa', 'alaga', 'hayop',
            // Plants/Objects
            'flower', 'plant', 'tree', 'rose', 'daisy', 'lily', 'orchid', 'cactus',
            'bulaklak', 'puno', 'halaman', 'sapot', 
            // Objects
            'phone', 'laptop', 'car', 'bike', 'item', 'stuff', 'thing'
        ];
        
        $hasNonCrisisKeyword = false;
        foreach ($nonCrisisKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $hasNonCrisisKeyword = true;
                break;
            }
        }
        
        // If it's pet/plant/object loss, return none - this is grief, not crisis
        if ($hasNonCrisisKeyword) {
            return [
                'level' => 'none',
                'keywords' => [],
                'needs_counselor' => false,
                'reason' => 'Pet/plant/object loss detected - grief, not crisis'
            ];
        }
        
        $crisisKeywords = [
            'critical' => [
                'suicide', 'kill myself', 'end my life', 'want to die', 'better off dead',
                'patayin ang sarili', 'mamatay na', 'ayaw ko na mabuhay', 'gusto ko nang mamatay',
                'papatayin ko sarili ko', 'magpapakamatay', 'magsuicide'
            ],
            'severe' => [
                'self harm', 'hurt myself', 'cut myself', 'harm myself', 'no reason to live',
                'sakatan ang sarili', 'sugatan ang sarili', 'wala nang dahilan', 'walang silbi',
                'worthless', 'hopeless', 'give up', 'cant go on', 'sukuan na',
                // Death/Grief - ONLY for human death with clear context
                'parent died', 'mom died', 'dad died', 'mother died', 'father died',
                'sibling died', 'brother died', 'sister died', 'friend died', 'best friend died',
                'grandpa died', 'grandma died', 'grandfather died', 'grandmother died',
                'namatay ang nanay', 'namatay ang tatay', 'namatay ang kapatid', 
                'namatay ang friend', 'namatay ang lolo', 'namatay ang lola',
                'my person died', 'someone close died', 'lost someone', 'person i love died'
            ],
            'moderate' => [
                'depressed', 'very sad', 'extremely sad', 'cant take it', 'too much',
                'sobrang lungkot', 'hindi ko na kaya', 'pagod na pagod na', 'wala nang pag-asa'
            ]
        ];

        $detectedLevel = 'none';
        $matchedKeywords = [];
        $contextAnalysis = [];

        // ===== STEP 4: Keyword Matching =====
        // Check for critical keywords first
        foreach ($crisisKeywords['critical'] as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $detectedLevel = 'critical';
                $matchedKeywords[] = $keyword;
            }
        }

        // Check for severe if not critical
        if ($detectedLevel === 'none') {
            foreach ($crisisKeywords['severe'] as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $detectedLevel = 'severe';
                    $matchedKeywords[] = $keyword;
                }
            }
        }

        // Check for moderate if not severe or critical
        if ($detectedLevel === 'none') {
            foreach ($crisisKeywords['moderate'] as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $detectedLevel = 'moderate';
                    $matchedKeywords[] = $keyword;
                }
            }
        }
        
        // ===== STEP 5: Apply Negation Rule =====
        // If negation detected + crisis keyword, downgrade level significantly
        if (!empty($matchedKeywords) && $hasNegation) {
            $contextAnalysis[] = 'negation_detected';
            
            // "I'm NOT going to kill myself" - NOT a crisis
            if ($detectedLevel === 'critical') {
                $detectedLevel = 'none'; // Negated critical statement is not crisis
            } elseif ($detectedLevel === 'severe') {
                $detectedLevel = 'moderate'; // Negated severe is only moderate
            }
        }
        
        // ===== STEP 6: Apply Temporal Rule =====
        // If past tense + no present indicators, downgrade significantly
        if (!empty($matchedKeywords) && $hasPastTense && !$hasPresentTense) {
            $contextAnalysis[] = 'past_tense_detected';
            
            // "I used to think about ending it" - past issue, not current crisis
            if ($detectedLevel === 'critical') {
                $detectedLevel = 'moderate'; // Past suicide ideation isn't current crisis
            } elseif ($detectedLevel === 'severe') {
                $detectedLevel = 'none'; // Past self-harm isn't current crisis
            }
        }
        
        // ===== STEP 7: Context Window Analysis =====
        // Check words around crisis keyword for context
        if (!empty($matchedKeywords)) {
            $contextResult = $this->analyzeContextWindow($text, $matchedKeywords[0]);
            $contextAnalysis = array_merge($contextAnalysis, $contextResult['markers']);
            
            // Adjust level based on context window
            if ($contextResult['is_frustration_venting']) {
                // "I just want to die, this is so frustrating" - venting frustration, not suicide
                if ($detectedLevel === 'critical') {
                    $detectedLevel = 'moderate';
                }
                $contextAnalysis[] = 'venting_not_suicide';
            }
            
            if ($contextResult['is_hyperbole']) {
                // "I'm dying of boredom" - hyperbole, not actual crisis
                $detectedLevel = 'none';
                $contextAnalysis[] = 'hyperbole_not_crisis';
            }
        }

        return [
            'level' => $detectedLevel,
            'keywords' => $matchedKeywords,
            'needs_counselor' => in_array($detectedLevel, ['critical', 'severe']),
            'context_analysis' => $contextAnalysis,
        ];
    }
    
    /**
     * Analyze context window around crisis keyword
     * Check ±5 words around the keyword for context
     */
    private function analyzeContextWindow(string $text, string $keyword): array
    {
        $text = strtolower($text);
        $markers = [];
        $isHyperbole = false;
        $isFrustrationVenting = false;
        
        // Find keyword position
        $keywordPos = strpos($text, $keyword);
        if ($keywordPos === false) {
            return ['markers' => [], 'is_hyperbole' => false, 'is_frustration_venting' => false];
        }
        
        // Extract words around keyword (±5 words)
        $words = preg_split('/\s+/', $text);
        $keywordWordIndex = 0;
        $currentPos = 0;
        
        // Find keyword word index
        foreach ($words as $index => $word) {
            if (strpos($word, $keyword) !== false) {
                $keywordWordIndex = $index;
                break;
            }
        }
        
        // Get context window
        $contextStart = max(0, $keywordWordIndex - 5);
        $contextEnd = min(count($words) - 1, $keywordWordIndex + 5);
        $contextWords = array_slice($words, $contextStart, $contextEnd - $contextStart + 1);
        $contextText = strtolower(implode(' ', $contextWords));
        
        // Check for hyperbole indicators
        $hyperboleMarkers = ['dying of', 'killing me', 'so happy i could die', 'literally dying', 'dying to', 'just', 'about to'];
        foreach ($hyperboleMarkers as $marker) {
            if (strpos($contextText, $marker) !== false) {
                $isHyperbole = true;
                $markers[] = 'hyperbole_marker';
                break;
            }
        }
        
        // Check for frustration/venting indicators
        $frustrationMarkers = ['frustrated', 'frustrated!', 'angry', 'angry!', 'annoyed', 'so annoying', 'hate this', 'ugh', 'hate it', 'this sucks'];
        foreach ($frustrationMarkers as $marker) {
            if (strpos($contextText, $marker) !== false) {
                $isFrustrationVenting = true;
                $markers[] = 'frustration_marker';
                break;
            }
        }
        
        // Check for schoolwork/task context (not actual crisis)
        $taskMarkers = ['homework', 'assignment', 'project', 'exam', 'test', 'work', 'job'];
        foreach ($taskMarkers as $marker) {
            if (strpos($contextText, $marker) !== false) {
                $isFrustrationVenting = true;
                $markers[] = 'task_context';
                break;
            }
        }
        
        return [
            'markers' => $markers,
            'is_hyperbole' => $isHyperbole,
            'is_frustration_venting' => $isFrustrationVenting,
        ];
    }

    /**
     * Analyze text deeply and provide context
     *
     * @param string $text
     * @param string $emotion
     * @return array
     */
    public function analyzeContext(string $text, string $emotion): array
    {
        $text = strtolower($text);
        $context = [];

        // Academic stress indicators
        $academicKeywords = ['exam', 'test', 'grade', 'study', 'school', 'class', 'homework', 'assignment', 'thesis', 'project'];
        foreach ($academicKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $context[] = 'academic_stress';
                break;
            }
        }

        // Relationship issues
        $relationshipKeywords = ['relationship', 'boyfriend', 'girlfriend', 'friend', 'family', 'parents', 'breakup', 'fight', 'argue'];
        foreach ($relationshipKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $context[] = 'relationship_issues';
                break;
            }
        }

        // Financial concerns
        $financialKeywords = ['money', 'financial', 'broke', 'afford', 'expensive', 'budget', 'pera', 'gastos'];
        foreach ($financialKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $context[] = 'financial_concerns';
                break;
            }
        }

        // Health concerns
        $healthKeywords = ['sick', 'health', 'pain', 'tired', 'exhausted', 'sleep', 'insomnia', 'sakit', 'pagod'];
        foreach ($healthKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $context[] = 'health_concerns';
                break;
            }
        }

        // Loneliness/isolation
        $lonelinessKeywords = ['lonely', 'alone', 'isolated', 'no friends', 'nag-iisa', 'walang kaibigan'];
        foreach ($lonelinessKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $context[] = 'loneliness';
                break;
            }
        }

        return [
            'contexts' => $context,
            'has_context' => !empty($context)
        ];
    }

    /**
     * Generate AI response based on emotion with crisis detection and remedies
     *
     * @param string $text
     * @param string $emotion
     * @param float $confidence
     * @return string
     */
    public function generateResponse(string $text, string $emotion, float $confidence): string
    {
        return $this->generateResponseWithContext($text, $emotion, $confidence, []);
    }
    
    /**
     * Generate response with conversation history context
     * MODIFIED: More nuanced approach based on situation severity
     */
    public function generateResponseWithContext(string $text, string $emotion, float $confidence, array $conversationHistory = []): string
    {
        // Detect crisis situation
        $crisis = $this->detectCrisis($text);

        // Analyze context
        $context = $this->analyzeContext($text, $emotion);

        // CRITICAL: Suicidal thoughts detected - IMMEDIATE counselor intervention
        if ($crisis['level'] === 'critical') {
            return $this->generateCriticalResponse($context);
        }

        // SEVERE: Self-harm or severe distress - Strong counselor suggestion
        if ($crisis['level'] === 'severe') {
            return $this->generateSevereResponse($context);
        }

        // MODERATE/NONE: Companion mode - Focus on comfort, tips, advice
        // Avoid pushing counselor unless situation escalates
        return $this->generateCompanionResponse($text, $emotion, $confidence, $context, $conversationHistory, $crisis['level']);
    }

    /**
     * Generate critical crisis response
     */
    private function generateCriticalResponse(array $context): string
    {
        $openings = [
            "🚨 <strong>Hey, stop for a second. I need you to hear me.</strong><br><br>What you just told me... I'm really, really worried about you right now. Like, seriously worried.",
            "🚨 <strong>Okay, I need you to listen to me right now.</strong><br><br>What you're feeling... I can tell you're in a really dark place. And I'm not going to pretend that's okay.",
            "🚨 <strong>Hey. Look at me (well, you know what I mean).</strong><br><br>I'm really scared for you right now based on what you just shared. This is serious."
        ];
        
        $response = $openings[array_rand($openings)] . "<br><br>";
        
        $response .= "I know everything feels overwhelming and maybe even hopeless right now. But here's the truth: <strong>your life matters.</strong> You matter to me, and you matter to so many people - even if it doesn't feel that way right now.<br><br>";
        
        $response .= "I need you to do something for me, okay? Right now:<br><br>";
        
        $response .= "1. <strong>Can you talk to a counselor RIGHT NOW?</strong> Click 'Chat with Counselor' - they're trained for this exact situation<br>";
        $response .= "2. <strong>Or call this number:</strong> 1553 or 0917-899-USAP (8727) - Someone will answer 24/7, I promise<br>";
        $response .= "3. <strong>Is there someone near you?</strong> A roommate, friend, family member? Please don't be alone right now<br><br>";
        
        $questions = [
            "Can you tell me what's making you feel this way? I'm here, and I'm listening.",
            "What happened that brought you to this point? Talk to me.",
            "I want to understand what you're going through. Will you tell me more?"
        ];
        
        $response .= "💜 " . $questions[array_rand($questions)] . "<br><br>";
        
        $response .= "You're not alone in this. I know it feels like you are, but you're not. There are people who want to help - who NEED to help. Please let them.<br><br>";
        
        $response .= "⚠️ <em>I've flagged this for a counselor to reach out to you as soon as possible. Please, please talk to someone.</em>";
        
        return $response;
    }

    /**
     * Generate severe crisis response
     */
    private function generateSevereResponse(array $context): string
    {
        $openings = [
            "⚠️ <strong>Okay, I need to be real with you right now.</strong><br><br>What you just shared... that's concerning. Like, really concerning. I'm worried about you.",
            "⚠️ <strong>Hey, I'm hearing some really heavy stuff from you.</strong><br><br>I can tell you're in a lot of pain right now, and I don't want to minimize that.",
            "⚠️ <strong>I need you to know I'm taking what you said seriously.</strong><br><br>You're clearly going through something really difficult, and I'm concerned."
        ];
        
        $response = $openings[array_rand($openings)] . " 💙<br><br>";
        
        if (in_array('loneliness', $context['contexts'])) {
            $response .= "I can sense you're feeling really isolated right now. That feeling of being alone with your pain... it's heavy. It's so, so heavy. And you don't deserve to feel this way.<br><br>";
        }
        
        $questions = [
            "Can we talk about this? What's been happening that's brought you to this point?",
            "I want to understand what you're going through. What happened?",
            "Talk to me. What's going on that's making you feel this way?"
        ];
        
        $response .= "<strong>" . $questions[array_rand($questions)] . "</strong><br><br>";
        
        $followups = [
            "• Have you felt this way before, or is this new?<br>• Is there something specific that triggered these feelings?<br>• When did this start getting really bad?",
            "• What's the worst part of what you're feeling right now?<br>• Is there anyone in your life who knows you're feeling this way?<br>• What would help, even just a little bit?",
            "• How long have you been carrying this?<br>• Did something happen recently that made it worse?<br>• Have you told anyone else about this?"
        ];
        
        $response .= $followups[array_rand($followups)] . "<br><br>";
        
        $counselorPush = [
            "Look, I really think you need to talk to a counselor about this. Like, not eventually - now. They're trained to help with exactly what you're feeling, and they can do more than I can.",
            "I'm going to be straight with you: you need professional support right now. A counselor can help you work through this in ways I can't. Would you be willing to talk to one?",
            "Here's the thing - I care about you, but I'm not equipped to help you with this alone. A counselor is. They deal with these feelings all the time and they know how to help. Can you reach out to them?"
        ];
        
        $response .= $counselorPush[array_rand($counselorPush)] . "<br><br>";
        
        $response .= "📞 <strong>You can reach them:</strong> Click 'Chat with Counselor' or call 1553 (it's free and confidential)<br><br>";
        
        $response .= "💜 <em>I'm here with you. You don't have to go through this alone. Please, let someone help you.</em>";
        
        return $response;
    }

    /**
     * Generate moderate distress response with intelligent analysis
     */
    private function generateModerateResponse(string $emotion, array $context): string
    {
        $response = "💜 <strong>I can tell you're going through something difficult right now.</strong><br><br>";
        
        // Provide context-specific understanding and support
        if (in_array('academic_stress', $context['contexts'])) {
            $response .= "Academic pressure can feel overwhelming - like everything's piling up and you can't catch your breath. ";
            $response .= "What's weighing on you most right now?<br><br>";
            $response .= "Remember: you don't have to tackle everything at once. What's the one thing that needs your attention first? ";
            $response .= "Sometimes breaking it down makes it feel more manageable.<br><br>";
        }
        
        if (in_array('relationship_issues', $context['contexts'])) {
            $response .= "Relationship struggles affect us deeply - they can impact our mood, our focus, everything. ";
            $response .= "What happened? I'm here to listen without judgment. 💙<br><br>";
            $response .= "Your feelings about this - whatever they are - they're valid. You're allowed to feel hurt, angry, confused, or all of it at once.<br><br>";
        }
        
        if (in_array('financial_concerns', $context['contexts'])) {
            $response .= "Financial stress is exhausting because it's constant - always there in the background. ";
            $response .= "What's your biggest concern right now?<br><br>";
            $response .= "Have you explored resources like the Financial Aid Office? Sometimes there are options we don't know about. ";
            $response .= "It's worth asking.<br><br>";
        }
        
        if (in_array('health_concerns', $context['contexts'])) {
            $response .= "When your body isn't feeling right, everything becomes harder. Your health matters. ";
            $response .= "What's been going on?<br><br>";
            $response .= "Have you been able to see a doctor or the campus health center? Sometimes we try to push through, ";
            $response .= "but you deserve to feel better.<br><br>";
        }
        
        if (in_array('loneliness', $context['contexts'])) {
            $response .= "Feeling disconnected and alone is one of the hardest experiences. You can be surrounded by people ";
            $response .= "and still feel completely isolated. I'm here with you right now. 💜<br><br>";
            $response .= "What kind of connection are you missing? Is it about meeting new people, or feeling understood by the people already in your life?<br><br>";
        }
        
        // If no specific context
        if (empty($context['contexts'])) {
            $response .= "I can sense you're carrying something heavy. What's on your mind?<br><br>";
        }
        
        // Suggest counselor support
        $response .= "💬 <strong>I think talking to a counselor could really help you work through this.</strong> ";
        $response .= "They're trained for exactly these situations and have tools and strategies that can make a real difference. ";
        $response .= "Would you be open to connecting with one?<br><br>";
        
        $response .= "In the meantime, I'm here. What do you need right now?";
        
        return $response;
    }

    /**
     * Generate companion response - Focus on comfort, tips, and advice
     * AVOID pushing counselor unless situation becomes severe
     */
    private function generateCompanionResponse(string $text, string $emotion, float $confidence, array $context, array $conversationHistory, string $crisisLevel): string
    {
        // For moderate distress, acknowledge but don't push counselor immediately
        if ($crisisLevel === 'moderate') {
            return $this->generateModerateCompanionResponse($emotion, $context);
        }

        // For normal situations, use full companion mode
        return $this->generateRegularCompanionResponse($text, $emotion, $confidence, $context, $conversationHistory);
    }

    /**
     * Generate moderate distress companion response
     * Acknowledge difficulty but focus on support and self-help strategies
     */
    private function generateModerateCompanionResponse(string $emotion, array $context): string
    {
        $response = "💜 <strong>I can tell you're going through something really challenging right now.</strong><br><br>";

        // Provide context-specific comfort and practical advice
        if (in_array('academic_stress', $context['contexts'])) {
            $response .= "Academic pressure can feel overwhelming - like everything's piling up and you can't catch your breath. ";
            $response .= "You're not alone in feeling this way. Many students go through similar struggles.<br><br>";
            $response .= "💡 <strong>Some things that might help:</strong><br>";
            $response .= "• Break your work into smaller, manageable chunks<br>";
            $response .= "• Take short breaks using the Pomodoro technique (25 min work, 5 min break)<br>";
            $response .= "• Talk to your professors about extensions if you're really struggling<br>";
            $response .= "• Remember that one bad grade doesn't define your worth or intelligence<br><br>";
            $response .= "What's one small step you could take today to make this feel more manageable?";
        }

        elseif (in_array('relationship_issues', $context['contexts'])) {
            $response .= "Relationship struggles affect us deeply - they can impact our mood, our focus, everything. ";
            $response .= "It's okay to feel hurt, angry, confused, or all of it at once. Your feelings are valid.<br><br>";
            $response .= "💡 <strong>When relationships are tough, it helps to:</strong><br>";
            $response .= "• Give yourself time to process your emotions<br>";
            $response .= "• Talk to trusted friends who can listen without judgment<br>";
            $response .= "• Focus on self-care activities that bring you comfort<br>";
            $response .= "• Remember that you deserve relationships that make you feel valued<br><br>";
            $response .= "What would feel most supportive for you right now?";
        }

        elseif (in_array('financial_concerns', $context['contexts'])) {
            $response .= "Financial stress is exhausting because it's constant - always there in the background. ";
            $response .= "You're not alone in this worry.<br><br>";
            $response .= "💡 <strong>Practical steps you might consider:</strong><br>";
            $response .= "• Check if your school has emergency financial aid or food assistance<br>";
            $response .= "• Look into work-study programs or part-time jobs on campus<br>";
            $response .= "• Create a basic budget to see where your money is going<br>";
            $response .= "• Reach out to financial aid office - they might have resources you don't know about<br><br>";
            $response .= "What's your biggest financial concern right now?";
        }

        elseif (in_array('health_concerns', $context['contexts'])) {
            $response .= "When your body isn't feeling right, everything becomes harder. Your health matters so much. ";
            $response .= "It's important to be gentle with yourself when you're not feeling well.<br><br>";
            $response .= "💡 <strong>Self-care strategies that might help:</strong><br>";
            $response .= "• Rest when you need to - your body is telling you something<br>";
            $response .= "• Stay hydrated and eat nourishing foods when you can<br>";
            $response .= "• Reach out to campus health services for support<br>";
            $response .= "• Talk to friends or family about how you're feeling<br><br>";
            $response .= "How are you taking care of yourself right now?";
        }

        elseif (in_array('loneliness', $context['contexts'])) {
            $response .= "Feeling disconnected and alone is one of the hardest experiences. You can be surrounded by people ";
            $response .= "and still feel completely isolated. I'm here with you right now. 💜<br><br>";
            $response .= "💡 <strong>Some ways to build connection:</strong><br>";
            $response .= "• Join a club or study group that interests you<br>";
            $response .= "• Reach out to one person you trust - even a simple 'hey, how are you?' can help<br>";
            $response .= "• Consider campus events or activities that sound fun<br>";
            $response .= "• Remember that many people feel this way sometimes<br><br>";
            $response .= "What kind of connection are you missing most right now?";
        }

        else {
            // No specific context - general support
            $response .= "I can sense you're carrying something heavy right now. Whatever you're going through, ";
            $response .= "it's okay to feel overwhelmed by it.<br><br>";
            $response .= "💡 <strong>Remember:</strong><br>";
            $response .= "• Your feelings are valid and important<br>";
            $response .= "• It's okay to not be okay sometimes<br>";
            $response .= "• Small steps can lead to big changes<br>";
            $response .= "• You're stronger than you might feel right now<br><br>";
            $response .= "What's on your mind? I'm here to listen.";
        }

        // For moderate distress, suggest counselor support gently (not pushy)
        // Only if they've been showing signs of struggle for a while
        if (rand(1, 3) === 1) { // Only suggest counselor 1/3 of the time for moderate distress
            $response .= "<br><br>💬 <em>If this continues to feel really heavy, talking to a counselor can provide additional support. ";
            $response .= "They're there when you need them.</em>";
        }

        return $response;
    }

    /**
     * Generate regular companion response - Full comfort and advice mode
     */
    private function generateRegularCompanionResponse(string $text, string $emotion, float $confidence, array $context, array $conversationHistory): string
    {
        // Try conversational AI first
        $conversationalService = new ConversationalAIService();
        $conversationalResponse = $conversationalService->generateConversationalResponse(
            $text,
            $emotion,
            $conversationHistory,
            $context
        );

        if ($conversationalResponse) {
            \Log::info('Using conversational AI companion response');
            return $conversationalResponse;
        }

        // Try ML response
        $mlResponse = $this->generateMLResponse($text, $emotion, $context, $conversationHistory);
        if ($mlResponse) {
            \Log::info('Using ML companion response');
            return $mlResponse . "<br><br>";
        }

        // Fallback: Rule-based companion response
        \Log::info('Using rule-based companion response');

        $response = "";

        // Start with empathy based on emotion
        switch ($emotion) {
            case 'sad':
                $response .= "💙 <strong>I can sense you're feeling down right now.</strong> That's completely understandable. ";
                $response .= "Sometimes life brings us moments that make us feel heavy-hearted.<br><br>";
                break;
            case 'anxious':
                $response .= "😌 <strong>I hear that you're feeling anxious.</strong> That racing feeling in your chest, ";
                $response .= "the worry that won't quiet down... it's really uncomfortable.<br><br>";
                break;
            case 'angry':
                $response .= "💚 <strong>Feeling angry is valid too.</strong> Sometimes we need to feel that fire ";
                $response .= "when something feels unfair or hurtful.<br><br>";
                break;
            case 'happy':
                $response .= "😊 <strong>I'm so glad to hear you're feeling positive!</strong> ";
                $response .= "Those good moments are worth savoring.<br><br>";
                break;
            default:
                $response .= "💜 <strong>I'm here with you.</strong> Whatever you're feeling right now, ";
                $response .= "it's okay to feel it.<br><br>";
        }

        // Add context-specific comfort and tips
        if ($context['has_context']) {
            $response .= $this->generateContextualComfort($context['contexts'], $emotion);
        }

        // Add practical advice based on emotion
        $response .= $this->generatePracticalAdvice($emotion, $context);

        // End with gentle, open question
        $response .= $this->generateCompanionQuestion($emotion);

        return $response;
    }

    /**
     * Generate contextual comfort based on detected contexts
     */
    private function generateContextualComfort(array $contexts, string $emotion): string
    {
        $comfort = "";

        if (in_array('academic_stress', $contexts)) {
            $comfort .= "📚 <strong>Academic stress is so common among students.</strong> ";
            $comfort .= "You're pushing yourself hard, and that's commendable, but remember to be kind to yourself too. ";
            $comfort .= "Your worth isn't measured by your grades.<br><br>";
        }

        if (in_array('relationship_issues', $contexts)) {
            $comfort .= "💕 <strong>Relationships can be complicated and painful sometimes.</strong> ";
            $comfort .= "It's okay to feel hurt when someone you care about lets you down. ";
            $comfort .= "Your heart is healing, even when it doesn't feel like it.<br><br>";
        }

        if (in_array('loneliness', $contexts)) {
            $comfort .= "🤗 <strong>Feeling lonely doesn't mean you're alone.</strong> ";
            $comfort .= "Many people feel this way, even when they're surrounded by others. ";
            $comfort .= "Your desire for connection is a beautiful part of being human.<br><br>";
        }

        if (in_array('future_anxiety', $contexts)) {
            $comfort .= "🔮 <strong>Worrying about the future is something most people do.</strong> ";
            $comfort .= "It's your mind trying to keep you safe, but sometimes it goes into overdrive. ";
            $comfort .= "You're capable of handling whatever comes your way.<br><br>";
        }

        return $comfort;
    }

    /**
     * Generate practical, actionable advice
     */
    private function generatePracticalAdvice(string $emotion, array $context): string
    {
        $advice = "💡 <strong>Some gentle suggestions that might help:</strong><br><br>";

        switch ($emotion) {
            case 'sad':
                $advice .= "• Try a small act of self-kindness - maybe a warm drink or a favorite song<br>";
                $advice .= "• Reach out to someone you trust, even just to say 'I'm having a rough day'<br>";
                $advice .= "• Do something comforting and familiar - a walk, reading, or calling a friend<br>";
                $advice .= "• Remember that sad feelings don't last forever, even when they feel endless<br><br>";
                break;

            case 'anxious':
                $advice .= "• Try the 5-4-3-2-1 grounding technique: 5 things you can see, 4 you can touch, 3 you can hear, 2 you can smell, 1 you can taste<br>";
                $advice .= "• Take slow, deep breaths - in for 4 counts, hold for 4, out for 4<br>";
                $advice .= "• Write down your worries - sometimes seeing them on paper makes them feel more manageable<br>";
                $advice .= "• Do something with your hands - coloring, cooking, or a simple craft<br><br>";
                break;

            case 'angry':
                $advice .= "• Take some space if you need it - it's okay to step away until you feel calmer<br>";
                $advice .= "• Physical activity can help process anger - a walk, punching a pillow, or dancing<br>";
                $advice .= "• Write down what's making you angry - sometimes naming it helps<br>";
                $advice .= "• Remember that anger is often covering up other feelings like hurt or fear<br><br>";
                break;

            case 'happy':
                $advice .= "• Savor this good feeling - maybe write down what made you happy<br>";
                $advice .= "• Share your joy with someone - happiness grows when it's shared<br>";
                $advice .= "• Take a moment to appreciate the good things in your life<br><br>";
                break;

            default:
                $advice .= "• Practice self-compassion - talk to yourself like you would a good friend<br>";
                $advice .= "• Do one small thing that brings you comfort<br>";
                $advice .= "• Remember that all feelings are temporary visitors<br>";
                $advice .= "• You're doing your best, and that's enough<br><br>";
        }

        return $advice;
    }

    /**
     * Generate gentle, companion-style questions
     */
    private function generateCompanionQuestion(string $emotion): string
    {
        $questions = [];

        switch ($emotion) {
            case 'sad':
                $questions = [
                    "What would feel most comforting for you right now?",
                    "Is there something specific that's been weighing on you?",
                    "What usually helps lift your spirits, even a little?",
                    "Would you like to talk about what's been difficult lately?"
                ];
                break;

            case 'anxious':
                $questions = [
                    "What's been causing the most worry for you?",
                    "What usually helps you feel more grounded?",
                    "Is there something specific you're anxious about?",
                    "What would help you feel a bit more at ease?"
                ];
                break;

            case 'angry':
                $questions = [
                    "What happened that sparked these feelings?",
                    "What do you need right now to feel more in control?",
                    "Is there someone you can talk to about this?",
                    "What would help you process these feelings?"
                ];
                break;

            default:
                $questions = [
                    "What's been on your mind lately?",
                    "What are you needing most right now?",
                    "Is there something you'd like to share?",
                    "What would feel supportive for you?"
                ];
        }

        return "🤗 " . $questions[array_rand($questions)];
    }

    /**
     * Generate regular emotional support response
     */
    private function generateRegularResponse(string $text, string $emotion, float $confidence, array $context): string
    {
        return $this->generateRegularResponseWithHistory($text, $emotion, $confidence, $context, []);
    }
    
    /**
     * Generate response with conversation history for continuity
     * Uses intelligent analysis instead of random templates
     */
    private function generateRegularResponseWithHistory(string $userText, string $emotion, float $confidence, array $context, array $conversationHistory): string
    {
        // PRIORITY 1: Use new conversational AI service for natural, empathetic dialogue
        $conversationalService = new ConversationalAIService();
        $conversationalResponse = $conversationalService->generateConversationalResponse(
            $userText,
            $emotion,
            $conversationHistory,
            $context
        );
        
        if ($conversationalResponse) {
            \Log::info('Using conversational AI response');
            return $conversationalResponse;
        }
        
        // PRIORITY 2: TRY ML-GENERATED RESPONSE (This handles everything!)
        $mlResponse = $this->generateMLResponse($userText, $emotion, $context, $conversationHistory);
        if ($mlResponse) {
            \Log::info('Using ML-generated response');
            return $mlResponse . "<br><br>";
        }
        
        // PRIORITY 3: FALLBACK TO RULE-BASED RESPONSE (Only if ML unavailable)
        \Log::info('Using rule-based response (ML unavailable)');
        
        // Extract key content from user's message
        $keyContent = $this->extractKeyContent($userText);
        
        // Extract context from conversation history (what were they talking about?)
        $historyContext = $this->extractHistoryContext($conversationHistory);
        
        // Analyze the actual user input for intelligent response
        $analysis = $this->analyzeUserInput($emotion, $context, $conversationHistory);
        $analysis['key_content'] = $keyContent;
        $analysis['history_context'] = $historyContext;
        
        $response = "";
        
        // 1. SPECIFIC ACKNOWLEDGMENT - Respond to what they actually said
        if (!empty($keyContent)) {
            $response .= $this->generateSpecificAcknowledgment($keyContent, $emotion, $historyContext, $userText);
            
            // If we got a complete response (crisis, question with advice, etc.), return it
            // Don't add more questions that would be repetitive
            if (isset($keyContent['crisis_escalation']) || 
                (isset($keyContent['is_question']) && strlen($response) > 200)) {
                return $response;
            }
            
            // For greetings and simple sharing, the acknowledgment already includes a natural question
            // Don't add another therapeutic question - it would be redundant
            if (isset($keyContent['is_greeting']) || isset($keyContent['is_simple_sharing'])) {
                return $response;
            }
        }
        
        // 2. CONVERSATION CONTINUITY - Show we remember (only if no specific event)
        if (empty($keyContent) && !empty($conversationHistory)) {
            // Reference what they were talking about
            if (!empty($historyContext['loss_type'])) {
                $lossSubject = $historyContext['loss_subject'] ?? 'loss';
                $response .= "I know you're still dealing with losing your {$lossSubject}. ";
            } elseif (!empty($historyContext['event']) && $historyContext['event'] === 'breakup') {
                $response .= "I know you're still processing that breakup. ";
            }
            
            $lastEntry = end($conversationHistory);
            $lastEmotion = $lastEntry['emotion'] ?? 'neutral';
            
            // Only mention emotion change if it's significant
            if ($lastEmotion !== $emotion && !in_array($emotion, ['neutral'])) {
                if ($lastEmotion === 'sad' && $emotion === 'happy') {
                    $response .= "I'm really glad to see you're feeling better! 😊 ";
                } elseif (in_array($lastEmotion, ['happy', 'neutral']) && $emotion === 'sad') {
                    $response .= "I can sense something shifted. ";
                } elseif ($emotion === 'anxious') {
                    $response .= "I'm picking up some worry in what you're saying. ";
                }
            }
        }
        
        // 3. EMPATHETIC ACKNOWLEDGMENT - Based on actual emotion (if no specific event)
        if (empty($keyContent)) {
            $response .= $this->generateEmpatheticAcknowledgment($emotion, $analysis);
        }
        
        // 4. CONTEXT-SPECIFIC UNDERSTANDING - Show we understand the situation
        if ($context['has_context'] && empty($keyContent['loss_type'])) {
            $response .= $this->generateContextualUnderstanding($context['contexts'], $emotion);
        }
        
        // 5. THERAPEUTIC QUESTION - Ask something meaningful
        $response .= $this->generateTherapeuticQuestion($emotion, $context, $analysis);
        
        // 6. SUPPORTIVE GUIDANCE - Offer practical help (only after 3+ exchanges)
        $exchangeCount = count($conversationHistory);
        if ($exchangeCount >= 3) {
            $response .= $this->generateSupportiveGuidance($emotion, $context, $analysis);
        }
        
        // 7. AVOID REPETITION - Check if response is too similar to recent ones
        $response = $this->avoidRepetitiveResponse($response, $conversationHistory);
        
        return $response;
    }
    
    /**
     * Generate ML-based conversational response using DialoGPT
     */
    private function generateMLResponse(string $userText, string $emotion, array $context, array $conversationHistory): ?string
    {
        try {
            // Check if ML server is running
            if (!$this->isMLServerRunning()) {
                Log::info('ML server not running, skipping ML response generation');
                return null;
            }
            
            // Prepare request data
            $requestData = [
                'text' => $userText,
                'emotion' => $emotion,
                'contexts' => $context['contexts'] ?? [],
                'crisis_level' => $context['crisis_level'] ?? 'none',
                'conversation_history' => array_slice($conversationHistory, -3) // Last 3 exchanges
            ];
            
            // Call ML server API
            $ch = curl_init($this->mlApiUrl('generate_response'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                Log::warning('ML server returned error: ' . $httpCode);
                return null;
            }
            
            $result = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON from ML server');
                return null;
            }
            
            if (!isset($result['success']) || !$result['success'] || empty($result['response'])) {
                Log::warning('ML response generation failed');
                return null;
            }
            
            $mlResponse = $result['response'];
            
            // Validate response quality
            if (strlen($mlResponse) < 10) {
                Log::warning('ML response too short');
                return null;
            }
            
            Log::info('✅ ML response generated successfully via server');
            
            return $mlResponse;
            
        } catch (\Exception $e) {
            Log::error('ML response generation error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate specific acknowledgment based on what user actually said
     */
    private function generateSpecificAcknowledgment(array $keyContent, string $emotion, array $historyContext = [], string $userText = ''): string
    {
        $response = "";
        
        // Handle simple sharing intent - just listen, don't probe
        if (isset($keyContent['is_simple_sharing'])) {
            $responses = [
                "That's cool, no pressure! What's on your mind? 😊",
                "No worries! Just tell me whatever you want to share.",
                "All good! I'm here to listen whenever you're ready.",
                "No rush! Share whatever feels right. 💜",
                "That's totally fine! What do you want to talk about?"
            ];
            return $responses[array_rand($responses)] . "<br><br>";
        }
        
        // Handle greetings - keep it simple and natural
        if (isset($keyContent['is_greeting'])) {
            $greetings = [
                "Hey! 👋 What's up?",
                "Hi! 😊 How's it going?",
                "Hello! How's your day?",
                "Hey there! What's new?",
                "Hi! What's on your mind?",
                "Hey! How are you doing?"
            ];
            return $greetings[array_rand($greetings)];
        }
        
        // Handle complex questions about mental health
        if (isset($keyContent['is_question'])) {
            $complexAnswer = $this->answerComplexQuestion($userText, $emotion, $historyContext);
            if ($complexAnswer) {
                return $complexAnswer;
            }
        }
        
        // Handle CRISIS ESCALATION - User is in severe distress
        if (isset($keyContent['crisis_escalation'])) {
            $crisisType = $keyContent['crisis_type'] ?? 'severe_distress';
            
            // Reference what they were talking about if we know
            $contextReference = "";
            if (!empty($historyContext['loss_type']) && $historyContext['loss_type'] === 'pet') {
                $petName = $historyContext['loss_subject'] ?? 'pet';
                $contextReference = "I know losing your {$petName} has been devastating. ";
            } elseif (!empty($historyContext['loss_type']) && in_array($historyContext['loss_type'], ['parent', 'sibling'])) {
                $lossSubject = $historyContext['loss_subject'] ?? 'loved one';
                $contextReference = "I know losing your {$lossSubject} has been unbearable. ";
            } elseif (!empty($historyContext['event']) && $historyContext['event'] === 'breakup') {
                $contextReference = "I know this breakup has been crushing you. ";
            }
            
            // Check if they're asking "what's next" - they want to know what to do
            $isAskingWhatToDo = isset($keyContent['is_question']) && 
                                preg_match('/(whats next|what next|what do i do|what should i do|what can i do)/i', $userText);
            
            if ($crisisType === 'suicidal') {
                $response .= "🚨 <strong>PLEASE STOP AND READ THIS</strong> 🚨<br><br>";
                $response .= $contextReference;
                $response .= "What you're feeling right now is a crisis, and you need immediate help. This is beyond what I can provide.<br><br>";
                $response .= "<strong>RIGHT NOW:</strong><br>";
                $response .= "• Call the National Crisis Hotline: <strong>0917-899-8727 (USAP)</strong><br>";
                $response .= "• Or text USAP to 2919<br>";
                $response .= "• Or go to your nearest hospital emergency room<br><br>";
                $response .= "Please, please reach out to a counselor here or call that number. You don't have to face this alone. 💙<br><br>";
            } elseif ($crisisType === 'mental_breakdown') {
                $response .= "💜 I hear you. {$contextReference}The pain is so intense right now that it feels like you're losing control. That's a sign you need more support than I can give.<br><br>";
                $response .= "<strong>You need to talk to someone right now:</strong><br>";
                $response .= "• Reach out to a counselor here immediately<br>";
                $response .= "• Or call the Crisis Hotline: <strong>0917-899-8727 (USAP)</strong><br>";
                $response .= "• Or talk to someone you trust - family, friend, anyone<br><br>";
                $response .= "This level of pain needs professional support. Please don't try to handle this alone. 💙<br><br>";
            } else {
                // Severe distress - acknowledge the question if they asked "what's next"
                if ($isAskingWhatToDo) {
                    $response .= "💜 I hear you asking what to do when you can't take it anymore. {$contextReference}That feeling of being at your limit - like you've run out of strength - that's a sign you need help carrying this weight.<br><br>";
                } else {
                    $response .= "💜 I hear you saying you can't take it anymore. {$contextReference}That feeling of being overwhelmed - like everything is too much - that's real.<br><br>";
                }
                
                $response .= "<strong>Here's what you need to do next:</strong><br>";
                $response .= "1. <strong>Talk to a counselor</strong> - Click 'Chat with Counselor' or reach out to someone here. They're trained to help with exactly this.<br>";
                $response .= "2. <strong>Call the Crisis Hotline</strong> - 0917-899-8727 (USAP) - They're available 24/7 and can talk you through this.<br>";
                $response .= "3. <strong>Reach out to someone you trust</strong> - Family, friend, teacher - anyone who cares about you.<br>";
                $response .= "4. <strong>Stay safe</strong> - If you're thinking about hurting yourself, go to the nearest hospital or call emergency services.<br><br>";
                $response .= "You don't have to carry this alone. Please, let someone help you. Are you safe right now?<br><br>";
            }
            
            return $response;
        }
        
        // Handle questions - especially follow-up questions
        if (isset($keyContent['is_question']) && isset($keyContent['question_type']) && $keyContent['question_type'] === 'seeking_advice') {
            // User is asking for advice/suggestions
            
            // CHECK CONVERSATION HISTORY FOR CONTEXT
            $contextualAdvice = false;
            
            // If they were talking about pet loss
            if (!empty($historyContext['loss_type']) && $historyContext['loss_type'] === 'pet') {
                $contextualAdvice = true;
                $petName = $historyContext['loss_subject'] ?? 'pet';
                $response .= "I remember you were telling me about losing your {$petName}. That grief is so real. Here are some things that might help with pet loss:<br><br>";
                $response .= "• Allow yourself to grieve - your {$petName} was family, not 'just a pet'<br>";
                $response .= "• Keep photos or mementos that bring comfort<br>";
                $response .= "• Talk about your {$petName} with people who understand<br>";
                $response .= "• Consider writing a letter to your {$petName} expressing what you miss<br>";
                $response .= "• When ready, create a small memorial or plant something in their memory<br>";
                $response .= "• Join a pet loss support group (online or in-person)<br>";
                $response .= "• Be patient with yourself - there's no timeline for grief<br><br>";
                $response .= "Would any of these feel helpful to you right now?<br><br>";
            }
            // If they were talking about breakup
            elseif (!empty($historyContext['event']) && $historyContext['event'] === 'breakup') {
                $contextualAdvice = true;
                $response .= "I remember you mentioned going through a breakup. That heartbreak is real. Here's what might help:<br><br>";
                $response .= "• Let yourself feel the pain - don't rush the healing process<br>";
                $response .= "• Reach out to friends and family for support<br>";
                $response .= "• Avoid contact with your ex for a while (no social media stalking!)<br>";
                $response .= "• Do things you enjoy - rediscover yourself<br>";
                $response .= "• Journal your feelings to process them<br>";
                $response .= "• Focus on self-care - sleep, eat well, exercise<br>";
                $response .= "• Remember: healing isn't linear, bad days are normal<br><br>";
                $response .= "Which of these sounds most doable for you?<br><br>";
            }
            // If they were talking about academic failure
            elseif (!empty($historyContext['event']) && $historyContext['event'] === 'academic_failure') {
                $contextualAdvice = true;
                $response .= "I remember you were dealing with that academic setback. Here's what might help:<br><br>";
                $response .= "• Talk to your professor about what went wrong and how to improve<br>";
                $response .= "• Identify specific areas where you struggled<br>";
                $response .= "• Create a study plan with realistic goals<br>";
                $response .= "• Find a study group or tutor for support<br>";
                $response .= "• Take care of yourself - stress affects learning<br>";
                $response .= "• Remember: one failure doesn't define you<br><br>";
                $response .= "What feels like the most helpful next step for you?<br><br>";
            }
            // If they were talking about loss of a person
            elseif (!empty($historyContext['loss_type']) && in_array($historyContext['loss_type'], ['parent', 'sibling', 'friend', 'grandparent'])) {
                $contextualAdvice = true;
                $lossSubject = $historyContext['loss_subject'] ?? 'loved one';
                $response .= "I remember you were telling me about losing your {$lossSubject}. That grief is profound. Here's what might help:<br><br>";
                $response .= "• <strong>Please talk to a counselor</strong> - grief this deep needs professional support<br>";
                $response .= "• Allow yourself to feel whatever comes - anger, sadness, numbness, all of it<br>";
                $response .= "• Talk about your {$lossSubject} with people who knew them<br>";
                $response .= "• Keep routines when possible - they provide stability<br>";
                $response .= "• Be patient with yourself - grief has no timeline<br>";
                $response .= "• Consider joining a grief support group<br>";
                $response .= "• Take care of your basic needs even when you don't feel like it<br><br>";
                $response .= "Have you been able to talk to anyone about this?<br><br>";
            }
            
            // If no specific context found, provide general advice based on emotion
            if (!$contextualAdvice) {
                $response .= "I hear you asking for help with this. Let me share some things that might help:<br><br>";
                
                if ($emotion === 'sad') {
                    $response .= "When dealing with sadness:<br>";
                    $response .= "• Allow yourself to feel - it's okay to be sad<br>";
                    $response .= "• Talk about your feelings with someone you trust<br>";
                    $response .= "• Keep a journal to express your emotions<br>";
                    $response .= "• Maintain routines - they provide stability<br>";
                    $response .= "• Be gentle with yourself - healing takes time<br><br>";
                    $response .= "Would any of these feel helpful to you right now?<br><br>";
                } elseif ($emotion === 'anxious') {
                    $response .= "When feeling anxious or stressed:<br>";
                    $response .= "• Try deep breathing - breathe in for 4, hold for 4, out for 4<br>";
                    $response .= "• Break big problems into smaller, manageable steps<br>";
                    $response .= "• Write down your worries to get them out of your head<br>";
                    $response .= "• Do something physical - walk, stretch, exercise<br>";
                    $response .= "• Talk to someone about what's worrying you<br><br>";
                    $response .= "Which of these sounds most doable for you?<br><br>";
                } else {
                    $response .= "• Talk to someone you trust about how you're feeling<br>";
                    $response .= "• Do something that usually makes you feel better<br>";
                    $response .= "• Take care of your basic needs - sleep, eat, rest<br>";
                    $response .= "• Be patient with yourself<br><br>";
                    $response .= "What usually helps you feel even a little bit better?<br><br>";
                }
            }
            
            return $response;
        }
        
        // Handle loss/grief
        if (isset($keyContent['loss_type'])) {
            $subject = $keyContent['loss_subject'] ?? 'them';
            $timeframe = $keyContent['timeframe'] ?? '';
            
            switch ($keyContent['loss_type']) {
                case 'pet':
                    // More natural, empathetic responses for pet loss
                    $openings = [
                        "Oh no, I'm so sorry about your {$subject}. 💔 ",
                        "I'm really sorry you lost your {$subject}. 💔 ",
                        "My heart breaks for you - losing your {$subject}. 💔 "
                    ];
                    $response .= $openings[array_rand($openings)];
                    
                    if ($timeframe) {
                        $timeResponses = [
                            "Losing them {$timeframe}... that pain is so fresh and raw right now. ",
                            "And this just happened {$timeframe}... you're still in shock, aren't you? ",
                            "That was {$timeframe}... the grief must feel overwhelming. "
                        ];
                        $response .= $timeResponses[array_rand($timeResponses)];
                    }
                    
                    $validations = [
                        "Your {$subject} wasn't 'just a pet' - they were family. They were part of your daily life, your routine, your heart.",
                        "Pets aren't 'just animals' - they're family. They're there for us every single day, and when they're gone, that absence is huge.",
                        "The bond you had with your {$subject} was real. They gave you unconditional love, and losing that is a genuine loss."
                    ];
                    $response .= $validations[array_rand($validations)] . "<br><br>";
                    
                    $questions = [
                        "How long were you two together?",
                        "What was your {$subject}'s name? I'd love to hear about them if you want to share.",
                        "Do you want to tell me about them? Sometimes talking about the good memories helps.",
                        "What's the hardest part right now - is it the quiet, the routine being different, or just missing them?"
                    ];
                    $response .= $questions[array_rand($questions)] . "<br><br>";
                    
                    // Add gentle support offer
                    $supports = [
                        "I'm here if you need to talk. Grief isn't something you have to go through alone.",
                        "There's no timeline for this kind of grief. Take all the time you need.",
                        "If you need someone to listen - really listen - I'm here. You don't have to be strong right now."
                    ];
                    $response .= $supports[array_rand($supports)] . "<br><br>";
                    break;
                    
                case 'parent':
                    $response .= "I'm so, so sorry about your {$subject}. 💔 There are no words that can make this better. ";
                    if ($timeframe) {
                        $response .= "And this just happened {$timeframe}... you're in the middle of the most painful experience. ";
                    }
                    $response .= "<br><br>Losing a parent is one of the most profound losses anyone can experience. Your whole world just changed.<br><br>";
                    $response .= "⚠️ <strong>Please, please talk to a counselor.</strong> You need support right now - real, professional support. This is too much to carry alone.<br><br>";
                    $response .= "How are you doing right now, in this moment?<br><br>";
                    break;
                    
                case 'friend':
                    $response .= "I'm so sorry you lost your friend. 💔 ";
                    if ($timeframe) {
                        $response .= "And this happened {$timeframe}... the shock and pain must be overwhelming. ";
                    }
                    $response .= "Losing someone you care about is devastating.<br><br>";
                    $response .= "How are you coping with this?<br><br>";
                    break;
                    
                case 'grandparent':
                    $response .= "I'm so sorry about your {$subject}. 💔 ";
                    if ($timeframe) {
                        $response .= "Losing them {$timeframe}... that grief is so heavy right now. ";
                    }
                    $response .= "Grandparents hold such a special place in our hearts.<br><br>";
                    $response .= "What's the hardest part for you right now?<br><br>";
                    break;
                    
                case 'sibling':
                    $response .= "I'm so, so sorry about your {$subject}. 💔 There are no words for this kind of loss. ";
                    if ($timeframe) {
                        $response .= "And this happened {$timeframe}... you're in the middle of unimaginable pain. ";
                    }
                    $response .= "<br><br>Losing a sibling... that's losing part of yourself. Someone who knew you your whole life.<br><br>";
                    $response .= "⚠️ <strong>Please talk to a counselor.</strong> This is too much to carry alone. You need real support right now.<br><br>";
                    $response .= "How are you holding up?<br><br>";
                    break;
                    
                default:
                    $response .= "I'm so sorry for your loss. 💔 ";
                    if ($timeframe) {
                        $response .= "And this happened {$timeframe}... you're still in shock. ";
                    }
                    $response .= "Grief is one of the hardest things we go through.<br><br>";
                    $response .= "How are you holding up?<br><br>";
            }
            
            return $response;
        }
        
        // Handle breakup
        if (isset($keyContent['event']) && $keyContent['event'] === 'breakup') {
            $response .= "Ugh, breakups are brutal. 💔 I'm sorry you're going through this. ";
            if (isset($keyContent['timeframe'])) {
                $response .= "And it just happened {$keyContent['timeframe']}... the pain is so fresh. ";
            }
            $response .= "<br><br>Heartbreak is real pain. It's not 'just' emotional - it physically hurts.<br><br>";
            $response .= "What happened? Do you want to talk about it?<br><br>";
            return $response;
        }
        
        // Handle academic failure
        if (isset($keyContent['event']) && $keyContent['event'] === 'academic_failure') {
            $response .= "I'm sorry you're dealing with that academic setback. That disappointment is real. 😔<br><br>";
            $response .= "Failing something doesn't mean you're a failure. It means you hit a rough patch. What happened?<br><br>";
            return $response;
        }
        
        // Handle achievement
        if (isset($keyContent['event']) && $keyContent['event'] === 'achievement') {
            $response .= "That's amazing! 🎉 I'm so happy for you! All that hard work paid off!<br><br>";
            $response .= "How does it feel?<br><br>";
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Analyze user input for intelligent response generation
     */
    private function analyzeUserInput(string $emotion, array $context, array $conversationHistory): array
    {
        return [
            'is_first_message' => empty($conversationHistory),
            'emotion_intensity' => $this->assessEmotionIntensity($emotion, $context),
            'needs_validation' => in_array($emotion, ['sad', 'anxious', 'angry']),
            'needs_exploration' => !empty($context['contexts']),
            'conversation_depth' => count($conversationHistory)
        ];
    }
    
    /**
     * Extract context from conversation history - what were they talking about?
     */
    private function extractHistoryContext(array $conversationHistory): array
    {
        $historyContext = [];
        
        // Look through recent messages to find what they were discussing
        foreach ($conversationHistory as $entry) {
            if (empty($entry['user'])) continue;
            
            // Extract key content from each previous message
            $keyContent = $this->extractKeyContent($entry['user']);
            
            // If we found something significant, remember it
            if (!empty($keyContent['loss_type'])) {
                $historyContext['loss_type'] = $keyContent['loss_type'];
                $historyContext['loss_subject'] = $keyContent['loss_subject'] ?? '';
            }
            
            if (!empty($keyContent['event'])) {
                $historyContext['event'] = $keyContent['event'];
            }
        }
        
        return $historyContext;
    }
    
    /**
     * Extract key content from user's message to respond specifically
     */
    private function extractKeyContent(string $text): array
    {
        $text_lower = strtolower(trim($text));
        $key_info = [];
        
        // Greeting detection
        $greetings = ['hi', 'hello', 'hey', 'sup', 'yo', 'hola', 'kumusta', 'kamusta', 'musta'];
        if (in_array($text_lower, $greetings) || preg_match('/^(hi|hello|hey|sup|yo|hola|kumusta|kamusta|musta)\s*[!.]*$/i', $text_lower)) {
            $key_info['is_greeting'] = true;
            return $key_info;
        }
        
        // SIMPLE SHARING INTENT - User just wants to share, not asking for advice
        if (preg_match('/(just wanna share|just want to share|wanna share|want to share|nothing|just nothing|nothing much|nothing special|nothing really|walang special|walang malalim|i dont know|i don\'?t know|dont know|don\'?t know|idk|not sure|unsure)/i', $text) && 
            !preg_match('/\?$/', $text)) {
            $key_info['is_simple_sharing'] = true;
            return $key_info;
        }
        
        // CRISIS ESCALATION DETECTION - User is in severe distress
        // Match variations: "can't take", "cant take", "can not take", etc.
        if (preg_match('/(can\'?t take|cant take|cannot take|can not take|hindi ko na kaya|ayoko na|gusto ko na|want to die|kill myself|end it all|nababaliw|mababaliw|losing my mind|going crazy|can\'?t go on|cant go on|give up|wala na|tapos na|no more|not anymore|too much)/i', $text)) {
            $key_info['crisis_escalation'] = true;
            
            // Detect specific crisis type
            if (preg_match('/(want to die|kill myself|end it all|gusto ko na|ayoko na)/i', $text)) {
                $key_info['crisis_type'] = 'suicidal';
            } elseif (preg_match('/(nababaliw|mababaliw|losing my mind|going crazy)/i', $text)) {
                $key_info['crisis_type'] = 'mental_breakdown';
            } else {
                $key_info['crisis_type'] = 'severe_distress';
            }
        }
        
        // EMOTIONAL EXPRESSION - User is expressing feelings without specific event
        if (preg_match('/(feel|feeling|nararamdaman|pakiramdam)/i', $text) && 
            preg_match('/(lost|alone|empty|hopeless|worthless|numb|broken|mag-isa|walang kwenta|wala na)/i', $text)) {
            $key_info['emotional_state'] = true;
        }
        
        // QUESTION DETECTION - User is asking for help/advice
        if (preg_match('/\?$/', $text) || 
            preg_match('/(what|how|can you|do you|should i|could you|any|suggestions|advice|help|paano|ano|pwede|maaari|whats next|what next)/i', $text)) {
            $key_info['is_question'] = true;
            
            // Detect what kind of question
            if (preg_match('/(suggestions?|advice|help|recommend|what (can|should)|how (can|do)|paano|tulong|whats next|what next)/i', $text)) {
                $key_info['question_type'] = 'seeking_advice';
            } elseif (preg_match('/(do you|can you|will you|pwede|maaari)/i', $text)) {
                $key_info['question_type'] = 'asking_capability';
            }
        }
        
        // Enhanced Loss/Grief detection - multiple patterns
        // BUT EXCLUDE: "lost motivation", "lost interest", "lost hope", "lost confidence" (not actual death/loss)
        $nonLossTerms = ['motivation', 'interest', 'hope', 'confidence', 'focus', 'energy', 'drive', 'passion', 'gana', 'sigla', 'lakas'];
        
        // Pattern 1: "lost my dog", "namatay yung aso", "my dog die"
        if (preg_match('/(lost|losing|died|die|death|passed away|namatay|nawala|pumanaw)\s+(my|our|the|ang|yung)?\s*(\w+)/i', $text, $matches)) {
            $subject = $matches[3] ?? '';
            // Only process if it's not a non-loss term
            if (!in_array(strtolower($subject), $nonLossTerms)) {
                $key_info = array_merge($key_info, $this->identifyLossSubject($subject));
            }
        }
        
        // Pattern 2: "my dog died", "my mom passed away", "my dog die"
        if (empty($key_info['loss_type']) && preg_match('/(my|our|the|ang|yung)\s+(\w+)\s+(died|die|passed away|namatay|pumanaw|is dead|is gone)/i', $text, $matches)) {
            $subject = $matches[2] ?? '';
            if (!in_array(strtolower($subject), $nonLossTerms)) {
                $key_info = array_merge($key_info, $this->identifyLossSubject($subject));
            }
        }
        
        // Pattern 3: "have a dog and he died", "had a cat and she passed"
        if (empty($key_info['loss_type']) && preg_match('/(have|had|may)\s+(a|an)?\s*(\w+)\s+and\s+(he|she|it|they)\s+(died|die|passed away|passed|namatay|pumanaw)/i', $text, $matches)) {
            $subject = $matches[3] ?? '';
            if (!in_array(strtolower($subject), $nonLossTerms)) {
                $key_info = array_merge($key_info, $this->identifyLossSubject($subject));
            }
        }
        
        // Pattern 4: "dog passed away", "cat died yesterday", "dog die today"
        if (empty($key_info['loss_type']) && preg_match('/(\w+)\s+(died|die|passed away|passed|namatay|pumanaw)/i', $text, $matches)) {
            $subject = $matches[1] ?? '';
            if (!in_array(strtolower($subject), $nonLossTerms)) {
                $key_info = array_merge($key_info, $this->identifyLossSubject($subject));
            }
        }
        
        // Breakup detection
        if (preg_match('/(broke up|breakup|hiwalay|break up|left me|dumped|ex-|broke it off)/i', $text)) {
            $key_info['event'] = 'breakup';
        }
        
        // Failure detection
        if (preg_match('/(failed|failing|bumagsak|bagsak|flunked)\s+(exam|test|subject|class|course)/i', $text)) {
            $key_info['event'] = 'academic_failure';
        }
        
        // Achievement detection
        if (preg_match('/(passed|got an?|achieved|won|pumasa|nakakuha|aced|nailed)/i', $text)) {
            $key_info['event'] = 'achievement';
        }
        
        // Time indicators
        if (preg_match('/(yesterday|today|last night|this morning|last week|last month|kahapon|kanina|recently|just now)/i', $text, $matches)) {
            $key_info['timeframe'] = $matches[1];
        }
        
        return $key_info;
    }
    
    /**
     * Identify what type of loss based on subject
     */
    private function identifyLossSubject(string $subject): array
    {
        $subject = strtolower(trim($subject));
        $key_info = [];
        
        // Pet loss
        if (in_array($subject, ['dog', 'cat', 'pet', 'puppy', 'kitten', 'aso', 'pusa', 'alaga', 'rabbit', 'bird', 'hamster'])) {
            $key_info['loss_type'] = 'pet';
            $key_info['loss_subject'] = $subject;
        }
        // Parent loss
        elseif (in_array($subject, ['mom', 'dad', 'mother', 'father', 'parent', 'nanay', 'tatay', 'mama', 'papa', 'mommy', 'daddy'])) {
            $key_info['loss_type'] = 'parent';
            $key_info['loss_subject'] = $subject;
        }
        // Friend loss
        elseif (in_array($subject, ['friend', 'bestfriend', 'kaibigan', 'bff', 'buddy', 'pal'])) {
            $key_info['loss_type'] = 'friend';
            $key_info['loss_subject'] = $subject;
        }
        // Grandparent loss
        elseif (in_array($subject, ['grandma', 'grandpa', 'lola', 'lolo', 'grandmother', 'grandfather', 'granny', 'nana'])) {
            $key_info['loss_type'] = 'grandparent';
            $key_info['loss_subject'] = $subject;
        }
        // Sibling loss
        elseif (in_array($subject, ['brother', 'sister', 'sibling', 'kuya', 'ate', 'kapatid'])) {
            $key_info['loss_type'] = 'sibling';
            $key_info['loss_subject'] = $subject;
        }
        // General person
        elseif (strlen($subject) > 2) {
            $key_info['loss_type'] = 'someone_important';
            $key_info['loss_subject'] = $subject;
        }
        
        return $key_info;
    }
    
    /**
     * Assess how intense the emotion is
     */
    private function assessEmotionIntensity(string $emotion, array $context): string
    {
        if (in_array($emotion, ['sad', 'anxious']) && !empty($context['contexts'])) {
            return 'high';
        } elseif ($emotion === 'angry') {
            return 'high';
        } elseif ($emotion === 'happy') {
            return 'positive';
        }
        return 'moderate';
    }
    
    /**
     * Generate empathetic acknowledgment based on emotion
     */
    private function generateEmpatheticAcknowledgment(string $emotion, array $analysis): string
    {
        $intensity = $analysis['emotion_intensity'];
        $historyContext = $analysis['history_context'] ?? [];
        
        // If we know what they're dealing with, reference it
        $contextPrefix = "";
        if (!empty($historyContext['loss_type'])) {
            $lossSubject = $historyContext['loss_subject'] ?? 'loss';
            $contextPrefix = "I know you're still grieving your {$lossSubject}. ";
        } elseif (!empty($historyContext['event']) && $historyContext['event'] === 'breakup') {
            $contextPrefix = "I know that breakup is still weighing on you. ";
        } elseif (!empty($historyContext['event']) && $historyContext['event'] === 'academic_failure') {
            $contextPrefix = "I know that academic setback is still affecting you. ";
        }
        
        switch ($emotion) {
            case 'depressed':
                if ($intensity === 'high') {
                    return $contextPrefix . "Depression is serious, and I want to make sure you get proper support. What you're experiencing is treatable, but it needs professional help. 💙<br><br>";
                }
                return $contextPrefix . "I hear that you're depressed. That's more than just sadness - it's something that deserves professional attention. Have you talked to a counselor or doctor about this? 💙<br><br>";
            
            case 'sad':
                if ($intensity === 'high') {
                    return $contextPrefix . "The pain you're feeling right now is so heavy. �<br><br>";
                }
                return $contextPrefix . "I hear the sadness in what you're sharing. I'm here with you. �<br><br>";

            case 'down':
                return $contextPrefix . "It sounds like you're feeling a bit off or not quite yourself. That's okay — I'm here to listen to whatever you're going through. 💙<br><br>";

            case 'frustrated':
                return $contextPrefix . "I can hear how frustrating this is for you. That tension is real, and it's okay to feel annoyed by it. Let's see what might help. 💜<br><br>";

            case 'lonely':
                return $contextPrefix . "Feeling alone is really hard. I'm here with you, and I want to understand what's been making you feel this way. 💙<br><br>";

            case 'overwhelmed':
                return $contextPrefix . "It sounds like everything is piling up and it's too much right now. That feeling is overwhelming. Let's take it one step at a time. 💜<br><br>";

            case 'anxious':
                if ($intensity === 'high') {
                    return $contextPrefix . "Your mind sounds like it's racing right now. That's exhausting. 😰<br><br>";
                }
                return $contextPrefix . "I can sense the worry in your words. Let's work through this together.<br><br>";
                
            case 'angry':
                return $contextPrefix . "I can feel the frustration here. Your feelings are completely valid. 😤<br><br>";
                
            case 'happy':
                return $contextPrefix . "I love seeing this positive energy from you! 😊<br><br>";

            case 'content':
            case 'calm':
                return $contextPrefix . "It's good to hear you're feeling a bit more steady. I'm here if you want to share more about how things are going. 💛<br><br>";

            case 'okay':
            case 'meh':
                return $contextPrefix . "Sounds like things are just okay right now. That's okay too — I'm still here to listen to anything you want to talk about. 💙<br><br>";
                
            default:
                return $contextPrefix . "I'm here and listening to what you're sharing.<br><br>";
        }
    }
    
    /**
     * Generate contextual understanding based on detected contexts
     */
    private function generateContextualUnderstanding(array $contexts, string $emotion): string
    {
        $understanding = "";
        
        foreach ($contexts as $context) {
            switch ($context) {
                case 'academic_stress':
                    if ($emotion === 'anxious') {
                        $understanding .= "Academic pressure can feel overwhelming - like everything's piling up at once. ";
                    } elseif ($emotion === 'sad') {
                        $understanding .= "School stress can really drain you emotionally. It's more than just grades - it affects your whole wellbeing. ";
                    } else {
                        $understanding .= "I see you're dealing with school-related stuff. ";
                    }
                    break;
                    
                case 'relationship_issues':
                    if ($emotion === 'sad') {
                        $understanding .= "Relationship pain hits different - it can affect everything else in your life. ";
                    } elseif ($emotion === 'angry') {
                        $understanding .= "When people we care about hurt us or let us down, that anger is real and justified. ";
                    } else {
                        $understanding .= "Relationships can be complicated. ";
                    }
                    break;
                    
                case 'financial_concerns':
                    $understanding .= "Money stress is constant and exhausting - it's always there in the back of your mind. ";
                    break;
                    
                case 'health_concerns':
                    $understanding .= "When your body isn't feeling right, it makes everything harder. Your health matters. ";
                    break;
                    
                case 'loneliness':
                    $understanding .= "Feeling disconnected and alone is one of the hardest things to experience. You're not alone in feeling this way. 💜 ";
                    break;
            }
        }
        
        if ($understanding) {
            $understanding .= "<br><br>";
        }
        
        return $understanding;
    }
    
    /**
     * Generate therapeutic question to deepen understanding
     * Uses conversation stage to ask appropriate follow-ups
     */
    private function generateTherapeuticQuestion(string $emotion, array $context, array $analysis): string
    {
        // Determine conversation stage
        $conversationDepth = $analysis['conversation_depth'] ?? 0;
        $historyContext = $analysis['history_context'] ?? [];
        
        // If we already know what they're dealing with, ask more specific questions
        if (!empty($historyContext)) {
            // Don't ask "what happened" - we already know
            // Ask about coping, feelings, support instead
            if ($conversationDepth <= 2) {
                return $this->getContextAwareQuestion($emotion, $historyContext);
            } else {
                return $this->getSupportQuestion($emotion, $context);
            }
        }
        
        // Get conversation-aware questions for new topics
        if ($conversationDepth === 0) {
            // First message - understand the situation
            return $this->getInitialQuestion($emotion, $context);
        } elseif ($conversationDepth <= 2) {
            // Early conversation - explore deeper
            return $this->getExplorationQuestion($emotion, $context);
        } elseif ($conversationDepth <= 4) {
            // Mid conversation - understand feelings
            return $this->getDeepDiveQuestion($emotion, $context);
        } else {
            // Later conversation - support and coping
            return $this->getSupportQuestion($emotion, $context);
        }
    }
    
    /**
     * Context-aware questions when we know what they're dealing with
     */
    private function getContextAwareQuestion(string $emotion, array $historyContext): string
    {
        $questions = [];
        
        if (!empty($historyContext['loss_type'])) {
            $lossSubject = $historyContext['loss_subject'] ?? 'them';
            $questions = [
                "What's the hardest part for you right now?",
                "Have you been able to talk to anyone about this?",
                "What helps you feel even a little bit better?",
                "Are you taking care of yourself through this?"
            ];
        } elseif (!empty($historyContext['event']) && $historyContext['event'] === 'breakup') {
            $questions = [
                "How are you coping day-to-day?",
                "Do you have people supporting you through this?",
                "What's been the hardest moment so far?",
                "Are you taking care of yourself?"
            ];
        } else {
            $questions = [
                "How are you handling this?",
                "What do you need right now?",
                "Have you talked to anyone about this?"
            ];
        }
        
        return $questions[array_rand($questions)] . "<br><br>";
    }
    
    /**
     * Initial questions to understand the situation
     */
    private function getInitialQuestion(string $emotion, array $context): string
    {
        $questions = [];
        
        switch ($emotion) {
            case 'sad':
                $questions = [
                    "What happened that's making you feel this way?",
                    "How long have you been feeling like this?",
                    "Is this something that just happened, or has it been building up?"
                ];
                break;
            case 'anxious':
                $questions = [
                    "What's the main thing that's worrying you right now?",
                    "When did you start feeling this anxiety?",
                    "Is this about something specific, or more of a general feeling?"
                ];
                break;
            case 'angry':
                $questions = [
                    "What happened that made you feel this way?",
                    "Who or what are you angry at?",
                    "Is this something that's been building up, or did something just trigger it?"
                ];
                break;
            case 'happy':
                $questions = [
                    "That's great! What happened?",
                    "Tell me more about what's making you feel so good!",
                    "How long have you been feeling this way?"
                ];
                break;
            default:
                $questions = [
                    "How are you really feeling right now?",
                    "What's been on your mind lately?",
                    "Is there something specific you want to talk about?"
                ];
        }
        
        return $questions[array_rand($questions)] . "<br><br>";
    }
    
    /**
     * Exploration questions to dig deeper
     */
    private function getExplorationQuestion(string $emotion, array $context): string
    {
        // Context-specific exploration
        if (in_array('academic_stress', $context['contexts'])) {
            $questions = [
                "How is this affecting your daily life?",
                "Have you talked to anyone about this - professors, friends, family?",
                "What's the hardest part about this situation for you?",
                "When do you feel the most stressed about this?"
            ];
        } elseif (in_array('relationship_issues', $context['contexts'])) {
            $questions = [
                "How long has this been going on?",
                "Have you been able to talk to them about how you're feeling?",
                "What do you think you need right now?",
                "How is this affecting other parts of your life?"
            ];
        } elseif (in_array('loneliness', $context['contexts'])) {
            $questions = [
                "What does a typical day look like for you?",
                "Is there anyone you feel comfortable talking to?",
                "What kind of connection are you looking for?",
                "Have you always felt this way, or is this new?"
            ];
        } else {
            $questions = [
                "Tell me more about that.",
                "How is this affecting you?",
                "What's been the hardest part?",
                "How long has this been going on?"
            ];
        }
        
        return $questions[array_rand($questions)] . "<br><br>";
    }
    
    /**
     * Deep dive questions to understand feelings
     */
    private function getDeepDiveQuestion(string $emotion, array $context): string
    {
        $questions = [
            "How does this make you feel about yourself?",
            "What's the worst part about this for you?",
            "Have you experienced something like this before?",
            "What would help you feel better right now?",
            "What are you most afraid of in this situation?",
            "How do you usually cope when things get tough?",
            "What would you tell a friend who was going through the same thing?"
        ];
        
        return $questions[array_rand($questions)] . "<br><br>";
    }
    
    /**
     * Support questions focused on moving forward
     */
    private function getSupportQuestion(string $emotion, array $context): string
    {
        $questions = [
            "What's one small thing you could do today that might help?",
            "Have you thought about talking to a counselor about this?",
            "What kind of support do you need right now?",
            "What helps you feel better when you're going through tough times?",
            "Is there someone in your life you can reach out to?",
            "What would make tomorrow a little bit easier for you?"
        ];
        
        return $questions[array_rand($questions)] . "<br><br>";
    }
    
    /**
     * Generate supportive guidance with practical suggestions
     * Adapts based on conversation depth
     */
    private function generateSupportiveGuidance(string $emotion, array $context, array $analysis): string
    {
        $conversationDepth = $analysis['conversation_depth'] ?? 0;
        $guidance = "";
        
        // Early conversation - validation and empathy
        if ($conversationDepth <= 1) {
            switch ($emotion) {
                case 'sad':
                    $guidance .= "I'm here with you through this. Your feelings are completely valid. 💙";
                    break;
                case 'anxious':
                    $guidance .= "I hear you. Anxiety can be overwhelming, but we'll work through this together. 💜";
                    break;
                case 'angry':
                    $guidance .= "Your anger is valid. It's telling you something important. I'm here to listen. 💜";
                    break;
                case 'happy':
                    $guidance .= "I love seeing you happy! Hold onto this feeling. 😊";
                    break;
                default:
                    $guidance .= "I'm here for you, whatever you're going through. 💜";
            }
        }
        // Mid conversation - understanding and exploration
        elseif ($conversationDepth <= 3) {
            switch ($emotion) {
                case 'sad':
                    $guidance .= "It's okay to feel this way. Sadness is a natural response to pain. You don't have to go through this alone. 💙";
                    break;
                case 'anxious':
                    $guidance .= "When anxiety feels overwhelming, try to focus on what you can control right now - just this moment. ";
                    if (in_array('academic_stress', $context['contexts'])) {
                        $guidance .= "Breaking things into smaller pieces can help. 💜";
                    } else {
                        $guidance .= "Take it one step at a time. 💜";
                    }
                    break;
                case 'angry':
                    $guidance .= "Anger often shows us where our boundaries are. It's okay to feel this way. What matters is how we process it. 💜";
                    break;
                case 'happy':
                    $guidance .= "This happiness is real and it matters. Remember this feeling on harder days. 😊";
                    break;
                default:
                    $guidance .= "I'm listening and I'm here to support you however I can. 💜";
            }
        }
        // Later conversation - coping strategies and support
        else {
            switch ($emotion) {
                case 'sad':
                    $guidance .= "You've been carrying this for a while. Have you thought about talking to a counselor? ";
                    $guidance .= "They can provide tools and support that might really help. ";
                    $guidance .= "In the meantime, what usually helps you feel even a little bit better? 💙";
                    break;
                case 'anxious':
                    $guidance .= "Managing anxiety is a process. Some things that help: deep breathing, breaking tasks into tiny steps, ";
                    $guidance .= "talking to someone you trust, or writing down your worries. ";
                    $guidance .= "What sounds most helpful to you right now? 💜";
                    break;
                case 'angry':
                    $guidance .= "It's important to process anger in healthy ways. Some people find it helps to: ";
                    $guidance .= "talk it out, write about it, do physical activity, or take space to cool down. ";
                    $guidance .= "What usually works for you? 💜";
                    break;
                case 'happy':
                    $guidance .= "Keep nurturing this positive energy. You deserve to feel good. ";
                    $guidance .= "What can you do to keep this momentum going? 😊";
                    break;
                default:
                    $guidance .= "Remember, reaching out for support is a sign of strength, not weakness. ";
                    $guidance .= "I'm here, and there are counselors available if you need more support. 💜";
            }
        }
        
        return $guidance;
    }
    
    /**
     * Use ML training data to improve response relevance
     */
    private function getLearnedResponsePatterns(string $emotion, array $contexts): array
    {
        // Query training data for similar emotional states and contexts
        $similarEntries = MLTrainingData::where('actual_emotion', $emotion)
            ->where('is_validated', true)
            ->where('feedback_rating', '>=', 4) // Only use well-rated responses
            ->limit(10)
            ->get();
        
        $patterns = [
            'common_concerns' => [],
            'effective_approaches' => []
        ];
        
        foreach ($similarEntries as $entry) {
            // Extract common words/phrases from highly-rated interactions
            $text = strtolower($entry->text_input);
            
            // Identify what users commonly express in this emotional state
            if (strlen($text) > 20) {
                $words = array_filter(
                    explode(' ', $text),
                    function($word) {
                        return strlen($word) > 4 && !in_array($word, ['about', 'really', 'think', 'feeling']);
                    }
                );
                
                foreach ($words as $word) {
                    if (!isset($patterns['common_concerns'][$word])) {
                        $patterns['common_concerns'][$word] = 0;
                    }
                    $patterns['common_concerns'][$word]++;
                }
            }
        }
        
        // Sort by frequency
        arsort($patterns['common_concerns']);
        $patterns['common_concerns'] = array_slice($patterns['common_concerns'], 0, 5, true);
        
        return $patterns;
    }
    
    /**
     * Answer complex mental health questions using knowledge base
     */
    private function answerComplexQuestion(string $question, string $emotion, array $historyContext): ?string
    {
        $question_lower = strtolower($question);
        
        // Load knowledge base
        $knowledgePath = base_path('ml_service/knowledge_base.json');
        if (!file_exists($knowledgePath)) {
            return null;
        }
        
        $knowledge = json_decode(file_get_contents($knowledgePath), true);
        if (!$knowledge) {
            return null;
        }
        
        // Question patterns and answers
        $patterns = [
            // Therapy questions
            '/how long.*therapy|therapy.*how long/i' => function() use ($knowledge) {
                return "Great question! " . $knowledge['common_questions']['how_long_therapy'] . "<br><br>The important thing is finding what works for you. Would you like to know more about different types of therapy?<br><br>";
            },
            '/is therapy confidential|confidential.*therapy/i' => function() use ($knowledge) {
                return $knowledge['common_questions']['is_therapy_confidential'] . "<br><br>This confidentiality helps create a safe space for you to be open and honest. Do you have concerns about privacy?<br><br>";
            },
            '/how.*find.*therapist|where.*find.*therapist/i' => function() use ($knowledge) {
                return "Finding the right therapist is important! " . $knowledge['common_questions']['how_find_therapist'] . "<br><br>Would you like help finding resources in your area?<br><br>";
            },
            '/medication.*therapy|therapy.*medication|meds.*therapy/i' => function() use ($knowledge) {
                return $knowledge['common_questions']['medication_vs_therapy'] . "<br><br>Have you talked to a doctor or counselor about your options?<br><br>";
            },
            
            // Coping strategies
            '/how.*cope|coping.*strategies|what.*help/i' => function() use ($emotion, $knowledge) {
                if ($emotion === 'anxious') {
                    $strategies = $knowledge['mental_health_topics']['anxiety']['coping_strategies'];
                    return "For anxiety, here are evidence-based strategies that really help:<br><br>" .
                           "• " . implode("<br>• ", array_slice($strategies, 0, 5)) . "<br><br>" .
                           "Which of these sounds most doable for you right now?<br><br>";
                } elseif ($emotion === 'sad') {
                    $strategies = $knowledge['mental_health_topics']['depression']['coping_strategies'];
                    return "When you're feeling down, these strategies can help:<br><br>" .
                           "• " . implode("<br>• ", array_slice($strategies, 0, 5)) . "<br><br>" .
                           "What feels like a good first step for you?<br><br>";
                } else {
                    return "There are many coping strategies that can help. The best ones depend on what you're dealing with. Can you tell me more about what's been challenging for you?<br><br>";
                }
            },
            
            // Explicit advice / suggestion requests
            '/suggestion|any suggestion|do you have.*suggestion|could you suggest|can you suggest|do you have any advice|do you have.*advice|any advice|help me/i' => function() use ($emotion, $knowledge) {
                if ($emotion === 'sad') {
                    $strategies = $knowledge['mental_health_topics']['depression']['coping_strategies'];
                    return "I'm sorry you're feeling sad. Here are some gentle suggestions that may help:<br><br>" .
                           "• " . implode("<br>• ", array_slice($strategies, 0, 5)) . "<br><br>" .
                           "Would you like one small step to try first?<br><br>";
                }
                if ($emotion === 'anxious') {
                    $strategies = $knowledge['mental_health_topics']['anxiety']['coping_strategies'];
                    return "It sounds like you're looking for something practical right now. Try one of these anxiety-friendly strategies:<br><br>" .
                           "• " . implode("<br>• ", array_slice($strategies, 0, 5)) . "<br><br>" .
                           "Which of these feels easiest to start with?<br><br>";
                }
                $activities = $knowledge['self_care_activities'];
                return "A few simple suggestions that often help are:<br><br>" .
                       "• " . implode(", ", array_slice($activities['physical'], 0, 3)) . "<br>" .
                       "• " . implode(", ", array_slice($activities['mental'], 0, 3)) . "<br>" .
                       "• " . implode(", ", array_slice($activities['social'], 0, 2)) . "<br><br>" .
                       "Would you like one small thing to try first?<br><br>";
            },
            
            // Breathing exercises
            '/breathing.*exercise|how.*breathe|breathing.*technique/i' => function() use ($knowledge) {
                $breathing = $knowledge['coping_techniques']['breathing_exercises'];
                return "Breathing exercises are powerful! Here are three effective techniques:<br><br>" .
                       "<strong>4-7-8 Breathing:</strong> " . $breathing['4-7-8_breathing'] . "<br><br>" .
                       "<strong>Box Breathing:</strong> " . $breathing['box_breathing'] . "<br><br>" .
                       "Try one right now and see how you feel. Which one would you like to try?<br><br>";
            },
            
            // Grounding techniques
            '/grounding|panic.*attack|overwhelmed/i' => function() use ($knowledge) {
                $grounding = $knowledge['coping_techniques']['grounding_techniques'];
                return "When you're feeling overwhelmed, grounding techniques can help bring you back to the present:<br><br>" .
                       "<strong>5-4-3-2-1 Technique:</strong> " . $grounding['5-4-3-2-1'] . "<br><br>" .
                       "Try this right now. Take your time with each step. How are you feeling?<br><br>";
            },
            
            // Depression questions
            '/am i depressed|signs.*depression|symptoms.*depression/i' => function() use ($knowledge) {
                $symptoms = $knowledge['mental_health_topics']['depression']['symptoms'];
                return "Depression can show up in different ways. Common signs include:<br><br>" .
                       "• " . implode("<br>• ", $symptoms) . "<br><br>" .
                       $knowledge['mental_health_topics']['depression']['when_to_seek_help'] . "<br><br>" .
                       "Have you been experiencing any of these? It's important to talk to a professional who can properly assess and help you.<br><br>";
            },
            
            // Anxiety questions
            '/anxiety.*normal|too much.*anxiety|anxiety.*disorder/i' => function() use ($knowledge) {
                return "Anxiety becomes a concern when it's persistent, excessive, and interferes with your daily life. " .
                       $knowledge['mental_health_topics']['anxiety']['when_to_seek_help'] . "<br><br>" .
                       "If you're asking this question, it might be worth talking to a counselor. They can help you understand what you're experiencing and provide support.<br><br>";
            },
            
            // Grief questions
            '/how long.*grief|grief.*normal|stages.*grief/i' => function() use ($knowledge) {
                $stages = $knowledge['mental_health_topics']['grief']['stages'];
                return "Grief is a deeply personal process. While people often talk about stages (" . implode(", ", $stages) . "), grief doesn't follow a linear path. You might move back and forth between these feelings.<br><br>" .
                       "There's no 'normal' timeline for grief. " . $knowledge['mental_health_topics']['grief']['when_to_seek_help'] . "<br><br>" .
                       "How are you coping with your loss?<br><br>";
            },
            
            // Self-care questions
            '/self.*care|take care.*myself|what.*should.*do/i' => function() use ($knowledge) {
                $activities = $knowledge['self_care_activities'];
                return "Self-care is essential for mental health! Here are different types of self-care:<br><br>" .
                       "<strong>Physical:</strong> " . implode(", ", array_slice($activities['physical'], 0, 4)) . "<br>" .
                       "<strong>Mental:</strong> " . implode(", ", array_slice($activities['mental'], 0, 4)) . "<br>" .
                       "<strong>Social:</strong> " . implode(", ", array_slice($activities['social'], 0, 3)) . "<br>" .
                       "<strong>Creative:</strong> " . implode(", ", array_slice($activities['creative'], 0, 4)) . "<br><br>" .
                       "What type of self-care appeals to you most?<br><br>";
            },
            
            // Crisis resources
            '/crisis.*hotline|suicide.*hotline|need.*help.*now/i' => function() use ($knowledge) {
                $crisis = $knowledge['crisis_resources']['philippines'];
                return "🚨 <strong>If you're in crisis, please reach out for immediate help:</strong><br><br>" .
                       "• <strong>National Crisis Hotline:</strong> " . $crisis['national_crisis_hotline'] . "<br>" .
                       "• <strong>Text Service:</strong> " . $crisis['text_service'] . "<br>" .
                       "• <strong>Hopeline:</strong> " . $crisis['hopeline'] . "<br><br>" .
                       "These services are confidential and available 24/7. Please don't hesitate to call. Are you safe right now?<br><br>";
            },
            
            // Helping others
            '/how.*help.*friend|friend.*depressed|worried.*about.*friend/i' => function() use ($knowledge) {
                return $knowledge['common_questions']['how_help_friend'] . "<br><br>" .
                       "It's wonderful that you care about your friend. Remember, you can't force someone to get help, but you can be supportive and encourage them. Are you okay? Supporting someone can be emotionally taxing too.<br><br>";
            }
        ];
        
        // Check each pattern
        foreach ($patterns as $pattern => $answerFunc) {
            if (preg_match($pattern, $question_lower)) {
                return $answerFunc();
            }
        }
        
        return null;
    }
    
    /**
     * Track response history to avoid repetition
     */
    private function avoidRepetitiveResponse(string $response, array $conversationHistory): string
    {
        // Get last 3 AI responses
        $recentResponses = [];
        foreach (array_slice($conversationHistory, -3) as $entry) {
            if (isset($entry['ai']) && $entry['ai']) {
                $recentResponses[] = strtolower(strip_tags($entry['ai']));
            }
        }
        
        // Check if current response is too similar to recent ones
        $currentLower = strtolower(strip_tags($response));
        
        foreach ($recentResponses as $recent) {
            similar_text($currentLower, $recent, $percent);
            
            // If more than 70% similar, add variation
            if ($percent > 70) {
                $variations = [
                    "I want to make sure I'm understanding you correctly. ",
                    "Let me approach this from a different angle. ",
                    "I hear what you're saying. ",
                    "That's an important point. ",
                    "I'm glad you're sharing this with me. "
                ];
                
                $response = $variations[array_rand($variations)] . $response;
                break;
            }
        }
        
        return $response;
    }
}
