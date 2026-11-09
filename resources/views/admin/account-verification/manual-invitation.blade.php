<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer">
    <title>One-time in-person invitation · MAGIREM AI</title>
    <style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:#f8faf7;color:#263a33;font-family:Inter,"Segoe UI",sans-serif}.panel{width:min(650px,100%);padding:clamp(22px,5vw,38px);border:1px solid #dce8e1;border-radius:20px;background:white;box-shadow:0 18px 55px rgba(16,63,50,.1)}.brand{width:140px;margin:0 auto 18px}.brand img{width:100%;height:auto;aspect-ratio:1229/1171;object-fit:contain}.eyebrow{color:#236b55;font-size:10px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}.panel h1{margin:8px 0;color:#103f32;font-size:clamp(25px,6vw,34px)}.copy{color:#71837a;font-size:13px;line-height:1.65}.secret{padding:15px;border:1px solid #f4c84e;border-radius:12px;background:#fff9e7;color:#103f32;font-family:ui-monospace,Consolas,monospace;font-size:14px;overflow-wrap:anywhere}.url{margin-top:10px;padding:12px;border:1px solid #dce8e1;border-radius:10px;background:#fbfdfb;color:#236b55;font-size:12px;overflow-wrap:anywhere}.warning{padding:12px;border-radius:10px;background:#fff3ec;color:#8a4b25;font-size:12px;line-height:1.6}.button{margin-top:16px;min-height:42px;padding:0 14px;border:0;border-radius:10px;background:#103f32;color:white;font:inherit;font-weight:700;cursor:pointer}@media print{body{background:#fff}.panel{box-shadow:none}}</style>
</head>
<body>
<main class="panel">
    <div class="brand"><x-brand-logo variant="full" /></div>
    <span class="eyebrow">One-time in-person handoff</span>
    <h1>Invitation for {{ $applicant->name }}</h1>
    <p class="copy">This bearer invitation is shown once. Confirm the recipient’s identity face-to-face before handing it over. Do not email, message, photograph, or place this page in shared notes. It expires {{ $expiresAt->format('M j, Y g:i A') }}. A successful password setup does not complete onboarding until the owner verifies their personal Gmail.</p>
    <p class="secret" id="handoff-token">{{ $handoffToken }}</p>
    <p class="copy">If handing over the activation URL, the invitation token is in its browser fragment and will not be sent in the web request:</p>
    <p class="url" id="activation-url">{{ $activationUrl }}</p>
    <div class="warning">Do not record this token in the applicant audit note or email log. If it is exposed or lost, issue a new invitation; the previous token is automatically revoked.</div>
    <button class="button" type="button" onclick="window.print()">Print secure handoff copy</button>
</main>
</body>
</html>
