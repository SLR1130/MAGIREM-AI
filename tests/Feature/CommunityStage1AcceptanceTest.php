<?php

namespace Tests\Feature;

use App\Models\CommunityMessage;
use App\Models\CommunityReport;
use App\Models\Notification;
use App\Models\PostComment;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityStage1AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $name, string $program): User
    {
        $student = User::factory()->create([
            'role' => 'student',
            'name' => $name,
            'program' => $program,
        ]);

        app(PrivacyService::class)->updateSettings($student->id, [
            'allow_anonymous_community' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        return $student->fresh();
    }

    public function test_three_verified_students_can_interact_and_recover_saved_content(): void
    {
        $a = $this->student('Student A - MinSU Bongabong', 'Bongabong');
        $b = $this->student('Student B - MinSU Calapan', 'Calapan');
        $c = $this->student('Student C - MinSU Victoria', 'Victoria');
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Synthetic moderator']);

        $this->assertTrue($a->community_verified_at !== null);
        $this->assertTrue($b->community_verified_at !== null);
        $this->assertTrue($c->community_verified_at !== null);

        // A submits through the same route used by the Blade composer. Moderation
        // is explicit before the other students can see the post.
        $this->actingAs($a)->post(route('student.community.post'), [
            'title' => 'Synthetic campus study check-in',
            'message' => 'A synthetic post for the multi-student acceptance flow.',
            'category' => 'academic',
            'support_label' => 'encouragement',
            'is_anonymous' => 1,
        ])->assertRedirect();
        $post = CommunityMessage::where('user_id', $a->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.community-moderation.approve', $post->id))->assertRedirect();
        $post->refresh();

        // B sees the approved post, comments, and C replies to B.
        $this->actingAs($b)->get(route('student.community'))
            ->assertOk()
            ->assertSee('Synthetic campus study check-in')
            ->assertSee('Anonymous')
            ->assertDontSee($a->name)
            ->assertDontSee($a->email);
        $this->actingAs($b)->post(route('student.post.comment', $post->id), [
            'content' => 'Synthetic supportive comment from B',
        ])->assertRedirect();
        $comment = PostComment::where('post_id', $post->id)->where('user_id', $b->id)->firstOrFail();
        $this->actingAs($c)->post(route('student.post.comment', $post->id), [
            'content' => 'Synthetic reply from C',
            'parent_id' => $comment->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $a->id,
            'type' => 'community_comment',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $b->id,
            'type' => 'community_reply',
        ]);

        // Both reactions are admitted through the common route and counts are persisted.
        $this->actingAs($b)->postJson(route('student.community.reaction', $post->id), ['reaction' => 'support'])
            ->assertOk()->assertJsonPath('likes_count', 1);
        $this->actingAs($c)->postJson(route('student.community.reaction', $post->id), ['reaction' => 'support'])
            ->assertOk()->assertJsonPath('likes_count', 2);
        $this->assertSame(2, (int) $post->fresh()->likes_count);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $a->id,
            'type' => 'community_reaction',
        ]);

        // A can edit; B cannot. Editing returns a post to moderation and the
        // moderator can publish the edited version without replacing its record.
        $this->actingAs($b)->put(route('student.community.update', $post->id), [
            'message' => 'Unauthorized synthetic edit',
            'category' => 'academic',
        ])->assertForbidden();
        $this->actingAs($a)->put(route('student.community.update', $post->id), [
            'title' => 'Edited synthetic campus study check-in',
            'message' => 'Edited synthetic post retained under the original record.',
            'category' => 'academic',
            'support_label' => 'sharing',
        ])->assertRedirect();
        $this->assertDatabaseHas('community_messages', [
            'id' => $post->id,
            'moderation_status' => 'pending',
            'is_approved' => 0,
        ]);
        $this->actingAs($admin)->post(route('admin.community-moderation.approve', $post->id))->assertRedirect();

        // C reports; only the administrator can review the confidential report.
        $this->actingAs($c)->post(route('student.community.report'), [
            'type' => 'post',
            'target_id' => $post->id,
            'reason' => 'harmful',
        ])->assertRedirect();
        $report = CommunityReport::where('reporter_id', $c->id)->firstOrFail();
        $this->actingAs($b)->get(route('admin.community-reports'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.community-reports.review', $report->id), [
            'status' => 'reviewed',
            'moderator_notes' => 'Synthetic acceptance review',
        ])->assertRedirect();
        $this->assertDatabaseHas('community_reports', ['id' => $report->id, 'reviewed_by' => $admin->id, 'status' => 'reviewed']);

        // Blocking is reciprocal for interactions and feed visibility.
        $this->actingAs($a)->post(route('student.community.block', $b->id))->assertRedirect();
        $this->actingAs($b)->postJson(route('student.community.reaction', $post->id), ['reaction' => 'helpful'])->assertForbidden();
        $this->actingAs($b)->post(route('student.post.comment', $post->id), ['content' => 'Blocked synthetic comment'])->assertForbidden();
        $this->actingAs($b)->get(route('student.community'))->assertOk()->assertDontSee('Edited synthetic campus study check-in');

        // Verify notification ownership through the authenticated API, then perform
        // a real logout/login cycle and recover the same post and comments from MySQL.
        $this->actingAs($a)->get(route('student.notifications.get'))->assertOk()->assertJsonPath('notifications.0.user_id', $a->id);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post('/login', ['email' => $a->email, 'password' => 'password'])->assertRedirect();
        $this->get(route('student.community'))->assertOk()->assertSee('Edited synthetic campus study check-in');
        $this->getJson(route('student.post.comments', $post->id))->assertOk()
            ->assertJsonFragment(['content' => 'Synthetic supportive comment from B'])
            ->assertJsonFragment(['content' => 'Synthetic reply from C']);
    }

    public function test_anonymous_post_does_not_expose_owner_or_private_records(): void
    {
        $author = $this->student('Anonymous acceptance author', 'Synthetic campus');
        $viewer = $this->student('Synthetic viewer', 'Synthetic campus');
        $post = CommunityMessage::create([
            'user_id' => $author->id,
            'message' => 'Approved anonymous acceptance post.',
            'category' => 'general',
            'is_anonymous' => true,
            'is_approved' => true,
            'moderation_status' => 'approved',
        ]);

        $this->actingAs($viewer)->get(route('student.community'))->assertOk()
            ->assertSee('Anonymous')
            ->assertDontSee($author->name)
            ->assertDontSee($author->email)
            ->assertDontSee(route('student.user-profile', $author->id));

        $reaction = $this->actingAs($viewer)->postJson(route('student.community.reaction', $post->id), ['reaction' => 'support']);
        $reaction->assertOk()->assertJsonMissingPath('user_id')->assertJsonMissingPath('author');

        $this->actingAs($viewer)->post(route('student.post.comment', $post->id), ['content' => 'Public synthetic comment'])->assertRedirect();
        $comments = $this->actingAs($viewer)->getJson(route('student.post.comments', $post->id))->assertOk();
        $comments->assertJsonMissingPath('comments.data.0.email')->assertJsonMissingPath('comments.data.0.student_id');
        $this->assertStringNotContainsString($author->email, $comments->getContent());
        if (!empty($author->student_id)) {
            $this->assertStringNotContainsString($author->student_id, $comments->getContent());
        }
    }

    public function test_student_role_is_sufficient_but_consent_and_active_status_are_required_for_community_mutations(): void
    {
        $studentWithoutLegacyVerification = User::factory()->unverified()->create(['role' => 'student']);
        $noConsent = User::factory()->create(['role' => 'student']);
        $inactive = User::factory()->create(['role' => 'student', 'is_active' => false]);
        app(PrivacyService::class)->resetToDefaults($noConsent->id);
        app(PrivacyService::class)->updateSettings($studentWithoutLegacyVerification->id, [
            'allow_anonymous_community' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        CommunityMessage::create([
            'user_id' => $studentWithoutLegacyVerification->id,
            'message' => 'Approved synthetic role-access post.',
            'category' => 'general',
            'is_anonymous' => true,
            'is_approved' => true,
            'moderation_status' => 'approved',
        ]);

        $payload = ['message' => 'Synthetic restricted community action', 'category' => 'general'];
        $this->actingAs($studentWithoutLegacyVerification)->post(route('student.community.post'), $payload)->assertRedirect();
        $this->actingAs($noConsent)->post(route('student.community.post'), $payload)->assertForbidden();
        $this->actingAs($inactive)->post(route('student.community.post'), $payload)->assertRedirect(route('login'));
        $this->assertGuest();
        $this->actingAs($studentWithoutLegacyVerification)->get(route('student.community'))->assertOk()->assertSee('id="is_anonymous"', false)->assertDontSee('pending MinSU enrollment verification');
        $this->actingAs($noConsent)->get(route('student.community'))->assertOk()->assertSee('Consent required to interact')->assertDontSee('id="is_anonymous"', false)->assertDontSee('View comments');
    }
}
