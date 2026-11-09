<?php

namespace Tests\Unit\Services;

use App\Services\EnhancedCrisisAnalysisService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnhancedCrisisMultilingualRegressionTest extends TestCase
{
    #[DataProvider('highRiskCases')]
    public function test_high_risk_evidence_is_preserved(
        string $message,
        bool $expectIntent,
        bool $expectPlan,
        float $minimumConfidence
    ): void {
        $result = app(EnhancedCrisisAnalysisService::class)->analyzeMessage($message);

        $this->assertContains($result['crisis_level'], ['severe', 'critical']);
        $this->assertSame($expectIntent, $result['intent_detected']);
        $this->assertSame($expectPlan, $result['plan_detected']);
        $this->assertGreaterThanOrEqual($minimumConfidence, $result['crisis_confidence']);
    }

    public static function highRiskCases(): array
    {
        return [
            'cross-sentence English distress and intent' => [
                "I can't take it anymore. I want to end this.", true, false, 0.85,
            ],
            'English inflection and explicit plan after joke' => [
                "lol I'm joking about killing myself, but honestly I've already made a plan.", false, true, 0.92,
            ],
            'positive affect does not suppress timed decision' => [
                "I'm happy now because I've finally decided to end everything tonight.", true, true, 0.97,
            ],
            'Tagalog direct current intent' => [
                'Gusto ko nang mamatay ngayon.', true, false, 0.97,
            ],
            'Tagalog explicit plan and timing' => [
                'May plano na akong magpakamatay mamayang gabi.', true, true, 0.92,
            ],
            'Taglish distress and timed decision' => [
                'Pagod na ako, I decided to end everything tonight.', true, true, 0.97,
            ],
            'denial cannot suppress later plan' => [
                "I don't want to die, but I already made a plan to end my life tonight.", true, true, 0.97,
            ],
        ];
    }

    #[DataProvider('nonCrisisCases')]
    public function test_bounded_non_crisis_language_is_not_escalated(
        string $message,
        bool $expectHyperbole = false
    ): void {
        $result = app(EnhancedCrisisAnalysisService::class)->analyzeMessage($message);

        $this->assertSame('none', $result['crisis_level']);
        $this->assertFalse($result['intent_detected']);
        $this->assertFalse($result['plan_detected']);
        $this->assertLessThan(0.5, $result['crisis_confidence']);
        if ($expectHyperbole) {
            $this->assertTrue($result['hyperbole_detected']);
        }
    }

    public static function nonCrisisCases(): array
    {
        return [
            'English explicit denial and help seeking' => [
                "I don't want to die. I want help.", false,
            ],
            'Tagalog explicit denial and help seeking' => [
                'Hindi ko gustong mamatay; gusto kong humingi ng tulong.', false,
            ],
            'Tagalog desire to live is recovery language' => [
                'Gusto ko nang mabuhay.', false,
            ],
            'academic hyperbole' => [
                'This assignment is literally killing me 😅', true,
            ],
            'bounded dark humor' => [
                "If I see one more romantic comedy I'm going to kill myself lmao", true,
            ],
            'Tagalog embarrassment idiom' => [
                'Patay ako sa hiya.', true,
            ],
            'Tagalog excitement idiom' => [
                'Mamatay ako sa kilig haha.', true,
            ],
            'ordinary future intent with timing' => [
                'I want to finish my project tonight.', false,
            ],
            'benign medication access' => [
                'I have pills in my room for my headache.', false,
            ],
        ];
    }

    public function test_historical_ideation_with_current_recovery_is_not_critical(): void
    {
        $result = app(EnhancedCrisisAnalysisService::class)
            ->analyzeMessage('Dati gusto kong mamatay, pero okay ako ngayon at humihingi ako ng tulong.');

        $this->assertNotSame('critical', $result['crisis_level']);
        $this->assertFalse($result['intent_detected']);
    }
}
