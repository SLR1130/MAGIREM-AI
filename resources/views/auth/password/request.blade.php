<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="referrer" content="no-referrer"><meta name="robots" content="noindex,nofollow">
    <title>Reset password · MAGIREM AI</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 90% 5%,rgba(230,241,235,.95),transparent 28rem),#f8faf7;color:#263a33;font-family:Inter,"Segoe UI",sans-serif}.panel{width:min(480px,100%);padding:clamp(24px,6vw,40px);border:1px solid #dce8e1;border-radius:22px;background:#fff;box-shadow:0 22px 70px rgba(16,63,50,.1)}.brand{margin-bottom:18px}.brand .auth-logo{width:104px;height:auto;aspect-ratio:auto;border-radius:8px;box-shadow:0 8px 20px rgba(16,63,50,.12)}.panel h1{margin:0 0 8px;color:#103f32;font-size:clamp(26px,7vw,34px);letter-spacing:-.04em}.intro{margin:0 0 22px;color:#71837a;font-size:14px;line-height:1.65}.field{margin:16px 0}.field label{display:block;margin:0 0 7px;color:#314a40;font-size:12px;font-weight:750}.field input{width:100%;height:48px;padding:0 13px;border:1px solid #d5e2da;border-radius:11px;background:#fbfdfb;color:#263a33;font:inherit;outline:none}.field input:focus{border-color:#236b55;box-shadow:0 0 0 3px rgba(35,107,85,.12)}.button{width:100%;min-height:48px;margin-top:8px;border:0;border-radius:11px;background:#103f32;color:white;font:inherit;font-weight:750;cursor:pointer}.button:hover{background:#236b55}.button:focus-visible,a:focus-visible{outline:3px solid #f4c84e;outline-offset:3px}.note,.flash,.errors{padding:12px 14px;border-radius:11px;font-size:12px;line-height:1.55}.note{margin:16px 0;background:#edf5ef;color:#426154}.flash{margin:0 0 16px;border:1px solid #c9e6d5;background:#f0faf4;color:#216e55}.errors{margin:0 0 16px;border:1px solid #efc8c3;background:#fff5f3;color:#a8433d}.errors ul{margin:5px 0 0;padding-left:18px}.back{display:inline-block;margin-top:20px;color:#236b55;font-size:13px;font-weight:750;text-decoration:none}.back:hover{text-decoration:underline}
    </style>
</head>
<body>
<main class="panel">
    <div class="brand"><x-brand-logo variant="full" class="auth-logo" /></div>
    <h1>Reset your password</h1>
    <p class="intro">Enter the MinSU institutional email you use to sign in. If your account is eligible, a secure link will be sent only to its verified personal Gmail.</p>
    @if(session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="errors" role="alert"><strong>Please check the address.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('password.email') }}" autocomplete="on">
        @csrf
        <div class="field"><label for="email">MinSU institutional email</label><input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="name@minsu.edu.ph" autocomplete="username" required maxlength="255"></div>
        <div class="note">For privacy, this page will not confirm whether an account exists or display its linked Gmail address. Pending accounts remain pending after recovery.</div>
        <button class="button" type="submit">Send reset link</button>
    </form>
    <a class="back" href="{{ route('login') }}">← Back to sign in</a>
</main>
</body>
</html>
