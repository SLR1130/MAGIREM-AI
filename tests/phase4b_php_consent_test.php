<?php
namespace Illuminate\Support\Facades {
    class FakeResponse { public function __construct(private bool $ok) {} public function successful(): bool { return $this->ok; } public function json(): array { return ['success'=>true]; } }
    class FakePending { public function connectTimeout($v): self { return $this; } public function timeout($v): self { return $this; } public function withBody($body,$type): self { Http::$body=$body; return $this; } public function post($url): FakeResponse { Http::$url=$url; return new FakeResponse(!Http::$fail); } }
    class Http { public static array $headers=[]; public static string $body=''; public static string $url=''; public static bool $fail=false; public static function withHeaders(array $h): FakePending { self::$headers=$h; return new FakePending(); } }
}
namespace {
    $syntheticSubject = 7;
    $service = null;
    function auth() { global $syntheticSubject; return new class($syntheticSubject) { public function __construct(private $id) {} public function id() { return $this->id; } }; }
    function config($key, $default = null) {
        $values = [
            'services.mindcare_ml.phase4a.enabled' => true,
            'services.mindcare_ml.phase4a.secret' => 'phase4b-php-secret',
            'services.mindcare_ml.phase4a.ttl' => 60,
            'services.mindcare_ml.phase4a.key_id' => 'phase4b-php',
            'services.mindcare_ml.phase4a.issuer' => 'laravel-phase4b',
            'services.mindcare_ml.phase4a.audience' => 'mindcare-flask-phase4b',
            'services.mindcare_ml.phase4a.connect_timeout' => 1,
            'services.mindcare_ml.phase4a.timeout' => 2,
            'services.mindcare_ml.url' => 'http://127.0.0.1:5014',
            'services.mindcare_ml.phase4b.consent_db' => 'C:\\tmp\\mindcare-phase4b-php-' . bin2hex(random_bytes(6)) . '.db',
            'services.mindcare_ml.phase4b.policy_version' => 'synthetic-policy-v1',
        ];
        return $values[$key] ?? $default;
    }
    function app($class) { global $service; if ($service === null) $service = new \App\Services\MemoryConsentService(); return $service; }
    require __DIR__ . '/../app/Services/MemoryConsentService.php';
    require __DIR__ . '/../app/Services/InternalMemoryAuthClient.php';
    $service = new \App\Services\MemoryConsentService(config('services.mindcare_ml.phase4b.consent_db'), 'synthetic-policy-v1');
    $before = $service->decisionForAuthenticatedSubject(7);
    if ($before->state !== 'not_granted' || $before->allowsMemory()) throw new \RuntimeException('default consent failed');
    $granted = $service->setStateForAuthenticatedSubject('granted');
    $client = new \App\Services\InternalMemoryAuthClient();
    $client->post('memory.chat', 7, ['user_id'=>7,'message'=>'synthetic'], true);
    $token = substr(\Illuminate\Support\Facades\Http::$headers['Authorization'], strlen('MindCare-HMAC '));
    $parts = explode('.', $token);
    $claims = json_decode(base64_decode(strtr($parts[3], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
    if ($claims['memory_authorized'] !== true || $claims['consent_state'] !== 'granted' || (int)$claims['consent_revision'] !== $granted->revision) throw new \RuntimeException('granted claim failed');
    $revoked = $service->setStateForAuthenticatedSubject('revoked');
    $client->post('memory.chat', 7, ['user_id'=>7,'message'=>'synthetic'], true);
    $token2 = substr(\Illuminate\Support\Facades\Http::$headers['Authorization'], strlen('MindCare-HMAC '));
    $claims2 = json_decode(base64_decode(strtr(explode('.', $token2)[3], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
    if ($claims2['memory_authorized'] !== false || $claims2['consent_state'] !== 'revoked' || (int)$claims2['consent_revision'] !== $revoked->revision) throw new \RuntimeException('revocation claim failed');
    $syntheticSubject = 8;
    try { $client->post('memory.chat', 7, ['user_id'=>7,'message'=>'synthetic'], true); throw new \RuntimeException('subject mismatch accepted'); } catch (\RuntimeException $e) { if ($e->getMessage() !== 'Authenticated subject mismatch.') throw $e; }
    $syntheticSubject = 7;
    \Illuminate\Support\Facades\Http::$fail = true;
    try { $client->post('memory.chat', 7, ['user_id'=>7,'message'=>'synthetic'], true); throw new \RuntimeException('service failure was not surfaced'); } catch (\RuntimeException $e) { if ($e->getMessage() !== 'Internal ML service unavailable.') throw $e; }
    echo "PHP_CONSENT_TESTS_OK\n";
}

