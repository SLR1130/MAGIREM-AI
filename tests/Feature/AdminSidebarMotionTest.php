<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarMotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_keeps_navigation_and_accessible_motion_states(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('<body class="app-layout admin-layout">', false)
            ->assertSee('aria-label="Administrator navigation"', false)
            ->assertSee(route('admin.dashboard'), false)
            ->assertSee(route('student.community'), false)
            ->assertSee(route('admin.users'), false)
            ->assertSee(route('admin.reports'), false)
            ->assertSee('adminSidebarReveal')
            ->assertSee('adminDashboardRise')
            ->assertSee('body.admin-layout .admin-sidebar.mobile-open .sidebar-item', false)
            ->assertSee('body.admin-layout .sidebar-overlay.active', false)
            ->assertSee('@media (prefers-reduced-motion: reduce)', false)
            ->assertSee('.admin-sidebar .sidebar-link:focus-visible', false);
    }

    public function test_counselor_keeps_the_separate_counselor_shell(): void
    {
        $counselor = User::factory()->create(['role' => 'counselor']);

        $this->actingAs($counselor)->get(route('counselor.dashboard'))
            ->assertOk()
            ->assertSee('<body class="app-layout counselor-layout">', false)
            ->assertSee('aria-label="Counselor workspace navigation"', false)
            ->assertDontSee('aria-label="Administrator navigation"', false);
    }
}
