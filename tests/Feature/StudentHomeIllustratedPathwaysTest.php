<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentHomeIllustratedPathwaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_home_keeps_accessible_pathways_and_real_destination_links(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $html = $this->actingAs($student)->get(route('home'))->assertOk()->getContent();

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        $tabs = $xpath->query("//button[@role='tab' and @data-msc-pathway-tab]");
        $panels = $xpath->query("//*[@role='tabpanel' and @data-msc-pathway-panel]");
        $this->assertCount(4, $tabs);
        $this->assertCount(4, $panels);
        $this->assertSame(1, $xpath->query("//button[@role='tab' and @aria-selected='true']")->length);
        $this->assertSame(3, $xpath->query("//*[@role='tabpanel' and @hidden]")->length);

        foreach ($tabs as $tab) {
            $panelId = $tab->getAttribute('aria-controls');
            $this->assertNotSame('', $panelId);
            $this->assertSame(1, $xpath->query("//*[@role='tabpanel' and @id='$panelId']")->length);
        }

        foreach (['student.ai-chat', 'student.journal', 'student.coping-toolkit', 'student.community', 'student.resources', 'student.counselor-directory', 'student.appointments'] as $routeName) {
            $this->assertStringContainsString(route($routeName), $html);
        }

        foreach (['home-daylight-workspace-1600.webp', 'home-daylight-journal-1600.webp', 'home-daylight-hero-1920.webp', 'home-daylight-community-1600.webp', 'home-daylight-guidance-1600.webp'] as $image) {
            $this->assertFileExists(public_path('images/wellness/'.$image));
            $this->assertStringContainsString($image, $html);
        }
    }

    public function test_guest_home_still_uses_the_existing_login_and_registration_routes(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(route('login'), $html);
        $this->assertStringContainsString(route('register'), $html);
        $this->assertStringContainsString('msc-support-story', $html);
        $this->assertStringContainsString('data-msc-pathway-tab', $html);
    }

    public function test_home_uses_local_responsive_photography_and_optional_motion_controls(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $html = $this->actingAs($student)->get(route('home'))->assertOk()->getContent();

        foreach ([960, 1920, 3840] as $width) {
            $path = 'images/wellness/home-daylight-hero-'.$width.'.webp';
            $this->assertStringContainsString(asset($path), $html);
            $dimensions = getimagesize(public_path($path));
            $this->assertSame($width, $dimensions[0]);
            $this->assertSame('image/webp', $dimensions['mime']);
        }
        $this->assertSame(2160, getimagesize(public_path('images/wellness/home-daylight-hero-3840.webp'))[1]);
        $this->assertStringContainsString('data-msc-scene-pause', $html);
        $this->assertStringContainsString('aria-label="Pause homepage motion"', $html);
        $this->assertStringContainsString('sizes="100vw"', $html);
        $this->assertStringContainsString('class="msc-nature-atmosphere"', $html);
        $this->assertStringNotContainsString('class="msc-nature-scene__caption"', $html);
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query("//section[contains(@class, 'msc-home-stage')]/div[@data-msc-hero-art]")->length, 'Background must span the stage, not occupy a separate hero column.');
        $this->assertSame(1, $xpath->query("//*[@class='msc-nature-scene' and @aria-hidden='true']//img[@alt='']")->length);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringNotContainsString('magirem-minsu-sanctuary.webp', $html);
        $this->assertStringNotContainsString('class="msc-sanctuary-art__foreground"', $html);
        $this->assertStringNotContainsString('home-forest-photo-', $html);
        $this->assertStringNotContainsString('home-path-talk-v1.webp', $html);
        $this->assertStringNotContainsString('home-guidance-conversation-v1.webp', $html);
        $this->assertStringContainsString(route('student.coping-toolkit'), $html);

        $credits = json_decode(file_get_contents(public_path('images/wellness/home-daylight-photos.source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(5, $credits['photos']);
        $this->assertSame('Ales Krivec', $credits['photos'][0]['photographer']);
        foreach ($credits['photos'] as $credit) {
            $this->assertNotEmpty($credit['source']);
            $this->assertNotEmpty($credit['license_url']);
        }
    }
}
