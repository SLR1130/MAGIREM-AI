<?php

namespace Tests\Unit\Services;

use App\Http\Controllers\AIChatController;
use ReflectionMethod;
use Tests\TestCase;

class CounselorReferralRequestDetectionTest extends TestCase
{
    public function test_direct_request_to_connect_with_a_counselor_is_recognized(): void
    {
        $controller = app(AIChatController::class);
        $method = new ReflectionMethod($controller, 'isExplicitCounselorRequest');

        $this->assertTrue($method->invoke(
            $controller,
            "Bro, I've been struggling emotionally for weeks. Can you help me connect with a counselor from our university?"
        ));
    }
}