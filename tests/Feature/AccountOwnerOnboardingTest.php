<?php

namespace Tests\Feature;

use App\Jobs\SendAccountVerificationEmail;
use App\Mail\AccountVerificationMail;
use App\Models\AccountInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AccountOwnerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
        config([
            'services.account_verification.reviewer_user_ids' => [],
            'services.account_verification.reviewer_authorization_approved' => false,
            'services.account_verification.invitation_ttl_hours' => 24,
        ]);
        $this->reviewer = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
        config([
            'services.account_verification.reviewer_user_ids' => [$this->reviewer->id],
            'services.account_verification.reviewer_authorization_approved' => true,
        ]);
    }

    public function test_admin_created_student_sets_own_password_and_verifies_gmail_without_reentering_reviewer_queue(): void
    {
        $response = $this->actingAs($this->reviewer)->post(route('admin.store-user'), [
            'name' => 'Synthetic Onboarding Student',
            'email' => 'onboarding.student@minsu.edu.ph',
            'role' => 'student',
            'student_id' => 'ONBOARD-TEST-01',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'approve_verified' => '1',
            'invitation_method' => 'in_person',
            'institutional_identity_confirmed' => '1',
            'eligibility_confirmed' => '1',
            'verification_basis' => 'Confirmed against an isolated synthetic enrollment fixture.',
        ])->assertOk()->assertSee('One-time in-person handoff');

        preg_match('/id="handoff-token">([a-f0-9]{64})<\/p>/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'The one-time manual handoff must be shown to the authorized reviewer.');
        $token = $matches[1];
        $owner = User::query()->where('email', 'onboarding.student@minsu.edu.ph')->firstOrFail();

        $this->assertSame('approved', $owner->approval_status);
        $this->assertTrue($owner->is_active);
        $this->assertTrue($owner->requiresOwnerOnboarding());
        $this->assertNull($owner->password_setup_completed_at);
        $this->assertNull($owner->notification_email);
        $this->assertNull($owner->notification_email_verified_at);
        $this->assertSame(1, AccountInvitation::query()->where('user_id', $owner->id)->count());
        $invitation = AccountInvitation::query()->where('user_id', $owner->id)->firstOrFail();
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame('in_person', $invitation->delivery_method);
        Queue::assertNothingPushed(); // No OTP or email is sent for an in-person handoff.

        $this->post(route('logout'));
        $this->get(route('account.activate'))->assertOk()
            ->assertSee('assets/branding/magirem-logo-full.png')
            ->assertSee('width:min(500px,100%)', false)
            ->assertSee('prefers-reduced-motion', false)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->from(route('account.activate'))->post(route('account.activate.accept'), [
            'token' => $token,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertRedirect(route('account.activate') . '#invitation=' . $token)
            ->assertSessionHasErrors('password');
        $this->assertNull(session('_old_input.token'), 'Bearer invitation tokens must not be flashed into server-side old input.');
        $this->from(route('account.activate'))->post(route('account.activate.accept'), [
            'token' => $token,
            'password' => 'Owner-Setup-2026!Strong',
            'password_confirmation' => 'Owner-Setup-2026!Strong',
        ])->assertRedirect(route('account.status'));

        $owner->refresh();
        $this->assertAuthenticatedAs($owner);
        $this->assertTrue(Hash::check('Owner-Setup-2026!Strong', $owner->password));
        $this->assertNotNull($owner->password_setup_completed_at);
        $this->assertTrue($owner->requiresOwnerOnboarding());
        $this->assertSame('approved', $owner->approval_status);
        $this->get(route('account.status'))->assertOk()->assertSee('Finish setting up your account')
            ->assertSee('Owner setup checklist')->assertSee('Add your personal Gmail')
            ->assertSee('authorized internal setup')->assertSee('no public application review is needed again');
        $this->get(route('student.journal'))->assertRedirect(route('account.status'));

        $this->from(route('account.status'))->post(route('account.onboarding.email'), [
            'notification_email' => 'owner.personal@gmail.com',
        ])->assertRedirect(route('account.status'));
        $owner->refresh();
        $this->assertSame('owner.personal@gmail.com', $owner->notification_email);
        $this->assertNull($owner->notification_email_verified_at);
        $verificationJob = Queue::pushed(SendAccountVerificationEmail::class)
            ->first(function (SendAccountVerificationEmail $job): bool {
                $payload = json_decode(Crypt::decryptString($job->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
                return ($payload['kind'] ?? null) === 'verification_code';
            });
        $this->assertNotNull($verificationJob, 'A verification code should be queued only after password setup.');
        $verificationPayload = json_decode(Crypt::decryptString($verificationJob->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);

        $this->from(route('account.status'))->post(route('account.email.confirm'), [
            'code' => $verificationPayload['code'],
        ])->assertRedirect(route('home'));

        $owner->refresh();
        $this->assertNotNull($owner->notification_email_verified_at);
        $this->assertFalse($owner->requiresOwnerOnboarding());
        $this->assertSame('approved', $owner->approval_status);
        $this->assertTrue($owner->is_active);
        $this->get(route('student.journal'))->assertOk();

        $this->post(route('logout'));
        $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $owner->institutional_email,
        ])->assertRedirect(route('password.request'))->assertSessionHas('status');
        $resetJob = Queue::pushed(SendAccountVerificationEmail::class)->first(function (SendAccountVerificationEmail $job): bool {
            $payload = json_decode(Crypt::decryptString($job->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
            return ($payload['kind'] ?? null) === 'password_reset';
        });
        $this->assertNotNull($resetJob, 'Verified personal Gmail must allow the existing recovery flow.');
        $this->assertDatabaseHas('account_verification_email_deliveries', [
            'user_id' => $owner->id,
            'kind' => 'password_reset',
            'recipient_email' => 'owner.personal@gmail.com',
        ]);
        $this->assertSame('approved', $owner->fresh()->approval_status);
        $this->assertTrue($owner->fresh()->is_active);

        $this->from(route('account.activate'))->post(route('account.activate.accept'), [
            'token' => $token,
            'password' => 'Another-Password-2026!No',
            'password_confirmation' => 'Another-Password-2026!No',
        ])->assertRedirect(route('account.activate'))
            ->assertSessionHasErrors('invitation');
        $this->assertTrue(Hash::check('Owner-Setup-2026!Strong', $owner->fresh()->password));
    }

    public function test_reviewer_approval_emails_only_a_one_time_setup_invitation_to_confirmed_gmail(): void
    {
        $this->actingAs($this->reviewer)->post(route('admin.store-user'), [
            'name' => 'Synthetic Email Invitation Student',
            'email' => 'email.invitation@minsu.edu.ph',
            'role' => 'student',
            'student_id' => 'ONBOARD-TEST-02',
            'program' => 'BSIT',
            'year_level' => '1st Year',
            'notification_email' => 'confirmed.owner@gmail.com',
            'notification_email_contact_confirmed' => '1',
        ])->assertRedirect(route('admin.users'));

        $owner = User::query()->where('email', 'email.invitation@minsu.edu.ph')->firstOrFail();
        $this->assertNotNull($owner->notification_email_confirmed_at);
        $this->assertSame($this->reviewer->id, $owner->notification_email_confirmed_by);
        $this->assertNull($owner->notification_email_verified_at);
        Queue::assertNothingPushed(); // Merely recording the reviewer-confirmed contact cannot send an invitation.

        $this->actingAs($this->reviewer)->post(route('admin.account-verification.approve', $owner), [
            'verification_basis' => 'Confirmed against an isolated synthetic enrollment fixture.',
            'internal_review_reason' => 'Synthetic identity and eligibility independently confirmed.',
            'institutional_identity_confirmed' => '1',
            'eligibility_confirmed' => '1',
            'invitation_method' => 'email',
            'notification_email' => 'confirmed.owner@gmail.com',
            'notification_email_contact_confirmed' => '1',
        ])->assertRedirect(route('admin.account-verification.show', $owner))
            ->assertSessionHas('success');

        $owner->refresh();
        $this->assertSame('approved', $owner->approval_status);
        $this->assertTrue($owner->is_active);
        $this->assertTrue($owner->requiresOwnerOnboarding());
        $this->assertNull($owner->password_setup_completed_at);
        $this->assertNull($owner->notification_email_verified_at);
        $this->assertDatabaseHas('account_verification_email_deliveries', [
            'user_id' => $owner->id,
            'kind' => 'account_invitation',
            'recipient_email' => 'confirmed.owner@gmail.com',
            'status' => 'queued',
        ]);
        $this->assertDatabaseMissing('account_verification_email_deliveries', [
            'user_id' => $owner->id,
            'kind' => 'approved',
        ]);
        $invitationJob = Queue::pushed(SendAccountVerificationEmail::class)->first(function (SendAccountVerificationEmail $job): bool {
            $payload = json_decode(Crypt::decryptString($job->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
            return ($payload['kind'] ?? null) === 'account_invitation'
                && ($payload['invitation_token'] ?? null) !== ''
                && isset($payload['invitation_id']);
        });
        $this->assertNotNull($invitationJob);
        $invitationPayload = json_decode(Crypt::decryptString($invitationJob->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
        $invitationToken = $invitationPayload['invitation_token'];
        $invitationJob->handle();
        Mail::assertSent(AccountVerificationMail::class, fn (AccountVerificationMail $mail): bool =>
            $mail->kind === 'account_invitation' && $mail->hasTo('confirmed.owner@gmail.com')
        );
        Mail::assertNotSent(AccountVerificationMail::class, fn (AccountVerificationMail $mail): bool =>
            $mail->kind === 'account_invitation' && $mail->hasTo('email.invitation@minsu.edu.ph')
        );
        $this->assertDatabaseHas('account_verification_email_deliveries', [
            'user_id' => $owner->id,
            'kind' => 'account_invitation',
            'status' => 'accepted',
            'recipient_email' => 'confirmed.owner@gmail.com',
        ]);
        $invitationMail = Mail::sent(AccountVerificationMail::class)->first(fn (AccountVerificationMail $mail): bool => $mail->kind === 'account_invitation');
        $this->assertNotNull($invitationMail);
        $this->assertStringContainsString('#invitation=', (string) $invitationMail->inviteUrl);
        $this->assertStringNotContainsString('?invitation=', (string) $invitationMail->inviteUrl);
        $this->assertStringNotContainsString($owner->institutional_email, $invitationMail->render());
        $this->assertStringNotContainsString('school ID', $invitationMail->render());
        $this->get(route('admin.account-verification.show', $owner))->assertOk()
            ->assertSee('Owner setup invitations')
            ->assertSee('confirmed.owner@gmail.com')
            ->assertDontSee($invitationToken);
    }

    public function test_non_reviewer_cannot_supply_gmail_or_choose_an_invitation_method(): void
    {
        $ordinaryAdmin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($ordinaryAdmin)->from(route('admin.create-user'))->post(route('admin.store-user'), [
            'name' => 'Synthetic Restricted Student',
            'email' => 'restricted.owner@minsu.edu.ph',
            'role' => 'student',
            'student_id' => 'ONBOARD-TEST-03',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'notification_email' => 'not-authorized@gmail.com',
            'notification_email_contact_confirmed' => '1',
            'invitation_method' => 'email',
        ])->assertRedirect(route('admin.create-user'))
            ->assertSessionHasErrors(['notification_email', 'notification_email_contact_confirmed', 'invitation_method']);

        $this->assertDatabaseMissing('users', ['email' => 'restricted.owner@minsu.edu.ph']);
        Queue::assertNothingPushed();
    }

    public function test_admin_entered_applicant_password_is_rejected_and_no_account_is_created(): void
    {
        $this->actingAs($this->reviewer)->from(route('admin.create-user'))->post(route('admin.store-user'), [
            'name' => 'Synthetic Password Injection',
            'email' => 'password.injection@minsu.edu.ph',
            'role' => 'student',
            'student_id' => 'ONBOARD-TEST-04',
            'program' => 'BSIT',
            'year_level' => '3rd Year',
            'password' => 'Admin-Knows-This-Password-2026!',
            'password_confirmation' => 'Admin-Knows-This-Password-2026!',
        ])->assertRedirect(route('admin.create-user'))
            ->assertSessionHasErrors(['password', 'password_confirmation']);

        $this->assertDatabaseMissing('users', ['email' => 'password.injection@minsu.edu.ph']);
        Queue::assertNothingPushed();
    }

    public function test_expired_and_invalid_invitations_cannot_claim_or_activate_an_account(): void
    {
        $response = $this->actingAs($this->reviewer)->post(route('admin.store-user'), [
            'name' => 'Synthetic Expired Invitation Student',
            'email' => 'expired.invitation@minsu.edu.ph',
            'role' => 'student',
            'student_id' => 'ONBOARD-TEST-05',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'approve_verified' => '1',
            'invitation_method' => 'in_person',
            'institutional_identity_confirmed' => '1',
            'eligibility_confirmed' => '1',
            'verification_basis' => 'Confirmed against an isolated synthetic enrollment fixture.',
        ])->assertOk();
        preg_match('/id="handoff-token">([a-f0-9]{64})<\/p>/', $response->getContent(), $matches);
        $token = $matches[1] ?? '';
        $this->assertNotSame('', $token);
        $owner = User::query()->where('email', 'expired.invitation@minsu.edu.ph')->firstOrFail();
        $invitation = AccountInvitation::query()->where('user_id', $owner->id)->firstOrFail();
        $invitation->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->post(route('logout'));
        $this->from(route('account.activate'))->post(route('account.activate.accept'), [
            'token' => $token,
            'password' => 'Owner-Setup-2026!Strong',
            'password_confirmation' => 'Owner-Setup-2026!Strong',
        ])->assertRedirect(route('account.activate'))->assertSessionHasErrors('invitation');
        $this->from(route('account.activate'))->post(route('account.activate.accept'), [
            'token' => str_repeat('a', 64),
            'password' => 'Owner-Setup-2026!Strong',
            'password_confirmation' => 'Owner-Setup-2026!Strong',
        ])->assertRedirect(route('account.activate'))->assertSessionHasErrors('invitation');

        $this->assertGuest();
        $this->assertNull($owner->fresh()->password_setup_completed_at);
        $this->assertTrue($owner->fresh()->requiresOwnerOnboarding());
        $this->assertSame('approved', $owner->fresh()->approval_status);
    }

    public function test_legacy_existing_account_is_not_forced_into_owner_setup(): void
    {
        $existing = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'approval_status' => 'approved',
            'password' => Hash::make('Existing-Password-2026!'),
        ]);

        $this->assertFalse($existing->requiresOwnerOnboarding());
        $this->post(route('login'), [
            'email' => $existing->email,
            'password' => 'Existing-Password-2026!',
        ])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($existing);
    }

    public function test_login_keeps_mobile_page_at_top_without_autofocusing_email(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()->assertSee('magirem-logo--emblem', false);
        $this->assertStringNotContainsString('autofocus', strtolower($response->getContent()));
    }
}
