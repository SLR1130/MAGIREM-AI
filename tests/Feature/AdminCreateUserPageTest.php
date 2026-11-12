<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCreateUserPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_the_restyled_create_user_form_and_existing_named_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.create-user'))
            ->assertOk()
            ->assertSee('Identity &amp; access', false)
            ->assertSee('Create new user')
            ->assertSee('designated Guidance reviewer')
            ->assertSee(route('admin.users'), false)
            ->assertSee(route('admin.store-user'), false)
            ->assertSee('Institutional verification &amp; access', false)
            ->assertSee('not a self-registration application', false)
            ->assertSee('Only administrator accounts set a password here.', false)
            ->assertSee('notification_email_contact_confirmed', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('student-fields', false)
            ->assertSee('counselor-fields', false)
            ->assertSee('aria-label="Back to users"', false)
            ->assertSee('acu-field--email', false)
            ->assertSee('id="acu-role-card"', false)
            ->assertDontSee('class="acu-page-icon"', false)
            ->assertSee('data-password-toggle', false);
    }

    public function test_non_admin_cannot_access_create_user_page(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)->get(route('admin.create-user'))->assertForbidden();
    }

    public function test_password_confirmation_is_enforced_without_creating_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->from(route('admin.create-user'))
            ->post(route('admin.store-user'), [
                'name' => 'Synthetic Test User',
                'email' => 'synthetic-confirmation@example.test',
                'role' => 'admin',
                'password' => 'secure-test-password',
                'password_confirmation' => 'different-test-password',
            ])
            ->assertRedirect(route('admin.create-user'))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'synthetic-confirmation@example.test']);
    }

    public function test_counselor_creation_keeps_profile_fields_and_requires_owner_password_setup(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)
            ->post(route('admin.store-user'), [
                'name' => 'Synthetic Counselor',
                'email' => 'Synthetic.Counselor@MINsu.EDU.PH',
                'role' => 'counselor',
                'specialization' => 'General Wellness',
                'years_of_experience' => 5,
                'availability_schedule' => 'By Appointment',
                'status' => 'offline',
            ])->assertRedirect(route('admin.users'))->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'synthetic.counselor@minsu.edu.ph',
            'role' => 'counselor',
            'specialization' => 'General Wellness',
            'years_of_experience' => 5,
            'availability_schedule' => 'By Appointment',
            'status' => 'offline',
        ]);
        $counselor = User::query()->where('email', 'synthetic.counselor@minsu.edu.ph')->firstOrFail();
        $this->assertFalse($counselor->is_active);
        $this->assertSame('pending', $counselor->approval_status);
        $this->assertSame('counselor', $counselor->requested_role);
        $this->assertNull($counselor->approval_submitted_at);
        $this->assertNull($counselor->notification_email_verified_at);
        $this->assertDatabaseHas('account_verification_audits', [
            'application_user_id' => $counselor->id,
            'actor_user_id' => $admin->id,
            'action' => 'admin_created_pending',
        ]);
        $this->assertNull($counselor->password_setup_completed_at);
        $this->assertTrue($counselor->requiresOwnerOnboarding());
        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true, 'approval_status' => 'approved']);
        config([
            'services.account_verification.reviewer_user_ids' => [$reviewer->id],
            'services.account_verification.reviewer_authorization_approved' => true,
        ]);
        $this->actingAs($reviewer)->get(route('admin.account-verification.index', ['source' => 'admin_created']))
            ->assertOk()->assertSee('Synthetic Counselor')->assertSee('Admin-created restricted accounts');
        $this->get(route('admin.account-verification.index', ['source' => 'self_registration']))
            ->assertOk()->assertDontSee('Synthetic Counselor');
    }

    public function test_student_creation_keeps_profile_fields_and_requires_owner_password_setup(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)
            ->post(route('admin.store-user'), [
                'name' => 'Synthetic Student',
                'email' => 'synthetic-student@minsu.edu.ph',
                'role' => 'student',
                'student_id' => 'TEST-0001',
                'program' => 'BSIT',
                'year_level' => '2nd Year',
            ])->assertRedirect(route('admin.users'))->assertSessionHas('success');

        $student = User::query()->where('email', 'synthetic-student@minsu.edu.ph')->firstOrFail();
        $this->assertSame('student', $student->role);
        $this->assertSame('TEST-0001', $student->student_id);
        $this->assertSame('BSIT', $student->program);
        $this->assertSame('2nd Year', $student->year_level);
        $this->assertSame('synthetic-student@minsu.edu.ph', $student->institutional_email);
        $this->assertFalse($student->is_active);
        $this->assertSame('pending', $student->approval_status);
        $this->assertNull($student->notification_email_verified_at);
        $this->assertNull($student->password_setup_completed_at);
        $this->assertTrue($student->requiresOwnerOnboarding());
        $this->assertNotNull($student->password);
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $student->email, 'password' => 'any-guessed-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_explicitly_authorized_reviewer_can_create_an_approved_student_without_applicant_otp(): void
    {
        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true, 'approval_status' => 'approved']);
        config([
            'services.account_verification.reviewer_user_ids' => [$reviewer->id],
            'services.account_verification.reviewer_authorization_approved' => true,
        ]);
        $this->actingAs($reviewer)->post(route('admin.store-user'), [
            'name' => 'Verified Synthetic Student',
            'email' => 'verified.student@minsu.edu.ph',
            'role' => 'student',
            'student_id' => 'VERIFIED-TEST-01',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'approve_verified' => '1',
            'invitation_method' => 'in_person',
            'institutional_identity_confirmed' => '1',
            'eligibility_confirmed' => '1',
            'verification_basis' => 'Confirmed through synthetic official enrollment fixture.',
        ])->assertOk()->assertSee('One-time in-person handoff')->assertSee('handoff-token');

        $student = User::query()->where('email', 'verified.student@minsu.edu.ph')->firstOrFail();
        $this->assertSame('approved', $student->approval_status);
        $this->assertTrue($student->is_active);
        $this->assertNotNull($student->institutional_verified_at);
        $this->assertSame($reviewer->id, $student->approval_reviewer_id);
        $this->assertNotNull($student->approval_reviewed_at);
        $this->assertNull($student->approval_submitted_at);
        $this->assertNull($student->notification_email);
        $this->assertNull($student->notification_email_verified_at);
        $this->assertNull($student->email_verified_at);
        $this->assertNull($student->password_setup_completed_at);
        $this->assertTrue($student->requiresOwnerOnboarding());
        $this->assertDatabaseHas('account_verification_audits', [
            'application_user_id' => $student->id,
            'actor_user_id' => $reviewer->id,
            'action' => 'admin_created_approved',
            'to_status' => 'approved',
        ]);
        $this->assertTrue($student->canReviewAccountVerification() === false, 'Student must not inherit reviewer access.');
    }

    public function test_non_reviewer_cannot_forge_direct_approval_during_admin_creation(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.store-user'), [
            'name' => 'Forged Approval Student',
            'email' => 'forged.student@minsu.edu.ph',
            'role' => 'student',
            'student_id' => 'FORGED-TEST-01',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'approve_verified' => '1',
            'institutional_identity_confirmed' => '1',
            'eligibility_confirmed' => '1',
            'verification_basis' => 'Forged client supplied value.',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'forged.student@minsu.edu.ph']);
    }

    public function test_authorized_admin_creation_of_counselor_requires_official_staff_authorization(): void
    {
        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true, 'approval_status' => 'approved']);
        config([
            'services.account_verification.reviewer_user_ids' => [$reviewer->id],
            'services.account_verification.reviewer_authorization_approved' => true,
        ]);

        $this->actingAs($reviewer)->from(route('admin.create-user'))->post(route('admin.store-user'), [
            'name' => 'Synthetic Counselor',
            'email' => 'counselor.verify@minsu.edu.ph',
            'role' => 'counselor',
            'specialization' => 'General Wellness',
            'years_of_experience' => 4,
            'availability_schedule' => 'By Appointment',
            'approve_verified' => '1',
            'invitation_method' => 'in_person',
            'institutional_identity_confirmed' => '1',
            'eligibility_confirmed' => '1',
            'verification_basis' => 'Confirmed via synthetic staff registry fixture.',
        ])->assertRedirect(route('admin.create-user'))->assertSessionHasErrors('counselor_authorized');

        $this->assertDatabaseMissing('users', ['email' => 'counselor.verify@minsu.edu.ph']);
    }

    public function test_reviewer_can_later_approve_restricted_admin_created_account_without_applicant_otp(): void
    {
        $creator = User::factory()->create(['role' => 'admin', 'is_active' => true, 'approval_status' => 'approved']);
        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true, 'approval_status' => 'approved']);
        config([
            'services.account_verification.reviewer_user_ids' => [$reviewer->id],
            'services.account_verification.reviewer_authorization_approved' => true,
        ]);

        $this->actingAs($creator)->post(route('admin.store-user'), [
            'name' => 'Pending Synthetic Student',
            'email' => 'pending.student@minsu.edu.ph',
            'role' => 'student',
            'student_id' => 'PENDING-TEST-01',
            'program' => 'BSIT',
            'year_level' => '1st Year',
        ])->assertRedirect(route('admin.users'));

        $student = User::query()->where('email', 'pending.student@minsu.edu.ph')->firstOrFail();
        $this->assertFalse($student->is_active);
        $this->assertNull($student->approval_submitted_at);

        $this->actingAs($reviewer)
            ->get(route('admin.account-verification.index', ['source' => 'admin_created']))
            ->assertOk()->assertSee('Pending Synthetic Student');
        $this->get(route('admin.account-verification.show', $student))
            ->assertOk()->assertSee('admin-created restricted account')->assertSee('Verification basis');

        $this->post(route('admin.account-verification.approve', $student), [
            'verification_basis' => 'Confirmed in the synthetic official enrollment register.',
            'internal_review_reason' => 'Enrollment and identity were independently confirmed.',
            'institutional_identity_confirmed' => '1',
            'eligibility_confirmed' => '1',
            'invitation_method' => 'in_person',
        ])->assertOk()->assertSee('One-time in-person handoff')->assertSee('handoff-token');

        $student->refresh();
        $this->assertTrue($student->is_active);
        $this->assertSame('approved', $student->approval_status);
        $this->assertSame($reviewer->id, $student->approval_reviewer_id);
        $this->assertNull($student->notification_email_verified_at);
        $this->assertTrue($student->requiresOwnerOnboarding());
        $this->assertNull($student->password_setup_completed_at);
        $this->assertDatabaseHas('account_verification_audits', [
            'application_user_id' => $student->id,
            'actor_user_id' => $reviewer->id,
            'action' => 'admin_created_approved',
            'from_status' => 'pending',
            'to_status' => 'approved',
        ]);
    }

    public function test_student_and_counselor_admin_creation_requires_exact_institutional_domain(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->from(route('admin.create-user'))->post(route('admin.store-user'), [
            'name' => 'Wrong Domain Student',
            'email' => 'student@minsu.edu.ph.attacker.example',
            'role' => 'student',
            'student_id' => 'WRONG-DOMAIN-01',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
        ])->assertRedirect(route('admin.create-user'))->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'student@minsu.edu.ph.attacker.example']);
    }

    public function test_duplicate_email_is_rejected_without_creating_another_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        User::factory()->create(['email' => 'already-used@example.test', 'role' => 'student']);

        $this->actingAs($admin)
            ->from(route('admin.create-user'))
            ->post(route('admin.store-user'), [
                'name' => 'Duplicate Synthetic User',
                'email' => 'already-used@example.test',
                'role' => 'admin',
                'password' => 'secure-test-password',
                'password_confirmation' => 'secure-test-password',
            ])
            ->assertRedirect(route('admin.create-user'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 2);
    }
}
