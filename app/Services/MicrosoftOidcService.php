<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MicrosoftOidcService
{
    public function isConfigured(): bool
    {
        $config = config('services.microsoft');
        return (bool) ($config['enabled'] ?? false)
            && $this->isTenantId((string) ($config['tenant_id'] ?? ''))
            && filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null);
    }

    public function authorizationUrl(Request $request): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Microsoft institutional sign-in is not configured.');
        }

        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $request->session()->put('microsoft_oidc', [
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'issued_at' => now()->timestamp,
        ]);
        $request->session()->forget('microsoft_registration_identity');

        $tenant = rawurlencode((string) config('services.microsoft.tenant_id'));
        $params = http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'response_type' => 'code',
            'response_mode' => 'query',
            'redirect_uri' => $this->redirectUri(),
            'scope' => 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?{$params}";
    }

    public function identityFromCallback(Request $request): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Microsoft institutional sign-in is not configured.');
        }

        $flow = $request->session()->pull('microsoft_oidc');
        $providedState = (string) $request->query('state', '');
        if (!is_array($flow) || !isset($flow['state'], $flow['nonce'], $flow['verifier'], $flow['issued_at'])
            || $providedState === '' || !hash_equals((string) $flow['state'], $providedState)
            || (int) $flow['issued_at'] < now()->subMinutes(10)->timestamp
            || (int) $flow['issued_at'] > now()->addSeconds(60)->timestamp
        ) {
            throw new RuntimeException('The Microsoft sign-in state is invalid or expired.');
        }
        if ($request->filled('error') || !$request->filled('code')) {
            throw new RuntimeException('Microsoft sign-in was not completed.');
        }

        $metadata = $this->metadata();
        $tokenResponse = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(15)->post($metadata['token_endpoint'], [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => (string) $request->query('code'),
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => $flow['verifier'],
            'scope' => 'openid profile email',
        ])->throw()->json();

        $idToken = (string) ($tokenResponse['id_token'] ?? '');
        if ($idToken === '') {
            throw new RuntimeException('Microsoft did not return an identity token.');
        }

        return $this->validateIdToken($idToken, $metadata, (string) $flow['nonce']);
    }

    /** Public for isolated cryptographic tests; tokens are never persisted or logged. */
    public function validateIdToken(string $jwt, array $metadata, string $expectedNonce): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed Microsoft identity token.');
        }
        [$encodedHeader, $encodedClaims, $encodedSignature] = $parts;
        $header = json_decode($this->decodeBase64Url($encodedHeader), true, 16, JSON_THROW_ON_ERROR);
        $claims = json_decode($this->decodeBase64Url($encodedClaims), true, 32, JSON_THROW_ON_ERROR);
        if (($header['alg'] ?? null) !== 'RS256' || empty($header['kid']) || !is_array($claims)) {
            throw new RuntimeException('Unsupported Microsoft identity token signature.');
        }

        $jwks = $this->jwks((string) ($metadata['jwks_uri'] ?? ''));
        $jwk = collect($jwks['keys'] ?? [])->first(function ($key) use ($header): bool {
            if (!is_array($key)
                || ($key['kid'] ?? null) !== $header['kid']
                || ($key['kty'] ?? null) !== 'RSA'
                || (isset($key['use']) && $key['use'] !== 'sig')
                || (isset($key['alg']) && $key['alg'] !== 'RS256')
            ) {
                return false;
            }

            return !isset($key['key_ops'])
                || (is_array($key['key_ops']) && in_array('verify', $key['key_ops'], true));
        });
        if (!is_array($jwk) || empty($jwk['n']) || empty($jwk['e'])) {
            throw new RuntimeException('Microsoft signing key was not found.');
        }

        $publicKey = openssl_pkey_get_public($this->jwkToPem($jwk));
        $signature = $this->decodeBase64Url($encodedSignature);
        $verified = $publicKey && openssl_verify($encodedHeader . '.' . $encodedClaims, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
        if (!$verified) {
            throw new RuntimeException('Microsoft identity token signature did not validate.');
        }

        $tenantId = strtolower((string) config('services.microsoft.tenant_id'));
        $expectedIssuer = 'https://login.microsoftonline.com/' . $tenantId . '/v2.0';
        $now = now()->timestamp;
        $audiences = (array) ($claims['aud'] ?? []);
        $clientId = (string) config('services.microsoft.client_id');
        $authorizedParty = $claims['azp'] ?? null;
        $issuedAt = (int) ($claims['iat'] ?? 0);
        if (($claims['iss'] ?? null) !== $expectedIssuer
            || !in_array($clientId, $audiences, true)
            || (count($audiences) > 1 && !is_string($authorizedParty))
            || ($authorizedParty !== null && !hash_equals($clientId, (string) $authorizedParty))
            || strtolower((string) ($claims['tid'] ?? '')) !== $tenantId
            || !hash_equals($expectedNonce, (string) ($claims['nonce'] ?? ''))
            || (int) ($claims['exp'] ?? 0) <= $now
            || (isset($claims['nbf']) && (int) $claims['nbf'] > $now + 60)
            || $issuedAt < $now - 3600 || $issuedAt > $now + 60
        ) {
            throw new RuntimeException('Microsoft identity claims were invalid for this application or tenant.');
        }

        $objectId = strtolower((string) ($claims['oid'] ?? ''));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $objectId)) {
            throw new RuntimeException('Microsoft identity did not include a stable tenant object ID.');
        }
        $email = strtolower(trim((string) ($claims['preferred_username'] ?? $claims['email'] ?? '')));
        $domain = strtolower((string) config('services.microsoft.institutional_domain', 'minsu.edu.ph'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@' . $domain)) {
            throw new RuntimeException('The verified Microsoft account is not in the configured institutional email domain.');
        }

        return [
            'tenant_id' => $tenantId,
            'object_id' => $objectId,
            'email' => $email,
            'name' => trim((string) ($claims['name'] ?? '')) ?: $email,
            'verified_at' => now()->toIso8601String(),
        ];
    }

    private function metadata(): array
    {
        $tenant = strtolower((string) config('services.microsoft.tenant_id'));
        $metadata = Cache::remember('microsoft.oidc.discovery.' . $tenant, now()->addHour(), function () use ($tenant) {
            return Http::acceptJson()->connectTimeout(5)->timeout(10)
                ->get("https://login.microsoftonline.com/{$tenant}/v2.0/.well-known/openid-configuration")
                ->throw()->json();
        });

        $issuer = 'https://login.microsoftonline.com/' . $tenant . '/v2.0';
        foreach (['issuer', 'token_endpoint', 'jwks_uri'] as $key) {
            if (empty($metadata[$key])) {
                throw new RuntimeException('Microsoft OpenID configuration is incomplete.');
            }
        }
        if ($metadata['issuer'] !== $issuer) {
            throw new RuntimeException('Microsoft OpenID issuer did not match the configured tenant.');
        }
        foreach (['token_endpoint', 'jwks_uri'] as $key) {
            $url = parse_url((string) $metadata[$key]);
            if (($url['scheme'] ?? '') !== 'https' || strtolower((string) ($url['host'] ?? '')) !== 'login.microsoftonline.com') {
                throw new RuntimeException('Microsoft OpenID endpoint host is not trusted.');
            }
        }

        return $metadata;
    }

    private function jwks(string $uri): array
    {
        $parsed = parse_url($uri);
        if (($parsed['scheme'] ?? '') !== 'https' || strtolower((string) ($parsed['host'] ?? '')) !== 'login.microsoftonline.com') {
            throw new RuntimeException('Microsoft signing-key endpoint is not trusted.');
        }
        return Cache::remember('microsoft.oidc.jwks.' . sha1($uri), now()->addHour(), function () use ($uri) {
            return Http::acceptJson()->connectTimeout(5)->timeout(10)->get($uri)->throw()->json();
        });
    }

    private function redirectUri(): string
    {
        return (string) (config('services.microsoft.redirect_uri') ?: route('microsoft.callback'));
    }

    private function isTenantId(string $tenant): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tenant);
    }

    private function decodeBase64Url(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new RuntimeException('Malformed Microsoft token encoding.');
        }
        return $decoded;
    }

    private function jwkToPem(array $jwk): string
    {
        $modulus = $this->decodeBase64Url((string) $jwk['n']);
        $exponent = $this->decodeBase64Url((string) $jwk['e']);
        $rsaPublicKey = $this->derSequence($this->derInteger($modulus) . $this->derInteger($exponent));
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        $bitString = "\x03" . $this->derLength(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey;
        $subjectPublicKeyInfo = $this->derSequence($algorithm . $bitString);
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private function derInteger(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '' || (ord($value[0]) & 0x80)) {
            $value = "\x00" . $value;
        }
        return "\x02" . $this->derLength(strlen($value)) . $value;
    }

    private function derSequence(string $value): string
    {
        return "\x30" . $this->derLength(strlen($value)) . $value;
    }

    private function derLength(int $length): string
    {
        if ($length < 0x80) return chr($length);
        $bytes = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
