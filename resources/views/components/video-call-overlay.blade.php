@once
<style>
.mc-video-modal{
    position:fixed;
    inset:0;
    z-index:10050;
    display:none;
    align-items:center;
    justify-content:center;
    padding:22px;
    background:rgba(9,31,24,.76);
    backdrop-filter:blur(12px);
}
.mc-video-modal.open{display:flex}
.mc-video-card{
    width:min(1040px,96vw);
    border:1px solid rgba(255,255,255,.12);
    border-radius:24px;
    overflow:hidden;
    background:#0B211A;
    box-shadow:0 28px 80px rgba(0,0,0,.32);
}
.mc-video-topbar{
    height:66px;
    padding:0 18px 0 20px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    background:#103F32;
    color:white;
}
.mc-video-title{font-size:14px;font-weight:800;letter-spacing:-.01em}
.mc-video-status{margin-top:3px;font-size:11px;color:rgba(255,255,255,.68)}
.mc-video-stage{
    min-height:540px;
    display:grid;
    grid-template-columns:1fr;
    position:relative;
    background:#071712;
}
.mc-video-remote{
    width:100%;
    height:100%;
    min-height:540px;
    object-fit:cover;
    background:#071712;
}
.mc-video-local{
    position:absolute;
    right:20px;
    bottom:20px;
    width:min(240px,28%);
    aspect-ratio:16/10;
    object-fit:cover;
    border-radius:16px;
    background:#16372D;
    border:2px solid rgba(255,255,255,.22);
    box-shadow:0 12px 35px rgba(0,0,0,.25);
}
.mc-video-waiting{
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    pointer-events:none;
    color:white;
    text-align:center;
    padding:30px;
}
.mc-video-waiting-inner{
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:12px;
}
.mc-video-pulse{
    width:74px;height:74px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    background:#E6F1EB;color:#103F32;
    animation:mcPulse 1.8s ease-in-out infinite;
}
.mc-video-pulse svg{width:30px;height:30px}
@keyframes mcPulse{
    0%,100%{box-shadow:0 0 0 0 rgba(230,241,235,.18)}
    50%{box-shadow:0 0 0 16px rgba(230,241,235,0)}
}
.mc-video-controls{
    min-height:82px;
    padding:14px 18px;
    display:flex;
    justify-content:center;
    align-items:center;
    gap:10px;
    background:#103F32;
}
.mc-video-control{
    width:48px;height:48px;border-radius:14px;border:1px solid rgba(255,255,255,.15);
    background:rgba(255,255,255,.09);color:white;display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:.18s ease;
}
.mc-video-control:hover{background:rgba(255,255,255,.16)}
.mc-video-control.off{background:#EFF4F1;color:#103F32}
.mc-video-control.end{width:auto;padding:0 18px;background:#B94A48;border-color:#B94A48;font-weight:800;font-size:12px;gap:8px}
.mc-video-control svg{width:20px;height:20px}
.mc-incoming-call{
    position:fixed;
    right:24px;
    bottom:24px;
    z-index:10060;
    width:min(360px,calc(100vw - 48px));
    padding:16px;
    border:1px solid #DCE7E1;
    border-radius:18px;
    background:white;
    box-shadow:0 24px 65px rgba(16,63,50,.2);
    display:none;
}
.mc-incoming-call.open{display:block}
.mc-incoming-row{display:flex;align-items:center;gap:12px}
.mc-incoming-avatar{
    width:46px;height:46px;border-radius:14px;background:#103F32;color:white;
    display:flex;align-items:center;justify-content:center;font-weight:800;
}
.mc-incoming-copy{flex:1;min-width:0}
.mc-incoming-copy strong{display:block;color:#103F32;font-size:13px}
.mc-incoming-copy span{display:block;color:#6F8179;font-size:11px;margin-top:3px}
.mc-incoming-actions{display:flex;gap:8px;margin-top:13px}
.mc-incoming-btn{
    flex:1;height:40px;border-radius:11px;border:1px solid #DCE7E1;background:white;
    color:#103F32;font:inherit;font-size:11px;font-weight:800;cursor:pointer;
}
.mc-incoming-btn.accept{background:#103F32;color:white;border-color:#103F32}
@media(max-width:700px){
    .mc-video-modal{padding:0}
    .mc-video-card{width:100vw;height:100dvh;border-radius:0}
    .mc-video-stage,.mc-video-remote{min-height:calc(100dvh - 148px)}
    .mc-video-local{width:34%;right:12px;bottom:12px}
}
</style>

<div class="mc-video-modal" id="mcVideoModal" aria-hidden="true">
    <div class="mc-video-card">
        <div class="mc-video-topbar">
            <div>
                <div class="mc-video-title" id="mcVideoTitle">Private video session</div>
                <div class="mc-video-status" id="mcVideoStatus">Preparing secure connection…</div>
            </div>
        </div>

        <div class="mc-video-stage">
            <video class="mc-video-remote" id="mcRemoteVideo" autoplay playsinline></video>
            <div class="mc-video-waiting" id="mcVideoWaiting">
                <div class="mc-video-waiting-inner">
                    <div class="mc-video-pulse">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 10l4.6-2.3A1 1 0 0121 8.6v6.8a1 1 0 01-1.4.9L15 14M5 6h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/>
                        </svg>
                    </div>
                    <strong id="mcVideoWaitingTitle">Calling…</strong>
                    <span id="mcVideoWaitingCopy">Waiting for the other participant to join.</span>
                </div>
            </div>
            <video class="mc-video-local" id="mcLocalVideo" autoplay muted playsinline></video>
        </div>

        <div class="mc-video-controls">
            <button type="button" class="mc-video-control" id="mcToggleMic" aria-label="Mute microphone" title="Mute microphone">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 2a3 3 0 00-3 3v7a3 3 0 006 0V5a3 3 0 00-3-3zm-7 10a7 7 0 0014 0M12 19v3m-4 0h8"/>
                </svg>
            </button>
            <button type="button" class="mc-video-control" id="mcToggleCamera" aria-label="Turn camera off" title="Turn camera off">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 10l4.6-2.3A1 1 0 0121 8.6v6.8a1 1 0 01-1.4.9L15 14M5 6h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/>
                </svg>
            </button>
            <button type="button" class="mc-video-control end" id="mcEndCall">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M4 15.5c4.8-4.6 11.2-4.6 16 0M7 12l-3 3.5M17 12l3 3.5"/>
                </svg>
                End call
            </button>
        </div>
    </div>
</div>

<div class="mc-incoming-call" id="mcIncomingCall">
    <div class="mc-incoming-row">
        <div class="mc-incoming-avatar">M</div>
        <div class="mc-incoming-copy">
            <strong>Incoming counselor video call</strong>
            <span>A private counseling video session is ready to connect.</span>
        </div>
    </div>
    <div class="mc-incoming-actions">
        <button type="button" class="mc-incoming-btn" id="mcDeclineCall">Decline</button>
        <button type="button" class="mc-incoming-btn accept" id="mcAcceptCall">Accept</button>
    </div>
</div>

<script>
window.MinSuansVideoCallConfig = {
    csrf: @json(csrf_token()),
    currentUserId: {{ auth()->id() }},
    role: @json((string) auth()->user()->role),
    baseUrl: @json(url('/api/video-calls'))
};
</script>
<script src="{{ asset('js/minsuanscore-video-call.js') }}"></script>
@endonce
