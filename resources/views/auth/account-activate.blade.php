<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Set up your account · MAGIREM AI</title>
    <style>
        *{box-sizing:border-box}html,body{width:100%;min-width:0}body{margin:0;min-height:100vh;display:grid;grid-template-columns:minmax(0,1fr);place-items:center;padding:20px;background:radial-gradient(circle at 92% 4%,rgba(230,241,235,.95),transparent 28rem),#f8faf7;color:#263a33;font-family:Inter,"Segoe UI",sans-serif}.panel{width:min(500px,100%);min-width:0;padding:clamp(22px,6vw,40px);border:1px solid #dce8e1;border-radius:22px;background:#fff;box-shadow:0 22px 70px rgba(16,63,50,.1)}.brand{margin:0 0 18px;width:104px}.brand img{display:block;width:100%;max-width:100%;height:auto;aspect-ratio:auto;object-fit:contain;border-radius:8px}.eyebrow{color:#236b55;font-size:10px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}.panel h1{margin:8px 0;color:#103f32;font-size:clamp(27px,7vw,35px);letter-spacing:-.04em;overflow-wrap:anywhere}.intro{margin:0 0 20px;color:#71837a;font-size:14px;line-height:1.65}.field{margin:15px 0}.field label{display:block;margin:0 0 7px;color:#314a40;font-size:12px;font-weight:750}.field input{width:100%;height:48px;padding:0 13px;border:1px solid #d5e2da;border-radius:11px;background:#fbfdfb;color:#263a33;font:inherit}.field input:focus{outline:3px solid rgba(35,107,85,.14);border-color:#236b55}.help{margin:5px 0 0;color:#71837a;font-size:11px;line-height:1.5}.button{width:100%;min-height:48px;margin-top:8px;border:0;border-radius:11px;background:#103f32;color:#fff;font:inherit;font-weight:750;cursor:pointer}.button:hover{background:#236b55}.button:focus-visible,a:focus-visible{outline:3px solid #f4c84e;outline-offset:3px}.note,.flash,.errors{margin:0 0 15px;padding:12px 14px;border-radius:11px;font-size:12px;line-height:1.55;overflow-wrap:anywhere}.note{background:#edf5ef;color:#426154}.flash{border:1px solid #c9e6d5;background:#f0faf4;color:#216e55}.errors{border:1px solid #efc8c3;background:#fff5f3;color:#a8433d}.errors ul{margin:5px 0 0;padding-left:18px}.back{display:inline-block;margin-top:18px;color:#236b55;font-size:13px;font-weight:750;text-decoration:none}.back:hover{text-decoration:underline}
        @media(max-width:640px){body{padding:14px}.panel{width:calc(100vw - 28px);max-width:calc(100vw - 28px);padding:22px 18px}.panel h1{font-size:clamp(24px,8vw,30px)}.intro{overflow-wrap:anywhere}}
        @media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;animation:none!important;transition:none!important}}
    </style>
</head>
<body>
<main class="panel">
    <div class="brand"><x-brand-logo variant="full" class="auth-logo" /></div>
    <span class="eyebrow">Secure account invitation</span>
    <h1>Set up your MAGIREM AI account</h1>
    <p class="intro">Create a strong password using the one-time invitation from your authorized Guidance Office reviewer. Opening the invitation does not activate access; your account must already be approved, and personal Gmail verification is still required.</p>

    @if(session('success'))<div class="flash" role="status">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="note" role="status">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="errors" role="alert"><strong>We could not complete setup.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="note" id="invite-help">Use the secure invitation link you received. The invitation token is sent in the form body only; it is removed from the address bar before submission.</div>
    <form method="POST" action="{{ route('account.activate.accept') }}" id="activation-form" autocomplete="on">
        @csrf
        <input type="hidden" name="token" id="invitation-token" value="">
        <div class="field"><label for="password">Choose your password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required><p class="help">At least 12 characters with uppercase, lowercase, a number, and a symbol.</p></div>
        <div class="field"><label for="password_confirmation">Confirm your password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="128" required></div>
        <button class="button" type="submit">Set password and continue</button>
    </form>
    <a class="back" href="{{ route('login') }}">Return to sign in</a>
</main>
<script>
    (function () {
        const tokenField = document.getElementById('invitation-token');
        const fragment = new URLSearchParams(window.location.hash.slice(1));
        const token = fragment.get('invitation');
        if (token && /^[a-f0-9]{64}$/i.test(token)) {
            tokenField.value = token.toLowerCase();
            window.history.replaceState(null, document.title, window.location.pathname + window.location.search);
            document.getElementById('invite-help').textContent = 'Invitation loaded. Choose your own strong password to continue.';
        } else {
            document.getElementById('invite-help').textContent = 'Open the one-time invitation link from the authorized Guidance Office reviewer. If it expired or was already used, contact that reviewer for a new invitation.';
            document.getElementById('password').disabled = true;
            document.getElementById('password_confirmation').disabled = true;
            document.querySelector('.button').disabled = true;
        }
        document.getElementById('activation-form').addEventListener('submit', function (event) {
            if (!tokenField.value) {
                event.preventDefault();
                document.getElementById('invite-help').focus();
            }
        });
    })();
</script>
</body>
</html>
