<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\EnhancedCrisisAnalysisService;
use App\Services\WellbeingScoreCalculator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EnhancedCrisisDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected $crisisService;
    protected $wellbeingCalculator;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->crisisService = app(EnhancedCrisisAnalysisService::class);
        $this->wellbeingCalculator = app(WellbeingScoreCalculator::class);
    }
    
    /**
     * Test: Sarcasm should NOT trigger crisis alert
     */
    public function test_sarcasm_with_emoji_is_not_crisis()
    {
        $text = "Yeah sure I'm suicidal 🙄";
        $result = $this->crisisService->analyzeMessage($text);
        
        $this->assertEquals('none', $result['crisis_level']);
        $this->assertTrue($result['sarcasm_detected']);
        $this->assertLessThan(0.5, $result['crisis_confidence']);
    }
    
    /**
     * Test: Joking about serious topics should not trigger crisis
     */
    public function test_joking_with_lol_not_crisis()
    {
        $text = "I'm dying of laughter lol 😂";
        $result = $this->crisisService->analyzeMessage($text);
        
        $this->assertEquals('none', $result['crisis_level']);
        $this->assertTrue($result['satire_detected']);
    }
    
    /**
     * Test: Hyperbole should be detected as non-crisis
     */
    public function test_hyperbole_detected()
    {
        $text = "This assignment is literally killing me 😅";
        $result = $this->crisisService->analyzeMessage($text);
        
        $this->assertEquals('none', $result['crisis_level']);
        $this->assertTrue($result['hyperbole_detected']);
    }
    
    /**
     * Test: Genuine crisis should still be detected
     */
    public function test_genuine_crisis_detected()
    {
        $text = "I can't take it anymore. I want to end this.";
        $result = $this->crisisService->analyzeMessage($text);
        
        $this->assertNotEquals('none', $result['crisis_level']);
        $this->assertGreaterThan(0.6, $result['crisis_confidence']);
    }
    
    /**
     * Test: Explicit denial should reduce crisis confidence
     */
    public function test_negation_reduces_crisis_confidence()
    {
        $text = "I'm NOT going to hurt myself.";
        $result = $this->crisisService->analyzeMessage($text);
        
        // Should be low confidence, not high crisis
        $this->assertLessThan(0.5, $result['crisis_confidence']);
    }
    
    /**
     * Test: Intent detection should escalate to critical
     */
    public function test_intent_escalates_severity()
    {
        $text = "I think I'm going to kill myself.";
        $result = $this->crisisService->analyzeMessage($text);
        
        $this->assertTrue($result['intent_detected']);
        $this->assertEquals('critical', $result['crisis_level']);
        $this->assertGreaterThan(0.8, $result['crisis_confidence']);
    }
    
    /**
     * Test: Plan + method + access = imminent danger
     */
    public function test_plan_method_access_imminent_danger()
    {
        $text = "I found my dad's gun. I know exactly how I'm going to do it tonight.";
        $result = $this->crisisService->analyzeMessage($text);
        
        $this->assertTrue($result['plan_detected']);
        $this->assertTrue($result['method_detected']);
        $this->assertTrue($result['access_detected']);
        $this->assertTrue($result['temporal_urgency'] >= 0.8);
        $this->assertEquals('critical', $result['crisis_level']);
        $this->assertGreaterThan(0.95, $result['crisis_confidence']);
    }
    
    /**
     * Test: Historical crisis + relapse = escalate concern
     */
    public function test_relapse_escalates_concern()
    {
        $text = "I thought I was past this, but those suicidal thoughts are back.";
        $result = $this->crisisService->analyzeMessage($text);
        
        $this->assertTrue($result['recurrence_detected']);
        $this->assertGreaterThanOrEqual('moderate', $result['crisis_level']);
    }
    
    /**
     * Test: Rhetorical question should not be immediate crisis
     */
    public function test_rhetorical_question_detected()
    {
        $text = "What's the point of living?";
        $result = $this->crisisService->analyzeMessage($text);
        
        // Should be moderate/none, not critical
        $this->assertNotEquals('critical', $result['crisis_level']);
    }
    
    /**
     * Test: Immediate temporal markers increase urgency
     */
    public function test_immediate_temporal_urgency()
    {
        $text = "I'm going to do it tonight.";
        $result = $this->crisisService->analyzeMessage($text);
        
        // Even without explicit suicide keyword, "tonight" should increase urgency
        // (though this alone won't trigger crisis without explicit keywords)
        $this->assertGreaterThan(0, $result['temporal_urgency']);
    }
    
    /**
     * Test: Wellbeing score calculation with positive emotion
     */
    public function test_wellbeing_score_with_happy_emotion()
    {
        $emotionData = [
            'emotion' => 'happy',
            'intensity' => 0.8,
            'confidence' => 0.9
        ];
        
        $crisisData = [
            'level' => 'none',
            'confidence' => 0
        ];
        
        $metrics = $this->wellbeingCalculator->calculateWellbeing(1, $emotionData, $crisisData);
        
        // Happy emotion should increase wellbeing score
        $this->assertGreaterThan(60, $metrics['wellbeing_score']);
        $this->assertLessThan(100, $metrics['wellbeing_score']);
    }
    
    /**
     * Test: Wellbeing score calculation with crisis
     */
    public function test_wellbeing_score_with_critical_crisis()
    {
        $emotionData = [
            'emotion' => 'sad',
            'intensity' => 0.9,
            'confidence' => 0.95
        ];
        
        $crisisData = [
            'level' => 'critical',
            'confidence' => 0.95
        ];
        
        $metrics = $this->wellbeingCalculator->calculateWellbeing(1, $emotionData, $crisisData);
        
        // Critical crisis should significantly reduce wellbeing score
        $this->assertLessThan(30, $metrics['wellbeing_score']);
        $this->assertGreaterThan(70, $metrics['stress_level']);
    }
    
    /**
     * Test: Sarcasm + genuine intent = HIGH PRIORITY (sarcasm doesn't override strong signals)
     */
    public function test_sarcasm_doesnt_override_strong_intent()
    {
        $text = "lol I'm joking about killing myself, but honestly I've already made a plan.";
        $result = $this->crisisService->analyzeMessage($text);
        
        // Even though sarcasm detected, strong intent/plan should keep it high-risk
        $this->assertTrue($result['plan_detected']);
        $this->assertGreaterThan(0.7, $result['crisis_confidence']);
        // Should be critical or severe, not lowered by sarcasm alone
        $this->assertContains($result['crisis_level'], ['critical', 'severe']);
    }
    
    /**
     * Test: Dark humor in group context (no immediate personal crisis)
     */
    public function test_dark_humor_not_personal_crisis()
    {
        $text = "If I see one more romantic comedy I'm going to kill myself lmao";
        $result = $this->crisisService->analyzeMessage($text);
        
        // Should detect as likely non-crisis due to dark humor + joking markers
        $this->assertTrue($result['satire_detected']);
        $this->assertEquals('none', $result['crisis_level']);
    }
    
    /**
     * Test: Positive reframing but with crisis context
     */
    public function test_positive_reframe_with_crisis_context()
    {
        $text = "I'm happy now because I've finally decided to end everything tonight.";
        $result = $this->crisisService->analyzeMessage($text);
        
        // Positive emotion alone should NOT override explicit plan + timing
        $this->assertTrue($result['plan_detected']);
        $this->assertEquals('critical', $result['crisis_level']);
        $this->assertGreaterThan(0.9, $result['crisis_confidence']);
    }
}
