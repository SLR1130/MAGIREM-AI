<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\CounselorDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounselorDirectoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_counselors_are_directory_eligible(): void
    {
        $active = User::factory()->create(['role' => 'counselor', 'is_active' => true, 'specialization' => 'Academic support']);
        $inactive = User::factory()->create(['role' => 'counselor', 'is_active' => false]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $service = app(CounselorDirectoryService::class);
        $this->assertSame([$active->id], $service->eligibleCounselors()->pluck('id')->all());
        $this->assertTrue($service->isEligibleCounselor($active->id));
        $this->assertFalse($service->isEligibleCounselor($inactive->id));
        $this->assertFalse($service->isEligibleCounselor($student->id));
        $this->assertSame('eligible_counselors_available', $service->aiContext()['lookup_outcome']);
    }
}
