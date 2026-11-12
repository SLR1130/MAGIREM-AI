<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkspacePolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_directory_and_reports_keep_their_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        User::factory()->create(['role' => 'student', 'name' => 'Synthetic student']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Platform control center')
            ->assertSee('aria-label="Current admin section"', false)
            ->assertSee('aria-controls="appSidebar" aria-expanded="false"', false)
            ->assertSee('body.admin-layout .admin-sidebar-footer {', false)
            ->assertSee('The header account menu provides account details and logout on mobile.', false)
            ->assertSee('aria-label="Open account menu"', false)
            ->assertSee('class="user-dropdown-logout"', false)
            ->assertSee('data-adm-tab="overview" aria-pressed="true"', false)
            ->assertSee('data-adm-tab="activity" aria-pressed="false"', false)
            ->assertSee('data-adm-tab="tools" aria-pressed="false"', false)
            ->assertSee('adminWorkspaceEnter')
            ->assertSee('Mobile-only dashboard bento')
            ->assertSee('.admin-dash .adm-stats { grid-template-columns:repeat(2,minmax(0,1fr))', false)
            ->assertDontSee('System health')
            ->assertDontSee('Operational');

        $this->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Synthetic student')
            ->assertSee('Total users')
            ->assertSee('Visible now')
            ->assertSee('aria-label="Search users on this page"', false)
            ->assertSee('data-user-role="all" aria-pressed="true"', false)
            ->assertSee('aria-label="Account directory; scroll horizontally to view all columns"', false)
            ->assertSee('compact floating action on phones')
            ->assertSee('Create new user')
            ->assertSee(route('admin.create-user'), false);

        $this->get(route('admin.create-user'))
            ->assertOk()
            ->assertSee(route('admin.store-user'), false)
            ->assertSee('notification_email_contact_confirmed', false);

        $this->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('Mobile-only report bento', false)
            ->assertSee('.admin-reports .ar-stats { grid-template-columns:repeat(2,minmax(0,1fr))', false)
            ->assertSee('.admin-reports .ar-tabs { display:grid; grid-template-columns:repeat(2,minmax(0,1fr))', false)
            ->assertSee('data-report-tab="trends" aria-pressed="true"', false)
            ->assertSee('data-report-tab="distribution" aria-pressed="false"', false)
            ->assertSee('data-report-tab="risk" aria-pressed="false"', false)
            ->assertSee('data-report-tab="engagement" aria-pressed="false"', false)
            ->assertSee('id="emotionTrendsChart"', false)
            ->assertSee('data-trend-series="0"', false)
            ->assertSee('View trend data table')
            ->assertSee('View journal data table')
            ->assertSee('prefers-reduced-motion: reduce', false)
            ->assertSee('id="emotionCategoryDrilldown"', false)
            ->assertSee('aria-controls="emotionCategoryDrilldown" aria-expanded="false"', false)
            ->assertSee('Charts could not load.');
    }

    public function test_authorized_review_keeps_verification_and_privacy_controls(): void
    {
        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        config([
            'services.account_verification.reviewer_user_ids' => [$reviewer->id],
            'services.account_verification.reviewer_authorization_approved' => true,
        ]);
        $applicant = User::factory()->create([
            'role' => 'student',
            'name' => 'Synthetic applicant',
            'requested_role' => 'student',
            'approval_status' => 'pending',
            'approval_submitted_at' => now(),
            'institutional_email' => 'synthetic.applicant@minsu.edu.ph',
        ]);

        $this->actingAs($reviewer)->get(route('admin.account-verification.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Synthetic applicant')
            ->assertSee('class="avi-btn avi-btn--light avi-back"', false)
            ->assertSee('aria-label="Back to users"', false)
            ->assertSee(route('admin.users'), false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('aria-label="Applications; scroll horizontally to view all columns"', false);

        $this->get(route('admin.account-verification.show', $applicant))
            ->assertOk()
            ->assertSee('Review application')
            ->assertSee('institutional_identity_confirmed', false)
            ->assertSee('eligibility_confirmed', false)
            ->assertSee('Private identification image');

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($student)->get(route('admin.account-verification.show', $applicant))->assertForbidden();
    }
}
