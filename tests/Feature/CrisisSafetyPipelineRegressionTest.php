<?php

namespace Tests\Feature;

use App\Services\EnhancedCrisisAnalysisService;
use App\Services\RiskPhraseScreen;
use Tests\TestCase;

class CrisisSafetyPipelineRegressionTest extends TestCase
{
    public function test_deterministic_screen_preserves_risk_when_enhanced_context_is_lower(): void
    {
        $message = 'I want to kill myself lol';
        $enhanced = app(EnhancedCrisisAnalysisService::class)->analyzeMessage($message);
        $screen = app(RiskPhraseScreen::class)->screen($message);

        $this->assertSame('critical', $screen['level']);
        $this->assertSame('critical', RiskPhraseScreen::higher(
            $enhanced['crisis_level'],
            $screen['level']
        ));
    }

    public function test_joke_marker_cannot_lower_an_independent_plan(): void
    {
        $result = app(EnhancedCrisisAnalysisService::class)->analyzeMessage(
            "lol I was joking earlier, but I made a plan to end my life tonight"
        );

        $this->assertTrue($result['plan_detected']);
        $this->assertSame('critical', $result['crisis_level']);
        $this->assertGreaterThanOrEqual(0.97, $result['crisis_confidence']);
    }

    public function test_safety_results_are_indicators_not_diagnoses(): void
    {
        $result = app(EnhancedCrisisAnalysisService::class)->analyzeMessage('I feel overwhelmed today.');

        $this->assertArrayHasKey('crisis_level', $result);
        $this->assertArrayNotHasKey('diagnosis', $result);
    }
}
