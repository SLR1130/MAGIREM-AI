<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_authenticated_pages_include_pwa_metadata(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('manifest.webmanifest')->assertSee('js/pwa-register.js')
            ->assertSee('data-pwa-install-action', false)->assertSee('js/pwa-install.js');
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('home'))->assertOk()
            ->assertSee('manifest.webmanifest')->assertSee('js/pwa-register.js');
    }

    public function test_manifest_uses_real_correctly_sized_brand_icons(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        foreach ([192, 512] as $size) {
            $icon = collect($manifest['icons'])->firstWhere('sizes', "{$size}x{$size}");
            $this->assertNotNull($icon);
            [$width, $height] = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame([$size, $size], [$width, $height]);
        }
    }
}
