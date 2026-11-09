<?php

namespace App\Services;

use App\Models\AIConversationSession;
use App\Models\EmotionalEntry;
use App\Models\PrivacySetting;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/**
 * Private, non-diagnostic recommendations. Only a few recent student-authored
 * chat excerpts and active local catalog metadata go to the loopback bridge.
 * Journals, model scores, and hidden sessions are intentionally excluded.
 */
final class MagiResourceRecommendationService
{
    private const CACHE_TTL_SECONDS = 1800;

    public static function cacheKey(int $userId): string
    {
        return 'magi-resource-recommendations:v1:' . $userId;
    }

    public static function forgetForUser(int $userId): void
    {
        try {
            Cache::forget(self::cacheKey($userId));
        } catch (Throwable $ignored) {
            // Consent checks still block access if the cache is unreachable.
        }
    }

    public function get(User $user, bool $refresh = false): array
    {
        if (!auth()->check() || (int) auth()->id() !== (int) $user->id || !$user->isStudent() || !$user->isApproved()) {
            abort(403);
        }

        $key = self::cacheKey((int) $user->id);
        $settings = PrivacySetting::where('user_id', $user->id)->first();
        if (!$settings?->canUseAI()) {
            self::forgetForUser((int) $user->id);
            return ['state' => 'consent_required'];
        }
        $consentRevision = $settings->ai_recommendations_consented_at?->toISOString();
        $policyVersion = (string) $settings->consent_policy_version;

        $sessionIds = AIConversationSession::where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('is_hidden')->orWhere('is_hidden', false))
            ->select('id');
        $messages = EmotionalEntry::where('user_id', $user->id)
            ->whereIn('session_id', $sessionIds)
            ->where('created_at', '>=', now()->subDays(60))
            ->whereNotNull('entry_text')
            ->orderByDesc('created_at')->orderByDesc('id')->limit(8)
            ->get(['entry_text'])
            ->map(fn ($entry) => Str::limit(trim((string) $entry->entry_text), 240, ''))
            ->filter(fn ($text) => mb_strlen($text) >= 4)->values()->all();
        if (!$messages) {
            self::forgetForUser((int) $user->id);
            return ['state' => 'getting_to_know_you'];
        }

