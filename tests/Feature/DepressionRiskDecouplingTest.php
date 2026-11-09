<?php

namespace Tests\Feature;

use App\Models\CrisisAlert;
use App\Models\EmotionalEntry;
use App\Models\User;
use App\Models\WellbeingProgress;
use App\Services\DepressionRiskService;
use App\Services\SituationWeightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepressionRiskDecouplingTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_emotion_aggregate_cannot_create_an_alert(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        foreach (range(0, 6) as $day) {
            EmotionalEntry::create([
                'user_id' => $student->id,
                'entry_text' => 'Synthetic historical test entry.',
                'detected_emotion' => 'suicidal',
                'crisis_level' => 'critical',
                'is_anonymous' => false,
                'created_at' => now()->subDays($day),
                'updated_at' => now()->subDays($day),
            ]);
        }

        $result = app(DepressionRiskService::class)->evaluateAfterEntry($student->id);

        $this->assertGreaterThanOrEqual(55, $result['score']);
        $this->assertFalse($result['alert_triggered']);
        $this->assertNull($result['alert_message']);
        $this->assertSame(0, CrisisAlert::count());
    }

    public function test_situation_weight_has_no_historical_depression_risk_factor(): void
    {
        $weight = app(SituationWeightService::class)->calculateWeight(
            'I feel sad after a difficult exam.',
            [],
            ['emotion' => 'sadness', 'intensity' => 0.4],
            ['crisis_level' => 'none']
        );

        $this->assertArrayNotHasKey('depression_risk', $weight['factors']);
        $this->assertSame('none', $weight['factors']['crisis_level']);
    }

    public function test_wellness_endpoint_returns_only_non_clinical_wellbeing_data(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        WellbeingProgress::create([
            'user_id' => $student->id,
            'date' => today(),
            'wellbeing_score' => 62,
            'stress_level' => 38,
            'emotional_stability' => 62,
        ]);

        $this->actingAs($student)
            ->getJson('/student/api/wellness-score')
            ->assertOk()
            ->assertJsonPath('score_type', 'non_clinical_wellbeing_indicator')
            ->assertJsonMissingPath('risk');
    }
}
