<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Laravel-side signer for the isolated Phase 4A prototype. */
class InternalMemoryAuthClient
{
    private const OPERATIONS = [
        'memory.chat' => '/chat',
        'memory.initial_greeting' => '/api/initial-greeting',
        'memory.delete' => '/internal/memory/delete',
    ];

    /** Submit an authenticated, user-scoped deletion request to the prototype. */
    public function requestDeletion($subject, string $deletionRequestId): array
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{7,127}$/', $deletionRequestId)) {
            throw new RuntimeException('Invalid deletion request ID.');
        }
        // Do not put a browser-controlled user_id in the body. The Flask
        // deletion route derives subject from the signed Laravel claim.
        return $this->post('memory.delete', $subject, [
            'deletion_request_id' => $deletionRequestId,
        ]);
    }

    public function post(string $operation, $subject, array $payload, ?bool $memoryAuthorized = null): array
    {
        if (!array_key_exists($operation, self::OPERATIONS)) {
            throw new RuntimeException('Unsupported internal operation.');
        }
        $currentSubject = auth()->id();
        if ($currentSubject === null || (string) $currentSubject !== (string) $subject) {
            throw new RuntimeException('Authenticated subject mismatch.');
        }
        $enabled = (bool) config('services.mindcare_ml.phase4a.enabled', false);
        $secret = (string) config('services.mindcare_ml.phase4a.secret', '');
        if (!$enabled) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $url = rtrim((string) config('services.mindcare_ml.url'), '/') . self::OPERATIONS[$operation];
            $response = Http::connectTimeout((int) config('services.mindcare_ml.chat.connect_timeout', config('services.mindcare_ml.phase4a.connect_timeout', 5)))
                ->timeout((int) config('services.mindcare_ml.chat.request_timeout', config('services.mindcare_ml.phase4a.timeout', 15)))
                ->withBody($body, 'application/json')
                ->send('POST', $url);
            if (!$response->successful()) {
                $this->throwForFailure($response, 'ML service unavailable.');
            }
            $decoded = $response->json();
            if (!is_array($decoded)) {
                throw new RuntimeException('ML service returned an invalid response.');
            }
            return $decoded;
        }
        if ($secret === '') {
            // A secure Laravel configuration must never silently downgrade to
            // an unsigned Flask request.  Surface the misconfiguration so the
            // caller reports the AI service as unavailable instead of sending
            // a request that Flask will reject before its handler runs.
            throw new RuntimeException('Internal ML authentication is enabled but no HMAC secret is configured.');
        }
        // Phase 4B authority: ignore caller/browser flags and evaluate current
        // authenticated consent, including policy version and revision.
        $consent = app(MemoryConsentService::class)->decisionForAuthenticatedSubject($subject);
        $memoryAuthorized = $consent->allowsMemory();
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $issuedAt = time();
        $ttl = max(1, (int) config('services.mindcare_ml.phase4a.ttl', 60));
        $keyId = (string) config('services.mindcare_ml.phase4a.key_id', 'phase4a-dev');
        $issuer = (string) config('services.mindcare_ml.phase4a.issuer', 'laravel-phase4a');
        $audience = (string) config('services.mindcare_ml.phase4a.audience', 'mindcare-flask-phase4a');
        $requestId = bin2hex(random_bytes(16));
        $bodyDigest = hash('sha256', $body);
        $header = ['alg' => 'HS256', 'typ' => 'MINDCARE-HMAC', 'kid' => $keyId];
        $claims = [
            'iss' => $issuer,
            'aud' => $audience,
            'sub' => (string) $subject,
            'op' => $operation,
            'method' => 'POST',
            'path' => self::OPERATIONS[$operation],
            'iat' => $issuedAt,
            'exp' => $issuedAt + $ttl,
            'jti' => $requestId,
            'purpose' => 'internal-memory-operation',
            'body_sha256' => $bodyDigest,
            'memory_authorized' => (bool) $memoryAuthorized,
            'consent_purpose' => $consent->purpose,
            'consent_state' => $consent->state,
            'consent_policy_version' => $consent->policyVersion,
            'consent_revision' => $consent->revision,
        ];
        $headerSegment = $this->base64Url(json_encode($header, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $claimsSegment = $this->base64Url(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $signingInput = "v1.{$keyId}.{$headerSegment}.{$claimsSegment}.{$bodyDigest}";
        $signature = $this->base64Url(hash_hmac('sha256', $signingInput, $secret, true));
        $token = "v1.{$keyId}.{$headerSegment}.{$claimsSegment}.{$signature}";
        $url = rtrim((string) config('services.mindcare_ml.url'), '/') . self::OPERATIONS[$operation];
        $response = Http::withHeaders([
                'Authorization' => 'MindCare-HMAC ' . $token,
                'Accept' => 'application/json',
            ])
            ->connectTimeout((int) config('services.mindcare_ml.chat.connect_timeout', config('services.mindcare_ml.phase4a.connect_timeout', 5)))
            ->timeout((int) config('services.mindcare_ml.chat.request_timeout', config('services.mindcare_ml.phase4a.timeout', 15)))
            ->withBody($body, 'application/json')
            ->send('POST', $url);
        if (!$response->successful()) {
            $this->throwForFailure($response, 'Internal ML service unavailable.');
        }
        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new RuntimeException('Internal ML service returned an invalid response.');
        }
        return $decoded;
    }

    public function memoryAuthorizedFor($subject): bool
    {
        if (!(bool) config('services.mindcare_ml.phase4a.enabled', false)
            || (string) config('services.mindcare_ml.phase4a.secret', '') === '') {
            return false;
        }
        return app(MemoryConsentService::class)
            ->decisionForAuthenticatedSubject($subject)
            ->allowsMemory();
    }

    private function throwForFailure($response, string $fallback): void
    {
        $payload = $response->json();
        $queueCode = is_array($payload) ? (string) ($payload['code'] ?? '') : '';
        if (in_array($queueCode, ['GPU_QUEUE_BUSY', 'GPU_REQUEST_IN_PROGRESS'], true)) {
            $status = (int) $response->status();
            $retryAfter = is_array($payload) ? (int) ($payload['retry_after_seconds'] ?? 5) : 5;
            throw new MlServiceBusyException($queueCode, $status, max(1, min(60, $retryAfter)));
        }

        throw new RuntimeException($fallback);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