        $catalog = Resource::where('is_active', true)->orderBy('id')->limit(500)
            ->get(['id', 'title', 'description', 'category']);
        if ($catalog->isEmpty()) {
            self::forgetForUser((int) $user->id);
            return ['state' => 'no_resources'];
        }
        // Only preselect candidates to keep the local model's context bounded.
        // Lexical overlap is not presented as an inferred student topic; MAGI
        // still selects from the catalog and supplies evidence and reasons.
        $terms = collect($messages)->flatMap(function ($message) {
            preg_match_all('/[\pL\pN]{4,}/u', mb_strtolower($message), $matches);
            return $matches[0];
        })->unique()->take(120)->values()->all();
        $rankedCatalog = $catalog->sortByDesc(function ($resource) use ($terms) {
            $text = mb_strtolower($resource->title . ' ' . $resource->description . ' ' . $resource->category);
            return count(array_filter($terms, fn ($term) => mb_stripos($text, $term) !== false));
        })->take(30);
        $candidates = $rankedCatalog->map(fn ($resource) => [
            'id' => (int) $resource->id,
            'title' => Str::limit(trim(strip_tags((string) $resource->title)), 100, ''),
            'description' => Str::limit(trim(strip_tags((string) $resource->description)), 160, ''),
            'category' => Str::limit(trim(strip_tags((string) $resource->category)), 50, ''),
        ])->values()->all();
        $fingerprint = hash('sha256', json_encode([$consentRevision, $policyVersion, $messages, $candidates], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if (!$refresh) {
            try {
                $cached = Cache::get($key);
                if (is_string($cached)) {
                    $decoded = json_decode(Crypt::decryptString($cached), true, 32, JSON_THROW_ON_ERROR);
                    if (($decoded['fingerprint'] ?? null) === $fingerprint) {
                        if (($decoded['state'] ?? null) === 'unavailable') {
                            return ['state' => 'unavailable'];
                        }
                        return $this->validate($decoded['result'] ?? [], $messages, $catalog);
                    }
                }
            } catch (Throwable $e) {
                self::forgetForUser((int) $user->id);
            }
        }

        $lock = null;
        try {
            $lock = Cache::lock($key . ':lock', 90);
            if (!$lock->get()) {
                return ['state' => 'busy'];
            }
            $response = app(InferenceBridgeClient::class)->resourceRecommendations([
                'request_id' => (string) Str::uuid(),
                'messages' => $messages,
                'resources' => $candidates,
            ]);
            // Re-read the allowed IDs after inference so an unpublished item
            // cannot be returned merely because it was active at request start.
            $catalog = Resource::where('is_active', true)
                ->whereIn('id', array_column($candidates, 'id'))
                ->get(['id', 'title', 'description', 'category']);
            $result = $this->validate($response['recommendations'] ?? [], $messages, $catalog);

            // Recheck consent and its revision after the model returns; an
            // in-flight request must not expose or cache a revoked result.
            $current = PrivacySetting::where('user_id', $user->id)->first();
            if (!$current?->canUseAI()
                || $current->ai_recommendations_consented_at?->toISOString() !== $consentRevision
                || (string) $current->consent_policy_version !== $policyVersion) {
                self::forgetForUser((int) $user->id);
                return ['state' => 'consent_required'];
            }

            Cache::put($key, Crypt::encryptString(json_encode([
                'fingerprint' => $fingerprint,
                'result' => $response['recommendations'],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), now()->addSeconds(self::CACHE_TTL_SECONDS));
            return $result;
        } catch (Throwable $e) {
            // Never log private messages, model output, or exception payloads.
            try {
                $current = PrivacySetting::where('user_id', $user->id)->first();
                if (!$current?->canUseAI()) {
                    self::forgetForUser((int) $user->id);
                    return ['state' => 'consent_required'];
                }
                Cache::put($key, Crypt::encryptString(json_encode([
                    'fingerprint' => $fingerprint,
                    'state' => 'unavailable',
                ], JSON_THROW_ON_ERROR)), now()->addMinute());
            } catch (Throwable $ignored) {
                // A cache outage must not expose data or break the page.
            }
            return ['state' => 'unavailable'];
        } finally {
            if ($lock) {
                try {
                    $lock->release();
                } catch (Throwable $ignored) {
                    // The bounded lock expires automatically.
                }
            }
        }
    }

    private function validate(mixed $result, array $messages, $catalog): array
    {
        if (!is_array($result) || array_diff(array_keys($result), ['introduction', 'items'])
            || !isset($result['introduction'], $result['items'])
            || !is_string($result['introduction'])
            || !is_array($result['items']) || !array_is_list($result['items'])
            || count($result['items']) > 3) {
            throw new \UnexpectedValueException('Invalid recommendation response.');
        }
        $intro = $this->safeText($result['introduction'], 280);
        $topics = [];
        $items = [];
        foreach ($result['items'] as $item) {
            if (!is_array($item) || array_diff(array_keys($item), ['resource_id', 'topic', 'evidence', 'reason'])
                || !is_int($item['resource_id'] ?? null) || !is_string($item['topic'] ?? null)
                || !is_string($item['reason'] ?? null) || !is_string($item['evidence'] ?? null)
                || isset($items[$item['resource_id']])) {
                throw new \UnexpectedValueException('Invalid recommendation.');
            }
            $topic = $this->safeText($item['topic'], 60);
            $evidence = trim($item['evidence']);
            if (mb_strlen($evidence) < 4 || mb_strlen($evidence) > 160
                || !collect($messages)->contains(fn ($message) => mb_stripos($message, $evidence) !== false)) {
                throw new \UnexpectedValueException('Unsupported topic.');
            }
            if (!in_array($topic, $topics, true)) {
                $topics[] = $topic;
            }
            $resource = $catalog->firstWhere('id', $item['resource_id']);
            if (!$resource) {
                throw new \UnexpectedValueException('Unknown or inactive resource.');
            }
            $items[$item['resource_id']] = [
                'title' => $resource->title,
                'category' => $resource->category ?: 'Wellness resource',
                'reason' => $this->safeText($item['reason'], 220),
                'url' => route('student.resources.show', ['resource' => $resource->id]),
            ];
        }
        return [
            'state' => $items ? 'ready' : 'no_match',
            'introduction' => $intro,
            'topics' => $topics,
            'items' => array_values($items),
        ];
    }

    private function safeText(string $value, int $max): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max || $value !== strip_tags($value)
            || preg_match('~https?://|www\.|[\x00-\x1F\x7F]|\\b(?:diagnos(?:is|ed|e)|disorder|suicid(?:al|e))\\b~iu', $value)) {
            throw new \UnexpectedValueException('Unsafe recommendation text.');
        }
        return $value;
    }
}
