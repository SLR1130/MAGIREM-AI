<?php

namespace Tests\Feature;

use App\Jobs\SendAccountVerificationEmail;
use App\Mail\AccountVerificationMail;
use App\Models\AccountVerificationEmailDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.account_verification.native_registration_enabled' => false,
            'auth.passwords.users.expire' => 30,
            'auth.passwords.users.throttle' => 60,
        ]);
    }

    public function test_only_verified_personal_gmail_receives_the_hashed_broker_reset_token(): void
    {
        Queue::fake();
        Mail::fake();
        $user = $this->eligibleUser();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.211']);
        $job = null;

        $this->from(route('password.request'))->post(route('password.email'), ['email' => strtoupper($user->email)])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status');

        Queue::assertPushed(SendAccountVerificationEmail::class, function (SendAccountVerificationEmail $queued) use (&$job, $user): bool {
            $payload = json_decode(Crypt::decryptString($queued->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
            if (($payload['kind'] ?? null) !== 'password_reset') return false;
            $job = $queued;
            return isset($payload['reset_url'])
                && parse_url($payload['reset_url'], PHP_URL_QUERY) === null
                && str_contains((string) parse_url($payload['reset_url'], PHP_URL_FRAGMENT), 'token=')
                && str_contains((string) parse_url($payload['reset_url'], PHP_URL_FRAGMENT), rawurlencode($user->email));
        });
        $this->assertNotNull($job);

        $delivery = AccountVerificationEmailDelivery::query()->where('kind', 'password_reset')->firstOrFail();
        $this->assertSame('personal.reset@gmail.com', $delivery->recipient_email);
        $this->assertNotSame($user->email, $delivery->recipient_email);
        $payload = json_decode(Crypt::decryptString($job->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
        parse_str((string) parse_url($payload['reset_url'], PHP_URL_FRAGMENT), $fragment);
        $storedToken = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertIsString($storedToken);
        $this->assertNotSame($fragment['token'], $storedToken, 'The broker stores only a protected representation of the token.');
        $this->assertTrue(Hash::check($fragment['token'], $storedToken));
        $this->assertSame(30, (int) config('auth.passwords.users.expire'));

        $job->handle();
        Mail::assertSent(AccountVerificationMail::class, function (AccountVerificationMail $mail) use ($user): bool {
            $rendered = $mail->render();
            return $mail->kind === 'password_reset'
                && $mail->hasTo('personal.reset@gmail.com')
                && !$mail->hasTo($user->email)
                && str_contains($rendered, 'expires in 30 minutes')
                && !str_contains($rendered, 'private journal')
                && !str_contains($rendered, 'school ID');
        });
        $this->assertSame('accepted', $delivery->fresh()->status);
        $this->assertSame('status-unchanged', $user->fresh()->approval_notification_status);
    }

    public function test_unknown_unverified_and_disabled_accounts_receive_the_same_public_response_and_no_reset_email(): void
    {
        Queue::fake();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.212']);
        $verified = $this->eligibleUser();
        $unverified = $this->eligibleUser('unverified@minsu.edu.ph', ['notification_email_verified_at' => null]);
        $disabled = $this->eligibleUser('disabled@minsu.edu.ph', ['is_active' => false]);
        $nonGmail = $this->eligibleUser('non-gmail@minsu.edu.ph', ['notification_email' => 'verified@outlook.com']);

        $verifiedResponse = $this->from(route('password.request'))->post(route('password.email'), ['email' => $verified->email]);
        $knownButUnverifiedResponse = $this->from(route('password.request'))->post(route('password.email'), ['email' => $unverified->email]);
        $disabledResponse = $this->from(route('password.request'))->post(route('password.email'), ['email' => $disabled->email]);
        $nonGmailResponse = $this->from(route('password.request'))->post(route('password.email'), ['email' => $nonGmail->email]);
        $unknownResponse = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'missing@minsu.edu.ph']);

        foreach ([$verifiedResponse, $knownButUnverifiedResponse, $disabledResponse, $nonGmailResponse, $unknownResponse] as $response) {
            $response->assertRedirect(route('password.request'))->assertSessionHas('status');
        }
        $messages = array_map(fn ($response) => $response->getSession()->get('status'), [$verifiedResponse, $knownButUnverifiedResponse, $disabledResponse, $nonGmailResponse, $unknownResponse]);
        $this->assertCount(1, array_unique($messages), 'Eligible and ineligible addresses must receive identical public wording.');
        $this->assertStringNotContainsString('personal.reset@gmail.com', $messages[0]);
        Queue::assertPushed(SendAccountVerificationEmail::class, 1);
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->assertDatabaseMissing('account_verification_email_deliveries', ['user_id' => $unverified->id, 'kind' => 'password_reset']);
        $this->assertDatabaseMissing('account_verification_email_deliveries', ['user_id' => $nonGmail->id, 'kind' => 'password_reset']);
    }

    public function test_valid_reset_changes_only_password_secrets_and_does_not_approve_or_activate_an_account(): void
    {
        $user = $this->eligibleUser('pending@minsu.edu.ph', [
            'role' => 'counselor',
            'requested_role' => 'counselor',
            'approval_status' => 'pending',
            'is_active' => true,
        ]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.213']);
        $oldRememberToken = $user->remember_token;
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'old-session-' . Str::random(20), 'user_id' => $user->id,
            'ip_address' => '127.0.0.1', 'user_agent' => 'synthetic test', 'payload' => 'encrypted-session', 'last_activity' => now()->timestamp,
        ]);
        $token = Password::broker()->createToken($user);

        $this->from(route('password.reset'))->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'Replacement-Strong-42!',
            'password_confirmation' => 'Replacement-Strong-42!',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('Replacement-Strong-42!', $fresh->password));
        $this->assertFalse(Hash::check('Factory-Old-Password-42!', $fresh->password));
        $this->assertNotSame($oldRememberToken, $fresh->remember_token);
        $this->assertSame('counselor', $fresh->role);
        $this->assertSame('counselor', $fresh->requested_role);
        $this->assertSame($user->email, $fresh->email);
        $this->assertSame($user->institutional_email, $fresh->institutional_email);
        $this->assertSame('pending', $fresh->approval_status);
        $this->assertTrue($fresh->is_active);
        $this->assertSame('personal.reset@gmail.com', $fresh->notification_email);
        $this->assertNotNull($fresh->notification_email_verified_at);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertGuest();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'Replacement-Strong-42!'])
            ->assertRedirect(route('account.status'));
        $this->assertAuthenticatedAs($fresh);
    }

    public function test_expired_invalid_and_reused_tokens_are_rejected_without_changing_the_password(): void
    {
        $user = $this->eligibleUser();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.214']);
        $oldHash = $user->password;
        $expired = Password::broker()->createToken($user);
        $this->travel(31)->minutes();
        $this->from(route('password.reset'))->post(route('password.update'), $this->resetPayload($user, $expired))
            ->assertRedirect(route('password.reset'))->assertSessionHasErrors('email');
        $this->assertSame($oldHash, $user->fresh()->password);

        $this->travelBack();
        $valid = Password::broker()->createToken($user);
        $payload = $this->resetPayload($user, $valid);
        $this->post(route('password.update'), $payload)->assertRedirect(route('login'));
        $newHash = $user->fresh()->password;
        $this->from(route('password.reset'))->post(route('password.update'), $payload)
            ->assertRedirect(route('password.reset'))->assertSessionHasErrors('email');
        $this->assertSame($newHash, $user->fresh()->password);

        $this->from(route('password.reset'))->post(route('password.update'), $this->resetPayload($user, str_repeat('x', 64)))
            ->assertRedirect(route('password.reset'))->assertSessionHasErrors('email');
        $this->assertSame($newHash, $user->fresh()->password);
    }

    public function test_request_and_redemption_are_throttled_and_non_minusu_domains_are_rejected(): void
    {
        Queue::fake();
        $user = $this->eligibleUser();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.217']);
        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'person@example.com'])
            ->assertRedirect(route('password.request'))->assertSessionHasErrors('email');

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $response = $this->post(route('password.email'), ['email' => $user->email]);
            $this->assertSame(302, $response->getStatusCode());
        }
        $this->post(route('password.email'), ['email' => $user->email])->assertStatus(429);

        RateLimiter::clear(sha1('|203.0.113.217'));
        $token = Password::broker()->createToken($user);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $response = $this->post(route('password.update'), $this->resetPayload($user, $token));
            $this->assertSame(302, $response->getStatusCode());
        }
        $this->post(route('password.update'), $this->resetPayload($user, $token))->assertStatus(429);
    }

    public function test_gmail_verification_revoked_after_request_prevents_reset_and_consumes_the_token(): void
    {
        Queue::fake();
        $user = $this->eligibleUser();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.218']);
        $token = Password::broker()->createToken($user);
        $user->forceFill(['notification_email_verified_at' => null])->save();

        $this->from(route('password.reset'))->post(route('password.update'), $this->resetPayload($user, $token))
            ->assertRedirect(route('password.reset'))->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('Replacement-Strong-42!', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_reset_link_queued_before_personal_gmail_changes_is_not_sent_to_the_old_address(): void
    {
        Queue::fake();
        Mail::fake();
        $user = $this->eligibleUser();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.219']);
        $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('password.request'));
        $job = Queue::pushed(SendAccountVerificationEmail::class)->first();
        $this->assertNotNull($job);

        $user->forceFill([
            'notification_email' => 'replacement.address@gmail.com',
            'notification_email_verified_at' => null,
        ])->save();
        $job->handle();

        Mail::assertNothingOutgoing();
        $this->assertDatabaseHas('account_verification_email_deliveries', [
            'user_id' => $user->id,
            'kind' => 'password_reset',
            'status' => 'failed',
            'last_error_code' => 'recipient_no_longer_verified',
        ]);
    }

    public function test_recovery_accepts_case_variants_for_existing_institutional_email(): void
    {
        Queue::fake();
        $user = $this->eligibleUser('student@minsu.edu.ph');
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.220']);
        $user->forceFill([
            'email' => 'Student@MinSU.edu.ph',
            'institutional_email' => 'Student@MinSU.edu.ph',
        ])->save();

        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'STUDENT@MINSU.EDU.PH'])
            ->assertRedirect(route('password.request'))->assertSessionHas('status');
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'Student@MinSU.edu.ph']);

        $token = Password::broker()->createToken($user->fresh());
        $this->post(route('password.update'), [
            'email' => 'STUDENT@MINSU.EDU.PH',
            'token' => $token,
            'password' => 'Replacement-Strong-42!',
            'password_confirmation' => 'Replacement-Strong-42!',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('Replacement-Strong-42!', $user->fresh()->password));
    }

    private function eligibleUser(string $email = 'student@minsu.edu.ph', array $overrides = []): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => 'Synthetic Recovery Applicant',
            'role' => 'student',
            'password' => 'Factory-Old-Password-42!',
        ]);
        $user->forceFill(array_merge([
            'institutional_email' => $email,
            'notification_email' => 'personal.reset@gmail.com',
            'notification_email_enabled' => true,
            'notification_email_verified_at' => now(),
            'approval_status' => 'approved',
            'approval_notification_status' => 'status-unchanged',
            'is_active' => true,
        ], $overrides))->save();

        return $user->fresh();
    }

    private function resetPayload(User $user, string $token): array
    {
        return [
            'email' => $user->email,
            'token' => $token,
            'password' => 'Replacement-Strong-42!',
            'password_confirmation' => 'Replacement-Strong-42!',
        ];
    }
}
