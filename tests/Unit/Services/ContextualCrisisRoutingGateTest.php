<?php

namespace Tests\Unit\Services;

use App\Http\Controllers\AIChatController;
use Tests\TestCase;

class ContextualCrisisRoutingGateTest extends TestCase
{
    private function shouldAssess(string $message, array $screen = [], array $enhanced = []): bool
    {
        $controller = app(AIChatController::class);
        $method = new \ReflectionMethod($controller, 'requiresContextualCrisisAssessment');
        return $method->invoke($controller, $message, array_merge(['level' => 'none'], $screen), $enhanced);
    }

    public function test_ordinary_sadness_and_academic_anxiety_do_not_start_confirmation_assessment(): void
    {
        $this->assertFalse($this->shouldAssess('I am sad that I did not pass my presentation.'));
        $this->assertFalse($this->shouldAssess('Kabado ako sa exam bukas.'));
    }

    public function test_existing_candidate_evidence_and_explicit_ideation_still_start_assessment(): void
    {
        $this->assertTrue($this->shouldAssess('I feel hopeless.', ['level' => 'moderate']));
        $this->assertTrue($this->shouldAssess('Sometimes I am thinking about dying.'));
        $this->assertTrue($this->shouldAssess('Sometimes I am thinking about suicide.'));
        $this->assertTrue($this->shouldAssess('I may hurt myself tonight.', [], ['intent_detected' => true]));
    }

    public function test_test_labels_are_not_treated_as_first_person_disclosures(): void
    {
        $controller = app(AIChatController::class);
        $method = new \ReflectionMethod($controller, 'isMetalinguisticSafetyText');
        $this->assertTrue($method->invoke($controller, 'Explicit current self-harm intent'));
        $this->assertTrue($method->invoke($controller, 'Test case: explicit self-harm intent'));
        $this->assertFalse($method->invoke($controller, 'I want to hurt myself tonight.'));
    }

    public function test_crisis_response_breaks_are_preserved_as_safe_newlines(): void
    {
        $controller = app(AIChatController::class);
        $method = new \ReflectionMethod($controller, 'sanitizeResponseText');
        $response = $method->invoke($controller, 'First<br><br><strong>Second</strong><br />Third');

        $this->assertSame("First\n\nSecond\nThird", $response);
    }
    public function test_crisis_contact_text_uses_verified_hopeline_calling_language(): void
    {
        $controller = app(AIChatController::class);
        $method = new \ReflectionMethod($controller, 'withVerifiedCrisisContacts');
        $response = $method->invoke($controller, '<strong>Text Hopeline</strong> - Text <strong>2919</strong> (DSWD Crisis Line, available on Globe/TM)<br> or <strong>989-8727</strong>');
        $this->assertStringContainsString('Hopeline Philippines', $response);
        $this->assertStringContainsString('toll-free for Globe/TM subscribers', $response);
        $this->assertStringNotContainsString('DSWD Crisis Line', $response);
        $this->assertStringNotContainsString('989-8727', $response);
    }
}
