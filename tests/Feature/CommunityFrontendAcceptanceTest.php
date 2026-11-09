<?php

namespace Tests\Feature;

use App\Models\CommunityMessage;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityFrontendAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_rendered_community_composer_exposes_safe_interaction_controls(): void
    {
        $viewer = User::factory()->create(['role' => 'student', 'name' => 'Synthetic viewer']);
        $author = User::factory()->create(['role' => 'student', 'name' => 'Synthetic named author']);
        foreach ([$viewer, $author] as $student) {
            app(PrivacyService::class)->updateSettings($student->id, [
                'allow_anonymous_community' => true,
                'consent_policy_version' => config('privacy.consent_policy_version'),
            ]);
        }

        CommunityMessage::create([
            'user_id' => $author->id,
            'title' => 'Frontend acceptance post',
            'message' => 'A safe synthetic message for rendered controls.',
            'category' => 'general',
            'is_anonymous' => false,
            'is_approved' => true,
            'moderation_status' => 'approved',
        ]);

        $response = $this->actingAs($viewer)->get(route('student.community'));

        $response->assertOk()
            ->assertSee('Community Circle')
            ->assertSee('One shared space for students and authorized MinSU staff.')
            ->assertSee('Everyday text posts appear right away; only posts needing a specific safety or media review are held.')
            ->assertSee('data-inline-composer="true"', false)
            ->assertSee('Share post')
            ->assertSee('Topic')
            ->assertSee('Post anonymously')
            ->assertSee('Conversation starters')
            ->assertDontSee('<section class="my-posts-panel"', false)
            ->assertDontSee('Pending Review')
            ->assertSee('View comments')
            ->assertSee('Add a supportive comment')
            ->assertSee('Block this community member')
            ->assertSee('Report...')
            ->assertSee('Loading comments...')
            ->assertSee('maxlength="1000"', false)
            ->assertSee('textContent')
            ->assertSee("credentials: 'same-origin'", false);
    }

    public function test_feed_and_my_posts_are_separate_center_column_views(): void
    {
        $student = User::factory()->create(['role' => 'student', 'name' => 'Synthetic owner']);
        app(PrivacyService::class)->updateSettings($student->id, [
            'allow_anonymous_community' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        CommunityMessage::create([
            'user_id' => $student->id,
            'message' => 'Synthetic pending private post.',
            'category' => 'general',
            'is_anonymous' => true,
            'is_approved' => false,
            'moderation_status' => 'pending',
        ]);

        $feed = $this->actingAs($student)->get(route('student.community', ['view' => 'feed']));
        $feed->assertOk()
            ->assertSee('data-inline-composer="true"', false)
            ->assertDontSee('Synthetic pending private post.')
            ->assertDontSee('<section class="my-posts-panel"', false);

        $mine = $this->actingAs($student)->get(route('student.community', ['view' => 'mine', 'status' => 'pending']));
        $mine->assertOk()
            ->assertSee('Your Community posts')
            ->assertSee('Synthetic pending private post.')
            ->assertSee('data-inline-composer="false"', false)
            ->assertDontSee('Conversations happening');
    }
}
