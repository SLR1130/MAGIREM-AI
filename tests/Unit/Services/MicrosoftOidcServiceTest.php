<?php

namespace Tests\Unit\Services;

use App\Services\MicrosoftOidcService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MicrosoftOidcServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT = '11111111-2222-4333-8444-555555555555';
    private const CLIENT = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

    private MicrosoftOidcService $service;
    private mixed $privateKey;
    private array $jwk;
    private string $jwksUri;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.microsoft.enabled' => true,
            'services.microsoft.tenant_id' => self::TENANT,
            'services.microsoft.client_id' => self::CLIENT,
            'services.microsoft.client_secret' => 'unit-test-secret',
            'services.microsoft.institutional_domain' => 'minsu.edu.ph',
        ]);
        Cache::flush();
        $this->jwksUri = 'https://login.microsoftonline.com/' . self::TENANT . '/discovery/v2.0/keys';
        $this->privateKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        if (!$this->privateKey) {
            $this->markTestSkipped('PHP OpenSSL key generation is unavailable; set OPENSSL_CONF to tests/Fixtures/openssl.cnf before running these crypto tests.');
        }
        $details = openssl_pkey_get_details($this->privateKey);
        $this->jwk = [
            'kid' => 'synthetic-test-key',
            'kty' => 'RSA',
            'use' => 'sig',
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ];
        Http::fake([$this->jwksUri => Http::response(['keys' => [$this->jwk]], 200)]);
        $this->service = app(MicrosoftOidcService::class);
    }

    public function test_valid_single_tenant_signed_identity_token_is_accepted(): void
    {
        $identity = $this->service->validateIdToken($this->token($this->claims()), ['jwks_uri' => $this->jwksUri], 'expected-nonce');

        $this->assertSame(self::TENANT, $identity['tenant_id']);
        $this->assertSame('bbbbbbbb-cccc-4ddd-8eee-ffffffffffff', $identity['object_id']);
        $this->assertSame('synthetic.student@minsu.edu.ph', $identity['email']);
        $this->assertSame('Synthetic Student', $identity['name']);
    }

    public function test_wrong_issuer_audience_tenant_nonce_expiry_and_domain_are_rejected(): void
    {
        $metadata = ['jwks_uri' => $this->jwksUri];
        $invalidClaims = [
            ['iss' => 'https://login.microsoftonline.com/common/v2.0'],
            ['aud' => 'another-client'],
            ['azp' => 'another-client'],
            ['aud' => [self::CLIENT, 'another-client'], 'azp' => 'another-client'],
            ['tid' => '22222222-3333-4444-8555-666666666666'],
            ['nonce' => 'another-nonce'],
            ['exp' => now()->subMinute()->timestamp],
            ['preferred_username' => 'student@gmail.com'],
            ['preferred_username' => 'not-an-email@minsu.edu.ph'],
        ];

        foreach ($invalidClaims as $changes) {
            try {
                $this->service->validateIdToken($this->token(array_replace($this->claims(), $changes)), $metadata, 'expected-nonce');
                $this->fail('An invalid identity token was accepted: ' . implode(',', array_keys($changes)));
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_microsoft_registration_routes_are_not_exposed_by_the_native_authentication_flow(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('microsoft.redirect'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('microsoft.callback'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_unsupported_algorithm_and_tampered_signature_are_rejected(): void
    {
        $claims = $this->claims();
        $metadata = ['jwks_uri' => $this->jwksUri];
        $unsupported = $this->token($claims, 'none');
        $this->assertRejected($unsupported, $metadata);

        $valid = $this->token($claims);
        $parts = explode('.', $valid);
        $parts[1] = $this->base64UrlEncode(json_encode(array_replace($claims, ['name' => 'Tampered']), JSON_THROW_ON_ERROR));
        $this->assertRejected(implode('.', $parts), $metadata);
    }

    public function test_untrusted_jwks_hosts_are_rejected(): void
    {
        $this->assertRejected($this->token($this->claims()), ['jwks_uri' => 'https://attacker.invalid/keys']);
    }

    private function assertRejected(string $jwt, array $metadata): void
    {
        try {
            $this->service->validateIdToken($jwt, $metadata, 'expected-nonce');
            $this->fail('Invalid identity token was accepted.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }
    }

    private function claims(): array
    {
        return [
            'iss' => 'https://login.microsoftonline.com/' . self::TENANT . '/v2.0',
            'aud' => self::CLIENT,
            'tid' => self::TENANT,
            'nonce' => 'expected-nonce',
            'iat' => now()->timestamp,
            'nbf' => now()->subMinute()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
            'oid' => 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff',
            'preferred_username' => 'synthetic.student@minsu.edu.ph',
            'name' => 'Synthetic Student',
        ];
    }

    private function token(array $claims, string $algorithm = 'RS256'): string
    {
        $header = ['alg' => $algorithm, 'kid' => 'synthetic-test-key', 'typ' => 'JWT'];
        $input = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)) . '.'
            . $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        openssl_sign($input, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        return $input . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
