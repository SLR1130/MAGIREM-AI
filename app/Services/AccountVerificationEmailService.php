<?php

namespace App\Services;

use App\Jobs\SendAccountVerificationEmail;
use App\Models\AccountEmailVerification;
use App\Models\AccountInvitation;
use App\Models\AccountVerificationEmailDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class AccountVerificationEmailService
{
    public function issueCode(User $user): bool
    {
        if (!$this->transportConfigured()) {
            $this->recordUnavailable($user, 'verification_code', $user->notification_email, 'transport_not_configured');
            $user->forceFill(['verification_email_delivery_status' => 'not_configured'])->save();
            return false;
        }

        return $this->issueCodeAndQueue($user);
    }

    /** @return array{status: 'queued'|'cooldown'|'not_configured'|'failed', retry_after: int} */
    public function resendCode(User $user): array
    {
        if (!$this->transportConfigured()) {
            $this->recordUnavailable($user, 'verification_code', $user->notification_email, 'transport_not_configured');
            $user->forceFill(['verification_email_delivery_status' => 'not_configured'])->save();
            return ['status' => 'not_configured', 'retry_after' => 0];
        }

        return DB::transaction(function () use ($user): array {
            $verification = $user->verificationEmail()->lockForUpdate()->first();
            $cooldownSeconds = max(0, (int) config('services.account_verification.resend_cooldown_seconds', 60));
            if ($verification?->last_sent_at && $cooldownSeconds > 0) {
                $elapsedSeconds = max(0, now()->getTimestamp() - $verification->last_sent_at->getTimestamp());
                $retryAfter = max(0, $cooldownSeconds - $elapsedSeconds);
                if ($retryAfter > 0) {
                    return ['status' => 'cooldown', 'retry_after' => $retryAfter];
                }
            }

            return [
                'status' => $this->issueCodeAndQueue($user, $verification) ? 'queued' : 'failed',
                'retry_after' => 0,
            ];
        });
    }

    private function issueCodeAndQueue(User $user, ?AccountEmailVerification $verification = null): bool
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $verification ??= $user->verificationEmail()->firstOrNew();
        $verification->code_hash = $this->codeHash($user->id, $code);
        $verification->expires_at = now()->addMinutes(30);
        $verification->attempts = 0;
        $verification->send_count = (int) $verification->send_count + 1;
        $verification->last_sent_at = now();
        $verification->verified_at = null;
        $verification->save();

        $queued = $this->queue($user, 'verification_code', ['kind' => 'verification_code', 'name' => $user->name, 'code' => $code]);
        $user->forceFill(['verification_email_delivery_status' => $queued ? 'queued' : 'failed'])->save();
        return $queued;
    }

    public function queueStatusNotice(User $user, string $kind): string
    {
        $recipient = $user->notification_email;
        if (!$user->notification_email_verified_at) {
            $this->recordUnavailable($user, $kind, $recipient ?: '', 'recipient_not_verified');
            return 'not_verified';
        }
        if (!$recipient || !$user->notification_email_enabled) {
            $this->recordUnavailable($user, $kind, $recipient ?: '', 'recipient_unavailable');
            return 'not_available';
        }
        if (!$this->transportConfigured()) {
            $this->recordUnavailable($user, $kind, $recipient, 'transport_not_configured');
            return 'not_configured';
        }

        return $this->queue($user, $kind, ['kind' => $kind, 'name' => $user->name]) ? 'queued' : 'failed';
    }

    public function queuePasswordReset(User $user, #[\SensitiveParameter] string $token): bool
    {
        return DB::transaction(function () use ($user, $token): bool {
            $current = User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            $recipient = (string) ($current?->notification_email ?? '');
            if (!$current || !$current->is_active || !$current->notification_email_enabled
                || !$current->notification_email_verified_at || !$this->isPersonalGmail($recipient)
            ) {
                $reason = !$current?->notification_email_verified_at ? 'recipient_not_verified' : 'recipient_unavailable';
                $this->recordUnavailable($user, 'password_reset', $recipient, $reason);
                return false;
            }
            if (!$this->transportConfigured()) {
                $this->recordUnavailable($current, 'password_reset', $recipient, 'transport_not_configured');
                return false;
            }

            $baseUrl = rtrim((string) config('app.url'), '/');
            $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
            $host = (string) parse_url($baseUrl, PHP_URL_HOST);
            $hasUrlCredentials = parse_url($baseUrl, PHP_URL_USER) !== null || parse_url($baseUrl, PHP_URL_PASS) !== null;
            if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)
                || $host === '' || $hasUrlCredentials || ($this->appIsProduction() && $scheme !== 'https')
            ) {
                $this->recordUnavailable($current, 'password_reset', $recipient, 'reset_url_not_secure');
                return false;
            }

            // Fragments are not sent in HTTP requests or ordinary access logs.
            // The reset form reads the one-time token and email, then immediately
            // removes the fragment from browser history before submitting by POST.
            $path = route('password.reset', [], false);
            $fragment = http_build_query([
                'token' => $token,
                'email' => $current->getEmailForPasswordReset(),
            ], '', '&', PHP_QUERY_RFC3986);
            $resetUrl = $baseUrl . $path . '#' . $fragment;

            return $this->queue($current, 'password_reset', [
                'kind' => 'password_reset',
                'name' => $current->name,
                'reset_url' => $resetUrl,
            ]);
        });
    }

    public function queueAccountInvitation(User $user, AccountInvitation $invitation, string $inviteUrl): bool
    {
        if (!$this->transportConfigured()) {
            $this->recordUnavailable($user, 'account_invitation', (string) $user->notification_email, 'transport_not_configured');
            return false;
        }

        $fragment = (string) parse_url($inviteUrl, PHP_URL_FRAGMENT);
        parse_str($fragment, $fragmentValues);
        $token = $fragmentValues['invitation'] ?? null;
        if (!is_string($token) || $token === '') {
            return false;
        }

        $deliveryId = null;
        $queued = $this->queue($user, 'account_invitation', [
            'kind' => 'account_invitation',
            'name' => $user->name,
            'invite_url' => $inviteUrl,
            'invitation_id' => $invitation->id,
            'invitation_token' => $token,
        ], $deliveryId);

        if ($deliveryId) {
            $invitation->forceFill(['email_delivery_id' => $deliveryId])->save();
        }

        return $queued;
    }

    public function confirmCode(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $verification = $user->verificationEmail()->lockForUpdate()->first();
            if (!$verification || $verification->verified_at || !$verification->code_hash
                || !$verification->expires_at || $verification->expires_at->isPast()
                || $verification->attempts >= 5
            ) {
                return false;
            }

            if (!hash_equals($verification->code_hash, $this->codeHash($user->id, $code))) {
                $verification->attempts++;
                $verification->save();
                return false;
            }

            $verification->verified_at = now();
            $verification->code_hash = null;
            $verification->save();
            $user->forceFill(['notification_email_verified_at' => now()])->save();
            return true;
        });
    }

    public function transportConfigured(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $driver = config('mail.default');
        if (in_array($driver, ['log', 'array', 'null', 'failover', 'roundrobin'], true)) {
            return false;
        }

        if ($driver === 'smtp') {
            $smtp = (array) config('mail.mailers.smtp', []);
            $host = strtolower(trim((string) ($smtp['host'] ?? '')));
            $scheme = strtolower(trim((string) ($smtp['scheme'] ?? '')));
            $port = (int) ($smtp['port'] ?? 0);
            $username = strtolower(trim((string) ($smtp['username'] ?? '')));
            $password = (string) ($smtp['password'] ?? '');
            $from = strtolower(trim((string) config('mail.from.address', '')));

            $secureTransport = ($port === 465 && $scheme === 'smtps')
                || ($port === 587 && $scheme === 'smtp');

            if (!extension_loaded('openssl') || !$secureTransport
                || !filter_var($from, FILTER_VALIDATE_EMAIL)
                || !filter_var($username, FILTER_VALIDATE_EMAIL)
                || !filled($password) || !filled($host)
            ) {
                return false;
            }

            // A dedicated Gmail sender should authenticate and send as the same
            // account. Verified aliases can be introduced explicitly later.
            return $host !== 'smtp.gmail.com' || hash_equals($username, $from);
        }
        if ($driver === 'ses') {
            return filled(config('services.ses.key')) && filled(config('services.ses.secret'));
        }
        if ($driver === 'postmark') {
            return filled(config('services.postmark.key'));
        }
        if ($driver === 'resend') {
            return filled(config('services.resend.key'));
        }
        if ($driver === 'mailgun') {
            return filled(config('services.mailgun.secret')) && filled(config('services.mailgun.domain'));
        }

        return false;
    }

    private function codeHash(int $userId, string $code): string
    {
        return hash_hmac('sha256', $userId . ':' . $code, (string) config('app.key'));
    }

    private function queue(User $user, string $kind, array $payload, ?int &$deliveryId = null): bool
    {
        $delivery = AccountVerificationEmailDelivery::query()->create([
            'user_id' => $user->id,
            'kind' => $kind,
            'recipient_email' => $user->notification_email,
            'status' => 'queued',
            'queued_at' => now(),
        ]);
        $deliveryId = (int) $delivery->id;
        try {
            $encrypted = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
            SendAccountVerificationEmail::dispatch($delivery->id, $encrypted)->afterCommit();
            return true;
        } catch (Throwable) {
            $delivery->update(['status' => 'failed', 'last_error_code' => 'queue_unavailable']);
            return false;
        }
    }

    private function recordUnavailable(User $user, string $kind, string $recipient, string $reason): void
    {
        AccountVerificationEmailDelivery::query()->create([
            'user_id' => $user->id,
            'kind' => $kind,
            'recipient_email' => $recipient,
            'status' => 'failed',
            'last_error_code' => $reason,
            'queued_at' => now(),
        ]);
    }

    private function appIsProduction(): bool
    {
        return app()->environment('production');
    }

    private function isPersonalGmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && preg_match('/^[^@\s]+@(?:gmail\.com|googlemail\.com)$/i', $email) === 1;
    }
}
