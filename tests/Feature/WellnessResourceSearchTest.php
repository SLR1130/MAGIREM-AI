<?php

namespace Tests\Feature;

use App\Models\CopingStrategy;
use App\Models\Resource;
use App\Models\User;
use App\Services\WellnessResourceSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WellnessResourceSearchTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    public function test_search_combines_local_resources_with_safe_medlineplus_metadata(): void
    {
        config(['services.wellness_resources.medlineplus.enabled' => true]);
        $resource = Resource::create([
            'title' => 'Sleep synthetic routines',
            'description' => 'A local synthetic guide.',
            'category' => 'sleep',
            'content' => 'General information.',
            'is_active' => true,
        ]);
        Http::fake([
            'https://wsearch.nlm.nih.gov/*' => Http::response(
                '<nlmSearchResult><list><document url="https://medlineplus.gov/sleep"><content name="title">Sleep</content><content name="organizationName">National Library of Medicine</content><content name="snippet">A short public summary.</content></document></list></nlmSearchResult>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        \Illuminate\Support\Facades\Cache::forget('wellness:medlineplus:reader-v2:' . sha1('sleep synthetic'));
        $result = app(WellnessResourceSearchService::class)->search('sleep synthetic', 1, true);

        $this->assertTrue($result['local']->contains('id', $resource->id));
        $this->assertSame('Sleep', $result['remote']->first()['title']);
        $this->assertSame('MedlinePlus', $result['remote']->first()['provider']);
        Http::assertSent(function ($request) {
            return $request->url() !== ''
                && $request['term'] === 'sleep synthetic'
                && !str_contains($request['term'], 'student');
        });
    }

    public function test_limited_tagalog_mapping_is_explicit_and_does_not_store_query(): void
    {
        Http::fake(['https://wsearch.nlm.nih.gov/*' => Http::response('<nlmSearchResult><list/></nlmSearchResult>', 200)]);
        $result = app(WellnessResourceSearchService::class)->search('hindi makatulog', 1, true);

        $this->assertSame('hindi makatulog', $result['query']);
        $this->assertSame('sleep difficulties', $result['provider_query']);
        $this->assertNotNull($result['mapping_note']);
        Http::assertSent(fn ($request) => $request['term'] === 'sleep difficulties');
    }

    public function test_provider_failure_keeps_local_results_and_reports_retryable_error(): void
    {
        Resource::create(['title' => 'Grounding', 'description' => 'Local', 'category' => 'coping', 'content' => 'Synthetic content.', 'is_active' => true]);
        Http::fake(['https://wsearch.nlm.nih.gov/*' => Http::response('upstream failure', 503)]);

        $result = app(WellnessResourceSearchService::class)->search('grounding', 1, true);

        $this->assertTrue($result['local']->isNotEmpty());
        $this->assertNotEmpty($result['errors']);
        $this->assertEmpty($result['remote']);
    }

    public function test_opted_in_search_reports_when_the_trusted_provider_is_disabled(): void
    {
        config(['services.wellness_resources.medlineplus.enabled' => false]);
        Resource::create(['title' => 'Local grounding guide', 'description' => 'Local', 'category' => 'coping', 'content' => 'Synthetic content.', 'is_active' => true]);
        Http::fake();

        $result = app(WellnessResourceSearchService::class)->search('grounding', 1, true);

        $this->assertSame([], $result['remote']->all());
        $this->assertStringContainsString('not configured', implode(' ', $result['errors']));
        Http::assertNothingSent();
    }

    public function test_local_first_search_does_not_contact_public_provider_until_student_opts_in(): void
    {
        Resource::create(['title' => 'Journaling reflection guide', 'description' => 'Local writing prompt.', 'category' => 'reflection', 'content' => 'Synthetic content.', 'is_active' => true]);
        Http::fake();

        $result = app(WellnessResourceSearchService::class)->search('journaling', 1);

        $this->assertTrue($result['local']->isNotEmpty());
        $this->assertFalse($result['public_requested']);
        $this->assertEmpty($result['remote']);
        Http::assertNothingSent();
    }

    public function test_json_search_stays_local_by_default_and_uses_public_provider_only_after_explicit_opt_in(): void
    {
        $student = $this->student();
        Resource::create(['title' => 'Sleep routine guide', 'description' => 'Local sleep information.', 'category' => 'sleep', 'content' => 'Synthetic content.', 'is_active' => true]);
        Http::fake(['https://wsearch.nlm.nih.gov/*' => Http::response('<nlmSearchResult><list><document url="https://medlineplus.gov/sleep"><content name="title">Sleep</content><content name="snippet">A provider summary.</content></document></list></nlmSearchResult>', 200)]);
        \Illuminate\Support\Facades\Cache::forget('wellness:medlineplus:reader-v2:' . sha1('sleep'));

        $this->actingAs($student)->getJson(route('student.resources.search', ['q' => 'sleep']))
            ->assertOk()
            ->assertJsonPath('public_requested', false)
            ->assertJsonPath('local.items.0.title', 'Sleep routine guide')
            ->assertJsonPath('counts.library', 1)
            ->assertJsonPath('counts.all', 1);
        Http::assertNothingSent();

        $this->actingAs($student)->getJson(route('student.resources.search', ['q' => 'sleep', 'public_sources' => 1]))
            ->assertOk()
            ->assertJsonPath('public_requested', true)
            ->assertJsonPath('public.0.provider', 'MedlinePlus')
            ->assertJsonPath('counts.web', 1)
            ->assertJsonPath('counts.all', 2);
        Http::assertSent(fn ($request) => $request['term'] === 'sleep');
    }

    public function test_local_title_match_ranks_before_broad_description_match(): void
    {
        $titleMatch = Resource::create(['title' => 'Sleep planning for students', 'description' => 'A local guide.', 'category' => 'sleep', 'content' => 'Synthetic content.', 'is_active' => true]);
        Resource::create(['title' => 'Healthy routines', 'description' => 'This guide briefly mentions sleep.', 'category' => 'general', 'content' => 'Synthetic content.', 'is_active' => true]);
        Http::fake();

        $result = app(WellnessResourceSearchService::class)->search('sleep', 1);

        $this->assertSame($titleMatch->id, $result['local']->items()[0]->id);
        Http::assertNothingSent();
    }
    public function test_blank_query_uses_local_catalog_without_external_request(): void
    {
        Http::fake();
        $result = app(WellnessResourceSearchService::class)->search('', 1);

        $this->assertFalse($result['searched']);
        Http::assertNothingSent();
    }

    public function test_student_can_search_toolkit_and_open_authorized_local_detail(): void
    {
        $student = $this->student();
        $resource = Resource::create(['title' => 'Financial stress guide', 'description' => 'Synthetic detail', 'category' => 'general', 'content' => 'Steps', 'is_active' => true]);
        CopingStrategy::create(['name' => 'Grounding practice', 'description' => 'Notice the present', 'category' => 'grounding', 'instructions' => 'Name five things.', 'duration_minutes' => 5, 'difficulty' => 'easy', 'is_active' => true]);
        Http::fake(['https://wsearch.nlm.nih.gov/*' => Http::response('<nlmSearchResult><list/></nlmSearchResult>', 200)]);

        $this->actingAs($student)->get(route('student.resources', ['q' => 'financial']))
            ->assertOk()->assertSee('Financial stress guide')->assertSee('Search library');
        $this->actingAs($student)->get(route('student.resources.show', $resource->id))
            ->assertOk()->assertSee('Synthetic detail');
        $this->actingAs($student)->get(route('student.coping-toolkit', ['q' => 'grounding']))
            ->assertOk()->assertSee('Start activity')->assertSee('Grounding practice');
    }

    public function test_query_results_are_rendered_before_topic_discovery_sections(): void
    {
        $student = $this->student();
        Resource::create([
            'title' => 'Sleep hygiene guide',
            'description' => 'A practical local guide.',
            'category' => 'sleep',
            'content' => 'Wind down gently.',
            'is_active' => true,
        ]);

        $html = $this->actingAs($student)->get(route('student.resources', ['q' => 'sleep']))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'Explore by topic'),
            strpos($html, 'Sleep hygiene guide'),
            'Active search results should appear before discovery content.'
        );
        $this->assertStringContainsString('Results for', $html);
        $this->assertStringContainsString('aspect-ratio: 16 / 9', $html);
    }

    public function test_opted_in_public_results_are_rendered_with_provider_and_safe_link(): void
    {
        $student = $this->student();
        Http::fake([
            'https://wsearch.nlm.nih.gov/*' => Http::response(
                '<nlmSearchResult><list><document url="https://medlineplus.gov/sleep"><content name="title">Sleep health</content><content name="snippet">A trusted public summary.</content></document></list></nlmSearchResult>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        Cache::forget('wellness:medlineplus:reader-v2:' . sha1('sleep'));
        $this->actingAs($student)
            ->get(route('student.resources', ['q' => 'sleep', 'public_sources' => 1]))
            ->assertOk()
            ->assertSee('Trusted web articles')
            ->assertSee('Sleep health')
            ->assertSee('MedlinePlus')
            ->assertSee('https://medlineplus.gov/sleep')
            ->assertSee('Open full topic at MedlinePlus');
    }

    public function test_library_zero_count_does_not_hide_real_web_results_or_report_overall_zero(): void
    {
        $student = $this->student();
        Http::fake([
            'https://wsearch.nlm.nih.gov/*' => Http::response(
                '<nlmSearchResult><list><document url="https://medlineplus.gov/sleep"><content name="title">Sleep health article</content><content name="snippet">Source-provided summary for sleep.</content><content name="organizationName">National Library of Medicine</content></document></list></nlmSearchResult>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);
        Cache::forget('wellness:medlineplus:reader-v2:' . sha1('sleep'));

        $this->actingAs($student)
            ->get(route('student.resources', ['q' => 'sleep', 'public_sources' => 1]))
            ->assertOk()
            ->assertSee('1 resource')
            ->assertSee('Sleep health article')
            ->assertSee('Web Articles')
            ->assertDontSee('0 local results')
            ->assertDontSee('images/wellness/sleep.svg')
            ->assertDontSee('images/wellness/wellbeing.svg');
    }

    public function test_youtube_is_not_contacted_without_separate_student_opt_in(): void
    {
        config([
            'services.wellness_resources.youtube.enabled' => true,
            'services.wellness_resources.youtube.api_key' => 'test-only-key',
        ]);
        Http::fake();

        $result = app(WellnessResourceSearchService::class)->search('sleep', 1, false, null, false, false);

        $this->assertFalse($result['videos_requested']);
        $this->assertEmpty($result['videos']);
        Http::assertNothingSent();
    }

    public function test_opted_in_youtube_search_without_api_key_is_honestly_unavailable(): void
    {
        config([
            'services.wellness_resources.youtube.enabled' => true,
            'services.wellness_resources.youtube.api_key' => '',
        ]);
        Http::fake();

        $result = app(WellnessResourceSearchService::class)->search('sleep', 1, false, null, false, true);

        $this->assertTrue($result['videos_requested']);
        $this->assertEmpty($result['videos']);
        $this->assertFalse($result['providers']['videos']['available']);
        $this->assertStringContainsString('YOUTUBE_DATA_API_KEY', implode(' ', $result['provider_errors']['videos']));
        Http::assertNothingSent();
    }

    public function test_student_sees_youtube_configuration_blocker_instead_of_fake_video_cards(): void
    {
        $student = $this->student();
        config([
            'services.wellness_resources.youtube.enabled' => true,
            'services.wellness_resources.youtube.api_key' => '',
        ]);
        Http::fake();

        $this->actingAs($student)
            ->get(route('student.resources', ['q' => 'sleep', 'videos' => 1]))
            ->assertOk()
            ->assertSee('YOUTUBE_DATA_API_KEY is not configured')
            ->assertSee('Videos &amp; Podcasts', false)
            ->assertDontSee('<button class="resource-link play-video"', false);
        Http::assertNothingSent();
    }

    public function test_youtube_discovery_uses_official_api_and_keeps_only_confirmed_embeddable_results(): void
    {
        config([
            'services.wellness_resources.youtube.enabled' => true,
            'services.wellness_resources.youtube.api_key' => 'test-only-key',
        ]);
        Cache::forget('wellness:youtube:' . sha1('sleep'));
        $goodId = 'AbCdEf12345';
        $restrictedId = 'ZyXwVu98765';
        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'items' => [
                    ['id' => ['kind' => 'youtube#video', 'videoId' => $goodId], 'snippet' => ['title' => 'Sleep education episode', 'description' => 'Practical sleep education.', 'channelTitle' => 'Student Wellness Channel', 'publishedAt' => '2026-10-01T00:00:00Z', 'thumbnails' => ['high' => ['url' => "https://i.ytimg.com/vi/{$goodId}/hqdefault.jpg"]]]],
                    ['id' => ['kind' => 'youtube#video', 'videoId' => $restrictedId], 'snippet' => ['title' => 'Restricted video', 'description' => 'Not embeddable.', 'channelTitle' => 'Channel', 'thumbnails' => []]],
                ],
            ]),
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [
                    ['id' => $goodId, 'contentDetails' => ['duration' => 'PT18M7S'], 'status' => ['embeddable' => true]],
                    ['id' => $restrictedId, 'contentDetails' => ['duration' => 'PT5M'], 'status' => ['embeddable' => false]],
                ],
            ]),
        ]);

        $result = app(WellnessResourceSearchService::class)->search('sleep', 1, false, null, false, true);

        $this->assertCount(1, $result['videos']);
        $video = $result['videos']->first();
        $this->assertSame($goodId, $video['video_id']);
        $this->assertSame('Student Wellness Channel', $video['channel']);
        $this->assertSame('18:07', $video['duration']);
        $this->assertTrue($video['embeddable']);
        $this->assertSame("https://i.ytimg.com/vi/{$goodId}/hqdefault.jpg", $video['thumbnail']);
        $this->assertSame("https://www.youtube.com/watch?v={$goodId}", $video['url']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/search')
            && $request['q'] === 'sleep'
            && $request['type'] === 'video'
            && $request['videoEmbeddable'] === 'true');
    }

    public function test_video_search_json_contains_real_provider_fields_and_confirmed_embed_state(): void
    {
        $student = $this->student();
        config([
            'services.wellness_resources.youtube.enabled' => true,
            'services.wellness_resources.youtube.api_key' => 'test-only-key',
        ]);
        Cache::forget('wellness:youtube:' . sha1('sleep'));
        $id = 'AbCdEf12345';
        Http::fake([
            'https://www.googleapis.com/youtube/v3/search*' => Http::response(['items' => [[
                'id' => ['kind' => 'youtube#video', 'videoId' => $id],
                'snippet' => ['title' => 'Sleep podcast episode', 'description' => 'An educational conversation.', 'channelTitle' => 'Wellness channel', 'publishedAt' => '2026-10-01T00:00:00Z', 'thumbnails' => ['high' => ['url' => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg"]]],
            ]]]),
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [[
                'id' => $id, 'contentDetails' => ['duration' => 'PT42M'], 'status' => ['embeddable' => true],
            ]]]),
        ]);

        $response = $this->actingAs($student)->getJson(route('student.resources.search', ['q' => 'sleep', 'videos' => 1]));
        $response->assertOk()
            ->assertJsonPath('videos_requested', true)
            ->assertJsonPath('videos.0.provider', 'YouTube')
            ->assertJsonPath('videos.0.video_id', $id)
            ->assertJsonPath('videos.0.channel', 'Wellness channel')
            ->assertJsonPath('videos.0.duration', '42:00')
            ->assertJsonPath('videos.0.embeddable', true)
            ->assertJsonPath('videos.0.image', "https://i.ytimg.com/vi/{$id}/hqdefault.jpg")
            ->assertJsonPath('counts.library', 0)
            ->assertJsonPath('counts.videos', 1)
            ->assertJsonPath('counts.all', 1);

        $this->actingAs($student)->get(route('student.resources', ['q' => 'sleep', 'videos' => 1]))
            ->assertOk()
            ->assertSee('Videos &amp; Podcasts', false)
            ->assertSee('Sleep podcast episode')
            ->assertSee('data-open-video', false)
            ->assertSee('youtube-nocookie.com/embed/', false);
    }

    public function test_server_rendered_catalog_uses_text_first_cards_without_unrelated_search_art(): void
    {
        $student = $this->student();
        Resource::create(['title' => 'Grounding guide', 'description' => 'Try a short reset.', 'category' => 'coping', 'content' => 'A local exercise.', 'is_active' => true]);

        $this->actingAs($student)->get(route('student.resources', ['q' => 'grounding']))
            ->assertOk()
            ->assertSee('Library resource')
            ->assertDontSee('images/wellness/wellbeing.svg')
            ->assertDontSee('images/wellness/self-care.svg');
    }

    public function test_medlineplus_reader_uses_plain_source_summary_and_never_renders_provider_html(): void
    {
        config(['services.wellness_resources.medlineplus.enabled' => true]);
        Http::fake(['https://wsearch.nlm.nih.gov/*' => Http::response(
                '<nlmSearchResult><list><document url="https://medlineplus.gov/sleep"><content name="title">&lt;span class="qt0"&gt;Sleep&lt;/span&gt; health</content><content name="organizationName">National Library of Medicine</content><content name="snippet">Short summary.</content><content name="fullSummary"><p>Source summary with <strong>helpful detail</strong>.</p><p>Second paragraph.</p></content></document></list></nlmSearchResult>',
            200,
            ['Content-Type' => 'application/xml']
        )]);
        Cache::forget('wellness:medlineplus:reader-v2:' . sha1('sleep reader'));

        $result = app(WellnessResourceSearchService::class)->search('sleep reader', 1, true)['remote']->first();

        $this->assertSame('MedlinePlus health-topic summary', $result['reader_content_kind']);
        $this->assertSame('Sleep health', $result['title']);
        $this->assertStringContainsString("Source summary with helpful detail.\n\nSecond paragraph.", $result['reader_content']);
        $this->assertStringNotContainsString('<strong>', $result['reader_content']);
        $this->assertStringContainsString('not the complete MedlinePlus article', $result['reader_note']);
    }

    public function test_europe_pmc_reader_shows_abstract_only_with_supported_explicit_license(): void
    {
        config([
            'services.wellness_resources.medlineplus.enabled' => false,
            'services.wellness_resources.europe_pmc.enabled' => true,
        ]);
        Cache::forget('wellness:europe-pmc:reader-v2:' . sha1('sleep research reader'));
        Http::fake(['https://www.ebi.ac.uk/europepmc/webservices/rest/search*' => Http::response([
            'resultList' => ['result' => [
                ['source' => 'PMC', 'pmcid' => 'PMC123456', 'title' => 'Licensed abstract', 'abstractText' => '<p>Safe <i>source</i> abstract.</p>', 'license' => 'CC BY 4.0', 'authorString' => 'A. Author', 'journalInfo' => ['journal' => ['title' => 'Wellness Journal']], 'pubYear' => '2026'],
                ['source' => 'MED', 'id' => '12345678', 'title' => 'Metadata only paper', 'abstractText' => 'Do not redistribute this abstract.', 'journalTitle' => 'Other Journal', 'pubYear' => '2025'],
            ]],
        ])]);

        $results = app(WellnessResourceSearchService::class)->search('sleep research reader', 1, true, null, true)['remote'];

        $licensed = $results->firstWhere('title', 'Licensed abstract');
        $metadataOnly = $results->firstWhere('title', 'Metadata only paper');
        $this->assertSame('Europe PMC abstract', $licensed['reader_content_kind']);
        $this->assertSame('Safe source abstract.', $licensed['reader_content']);
        $this->assertSame('A. Author', $licensed['author']);
        $this->assertSame('Wellness Journal', $licensed['organization']);
        $this->assertSame('', $metadataOnly['reader_content']);
        $this->assertStringContainsString('supported reuse license', $metadataOnly['reader_note']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/search') && $request['resultType'] === 'core');
    }

    public function test_article_card_opens_reader_and_keeps_original_source_action(): void
    {
        $student = $this->student();
        config(['services.wellness_resources.medlineplus.enabled' => true]);
        Http::fake(['https://wsearch.nlm.nih.gov/*' => Http::response(
            '<nlmSearchResult><list><document url="https://medlineplus.gov/sleep"><content name="title">Sleep health article</content><content name="organizationName">National Library of Medicine</content><content name="snippet">A genuine short source preview.</content><content name="fullSummary"><p>A source-provided health-topic summary.</p></content></document></list></nlmSearchResult>',
            200,
            ['Content-Type' => 'application/xml']
        )]);
        Cache::forget('wellness:medlineplus:reader-v2:' . sha1('sleep reader ui'));

        $this->actingAs($student)->get(route('student.resources', ['q' => 'sleep reader ui', 'public_sources' => 1]))
            ->assertOk()
            ->assertSee('data-open-article', false)
            ->assertSee('id="article-reader"', false)
            ->assertSee('Read preview in MAGIREM AI')
            ->assertSee('Open full topic at MedlinePlus')
            ->assertSee('data-reader-content', false)
            ->assertDontSee('Web article</small>', false);
    }

    public function test_resource_dialogs_are_viewport_centered_and_hover_preview_is_lazy_and_guarded(): void
    {
        $student = $this->student();

        $this->actingAs($student)->get(route('student.resources'))
            ->assertOk()
            ->assertSee('class="video-dialog wellness-modal"', false)
            ->assertSee('class="article-dialog wellness-modal"', false)
            ->assertSee('place-items:center!important', false)
            ->assertSee('position:fixed!important', false)
            ->assertSee('class="article-dialog-body"', false)
            ->assertSee('data-video-preview', false)
            ->assertSee('setTimeout(() => {', false)
            ->assertSee('}, 420);', false)
            ->assertSee('navigator.connection', false)
            ->assertSee('prefers-reduced-motion', false)
            ->assertSee('onAutoplayBlocked', false)
            ->assertSee('youtube-nocookie.com/embed/', false);
    }

}
