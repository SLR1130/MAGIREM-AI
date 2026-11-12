<!doctype html>
<html lang="en">
<body style="margin:0;background:#F8FAF7;color:#263A33;font-family:Arial,sans-serif;padding:28px">
    <main style="max-width:560px;margin:auto;background:#fff;border:1px solid #DCE8E1;border-radius:16px;padding:28px">
        <p style="margin:0 0 8px;color:#236B55;font-size:12px;font-weight:bold;letter-spacing:.12em;text-transform:uppercase">MAGIREM AI</p>
        <h1 style="margin:0 0 14px;color:#103F32;font-size:22px">{{ $kind === 'password_reset' ? 'Reset your password' : ($kind === 'account_invitation' ? 'Set up your account' : 'Registration update') }}</h1>
        <p>Hello {{ $name }},</p>
        @if($kind === 'delivery_test')
            <p>This is a one-time test of the MAGIREM AI notification service. No account was created, and no account status changed.</p>
        @elseif($kind === 'password_reset')
            <p>We received a request to reset your MAGIREM AI password. Use the button below to choose a new password. This link expires in 30 minutes and can be used once.</p>
            <p style="margin:22px 0;text-align:center"><a href="{{ $resetUrl }}" style="display:inline-block;padding:12px 20px;border-radius:10px;background:#103F32;color:#fff;font-weight:bold;text-decoration:none">Reset password</a></p>
            <p>If you did not request this, ignore this message. Your password will not change unless the link is used.</p>
        @elseif($kind === 'account_invitation')
            <p>An authorized Guidance Office reviewer created and approved your MAGIREM AI account. Use the one-time link below to create your own password. After setup, enter the verification code sent to your personal Gmail. Your account remains restricted until setup and Gmail verification are complete; once verified, MAGIREM AI takes you to the dashboard for your approved role without another Guidance review.</p>
            <p style="margin:22px 0;text-align:center"><a href="{{ $inviteUrl }}" style="display:inline-block;padding:12px 20px;border-radius:10px;background:#103F32;color:#fff;font-weight:bold;text-decoration:none">Set up my account</a></p>
            <p><strong>After setup:</strong> sign in at MAGIREM AI using your <strong>MinSU institutional email</strong> and the password you created. Do not use your personal Gmail as the sign-in email. There is no separate password-change page; to change or reset your password later, choose “Forgot password?”; the reset link is sent only to your verified personal Gmail.</p>
            <p>This invitation expires and can be used once. If you were not expecting it, do not forward it; contact the designated Guidance Office reviewer.</p>
        @elseif($kind === 'verification_code')
            <p>Use this one-time code to verify the notification email for your account:</p>
            <p style="padding:14px;border-radius:10px;background:#E6F1EB;color:#103F32;font-size:26px;font-weight:bold;letter-spacing:.22em;text-align:center">{{ $code }}</p>
            <p>This code expires in 30 minutes. If you did not request it, you can ignore this email.</p>
        @elseif($kind === 'approved')
            <p>Your registration has been approved. You may now sign in using your MinSU institutional email and password.</p>
        @elseif($kind === 'needs_information')
            <p>More information is needed to continue reviewing your registration. Please sign in to MAGIREM AI and check your account status page.</p>
        @elseif($kind === 'rejected')
            <p>Your registration review is complete. Please sign in to MAGIREM AI to view the status and next steps. Sensitive review notes are not sent by email.</p>
        @elseif($kind === 'suspended')
            <p>Your account access has been changed. Please contact the MAGIREM AI administrator through the approved institutional support channel.</p>
        @else
            <p>Your registration was received and is awaiting administrator review.</p>
        @endif
        <p style="margin-top:24px;color:#71837A;font-size:12px">This message does not contain wellness, journal, or crisis information.</p>
    </main>
</body>
</html>
