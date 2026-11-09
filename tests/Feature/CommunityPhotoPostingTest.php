<?php

namespace Tests\Feature;

use App\Models\CommunityMessage;
use App\Models\CommunityReaction;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityPhotoPostingTest extends TestCase
{
    use RefreshDatabase;

    private function consentingStudent(): User
    {
        $user = User::factory()->create(['role' => 'student']);
        app(PrivacyService::class)->updateSettings($user->id, [
            'allow_anonymous_community' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        return $user->fresh();
    }

    public function test_image_only_post_is_reencoded_and_served_only_from_private_route(): void
    {
        Storage::fake('local');
        $author = $this->consentingStudent();
        $viewer = $this->consentingStudent();

        $this->actingAs($author)->post(route('student.community.post'), [
            'image' => UploadedFile::fake()->image('synthetic-share.png', 640, 480),
            'is_anonymous' => 1,
        ])->assertRedirect();

        $message = CommunityMessage::query()->where('user_id', $author->id)->firstOrFail();
        $this->assertTrue($message->is_approved);
        $this->assertSame('approved', $message->moderation_status);
        $this->assertSame('', $message->message);
        $this->assertStringStartsWith('community_media/', (string) $message->image_path);
        $this->assertSame('general', $message->category);
        Storage::disk('local')->assertExists($message->image_path);

        $this->actingAs($viewer)
            ->get(route('student.community.image', $message->id))
            ->assertOk()
            ->assertHeader('content-type', 'image/webp')
            ->assertHeader('cache-control', 'max-age=0, no-store, private');
    }

    public function test_invalid_image_content_is_rejected_without_persisting_a_post(): void
    {
        Storage::fake('local');
        $student = $this->consentingStudent();

        $this->actingAs($student)
            ->from(route('student.community'))
            ->post(route('student.community.post'), [
                'message' => '',
                'image' => UploadedFile::fake()->create('not-an-image.jpg', 12, 'image/jpeg'),
            ])
            ->assertRedirect(route('student.community'))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('community_messages', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles('community_media'));
    }

    public function test_like_and_heart_are_distinct_and_switch_without_duplicates(): void
    {
        $author = $this->consentingStudent();
        $viewer = $this->consentingStudent();
        $message = CommunityMessage::create([
            'user_id' => $author->id,
            'message' => 'Approved synthetic post',
            'category' => 'general',
            'is_anonymous' => true,
            'is_approved' => true,
            'moderation_status' => 'approved',
        ]);

        $this->actingAs($viewer)->post(route('student.community.reaction', $message->id), ['reaction' => 'like'])->assertOk();
        $this->actingAs($viewer)->post(route('student.community.reaction', $message->id), ['reaction' => 'heart'])->assertOk();

        $this->assertDatabaseCount('community_reactions', 1);
        $this->assertDatabaseHas('community_reactions', [
            'community_message_id' => $message->id,
            'user_id' => $viewer->id,
            'reaction' => 'heart',
        ]);

        $this->actingAs($viewer)->delete(route('student.community.reaction', $message->id))->assertOk();
        $this->assertDatabaseCount('community_reactions', 0);
    }
}