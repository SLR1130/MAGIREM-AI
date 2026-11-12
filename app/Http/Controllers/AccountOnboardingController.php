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
                    ? 'Your password is set. A one-time code is queued for the confirmed personal Gmail; delivery is not confirmed yet. Enter it after it arrives to finish setup. Guidance approval remains unchanged.'
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
            $queued ? 'A one-time code is queued for your personal Gmail; delivery is not confirmed yet. Your Gmail remains unverified until you enter the code.' : 'No code was queued. Your Gmail remains unverified; retry after mail delivery is available.'
        );
    }

    public function resendInvitation(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->canReviewAccountVerification(), 403);
        abort_unless($user->requiresOwnerOnboarding() && $user->password_setup_completed_at === null
            && $user->is_active && $user->isApproved()
            && $user->notification_email_confirmed_at && $user->notification_email_confirmed_by, 409);

        $result = $this->invitations->issue($user, (int) $request->user()->id, 'email');
        if ($result['delivery_status'] === 'queued') {
            // The reviewer receives a persistent bell alert for queueing and a
            // separate alert after the provider accepts or fails delivery.
            return back();
        }

        return back()->with('warning', 'The account remains restricted. The invitation could not be queued; no password was sent.');
    }

    public function confirmCounselorGmailAndSendInvitation(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->canReviewAccountVerification(), 403);
        abort_unless($this->isEligibleAdminCreatedCounselor($user), 409, 'Only an approved admin-created counselor who has not set a password can use this setup flow.');

        $validated = $request->validate([
            'notification_email' => ['required', 'email:rfc', 'max:255', 'regex:/^[^@\s]+@(?:gmail\.com|googlemail\.com)$/i'],
            'notification_email_contact_confirmed' => ['required', 'accepted'],
        ], [
            'notification_email.regex' => 'Enter the counselor\'s personal Gmail address ending in gmail.com or googlemail.com.',
            'notification_email_contact_confirmed.accepted' => 'Confirm that you independently verified this Gmail belongs to the counselor.',
        ]);

        $reviewerId = (int) $request->user()->id;
        $email = mb_strtolower(trim($validated['notification_email']));
        $alreadyQueued = $user->accountInvitations()
            ->where('delivery_method', 'email')
            ->where('recipient_email', $email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->whereHas('emailDelivery', static fn ($query) => $query->whereIn('status', ['queued', 'sending', 'retrying']))
            ->exists();
        if ($alreadyQueued) {
            return redirect()->route('admin.account-verification.show', $user)
                ->with('info', 'A setup invitation for this confirmed Gmail is already queued. No duplicate email or replacement link was created; wait for delivery status to update.');
        }

        DB::transaction(function () use ($user, $reviewerId, $email): void {
            $counselor = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($this->isEligibleAdminCreatedCounselor($counselor), 409);

            $counselor->forceFill([
                'notification_email' => $email,
                'notification_email_enabled' => true,
                'notification_email_confirmed_at' => now(),
                'notification_email_confirmed_by' => $reviewerId,
                'notification_email_verified_at' => null,
                'verification_email_delivery_status' => 'not_requested',
            ])->save();
            $counselor->verificationEmail()->delete();

            AccountVerificationAudit::query()->create([
                'application_user_id' => $counselor->id,
                'actor_user_id' => $reviewerId,
                'action' => 'owner_invitation_contact_confirmed',
                'from_status' => $counselor->approval_status,
                'to_status' => $counselor->approval_status,
                'internal_note' => 'Authorized reviewer independently confirmed or updated the approved counselor\'s personal Gmail for first-use setup. Gmail ownership remains unverified until the counselor completes OTP.',
                'created_at' => now(),
            ]);
        });

        try {
            $result = $this->invitations->issue($user->fresh(), $reviewerId, 'email');
        } catch (\InvalidArgumentException) {
            return redirect()->route('admin.account-verification.show', $user)
                ->with('warning', 'The Gmail association was recorded, but the setup invitation could not be queued. The account remains restricted; check mail configuration before retrying.');
        }

        $redirect = redirect()->route('admin.account-verification.show', $user->fresh());
        return $result['delivery_status'] === 'queued'
            ? $redirect
            : $redirect->with('warning', 'The Gmail association was recorded, but no setup invitation was queued. The counselor account remains restricted; check email delivery before retrying.');
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

    private function isEligibleAdminCreatedCounselor(User $user): bool
    {
        return $user->role === 'counselor'
            && $user->requested_role === 'counselor'
            && $user->approval_status === 'approved'
            && (bool) $user->is_active
            && $user->requiresOwnerOnboarding()
            && $user->password_setup_completed_at === null
            && $user->approval_submitted_at === null
            && $user->verificationAudits()
                ->whereIn('action', ['admin_created_pending', 'admin_created_approved'])
                ->exists();
    }
}
