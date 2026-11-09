<?php

namespace Tests\Feature;

use App\Models\CommunityMessage;
use App\Models\CommunityReaction;
use App\Models\CommunityReport;
use App\Models\PostComment;
use App\Models\Resource;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityStage1Test extends TestCase
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

    public function test_student_without_legacy_enrollment_marker_can_create_a_post_after_community_consent(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'student']);
        app(PrivacyService::class)->updateSettings($user->id, [
            'allow_anonymous_community' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);

        $this->actingAs($user)->post(route('student.community.post'), [
            'message' => 'Synthetic role-authorized post',
            'category' => 'general',
        ])->assertRedirect();

        $this->assertDatabaseCount('community_messages', 1);
    }

    public function test_guests_are_blocked_and_approved_counselors_and_admins_share_the_feed(): void
    {
        $payload = ['message' => 'Synthetic unauthorized action', 'category' => 'general'];
        $this->post(route('student.community.post'), $payload)->assertRedirect(route('login'));

        $student = $this->consentingStudent();
        $counselor = User::factory()->create(['role' => 'counselor', 'name' => 'Synthetic Authorized Counselor']);
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Synthetic Guidance Admin']);

        $this->actingAs($counselor)->post(route('student.community.post'), $payload + ['is_anonymous' => 1])->assertRedirect();
        $counselorPost = CommunityMessage::where('user_id', $counselor->id)->firstOrFail();
        $this->assertFalse($counselorPost->is_anonymous, 'Staff anonymity must be ignored server-side.');

        $this->actingAs($admin)->post(route('student.community.post'), [
            'message' => 'Synthetic Guidance announcement in the shared feed.',
            'category' => 'general',
            'is_anonymous' => 1,
        ])->assertRedirect();
        $adminPost = CommunityMessage::where('user_id', $admin->id)->firstOrFail();
        $this->assertFalse($adminPost->is_anonymous);

        $this->actingAs($student)->get(route('student.community'))->assertOk()
            ->assertSee($counselor->name)
            ->assertSee('Authorized Counselor')
            ->assertSee('Synthetic Guidance announcement in the shared feed.')
            ->assertSee('Guidance Admin');

        $this->actingAs($counselor)->post(route('student.post.comment', $adminPost->id), ['content' => 'A supportive staff comment.'])->assertRedirect();
        $this->actingAs($student)->postJson(route('student.community.reaction', $adminPost->id), ['reaction' => 'heart'])->assertOk();
        $this->assertDatabaseHas('post_comments', ['post_id' => $adminPost->id, 'user_id' => $counselor->id]);
        $this->assertDatabaseHas('community_reactions', ['community_message_id' => $adminPost->id, 'user_id' => $student->id, 'reaction' => 'heart']);

        foreach (['pending', 'rejected', 'suspended'] as $status) {
            $restricted = User::factory()->create(['role' => 'student', 'approval_status' => $status]);
            $this->actingAs($restricted)->get(route('student.community'))->assertRedirect(route('account.status'));
        }
    }

    public function test_students_are_public_by_default_and_must_explicitly_select_anonymous_posting(): void
    {
        $student = $this->consentingStudent();

        $this->actingAs($student)->post(route('student.community.post'), [
            'message' => 'A synthetic named community post.',
            'category' => 'general',
        ])->assertRedirect();
        $named = CommunityMessage::where('user_id', $student->id)->firstOrFail();
        $this->assertFalse($named->is_anonymous);

        $this->actingAs($student)->post(route('student.community.post'), [
            'message' => 'A synthetic anonymous community post.',
            'category' => 'general',
            'is_anonymous' => 1,
        ])->assertRedirect();
        $anonymous = CommunityMessage::where('user_id', $student->id)->where('is_anonymous', true)->firstOrFail();
        $response = $this->actingAs($this->consentingStudent())->get(route('student.community'));
        $response->assertOk()
            ->assertSee('A synthetic anonymous community post.')
            ->assertSee('Anonymous student');

        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $card = (new \DOMXPath($document))->query('//*[@id="community-post-' . $anonymous->id . '"]')->item(0);
        $this->assertNotNull($card, 'The published anonymous post should render as a feed card.');
        $this->assertStringNotContainsString($student->name, $card->textContent, 'The anonymous post card must not reveal its author.');
    }

    public function test_posts_from_inactive_or_unapproved_accounts_are_not_visible_in_the_shared_feed(): void
    {
        $author = $this->consentingStudent();
        $post = CommunityMessage::create([
            'user_id' => $author->id,
            'message' => 'Synthetic post by an account that later became inactive.',
            'category' => 'general',
            'is_approved' => true,
            'moderation_status' => 'approved',
        ]);
        $author->forceFill(['is_active' => false])->save();

        $this->actingAs($this->consentingStudent())->get(route('student.community'))
            ->assertOk()
            ->assertDontSee($post->message);

        $author->forceFill(['is_active' => true, 'approval_status' => 'pending'])->save();
        $this->actingAs($this->consentingStudent())->get(route('student.community'))
            ->assertOk()
            ->assertDontSee($post->message);
    }

    public function test_shared_feed_filters_posts_by_campus_and_topic(): void
    {
        $bongabongAuthor = $this->consentingStudent();
        $bongabongAuthor->forceFill(['campus' => 'Bongabong Campus'])->save();
        $calapanAuthor = $this->consentingStudent();
        $calapanAuthor->forceFill(['campus' => 'Calapan Campus'])->save();
        $viewer = $this->consentingStudent();

        CommunityMessage::create([
            'user_id' => $bongabongAuthor->id,
            'message' => 'Synthetic Bongabong academic post.',
            'category' => 'academic',
            'is_approved' => true,
            'moderation_status' => 'approved',
        ]);
        CommunityMessage::create([
            'user_id' => $calapanAuthor->id,
            'message' => 'Synthetic Calapan general post.',
            'category' => 'general',
            'is_approved' => true,
            'moderation_status' => 'approved',
        ]);

        $this->actingAs($viewer)->get(route('student.community', [
            'campus' => 'Bongabong Campus',
            'category' => 'academic',
        ]))->assertOk()
            ->assertSee('Synthetic Bongabong academic post.')
            ->assertDontSee('Synthetic Calapan general post.');
    }

    public function test_react_feed_boundary_returns_only_the_safe_public_projection(): void
    {
        $author = $this->consentingStudent();
        $viewer = $this->consentingStudent();
        $post = CommunityMessage::create([
            'user_id' => $author->id,
            'message' => 'Synthetic React boundary post.',
            'category' => 'general',
            'is_anonymous' => true,
            'is_approved' => true,
            'moderation_status' => 'approved',
        ]);

        $response = $this->actingAs($viewer)->getJson(route('student.community.data'));
        $response->assertOk()
            ->assertJsonPath('posts.0.id', $post->id)
            ->assertJsonPath('posts.0.author.name', 'Anonymous student')
            ->assertJsonPath('posts.0.author.anonymous', true)
            ->assertJsonPath('urls.post', route('student.community.post'))
            ->assertJsonMissingPath('posts.0.user_id')
            ->assertJsonMissingPath('posts.0.author.id')
            ->assertJsonMissingPath('posts.0.author.email')
            ->assertJsonMissingPath('posts.0.author.student_id');
    }

    public function test_community_blade_mounts_react_only_alongside_the_server_rendered_fallback(): void
    {
        $student = $this->consentingStudent();

        $this->actingAs($student)->get(route('student.community'))
            ->assertOk()
            ->assertSee('community-react-root', false)
            ->assertSee('community-legacy-fallback', false)
            ->assertSee(route('student.community.data'), false)
            ->assertSee('community-react', false);
    }

    public function test_react_json_mutations_keep_existing_community_authorization_and_responses(): void
    {
        $user = $this->consentingStudent();

        $created = $this->actingAs($user)->postJson(route('student.community.post'), [
            'message' => 'Synthetic JSON community creation.',
            'category' => 'general',
        ])->assertCreated()->assertJsonPath('success', true);

        $postId = $created->json('post_id');
        $this->assertDatabaseHas('community_messages', ['id' => $postId, 'user_id' => $user->id]);

        $this->actingAs($user)->postJson(route('student.post.comment', $postId), [
            'content' => 'Synthetic JSON comment.',
        ])->assertCreated()->assertJsonPath('success', true);

        $this->actingAs($user)->postJson(route('student.community.report'), [
            'type' => 'post',
            'target_id' => $postId,
            'reason' => 'other',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_rich_posts_preserve_only_approved_editor_nodes_marks_and_style_metadata(): void
    {
        $user = $this->consentingStudent();
        $document = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'A supported ', 'marks' => [['type' => 'bold']]],
                    ['type' => 'text', 'text' => 'community update 🌱'],
                ],
            ]],
        ];
        $style = ['background' => 'sage-grid', 'font' => 'lora', 'size' => 'display'];

        $created = $this->actingAs($user)->postJson(route('student.community.post'), [
            'post_format' => 'rich',
            'category' => 'general',
            'rich_content' => json_encode($document, JSON_THROW_ON_ERROR),
            'post_style' => json_encode($style, JSON_THROW_ON_ERROR),
        ])->assertCreated()->assertJsonPath('success', true);

        $post = CommunityMessage::findOrFail($created->json('post_id'));
        $this->assertSame('rich', $post->post_format);
        $this->assertSame($document, $post->rich_content);
        $this->assertSame($style, $post->post_style);
        $this->assertSame('A supported community update 🌱', $post->message);

        $this->actingAs($user)->getJson(route('student.community.data'))
            ->assertOk()
            ->assertJsonPath('posts.0.post_format', 'rich')
            ->assertJsonPath('posts.0.rich_content.content.0.content.0.marks.0.type', 'bold')
            ->assertJsonPath('posts.0.post_style.background', 'sage-grid')
            ->assertJsonMissingPath('posts.0.author.id');

        $this->actingAs($user)->putJson(route('student.community.update', $post->id), [
            'post_format' => 'rich',
            'category' => 'motivation',
            'rich_content' => json_encode($document, JSON_THROW_ON_ERROR),
            'post_style' => json_encode(['background' => 'forest-dots', 'font' => 'jakarta', 'size' => 'compact'], JSON_THROW_ON_ERROR),
        ])->assertOk();

        $post->refresh();
        $this->assertSame('pending', $post->moderation_status);
        $this->assertFalse($post->is_approved);
        $this->assertSame('forest-dots', $post->post_style['background']);
    }

    public function test_rich_posts_reject_html_like_nodes_and_unapproved_style_values(): void
    {
        $user = $this->consentingStudent();
        $unsafeDocument = ['type' => 'doc', 'content' => [['type' => 'script', 'content' => [['type' => 'text', 'text' => 'not allowed']]]]];

        $this->actingAs($user)->postJson(route('student.community.post'), [
            'post_format' => 'rich',
            'category' => 'general',
            'rich_content' => json_encode($unsafeDocument, JSON_THROW_ON_ERROR),
            'post_style' => json_encode(['background' => 'canvas', 'font' => 'jakarta', 'size' => 'standard'], JSON_THROW_ON_ERROR),
        ])->assertUnprocessable()->assertJsonValidationErrors('rich_content');

        $document = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A safe enough message']]]]];
        $this->actingAs($user)->postJson(route('student.community.post'), [
            'post_format' => 'rich',
            'category' => 'general',
            'rich_content' => json_encode($document, JSON_THROW_ON_ERROR),
            'post_style' => json_encode(['background' => 'arbitrary-css', 'font' => 'unknown', 'size' => 'huge'], JSON_THROW_ON_ERROR),
        ])->assertUnprocessable()->assertJsonValidationErrors('post_style');

        $this->assertDatabaseCount('community_messages', 0);
    }

    public function test_rich_document_text_styles_alignment_and_nested_lists_survive_publish_edit_and_feed_refresh(): void
    {
        $user = $this->consentingStudent();
        $document = [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'attrs' => ['textAlign' => 'center'], 'content' => [
                    ['type' => 'text', 'text' => 'A clearly formatted community message', 'marks' => [[
                        'type' => 'textStyle',
                        'attrs' => ['fontFamily' => 'DM Sans Variable', 'fontSize' => '18px', 'color' => '#2f6b4f'],
                    ]]],
                ]],
                ['type' => 'heading', 'attrs' => ['level' => 3, 'textAlign' => 'right'], 'content' => [
                    ['type' => 'text', 'text' => 'A short section heading'],
                ]],
                ['type' => 'bulletList', 'content' => [[
                    'type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A nested list item']]]],
                ]]],
                ['type' => 'orderedList', 'attrs' => ['start' => 2, 'type' => 'A'], 'content' => [[
                    'type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A numbered item']]]],
                ]]],
            ],
        ];
        $payload = [
            'post_format' => 'rich',
            'category' => 'general',
            'rich_content' => json_encode($document, JSON_THROW_ON_ERROR),
            'post_style' => json_encode(['background' => 'sunrise-wash', 'font' => 'dm-sans', 'size' => 'standard'], JSON_THROW_ON_ERROR),
        ];

        $created = $this->actingAs($user)->postJson(route('student.community.post'), $payload)->assertCreated();
        $post = CommunityMessage::findOrFail($created->json('post_id'));
        $saved = $post->rich_content;
        $this->assertSame('center', $saved['content'][0]['attrs']['textAlign']);
        $this->assertSame('DM Sans Variable', $saved['content'][0]['content'][0]['marks'][0]['attrs']['fontFamily']);
        $this->assertSame('18px', $saved['content'][0]['content'][0]['marks'][0]['attrs']['fontSize']);
        $this->assertSame('#2f6b4f', $saved['content'][0]['content'][0]['marks'][0]['attrs']['color']);
        $this->assertSame(3, $saved['content'][1]['attrs']['level']);
        $this->assertSame(2, $saved['content'][3]['attrs']['start']);
        $this->assertSame('A', $saved['content'][3]['attrs']['type']);
        $this->assertStringContainsString('A clearly formatted community message', $post->message);
        $this->assertStringContainsString('A numbered item', $post->message);

        $this->actingAs($user)->putJson(route('student.community.update', $post->id), $payload)->assertOk();
        $this->assertSame($saved, $post->fresh()->rich_content);
        $this->actingAs($user)->getJson(route('student.community.data'))
            ->assertOk()
            ->assertJsonPath('my_posts.0.rich_content.content.0.attrs.textAlign', 'center')
            ->assertJsonPath('my_posts.0.rich_content.content.0.content.0.marks.0.attrs.fontFamily', 'DM Sans Variable')
            ->assertJsonPath('my_posts.0.rich_content.content.0.content.0.marks.0.attrs.fontSize', '18px')
            ->assertJsonPath('my_posts.0.rich_content.content.1.attrs.level', 3)
            ->assertJsonPath('my_posts.0.rich_content.content.3.attrs.start', 2)
            ->assertJsonPath('my_posts.0.rich_content.content.3.attrs.type', 'A');
    }

    public function test_rich_content_rejects_unapproved_text_style_values_and_attributes(): void
    {
        $user = $this->consentingStudent();
        foreach ([
            ['fontFamily' => 'Arial, sans-serif'],
            ['fontSize' => '18.5px'],
            ['color' => 'url(https://example.invalid)'],
            ['fontWeight' => '900'],
        ] as $attrs) {
            $document = ['type' => 'doc', 'content' => [[
                'type' => 'paragraph', 'content' => [[
                    'type' => 'text', 'text' => 'Reject unapproved styling safely', 'marks' => [['type' => 'textStyle', 'attrs' => $attrs]],
                ]],
            ]]];
            $this->actingAs($user)->postJson(route('student.community.post'), [
                'post_format' => 'rich',
                'category' => 'general',
                'rich_content' => json_encode($document, JSON_THROW_ON_ERROR),
                'post_style' => json_encode(['background' => 'canvas', 'font' => 'jakarta', 'size' => 'standard'], JSON_THROW_ON_ERROR),
            ])->assertUnprocessable()->assertJsonValidationErrors('rich_content');
        }
        $this->assertDatabaseCount('community_messages', 0);
    }

    public function test_rich_post_photo_reuses_private_reencoding_and_authorized_retrieval(): void
    {
        Storage::fake('local');
        $author = $this->consentingStudent();
        $document = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A rich post with an attached synthetic image']]]]];

        $response = $this->actingAs($author)->post(route('student.community.post'), [
            'post_format' => 'rich',
            'category' => 'general',
            'rich_content' => json_encode($document, JSON_THROW_ON_ERROR),
            'post_style' => json_encode(['background' => 'canvas', 'font' => 'jakarta', 'size' => 'standard'], JSON_THROW_ON_ERROR),
            'image' => UploadedFile::fake()->image('synthetic-photo.png', 360, 240),
        ]);
        $response->assertRedirect();

        $post = CommunityMessage::where('user_id', $author->id)->firstOrFail();
        $this->assertStringStartsWith('community_media/', (string) $post->image_path);
        Storage::disk('local')->assertExists($post->image_path);
        $imageResponse = $this->actingAs($author)->get(route('student.community.image', $post->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp');
        $this->assertStringContainsString('private', (string) $imageResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $imageResponse->headers->get('Cache-Control'));
    }

    public function test_public_registration_remains_closed_by_default(): void
    {
        config(['services.account_verification.native_registration_enabled' => false]);
        $this->post(route('register.submit'), [
            'name' => 'Synthetic registered student',
            'institutional_email' => 'synthetic-registered@minsu.edu.ph',
            'requested_role' => 'student',
            'student_id' => 'SYN-REG-001',
            'year_level' => '1st Year',
            'program' => 'Synthetic Program',
            'campus' => 'Synthetic Campus',
            'notification_email' => 'student@gmail.com',
            'alternative_review' => '1',
            'privacy_acknowledgement' => '1',
        ])->assertRedirect(route('register'))->assertSessionHas('registration_error');

        $this->assertDatabaseMissing('users', ['email' => 'synthetic-registered@minsu.edu.ph']);
    }

    public function test_verified_student_can_create_post_with_category_and_support_label(): void
    {
        $user = $this->consentingStudent();

        $this->actingAs($user)->post(route('student.community.post'), [
            'title' => 'Study week check-in',
            'message' => 'Synthetic post asking for encouragement.',
            'category' => 'academic',
            'support_label' => 'encouragement',
            'is_anonymous' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('community_messages', [
            'user_id' => $user->id,
            'title' => 'Study week check-in',
            'category' => 'academic',
            'support_label' => 'encouragement',
            'is_anonymous' => 1,
            'moderation_status' => 'approved',
        ]);
    }

    public function test_duplicate_post_submissions_with_same_payload_are_collapsed(): void
    {
        $user = $this->consentingStudent();
        $payload = ['title' => 'Duplicate synthetic', 'message' => 'Same synthetic payload', 'category' => 'general', 'is_anonymous' => 1];

        $this->actingAs($user)->post(route('student.community.post'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('student.community.post'), $payload)->assertRedirect();

        $this->assertSame(1, CommunityMessage::where('user_id', $user->id)->where('message', 'Same synthetic payload')->count());
    }

    public function test_reaction_is_unique_per_student_and_can_change_or_remove(): void
    {
        $author = $this->consentingStudent();
        $actor = $this->consentingStudent();
        $post = CommunityMessage::create(['user_id' => $author->id, 'message' => 'Synthetic approved post', 'category' => 'general', 'is_anonymous' => true, 'is_approved' => true, 'moderation_status' => 'approved']);

        $this->actingAs($actor)->post(route('student.community.reaction', $post), ['reaction' => 'support'])->assertOk();
        $this->actingAs($actor)->post(route('student.community.reaction', $post), ['reaction' => 'helpful'])->assertOk();

        $this->assertDatabaseCount('community_reactions', 1);
        $this->assertDatabaseHas('community_reactions', ['community_message_id' => $post->id, 'user_id' => $actor->id, 'reaction' => 'helpful']);

        $this->actingAs($actor)->delete(route('student.community.reaction.remove', $post))->assertOk();
        $this->assertDatabaseCount('community_reactions', 0);
    }

    public function test_comments_support_one_level_replies_and_enforce_ownership(): void
    {
        $author = $this->consentingStudent();
        $commenter = $this->consentingStudent();
        $post = CommunityMessage::create(['user_id' => $author->id, 'message' => 'Synthetic approved post', 'category' => 'general', 'is_anonymous' => true, 'is_approved' => true, 'moderation_status' => 'approved']);

        $this->actingAs($commenter)->post(route('student.post.comment', $post->id), ['content' => 'A helpful synthetic reply'])->assertRedirect();
        $comment = PostComment::firstOrFail();
        $this->actingAs($author)->post(route('student.post.comment', $post->id), ['content' => 'A direct reply', 'parent_id' => $comment->id])->assertRedirect();
        $this->assertDatabaseHas('post_comments', ['parent_id' => $comment->id]);

        $this->actingAs($author)->delete(route('student.comment.delete', $comment->id))->assertForbidden();
    }

    public function test_anonymous_comment_response_excludes_private_user_fields_and_reports_are_deduplicated(): void
    {
        $author = $this->consentingStudent();
        $reporter = $this->consentingStudent();
        $post = CommunityMessage::create(['user_id' => $author->id, 'message' => 'Synthetic approved post', 'category' => 'general', 'is_anonymous' => true, 'is_approved' => true, 'moderation_status' => 'approved']);
        PostComment::create(['post_id' => $post->id, 'user_id' => $author->id, 'content' => 'Synthetic comment']);

        $comments = $this->actingAs($reporter)->getJson(route('student.post.comments', $post->id))->assertOk()->assertJsonMissingPath('comments.data.0.author.email')->assertJsonMissingPath('comments.data.0.author.student_id')->assertJsonMissingPath('comments.data.0.author.id');
        $this->assertStringNotContainsString($author->name, $comments->getContent());
        $payload = ['type' => 'post', 'target_id' => $post->id, 'reason' => 'spam'];
        $this->actingAs($reporter)->post(route('student.community.report'), $payload)->assertRedirect();
        $this->actingAs($reporter)->post(route('student.community.report'), $payload)->assertRedirect();
        $this->assertSame(1, CommunityReport::where('reporter_id', $reporter->id)->count());

        $comment = PostComment::firstOrFail();
        $this->actingAs($reporter)->post(route('student.community.report'), ['type' => 'comment', 'target_id' => $comment->id, 'reason' => 'privacy'])->assertRedirect();
        $this->assertDatabaseHas('community_reports', ['reporter_id' => $reporter->id, 'reportable_type' => 'comment', 'reportable_id' => $comment->id]);
    }

    public function test_post_edit_is_owner_only_and_delete_removes_it_from_public_feed(): void
    {
        $owner = $this->consentingStudent();
        $other = $this->consentingStudent();
        $post = CommunityMessage::create(['user_id' => $owner->id, 'message' => 'Original synthetic post', 'category' => 'general', 'is_anonymous' => true, 'is_approved' => true, 'moderation_status' => 'approved']);

        $this->actingAs($other)->put(route('student.community.update', $post->id), ['message' => 'Unauthorized edit', 'category' => 'general'])->assertForbidden();
        $this->actingAs($owner)->put(route('student.community.update', $post->id), ['title' => 'Updated', 'message' => 'Updated synthetic post', 'category' => 'motivation'])->assertRedirect();
        $this->assertDatabaseHas('community_messages', ['id' => $post->id, 'message' => 'Updated synthetic post', 'moderation_status' => 'pending', 'is_approved' => 0]);

        $this->actingAs($owner)->delete(route('student.community.delete', $post->id))->assertRedirect();
        $this->assertDatabaseHas('community_messages', ['id' => $post->id, 'moderation_status' => 'rejected', 'is_approved' => 0]);
    }

    public function test_blocked_student_cannot_react_to_blockers_post(): void
    {
        $author = $this->consentingStudent();
        $blocked = $this->consentingStudent();
        $post = CommunityMessage::create(['user_id' => $author->id, 'message' => 'Synthetic approved post', 'category' => 'general', 'is_anonymous' => false, 'is_approved' => true, 'moderation_status' => 'approved']);

        $this->actingAs($author)->post(route('student.community.block', $blocked->id))->assertRedirect();
        $this->actingAs($blocked)->post(route('student.community.reaction', $post), ['reaction' => 'support'])->assertForbidden();
        $this->assertDatabaseCount('community_reactions', 0);
    }
    public function test_only_admins_can_review_confidential_reports_and_verify_students(): void
    {
        $student = $this->consentingStudent();
        $pending = User::factory()->unverified()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($student)->get(route('admin.community-reports'))->assertForbidden();
        $reporter = $this->consentingStudent();
        $post = CommunityMessage::create(['user_id' => $student->id, 'message' => 'Synthetic report target', 'category' => 'general', 'is_anonymous' => true, 'is_approved' => true, 'moderation_status' => 'approved']);
        CommunityReport::create(['reporter_id' => $reporter->id, 'reportable_type' => 'post', 'reportable_id' => $post->id, 'reason' => 'harmful', 'status' => 'pending']);

        $this->actingAs($admin)->get(route('admin.community-reports'))->assertOk()->assertSee('Community reports');
        $report = CommunityReport::firstOrFail();
        $this->actingAs($admin)->post(route('admin.community-reports.review', $report->id), ['status' => 'dismissed', 'moderator_notes' => 'Synthetic review'])->assertRedirect();
        $this->assertDatabaseHas('community_reports', ['status' => 'dismissed', 'reviewed_by' => $admin->id]);
        $this->assertDatabaseHas('community_report_audits', ['to_status' => 'dismissed', 'moderator_id' => $admin->id]);

        $this->actingAs($admin)->post(route('admin.users.community-verify', $pending->id))->assertRedirect();
        $this->assertNotNull($pending->fresh()->community_verified_at);
    }

    public function test_counselors_can_share_active_resources_but_students_cannot_forge_staff_attachments(): void
    {
        $counselor = User::factory()->create(['role' => 'counselor', 'name' => 'Synthetic Resource Counselor']);
        $student = $this->consentingStudent();
        $resource = Resource::create([
            'title' => 'Synthetic active grounding resource',
            'description' => 'Curated resource available to Community members.',
            'category' => 'coping',
            'content' => 'Grounding resource body for test only.',
            'url' => null,
            'is_active' => true,
        ]);

        $this->actingAs($counselor)->post(route('student.community.post'), [
            'message' => 'A general educational post with a curated resource.',
            'category' => 'mental-health',
            'resource_id' => $resource->id,
            'is_anonymous' => 1,
        ])->assertRedirect();
        $post = CommunityMessage::where('user_id', $counselor->id)->firstOrFail();
        $this->assertFalse($post->is_anonymous);
        $this->assertSame($resource->id, $post->resource_id);
        $this->actingAs($student)->get(route('student.community'))->assertOk()
            ->assertSee('Synthetic active grounding resource')
            ->assertSee(route('student.community.resource.show', $resource->id));
        $this->actingAs($counselor)->get(route('student.community.resource.show', $resource->id))->assertOk()->assertSee('Grounding resource body for test only.');

        $this->actingAs($student)->post(route('student.community.post'), [
            'message' => 'Forged resource share attempt.',
            'category' => 'general',
            'resource_id' => $resource->id,
        ])->assertForbidden();
        $this->assertDatabaseMissing('community_messages', ['user_id' => $student->id, 'resource_id' => $resource->id]);
    }

    public function test_admin_pin_and_post_moderation_are_authorized_and_audited(): void
    {
        $student = $this->consentingStudent();
        $counselor = User::factory()->create(['role' => 'counselor']);
        $admin = User::factory()->create(['role' => 'admin']);
        $post = CommunityMessage::create([
            'user_id' => $student->id,
            'message' => 'Synthetic published item for admin pinning.',
            'category' => 'general',
            'is_anonymous' => false,
            'is_approved' => true,
            'moderation_status' => 'approved',
        ]);

        $this->actingAs($counselor)->post(route('admin.community-moderation.pin', $post->id), ['pinned' => 1])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.community-moderation.pin', $post->id), ['pinned' => 1])->assertRedirect();
        $this->assertTrue($post->fresh()->is_pinned);
        $this->assertDatabaseHas('community_moderation_audits', [
            'community_message_id' => $post->id,
            'actor_user_id' => $admin->id,
            'moderator_id' => $admin->id,
            'action' => 'pin',
            'to_pinned' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.community-moderation.pin', $post->id), ['pinned' => 0])->assertRedirect();
        $this->assertFalse($post->fresh()->is_pinned);
        $this->assertDatabaseHas('community_moderation_audits', [
            'community_message_id' => $post->id,
            'moderator_id' => $admin->id,
            'action' => 'unpin',
            'from_pinned' => 1,
            'to_pinned' => 0,
        ]);

        $pendingPost = CommunityMessage::create([
            'user_id' => $student->id,
            'message' => 'Synthetic post awaiting moderator approval.',
            'category' => 'general',
            'is_approved' => false,
            'moderation_status' => 'pending',
        ]);
        $this->actingAs($admin)->post(route('admin.community-moderation.approve', $pendingPost->id))->assertRedirect();
        $this->assertDatabaseHas('community_moderation_audits', [
            'community_message_id' => $pendingPost->id,
            'moderator_id' => $admin->id,
            'action' => 'approve',
            'from_status' => 'pending',
            'to_status' => 'approved',
        ]);
        $this->actingAs($admin)->get(route('admin.community-moderation'))->assertRedirect(route('student.community'));
    }
}
