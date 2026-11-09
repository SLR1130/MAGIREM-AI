<?php

namespace Tests\Feature;

use App\Models\CommunityMessage;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityVisualPostStudioTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $name): User
    {
        $user = User::factory()->create(['role' => 'student', 'name' => $name]);
        app(PrivacyService::class)->updateSettings($user->id, [
            'allow_anonymous_community' => true,
            'consent_policy_version' => config('privacy.consent_policy_version'),
        ]);
        return $user->fresh();
    }

    private function visualPayload(string $text = 'A small step still counts.'): array
    {
        return [
            'post_format' => 'visual',
            'visual_text' => $text,
            'visual_layout' => json_encode([
                'background' => 'forest',
                'x' => 50,
                'y' => 52,
                'font_size' => 48,
                'color' => '#ffffff',
                'align' => 'center',
            ]),
            'is_anonymous' => 1,
        ];
    }

    public function test_visual_post_is_server_rendered_and_held_for_review(): void
    {
        Storage::fake('local');
        $author = $this->student('Visual author');
        $viewer = $this->student('Visual viewer');

        $response = $this->actingAs($author)->post(route('student.community.post'), array_merge(
            $this->visualPayload(),
            ['image' => UploadedFile::fake()->image('studio-source.jpg', 640, 480)],
        ));

        $response->assertRedirect()->assertSessionHas('success', 'Your post is under review. You can find it in My Posts.');
        $post = CommunityMessage::where('user_id', $author->id)->latest('id')->firstOrFail();
        $this->assertSame('visual', $post->post_format);
        $this->assertSame('A small step still counts.', $post->visual_text);
        $this->assertIsArray($post->visual_layout);
        $this->assertFalse($post->is_approved);
        $this->assertSame('pending', $post->moderation_status);
        $this->assertStringStartsWith('community_media/', (string) $post->image_path);
        $this->assertStringStartsWith('community_visuals/', (string) $post->visual_media_path);
        Storage::disk('local')->assertExists($post->image_path);
        Storage::disk('local')->assertExists($post->visual_media_path);

        $this->actingAs($author)->get(route('student.community.image', $post->id))->assertOk();
        $this->actingAs($viewer)->get(route('student.community.image', $post->id))->assertForbidden();
    }

    public function test_moderated_visual_post_uses_normal_feed_and_social_interactions(): void
    {
        Storage::fake('local');
        $author = $this->student('Published visual author');
        $viewer = $this->student('Published visual viewer');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($author)->post(route('student.community.post'), array_merge(
            $this->visualPayload('Published visual message.'),
            ['image' => UploadedFile::fake()->image('published-source.webp', 500, 500)],
        ));
        $post = CommunityMessage::where('user_id', $author->id)->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.community-moderation.approve', $post->id))->assertRedirect();
        $post->refresh();
        $this->assertTrue($post->is_approved);
        $this->assertSame('approved', $post->moderation_status);

        $this->actingAs($viewer)->get(route('student.community'))
            ->assertOk()
            ->assertSee('Published visual message.')
            ->assertSee(route('student.community.image', $post->id));
        $this->actingAs($viewer)->get(route('student.community.image', $post->id))->assertOk();
        $this->actingAs($viewer)->postJson(route('student.community.reaction', $post->id), ['reaction' => 'like'])
            ->assertOk()->assertJsonPath('like_count', 1);
    }
    public function test_visual_post_edit_is_owner_only_and_returns_to_review(): void
    {
        Storage::fake('local');
        $author = $this->student('Visual editor');
        $other = $this->student('Other student');
        $this->actingAs($author)->post(route('student.community.post'), array_merge(
            $this->visualPayload('Original visual text.'),
            ['image' => UploadedFile::fake()->image('studio-source.png', 640, 480)],
        ));
        $post = CommunityMessage::where('user_id', $author->id)->latest('id')->firstOrFail();

        $updated = $this->visualPayload('Updated visual text.');
        $this->actingAs($other)->post(route('student.community.update', $post->id), array_merge($updated, ['_method' => 'PUT']))
            ->assertForbidden();
        $this->actingAs($author)->post(route('student.community.update', $post->id), array_merge($updated, ['_method' => 'PUT']))
            ->assertRedirect(route('student.community', ['view' => 'mine', 'status' => 'pending']) . '#my-post-' . $post->id)
            ->assertSessionHas('success', 'Your visual post was updated and returned to moderation.');

        $post->refresh();
        $this->assertSame('Updated visual text.', $post->visual_text);
        $this->assertFalse($post->is_approved);
        $this->assertSame('pending', $post->moderation_status);
        Storage::disk('local')->assertExists($post->visual_media_path);
    }

    public function test_visual_renderer_wraps_long_unbroken_and_multiline_text_inside_canvas(): void
    {
        Storage::fake('local');
        $author = $this->student('Visual overflow tester');
        $service = app(\App\Services\CommunityService::class);
        $fontMethod = new \ReflectionMethod($service, 'visualFontPath');
        $fontMethod->setAccessible(true);
        $wrapMethod = new \ReflectionMethod($service, 'wrapVisualText');
        $wrapMethod->setAccessible(true);
        $font = $fontMethod->invoke($service);
        $lines = $wrapMethod->invoke($service, str_repeat('unbrokentext', 41), $font, 88, 936);
        foreach ($lines as $line) {
            $box = imagettfbbox(88, 0, $font, $line);
            $this->assertLessThanOrEqual(936, abs((int) ($box[2] - $box[0])));
        }

        $payloads = [
            [str_repeat('unbrokentext', 41), ['x' => 8, 'y' => 12, 'font_size' => 88, 'align' => 'left']],
            [str_repeat('A practical budget reflection helps a student notice what is manageable today. ', 6), ['x' => 50, 'y' => 52, 'font_size' => 76, 'align' => 'center']],
            ["First line stays readable.\nSecond line remains inside.\nThird line too.", ['x' => 92, 'y' => 88, 'font_size' => 80, 'align' => 'right']],
        ];
        foreach ($payloads as [$visualText, $position]) {
            $layout = array_merge([
                'background' => 'forest',
                'x' => 50,
                'y' => 52,
                'font_size' => 48,
                'color' => '#ffffff',
                'align' => 'center',
            ], $position);
            $this->actingAs($author)->post(route('student.community.post'), [
                'post_format' => 'visual',
                'visual_text' => $visualText,
                'visual_layout' => json_encode($layout),
                'is_anonymous' => 1,
            ])->assertRedirect();
            $post = CommunityMessage::where('user_id', $author->id)->latest('id')->firstOrFail();
            $binary = Storage::disk('local')->get($post->visual_media_path);
            $image = imagecreatefromstring($binary);
            $this->assertNotFalse($image);
            $this->assertSame(1080, imagesx($image));
            $this->assertSame(1080, imagesy($image));
            imagedestroy($image);
        }
    }
    public function test_invalid_visual_layout_json_is_rejected(): void
    {
        $student = $this->student('Invalid layout student');
        $this->actingAs($student)->from(route('student.community'))->post(route('student.community.post'), [
            'post_format' => 'visual',
            'visual_text' => 'A visual thought.',
            'visual_layout' => '{not-json',
        ])->assertRedirect(route('student.community'))->assertSessionHasErrors('visual_layout');
    }

    public function test_layered_photo_composition_persists_private_source_caption_and_validated_layers(): void
    {
        Storage::fake('local');
        $author = $this->student('Canvas author');
        $layout = [
            'version' => 2,
            'width' => 1080,
            'height' => 1350,
            'background' => 'ivory',
            'layers' => [
                ['id' => 'photo-main', 'type' => 'image', 'x' => -120, 'y' => 0, 'width' => 1320, 'height' => 1350, 'rotation' => 0, 'opacity' => 1],
                ['id' => 'text-note', 'type' => 'text', 'text' => 'Small steps still count.', 'x' => 100, 'y' => 520, 'width' => 880, 'height' => 190, 'rotation' => -2, 'opacity' => 1, 'font' => 'lora', 'font_size' => 58, 'color' => '#ffffff', 'bold' => true, 'italic' => false, 'align' => 'center', 'background_color' => '#103f32'],
                ['id' => 'sticker-heart', 'type' => 'sticker', 'emoji' => '💛', 'x' => 840, 'y' => 120, 'width' => 120, 'height' => 120, 'rotation' => 8, 'opacity' => 0.95],
            ],
        ];
        $caption = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A short caption beneath the artwork.']]]]];

        $response = $this->actingAs($author)->postJson(route('student.community.post'), [
            'post_format' => 'visual',
            'category' => 'motivation',
            'support_label' => 'sharing',
            'visual_layout' => json_encode($layout),
            'visual_caption' => json_encode($caption),
            'image' => UploadedFile::fake()->image('canvas-photo.jpg', 640, 800),
            'is_anonymous' => 1,
        ]);

        $response->assertCreated()->assertJsonPath('moderation_status', 'pending');
        $post = CommunityMessage::where('user_id', $author->id)->latest('id')->firstOrFail();
        $this->assertSame(2, $post->visual_layout['version']);
        $this->assertCount(3, $post->visual_layout['layers']);
        $this->assertSame("Small steps still count.\n💛", $post->visual_text);
        $this->assertSame('motivation', $post->category);
        $this->assertSame('sharing', $post->support_label);
        $this->assertSame('A short caption beneath the artwork.', $post->rich_content['content'][0]['content'][0]['text']);
        $this->assertStringStartsWith('community_media/', (string) $post->image_path);
        $this->assertStringStartsWith('community_visuals/', (string) $post->visual_media_path);
        Storage::disk('local')->assertExists($post->image_path);
        Storage::disk('local')->assertExists($post->visual_media_path);

        $preview = imagecreatefromstring(Storage::disk('local')->get($post->visual_media_path));
        $this->assertNotFalse($preview);
        $this->assertSame(1080, imagesx($preview));
        $this->assertSame(1350, imagesy($preview));
        imagedestroy($preview);
    }

    public function test_layered_composition_rejects_unapproved_markup_unsupported_layers_and_unbounded_geometry(): void
    {
        Storage::fake('local');
        $author = $this->student('Canvas validation');
        $base = [
            'version' => 2, 'width' => 1080, 'height' => 1350, 'background' => 'mint',
            'layers' => [['id' => 'text-one', 'type' => 'text', 'text' => 'Hello', 'x' => 0, 'y' => 0, 'width' => 400, 'height' => 120, 'rotation' => 0, 'opacity' => 1, 'font' => 'jakarta', 'font_size' => 40, 'color' => '#173e32', 'bold' => false, 'italic' => false, 'align' => 'left', 'background_color' => null]],
        ];
        $unsafe = $base;
        $unsafe['layers'][0]['text'] = '<img src=x onerror=alert(1)>';
        $this->actingAs($author)->postJson(route('student.community.post'), ['post_format' => 'visual', 'visual_layout' => json_encode($unsafe)])
            ->assertUnprocessable()->assertJsonValidationErrors('visual_layout');

        $unsupported = $base;
        $unsupported['layers'][0]['type'] = 'html';
        $this->actingAs($author)->postJson(route('student.community.post'), ['post_format' => 'visual', 'visual_layout' => json_encode($unsupported)])
            ->assertUnprocessable()->assertJsonValidationErrors('visual_layout');

        $unbounded = $base;
        $unbounded['layers'][0]['x'] = 100000;
        $this->actingAs($author)->postJson(route('student.community.post'), ['post_format' => 'visual', 'visual_layout' => json_encode($unbounded)])
            ->assertUnprocessable()->assertJsonValidationErrors('visual_layout');
        $this->assertDatabaseCount('community_messages', 0);
    }

    public function test_supported_color_sticker_is_rasterized_into_the_private_preview_and_keeps_its_unicode_identity(): void
    {
        Storage::fake('local');
        $author = $this->student('Sticker asset author');
        $emoji = "\u{1F60D}"; // Smiling face with heart-shaped eyes from the owner's reported case.
        $layout = [
            'version' => 2, 'width' => 1080, 'height' => 1350, 'background' => 'forest',
            'layers' => [[
                'id' => 'sticker-love', 'type' => 'sticker', 'emoji' => $emoji,
                'x' => 420, 'y' => 470, 'width' => 180, 'height' => 180, 'rotation' => 0, 'opacity' => 1,
            ]],
        ];

        $this->actingAs($author)->postJson(route('student.community.post'), [
            'post_format' => 'visual', 'visual_layout' => json_encode($layout),
        ])->assertCreated();

        $post = CommunityMessage::where('user_id', $author->id)->latest('id')->firstOrFail();
        $this->assertSame($emoji, $post->visual_layout['layers'][0]['emoji']);
        $this->assertSame($emoji, $post->visual_text);
        $this->assertFileExists(public_path('assets/emoji/twemoji/64/1f60d.png'));

        $preview = imagecreatefromstring(Storage::disk('local')->get($post->visual_media_path));
        $this->assertNotFalse($preview);
        $differentFromBackground = 0;
        for ($y = 475; $y < 645; $y += 2) {
            for ($x = 425; $x < 595; $x += 2) {
                $pixel = imagecolorat($preview, $x, $y);
                $red = ($pixel >> 16) & 0xFF;
                $green = ($pixel >> 8) & 0xFF;
                $blue = $pixel & 0xFF;
                if (abs($red - 21) > 20 || abs($green - 63) > 20 || abs($blue - 50) > 20) {
                    $differentFromBackground++;
                }
            }
        }
        imagedestroy($preview);
        $this->assertGreaterThan(100, $differentFromBackground, 'The flattened post should contain visible Twemoji artwork, not only sticker metadata.');
    }

    public function test_heart_and_multi_codepoint_stickers_use_local_transparent_assets_and_survive_feed_serialization(): void
    {
        Storage::fake('local');
        $author = $this->student('Multi-codepoint sticker author');
        $viewer = $this->student('Multi-codepoint sticker viewer');
        $heart = "\u{2764}\u{FE0F}";
        $technologist = "\u{1F469}\u{200D}\u{1F4BB}";
        $layout = [
            'version' => 2, 'width' => 1080, 'height' => 1350, 'background' => 'ivory',
            'layers' => [
                ['id' => 'heart', 'type' => 'sticker', 'emoji' => $heart, 'x' => 120, 'y' => 220, 'width' => 120, 'height' => 120, 'rotation' => 0, 'opacity' => 1],
                ['id' => 'person-sequence', 'type' => 'sticker', 'emoji' => $technologist, 'x' => 460, 'y' => 220, 'width' => 120, 'height' => 120, 'rotation' => 0, 'opacity' => 1],
            ],
        ];
        $this->actingAs($author)->postJson(route('student.community.post'), [
            'post_format' => 'visual', 'visual_layout' => json_encode($layout),
        ])->assertCreated();
        $post = CommunityMessage::where('user_id', $author->id)->latest('id')->firstOrFail();
        $this->assertFileExists(public_path('assets/emoji/twemoji/64/2764-fe0f.png'));
        $this->assertFileExists(public_path('assets/emoji/twemoji/64/1f469-200d-1f4bb.png'));
        $this->actingAs(User::findOrFail($author->id))->post(route('admin.community-moderation.approve', $post->id))->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.community-moderation.approve', $post->id))->assertRedirect();

        $payload = $this->actingAs($viewer)->getJson(route('student.community.data'))->assertOk()->json();
        $feedPost = collect($payload['posts'])->firstWhere('id', $post->id);
        $this->assertSame([$heart, $technologist], array_column($feedPost['visual_layout']['layers'], 'emoji'));
        $this->assertStringContainsString('/image', $feedPost['image_url']);
        $this->actingAs($viewer)->get($feedPost['image_url'])->assertOk();
    }

    public function test_unavailable_sticker_asset_is_rejected_instead_of_saving_a_blank_layer(): void
    {
        Storage::fake('local');
        $author = $this->student('Unsupported sticker author');
        $layout = [
            'version' => 2, 'width' => 1080, 'height' => 1350, 'background' => 'mint',
            'layers' => [[
                'id' => 'unsupported-sticker', 'type' => 'sticker', 'emoji' => "\u{1FAE8}",
                'x' => 400, 'y' => 400, 'width' => 140, 'height' => 140, 'rotation' => 0, 'opacity' => 1,
            ]],
        ];

        $this->actingAs($author)->postJson(route('student.community.post'), [
            'post_format' => 'visual', 'visual_layout' => json_encode($layout),
        ])->assertUnprocessable()->assertJsonValidationErrors('visual_layout');
        $this->assertDatabaseCount('community_messages', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_sticker_layers_cannot_be_stretched_out_of_their_square_asset_ratio(): void
    {
        Storage::fake('local');
        $author = $this->student('Sticker proportion author');
        $layout = [
            'version' => 2, 'width' => 1080, 'height' => 1350, 'background' => 'mint',
            'layers' => [[
                'id' => 'stretched-sticker', 'type' => 'sticker', 'emoji' => "\u{2764}\u{FE0F}",
                'x' => 400, 'y' => 400, 'width' => 220, 'height' => 100, 'rotation' => 0, 'opacity' => 1,
            ]],
        ];

        $this->actingAs($author)->postJson(route('student.community.post'), [
            'post_format' => 'visual', 'visual_layout' => json_encode($layout),
        ])->assertUnprocessable()->assertJsonValidationErrors('visual_layout');
        $this->assertDatabaseCount('community_messages', 0);
    }

    public function test_approved_layered_post_serializes_composition_url_and_keeps_source_private_for_other_members_until_approval(): void
    {
        Storage::fake('local');
        $author = $this->student('Layered source author');
        $viewer = $this->student('Layered source viewer');
        $layout = [
            'version' => 2, 'width' => 1080, 'height' => 1350, 'background' => 'mint',
            'layers' => [['id' => 'canvas-photo', 'type' => 'image', 'x' => 0, 'y' => 0, 'width' => 1080, 'height' => 1350, 'rotation' => 0, 'opacity' => 1]],
        ];
        $this->actingAs($author)->postJson(route('student.community.post'), [
            'post_format' => 'visual', 'visual_layout' => json_encode($layout), 'image' => UploadedFile::fake()->image('canvas.jpg', 500, 700),
        ])->assertCreated();
        $post = CommunityMessage::where('user_id', $author->id)->latest('id')->firstOrFail();
        $this->actingAs($viewer)->get(route('student.community.image', $post->id) . '?composition=1')->assertForbidden();
        $this->actingAs($author)->get(route('student.community.image', $post->id) . '?composition=1')->assertOk();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.community-moderation.approve', $post->id))->assertRedirect();
        $payload = $this->actingAs($viewer)->getJson(route('student.community.data'))->assertOk()->json();
        $feedPost = collect($payload['posts'])->firstWhere('id', $post->id);
        $this->assertNotNull($feedPost);
        $this->assertSame(2, $feedPost['visual_layout']['version']);
        $this->assertStringContainsString('composition=1', $feedPost['composition_image_url']);
        $this->actingAs($viewer)->get($feedPost['composition_image_url'])->assertOk();
    }

    public function test_owner_edit_rehydrates_and_updates_layered_composition_without_replacing_original_photo(): void
    {
        Storage::fake('local');
        $author = $this->student('Composition editor');
        $savedSticker = "\u{1F49B}";
        $original = [
            'version' => 2, 'width' => 1080, 'height' => 1350, 'background' => 'mint',
            'layers' => [
                ['id' => 'photo-layer', 'type' => 'image', 'x' => 0, 'y' => 0, 'width' => 1080, 'height' => 1350, 'rotation' => 0, 'opacity' => 1],
                ['id' => 'first-line', 'type' => 'text', 'text' => 'Original design', 'x' => 80, 'y' => 400, 'width' => 920, 'height' => 200, 'rotation' => 0, 'opacity' => 1, 'font' => 'jakarta', 'font_size' => 48, 'color' => '#ffffff', 'bold' => false, 'italic' => false, 'align' => 'center', 'background_color' => null],
                ['id' => 'saved-heart-sticker', 'type' => 'sticker', 'emoji' => $savedSticker, 'x' => 760, 'y' => 180, 'width' => 120, 'height' => 120, 'rotation' => 0, 'opacity' => 1],
            ],
        ];
        $this->actingAs($author)->postJson(route('student.community.post'), ['post_format' => 'visual', 'visual_layout' => json_encode($original), 'image' => UploadedFile::fake()->image('original.jpg', 640, 800)])->assertCreated();
        $post = CommunityMessage::where('user_id', $author->id)->latest('id')->firstOrFail();
        $sourcePath = $post->image_path;
        $updated = $original;
        $updated['background'] = 'forest';
        $updated['layers'][1]['text'] = 'An edited and calmer design';
        $updated['layers'][1]['x'] = 120;

        $this->actingAs($author)->postJson(route('student.community.update', $post->id), [
            '_method' => 'PUT', 'post_format' => 'visual', 'visual_layout' => json_encode($updated), 'category' => 'motivation',
        ])->assertOk()->assertJsonPath('post_id', $post->id);

        $post->refresh();
        $this->assertSame($sourcePath, $post->image_path);
        $this->assertSame('forest', $post->visual_layout['background']);
        $this->assertSame('An edited and calmer design', $post->visual_layout['layers'][1]['text']);
        $this->assertSame($savedSticker, $post->visual_layout['layers'][2]['emoji']);
        $this->assertEquals(120, $post->visual_layout['layers'][1]['x']);
        $this->assertFalse($post->is_approved);
        Storage::disk('local')->assertExists($post->image_path);
        Storage::disk('local')->assertExists($post->visual_media_path);

        $mine = $this->actingAs($author)->getJson(route('student.community.data', ['view' => 'mine']))->assertOk()->json();
        $saved = collect($mine['my_posts'])->firstWhere('id', $post->id);
        $this->assertSame('An edited and calmer design', $saved['visual_layout']['layers'][1]['text']);
        $this->assertSame($savedSticker, $saved['visual_layout']['layers'][2]['emoji']);
        $this->assertStringContainsString('source=1', $saved['source_image_url']);
    }
}
