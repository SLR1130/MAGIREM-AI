<?php

namespace Tests\Feature;

use App\Models\CommunityMessage;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityStaffModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_dashboards_link_to_shared_feed_and_staff_can_post(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        app(PrivacyService::class)->updateSettings($student->id, ['allow_anonymous_community' => true, 'consent_policy_version' => config('privacy.consent_policy_version')]);
        $counselor = User::factory()->create(['role' => 'counselor', 'name' => 'Test Counselor']);
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Test Admin']);

        $this->actingAs($counselor)->get(route('counselor.dashboard'))->assertOk()->assertSee(route('student.community'), false);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee(route('student.community'), false);
        $this->actingAs($counselor)->get(route('student.community'))->assertOk()
            ->assertSee('aria-label="Counselor workspace navigation"', false)
            ->assertSee('Community Circle');
        $this->actingAs($admin)->get(route('student.community'))->assertOk()
            ->assertSee('aria-label="Administrator navigation"', false)
            ->assertSee('Community Circle')
            ->assertDontSee('Community moderation');

        foreach ([$counselor, $admin] as $staff) {
            $this->actingAs($staff)->getJson(route('student.community.data'))->assertOk()->assertJsonPath('viewer.can_interact', true);
            $this->actingAs($staff)->postJson(route('student.community.post'), [
                'message' => 'A supportive synthetic update from '.$staff->name.'.',
                'category' => 'general',
                'is_anonymous' => true,
            ])->assertCreated();
            $this->assertDatabaseHas('community_messages', ['user_id' => $staff->id, 'is_anonymous' => false]);
        }

        $this->actingAs($student)->getJson(route('student.community.data'))->assertOk()
            ->assertSee('Authorized Counselor')->assertSee('Guidance Admin')
            ->assertSee('A supportive synthetic update from Test Counselor.')
            ->assertSee('A supportive synthetic update from Test Admin.');

        $pendingCounselor = User::factory()->create(['role' => 'counselor', 'approval_status' => 'pending']);
        $this->actingAs($pendingCounselor)->get(route('student.community'))->assertRedirect(route('account.status'));
        $this->actingAs($pendingCounselor)->postJson(route('student.community.post'), ['message' => 'Unauthorized', 'category' => 'general'])->assertRedirect(route('account.status'));
        $this->assertDatabaseMissing('community_messages', ['user_id' => $pendingCounselor->id]);
    }

    public function test_only_admin_can_hide_and_restore_post_without_deleting_media(): void
    {
        Storage::fake('local');
        $student = User::factory()->create(['role' => 'student']);
        app(PrivacyService::class)->updateSettings($student->id, ['allow_anonymous_community' => true, 'consent_policy_version' => config('privacy.consent_policy_version')]);
        $counselor = User::factory()->create(['role' => 'counselor']);
        $admin = User::factory()->create(['role' => 'admin']);
        $image = 'community_media/'.str_repeat('a', 40).'.webp';
        Storage::disk('local')->put($image, 'synthetic test image');
        $post = CommunityMessage::create(['user_id' => $student->id, 'message' => 'Synthetic moderation test post.', 'category' => 'general', 'is_anonymous' => true, 'is_approved' => true, 'moderation_status' => 'approved', 'is_pinned' => true, 'image_path' => $image]);

        foreach ([$student, $counselor] as $unauthorized) {
            $this->actingAs($unauthorized)->post(route('admin.community-moderation.hide', $post->id), ['reason' => 'Synthetic safety review'])->assertForbidden();
            $this->actingAs($unauthorized)->post(route('admin.community-moderation.restore', $post->id))->assertForbidden();
        }
        $this->actingAs($admin)->post(route('admin.community-moderation.hide', $post->id), [])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('admin.community-moderation.hide', $post->id), ['reason' => 'Synthetic safety review'])->assertRedirect();
        $this->assertDatabaseHas('community_messages', ['id' => $post->id, 'is_approved' => false, 'is_pinned' => false, 'image_path' => $image]);
        Storage::disk('local')->assertExists($image);
        $this->assertDatabaseHas('community_moderation_audits', ['community_message_id' => $post->id, 'moderator_id' => $admin->id, 'action' => 'hide', 'notes' => 'Synthetic safety review']);
        $this->actingAs($counselor)->getJson(route('student.community.data'))->assertOk()->assertDontSee('Synthetic moderation test post.');
        $this->actingAs($admin)->get(route('admin.community-moderation'))->assertRedirect(route('student.community'));

        $this->actingAs($admin)->post(route('admin.community-moderation.restore', $post->id))->assertRedirect();
        $this->assertDatabaseHas('community_messages', ['id' => $post->id, 'is_approved' => true, 'is_pinned' => false, 'image_path' => $image]);
        $this->assertDatabaseHas('community_moderation_audits', ['community_message_id' => $post->id, 'moderator_id' => $admin->id, 'action' => 'restore']);
        $this->actingAs($student)->getJson(route('student.community.data'))->assertOk()->assertSee('Synthetic moderation test post.');
        $this->actingAs($admin)->post(route('admin.community-moderation.restore', $post->id))->assertStatus(409);
    }

    public function test_admin_moderation_controls_are_served_from_the_same_feed_without_hiding_posts_from_users(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        app(PrivacyService::class)->updateSettings($student->id, ['allow_anonymous_community' => true, 'consent_policy_version' => config('privacy.consent_policy_version')]);
        $admin = User::factory()->create(['role' => 'admin']);
        $published = CommunityMessage::create(['user_id' => $student->id, 'message' => 'A normal synthetic feelings post.', 'category' => 'general', 'is_approved' => true, 'moderation_status' => 'approved']);
        $pending = CommunityMessage::create(['user_id' => $student->id, 'message' => 'A synthetic post that needs review.', 'category' => 'general', 'is_approved' => false, 'moderation_status' => 'pending']);
        $flagged = CommunityMessage::create(['user_id' => $student->id, 'message' => 'A synthetic safety-held post.', 'category' => 'general', 'is_approved' => false, 'moderation_status' => 'flagged']);

        $this->actingAs($admin)->postJson(route('admin.community-moderation.pin', $published->id), ['pinned' => true])
            ->assertOk()->assertJsonPath('success', true);
        $this->actingAs($admin)->getJson(route('student.community.data'))
            ->assertOk()->assertJsonPath('viewer.can_moderate', true)
            ->assertJsonPath('pagination.total', 3)
            ->assertJsonFragment(['id' => $published->id, 'is_approved' => true, 'is_pinned' => true])
            ->assertJsonFragment(['id' => $pending->id, 'moderation_status' => 'pending', 'is_approved' => false])
            ->assertJsonFragment(['id' => $flagged->id, 'moderation_status' => 'flagged', 'is_approved' => false]);
        $this->actingAs($admin)->getJson(route('student.community.data', ['moderation' => 'needs_review']))
            ->assertOk()->assertJsonPath('pagination.total', 2);
        $this->actingAs($student)->getJson(route('student.community.data'))
            ->assertOk()->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('posts.0.moderation_status', null);

        $this->actingAs($admin)->postJson(route('admin.community-moderation.approve', $pending->id))
            ->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('community_messages', ['id' => $pending->id, 'is_approved' => true]);
        $this->actingAs($admin)->postJson(route('admin.community-moderation.hide', $published->id), ['reason' => 'Synthetic inline review'])
            ->assertOk()->assertJsonPath('success', true);
        $this->actingAs($admin)->getJson(route('student.community.data', ['moderation' => 'hidden']))
            ->assertOk()->assertJsonPath('pagination.total', 1)->assertJsonFragment(['id' => $published->id, 'can_restore' => true]);
        $this->actingAs($admin)->postJson(route('admin.community-moderation.restore', $published->id))
            ->assertOk()->assertJsonPath('success', true);
        $this->actingAs($admin)->getJson(route('student.community.data'))
            ->assertOk()->assertJsonPath('pagination.total', 3);
    }
}
