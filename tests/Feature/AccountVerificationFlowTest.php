<?php

namespace Tests\Feature;

use App\Jobs\SendAccountVerificationEmail;
use App\Mail\AccountVerificationMail;
use App\Models\AccountEmailVerification;
use App\Models\AccountVerificationAudit;
use App\Models\AccountVerificationEmailDelivery;
use App\Models\User;
use App\Services\AccountVerificationEmailService;
use App\Services\VerificationDocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AccountVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT = '11111111-2222-4333-8444-555555555555';

    private ?User $designatedReviewer = null;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.microsoft.tenant_id' => self::TENANT,
            'services.microsoft.institutional_domain' => 'minsu.edu.ph',
            'services.microsoft.enabled' => false,
            'services.microsoft.client_id' => null,
            'services.microsoft.client_secret' => null,
            'services.account_verification.native_registration_enabled' => true,
            'services.account_verification.reviewer_user_ids' => [],
            'services.account_verification.reviewer_authorization_approved' => false,
            'services.account_verification.privacy_notice_approved' => true,
            'services.account_verification.privacy_notice_version' => 'synthetic-test-notice-v1',
            'services.account_verification.privacy_notice_text' => 'Synthetic test privacy notice; not an institutional policy.',
            'services.account_verification.privacy_contact' => 'privacy-test@minsu.edu.ph',
            'services.account_verification.retention_policy_approved' => true,
            'services.account_verification.retention_policy_version' => 'synthetic-test-retention-v1',
            'services.account_verification.retention_schedule' => 'Synthetic test retention schedule.',
            'services.account_verification.id_retention_days_after_review' => 30,
            'services.account_verification.id_retention_statuses' => ['approved', 'rejected'],
            'services.account_verification.id_retention_enforcement_enabled' => true,
        ]);
    }

    public function test_registration_page_fails_closed_by_default_without_exposing_signup_fields(): void
    {
        config(['services.account_verification.native_registration_enabled' => false]);
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Student registration is not open yet')
            ->assertSee('Applications paused')
            ->assertSee('Native registration is currently closed')
            ->assertSee('Student account applications')
            ->assertDontSee('name="requested_role"', false)
            ->assertDontSee('id="registrationForm"', false)
            ->assertSee('assets/branding/magirem-emblem.png')
            ->assertDontSee('name="password"', false)
            ->assertDontSee('Microsoft sign-in', false)
            ->assertSee(route('login'), false);

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('microsoft.redirect'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('microsoft.callback'));
    }

    public function test_native_registration_requires_institutional_email_and_strong_password(): void
    {
        $this->registrationReviewer();
        $this->post(route('register.submit'), [
            'name' => 'Unverified Applicant',
            'institutional_email' => 'applicant@gmail.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
            'student_id' => 'NO-SSO-1',
        ])->assertSessionHasErrors(['institutional_email', 'password', 'notification_email', 'campus', 'privacy_acknowledgement']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_password_recovery_is_linked_from_login_and_uses_the_existing_guest_routes(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Forgot password?')->assertSee(route('password.request'), false)->assertSee('assets/branding/magirem-emblem.png');
        $this->get(route('password.request'))->assertOk()->assertSee('verified personal Gmail')->assertSee('assets/branding/magirem-logo-full.png');
        $this->get(route('password.reset'))->assertOk()->assertSee('assets/branding/magirem-logo-full.png')->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('password.email'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('password.update'));
        $this->get('/password/reset')->assertNotFound();
        $this->get('/reset-password/synthetic-token')->assertNotFound();
    }

    public function test_institutional_email_domain_is_case_insensitive_but_exact_and_owned_once(): void
    {
        $this->registrationReviewer();
        Mail::fake();
        Queue::fake();
        Storage::fake('local');
        $payload = $this->studentPayload('Student.One@MINsu.EDU.PH');
        $this->post(route('register.submit'), $payload)->assertRedirect(route('account.status'));
        $this->assertDatabaseHas('users', [
            'email' => 'student.one@minsu.edu.ph',
            'institutional_email' => 'student.one@minsu.edu.ph',
        ]);

        $this->post(route('logout'));
        $duplicate = $this->studentPayload('STUDENT.ONE@MINsu.EDU.PH');
        $duplicate['student_id'] = 'A-DIFFERENT-STUDENT-ID';
        $this->from(route('register'))->post(route('register.submit'), $duplicate)
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('institutional_email');
        $this->assertDatabaseCount('users', 2);

        $spoofed = $this->studentPayload('student.two@minsu.edu.ph.evil.example');
        $spoofed['student_id'] = 'ANOTHER-TEST-ID';
        $this->from(route('register'))->post(route('register.submit'), $spoofed)
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('institutional_email');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_registration_stays_closed_until_an_institutional_privacy_contact_and_retention_schedule_exist(): void
    {
        config([
            'services.account_verification.native_registration_enabled' => true,
            'services.account_verification.privacy_notice_approved' => false,
            'services.account_verification.privacy_contact' => null,
            'services.account_verification.retention_policy_approved' => false,
            'services.account_verification.retention_schedule' => null,
        ]);
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('institution-approved privacy notice')
            ->assertDontSee('name="password"', false);

        $this->post(route('register.submit'), $this->studentPayload())
            ->assertRedirect(route('register'))
            ->assertSessionHas('registration_error');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_local_development_registration_can_open_without_production_policy_flags(): void
    {
        $this->app->detectEnvironment(static fn () => 'local');
        $reviewer = $this->registrationReviewer();
        config([
            'services.account_verification.privacy_notice_approved' => false,
            'services.account_verification.privacy_notice_version' => null,
            'services.account_verification.privacy_notice_text' => null,
            'services.account_verification.privacy_contact' => null,
            'services.account_verification.retention_policy_approved' => false,
            'services.account_verification.retention_policy_version' => null,
            'services.account_verification.retention_schedule' => null,
            'services.account_verification.id_retention_days_after_review' => null,
            'services.account_verification.id_retention_statuses' => [],
            'services.account_verification.id_retention_enforcement_enabled' => false,
            'services.account_verification.reviewer_user_ids' => [$reviewer->id],
            'services.account_verification.reviewer_authorization_approved' => true,
        ]);
        Mail::fake();
        Queue::fake();
        Storage::fake('local');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Step 1 of 5')
            ->assertSee('LOCAL-DEVELOPMENT-NOTICE')
            ->assertSee('not an institution-approved production privacy policy')
            ->assertSee('name="privacy_acknowledgement"', false);

        $payload = $this->studentPayload('local.student@minsu.edu.ph');
        $payload['_token'] = csrf_token();
        $this->post(route('register.submit'), $payload)
            ->assertRedirect(route('account.status'));

        $applicant = User::query()->where('email', 'local.student@minsu.edu.ph')->firstOrFail();
        $this->assertSame('pending', $applicant->approval_status);
        $this->assertSame('LOCAL-DEVELOPMENT-NOTICE', $applicant->privacy_consent_version);
        $this->assertAuthenticatedAs($applicant);
    }

    public function test_registration_requires_approved_privacy_retention_and_a_real_authorized_guidance_reviewer(): void
    {
        config(['services.account_verification.reviewer_user_ids' => [], 'services.account_verification.reviewer_authorization_approved' => false]);
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('existing active administrator is explicitly authorized as a Guidance Office reviewer')
            ->assertDontSee('name="password"', false);

        config([
            'services.account_verification.reviewer_user_ids' => [999999],
            'services.account_verification.reviewer_authorization_approved' => true,
        ]);
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('existing active administrator is explicitly authorized as a Guidance Office reviewer')
            ->assertDontSee('name="password"', false);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        config([
            'services.account_verification.reviewer_user_ids' => [$admin->id],
            'services.account_verification.reviewer_authorization_approved' => false,
        ]);
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('existing active administrator is explicitly authorized as a Guidance Office reviewer')
            ->assertDontSee('name="password"', false);

        config(['services.account_verification.reviewer_authorization_approved' => true]);
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertSee('Synthetic test privacy notice; not an institutional policy.');
        $this->assertTrue($admin->canReviewAccountVerification());
    }

    public function test_local_registration_preview_is_loopback_only_and_requires_private_sqlite_database(): void
    {
        $reviewer = $this->registrationReviewer();
        $this->app->detectEnvironment(static fn () => 'local');
        config([
            'services.account_verification.local_registration_preview' => true,
            'services.account_verification.native_registration_enabled' => false,
            'services.account_verification.privacy_notice_approved' => false,
            'services.account_verification.retention_policy_approved' => false,
            'services.account_verification.reviewer_user_ids' => [$reviewer->id],
            'services.account_verification.reviewer_authorization_approved' => true,
            'database.default' => 'mysql',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('http://127.0.0.1/register')
            ->assertOk()
            ->assertSee('Student registration is not open yet')
            ->assertDontSee('name="password"', false);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => storage_path('app/audit-recovery/magirem-port8000-registration.sqlite'),
        ]);
        $this->get('http://example.invalid/register')
            ->assertOk()
            ->assertSee('Student registration is not open yet')
            ->assertDontSee('name="password"', false);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->get('http://127.0.0.1/register')
            ->assertOk()
            ->assertSee('Student registration is not open yet')
            ->assertDontSee('name="password"', false);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('http://127.0.0.1/register')
            ->assertOk()
            ->assertSee('Local registration test only')
            ->assertSee('LOCAL-SYNTHETIC-TEST')
            ->assertSee('not an institution-approved MinSU privacy notice')
            ->assertSee('name="password"', false);
    }

    public function test_native_registration_and_existing_admin_login_do_not_need_microsoft_configuration(): void
    {
        $admin = $this->registrationReviewer();
        config([
            'services.microsoft.enabled' => false,
            'services.microsoft.client_id' => null,
            'services.microsoft.client_secret' => null,
        ]);
        Mail::fake();
        Queue::fake();
        Storage::fake('local');
        $this->post(route('register.submit'), $this->studentPayload())->assertRedirect(route('account.status'));
        $applicant = User::query()->where('email', 'synthetic.student@minsu.edu.ph')->firstOrFail();
        $this->assertTrue(Hash::check('Synthetic-Applicant-Password-42!', $applicant->password));
        $this->assertNull($applicant->email_verified_at);
        $this->assertNull($applicant->institutional_verified_at);
        $this->assertNull($applicant->microsoft_tenant_id);
        $this->assertSame('pending', $applicant->approval_status);
        $this->assertSame('synthetic.student@minsu.edu.ph', $applicant->institutional_email);
        $this->assertAuthenticatedAs($applicant);
        $this->get(route('student.journal'))->assertRedirect(route('account.status'));
        $this->post(route('logout'));

        $admin->forceFill(['password' => Hash::make('Synthetic-Admin-Password-Only-For-Test')])->save();
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name@minsu.edu.ph')
            ->assertSee('continue using their existing account credentials');
        $this->post(route('login'), ['email' => $admin->email, 'password' => 'Synthetic-Admin-Password-Only-For-Test'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin', 'is_active' => true]);
        $this->assertDatabaseHas('users', ['id' => $applicant->id, 'approval_status' => 'pending']);
    }

    public function test_student_application_is_created_pending_and_cannot_access_protected_routes(): void
    {
        $this->registrationReviewer();
        Mail::fake();
        Queue::fake();
        Storage::fake('local');
        $identity = $this->identity('student-101');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('MinSU institutional email')
            ->assertSee('Synthetic test privacy notice; not an institutional policy.')
            ->assertSee('getUserMedia', false)
            ->assertSee('facingMode', false)
            ->assertSee('alternative_review', false);

        $this->post(route('register.submit'), $this->studentPayload($identity['email']))
            ->assertRedirect(route('account.status'));

        $applicant = User::query()->where('email', $identity['email'])->firstOrFail();
        $this->assertSame('pending', $applicant->approval_status);
        $this->assertSame('student', $applicant->requested_role);
        $this->assertSame('student', $applicant->role);
        $this->assertTrue($applicant->is_active);
        $this->assertTrue(Hash::check('Synthetic-Applicant-Password-42!', $applicant->password));
        $this->assertSame($identity['email'], $applicant->institutional_email);
        $this->assertNull($applicant->institutional_verified_at);
        $this->assertNull($applicant->email_verified_at);
        $this->assertNull($applicant->notification_email_verified_at);
        $this->assertNotNull($applicant->alternative_review_requested_at);
        $this->assertDatabaseHas('account_verification_audits', ['application_user_id' => $applicant->id, 'action' => 'submitted', 'to_status' => 'pending']);
        $this->assertDatabaseHas('privacy_settings', ['user_id' => $applicant->id]);

        $this->assertAuthenticatedAs($applicant);
        $this->get(route('account.status'))
            ->assertOk()
            ->assertSee('Your student application status')
            ->assertSee('pending')
            ->assertSee('class="account-status-layout"', false)
            ->assertSee('account-status-main', false);
        $this->get(route('student.journal'))->assertRedirect(route('account.status'));
        $this->get(route('student.ai-chat'))->assertRedirect(route('account.status'));
        $this->assertDatabaseCount('users', 2);
    }

    public function test_student_id_image_is_reencoded_and_stored_privately(): void
    {
        $this->registrationReviewer();
        Storage::fake('local');
        Mail::fake();
        Queue::fake();
        $identity = $this->identity('student-photo');
        $payload = $this->studentPayload($identity['email']);
        unset($payload['alternative_review']);
        $payload['id_photo'] = UploadedFile::fake()->image('school-card.png', 640, 360);

        $this->post(route('register.submit'), $payload)
            ->assertRedirect(route('account.status'));

        $applicant = User::query()->where('email', $identity['email'])->firstOrFail();
        $this->assertStringStartsWith('verification-documents/', $applicant->verification_document_path);
        $this->assertStringEndsWith('.webp', $applicant->verification_document_path);
        Storage::disk('local')->assertExists($applicant->verification_document_path);
        $this->assertFalse(Storage::disk('public')->exists($applicant->verification_document_path));
        $this->assertNotNull($applicant->verification_document_submitted_at);
    }

    public function test_invalid_id_image_is_rejected_and_no_application_is_created(): void
    {
        $this->registrationReviewer();
        Storage::fake('local');
        $identity = $this->identity('student-bad-image');
        $payload = $this->studentPayload($identity['email']);
        $payload['id_photo'] = UploadedFile::fake()->create('id.svg', 20, 'image/svg+xml');
        unset($payload['alternative_review']);

        $this->from(route('register'))
            ->post(route('register.submit'), $payload)
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('id_photo');

        $this->assertDatabaseCount('users', 1);
        $this->assertSame([], Storage::disk('local')->allFiles('verification-documents'));
    }

    public function test_public_registration_is_student_only_and_campus_is_limited_to_three_minusu_campuses(): void
    {
        $this->registrationReviewer();
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Create your student account')
            ->assertSee('Student information')
            ->assertSee('Bongabong Campus')
            ->assertSee('Calapan Campus')
            ->assertSee('Victoria Campus')
            ->assertDontSee('name="requested_role"', false)
            ->assertDontSee('name="staff_position"', false);

        $identity = $this->identity('public-counselor-blocked');
        $payload = $this->studentPayload($identity['email']);
        $payload['requested_role'] = 'counselor';
        $this->from(route('register'))
            ->post(route('register.submit'), $payload)
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('requested_role');
        $this->assertDatabaseMissing('users', ['email' => $identity['email']]);

        $identity = $this->identity('public-admin-blocked');
        $payload = $this->studentPayload($identity['email']);
        $payload['requested_role'] = 'admin';
        $this->from(route('register'))
            ->post(route('register.submit'), $payload)
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('requested_role');
        $this->assertDatabaseMissing('users', ['email' => $identity['email']]);

        $identity = $this->identity('public-role-field-blocked');
        $payload = $this->studentPayload($identity['email']);
        $payload['role'] = 'admin';
        $this->from(route('register'))
            ->post(route('register.submit'), $payload)
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => $identity['email']]);

        $identity = $this->identity('public-invalid-campus');
        $payload = $this->studentPayload($identity['email']);
        $payload['campus'] = 'Calapan Guidance Office';
        $this->from(route('register'))
            ->post(route('register.submit'), $payload)
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('campus');
        $this->assertDatabaseMissing('users', ['email' => $identity['email']]);
    }

    public function test_student_registration_renders_as_a_five_step_wizard_without_exposing_password_in_review(): void
    {
        $this->registrationReviewer();

        $response = $this->get(route('register'));
        $response->assertOk()
            ->assertSee('Step 1 of 5')
            ->assertSee('Compact the existing split wizard on laptop and desktop', false)
            ->assertSee('@media (min-width:901px)', false)
            ->assertSee('width:min(1120px,100%); min-height:650px; grid-template-columns:43% 57%', false)
            ->assertSee('.mreg-top { min-height:0; margin:0 0 18px; }', false)
            ->assertSee('Account information')
            ->assertSee('Student information')
            ->assertSee('Personal Gmail')
            ->assertSee('Review')
            ->assertSee('The verification code is sent after you submit your application.', false)
            ->assertSee('id="wizard-next"', false)
            ->assertSee('id="wizard-back"', false)
            ->assertSee('id="submit-registration"', false)
            ->assertSee('id="open-camera"', false)
            ->assertSee('id="id_photo"', false)
            ->assertSee('name="privacy_acknowledgement"', false)
            ->assertDontSee('name="requested_role"', false)
            ->assertDontSee('name="role"', false);

        $html = $response->getContent();
        for ($step = 1; $step <= 5; $step++) {
            $this->assertMatchesRegularExpression('/data-wizard-step="'.$step.'"/', $html);
        }
        $this->assertMatchesRegularExpression('/data-wizard-step="1"[^>]*tabindex="-1"/', $html);
        $this->assertMatchesRegularExpression('/data-wizard-step="2"[^>]*hidden/', $html);
        $this->assertMatchesRegularExpression('/data-wizard-step="3"[^>]*hidden/', $html);
        $this->assertMatchesRegularExpression('/data-wizard-step="4"[^>]*hidden/', $html);
        $this->assertMatchesRegularExpression('/data-wizard-step="5"[^>]*hidden/', $html);

        $summaryStart = strpos($html, 'id="registration-review-summary"');
        $this->assertNotFalse($summaryStart);
        $summary = substr($html, $summaryStart, (strpos($html, '</dl>', $summaryStart) ?: $summaryStart) - $summaryStart);
        $this->assertStringNotContainsString('password', strtolower($summary));
    }

    public function test_registration_validation_returns_to_the_failed_step_and_keeps_safe_input(): void
    {
        $this->registrationReviewer();
        Mail::fake();
        Queue::fake();
        Storage::fake('local');

        $identity = $this->identity('wizard-invalid-gmail');
        $payload = $this->studentPayload($identity['email']);
        $payload['notification_email'] = 'not-gmail@example.com';

        $response = $this->from(route('register'))
            ->followingRedirects()
            ->post(route('register.submit'), $payload);

        $response->assertOk()
            ->assertSee('Step 3 of 5')
            ->assertSee('value="not-gmail@example.com"', false)
            ->assertSee('id="wizard-next"', false);
        $this->assertMatchesRegularExpression('/data-wizard-step="3"[^>]*tabindex="-1"/', $response->getContent());
        $this->assertMatchesRegularExpression('/data-wizard-step="1"[^>]*hidden/', $response->getContent());
        $this->assertDatabaseMissing('users', ['institutional_email' => $identity['email']]);
    }

    public function test_new_application_notifies_only_authorized_reviewer_via_bell(): void
    {
        Mail::fake();
        Queue::fake();
        $reviewer = $this->registrationReviewer();
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $inactiveReviewer = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        config(['services.account_verification.reviewer_user_ids' => [$reviewer->id, $inactiveReviewer->id]]);

        $this->post(route('register.submit'), $this->studentPayload())
            ->assertRedirect(route('account.status'));
        $applicant = User::where('email', 'synthetic.student@minsu.edu.ph')->firstOrFail();
        $this->assertSame('pending', $applicant->approval_status);
        $notice = \App\Models\Notification::where('user_id', $reviewer->id)
            ->where('title', 'New student application')->firstOrFail();
        $this->assertStringNotContainsString($applicant->name, $notice->message);
        $this->assertStringNotContainsString($applicant->notification_email, $notice->message);
        $this->assertSame(route('admin.account-verification.show', $applicant, false), $notice->link);
        $this->assertDatabaseMissing('notifications', ['user_id' => $otherAdmin->id, 'title' => 'New student application']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $inactiveReviewer->id, 'title' => 'New student application']);

        \App\Services\NotificationService::accountApplicationSubmitted($applicant);
        $this->assertSame(1, \App\Models\Notification::where('user_id', $reviewer->id)->where('link', $notice->link)->count());
        $this->actingAs($reviewer)->getJson(route('admin.notifications.get'))
            ->assertOk()->assertJsonFragment(['id' => $notice->id, 'title' => 'New student application']);
        $this->postJson(route('admin.notifications.read', $notice->id))->assertOk();
        $this->assertTrue($notice->fresh()->is_read);
        $this->get($notice->link)->assertOk();
        config(['services.account_verification.reviewer_authorization_approved' => false]);
        \App\Services\NotificationService::accountApplicationSubmitted($applicant);
        $this->assertSame(1, \App\Models\Notification::where('user_id', $reviewer->id)->where('link', $notice->link)->count());
    }

    public function test_student_registration_otp_admin_approval_and_dashboard_access_complete_end_to_end(): void
    {
        Mail::fake();
        Queue::fake();
        Storage::fake('local');

        $student = $this->submitAndVerifySyntheticApplicant('e2e-student');
        $this->actingAs($student)->get(route('student.journal'))->assertRedirect(route('account.status'));

        $admin = $this->reviewerAdmin();
        $this->actingAs($admin)->post(route('admin.account-verification.approve', $student), [
            'institutional_identity_confirmed' => '1',
            'identification_reviewed' => '1',
            'eligibility_confirmed' => '1',
            'internal_review_reason' => 'Synthetic student eligibility reviewed for isolated workflow testing.',
        ])->assertRedirect(route('admin.account-verification.show', $student));

        $student = $student->fresh();
        $this->assertSame('approved', $student->approval_status);
        $this->assertSame('student', $student->role);
        $this->assertSame($admin->id, $student->approval_reviewer_id);
        $this->assertNotNull($student->approval_reviewed_at);
        $this->assertDatabaseHas('account_verification_audits', [
            'application_user_id' => $student->id,
            'actor_user_id' => $admin->id,
            'action' => 'approved',
            'to_status' => 'approved',
        ]);
        $this->assertDatabaseHas('account_verification_email_deliveries', [
            'user_id' => $student->id,
            'kind' => 'approved',
            'recipient_email' => $student->notification_email,
            'status' => 'queued',
        ]);

        $jobs = Queue::pushed(SendAccountVerificationEmail::class);
        $this->assertCount(2, $jobs, 'Only the OTP and the approval notice should be queued.');
        $approvalJob = $jobs->first(function (SendAccountVerificationEmail $job): bool {
            $payload = json_decode(Crypt::decryptString($job->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
            return ($payload['kind'] ?? null) === 'approved';
        });
        $this->assertNotNull($approvalJob);
        $approvalJob->handle();

        $approvalDelivery = AccountVerificationEmailDelivery::query()
            ->where('user_id', $student->id)->where('kind', 'approved')->firstOrFail();
        $this->assertSame('accepted', $approvalDelivery->fresh()->status);
        $this->assertSame('accepted', $student->fresh()->approval_notification_status);
        Mail::assertSent(AccountVerificationMail::class, fn (AccountVerificationMail $mail): bool =>
            $mail->kind === 'approved' && $mail->hasTo($student->notification_email)
        );

        // Repeated approval submissions must not enqueue a duplicate status email.
        $this->actingAs($admin)->post(route('admin.account-verification.approve', $student), [
            'institutional_identity_confirmed' => '1',
            'identification_reviewed' => '1',
            'eligibility_confirmed' => '1',
            'internal_review_reason' => 'Duplicate synthetic review attempt.',
        ])->assertStatus(409);
        $this->assertCount(2, Queue::pushed(SendAccountVerificationEmail::class));

        $this->post(route('logout'));
        $this->post(route('login'), [
            'email' => $student->institutional_email,
            'password' => 'Synthetic-Applicant-Password-42!',
        ])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($student->fresh());
        $this->get(route('student.journal'))->assertOk();
        $this->get(route('student.ai-chat'))
            ->assertOk()
            ->assertSee('AI Companion')
            ->assertSee('id="chatForm"', false);
    }

    public function test_approval_remains_committed_when_notification_transport_is_unavailable(): void
    {
        Queue::fake();
        Mail::fake();
        $emailService = \Mockery::mock(AccountVerificationEmailService::class)->makePartial();
        $emailService->shouldReceive('transportConfigured')->andReturn(false);
        $this->app->instance(AccountVerificationEmailService::class, $emailService);

        $admin = $this->reviewerAdmin();
        $student = $this->pendingApplicant('student', true);
        $student->forceFill(['alternative_review_requested_at' => now()])->save();

        $this->actingAs($admin)->post(route('admin.account-verification.approve', $student), [
            'institutional_identity_confirmed' => '1',
            'identification_reviewed' => '1',
            'eligibility_confirmed' => '1',
            'alternative_review_confirmed' => '1',
            'internal_review_reason' => 'Synthetic alternative eligibility review for transport failure testing.',
        ])->assertRedirect(route('admin.account-verification.show', $student));

        $student->refresh();
        $this->assertSame('approved', $student->approval_status);
        $this->assertSame('student', $student->role);
        $this->assertSame('not_configured', $student->approval_notification_status);
        $this->assertDatabaseHas('account_verification_audits', [
            'application_user_id' => $student->id,
            'actor_user_id' => $admin->id,
            'action' => 'approved',
            'to_status' => 'approved',
        ]);
        $this->assertDatabaseHas('account_verification_email_deliveries', [
            'user_id' => $student->id,
            'kind' => 'approved',
            'status' => 'failed',
            'last_error_code' => 'transport_not_configured',
        ]);
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    public function test_personal_email_verification_is_one_time_expiring_and_attempt_limited(): void
    {
        $user = $this->pendingApplicant('student');
        $code = '483920';
        AccountEmailVerification::query()->create([
            'user_id' => $user->id,
            'code_hash' => hash_hmac('sha256', $user->id . ':' . $code, (string) config('app.key')),
            'expires_at' => now()->addMinutes(30),
            'attempts' => 0,
            'send_count' => 1,
            'last_sent_at' => now(),
        ]);

        $this->actingAs($user)->from(route('account.status'))
            ->post(route('account.email.confirm'), ['code' => '000000'])
            ->assertRedirect(route('account.status'))
            ->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->notification_email_verified_at);

        $this->actingAs($user)->from(route('account.status'))
            ->post(route('account.email.confirm'), ['code' => $code])
            ->assertRedirect(route('account.status'));
        $this->assertNotNull($user->fresh()->notification_email_verified_at);
        $this->assertNull($user->verificationEmail()->value('code_hash'));

        $this->actingAs($user)->from(route('account.status'))
            ->post(route('account.email.confirm'), ['code' => $code])
            ->assertSessionHasErrors('code');
    }

    public function test_expired_code_and_fifth_failed_attempt_cannot_verify_email(): void
    {
        $user = $this->pendingApplicant('student');
        $service = app(AccountVerificationEmailService::class);
        AccountEmailVerification::query()->create([
            'user_id' => $user->id,
            'code_hash' => hash_hmac('sha256', $user->id . ':123456', (string) config('app.key')),
            'expires_at' => now()->subSecond(),
            'attempts' => 0,
        ]);
        $this->assertFalse($service->confirmCode($user, '123456'));

        $verification = $user->verificationEmail()->firstOrFail();
        $verification->forceFill(['expires_at' => now()->addMinutes(5), 'attempts' => 0])->save();
        for ($i = 0; $i < 5; $i++) $this->assertFalse($service->confirmCode($user, '000000'));
        $this->assertSame(5, (int) $verification->fresh()->attempts);
        $this->assertFalse($service->confirmCode($user, '123456'));
        $this->assertNull($user->fresh()->notification_email_verified_at);
    }

    public function test_unconfigured_mail_is_recorded_as_failed_not_as_sent(): void
    {
        $this->app['env'] = 'local';
        config(['mail.default' => 'log']);
        $user = $this->pendingApplicant('student');

        $this->assertFalse(app(AccountVerificationEmailService::class)->issueCode($user));
        $this->assertSame('not_configured', $user->fresh()->verification_email_delivery_status);
        $this->assertDatabaseHas('account_verification_email_deliveries', [
            'user_id' => $user->id,
            'status' => 'failed',
            'last_error_code' => 'transport_not_configured',
        ]);
        $this->assertDatabaseMissing('account_email_verifications', ['user_id' => $user->id]);
    }

    public function test_smtp_readiness_requires_authenticated_tls_and_a_matching_gmail_sender(): void
    {
        $this->app['env'] = 'local';
        $testOnlyPassword = bin2hex(random_bytes(24));
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 465,
            'mail.mailers.smtp.scheme' => 'smtps',
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.from.address' => 'magirem.sender@gmail.com',
        ]);
        $service = app(AccountVerificationEmailService::class);

        $this->assertFalse($service->transportConfigured(), 'Gmail must not be treated as configured without SMTP credentials.');

        config([
            'mail.mailers.smtp.username' => 'magirem.sender@gmail.com',
            'mail.mailers.smtp.password' => $testOnlyPassword,
        ]);
        $this->assertTrue($service->transportConfigured());

        config(['mail.from.address' => 'unverified.alias@gmail.com']);
        $this->assertFalse($service->transportConfigured(), 'Gmail readiness requires the configured sender to match the authenticated account.');

        config([
            'mail.from.address' => 'magirem.sender@gmail.com',
            'mail.mailers.smtp.port' => 25,
            'mail.mailers.smtp.scheme' => 'smtp',
        ]);
        $this->assertFalse($service->transportConfigured(), 'Plain or unsupported SMTP port/scheme combinations must fail closed.');
    }

    public function test_notification_mail_uses_only_the_personal_address_and_excludes_review_and_id_data(): void
    {
        $this->app['env'] = 'local';
        $testOnlyPassword = bin2hex(random_bytes(24));
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 465,
            'mail.mailers.smtp.scheme' => 'smtps',
            'mail.mailers.smtp.username' => 'magirem.sender@gmail.com',
            'mail.mailers.smtp.password' => $testOnlyPassword,
            'mail.from.address' => 'magirem.sender@gmail.com',
        ]);
        Queue::fake();
        Mail::fake();

        $user = $this->pendingApplicant('student');
        $privateReview = 'PRIVATE_REVIEW_NOTE_MUST_NOT_LEAK';
        $privateRequest = 'PRIVATE_INFORMATION_REQUEST_MUST_NOT_LEAK';
        $privateIdPath = 'verification-documents/' . str_repeat('a', 48) . '.webp';
        $user->forceFill([
            'internal_review_reason' => $privateReview,
            'information_request' => $privateRequest,
            'verification_document_path' => $privateIdPath,
        ])->save();

        $service = app(AccountVerificationEmailService::class);
        $this->assertTrue($service->issueCode($user->fresh()));
        $delivery = AccountVerificationEmailDelivery::query()->latest('id')->firstOrFail();
        $this->assertSame($user->notification_email, $delivery->recipient_email);

        $verificationJob = null;
        Queue::assertPushed(SendAccountVerificationEmail::class, function (SendAccountVerificationEmail $job) use (&$verificationJob, $user): bool {
            $verificationJob = $job;
            $payload = json_decode(Crypt::decryptString($job->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
            return $payload['kind'] === 'verification_code'
                && $payload['name'] === $user->name
                && preg_match('/^\d{6}$/', $payload['code'] ?? '') === 1;
        });
        $this->assertNotNull($verificationJob);
        $verificationJob->handle();

        $user->forceFill(['notification_email_verified_at' => now()])->save();
        $this->assertSame('queued', $service->queueStatusNotice($user->fresh(), 'approved'));
        $approvalJob = null;
        Queue::assertPushed(SendAccountVerificationEmail::class, function (SendAccountVerificationEmail $job) use (&$approvalJob): bool {
            $payload = json_decode(Crypt::decryptString($job->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
            if (($payload['kind'] ?? null) !== 'approved') {
                return false;
            }
            $approvalJob = $job;
            return !array_key_exists('internal_review_reason', $payload)
                && !array_key_exists('information_request', $payload)
                && !array_key_exists('verification_document_path', $payload);
        });
        $this->assertNotNull($approvalJob);
        $approvalJob->handle();

        $this->assertSame('queued', $service->queueStatusNotice($user->fresh(), 'needs_information'));
        $informationJob = null;
        Queue::assertPushed(SendAccountVerificationEmail::class, function (SendAccountVerificationEmail $job) use (&$informationJob): bool {
            $payload = json_decode(Crypt::decryptString($job->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
            if (($payload['kind'] ?? null) !== 'needs_information') {
                return false;
            }
            $informationJob = $job;
            return !array_key_exists('internal_review_reason', $payload)
                && !array_key_exists('information_request', $payload)
                && !array_key_exists('verification_document_path', $payload);
        });
        $this->assertNotNull($informationJob);
        $informationJob->handle();

        $this->assertSame('queued', $service->queueStatusNotice($user->fresh(), 'rejected'));
        $statusJob = null;
        Queue::assertPushed(SendAccountVerificationEmail::class, function (SendAccountVerificationEmail $job) use (&$statusJob): bool {
            $payload = json_decode(Crypt::decryptString($job->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
            if (($payload['kind'] ?? null) !== 'rejected') {
                return false;
            }
            $statusJob = $job;
            return !array_key_exists('internal_review_reason', $payload)
                && !array_key_exists('information_request', $payload)
                && !array_key_exists('verification_document_path', $payload);
        });
        $this->assertNotNull($statusJob);
        $statusJob->handle();

        Mail::assertSentCount(4);
        $sentMails = Mail::sent(AccountVerificationMail::class);
        $this->assertCount(4, $sentMails, 'Expected the verification and three account-status mailables.');
        $codeMail = $sentMails->first(fn (AccountVerificationMail $mail): bool => $mail->kind === 'verification_code');
        $approvalMail = $sentMails->first(fn (AccountVerificationMail $mail): bool => $mail->kind === 'approved');
        $informationMail = $sentMails->first(fn (AccountVerificationMail $mail): bool => $mail->kind === 'needs_information');
        $rejectionMail = $sentMails->first(fn (AccountVerificationMail $mail): bool => $mail->kind === 'rejected');
        $this->assertNotNull($codeMail, 'The one-time code mailable must be sent.');
        $this->assertNotNull($approvalMail, 'The approval-status mailable must be sent.');
        $this->assertNotNull($informationMail, 'The additional-information mailable must be sent.');
        $this->assertNotNull($rejectionMail, 'The rejection-status mailable must be sent.');
        $this->assertTrue($codeMail->hasTo($user->notification_email), 'The code must go to the personal notification email.');
        $this->assertFalse($codeMail->hasTo($user->institutional_email), 'The code must not go to the institutional account address.');
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $codeMail->code);
        $this->assertTrue($approvalMail->hasTo($user->notification_email), 'The approval notice must go to the personal notification email.');
        $this->assertFalse($approvalMail->hasTo($user->institutional_email), 'The approval notice must not go to the institutional account address.');
        $this->assertTrue($informationMail->hasTo($user->notification_email), 'The additional-information notice must go to the personal notification email.');
        $this->assertFalse($informationMail->hasTo($user->institutional_email), 'The additional-information notice must not go to the institutional account address.');
        $this->assertTrue($rejectionMail->hasTo($user->notification_email), 'The status email must go to the personal notification email.');
        $this->assertFalse($rejectionMail->hasTo($user->institutional_email), 'The status email must not go to the institutional account address.');
        $approvalBody = $approvalMail->render();
        $informationBody = $informationMail->render();
        $rejectionBody = $rejectionMail->render();
        foreach ([$approvalMail, $informationMail, $rejectionMail] as $statusMail) {
            $this->assertSame([], $statusMail->attachments, 'Account status emails must not attach student ID images.');
        }
        $this->assertStringNotContainsString($privateReview, $approvalBody);
        $this->assertStringNotContainsString($privateRequest, $approvalBody);
        $this->assertStringNotContainsString($privateIdPath, $approvalBody);
        $this->assertStringNotContainsString($privateReview, $informationBody);
        $this->assertStringNotContainsString($privateRequest, $informationBody);
        $this->assertStringNotContainsString($privateIdPath, $informationBody);
        $this->assertStringNotContainsString($privateReview, $rejectionBody);
        $this->assertStringNotContainsString($privateRequest, $rejectionBody);
        $this->assertStringNotContainsString($privateIdPath, $rejectionBody);
    }

    public function test_database_queue_worker_processes_verification_mail_using_isolated_test_database_and_mail_fake(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'array']);
        Mail::fake();

        $user = $this->pendingApplicant('student');
        $delivery = AccountVerificationEmailDelivery::query()->create([
            'user_id' => $user->id,
            'kind' => 'approved',
            'recipient_email' => $user->notification_email,
            'status' => 'queued',
            'queued_at' => now(),
        ]);
        $payload = Crypt::encryptString(json_encode(['kind' => 'approved', 'name' => $user->name], JSON_THROW_ON_ERROR));
        Queue::connection('database')->pushOn('default', new SendAccountVerificationEmail($delivery->id, $payload));

        $this->assertDatabaseCount('jobs', 1);
        $exitCode = Artisan::call('queue:work', [
            'connection' => 'database',
            '--queue' => 'default',
            '--once' => true,
            '--sleep' => 0,
            '--tries' => 1,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseHas('account_verification_email_deliveries', [
            'id' => $delivery->id,
            'status' => 'accepted',
            'attempts' => 1,
        ]);
        Mail::assertSent(AccountVerificationMail::class, fn (AccountVerificationMail $mail): bool => $mail->kind === 'approved' && $mail->hasTo($user->notification_email));
    }

    public function test_delivery_test_mailable_is_explicitly_harmless_and_has_no_attachments(): void
    {
        $mail = new AccountVerificationMail('delivery_test', 'MAGIREM AI test');
        $body = $mail->render();

        $this->assertStringContainsString('one-time test of the MAGIREM AI notification service', $body);
        $this->assertStringContainsString('No account was created, and no account status changed.', $body);
        $this->assertSame([], $mail->attachments);
    }

    public function test_notification_code_resend_route_is_rate_limited(): void
    {
        Queue::fake();
        $user = $this->pendingApplicant('student');

        $this->actingAs($user)->post(route('account.email.resend'))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('account_verification_email_deliveries', 1);

        $firstSentAt = (string) $user->verificationEmail()->value('last_sent_at');
        $this->actingAs($user)->post(route('account.email.resend'))
            ->assertRedirect()->assertSessionHas('warning', 'Please wait 60 seconds before requesting another code.');
        $this->assertDatabaseCount('account_verification_email_deliveries', 1);
        $this->assertSame($firstSentAt, (string) $user->verificationEmail()->value('last_sent_at'));

        $this->travel(61)->seconds();
        $this->actingAs($user)->post(route('account.email.resend'))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('account_verification_email_deliveries', 2);

        $this->actingAs($user)->post(route('account.email.resend'))->assertTooManyRequests();
    }

    public function test_failed_mail_job_records_neutral_failure_and_does_not_leak_payload(): void
    {
        $user = $this->pendingApplicant('student');
        $delivery = AccountVerificationEmailDelivery::query()->create([
            'user_id' => $user->id,
            'kind' => 'verification_code',
            'recipient_email' => $user->notification_email,
            'status' => 'queued',
            'queued_at' => now(),
        ]);
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('send')->once()->andThrow(new RuntimeException('private SMTP detail must not be persisted'));
        Mail::shouldReceive('to')->once()->with($user->notification_email)->andReturn($mailer);
        $payload = Crypt::encryptString(json_encode(['kind'=>'verification_code','name'=>$user->name,'code'=>'918273'], JSON_THROW_ON_ERROR));
        $job = new SendAccountVerificationEmail($delivery->id, $payload);

        try { $job->handle(); } catch (RuntimeException) {}
        $this->assertDatabaseHas('account_verification_email_deliveries', ['id'=>$delivery->id,'status'=>'retrying','last_error_code'=>'transport_error']);
        $job->failed(new RuntimeException('provider private error'));
        $this->assertDatabaseHas('account_verification_email_deliveries', ['id'=>$delivery->id,'status'=>'failed','last_error_code'=>'transport_error']);
        $this->assertDatabaseMissing('account_verification_email_deliveries', ['id'=>$delivery->id,'last_error_code'=>'private SMTP detail must not be persisted']);
    }

    public function test_account_review_requires_an_explicitly_allowlisted_admin(): void
    {
        $applicant = $this->pendingApplicant('student', true);
        $student = User::factory()->create(['role'=>'student','is_active'=>true]);
        $this->actingAs($student)->get(route('admin.account-verification.index'))->assertForbidden();

        $admin = User::factory()->create(['role'=>'admin','is_active'=>true]);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.account-verification.index'), false);
        $this->actingAs($admin)->get(route('admin.account-verification.index'))
            ->assertForbidden();

        $reviewer = $this->reviewerAdmin();
        $this->actingAs($reviewer)->get(route('admin.account-verification.index'))
            ->assertOk()->assertSee('Account verification')->assertSee($applicant->institutional_email);
        $this->get(route('admin.account-verification.show',$applicant))->assertOk()->assertSee('Review application');
    }

    public function test_confirmed_reviewer_id_229_can_view_ids_and_approve_or_reject_in_isolated_database(): void
    {
        Mail::fake();
        Queue::fake();
        Storage::fake('local');
        config([
            'services.account_verification.reviewer_user_ids' => [229],
            'services.account_verification.reviewer_authorization_approved' => true,
        ]);

        $reviewer = User::factory()->make(['role' => 'admin', 'is_active' => true]);
        $reviewer->forceFill(['id' => 229, 'approval_status' => 'approved'])->save();
        $this->assertTrue($reviewer->fresh()->canReviewAccountVerification());

        $approved = $this->pendingApplicant('student', true);
        $approvedPath = app(VerificationDocumentStorage::class)->store(UploadedFile::fake()->image('synthetic-id-approve.png', 420, 240));
        $approved->forceFill(['verification_document_path' => $approvedPath])->save();

        $this->actingAs($reviewer)->get(route('admin.account-verification.index'))
            ->assertOk()->assertSee('Account verification');
        $this->get(route('admin.account-verification.document', $approved))
            ->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->post(route('admin.account-verification.approve', $approved), [
            'institutional_identity_confirmed' => '1',
            'identification_reviewed' => '1',
            'eligibility_confirmed' => '1',
            'internal_review_reason' => 'Synthetic isolated reviewer authorization test.',
        ])->assertRedirect(route('admin.account-verification.show', $approved));
        $this->assertDatabaseHas('account_verification_audits', [
            'application_user_id' => $approved->id,
            'actor_user_id' => 229,
            'action' => 'approved',
            'to_status' => 'approved',
        ]);

        $rejected = $this->pendingApplicant('student', true);
        $rejectedPath = app(VerificationDocumentStorage::class)->store(UploadedFile::fake()->image('synthetic-id-reject.png', 420, 240));
        $rejected->forceFill(['verification_document_path' => $rejectedPath])->save();
        $this->post(route('admin.account-verification.reject', $rejected), [
            'internal_review_reason' => 'Synthetic isolated rejection authorization test.',
        ])->assertRedirect(route('admin.account-verification.show', $rejected));
        $this->assertDatabaseHas('account_verification_audits', [
            'application_user_id' => $rejected->id,
            'actor_user_id' => 229,
            'action' => 'rejected',
            'to_status' => 'rejected',
        ]);
    }

    public function test_allowlisted_but_inactive_or_unapproved_admin_cannot_review_applications(): void
    {
        $applicant = $this->pendingApplicant('student');
        $reviewer = $this->reviewerAdmin();

        $reviewer->forceFill(['is_active' => false])->save();
        $this->actingAs($reviewer->fresh())->get(route('admin.account-verification.index'))->assertRedirect(route('login'));

        $reviewer->forceFill(['is_active' => true, 'approval_status' => 'pending'])->save();
        $this->actingAs($reviewer->fresh())->get(route('admin.account-verification.index'))->assertForbidden();
        $this->assertDatabaseMissing('account_verification_audits', [
            'application_user_id' => $applicant->id,
            'action' => 'document_accessed',
        ]);
    }

    public function test_students_counselors_pending_applicants_and_unlisted_admins_cannot_review_or_read_private_ids(): void
    {
        Storage::fake('local');
        $applicant = $this->pendingApplicant('student');
        $path = app(VerificationDocumentStorage::class)->store(UploadedFile::fake()->image('private-id.png', 420, 240));
        $applicant->forceFill(['verification_document_path' => $path])->save();
        $counselor = User::factory()->create(['role' => 'counselor', 'is_active' => true]);
        $ordinaryAdmin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->get(route('admin.account-verification.index'))->assertRedirect(route('login'));
        $actors = [$applicant, User::factory()->create(['role' => 'student', 'is_active' => true]), $counselor, $ordinaryAdmin];
        foreach ($actors as $actor) {
            $this->actingAs($actor)->get(route('admin.account-verification.index'))->assertForbidden();
            $this->get(route('admin.account-verification.show', $applicant))->assertForbidden();
            $this->get(route('admin.account-verification.document', $applicant))->assertForbidden();
            $this->post(route('admin.account-verification.approve', $applicant), [])->assertForbidden();
            $this->post(route('admin.account-verification.reject', $applicant), [])->assertForbidden();
            $this->post(route('admin.account-verification.request-information', $applicant), [])->assertForbidden();
            $this->post(route('admin.account-verification.suspend', $applicant), [])->assertForbidden();
        }

        $this->assertDatabaseMissing('account_verification_audits', [
            'application_user_id' => $applicant->id,
            'action' => 'document_accessed',
        ]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_admin_verification_menu_and_each_status_filter_render(): void
    {
        $states = [
            'pending' => 'Pending status applicant',
            'approved' => 'Approved status applicant',
            'rejected' => 'Rejected status applicant',
            'needs_information' => 'Needs information applicant',
            'suspended' => 'Suspended status applicant',
        ];

        foreach ($states as $status => $name) {
            $applicant = $this->pendingApplicant('student');
            $applicant->forceFill(['name' => $name, 'approval_status' => $status])->save();
        }

        $admin = $this->reviewerAdmin();
        $ordinaryAdmin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($ordinaryAdmin)->get(route('admin.dashboard'))
            ->assertOk()->assertDontSee(route('admin.account-verification.index'), false);
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Account verification')
            ->assertSee(route('admin.account-verification.index'), false);

        $this->actingAs($admin)
            ->get(route('admin.account-verification.index'))
            ->assertOk()
            ->assertSee('Account verification')
            ->assertSee(route('admin.account-verification.index'), false);

        foreach ($states as $status => $name) {
            $this->get(route('admin.account-verification.index', ['status' => $status]))
                ->assertOk()
                ->assertSee('Account verification')
                ->assertSee($name);
        }
    }

    public function test_private_id_preview_requires_allowlisted_reviewer_and_access_is_audited(): void
    {
        Storage::fake('local');
        $applicant = $this->pendingApplicant('student');
        $path = app(VerificationDocumentStorage::class)->store(UploadedFile::fake()->image('id-card.png', 420, 240));
        $applicant->forceFill(['verification_document_path'=>$path,'verification_document_submitted_at'=>now()])->save();

        $this->actingAs($applicant)->get(route('admin.account-verification.document',$applicant))->assertForbidden();
        $admin = $this->reviewerAdmin();
        $response = $this->actingAs($admin)->get(route('admin.account-verification.document',$applicant));
        $response->assertOk()->assertHeader('Content-Type','image/webp');
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertDatabaseHas('account_verification_audits', ['application_user_id'=>$applicant->id,'actor_user_id'=>$admin->id,'action'=>'document_accessed']);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_student_approval_is_audited_and_counselor_approval_requires_institutional_authorization(): void
    {
        Mail::fake();
        Queue::fake();
        $admin = $this->reviewerAdmin();
        $student = $this->pendingApplicant('student', true);
        $student->forceFill(['alternative_review_requested_at'=>now()])->save();

        $this->actingAs($admin)->post(route('admin.account-verification.approve',$student), [
            'identification_reviewed'=>'1', 'eligibility_confirmed'=>'1', 'alternative_review_confirmed'=>'1', 'internal_review_reason'=>'Synthetic review without identity confirmation.',
        ])->assertSessionHasErrors('institutional_identity_confirmed');
        $this->assertSame('pending',$student->fresh()->approval_status);
        $this->assertNull($student->fresh()->institutional_verified_at);

        $this->actingAs($admin)->post(route('admin.account-verification.approve',$student), [
            'institutional_identity_confirmed'=>'1',
            'identification_reviewed'=>'1',
            'eligibility_confirmed'=>'1', 'alternative_review_confirmed'=>'1', 'internal_review_reason'=>'Verified using approved student records.',
        ])->assertRedirect(route('admin.account-verification.show',$student));

        $this->assertSame('approved',$student->fresh()->approval_status);
        $this->assertSame('student',$student->fresh()->role);
        $this->assertTrue($student->fresh()->is_active);
        $this->assertNotNull($student->fresh()->institutional_verified_at);
        $this->assertDatabaseHas('account_verification_audits',['application_user_id'=>$student->id,'actor_user_id'=>$admin->id,'action'=>'approved','to_status'=>'approved']);

        $counselor = $this->pendingApplicant('counselor', true);
        $counselor->forceFill(['alternative_review_requested_at'=>now()])->save();
        $this->post(route('admin.account-verification.approve',$counselor), [
            'institutional_identity_confirmed'=>'1',
            'identification_reviewed'=>'1',
            'eligibility_confirmed'=>'1', 'alternative_review_confirmed'=>'1', 'internal_review_reason'=>'Staff role reviewed through the official office.',
        ])->assertSessionHasErrors('counselor_authorized');
        $this->assertSame('pending',$counselor->fresh()->approval_status);

        $this->post(route('admin.account-verification.approve',$counselor), [
            'institutional_identity_confirmed'=>'1',
            'identification_reviewed'=>'1',
            'eligibility_confirmed'=>'1', 'alternative_review_confirmed'=>'1', 'counselor_authorized'=>'1', 'internal_review_reason'=>'Staff role reviewed through the official office.',
        ])->assertRedirect(route('admin.account-verification.show',$counselor));
        $this->assertSame('approved',$counselor->fresh()->approval_status);
        $this->assertStringContainsString('Counselor role authorization confirmed', $counselor->fresh()->internal_review_reason);
    }

    public function test_request_information_rejection_and_suspension_keep_status_audit(): void
    {
        Mail::fake();
        Queue::fake();
        $admin = $this->reviewerAdmin();
        $applicant = $this->pendingApplicant('student', true);

        $this->actingAs($admin)->post(route('admin.account-verification.request-information',$applicant), [
            'information_request'=>'Please upload a clearer image of your student identification.',
        ])->assertRedirect(route('admin.account-verification.show',$applicant));
        $this->assertSame('needs_information',$applicant->fresh()->approval_status);
        $this->actingAs($applicant->fresh())->get(route('account.status'))->assertOk()->assertSee('Please upload a clearer image');
        $this->assertCount(1, Queue::pushed(SendAccountVerificationEmail::class));

        $this->actingAs($admin)->post(route('admin.account-verification.reject',$applicant), [
            'internal_review_reason'=>'The supplied eligibility evidence was not sufficient.',
        ])->assertRedirect(route('admin.account-verification.show',$applicant));
        $this->assertSame('rejected',$applicant->fresh()->approval_status);
        $this->actingAs($applicant)->get(route('account.status'))->assertOk()->assertDontSee('The supplied eligibility evidence was not sufficient.');
        $this->actingAs($applicant)->get(route('student.journal'))->assertRedirect(route('account.status'));
        $this->assertCount(2, Queue::pushed(SendAccountVerificationEmail::class));

        $approved = $this->pendingApplicant('student', true);
        $approved->forceFill(['approval_status'=>'approved','role'=>'student','notification_email_verified_at'=>now()])->save();
        $this->actingAs($admin)->post(route('admin.account-verification.suspend',$approved), [
            'internal_review_reason'=>'Temporary suspension pending an institutional account review.',
        ])->assertRedirect(route('admin.account-verification.show',$approved));
        $this->assertSame('suspended',$approved->fresh()->approval_status);
        $this->assertFalse($approved->fresh()->is_active);
        $this->assertCount(3, Queue::pushed(SendAccountVerificationEmail::class));
        foreach (Queue::pushed(SendAccountVerificationEmail::class) as $job) {
            $job->handle();
        }
        Mail::assertSent(AccountVerificationMail::class, 3);
        $this->assertDatabaseHas('account_verification_email_deliveries', [
            'user_id' => $applicant->id,
            'kind' => 'needs_information',
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('account_verification_email_deliveries', [
            'user_id' => $applicant->id,
            'kind' => 'rejected',
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('account_verification_email_deliveries', [
            'user_id' => $approved->id,
            'kind' => 'suspended',
            'status' => 'accepted',
        ]);
        $this->actingAs($approved->fresh())->get(route('student.journal'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_only_admin_role_can_be_granted_by_review_and_existing_admin_remains_approved(): void
    {
        $admin = $this->reviewerAdmin();
        $this->assertTrue($admin->isApproved());
        $identity = $this->identity('role-escalation');
        $payload = $this->studentPayload();
        $payload['requested_role'] = 'admin';

        $payload['institutional_email'] = $identity['email'];
        $this->from(route('register'))
            ->post(route('register.submit'),$payload)
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('requested_role');
        $this->assertDatabaseCount('users',1);
        $this->assertDatabaseHas('users',['id'=>$admin->id,'role'=>'admin','is_active'=>true]);
    }

    public function test_document_route_rejects_unexpected_stored_paths(): void
    {
        $admin = $this->reviewerAdmin();
        $applicant = $this->pendingApplicant('student');
        $applicant->forceFill(['verification_document_path'=>'../public/storage/secret.webp'])->save();
        $this->actingAs($admin)->get(route('admin.account-verification.document',$applicant))->assertNotFound();
    }

    private function reviewerAdmin(): User
    {
        if ($this->designatedReviewer && $this->designatedReviewer->exists) {
            return $this->designatedReviewer;
        }

        $reviewer = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reviewerIds = array_map('intval', (array) config('services.account_verification.reviewer_user_ids', []));
        $reviewerIds[] = (int) $reviewer->id;
        config([
            'services.account_verification.reviewer_user_ids' => array_values(array_unique($reviewerIds)),
            'services.account_verification.reviewer_authorization_approved' => true,
        ]);
        $this->designatedReviewer = $reviewer;

        return $reviewer;
    }

    private function registrationReviewer(): User
    {
        return $this->reviewerAdmin();
    }

    private function identity(string $suffix): array
    {
        return [
            'email'=>strtolower($suffix).'@minsu.edu.ph',
            'name'=>'Synthetic '.ucfirst($suffix),
        ];
    }

    private function studentPayload(string $email = 'synthetic.student@minsu.edu.ph'): array
    {
        return [
            'name'=>'Synthetic Applicant',
            'institutional_email'=>$email,
            'password'=>'Synthetic-Applicant-Password-42!',
            'password_confirmation'=>'Synthetic-Applicant-Password-42!',
            'campus'=>'Bongabong Campus',
            'student_id'=>'TEST-STUDENT-001',
            'program'=>'BS Psychology',
            'year_level'=>'2nd Year',
            'notification_email'=>'student.one@gmail.com',
            'alternative_review'=>'1',
            'privacy_acknowledgement'=>'1',
        ];
    }

    private function pendingApplicant(string $role, bool $emailVerified = false): User
    {
        $user = User::factory()->create([
            'role'=>$role,
            'is_active'=>true,
            'email'=>uniqid('applicant-', true).'@minsu.edu.ph',
        ]);
        $user->forceFill([
            'requested_role'=>$role,
            'approval_status'=>'pending',
            'approval_submitted_at'=>now(),
            'institutional_email'=>$user->email,
            'institutional_verified_at'=>null,
            'email_verified_at'=>null,
            'microsoft_tenant_id'=>null,
            'microsoft_object_id'=>null,
            'campus'=>'Calapan Campus',
            'notification_email'=>'applicant.'.$user->id.'@gmail.com',
            'notification_email_verified_at'=>$emailVerified ? now() : null,
            'privacy_consent_version'=>'minsu-verification-test-v1',
            'privacy_consent_at'=>now(),
            'student_id'=>$role==='student' ? 'STU-'.$user->id : null,
            'program'=>$role==='student' ? 'BS Psychology' : null,
            'year_level'=>$role==='student' ? '1st Year' : null,
            'staff_position'=>$role==='counselor' ? 'Guidance Counselor' : null,
        ])->save();
        return $user->fresh();
    }

    private function submitAndVerifySyntheticApplicant(string $identitySuffix): User
    {
        $this->registrationReviewer();
        $identity = $this->identity($identitySuffix);
        $payload = $this->studentPayload($identity['email']);
        unset($payload['alternative_review']);
        $payload['id_photo'] = UploadedFile::fake()->image('synthetic-school-id.png', 640, 360);

        $response = $this->post(route('register.submit'), $payload);
        $response->assertRedirect(route('account.status'));

        $applicant = User::query()->where('email', $identity['email'])->firstOrFail();
        $this->post(route('logout'));
        $duplicatePayload = $payload;
        $duplicatePayload['student_id'] = 'TEST-DUPLICATE-' . $identitySuffix;
        $this->from(route('register'))
            ->post(route('register.submit'), $duplicatePayload)
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('institutional_email');
        $this->assertSame(1, User::query()->where('email', $identity['email'])->count());
        $this->actingAs($applicant);

        $this->assertSame('student', $applicant->requested_role);
        $this->assertSame('pending', $applicant->approval_status);
        $this->assertNotNull($applicant->verification_document_path);
        $this->assertNull($applicant->notification_email_verified_at);
        $this->assertDatabaseHas('account_verification_email_deliveries', [
            'user_id' => $applicant->id,
            'kind' => 'verification_code',
            'recipient_email' => $applicant->notification_email,
            'status' => 'queued',
        ]);

        $verificationJob = Queue::pushed(SendAccountVerificationEmail::class)->first(function (SendAccountVerificationEmail $job): bool {
            $payload = json_decode(Crypt::decryptString($job->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
            return ($payload['kind'] ?? null) === 'verification_code';
        });
        $this->assertNotNull($verificationJob);
        $emailPayload = json_decode(Crypt::decryptString($verificationJob->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
        $code = $emailPayload['code'] ?? null;
        $this->assertIsString($code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertStringNotContainsString($code, $response->getContent());
        $this->assertArrayNotHasKey('code', $applicant->getAttributes());
        $this->assertArrayNotHasKey('code', $applicant->verificationEmail->getAttributes());
        $this->assertNotSame($code, $applicant->verificationEmail->code_hash);

        $verificationJob->handle();
        $verificationDelivery = AccountVerificationEmailDelivery::query()
            ->where('user_id', $applicant->id)->where('kind', 'verification_code')->firstOrFail();
        $this->assertSame('accepted', $verificationDelivery->fresh()->status);
        Mail::assertSent(AccountVerificationMail::class, fn (AccountVerificationMail $mail): bool =>
            $mail->kind === 'verification_code'
                && $mail->hasTo($applicant->notification_email)
                && !$mail->hasTo($applicant->institutional_email)
                && $mail->code === $code
        );

        $this->actingAs($applicant)->from(route('account.status'))
            ->post(route('account.email.confirm'), ['code' => $code])
            ->assertRedirect(route('account.status'));
        $this->assertNotNull($applicant->fresh()->notification_email_verified_at);
        $this->assertNull($applicant->fresh()->institutional_verified_at, 'Personal Gmail OTP must not verify institutional identity.');
        $this->assertSame('pending', $applicant->fresh()->approval_status);
        $this->assertDatabaseHas('account_verification_audits', [
            'application_user_id' => $applicant->id,
            'action' => 'notification_email_verified',
        ]);

        $this->actingAs($applicant->fresh())->get(route('account.status'))->assertOk();
        return $applicant->fresh();
    }
}
