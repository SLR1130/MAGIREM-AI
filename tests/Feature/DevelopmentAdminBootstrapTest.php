<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevelopmentAdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_creates_one_admin_only_for_an_empty_isolated_test_database(): void
    {
        $this->artisan('magirem:bootstrap-admin')
            ->expectsConfirmation('Create exactly one active administrator on this local database?', 'yes')
            ->expectsQuestion('Administrator name', 'Development Owner')
            ->expectsQuestion('Administrator email', 'owner@example.test')
            ->expectsQuestion('Administrator password (input hidden)', 'a-long-test-password-456')
            ->expectsQuestion('Confirm administrator password (input hidden)', 'a-long-test-password-456')
            ->expectsOutput('One local administrator was created and verified.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('users', 1);
        $user = User::query()->firstOrFail();
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('a-long-test-password-456', $user->password));

        $this->post('/login', [
            'email' => 'owner@example.test',
            'password' => 'a-long-test-password-456',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_bootstrap_refuses_to_run_again_after_a_user_exists(): void
    {
        User::factory()->create(['role' => 'admin']);

        $this->artisan('magirem:bootstrap-admin')
            ->expectsOutput('Bootstrap refused: users table is not empty.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 1);
    }
}
