<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MagiAvatarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_student_chat_renders_magi_avatar_and_lifecycle_states(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('student.ai-chat'))
            ->assertOk()
            ->assertSee('magi-avatar--md', false)
            ->assertSee('data-animated="true"', false)
            ->assertSee('magiAvatarSmTemplate', false)
            ->assertSee('MAGI is thinking', false)
            ->assertSee("magiAvatarMarkup('thinking')", false)
            ->assertSee("magiAvatarMarkup('responding')", false)
            ->assertSee("setMagiState('error')", false)
            ->assertSee("addEventListener('animationend'", false)
            ->assertSee("magi:sprite-cycle", false)
            ->assertSee('css/magi-avatar.css', false)
            ->assertSee('js/magi-sprite.js', false)
            ->assertSee('assets/branding/magi-sprite-normalized.png', false)
            ->assertSee('data-sprite-columns="11"', false)
            ->assertSee('data-sprite-rows="4"', false)
            ->assertSee('data-sprite-idle-start="4"', false)
            ->assertSee('assets/branding/magi-avatar-static.png', false);
    }

    public function test_avatar_assets_are_available_and_normalized_atlas_has_integer_cells(): void
    {
        $sourcePath = public_path('assets/branding/magi-sprite-sheet.jpg');
        $spritePath = public_path('assets/branding/magi-sprite-normalized.png');
        $staticPath = public_path('assets/branding/magi-avatar-static.png');

        $this->assertFileExists($sourcePath);
        $this->assertFileExists($spritePath);
        $this->assertFileExists($staticPath);
        $dimensions = getimagesize($spritePath);
        $this->assertSame(1232, $dimensions[0]);
        $this->assertSame(448, $dimensions[1]);
        $this->assertSame(IMAGETYPE_PNG, $dimensions[2]);
        $this->assertSame(0, $dimensions[0] % 11);
        $this->assertSame(0, $dimensions[1] % 4);
    }

    public function test_avatar_styles_use_a_static_fallback_for_reduced_motion_and_errors(): void
    {
        $css = file_get_contents(public_path('css/magi-avatar.css'));

        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('.magi-avatar[data-state="error"] .magi-avatar__static', $css);
        $this->assertStringContainsString('overflow: hidden', $css);
        $this->assertStringContainsString('.magi-avatar__canvas', $css);
        $this->assertStringContainsString('border-radius: 50%', $css);
        $this->assertStringContainsString('background: #053e36', $css);
        $this->assertStringNotContainsString('.magi-avatar[data-state="thinking"] .magi-avatar__motion { transform:', $css);
        $this->assertStringContainsString('dataset.rendered', file_get_contents(public_path('js/magi-sprite.js')));
        $this->assertStringContainsString('prefers-reduced-motion', file_get_contents(public_path('js/magi-sprite.js')));
        $this->assertStringContainsString('naturalWidth % columns', file_get_contents(public_path('js/magi-sprite.js')));
        $this->assertStringContainsString('Math.floor(instance.image.naturalWidth / columns)', file_get_contents(public_path('js/magi-sprite.js')));

        $viewportRule = substr($css, 0, strpos($css, '.magi-avatar--sm'));
        $this->assertStringContainsString('overflow: hidden', $viewportRule);
        $this->assertStringContainsString('border-radius: 50%', $viewportRule);
    }

    public function test_chat_header_message_and_typing_mascots_use_the_shared_sprite_renderer(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $html = $this->actingAs($student)->get(route('student.ai-chat'))->assertOk()->getContent();

        $headerStart = strpos($html, '<div class="chat-header">');
        $headerEnd = strpos($html, '</div>', $headerStart);
        $this->assertNotFalse($headerStart);
        $header = substr($html, $headerStart, ($headerEnd ?: $headerStart) - $headerStart + 6);
        $this->assertStringContainsString('data-animated="true"', $header);
        $this->assertStringContainsString('loading="lazy"', $header);
        $this->assertStringNotContainsString('magi-avatar__frames', $html);
        $this->assertStringContainsString('data-sprite-src=', $html);

        $this->assertStringContainsString('data-animated="true"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('magiAvatarMarkup', $html);
        $this->assertStringContainsString('IntersectionObserver', $html);
        $this->assertStringContainsString("rootMargin: '72px 0px'", $html);
    }

    public function test_student_home_floating_magi_uses_the_new_sprite_sheet(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'approval_status' => 'approved',
        ]);

        $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('home-magi__toggle', false)
            ->assertSee('data-avatar-context="home"', false)
            ->assertSee('assets/branding/magi-sprite-normalized.png', false)
            ->assertSee('magi-sprite.js', false)
            ->assertSee('home-magi__panel', false);
    }
}
