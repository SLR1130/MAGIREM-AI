<?php

namespace App\Services;

use App\Models\Resource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class WellnessResourceSearchService
{
    private const MEDLINE_HOSTS = ['wsearch.nlm.nih.gov'];
    private const MEDLINE_RESULT_HOSTS = ['medlineplus.gov', 'www.medlineplus.gov', 'wsearch.nlm.nih.gov'];
    private const EUROPE_PMC_HOSTS = ['www.ebi.ac.uk', 'europepmc.org', 'www.europepmc.org'];
    private const YOUTUBE_API_HOSTS = ['www.googleapis.com'];
    private const YOUTUBE_THUMBNAIL_HOSTS = ['i.ytimg.com'];

    /**
     * Search local approved resources and, when configured, public provider
     * indexes. Only the explicit query is sent to remote providers.
     */
    public function search(string $query, int $page = 1, bool $includePublicSources = false, ?string $category = null, bool $includeResearch = false, bool $includeVideos = false): array
    {
        $originalQuery = trim($query);
        $cleanQuery = $this->cleanQuery($originalQuery);

        if ($cleanQuery === '') {
            return [
                'query' => '',
                'provider_query' => '',
                'mapping_note' => null,
                'local' => Resource::where('is_active', true)
                    ->when($category, fn ($builder) => $builder->where('category', $category))
                    ->orderBy('category')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->paginate(12, ['*'], 'resource_page', max(1, $page))
                    ->withQueryString(),
                'remote' => collect(),
                'videos' => collect(),
                'errors' => [],
                'provider_errors' => ['web' => [], 'videos' => []],
                'providers' => ['web' => ['requested' => false, 'available' => false], 'videos' => ['requested' => false, 'available' => false]],
                'searched' => false,
                'public_requested' => false,
                'videos_requested' => false,
                'research_requested' => false,
                'related_topics' => $this->relatedTopics(''),
            ];
        }

        $mapping = $this->mapPublicTerms($cleanQuery);
        $providerQuery = $mapping['query'];
        $local = $this->localSearch($cleanQuery, $providerQuery, $page, $category);
        $remote = collect();
        $videos = collect();
        $errors = [];
        $providers = [
            'web' => ['requested' => $includePublicSources, 'available' => false],
            'videos' => ['requested' => $includeVideos, 'available' => false],
        ];

        if ($includePublicSources) {
            if (!config('services.wellness_resources.medlineplus.enabled', true)) {
                $errors[] = 'Trusted public search is not configured in this environment. Local results are still shown.';
            } elseif ($this->withinRateLimit('medlineplus')) {
                try {
                    $remote = $remote->concat($this->searchMedlinePlus($providerQuery));
                    $providers['web']['available'] = true;
                } catch (\Throwable $exception) {
                    \Log::warning('Wellness provider request failed', ['provider' => 'medlineplus', 'exception' => get_class($exception)]);
                    $errors[] = 'MedlinePlus is temporarily unavailable. Local results are still shown.';
                }
            } else {
                $errors[] = 'MedlinePlus search is rate-limited briefly. Please try again later.';
            }
        }

        if ($includeVideos) {
            if (!config('services.wellness_resources.youtube.enabled', true)) {
                $errors[] = 'YouTube discovery is disabled in this environment. Add YOUTUBE_DATA_API_KEY and enable WELLNESS_YOUTUBE_ENABLED to use the official YouTube Data API.';
            } elseif (trim((string) config('services.wellness_resources.youtube.api_key', '')) === '') {
                $errors[] = 'YouTube discovery is unavailable because YOUTUBE_DATA_API_KEY is not configured. No video results were simulated.';
            } elseif ($this->withinRateLimit('youtube')) {
                try {
                    $videos = $this->searchYouTube($providerQuery);
                    $providers['videos']['available'] = true;
                } catch (\Throwable $exception) {
                    \Log::warning('Wellness provider request failed', ['provider' => 'youtube', 'exception' => get_class($exception)]);
                    $errors[] = 'YouTube video search is temporarily unavailable. Other results remain available.';
                }
            } else {
                $errors[] = 'YouTube search is rate-limited briefly. Please try again later.';
            }
        }

        $researchRequested = $includePublicSources && $includeResearch && (bool) config('services.wellness_resources.europe_pmc.enabled', false);
        if ($researchRequested) {
            if ($this->withinRateLimit('europe_pmc')) {
                try {
                    $remote = $remote->concat($this->searchEuropePmc($providerQuery));
                    $providers['web']['available'] = true;
                } catch (\Throwable $exception) {
                    \Log::warning('Wellness provider request failed', ['provider' => 'europe_pmc', 'exception' => get_class($exception)]);
                    $errors[] = 'Europe PMC research metadata is temporarily unavailable.';
                }
            } else {
                $errors[] = 'Europe PMC search is rate-limited briefly. Please try again later.';
            }
        }

        $remote = $this->deduplicatePublicResults($this->rankPublicResults($remote, $providerQuery));

        return [
            'query' => $cleanQuery,
            'provider_query' => $providerQuery,
            'mapping_note' => $mapping['note'],
            'local' => $local,
            'remote' => $remote->take((int) config('services.wellness_resources.max_results', 8)),
            'videos' => $videos->take((int) config('services.wellness_resources.max_results', 8)),
            'errors' => $errors,
            'provider_errors' => [
                'web' => array_values(array_filter($errors, fn (string $message) => !str_contains(mb_strtolower($message, 'UTF-8'), 'youtube'))),
                'videos' => array_values(array_filter($errors, fn (string $message) => str_contains(mb_strtolower($message, 'UTF-8'), 'youtube'))),
            ],
            'providers' => $providers,
            'searched' => true,
            'public_requested' => $includePublicSources,
            'videos_requested' => $includeVideos,
            'research_requested' => $researchRequested,
            'related_topics' => $this->relatedTopics($cleanQuery),
        ];
    }

    public function cleanQuery(string $query): string
    {
        $query = strip_tags($query);
        $query = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $query) ?? '';
        $query = preg_replace('/\s+/u', ' ', trim($query)) ?? '';
        return Str::limit($query, (int) config('services.wellness_resources.max_query_length', 120), '');
    }

    public function mapPublicTerms(string $query): array
    {
        $lower = mb_strtolower($query, 'UTF-8');
        $maps = [
            'hindi makatulog' => 'sleep difficulties',
            'stress sa school' => 'academic stress',
            'kinakabahan' => 'anxiety nervousness',
            'pagod sa school' => 'academic stress burnout',
            'hirap matulog' => 'sleep difficulties',
            'sobrang lungkot' => 'sadness grief support',
            'journaling' => 'journaling reflection',
            'journal' => 'journaling reflection',
            'academic pressure' => 'academic stress student wellbeing',
            'burnout' => 'burnout stress management',
            'self-care' => 'self care wellbeing',
        ];

        foreach ($maps as $term => $replacement) {
            if (str_contains($lower, $term)) {
                return [
                    'query' => trim(str_ireplace($term, $replacement, $query)),
                    'note' => sprintf('We searched public English resources for "%s" using the limited topic mapping "%s".', $term, $replacement),
                ];
            }
        }

        return ['query' => $query, 'note' => null];
    }

    protected function localSearch(string $originalQuery, string $providerQuery, int $page, ?string $category = null)
    {
        $normalizedQuery = mb_strtolower(trim($originalQuery), 'UTF-8');
        $terms = collect(preg_split('/\s+/u', mb_strtolower($originalQuery . ' ' . $providerQuery, 'UTF-8')) ?: [])
            ->map(fn ($term) => trim($term, " \t\n\r\0\x0B,.;:!?()[]{}\"'"))
            ->filter(fn ($term) => mb_strlen($term, 'UTF-8') >= 2)
            ->unique()
            ->values();

        $resources = Resource::query()
            ->where('is_active', true)
            ->when($category, fn ($builder) => $builder->where('category', $category));

        if ($terms->isNotEmpty()) {
            $resources->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%' . addcslashes($term, '%_\\') . '%';
                    $query->orWhere('title', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('category', 'like', $like)
                        ->orWhere('content', 'like', $like);
                }
            });
        }

        // Portable weighted ordering: direct title matches are shown before
        // broad description/content matches without changing the stored catalog.
        $scoreSql = 'CASE';
        $bindings = [];
        if ($normalizedQuery !== '') {
            $scoreSql .= ' WHEN LOWER(title) LIKE ? THEN 100';
            $bindings[] = '%' . addcslashes($normalizedQuery, '%_\\') . '%';
            $scoreSql .= ' WHEN LOWER(category) LIKE ? THEN 70';
            $bindings[] = '%' . addcslashes($normalizedQuery, '%_\\') . '%';
        }
        foreach ($terms as $term) {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $scoreSql .= ' WHEN LOWER(title) LIKE ? THEN 45';
            $bindings[] = $like;
            $scoreSql .= ' WHEN LOWER(category) LIKE ? THEN 30';
            $bindings[] = $like;
            $scoreSql .= ' WHEN LOWER(description) LIKE ? THEN 15';
            $bindings[] = $like;
            $scoreSql .= ' WHEN LOWER(content) LIKE ? THEN 5';
            $bindings[] = $like;
        }
        $scoreSql .= ' ELSE 0 END';

        return $resources
            ->select('resources.*')
            ->selectRaw($scoreSql . ' as relevance_score', $bindings)
            ->orderByDesc('relevance_score')
            ->orderBy('title')
            ->orderByDesc('id')
            ->paginate(12, ['*'], 'resource_page', max(1, $page))
            ->withQueryString();
    }

    /** @return array<int, string> */
    public function relatedTopics(string $query): array
    {
        $topics = [
            'Stress management', 'Sleep', 'Anxiety education', 'Journaling',
            'Self-care', 'Academic pressure', 'Burnout',
        ];
        $query = mb_strtolower($query, 'UTF-8');

        if ($query === '') {
            return $topics;
        }

        return collect($topics)
            ->sortByDesc(fn ($topic) => str_contains(mb_strtolower($topic, 'UTF-8'), $query) ? 1 : 0)
            ->values()
            ->all();
    }
    protected function searchMedlinePlus(string $query): Collection
    {
        $endpoint = (string) config('services.wellness_resources.medlineplus.endpoint');
        $this->assertAllowedEndpoint($endpoint, self::MEDLINE_HOSTS);
        $cacheKey = 'wellness:medlineplus:reader-v2:' . sha1(mb_strtolower($query, 'UTF-8'));

        return Cache::remember($cacheKey, (int) config('services.wellness_resources.medlineplus.cache_ttl', 86400), function () use ($endpoint, $query) {
            $response = $this->http($endpoint)->get($endpoint, [
                'db' => 'healthTopics',
                'term' => $query,
                'retmax' => (int) config('services.wellness_resources.max_results', 8),
                'rettype' => 'brief',
                'tool' => 'minsuanscore_wellness_resources',
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('MedlinePlus returned HTTP ' . $response->status());
            }

            return $this->parseMedlinePlus($response->body());
        });
    }

    protected function parseMedlinePlus(string $xml): Collection
    {
        $document = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($document === false) {
            throw new \RuntimeException('MedlinePlus returned malformed XML.');
        }

        $results = collect();
        foreach ($document->list->document ?? [] as $item) {
            $values = [];
            foreach ($item->content ?? [] as $content) {
                $name = mb_strtolower((string) ($content['name'] ?? ''), 'UTF-8');
                if ($name !== '') {
                    $values[$name] = $this->plainProviderText($content->asXML() ?: (string) $content);
                }
            }

            $url = (string) ($item['url'] ?? '');
            if (!$this->isAllowedResultUrl($url, self::MEDLINE_RESULT_HOSTS) || empty($values['title'])) {
                continue;
            }

            $results->push([
                'provider' => 'MedlinePlus',
                'type' => 'health_topic',
                'title' => $values['title'],
                'organization' => $values['organizationname'] ?? 'National Library of Medicine',
                'summary' => $values['snippet'] ?? ($values['fullsummary'] ?? ''),
                'reader_content' => $values['fullsummary'] ?? '',
                'reader_content_kind' => !empty($values['fullsummary']) ? 'MedlinePlus health-topic summary' : (!empty($values['snippet']) ? 'MedlinePlus search preview' : 'Source metadata'),
                'reader_note' => !empty($values['fullsummary'])
                    ? 'This source-provided health-topic summary is a preview, not the complete MedlinePlus article.'
                    : 'Only a short search preview is available here. Open MedlinePlus for the complete health topic.',
                'author' => $values['organizationname'] ?? 'National Library of Medicine',
                'category' => 'Health topic',
                'date' => $values['lastupdate'] ?? ($values['date'] ?? null),
                'url' => $url,
                'attribution' => 'Source: MedlinePlus.gov (National Library of Medicine).',
            ]);
        }

        return $results;
    }

    protected function searchEuropePmc(string $query): Collection
    {
        $endpoint = (string) config('services.wellness_resources.europe_pmc.endpoint');
        $this->assertAllowedEndpoint($endpoint, self::EUROPE_PMC_HOSTS);
        $cacheKey = 'wellness:europe-pmc:reader-v2:' . sha1(mb_strtolower($query, 'UTF-8'));

        return Cache::remember($cacheKey, (int) config('services.wellness_resources.europe_pmc.cache_ttl', 21600), function () use ($endpoint, $query) {
            $response = $this->http($endpoint)->get($endpoint, [
                'query' => $query,
                'format' => 'json',
                'resultType' => 'core',
                'pageSize' => (int) config('services.wellness_resources.max_results', 8),
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('Europe PMC returned HTTP ' . $response->status());
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                throw new \RuntimeException('Europe PMC returned malformed JSON.');
            }

            return collect(data_get($payload, 'resultList.result', []))
                ->filter(fn ($item) => is_array($item) && !empty($item['title']))
                ->map(function (array $item) {
                    $pmcid = $item['pmcid'] ?? null;
                    $id = $pmcid ?? $item['id'] ?? null;
                    $source = $item['source'] ?? 'MED';
                    $url = $id ? ($pmcid ? 'https://europepmc.org/article/PMC/' . rawurlencode($id) : 'https://europepmc.org/article/' . rawurlencode($source) . '/' . rawurlencode($id)) : null;
                    $license = trim((string) ($item['license'] ?? ''));
                    $abstract = $this->plainProviderText((string) ($item['abstractText'] ?? ''));
                    $displayAbstract = $abstract !== '' && $this->permitsAbstractDisplay($license);

                    return [
                        'provider' => 'Europe PMC',
                        'type' => 'research_metadata',
                        'title' => trim((string) $item['title']),
                        'organization' => data_get($item, 'journalInfo.journal.title') ?? ($item['journalTitle'] ?? 'Europe PMC indexed publication'),
                        'summary' => $displayAbstract
                            ? Str::limit($abstract, 320, '…')
                            : 'Bibliographic metadata only. Open Europe PMC to review any available abstract and access terms.',
                        'reader_content' => $displayAbstract ? $abstract : '',
                        'reader_content_kind' => $displayAbstract ? 'Europe PMC abstract' : 'Bibliographic metadata',
                        'reader_note' => $displayAbstract
                            ? 'This is the source-provided abstract, not the full article. Review the article license and access terms at Europe PMC.'
                            : 'Abstract text is not shown because this record did not provide a supported reuse license. Open Europe PMC to review available content and terms.',
                        'author' => (string) ($item['authorString'] ?? ''),
                        'license' => $license,
                        'category' => 'Scientific research metadata',
                        'date' => $item['firstPublicationDate'] ?? ($item['pubYear'] ?? null),
                        'url' => $url,
                        'attribution' => $displayAbstract
                            ? 'Source: Europe PMC. Abstract reuse is shown only for records with a supported explicit license.'
                            : 'Source: Europe PMC. Bibliographic metadata is shown; no article text is reproduced.',
                    ];
                })
                ->filter(fn ($item) => $item['url'] && $this->isAllowedResultUrl($item['url'], self::EUROPE_PMC_HOSTS))
                ->values();
        });
    }

    private function permitsAbstractDisplay(string $license): bool
    {
        $license = strtolower(trim($license));
        if (in_array($license, ['cc0', 'cc0 1.0', 'cc by', 'cc-by', 'cc by 4.0', 'cc-by-4.0', 'cc by 3.0', 'cc-by-3.0'], true)) {
            return true;
        }

        return preg_match('#^https?://creativecommons\.org/publicdomain/zero/1\.0/?$#', $license) === 1
            || preg_match('#^https?://creativecommons\.org/licenses/by/(?:3\.0|4\.0)/?$#', $license) === 1;
    }

    private function plainProviderText(string $content): string
    {
        // Decode XML-escaped provider markup before removing tags; decoding
        // after strip_tags would reintroduce literal query-highlight fragments.
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('~<br\s*/?>|</(?:p|div|li|h[1-6])\s*>~i', "\n\n", $content) ?? $content;
        $content = strip_tags($content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/[^\S\n]+/u', ' ', $content) ?? $content;
        $content = preg_replace('/ *\n */u', "\n", $content) ?? $content;
        $content = preg_replace('/\n{3,}/u', "\n\n", $content) ?? $content;

        return trim($content);
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function searchYouTube(string $query): Collection
    {
        $endpoint = rtrim((string) config('services.wellness_resources.youtube.endpoint'), '/');
        $this->assertAllowedEndpoint($endpoint . '/search', self::YOUTUBE_API_HOSTS);
        $apiKey = trim((string) config('services.wellness_resources.youtube.api_key', ''));
        if ($apiKey === '') {
            throw new \RuntimeException('YouTube Data API key is not configured.');
        }
        $cacheKey = 'wellness:youtube:' . sha1(mb_strtolower($query, 'UTF-8'));

        return Cache::remember($cacheKey, (int) config('services.wellness_resources.youtube.cache_ttl', 21600), function () use ($endpoint, $apiKey, $query) {
            $searchResponse = $this->http($endpoint . '/search')->get($endpoint . '/search', [
                'part' => 'snippet',
                'q' => $query,
                'type' => 'video',
                'videoEmbeddable' => 'true',
                'safeSearch' => 'strict',
                'order' => 'relevance',
                'maxResults' => min(10, max(1, (int) config('services.wellness_resources.max_results', 8))),
                'key' => $apiKey,
            ]);
            if (!$searchResponse->successful()) {
                throw new \RuntimeException('YouTube search returned HTTP ' . $searchResponse->status());
            }

            $searchPayload = $searchResponse->json();
            if (!is_array($searchPayload)) {
                throw new \RuntimeException('YouTube search returned malformed JSON.');
            }

            $candidates = collect(data_get($searchPayload, 'items', []))
                ->filter(fn ($item) => is_array($item) && ($item['id']['kind'] ?? null) === 'youtube#video')
                ->map(function (array $item) {
                    $id = (string) data_get($item, 'id.videoId', '');
                    if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
                        return null;
                    }
                    $snippet = (array) ($item['snippet'] ?? []);
                    $thumbs = (array) ($snippet['thumbnails'] ?? []);
                    $thumbnail = null;
                    foreach (['maxres', 'standard', 'high', 'medium', 'default'] as $size) {
                        $candidate = (string) data_get($thumbs, $size . '.url', '');
                        if ($candidate !== '' && $this->isAllowedResultUrl($candidate, self::YOUTUBE_THUMBNAIL_HOSTS)) {
                            $thumbnail = $candidate;
                            break;
                        }
                    }

                    return [
                        'video_id' => $id,
                        'title' => trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($snippet['title'] ?? ''))) ?? ''),
                        'description' => trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($snippet['description'] ?? ''))) ?? ''),
                        'channel' => trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($snippet['channelTitle'] ?? ''))) ?? ''),
                        'published_at' => (string) ($snippet['publishedAt'] ?? ''),
                        'thumbnail' => $thumbnail,
                    ];
                })
                ->filter(fn ($item) => is_array($item) && $item['title'] !== '')
                ->unique('video_id')
                ->values();

            if ($candidates->isEmpty()) {
                return collect();
            }

            $detailsEndpoint = $endpoint . '/videos';
            $this->assertAllowedEndpoint($detailsEndpoint, self::YOUTUBE_API_HOSTS);
            $detailsResponse = $this->http($detailsEndpoint)->get($detailsEndpoint, [
                'part' => 'contentDetails,status',
                'id' => $candidates->pluck('video_id')->implode(','),
                'key' => $apiKey,
            ]);
            if (!$detailsResponse->successful()) {
                throw new \RuntimeException('YouTube video details returned HTTP ' . $detailsResponse->status());
            }

            $detailsPayload = $detailsResponse->json();
            if (!is_array($detailsPayload)) {
                throw new \RuntimeException('YouTube video details returned malformed JSON.');
            }
            $details = collect(data_get($detailsPayload, 'items', []))
                ->filter(fn ($item) => is_array($item) && data_get($item, 'status.embeddable') === true)
                ->keyBy('id');

            return $candidates
                ->filter(fn (array $item) => $details->has($item['video_id']))
                ->map(function (array $item) use ($details) {
                    $duration = (string) data_get($details->get($item['video_id']), 'contentDetails.duration', '');
                    return [
                        'provider' => 'YouTube',
                        'type' => 'video',
                        'category' => 'Educational video',
                        ...$item,
                        'summary' => $item['description'],
                        'duration' => $this->formatIsoDuration($duration),
                        'embeddable' => true,
                        'url' => 'https://www.youtube.com/watch?v=' . rawurlencode($item['video_id']),
                        'attribution' => 'Video metadata and thumbnail provided by YouTube. Review the channel and content before relying on it.',
                    ];
                })
                ->values();
        });
    }

    protected function formatIsoDuration(string $duration): ?string
    {
        try {
            $interval = new \DateInterval($duration);
        } catch (\Throwable $exception) {
            return null;
        }
        $seconds = ($interval->d * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
        if ($seconds <= 0) {
            return null;
        }
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;
        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds)
            : sprintf('%d:%02d', $minutes, $remainingSeconds);
    }

    protected function deduplicatePublicResults(Collection $results): Collection
    {
        return $results->filter(fn (array $result) => !empty($result['url']))
            ->unique(fn (array $result) => mb_strtolower(rtrim((string) parse_url((string) $result['url'], PHP_URL_HOST), '.') . '/' . ltrim((string) parse_url((string) $result['url'], PHP_URL_PATH), '/'), 'UTF-8'))
            ->values();
    }

    protected function http(string $endpoint)
    {
        return Http::accept(str_contains($endpoint, 'wsearch') ? 'application/xml' : 'application/json')
            ->withOptions([
                'proxy' => (string) config('services.wellness_resources.proxy', ''),
                // Provider APIs are allowlisted; do not follow an upstream redirect
                // to an unvalidated host or forward query credentials elsewhere.
                'allow_redirects' => false,
            ])
            ->connectTimeout((int) config('services.wellness_resources.connect_timeout', 3))
            ->timeout((int) config('services.wellness_resources.timeout', 5))
            ->retry((int) config('services.wellness_resources.retries', 1), 200, function ($exception) {
                return $exception instanceof ConnectionException
                    || ($exception instanceof \Illuminate\Http\Client\RequestException && $exception->response?->serverError());
            });
    }

    protected function withinRateLimit(string $provider): bool
    {
        $configKey = match ($provider) {
            'medlineplus' => 'medlineplus',
            'youtube' => 'youtube',
            default => 'europe_pmc',
        };
        $limit = (int) config("services.wellness_resources.{$configKey}.rate_limit", 10);
        $subject = auth()->id() ? 'user:' . auth()->id() : 'ip:' . request()->ip();
        $key = 'wellness-search:' . $provider . ':' . sha1($subject);
        return RateLimiter::attempt($key, max(1, $limit), fn () => true, 60) === true;
    }

    protected function rankPublicResults(Collection $results, string $query): Collection
    {
        $terms = collect(preg_split('/\s+/u', mb_strtolower($query, 'UTF-8')) ?: [])
            ->filter(fn ($term) => mb_strlen($term, 'UTF-8') >= 3)
            ->unique()
            ->values();

        if ($terms->isEmpty()) {
            return $results->values();
        }

        return $results
            ->map(function (array $result) use ($terms) {
                $title = mb_strtolower((string) ($result['title'] ?? ''), 'UTF-8');
                $summary = mb_strtolower((string) ($result['summary'] ?? ''), 'UTF-8');
                $score = $terms->sum(function ($term) use ($title, $summary) {
                    return (str_contains($title, $term) ? 10 : 0) + (str_contains($summary, $term) ? 2 : 0);
                });
                $result['relevance_score'] = $score;
                return $result;
            })
            ->sortByDesc('relevance_score')
            ->values();
    }
    protected function assertAllowedEndpoint(string $endpoint, array $hosts): void
    {
        $parsed = parse_url($endpoint);
        if (($parsed['scheme'] ?? '') !== 'https' || !in_array(strtolower((string) ($parsed['host'] ?? '')), $hosts, true)) {
            throw new \RuntimeException('Configured wellness provider endpoint is not allowlisted.');
        }
    }

    protected function isAllowedResultUrl(string $url, array $hosts): bool
    {
        if ($url === '') {
            return false;
        }
        $parsed = parse_url($url);
        return ($parsed['scheme'] ?? '') === 'https'
            && in_array(strtolower((string) ($parsed['host'] ?? '')), $hosts, true);
    }
}

