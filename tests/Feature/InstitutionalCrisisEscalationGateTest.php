<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CrisisAlert;
use App\Models\User;
use App\Services\CrisisDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionalCrisisEscalationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_institutional_authorization_withholds_all_disclosure(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        User::factory()->create(['role' => 'counselor', 'is_active' => true]);
        config()->set('privacy.crisis_safeguard_policy_version', null);
        config()->set('privacy.institutional_escalation.authorized_counselor_ids', []);

        $result = app(CrisisDetectionService::class)->handleCrisisDetection($student->id, [
            'crisis_level' => 'critical', 'crisis_category' => 'synthetic_test',
        ], 'Synthetic test message.', 'Synthetic safe response.');

        $this->assertNull($result);
        $this->assertSame(0, CrisisAlert::count());
        $this->assertSame(0, Appointment::count());
    }
}
