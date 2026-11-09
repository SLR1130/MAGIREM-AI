<?php

namespace App\Http\Controllers;

use App\Models\AccountInvitation;
use App\Models\AccountVerificationAudit;
use App\Models\User;
use App\Services\AccountVerificationEmailService;
use App\Services\AccountInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AccountOnboardingController extends Controller
{
    public function __construct(
        private readonly AccountInvitationService $invitations,
        private readonly AccountVerificationEmailService $emails,
    ) {}

    public function activateForm(): Response
    {
        return response()->view('auth.account-activate')
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function acceptInvitation(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ], [
            'token.regex' => 'This invitation is invalid or expired. Ask the Guidance Office reviewer for a new invitation.',
            'password.min' => 'Use at least 12 characters.',
            'password.mixed' => 'Include uppercase and lowercase letters.',
            'password.numbers' => 'Include at least one number.',
            'password.symbols' => 'Include at least one symbol.',
        ]);
        if ($validator->fails()) {
            // Never flash a bearer invitation token into server-side old input.
            // Preserve it only in the URL fragment so browser code can restore
            // the form, then immediately remove it before another request.
            $token = $request->input('token');
            $target = is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1
                ? route('account.activate') . '#invitation=' . rawurlencode($token)
                : route('account.activate');

            return redirect()->to($target)->withErrors($validator);
        }
        $validated = $validator->validated();

        $user = DB::transaction(function () use ($validated): ?User {
            $hash = $this->invitations->hashToken($validated['token']);
            $invitation = AccountInvitation::query()->where('token_hash', $hash)->lockForUpdate()->first();
            if (!$invitation || $invitation->accepted_at || $invitation->revoked_at || !$invitation->expires_at || $invitation->expires_at->isPast()) {
                return null;
            }

            $user = User::query()->lockForUpdate()->find($invitation->user_id);
            if (!$user || !$user->is_active || !$user->isApproved() || !$user->requiresOwnerOnboarding()
                || $user->password_setup_completed_at !== null
                || !in_array($user->requested_role, ['student', 'counselor'], true)
            ) {
                return null;
            }

            if ($invitation->delivery_method === 'email') {
                if (!$user->notification_email_confirmed_at || !$user->notification_email_confirmed_by
                    || mb_strtolower((string) $invitation->recipient_email) !== mb_strtolower((string) $user->notification_email)
                ) {
                    return null;
                }
            } elseif ($invitation->delivery_method !== 'in_person') {
                return null;
            }

            $user->forceFill([
                'password' => Hash::make($validated['password']),
                'password_setup_completed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            $invitation->forceFill(['accepted_at' => now()])->save();
            AccountInvitation::query()
                ->where('user_id', $user->id)
                ->where('id', '!=', $invitation->id)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);

            AccountVerificationAudit::query()->create([
                'application_user_id' => $user->id,
                'actor_user_id' => $user->id,
                'action' => 'owner_password_setup_completed',
                'from_status' => $user->approval_status,
                'to_status' => $user->approval_status,
                'internal_note' => null,
                'created_at' => now(),
            ]);

            return $user->fresh();
        });

        if (!$user) {
            return back()->withErrors(['invitation' => 'This invitation is invalid, expired, already used, or the account is no longer eligible. Contact the designated Guidance Office reviewer.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $codeQueued = false;
        if ($user->notification_email) {
            $codeQueued = $this->emails->issueCode($user->fresh());
        }

        return redirect()->route('account.status')->with(
            $codeQueued ? 'success' : 'warning',
            $user->notification_email
                ? ($codeQueued
                    ? 'Your password is set. A one-time code was sent to the confirmed personal Gmail. Verify it to finish setup; Guidance approval remains unchanged.'
                    : 'Your password is set, but no Gmail code was sent. Use the account setup page to retry after mail delivery is available.')
                : 'Your password is set. Add your personal Gmail and verify it to finish setup; Guidance approval remains unchanged.'
        );
    }

    public function setPersonalEmail(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->eligibleForOwnerSetup($user), 403);
        abort_if(filled($user->notification_email), 409, 'A confirmed invitation contact is already on this account. Contact the authorized reviewer if it needs correction.');

        $validated = $request->validate([
            'notification_email' => ['required', 'email:rfc', 'max:255', 'regex:/^[^@\s]+@(?:gmail\.com|googlemail\.com)$/i'],
        ], [
            'notification_email.regex' => 'Enter a personal Gmail address ending in gmail.com or googlemail.com.',
        ]);

        $email = mb_strtolower(trim($validated['notification_email']));
        DB::transaction(function () use ($user, $email): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($this->eligibleForOwnerSetup($locked) && blank($locked->notification_email), 409);
            $locked->forceFill([
                'notification_email' => $email,
                'notification_email_enabled' => true,
                'notification_email_verified_at' => null,
                'notification_email_confirmed_at' => null,
                'notification_email_confirmed_by' => null,
                'verification_email_delivery_status' => 'not_requested',
            ])->save();
            $locked->verificationEmail()->delete();
            AccountVerificationAudit::query()->create([
                'application_user_id' => $locked->id,
                'actor_user_id' => $locked->id,
                'action' => 'owner_onboarding_gmail_submitted',
                'from_status' => $locked->approval_status,
                'to_status' => $locked->approval_status,
                'internal_note' => 'Owner supplied a personal Gmail during approved account onboarding; ownership remains unverified until OTP confirmation.',
                'created_at' => now(),
            ]);
        });

        $queued = $this->emails->issueCode($user->fresh());
        return back()->with(
            $queued ? 'success' : 'warning',
            $queued ? 'A one-time code was sent. Your Gmail remains unverified until you enter the code.' : 'No code was sent. Your Gmail remains unverified; retry after mail delivery is available.'
        );
    }

    public function resendInvitation(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->canReviewAccountVerification(), 403);
        abort_unless($user->requiresOwnerOnboarding() && $user->password_setup_completed_at === null
            && $user->is_active && $user->isApproved()
            && $user->notification_email_confirmed_at && $user->notification_email_confirmed_by, 409);

        $result = $this->invitations->issue($user, (int) $request->user()->id, 'email');
        return back()->with(
            $result['delivery_status'] === 'queued' ? 'success' : 'warning',
            $result['delivery_status'] === 'queued'
                ? 'A new one-time account invitation was queued for the independently confirmed personal Gmail. Any previous invitation was revoked.'
                : 'The account remains restricted. The invitation could not be queued; no password was sent.'
        );
    }

    public function createInPersonInvitation(Request $request, User $user)
    {
        abort_unless($request->user()?->canReviewAccountVerification(), 403);
        abort_unless($user->requiresOwnerOnboarding() && $user->password_setup_completed_at === null
            && $user->is_active && $user->isApproved(), 409);

        $result = $this->invitations->issue($user, (int) $request->user()->id, 'in_person');
        return response()
            ->view('admin.account-verification.manual-invitation', [
                'applicant' => $user,
                'handoffToken' => $result['handoff_token'],
                'activationUrl' => $result['activation_url'],
                'expiresAt' => $result['invitation']->expires_at,
            ])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function eligibleForOwnerSetup(User $user): bool
    {
        return $user->requiresOwnerOnboarding()
            && $user->password_setup_completed_at !== null
            && $user->is_active
            && $user->isApproved()
            && in_array($user->requested_role, ['student', 'counselor'], true);
    }
}
