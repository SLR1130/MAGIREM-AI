<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function notice(User $owner, array $attributes = []): Notification
    {
        return Notification::create(array_merge([
            'user_id' => $owner->id,
            'type' => 'message',
            'title' => 'Synthetic update',
            'message' => 'A synthetic notification.',
            'link' => '/home',
            'is_read' => false,
            'priority' => 'normal',
        ], $attributes));
    }

    public function test_each_role_sees_only_its_own_notifications_without_internal_sender_ids(): void
    {
        foreach (['student', 'counselor', 'admin'] as $role) {
            $owner = User::factory()->create(['role' => $role]);
            $other = User::factory()->create(['role' => $role]);
            $mine = $this->notice($owner, ['from_user_id' => $other->id]);
            $this->notice($other, ['title' => 'Other person private update']);

            $response = $this->actingAs($owner)->getJson(route($role . '.notifications.get'));
            $response->assertOk()->assertJsonPath('unreadCount', 1)
                ->assertJsonPath('notifications.0.id', $mine->id)
                ->assertJsonMissingPath('notifications.0.from_user_id')
                ->assertJsonPath('notifications.0.user_id', $owner->id)
                ->assertDontSee('Other person private update');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_latest_twenty_are_ordered_stably_while_unread_count_includes_all(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $created = [];
        for ($i = 0; $i < 25; $i++) {
            $created[] = $this->notice($student, ['title' => 'Update ' . $i]);
        }

        $response = $this->actingAs($student)->getJson(route('student.notifications.get'));
        $response->assertOk()->assertJsonCount(20, 'notifications')->assertJsonPath('unreadCount', 25)
            ->assertJsonPath('notifications.0.id', $created[24]->id);
        $this->actingAs($student)->postJson(route('student.notifications.read-all'))->assertOk();
        $this->assertSame(0, Notification::where('user_id', $student->id)->where('is_read', false)->count());
    }

    public function test_read_delete_and_read_all_are_scoped_to_owner_for_each_role(): void
    {
        foreach (['student', 'counselor', 'admin'] as $role) {
            $owner = User::factory()->create(['role' => $role]);
            $other = User::factory()->create(['role' => $role]);
            $mine = $this->notice($owner);
            $otherNotice = $this->notice($other);

            $this->actingAs($owner)->postJson(route($role . '.notifications.read', $otherNotice->id))->assertNotFound();
            $this->actingAs($owner)->deleteJson(route($role . '.notifications.delete', $otherNotice->id))->assertNotFound();
            $this->actingAs($owner)->postJson(route($role . '.notifications.read', $mine->id))->assertOk()->assertJsonPath('success', true);
            $this->assertTrue($mine->fresh()->is_read);
            $this->assertFalse($otherNotice->fresh()->is_read);

            $second = $this->notice($owner);
            $this->actingAs($owner)->postJson(route($role . '.notifications.read-all'))->assertOk();
            $this->assertTrue($second->fresh()->is_read);
            $this->assertFalse($otherNotice->fresh()->is_read);
            $this->actingAs($owner)->deleteJson(route($role . '.notifications.delete', $mine->id))->assertOk();
            $this->assertDatabaseMissing('notifications', ['id' => $mine->id]);
            $this->assertDatabaseHas('notifications', ['id' => $otherNotice->id]);
        }
    }

    public function test_notification_endpoints_require_authentication_and_correct_role(): void
    {
        $this->getJson(route('student.notifications.get'))->assertUnauthorized();
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->getJson(route('admin.notifications.get'))->assertForbidden();
        $this->actingAs($student)->getJson(route('counselor.notifications.get'))->assertForbidden();

        $pending = User::factory()->create(['role' => 'counselor', 'approval_status' => 'pending']);
        $this->actingAs($pending)->get(route('counselor.notifications.get'))->assertRedirect(route('account.status'));
    }

    public function test_shared_component_is_available_in_each_role_workspace(): void
    {
        foreach (['student' => 'home', 'counselor' => 'counselor.dashboard', 'admin' => 'admin.dashboard'] as $role => $page) {
            $user = User::factory()->create(['role' => $role]);
            $html = $this->actingAs($user)->get(route($page))->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'data-notification-center'));
            $this->assertStringContainsString(route($role . '.notifications.get', [], false), $html);
            $this->assertStringContainsString('data-notice-read-all', $html);
            $this->assertStringContainsString('data-notice-browser-alerts', $html);
            $this->assertStringNotContainsString('data-notice-toast-region', $html);
            $this->assertStringContainsString('data-home-url="' . route($role . '.dashboard', [], false) . '"', $html);
            $this->assertStringContainsString('aria-label="Close notifications"><svg', $html);
            $this->assertStringNotContainsString('aria-label="Close notifications">&times;', $html);
            $this->assertStringContainsString('notification-center.js', $html);
        }
    }
}
