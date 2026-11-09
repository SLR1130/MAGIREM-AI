<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $kind,
        public readonly string $name,
        public readonly ?string $code = null,
        public readonly ?string $resetUrl = null,
        public readonly ?string $inviteUrl = null,
    ) {}

    public function build(): self
    {
        $subject = match ($this->kind) {
            'delivery_test' => 'MAGIREM AI notification delivery test',
            'password_reset' => 'Reset your MAGIREM AI password',
            'account_invitation' => 'Finish setting up your MAGIREM AI account',
            'verification_code' => 'Verify your MAGIREM AI notification email',
            'approved' => 'Your MAGIREM AI registration status',
            'needs_information' => 'Action needed for your MAGIREM AI registration',
            'rejected' => 'Your MAGIREM AI registration status',
            'suspended' => 'Your MAGIREM AI account status',
            default => 'Your MAGIREM AI registration update',
        };

        return $this->subject($subject)->view('emails.account-verification');
    }
}
