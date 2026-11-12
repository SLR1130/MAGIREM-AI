<?php

namespace App\Services;

use App\Models\AccountInvitation;
use App\Models\AccountVerificationAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountInvitationService
{
    public function __construct(private readonly AccountVerificationEmailService $emails) {}

    /**
     * Issue one expiring owner-activation token. Plaintext is returned only for
     * the in-person handoff page; email tokens exist only in encrypted queue data.
     *
     * @return array{invitation: AccountInvitation, delivery_status: string, handoff_token: ?string, activation_url: string}
     */
    public function issue(User $user, int $reviewerId, string $method): array
    {
        $reviewer = User::query()->find($reviewerId);
        if (!$reviewer?->canReviewAccountVerification()) {
            throw new InvalidArgumentException('An active authorized Guidance Office reviewer is required.');
        }

        if (!in_array($method, ['email', 'in_person'], true)) {
            throw new InvalidArgumentException('Unsupported account invitation method.');
        }

        if (!$user->is_active || !$user->isApproved() || !$user->requiresOwnerOnboarding()
            || $user->password_setup_completed_at !== null
            || !in_array($user->requested_role, ['student', 'counselor'], true)
        ) {
            throw new InvalidArgumentException('This account is not eligible for owner activation.');
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)
            || $host === '' || parse_url($baseUrl, PHP_URL_USER) !== null || parse_url($baseUrl, PHP_URL_PASS) !== null
            || (app()->environment('production') && $scheme !== 'https')
        ) {
            throw new InvalidArgumentException('Secure account activation URL configuration is unavailable.');
        }

        $recipient = null;
        if ($method === 'email') {
            $recipient = mb_strtolower(trim((string) $user->notification_email));
            if (!$user->notification_email_enabled
                || !filter_var($recipient, FILTER_VALIDATE_EMAIL)
                || preg_match('/^[^@\s]+@(?:gmail\.com|googlemail\.com)$/i', $recipient) !== 1
                || !$user->notification_email_confirmed_at
                || !$user->notification_email_confirmed_by
            ) {
                throw new InvalidArgumentException('Email invitation requires a reviewer-confirmed personal Gmail contact.');
            }
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = now()->addHours(max(1, (int) config('services.account_verification.invitation_ttl_hours', 24)));
        $shouldQueueEmail = true;
        $invitation = DB::transaction(function () use ($user, $reviewerId, $method, $recipient, $token, $expiresAt, &$shouldQueueEmail): AccountInvitation {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedReviewer = User::query()->find($reviewerId);
            if (!$lockedReviewer?->canReviewAccountVerification()) {
                throw new InvalidArgumentException('The Guidance Office reviewer is no longer authorized.');
            }
            if (!$lockedUser->is_active || !$lockedUser->isApproved() || !$lockedUser->requiresOwnerOnboarding()
                || $lockedUser->password_setup_completed_at !== null
            ) {
                throw new InvalidArgumentException('This account is no longer eligible for owner activation.');
            }

            // A double-click or repeated form submission must not revoke the
            // only usable link and stack another queued Gmail job. Reuse the
            // same still-pending email invitation for this confirmed address.
            if ($method === 'email') {
                $pending = AccountInvitation::query()
                    ->where('user_id', $lockedUser->id)
                    ->where('delivery_method', 'email')
                    ->where('recipient_email', $recipient)
                    ->whereNull('accepted_at')
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', now())
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();
                $pendingStatus = $pending?->emailDelivery()->value('status');
                if ($pending && in_array($pendingStatus, ['queued', 'sending', 'retrying'], true)) {
                    $shouldQueueEmail = false;
                    return $pending;
                }
            }

            AccountInvitation::query()
                ->where('user_id', $lockedUser->id)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);

            $invitation = AccountInvitation::query()->create([
                'user_id' => $lockedUser->id,
                'created_by' => $reviewerId,
                'delivery_method' => $method,
                'token_hash' => $this->hashToken($token),
                'recipient_email' => $recipient,
                'expires_at' => $expiresAt,
            ]);

            AccountVerificationAudit::query()->create([
                'application_user_id' => $lockedUser->id,
                'actor_user_id' => $reviewerId,
                'action' => 'owner_invitation_issued',
                'from_status' => $lockedUser->approval_status,
                'to_status' => $lockedUser->approval_status,
                'internal_note' => 'Owner activation invitation issued by ' . $method . ' handoff; invitation token is not recorded.',
                'created_at' => now(),
            ]);

            return $invitation;
        });

        $activationUrl = $baseUrl . route('account.activate', [], false) . '#invitation=' . rawurlencode($token);
        $deliveryStatus = 'in_person';
        if ($method === 'email') {
            $deliveryStatus = !$shouldQueueEmail
                ? 'queued'
                : ($this->emails->queueAccountInvitation($user->fresh(), $invitation, $activationUrl) ? 'queued' : 'failed');
        }

        return [
            'invitation' => $invitation->fresh(),
            'delivery_status' => $deliveryStatus,
            'handoff_token' => $method === 'in_person' ? $token : null,
            'activation_url' => $activationUrl,
        ];
    }

    public function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
