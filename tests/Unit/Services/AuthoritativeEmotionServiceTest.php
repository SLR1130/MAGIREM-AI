<?php

namespace Tests\Unit\Services;

use App\Services\AuthoritativeEmotionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuthoritativeEmotionServiceTest extends TestCase
{
    #[DataProvider('routedEmotionCases')]
    public function test_final_flask_routing_is_authoritative(
        array $preliminary,
        array $flaskResponse,
        string $expectedEmotion,
        string $expectedSource
    ): void {
        $result = (new AuthoritativeEmotionService())->resolve($preliminary, $flaskResponse);

        $this->assertSame($expectedEmotion, $result['emotion']);
        $this->assertSame($expectedSource, $result['source']);
        $this->assertContains($result['preliminary_emotion'], ['sadness', 'joy', 'anxiety', 'anger', 'neutral', 'grief']);
        $this->assertGreaterThanOrEqual(0, $result['confidence']);
        $this->assertLessThanOrEqual(1, $result['confidence']);
    }

    public static function routedEmotionCases(): array
    {
        return [
            'English trained route' => [
                ['emotion' => 'neutral', 'confidence' => 0.48, 'intensity' => 0.4],
                ['emotion' => 'joy', 'analysis' => ['emotion' => 'joy', 'confidence' => 0.83, 'method' => 'trained_model']],
                'joy',
                'trained_model',
            ],
            'Tagalog trained route' => [
                ['emotion' => 'neutral', 'confidence' => 0.44, 'intensity' => 0.7],
                ['emotion' => 'sadness', 'analysis' => ['emotion' => 'sadness', 'confidence' => 0.78, 'method' => 'trained_model']],
                'sadness',
                'trained_model',
            ],
            'Taglish semantic fallback' => [
                ['emotion' => 'sadness', 'confidence' => 0.37, 'intensity' => 0.6],
                ['emotion' => 'anxiety', 'analysis' => ['emotion' => 'anxiety', 'confidence' => 0.64, 'method' => 'semantic_fallback']],
                'anxiety',
                'semantic_fallback',
            ],
            'Low-confidence semantic fallback replaces preliminary label' => [
                ['emotion' => 'calm', 'confidence' => 0.43, 'intensity' => 0.2, 'method' => 'ml_server_trained'],
                ['emotion' => 'neutral', 'analysis' => ['emotion' => 'neutral', 'confidence' => 0.60, 'method' => 'semantic_fallback']],
                'neutral',
                'semantic_fallback',
            ],
        ];
    }

    public function test_preliminary_result_is_used_only_when_no_routed_result_exists(): void
    {
        $result = (new AuthoritativeEmotionService())->resolve(
            ['emotion' => 'calm', 'confidence' => 62, 'intensity' => 0.2, 'method' => 'laravel_detector'],
            []
        );

        $this->assertSame('neutral', $result['emotion']);
        $this->assertSame(0.62, $result['confidence']);
        $this->assertSame('laravel_detector', $result['source']);
    }
}
