<?php

namespace App\Jobs;

use App\Mail\AccountVerificationMail;
use App\Models\AccountVerificationEmailDelivery;
use App\Models\AccountInvitation;
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
        } catch (Throwable) {
            $delivery->update(['status' => 'retrying', 'last_error_code' => 'transport_error']);
            throw new \RuntimeException('Account verification email transport failed.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = AccountVerificationEmailDelivery::query()->find($this->deliveryId);
        if (!$delivery) return;
        $delivery->update(['status' => 'failed', 'last_error_code' => 'transport_error']);
        if ($delivery->user) {
            $field = match ($delivery->kind) {
                'verification_code' => 'verification_email_delivery_status',
                'approved', 'needs_information', 'rejected', 'suspended', 'delivery_test' => 'approval_notification_status',
                default => null,
            };
            if ($field) $delivery->user()->update([$field => 'failed']);
        }
    }
}
