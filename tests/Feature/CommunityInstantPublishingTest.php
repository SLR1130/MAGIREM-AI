<?php

namespace Tests\Feature;

use App\Models\CommunityMessage;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityInstantPublishingTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $name, string $campus): User
    {
        $user = User::factory()->create([
            'role' => 'student',
            'name' => $name,
            'program' => $campus,
        ]);
        app(PrivacyService::class)->updateSettings($user->id, [
            'allow_anonymous_community' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        return $user->fresh();
    }

    public function test_safe_post_publishes_across_campuses_and_supports_social_interactions(): void
    {
        $a = $this->student('Synthetic A', 'Bongabong');
        $b = $this->student('Synthetic B', 'Calapan');
        $c = $this->student('Synthetic C', 'Victoria');

        $this->actingAs($a)->post(route('student.community.post'), [
            'message' => 'A safe synthetic campus post shared immediately.',
            'category' => 'general',
            'is_anonymous' => 1,
        ])->assertRedirect()->assertSessionHas('success', 'Your post has been shared with the community.');

        $post = CommunityMessage::where('user_id', $a->id)->latest('id')->firstOrFail();
        $this->assertTrue($post->is_approved);
        $this->assertSame('approved', $post->moderation_status);

        foreach ([$b, $c] as $viewer) {
            $this->actingAs($viewer)->get(route('student.community'))
                ->assertOk()
                ->assertSee('A safe synthetic campus post shared immediately.')
                ->assertSee('Anonymous')
                ->assertDontSee($a->name);
        }

        $this->actingAs($b)->postJson(route('student.community.reaction', $post->id), ['reaction' => 'like'])
            ->assertOk()->assertJsonPath('like_count', 1);
        $this->actingAs($c)->postJson(route('student.community.reaction', $post->id), ['reaction' => 'heart'])
            ->assertOk()->assertJsonPath('heart_count', 1);
        $this->actingAs($b)->post(route('student.post.comment', $post->id), ['content' => 'Synthetic supportive comment'])->assertRedirect();

        $this->assertDatabaseHas('post_comments', ['post_id' => $post->id, 'user_id' => $b->id]);
        $this->actingAs($a)->getJson(route('student.post.comments', $post->id))
            ->assertOk()->assertJsonFragment(['content' => 'Synthetic supportive comment']);
    }

    public function test_explicit_safety_signal_is_flagged_and_visible_only_to_its_author_until_review(): void
    {
        $author = $this->student('Synthetic safety author', 'Bongabong');
        $viewer = $this->student('Synthetic safety viewer', 'Calapan');

        $this->actingAs($author)->post(route('student.community.post'), [
            'message' => 'I want to kill myself right now.',
            'category' => 'general',
            'is_anonymous' => 1,
        ])->assertRedirect()->assertSessionHas('success', 'Your post is under review. You can find it in My Posts.');

        $post = CommunityMessage::where('user_id', $author->id)->latest('id')->firstOrFail();
        $this->assertFalse($post->is_approved);
        $this->assertSame('flagged', $post->moderation_status);
        $this->assertStringContainsString('Automatic safety hold', (string) $post->moderation_notes);

        $this->actingAs($viewer)->get(route('student.community'))
            ->assertOk()->assertDontSee('I want to kill myself right now.');
        $this->actingAs($author)->get(route('student.community', ['view' => 'mine', 'status' => 'flagged']))
            ->assertOk()->assertSee('Safety review')->assertSee('I want to kill myself right now.');
    }

    public function test_flagged_photo_is_private_to_author_and_available_only_to_admin_review(): void
    {
        Storage::fake('local');
        $author = $this->student('Synthetic photo author', 'Bongabong');
        $viewer = $this->student('Synthetic photo viewer', 'Calapan');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($author)->post(route('student.community.post'), [
            'message' => 'I want to kill myself right now.',
            'image' => UploadedFile::fake()->image('safety-photo.png', 320, 240),
            'is_anonymous' => 1,
        ])->assertRedirect();

        $post = CommunityMessage::where('user_id', $author->id)->latest('id')->firstOrFail();
        $this->assertSame('flagged', $post->moderation_status);
        $this->actingAs($author)->get(route('student.community.image', $post->id))->assertOk();
        $this->actingAs($viewer)->get(route('student.community.image', $post->id))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.community-moderation.image', $post->id))->assertOk();
    }

    public function test_ordinary_stress_and_support_language_is_not_held_for_review(): void
    {
        $student = $this->student('Synthetic stressed student', 'Victoria');

        $this->actingAs($student)->post(route('student.community.post'), [
            'message' => 'I feel stressed and sad about my exam and could use encouragement.',
            'category' => 'academic',
        ])->assertRedirect();

        $post = CommunityMessage::where('user_id', $student->id)->latest('id')->firstOrFail();
        $this->assertTrue($post->is_approved);
        $this->assertSame('approved', $post->moderation_status);
    }
}