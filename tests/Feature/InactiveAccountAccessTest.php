<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InactiveAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_account_cannot_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive-login@example.test',
            'role' => 'student',
        ]);
        $user->forceFill(['password' => Hash::make('correct-password'), 'is_active' => false])->save();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_existing_inactive_session_is_logged_out_from_student_routes(): void
    {
        $user = User::factory()->create(['role' => 'student', 'email' => 'session-student@example.test']);
        $user->forceFill(['is_active' => true, 'password' => Hash::make('correct-password')])->save();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'correct-password'])
            ->assertRedirect(route('home'));
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        Auth::forgetGuards();

        $this->get(route('student.journal'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_existing_inactive_session_is_blocked_from_video_call_routes(): void
    {
        $user = User::factory()->create(['role' => 'student', 'email' => 'session-video@example.test']);
        $user->forceFill(['is_active' => true, 'password' => Hash::make('correct-password')])->save();
        $route = app('router')->getRoutes()->getByName('video-calls.incoming');
        $this->assertNotNull($route);
        $this->assertContains('secure.auth', $route->gatherMiddleware());

        $this->post(route('login'), ['email' => $user->email, 'password' => 'correct-password'])
            ->assertRedirect(route('home'));
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        Auth::forgetGuards();

        $this->get(route('video-calls.incoming'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_inactive_session_is_rejected_before_public_home_can_load_personalized_data(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_active_account_can_still_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'active-login@example.test',
            'role' => 'student',
        ]);
        $user->forceFill(['password' => Hash::make('correct-password'), 'is_active' => true])->save();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }
}
