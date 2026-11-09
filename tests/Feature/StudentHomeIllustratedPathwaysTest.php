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

        foreach (['home-path-talk-v1.webp', 'home-path-journal-v1.webp', 'home-path-pause-v1.webp', 'home-path-community-v1.webp', 'home-guidance-conversation-v1.webp'] as $image) {
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
}
