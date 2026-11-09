<?php

namespace App\Services;

use App\Models\CounselorMessage;
use App\Models\EmotionalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EmotionScorerService
{
    private const EMOTION_SCORES = [
        // Positive/neutral (Flask labels first, then PHP legacy)
        'joy'        => 3,       // ← Flask label
        'excited'    => 3,
        'joyful'     => 3,
        'happy'      => 2,
        'grateful'   => 2,
        'content'    => 1,
        'calm'       => 1,
        'relaxed'    => 1,
        
        // Neutral
        'neutral'    => 0,
        'okay'       => 0,
        'meh'        => 0,
        'unsure'     => 0,
        
        // Mild negative
        'tired'      => -1,
        'bored'      => -1,
        'down'       => -1,
        
        // Moderate negative
        'frustrated' => -2,
        'disappointed' => -2,
        'lonely'     => -2,
        'overwhelmed' => -2,
        
        // Strong negative (Flask labels first)
        'sadness'    => -3,      // ← Flask label (primary)
        'sad'        => -3,      // ← Keep for backwards compatibility
        'anxiety'    => -3,      // ← Flask label (primary)
        'anxious'    => -3,      // ← Keep for backwards compatibility
        'anger'      => -3,      // ← Flask label (primary)
        'angry'      => -3,      // ← Keep for backwards compatibility
        'stressed'   => -3,
        'grief'      => -4,      // ← Flask label
        'depressed'  => -4,
        
        // Very strong negative
        'hopeless'   => -4,
        'helpless'   => -4,
        
        // Crisis level
        'suicidal'   => -5,
        'critical'   => -5,      // ← If Flask ever passes this
    ];

    protected $emotionDetectionService;

    public function __construct(EmotionDetectionService $emotionDetectionService)
    {
        $this->emotionDetectionService = $emotionDetectionService;
    }

    /**
     * Score a chat message and store result
     * 
     * @param int $messageId - counselor_messages.id
     * @param string $content - message text
     * @param int $userId - sender user_id
     * @return array ['score' => float, 'emotion' => string]
     */
    public function scoreChatMessage(int $messageId, string $content, int $userId, ?array $existingAnalysis = null): array
    {
        try {
            // Detect emotion using ML service with retry logic
            $emotionData = $existingAnalysis ?? $this->detectEmotionWithRetry($content);
            
            $emotion = $emotionData['emotion'];
            $confidence = $emotionData['confidence'] ?? 0.5;
            $originalScore = $this->mapEmotionToScore($emotion);
            $scaledScore = $this->scaleScoreByConfidence($originalScore, $confidence);
            
            // Get message timestamp for time weighting
            $timestamp = Carbon::now();
            $weightedScore = $this->applyTimeWeighting($scaledScore, $timestamp);
            
            // Store score
            $this->storeScore([
                'user_id' => $userId,
                'content_type' => 'message',
                'content_id' => $messageId,
                'emotion_label' => $emotion,
                'emotion_confidence' => $confidence,
                'original_score' => $originalScore,
                'weighted_score' => $weightedScore,
                'detected_at' => $timestamp,
            ]);
            
            return [
                'score' => $weightedScore,
                'emotion' => $emotion,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to score chat message', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to neutral score
            return $this->handleFailure($userId, 'message', $messageId);
        }
    }

    /**
     * Score a journal entry and store result
     * 
     * @param int $entryId - emotional_entries.id
     * @param string $content - entry text
     * @param int $userId - author user_id
     * @return array ['score' => float, 'emotion' => string]
     */
    public function scoreJournalEntry(int $entryId, string $content, int $userId, ?array $existingAnalysis = null): array
    {
        try {
            // Detect emotion using ML service with retry logic
            $emotionData = $existingAnalysis ?? $this->detectEmotionWithRetry($content);
            
            $emotion = $emotionData['emotion'];
            $confidence = $emotionData['confidence'] ?? 0.5;
            $originalScore = $this->mapEmotionToScore($emotion);
            $scaledScore = $this->scaleScoreByConfidence($originalScore, $confidence);
            
            // Get entry timestamp for time weighting
            $timestamp = Carbon::now();
            $weightedScore = $this->applyTimeWeighting($scaledScore, $timestamp);
            
            // Store score
            $this->storeScore([
                'user_id' => $userId,
                'content_type' => 'journal',
                'content_id' => $entryId,
                'emotion_label' => $emotion,
                'emotion_confidence' => $confidence,
                'original_score' => $originalScore,
                'weighted_score' => $weightedScore,
                'detected_at' => $timestamp,
            ]);
            
            return [
                'score' => $weightedScore,
                'emotion' => $emotion,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to score journal entry', [
                'entry_id' => $entryId,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to neutral score
            return $this->handleFailure($userId, 'journal', $entryId);
        }
    }

    /**
     * Batch process historical data
     * 
     * @param string $contentType - 'message' or 'journal'
     * @param int $batchSize - records per batch (default 100)
     * @return array ['processed' => int, 'failed' => int]
     */
    public function batchProcessHistoricalData(string $contentType, int $batchSize = 100): array
    {
        // Implementation will be added in task 12
        return ['processed' => 0, 'failed' => 0];
    }

    /**
     * Map emotion label to numerical score
     * 
     * @param string $emotion - happy, sad, anxious, angry, neutral, excited, calm, stressed, etc.
     * @return int - score between -5 and +3
     */
    private function mapEmotionToScore(string $emotion): int
    {
        $emotion = strtolower($emotion);
        return self::EMOTION_SCORES[$emotion] ?? 0;
    }

    /**
     * Apply time-based weighting to score
     * 
     * @param int $originalScore - base score
     * @param Carbon $timestamp - when content was created
     * @return float - weighted score (1 decimal place)
     */
    private function applyTimeWeighting(float $originalScore, Carbon $timestamp): float
    {
        $timeWeighting = new TimeWeightingService();
        $multiplier = $timeWeighting->getLateNightMultiplier($timestamp);
        return round($originalScore * $multiplier, 1);
    }

    /**
     * Scale score by confidence so low-confidence predictions don't overdrive wellbeing.
     */
    private function scaleScoreByConfidence(int $score, float $confidence): float
    {
        $confidence = min(max($confidence, 0.5), 1.0);
        return round($score * $confidence, 1);
    }

    /**
     * Store emotional score on the record that owns the content.
     * 
     * @param array $data - [user_id, content_type, content_id, emotion_label, original_score, weighted_score, detected_at]
     * @return bool
     */
    private function storeScore(array $data): bool
    {
        try {
            $values = [
                'detected_emotion' => $data['emotion_label'],
                'emotion_confidence' => $data['emotion_confidence'] ?? 0.5,
                'original_score' => $data['original_score'],
                'weighted_score' => $data['weighted_score'],
            ];

            if ($data['content_type'] === 'message') {
                return CounselorMessage::whereKey($data['content_id'])
                    ->where('sender_id', $data['user_id'])
                    ->update($values) === 1;
            }

            $values['emotion_score'] = $data['original_score'];
            $taxonomyMetadata = app(EmotionTaxonomy::class)->metadata([
                'emotion' => $data['emotion_label'] ?? null,
                'confidence' => $data['emotion_confidence'] ?? null,
                'source' => $data['emotion_source'] ?? null,
            ]);
            $values = array_merge($values, array_filter($taxonomyMetadata, static fn ($value) => $value !== null));
            return EmotionalEntry::whereKey($data['content_id'])
                ->where('user_id', $data['user_id'])
                ->update($values) === 1;
        } catch (\Exception $e) {
            Log::error('Failed to store emotional score', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Detect emotion with retry logic
     * 
     * @param string $content
     * @return array
     */
    private function detectEmotionWithRetry(string $content): array
    {
        $maxRetries = 3;
        $retryDelay = 2; // seconds
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->emotionDetectionService->detectEmotion($content);
            } catch (\Exception $e) {
                if ($attempt === $maxRetries) {
                    throw $e;
                }
                
                Log::warning("ML service attempt {$attempt} failed, retrying...", [
                    'error' => $e->getMessage()
                ]);
                
                sleep($retryDelay);
            }
        }
        
        // This should never be reached, but just in case
        throw new \Exception('ML service failed after all retries');
    }

    /**
     * Handle ML service failure by assigning neutral score
     * 
     * @param int $userId
     * @param string $contentType
     * @param int $contentId
     * @return array
     */
    private function handleFailure(int $userId, string $contentType, int $contentId): array
    {
        $timestamp = Carbon::now();
        
        $this->storeScore([
            'user_id' => $userId,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'emotion_label' => 'neutral',
            'emotion_confidence' => 0.0,
            'original_score' => 0,
            'weighted_score' => 0.0,
            'detected_at' => $timestamp,
        ]);
        
        return [
            'score' => 0.0,
            'emotion' => 'neutral',
        ];
    }
}
