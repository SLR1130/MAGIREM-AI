<?php

namespace Tests\Unit\Services;

use App\Services\AuthoritativeEmotionService;
use Tests\TestCase;

class EmotionConfidenceProvenanceTest extends TestCase
{
    public function test_semantic_fallback_is_uncertain_even_when_heuristic_score_is_one(): void
    {
        $result = app(AuthoritativeEmotionService::class)->resolve(
            ['emotion' => 'neutral', 'confidence' => 0.42, 'method' => 'laravel_preliminary'],
            [
                'analysis' => [
                    'emotion' => 'anxiety',
                    'confidence' => 1.0,
                    'method' => 'semantic_fallback',
                    'confidence_type' => 'semantic_heuristic_score',
                    'confidence_is_calibrated' => false,
                    'uncertain' => true,
                ],
            ]
        );

        $this->assertSame('anxiety', $result['emotion']);
        $this->assertSame(1.0, $result['confidence']);
        $this->assertSame('semantic_heuristic_score', $result['confidence_type']);
        $this->assertFalse($result['confidence_is_calibrated']);
        $this->assertTrue($result['uncertain']);
    }

    public function test_trained_route_preserves_model_provenance_without_claiming_calibration(): void
    {
        $result = app(AuthoritativeEmotionService::class)->resolve(
            ['emotion' => 'neutral', 'confidence' => 0.42, 'method' => 'laravel_preliminary'],
            [
                'analysis' => [
                    'emotion' => 'sadness',
                    'confidence' => 0.61,
                    'method' => 'trained_model',
                    'confidence_type' => 'model_softmax_max',
                    'confidence_is_calibrated' => false,
                    'uncertain' => false,
                ],
            ]
        );

        $this->assertSame('sadness', $result['emotion']);
        $this->assertSame('model_softmax_max', $result['confidence_type']);
        $this->assertFalse($result['confidence_is_calibrated']);
        $this->assertFalse($result['uncertain']);
    }

    public function test_laravel_preliminary_classifier_uses_configured_flask_health_probe(): void
    {
        $source = file_get_contents(base_path('app/Services/EmotionDetectionService.php'));

        $this->assertStringContainsString("return \$this->isMLServerRunning();", $source);
        $this->assertStringContainsString("\$healthUrl = \$this->mlApiUrl('health');", $source);
        $this->assertStringNotContainsString('file_exists($pythonScript)', $source);
    }

    public function test_student_views_do_not_present_numeric_emotion_confidence(): void
    {
        foreach (['chat.blade.php', 'journal.blade.php', 'entries.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/student/' . $view));
            $this->assertStringContainsString('AI-estimated', $source, $view);
        }

        $chat = file_get_contents(resource_path('views/student/chat.blade.php'));
        $this->assertStringNotContainsString('confidencePercent}%', $chat);
        $this->assertStringNotContainsString('confidence_score) }}%', $chat);
    }
}
