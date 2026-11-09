<?php

namespace App\Services;

use App\Models\PrivacySetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class InferenceBridgeClient
{
    public function crisis(array $payload): array
    {
        return $this->post('/internal/crisis-assessment', $payload, (int) config('services.mindcare_ml.inference_bridge.request_timeout', 100));
    }

    /** Dedicated non-chat transport; callers must perform the consent check. */
    public function resourceRecommendations(array $payload): array
    {
        $url = parse_url((string) config('services.mindcare_ml.inference_bridge.url'));
        if (!is_array($url) || !in_array(strtolower((string) ($url['host'] ?? '')), ['127.0.0.1', 'localhost', '::1', '[::1]'], true)
            || ($url['scheme'] ?? '') !== 'http') {
            throw new RuntimeException('Recommendations require a local inference bridge.');
        }

        return $this->post('/internal/magi-resource-recommendations', $payload, 70);
    }

    /**
     * Run the optional 35-label interpreter for an explicitly consented local
     * pilot. Consent is read from Laravel's PrivacySetting record; callers
     * cannot opt themselves in by posting a boolean to the Flask endpoint.
     * The result is deliberately not persisted by this client.
     */
    public function detailedEmotionShadow(int $userId, string $message, string $requestId): ?array
    {
        if (!config('services.mindcare_ml.detailed_emotion_shadow.enabled', false)
            || !config('services.mindcare_ml.detailed_emotion_shadow.approved', false)) {
            return null;
        }

        $settings = PrivacySetting::getOrCreateForUser($userId);
        // Both current AI and analytics consent are required for a shadow
        // interpretation. This is a server-side decision, never a request-body claim.
        if (!$settings->canUseAI() || !$settings->hasCurrentConsent('share_analytics')) {
            return null;
        }

        $aiConsentAt = $settings->ai_recommendations_consented_at?->getTimestamp() ?? 0;
        $analyticsConsentAt = $settings->analytics_consented_at?->getTimestamp() ?? 0;
        $consentRevision = max($aiConsentAt, $analyticsConsentAt);
        if ($consentRevision < 1) {
            return null;
        }

        return $this->post('/internal/detailed-emotion-shadow', [
            'request_id' => $requestId,
            'user_id' => (string) $userId,
            'message' => $message,
            'consent_granted' => true,
            'consent_purpose' => (string) config('services.mindcare_ml.detailed_emotion_shadow.purpose', 'emotion_shadow_evaluation'),
            'consent_revision' => $consentRevision,
        ], max(2, min(15, (int) config('services.mindcare_ml.detailed_emotion_shadow.timeout', 15))));
    }

    private function post(string $path, array $payload, int $timeout): array
    {
        $secret = (string) config('services.mindcare_ml.inference_bridge.secret', '');
        if (strlen($secret) < 32) {
            throw new RuntimeException('Inference bridge authentication is not configured.');
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $requestId = (string) ($payload['request_id'] ?? '');
        $timestamp = (string) time();
        $digest = hash('sha256', $body);
        $lineBreak = chr(10);
        $canonical = 'v1' . $lineBreak . 'POST' . $lineBreak . $path . $lineBreak . $requestId . $lineBreak . $timestamp . $lineBreak . $digest;
        $signature = hash_hmac('sha256', $canonical, $secret);

        $response = Http::connectTimeout((int) config('services.mindcare_ml.inference_bridge.connect_timeout', 5))
            ->timeout($timeout)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-MindCare-Inference-Request-ID' => $requestId,
                'X-MindCare-Inference-Timestamp' => $timestamp,
                'X-MindCare-Inference-Signature' => $signature,
            ])
            ->withBody($body, 'application/json')
            ->send('POST', rtrim((string) config('services.mindcare_ml.inference_bridge.url'), '/\\') . $path);

        if (!$response->successful()) {
            throw new RuntimeException('Inference bridge unavailable: HTTP ' . $response->status());
        }

        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new RuntimeException('Inference bridge returned an invalid response.');
        }

        if (($decoded['success'] ?? true) === false) {
            throw new RuntimeException('Inference bridge rejected the request.');
        }

        return $decoded;
    }
}
