<?php

namespace App\Jobs;

use App\Mail\AccountVerificationMail;
use App\Models\AccountVerificationEmailDelivery;
use App\Models\AccountInvitation;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAccountVerificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 25;
    public array $backoff = [20, 90];

    public function __construct(public readonly int $deliveryId, public readonly string $encryptedPayload)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $delivery = AccountVerificationEmailDelivery::query()->findOrFail($this->deliveryId);
        $payload = json_decode(Crypt::decryptString($this->encryptedPayload), true, 8, JSON_THROW_ON_ERROR);
        $delivery->update(['status' => 'sending', 'attempts' => $delivery->attempts + 1, 'last_error_code' => null]);

        // A reset request may wait in the queue while the user changes or
        // disables their notification address. Re-check trusted database state
        // at send time so a stale reset link is never delivered to an old inbox.
        if ($delivery->kind === 'password_reset') {
            $user = $delivery->user;
            if (!$user || !$user->is_active || !$user->notification_email_enabled
                || !$user->notification_email_verified_at
                || preg_match('/^[^@\s]+@(?:gmail\.com|googlemail\.com)$/i', (string) $user->notification_email) !== 1
                || !hash_equals(
                    mb_strtolower((string) $delivery->recipient_email),
                    mb_strtolower((string) $user->notification_email),
                )
            ) {
                $delivery->update(['status' => 'failed', 'last_error_code' => 'recipient_no_longer_verified']);
                return;
            }
        }

        if ($delivery->kind === 'account_invitation') {
            $invitation = AccountInvitation::query()->with('user')->find($payload['invitation_id'] ?? 0);
            $token = $payload['invitation_token'] ?? null;
            $user = $invitation?->user;
            $recipient = (string) ($delivery->recipient_email ?? '');
            $valid = $invitation && is_string($token) && $token !== '' && $user
                && $invitation->delivery_method === 'email'
                && !$invitation->accepted_at && !$invitation->revoked_at
                && $invitation->expires_at && $invitation->expires_at->isFuture()
                && hash_equals($invitation->token_hash, hash_hmac('sha256', $token, (string) config('app.key')))
                && $user->is_active && $user->isApproved() && $user->requiresOwnerOnboarding()
                && $user->password_setup_completed_at === null
                && $user->notification_email_verified_at === null
                && $user->notification_email_confirmed_at !== null
                && mb_strtolower((string) $user->notification_email) === mb_strtolower($recipient)
                && mb_strtolower((string) $invitation->recipient_email) === mb_strtolower($recipient);

            if (!$valid) {
                $delivery->update(['status' => 'failed', 'last_error_code' => 'invitation_no_longer_valid']);
                return;
            }
        }

        try {
            Mail::to($delivery->recipient_email)->send(new AccountVerificationMail(
                $payload['kind'],
                $payload['name'],
                $payload['code'] ?? null,
                $payload['reset_url'] ?? null,
                $payload['invite_url'] ?? null,
            ));
            $delivery->update(['status' => 'accepted', 'accepted_at' => now(), 'last_error_code' => null]);
            if ($delivery->user) {
                $field = match ($delivery->kind) {
                    'verification_code' => 'verification_email_delivery_status',
                    'approved', 'needs_information', 'rejected', 'suspended', 'delivery_test' => 'approval_notification_status',
                    default => null,
                };
                if ($field) $delivery->user()->update([$field => 'accepted']);
            }
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => 'retrying',
                'last_error_code' => $this->safeTransportErrorCode($exception),
            ]);
            throw new \RuntimeException('Account verification email transport failed.');
        }

        if ($delivery->kind === 'account_invitation') {
            try {
                $this->notifyInvitationIssuer($delivery, 'accepted');
            } catch (Throwable) {
                // Email delivery remains accepted even if an in-app alert cannot be recorded.
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = AccountVerificationEmailDelivery::query()->find($this->deliveryId);
        if (!$delivery) return;
        $failureCode = in_array($delivery->last_error_code, [
            'smtp_authentication_failed',
            'smtp_tls_failed',
            'smtp_connection_failed',
            'smtp_temporary_rejection',
            'smtp_permanent_rejection',
            'transport_error',
        ], true) ? $delivery->last_error_code : 'transport_error';
        $delivery->update(['status' => 'failed', 'last_error_code' => $failureCode]);
        if ($delivery->user) {
            $field = match ($delivery->kind) {
                'verification_code' => 'verification_email_delivery_status',
                'approved', 'needs_information', 'rejected', 'suspended', 'delivery_test' => 'approval_notification_status',
                default => null,
            };
            if ($field) $delivery->user()->update([$field => 'failed']);
        }
        if ($delivery->kind === 'account_invitation') {
            try {
                $this->notifyInvitationIssuer($delivery, 'failed', $failureCode);
            } catch (Throwable) {
                // Do not expose transport data or change the failure state.
            }
        }
    }

    private function safeTransportErrorCode(Throwable $exception): string
    {
        // Inspect provider error text only in memory. Persist a coarse allowlisted
        // code; never log or store the raw SMTP response or exception message.
        $message = strtolower($exception->getMessage());
        $class = strtolower($exception::class);
        if (str_contains($message, '535') || str_contains($message, 'authentication')
            || str_contains($message, 'authenticator') || str_contains($class, 'authentication')) {
            return 'smtp_authentication_failed';
        }
        if (str_contains($message, 'tls') || str_contains($message, 'ssl')
            || str_contains($message, 'certificate') || str_contains($class, 'tls')) {
            return 'smtp_tls_failed';
        }
        if (preg_match('/\b(?:421|450|451|452)\b/', $message)) {
            return 'smtp_temporary_rejection';
        }
        if (preg_match('/\b5\d\d\b/', $message) || str_contains($message, 'rejected')) {
            return 'smtp_permanent_rejection';
        }
        if (str_contains($message, 'connection') || str_contains($message, 'timed out')
            || str_contains($message, 'timeout') || str_contains($class, 'transport')) {
            return 'smtp_connection_failed';
        }

        return 'transport_error';
    }

    private function notifyInvitationIssuer(AccountVerificationEmailDelivery $delivery, string $status, ?string $failureCode = null): void
    {
        $invitation = AccountInvitation::query()->with('user')->where('email_delivery_id', $delivery->id)->first();
        $reviewer = $invitation?->created_by ? User::query()->find($invitation->created_by) : null;
        if (!$invitation?->user || !$reviewer?->canReviewAccountVerification()) return;

        $accepted = $status === 'accepted';
        $title = $accepted ? 'Setup invitation email accepted' : 'Setup invitation email failed';
        $link = route('admin.account-verification.show', $invitation->user, false);
        $failureMessage = match ($failureCode) {
            'smtp_authentication_failed' => 'Gmail rejected SMTP authentication. Check the sender account and app-password configuration privately, then retry.',
            'smtp_tls_failed' => 'The secure Gmail SMTP/TLS handshake failed. Check local SMTP security settings before retrying.',
            'smtp_connection_failed' => 'The application could not establish a Gmail SMTP connection. Check the worker and network, then retry.',
            'smtp_temporary_rejection' => 'Gmail temporarily rejected the message. Check the delivery status and retry after the provider recovers.',
            'smtp_permanent_rejection' => 'Gmail permanently rejected the message. Check sender and recipient configuration before retrying.',
            default => 'The one-time setup invitation could not be delivered. Review the account delivery status before retrying.',
        };
        Notification::query()->create([
            'user_id' => $reviewer->id,
            'type' => 'account',
            'title' => $title,
            'message' => $accepted
                ? 'The mail provider accepted the one-time setup invitation. This does not confirm inbox placement.'
                : $failureMessage,
            'is_read' => false,
            'priority' => $accepted ? 'normal' : 'high',
            'icon' => 'mail',
            'link' => $link,
        ]);
    }
}
