<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="referrer" content="no-referrer"><meta name="robots" content="noindex,nofollow">
    <title>Choose a new password · MAGIREM AI</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 90% 5%,rgba(230,241,235,.95),transparent 28rem),#f8faf7;color:#263a33;font-family:Inter,"Segoe UI",sans-serif}.panel{width:min(480px,100%);padding:clamp(24px,6vw,40px);border:1px solid #dce8e1;border-radius:22px;background:#fff;box-shadow:0 22px 70px rgba(16,63,50,.1)}.brand{margin-bottom:18px}.brand .auth-logo{width:104px;height:auto;aspect-ratio:auto;border-radius:8px;box-shadow:0 8px 20px rgba(16,63,50,.12)}.panel h1{margin:0 0 8px;color:#103f32;font-size:clamp(26px,7vw,34px);letter-spacing:-.04em}.intro{margin:0 0 22px;color:#71837a;font-size:14px;line-height:1.65}.field{margin:16px 0}.field label{display:block;margin:0 0 7px;color:#314a40;font-size:12px;font-weight:750}.field input{width:100%;height:48px;padding:0 13px;border:1px solid #d5e2da;border-radius:11px;background:#fbfdfb;color:#263a33;font:inherit;outline:none}.field input:focus{border-color:#236b55;box-shadow:0 0 0 3px rgba(35,107,85,.12)}.button{width:100%;min-height:48px;margin-top:8px;border:0;border-radius:11px;background:#103f32;color:white;font:inherit;font-weight:750;cursor:pointer}.button:hover{background:#236b55}.button:focus-visible,a:focus-visible{outline:3px solid #f4c84e;outline-offset:3px}.note,.flash,.errors{padding:12px 14px;border-radius:11px;font-size:12px;line-height:1.55}.note{margin:16px 0;background:#edf5ef;color:#426154}.flash{margin:0 0 16px;border:1px solid #c9e6d5;background:#f0faf4;color:#216e55}.errors{margin:0 0 16px;border:1px solid #efc8c3;border-radius:11px;background:#fff5f3;color:#a8433d}.errors ul{margin:5px 0 0;padding-left:18px}.back{display:inline-block;margin-top:20px;color:#236b55;font-size:13px;font-weight:750;text-decoration:none}.back:hover{text-decoration:underline}[hidden]{display:none!important}
    </style>
</head>
<body>
<main class="panel">
    <div class="brand"><x-brand-logo variant="full" class="auth-logo" /></div>
    <h1>Choose a new password</h1>
    <p class="intro">Create a strong password for your MinSU institutional sign-in. Reset links expire after 30 minutes and can be used once.</p>
    @if($errors->any())<div class="errors" role="alert"><strong>We could not reset the password.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    <div class="note" id="link-status" role="status">Checking your secure reset link…</div>
    <form id="reset-form" method="POST" action="{{ route('password.update') }}" autocomplete="off" hidden>
        @csrf
        <input type="hidden" name="token" id="reset-token">
        <input type="hidden" name="email" id="reset-email">
        <div class="field"><label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>@error('password')<small>{{ $message }}</small>@enderror</div>
        <div class="field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required></div>
        <div class="note">Use at least 12 characters with upper- and lowercase letters, a number, and a symbol. Account approval and activation status will not change.</div>
        <button class="button" type="submit">Update password</button>
    </form>
    <a class="back" href="{{ route('login') }}">← Back to sign in</a>
</main>
<script>
(() => {
    const status = document.getElementById('link-status');
    const form = document.getElementById('reset-form');
    const fragment = new URLSearchParams(window.location.hash.slice(1));
    const token = fragment.get('token');
    const email = fragment.get('email');
    // Fragments are not sent to the server. Remove the bearer token from the
    // visible URL/history as soon as it has been copied into the POST form.
    window.history.replaceState(null, document.title, window.location.pathname + window.location.search);
    if (token && email && token.length >= 20 && /^[^@\s]+@minsu\.edu\.ph$/i.test(email)) {
        document.getElementById('reset-token').value = token;
        document.getElementById('reset-email').value = email;
        form.hidden = false;
        status.textContent = 'Secure link ready. Choose a new password below.';
    } else {
        status.textContent = 'This reset link is missing or invalid. Request a new link to continue.';
    }
})();
</script>
</body>
</html>
