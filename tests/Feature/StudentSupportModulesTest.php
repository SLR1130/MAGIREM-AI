<?php

namespace Tests\Feature;

use App\Models\CommunityMessage;
use App\Models\CopingStrategy;
use App\Models\Resource;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StudentSupportModulesTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    public function test_resources_page_renders_catalog_and_support_directory_without_coping_cards(): void
    {
        $student = $this->student();
        Resource::create([
            'title' => 'Sleep hygiene guide',
            'description' => 'A general guide to sleep routines.',
            'category' => 'sleep',
            'content' => 'Keep a regular wind-down routine.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($student)->get(route('student.resources'));

        $response->assertOk()
            ->assertSee('Local educational resources')
            ->assertSee('Sleep hygiene guide')
            ->assertSee('Natasha Goulbourn Foundation')
            ->assertSee('Coping Toolkit')
            ->assertDontSee('Box Breathing');
    }

    public function test_coping_toolkit_keeps_six_guided_activities_and_uses_active_records(): void
    {
        $student = $this->student();
        CopingStrategy::create([
            'name' => 'Progressive Muscle Relaxation',
            'description' => 'Release physical tension gradually.',
            'category' => 'stress_management',
            'instructions' => 'Tense, then release each muscle group.',
            'duration_minutes' => 10,
            'difficulty' => 'medium',
            'is_active' => true,
        ]);

        $response = $this->actingAs($student)->get(route('student.coping-toolkit'));

        $response->assertOk()
            ->assertSee('Progressive Muscle Relaxation')
            ->assertSee('Cognitive Reframing')
            ->assertSee('Grounding Technique (5-4-3-2-1)')
            ->assertSee('Expressive Journaling')
            ->assertSee('Physical Activity')
            ->assertDontSee('40% anxiety reduction')
            ->assertDontSee('Stops panic attacks');
    }

    public function test_coping_toolkit_renders_accessible_local_only_practice_runner(): void
    {
        $student = $this->student();

        $response = $this->actingAs($student)->get(route('student.coping-toolkit'));

        $response->assertOk()
            ->assertSee('id="copingPracticeRunner"', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('data-practice-launch', false)
            ->assertSee('Nothing is saved or sent from this practice.')
            ->assertSee('Stop', false)
            ->assertSee('Pause', false)
            ->assertSee('Resume', false)
            ->assertSee('Restart', false)
            ->assertSee('Finish', false)
            ->assertSee('Cognitive Reframing')
            ->assertSee('Grounding Technique (5-4-3-2-1)');
    }

    public function test_coping_toolkit_remains_behind_existing_student_authentication(): void
    {
        $this->get(route('student.coping-toolkit'))
            ->assertRedirect(route('login'));
    }

    public function test_coping_search_finds_real_core_activities_by_title_and_relevant_keywords_without_duplicates(): void
    {
        $student = $this->student();
        CopingStrategy::create([
            'name' => 'Box Breathing',
            'description' => 'A paced breathing practice.',
            'category' => 'breathing',
            'instructions' => 'Breathe at an even, comfortable pace.',
            'is_active' => true,
        ]);

        $examples = [
            'breathing' => 'Box Breathing',
            'grounding' => 'Grounding Technique (5-4-3-2-1)',
            'journaling' => 'Expressive Journaling',
            'relaxation' => 'Progressive Muscle Relaxation',
            'stress' => 'Expressive Journaling',
            'focus' => 'Box Breathing',
        ];

        foreach ($examples as $query => $expectedActivity) {
            $response = $this->actingAs($student)->get(route('student.coping-toolkit', ['q' => $query]));
            $response->assertOk()->assertSee('Search coping activities');
            $html = $response->getContent();
            $resultsStart = strpos($html, 'id="matching-title"');
            $categoriesStart = strpos($html, 'aria-label="Filter coping activities by category"', $resultsStart);
            $this->assertNotFalse($resultsStart, "No result section was rendered for [$query].");
            $this->assertNotFalse($categoriesStart, "Category filters were not rendered for [$query].");
            $resultSection = substr($html, $resultsStart, $categoriesStart - $resultsStart);
            $this->assertStringContainsString($expectedActivity, $resultSection, "[$query] did not find its real activity.");
            $this->assertSame(1, substr_count($html, 'data-activity-name="Box Breathing"'), 'A matched practice must not be duplicated lower in the page.');
            $this->assertLessThan(strpos($html, 'aria-label="Filter coping activities by category"'), $resultsStart);
        }
    }

    public function test_coping_public_medlineplus_search_is_explicit_opt_in_and_separate_from_activities(): void
    {
        $student = $this->student();
        config(['services.wellness_resources.medlineplus.enabled' => true]);
        Cache::forget('wellness:medlineplus:reader-v2:' . sha1('grounding'));
        Http::fake([
            'https://wsearch.nlm.nih.gov/*' => Http::response(
                '<nlmSearchResult><list><document url="https://medlineplus.gov/grounding"><content name="title">Grounding information</content><content name="organizationName">National Library of Medicine</content><content name="snippet">An informational topic.</content></document></list></nlmSearchResult>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        $this->actingAs($student)->get(route('student.coping-toolkit', ['q' => 'grounding']))
            ->assertOk()
            ->assertSee('Include MedlinePlus')
            ->assertDontSee('Grounding information');
        Http::assertNothingSent();

        $response = $this->actingAs($student)->get(route('student.coping-toolkit', ['q' => 'grounding', 'public_sources' => 1]));
        $response->assertOk()
            ->assertSee('Grounding information')
            ->assertSee('Related wellness resources')
            ->assertSee('1 resource')
            ->assertSee('Only this topic will be sent for informational results.');
        Http::assertSent(fn ($request) => $request['term'] === 'grounding' && !str_contains($request['term'], 'student'));

        $html = $response->getContent();
        $activitiesAt = strpos($html, 'id="matching-title"');
        $resourcesAt = strpos($html, 'id="related-resources-title"');
        $this->assertNotFalse($activitiesAt);
        $this->assertNotFalse($resourcesAt);
        $this->assertLessThan($resourcesAt, $activitiesAt, 'Public reading must remain separate and below activity matches.');
    }

    public function test_legacy_psychosocial_and_peer_support_urls_redirect_to_canonical_modules(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->get(route('student.psychosocial'))
            ->assertRedirect(route('student.resources', ['section' => 'support']));

        $this->actingAs($student)
            ->get(route('student.peer-support'))
            ->assertRedirect(route('student.community'));
    }

    public function test_emergency_page_keeps_immediate_fallback_and_correct_hopeline_wording(): void
    {
        $student = $this->student();

        $response = $this->actingAs($student)->get(route('student.emergency-resources'));

        $response->assertOk()
            ->assertSee('Hopeline Philippines')
            ->assertSee('Toll-free calling option for Globe/TM subscribers.')
            ->assertDontSee('DSWD text service')
            ->assertDontSee('Text Hopeline');
    }

    public function test_community_feed_shows_only_moderated_messages_without_dead_actions(): void
    {
        $student = $this->student();
        CommunityMessage::create([
            'user_id' => $student->id,
            'message' => 'A moderated synthetic message.',
            'category' => 'general',
            'is_anonymous' => true,
            'is_approved' => true,
            'likes_count' => 0,
        ]);

        $response = $this->actingAs($student)->get(route('student.community'));

        $response->assertOk()
            ->assertSee('A moderated synthetic message.')
            ->assertSee('Community guidelines')
            ->assertDontSee('>Reply<')
            ->assertDontSee('>Share<');
    }
}
