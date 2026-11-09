<?php
namespace Illuminate\Support\Facades {
    class FakeResponse {
        public function successful(): bool { return true; }
        public function json(): array { return ['success' => true, 'synthetic' => true]; }
    }
    class FakePendingRequest {
        public function connectTimeout($value): self { return $this; }
        public function timeout($value): self { return $this; }
        public function withBody($body, $contentType): self { Http::$body = $body; return $this; }
        public function post($url): FakeResponse { return $this->send('POST', $url); }
        public function send($method, $url): FakeResponse { Http::$method = strtoupper($method); Http::$url = $url; return new FakeResponse(); }
    }
    class Http {
        public static array $headers = [];
        public static string $body = '';
        public static string $url = '';
        public static string $method = '';
        public static function withHeaders(array $headers): FakePendingRequest { self::$headers = $headers; return new FakePendingRequest(); }
    }
}
namespace {
    function auth() { return new class { public function id() { return 42; } }; }
    function app($class) {
        return new class {
            public function decisionForAuthenticatedSubject($subject) {
                return new class {
                    public string $purpose = 'long_term_memory';
                    public string $policyVersion = 'synthetic-policy-v1';
                    public string $state = 'not_granted';
                    public int $revision = 0;
                    public function allowsMemory(): bool { return false; }
                };
            }
        };
    }
    function config($key, $default = null) {
        $values = [
            'services.mindcare_ml.phase4a.enabled' => true,
            'services.mindcare_ml.phase4a.secret' => 'phase4a-php-harness-secret',
            'services.mindcare_ml.phase4a.ttl' => 60,
            'services.mindcare_ml.phase4a.key_id' => 'phase4a-php',
            'services.mindcare_ml.phase4a.issuer' => 'laravel-phase4a',
            'services.mindcare_ml.phase4a.audience' => 'mindcare-flask-phase4a',
            'services.mindcare_ml.phase4a.connect_timeout' => 1,
            'services.mindcare_ml.phase4a.timeout' => 2,
            'services.mindcare_ml.url' => 'http://127.0.0.1:5014',
        ];
        return $values[$key] ?? $default;
    }
    require __DIR__ . '/../app/Services/InternalMemoryAuthClient.php';
    $client = new \App\Services\InternalMemoryAuthClient();
    $body = ['user_id' => 42, 'message' => 'synthetic'];
    $result = $client->post('memory.chat', 42, $body, false);
    $token = \Illuminate\Support\Facades\Http::$headers['Authorization'];
    $encoded = substr($token, strlen('MindCare-HMAC '));
    $parts = explode('.', $encoded);
    $claims = json_decode(base64_decode(strtr($parts[3], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
    $expectedDigest = hash('sha256', \Illuminate\Support\Facades\Http::$body);
    if ($claims['sub'] !== '42' || $claims['op'] !== 'memory.chat' || $claims['method'] !== 'POST' || $claims['path'] !== '/chat' || $claims['body_sha256'] !== $expectedDigest || \Illuminate\Support\Facades\Http::$method !== 'POST' || \Illuminate\Support\Facades\Http::$url !== 'http://127.0.0.1:5014/chat') {
        throw new \RuntimeException('PHP signer claim verification failed');
    }
    echo "PHP_SIGNER_OK\n";
    echo "PHP_TOKEN=" . $encoded . "\n";
    echo "PHP_REQUEST_URL=" . \Illuminate\Support\Facades\Http::$url . "\n";
    echo "PHP_RESULT_SUCCESS=" . ($result['success'] ? 'true' : 'false') . "\n";
}
