<?php

namespace Tests\Unit\Services;

use App\Services\EmotionDetectionService;
use Tests\TestCase;

class MlEndpointConfigurationTest extends TestCase
{
    public function test_emotion_service_uses_configured_flask_instance(): void
    {
        config()->set('services.mindcare_ml.url', 'http://127.0.0.1:5002/');

        $service = app(EmotionDetectionService::class);
        $method = new \ReflectionMethod($service, 'mlApiUrl');
        $method->setAccessible(true);

        $this->assertSame('http://127.0.0.1:5002/analyze', $method->invoke($service, 'analyze'));
        $this->assertSame('http://127.0.0.1:5002/health', $method->invoke($service, '/health'));
    }

    public function test_emotion_service_keeps_port_5000_as_development_default(): void
    {
        config()->set('services.mindcare_ml.url', 'http://127.0.0.1:5000');

        $service = app(EmotionDetectionService::class);
        $method = new \ReflectionMethod($service, 'mlApiUrl');
        $method->setAccessible(true);

        $this->assertSame('http://127.0.0.1:5000/generate_response', $method->invoke($service, 'generate_response'));
    }
}
