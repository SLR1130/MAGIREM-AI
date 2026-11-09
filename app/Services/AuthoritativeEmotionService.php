<?php

namespace App\Services;

class AuthoritativeEmotionService
{
    private const CANONICAL = [
        'sad' => 'sadness', 'down' => 'sadness', 'sadness' => 'sadness',
        'happy' => 'joy', 'happiness' => 'joy', 'joy' => 'joy',
        'anxious' => 'anxiety', 'anxiety' => 'anxiety',
        'angry' => 'anger', 'anger' => 'anger', 'neutral' => 'neutral', 'grief' => 'grief',
    ];
    /**
     * Resolve the user-visible emotion after Flask confidence routing.
     * Laravel's preliminary result remains available as a separate safety signal.
     */
    public function resolve(array $preliminary, array $aiResponse): array
    {
        $analysis = is_array($aiResponse['analysis'] ?? null)
            ? $aiResponse['analysis']
            : [];
        $method = strtolower((string) ($analysis['method'] ?? $aiResponse['emotion_source'] ?? $preliminary['method'] ?? ''));

        $emotion = $this->firstEmotion([
            $analysis['emotion'] ?? null,
            $aiResponse['emotion'] ?? null,
            $preliminary['emotion'] ?? null,
        ]);

        $confidence = $analysis['confidence']
            ?? $aiResponse['confidence']
            ?? $preliminary['confidence']
            ?? 0.5;

        $confidence = (float) $confidence;
        if ($confidence > 1.0) {
            $confidence /= 100.0;
        }

        return [
            'emotion' => $emotion,
            'confidence' => max(0.0, min(1.0, $confidence)),
            // Keep the numeric signal for internal evaluation, but expose its
            // provenance and meaning separately from the user-facing label.
            'confidence_type' => (string) ($analysis['confidence_type']
                ?? (str_contains($method, 'trained') ? 'model_softmax_max' : 'legacy_unknown')),
            'confidence_is_calibrated' => (bool) ($analysis['confidence_is_calibrated'] ?? false),
            'uncertain' => (bool) ($analysis['uncertain'] ?? !str_contains($method, 'trained')),
            // Flask currently does not return a routed intensity. Retain the
            // preliminary numeric signal without treating it as a final label.
            'intensity' => max(0.0, min(1.0, (float) ($preliminary['intensity'] ?? 0.5))),
            'source' => (string) (
                $analysis['method']
                ?? $aiResponse['emotion_source']
                ?? $preliminary['method']
                ?? 'laravel_preliminary'
            ),
            'preliminary_emotion' => $this->firstEmotion([$preliminary['emotion'] ?? null]),
            'preliminary_confidence' => $this->normalizeConfidence($preliminary['confidence'] ?? 0.5),
        ];
    }

    private function firstEmotion(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $candidate = strtolower(trim($candidate));
            if (isset(self::CANONICAL[$candidate])) {
                return self::CANONICAL[$candidate];
            }
        }

        return 'neutral';
    }

    private function normalizeConfidence(mixed $confidence): float
    {
        $confidence = (float) $confidence;
        if ($confidence > 1.0) {
            $confidence /= 100.0;
        }

        return max(0.0, min(1.0, $confidence));
    }
}
