@extends('layouts.app')

@section('title', 'MAGI · MAGIREM AI')

@section('content')
<style>
    :root {
        --mc-forest:#103f32;
        --mc-forest-2:#174f3f;
        --mc-emerald:#236b55;
        --mc-emerald-soft:#4d9d7b;
        --mc-mint:#e6f1eb;
        --mc-mint-2:#d8ebe2;
        --mc-ivory:#f8faf7;
        --mc-paper:#fffef9;
        --mc-white:#fff;
        --mc-gold:#f4c84e;
        --mc-text:#263a33;
        --mc-muted:#6d8279;
        --mc-line:rgba(16,63,50,.11);
        --mc-line-strong:rgba(16,63,50,.18);
        --mc-shadow-sm:0 12px 32px rgba(16,63,50,.07);
        --mc-shadow-md:0 26px 68px rgba(16,63,50,.12);
        --mc-ease:cubic-bezier(.22,1,.36,1);
        --text-muted:#82948c;
    }


    /* ======================================================================
       IMMERSIVE CHAT SHELL V3
       AI Chat owns its own header. On this route the global application
       header is removed to avoid duplicated profile/notification controls
       and the clipped page-title artifact beside the sidebar.
       ====================================================================== */
    body.student-header-layout:has(.msc-chat) {
        min-height: 100dvh !important;
        height: 100dvh !important;
        overflow: hidden !important;
    }

    body.student-header-layout:has(.msc-chat) .app-main {
        min-height: 100dvh !important;
        height: 100dvh !important;
        padding: 0 !important;
        overflow: hidden !important;
    }

    body:has(.msc-chat) .content-wrapper {
        max-width: none !important;
        width: 100% !important;
        height: 100dvh !important;
        min-height: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: hidden !important;
    }

    /* Keep the global left sidebar as the only account/profile surface. */
    body:has(.msc-chat) .app-sidebar {
        min-height: 100dvh !important;
        height: 100dvh !important;
        overflow: hidden !important;
    }

    /* Prevent labels inside the persistent app sidebar from spilling into
       the chat canvas on narrower desktop widths. */
    body:has(.msc-chat) .app-sidebar a,
    body:has(.msc-chat) .app-sidebar button {
        min-width: 0;
        max-width: 100%;
    }

    body:has(.msc-chat) .app-sidebar a > span:last-child,
    body:has(.msc-chat) .app-sidebar button > span:last-child {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    body:has(.msc-chat) .content-wrapper {
        max-width:none !important;
        width:100% !important;
        padding:0 !important;
        margin:0 !important;
    }
    body:has(.msc-chat) .app-main { overflow:hidden; }

    .msc-chat,
    .msc-chat *,
    .msc-chat *::before,
    .msc-chat *::after { box-sizing:border-box; }

    .chat-wrapper.msc-chat {
        display:grid;
        grid-template-columns:286px minmax(0,1fr);
        width:100%;
        height:100dvh;
        min-height:620px;
        overflow:hidden;
        background:
            radial-gradient(circle at 86% 0%,rgba(216,235,226,.75),transparent 30rem),
            var(--mc-ivory);
        color:var(--mc-text);
        font-family:Inter,"Plus Jakarta Sans",ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    }

    /* ========== CONVERSATION RAIL ========== */
    .conversation-sidebar {
        min-width:0;
        display:flex;
        flex-direction:column;
        overflow:hidden;
        border-right:1px solid var(--mc-line);
        background:rgba(255,255,255,.82);
        backdrop-filter:blur(18px);
        -webkit-backdrop-filter:blur(18px);
    }

    .sb-profile {
        display:flex;
        align-items:center;
        gap:11px;
        min-height:72px;
        padding:14px 16px;
        border-bottom:1px solid var(--mc-line);
    }
    .sb-avatar {
        width:38px;height:38px;flex:0 0 auto;
        display:grid;place-items:center;
        border-radius:13px;
        background:var(--mc-forest);
        color:#fff;
        font-size:13px;font-weight:800;
        box-shadow:0 8px 20px rgba(16,63,50,.14);
    }
    .sb-user-info { min-width:0; }
    .sb-user-name { margin:0;color:var(--mc-forest);font-size:12px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .sb-user-meta { margin:3px 0 0;color:var(--mc-muted);font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }

    .sb-accordion { flex:1;overflow:auto;padding:8px;scrollbar-width:thin;scrollbar-color:rgba(35,107,85,.22) transparent; }
    .sb-acc-item { margin-bottom:4px;border:1px solid transparent;border-radius:14px;overflow:hidden;transition:all .2s var(--mc-ease); }
    .sb-acc-item.open { border-color:var(--mc-line);background:rgba(248,250,247,.86); }
    .sb-acc-trigger {
        width:100%;min-height:44px;display:flex;align-items:center;gap:9px;padding:0 10px;
        border:0;background:transparent;color:#61766d;text-align:left;font:inherit;font-size:11px;font-weight:750;cursor:pointer;
        border-radius:12px;transition:all .2s var(--mc-ease);
    }
    .sb-acc-trigger:hover { background:var(--mc-mint);color:var(--mc-forest); }
    .sb-acc-item.open .sb-acc-trigger { color:var(--mc-forest); }
    .sb-acc-icon { width:30px;height:30px;display:grid;place-items:center;flex:0 0 auto;border-radius:10px;background:#fff;color:var(--mc-emerald);box-shadow:0 6px 14px rgba(16,63,50,.05); }
    .sb-acc-icon svg { width:15px;height:15px; }
    .sb-acc-label { flex:1; }
    .sb-acc-preview { padding:2px 7px;border-radius:999px;background:var(--mc-mint);color:var(--mc-emerald);font-size:9px;font-weight:800; }
    .sb-acc-chevron { width:13px;height:13px;color:#94a49d;transition:transform .22s var(--mc-ease); }
    .sb-acc-item.open .sb-acc-chevron { transform:rotate(180deg); }
    .sb-acc-body { max-height:0;overflow:hidden;transition:max-height .32s var(--mc-ease); }
    .sb-acc-item.open .sb-acc-body { max-height:620px; }

    .sb-wellness-inner { padding:2px 10px 14px; }
    .sb-score-row { display:flex;align-items:baseline;gap:3px;margin:9px 0 10px; }
    .sb-score-num { color:var(--mc-forest);font-size:26px;font-weight:800;letter-spacing:-.04em; }
    .sb-score-denom { color:var(--mc-muted);font-size:10px; }
    .sb-score-badge { margin-left:auto;padding:3px 7px;border-radius:999px;background:var(--mc-mint);color:var(--mc-emerald);font-size:8px;font-weight:850;text-transform:uppercase;letter-spacing:.06em; }
    .sb-bar-track { position:relative;height:6px;margin-bottom:5px;border-radius:999px;background:#e8f0ec; }
    .sb-bar-fill { height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--mc-emerald),var(--mc-gold));transition:width .7s var(--mc-ease),background .25s ease; }
    .sb-bar-marker { position:absolute;top:-2px;width:1px;height:10px;background:rgba(16,63,50,.16); }
    .sb-bar-marker-red { background:rgba(181,68,59,.34); }
    .sb-bar-scale { display:flex;justify-content:space-between;margin-bottom:8px;color:#95a69e;font-size:8px; }
    .sb-wellness-desc,.sb-wellness-note { margin:0 0 7px;color:var(--mc-muted);font-size:9.5px;line-height:1.55; }
    .sb-wellness-link { color:var(--mc-emerald)!important;text-decoration:none;font-size:9.5px;font-weight:800; }

    .sb-conv-inner { padding:3px 4px 10px; }
    .new-chat-btn {
        width:100%;min-height:39px;display:flex;align-items:center;justify-content:center;gap:7px;margin-bottom:8px;
        border:0;border-radius:11px;background:var(--mc-forest);color:#fff;font:inherit;font-size:10px;font-weight:800;cursor:pointer;
        box-shadow:0 8px 20px rgba(16,63,50,.13);transition:all .2s var(--mc-ease);
    }
    .new-chat-btn:hover { transform:translateY(-1px);box-shadow:0 12px 26px rgba(16,63,50,.18); }
    .new-chat-btn svg { width:14px;height:14px; }

    .conversations-list { display:flex;flex-direction:column;gap:3px; }
    .conversation-item { display:flex;align-items:center;gap:5px;padding:8px;border-radius:11px;cursor:pointer;border:1px solid transparent;transition:all .2s var(--mc-ease); }
    .conversation-item:hover { background:var(--mc-mint); }
    .conversation-item.active { background:#edf6f1;border-color:rgba(35,107,85,.14); }
    .conversation-content { min-width:0;flex:1; }
    .conversation-title { overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--mc-forest);font-size:9.5px;font-weight:750; }
    .conversation-time { margin-top:2px;color:#95a59e;font-size:8px; }
    .conversation-delete-btn { width:26px;height:26px;flex:0 0 auto;display:grid;place-items:center;border:0;border-radius:8px;background:transparent;color:#9aaba4;cursor:pointer;opacity:.6;transition:all .2s ease; }
    .conversation-delete-btn:hover { opacity:1;background:#fff1ef;color:#a64a43; }
    .conversation-delete-btn svg { width:13px;height:13px; }

    .sb-links-inner { display:flex;flex-direction:column;gap:2px;padding:2px 4px 10px; }
    .sb-link { display:flex;align-items:center;gap:8px;min-height:36px;padding:0 9px;border-radius:10px;color:#647970!important;text-decoration:none;font-size:9.5px;font-weight:700;transition:all .2s ease; }
    .sb-link:hover { background:var(--mc-mint);color:var(--mc-forest)!important; }
    .sb-link svg { width:14px;height:14px;color:var(--mc-emerald); }

    /* ========== MAIN CHAT ========== */
    .chat-main { min-width:0;display:flex;flex-direction:column;overflow:hidden;background:rgba(248,250,247,.68); }

    .chat-header {
        min-height:72px;display:flex;align-items:center;justify-content:space-between;gap:20px;padding:12px 22px;
        border-bottom:1px solid var(--mc-line);background:rgba(255,255,255,.86);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);
    }
    .header-left,.header-right { display:flex;align-items:center;gap:11px; }
    .chat-header > .header-left > .magi-avatar { margin-inline:1px; }
    .ai-back-link { display:inline-flex;align-items:center;gap:5px;color:#597067;text-decoration:none;font-size:10px;font-weight:780;white-space:nowrap; }
    .ai-back-link:hover,.ai-back-link:focus-visible { color:var(--mc-forest);outline:none; }
    .ai-back-link svg { width:14px;height:14px; }
    .header-title h1 { margin:0;color:var(--mc-forest);font-size:14px;line-height:1.2;font-weight:820;letter-spacing:-.02em; }
    .header-title p { margin:4px 0 0;color:var(--mc-muted);font-size:9.5px; }
    .status-indicator { display:flex;align-items:center;gap:6px;color:#6e8178;font-size:9px;font-weight:750; }
    .status-dot { width:7px;height:7px;border-radius:50%;background:#38a169;box-shadow:0 0 0 4px rgba(56,161,105,.1); }
    .end-conversation-btn { display:inline-flex;align-items:center;gap:6px;min-height:36px;padding:0 11px;border:1px solid var(--mc-line);border-radius:10px;background:#fff;color:#65786f;font:inherit;font-size:9.5px;font-weight:750;cursor:pointer;transition:all .2s ease; }
    .end-conversation-btn:hover { background:#fff6f4;border-color:rgba(169,74,67,.18);color:#9d4943; }
    .end-conversation-btn svg { width:14px;height:14px; }

    .messages-outer { flex:1;min-height:0;overflow-y:auto;overscroll-behavior:contain;scrollbar-width:thin;scrollbar-color:rgba(35,107,85,.18) transparent; }
    .messages-container { width:min(860px,calc(100% - 44px));margin:0 auto;padding:26px 0 34px; }

    .welcome-card {
        position:relative;overflow:hidden;margin:14px auto 22px;padding:27px 28px;border:1px solid var(--mc-line);border-radius:20px;
        background:linear-gradient(145deg,#fff,#f0f7f3);box-shadow:var(--mc-shadow-sm);text-align:center;
    }
    .welcome-card::after { content:"";position:absolute;width:190px;height:190px;border:34px solid rgba(35,107,85,.06);border-radius:50%;right:-88px;top:-100px; }
    .welcome-card > .welcome-icon { display:grid;width:60px;height:60px;margin:0 auto 14px;place-items:center;background:transparent; }
    .welcome-card h2 { position:relative;z-index:1;margin:0;color:var(--mc-forest);font-size:20px;letter-spacing:-.03em; }
    .welcome-card p { position:relative;z-index:1;max-width:580px;margin:9px auto 0;color:var(--mc-muted);font-size:11px;line-height:1.7; }

    .quick-actions { display:flex;flex-wrap:wrap;justify-content:center;gap:7px;margin:0 auto 22px; }
    .quick-btn { min-height:36px;padding:0 11px;display:inline-flex;align-items:center;gap:6px;border:1px solid var(--mc-line);border-radius:999px;background:#fff;color:#60756b;font:inherit;font-size:9.5px;font-weight:700;cursor:pointer;transition:all .2s var(--mc-ease); }
    .quick-btn:hover { transform:translateY(-2px);background:var(--mc-mint);color:var(--mc-forest);border-color:rgba(35,107,85,.18); }
    .quick-btn svg { width:13px;height:13px;color:var(--mc-emerald); }

    .message { display:flex;gap:10px;margin:0 0 18px;align-items:flex-start; }
    .message.user { flex-direction:row-reverse; }
    .message.is-new { animation:mc-message-in .32s var(--mc-ease) both; }
    .message-avatar {
        width:31px;height:31px;flex:0 0 auto;display:grid;place-items:center;border-radius:11px;background:var(--mc-mint);color:var(--mc-emerald);font-size:10px;font-weight:850;
        box-shadow:0 6px 16px rgba(16,63,50,.06);
    }
    .message.ai .message-avatar { border-radius:50%;background:var(--mc-forest);color:#fff; }
    .message.user .message-avatar { background:#e8efe9;color:var(--mc-forest); }
    .message-content { min-width:0;max-width:min(78%,700px); }
    .message.user .message-content { display:flex;flex-direction:column;align-items:flex-end; }
    .message-bubble { padding:12px 14px;border:1px solid var(--mc-line);border-radius:15px 15px 15px 5px;background:#fff;color:#33483f;font-size:11.25px;line-height:1.72;box-shadow:0 8px 22px rgba(16,63,50,.045);word-wrap:break-word; }    .message.ai .message-bubble { overflow-wrap:anywhere; word-break:normal; }
    .message.ai .message-bubble p { margin:0 0 10px; }
    .message.ai .message-bubble p:last-child { margin-bottom:0; }
    .message.ai .message-bubble .ai-heading { margin:2px 0 8px;color:var(--mc-forest);font-size:1em;line-height:1.45;font-weight:800; }
    .message.ai .message-bubble ol,.message.ai .message-bubble ul { margin:8px 0 11px;padding-left:1.35rem; }
    .message.ai .message-bubble li { margin:5px 0;padding-left:2px; }
    .message.ai .message-bubble strong { color:inherit;font-weight:800; }
    .message.ai .message-bubble em { color:#5a7066; }
    .message.user .message-bubble { border-color:transparent;border-radius:15px 15px 5px 15px;background:var(--mc-forest);color:#fff;box-shadow:0 10px 26px rgba(16,63,50,.14); }
    .message-meta { display:flex;align-items:center;gap:6px;margin-top:6px;color:#91a29a;font-size:8.5px; }
    .message.user .message-meta { justify-content:flex-end; }

    .emotion-badge { display:inline-flex;align-items:center;gap:4px;padding:3px 7px;border-radius:999px;background:#edf3f0;color:#61766d;font-size:8px;font-weight:800;line-height:1;animation:mc-chip .25s var(--mc-ease) both; }
    .emotion-badge.happy,.emotion-badge.happiness,.emotion-badge.joy { background:#edf8ef;color:#3c7a4e; }
    .emotion-badge.sad,.emotion-badge.sadness { background:#eef4f8;color:#547389; }
    .emotion-badge.anxious,.emotion-badge.anxiety { background:#fff6dd;color:#936f14; }
    .emotion-badge.angry,.emotion-badge.anger { background:#fff0ed;color:#a14b44; }
    .emotion-badge.grief { background:#f0f0f7;color:#66658a; }
    .emotion-badge.neutral { background:#edf3f0;color:#63766e; }

    .typing-indicator { display:flex;align-items:center;gap:9px;margin:4px 0 18px;animation:mc-message-in .24s var(--mc-ease) both; }
    .typing-content { min-width:74px;padding:10px 13px;border:1px solid var(--mc-line);border-radius:14px 14px 14px 5px;background:#fff;box-shadow:0 8px 22px rgba(16,63,50,.045); }
    .typing-dots { display:flex;gap:4px;align-items:center;height:10px; }
    .typing-dots span,.typing-dot { width:5px;height:5px;border-radius:50%;background:var(--mc-emerald);animation:mc-dot 1.2s ease-in-out infinite; }
    .typing-dots span:nth-child(2),.typing-dot:nth-child(2){animation-delay:.15s}.typing-dots span:nth-child(3),.typing-dot:nth-child(3){animation-delay:.3s}

    /* Legacy static typing block kept for JS compatibility */
    #typingIndicator .message-bubble { background:#fff!important;border:1px solid var(--mc-line)!important; }
    #typingIndicator .typing-indicator { margin:0; }

    .input-area { flex:0 0 auto;padding:10px 18px 14px;border-top:1px solid var(--mc-line);background:rgba(248,250,247,.88);backdrop-filter:blur(16px); }
    .input-wrapper { width:min(920px,100%);margin:0 auto; }
    .input-container { display:flex;align-items:flex-end;gap:9px;padding:8px 8px 8px 14px;border:1px solid rgba(16,63,50,.15);border-radius:17px;background:#fff;box-shadow:0 12px 34px rgba(16,63,50,.07);transition:border-color .18s ease,box-shadow .18s ease,transform .18s var(--mc-ease); }
    .input-container:focus-within { border-color:rgba(35,107,85,.4);box-shadow:0 14px 38px rgba(16,63,50,.1),0 0 0 4px rgba(35,107,85,.05);transform:translateY(-1px); }
    .chat-input { flex:1;min-height:37px;max-height:150px;padding:8px 0;border:0;outline:0;resize:none;background:transparent;color:var(--mc-text);font:inherit;font-size:11px;line-height:1.6; }
    .chat-input::placeholder { color:#9aaba4; }
    .send-button { width:40px;height:40px;flex:0 0 auto;display:grid;place-items:center;border:0;border-radius:13px;background:var(--mc-forest);color:#fff;cursor:pointer;box-shadow:0 8px 20px rgba(16,63,50,.16);transition:all .2s var(--mc-ease); }
    .send-button:hover:not(:disabled) { transform:translateY(-2px) scale(1.02);box-shadow:0 12px 26px rgba(16,63,50,.2); }
    .send-button:disabled { opacity:.72;cursor:wait; }
    .send-button svg { width:17px;height:17px; }
    .input-hint { margin:6px 2px 0;text-align:center;color:#95a59e;font-size:8px; }
    .error-banner { display:flex;align-items:center;gap:8px;margin-bottom:8px;padding:9px 11px;border:1px solid rgba(169,74,67,.14);border-radius:11px;background:#fff3f1;color:#984740;font-size:9.5px; }
    .error-banner svg { width:14px;height:14px; }
    .chat-error-link { color:inherit;font-weight:700;text-decoration:underline;margin-left:auto;white-space:nowrap; }

    @keyframes mc-breathe { 0%,100%{transform:scale(1)} 50%{transform:scale(1.045)} }
    @keyframes mc-spin { to{transform:rotate(360deg)} }
    @keyframes mc-dot { 0%,60%,100%{transform:translateY(0);opacity:.35} 30%{transform:translateY(-3px);opacity:1} }
    @keyframes mc-message-in { from{opacity:0;transform:translateY(9px)} to{opacity:1;transform:none} }
    @keyframes mc-chip { from{opacity:0;transform:scale(.96)} to{opacity:1;transform:scale(1)} }
    @keyframes spin { to{transform:rotate(360deg)} }

    @media(max-width:1120px){
        .chat-wrapper.msc-chat{grid-template-columns:250px minmax(0,1fr)}
        .messages-container{width:min(820px,calc(100% - 32px))}
    }
    @media(max-width:900px){
        body:has(.msc-chat) .app-main{overflow:auto}
        .chat-wrapper.msc-chat{grid-template-columns:1fr;height:calc(100dvh - 64px)}
        .conversation-sidebar{display:none}
        .chat-header{padding-inline:16px}
        .messages-container{width:min(760px,calc(100% - 26px));padding-top:20px}
        .input-area{padding-inline:12px}
    }
    @media(max-width:560px){
        .chat-wrapper.msc-chat{height:100dvh;min-height:520px}
        .chat-header{min-height:64px;padding:9px 11px}
        .header-title h1{font-size:12px}.header-title p{font-size:8.5px}
        .end-conversation-btn{width:34px;padding:0;justify-content:center}.end-conversation-btn{font-size:0}.end-conversation-btn svg{width:15px;height:15px}
        .status-indicator span:last-child{display:none}
        .messages-container{width:calc(100% - 20px);padding-bottom:20px}
        .message-content{max-width:86%}
        .message-avatar{width:28px;height:28px}
        .message-bubble{font-size:10.5px;padding:11px 12px}
        .quick-actions{justify-content:flex-start;flex-wrap:nowrap;overflow:auto;padding-bottom:4px}
        .quick-btn{white-space:nowrap}
        .input-area{padding:8px 9px 10px}
        .input-hint{display:none}
    }

    body.dusk-mode .chat-wrapper.msc-chat {
        --mc-ivory:#0f1e19;--mc-paper:#152a23;--mc-white:#152a23;--mc-text:#edf5f1;--mc-muted:#9fb6ab;--mc-line:rgba(237,245,241,.09);--mc-line-strong:rgba(237,245,241,.15);
        background:radial-gradient(circle at 88% 0%,rgba(77,157,123,.14),transparent 26rem),#0f1e19;
    }
    body.dusk-mode .conversation-sidebar,body.dusk-mode .chat-header{background:rgba(21,42,35,.9);}
    body.dusk-mode .chat-main,body.dusk-mode .input-area{background:rgba(15,30,25,.92)}
    body.dusk-mode .message-bubble,body.dusk-mode .input-container,body.dusk-mode .welcome-card,body.dusk-mode .typing-content{background:#152a23;color:#e8f3ee}
    body.dusk-mode .sb-user-name,body.dusk-mode .header-title h1,body.dusk-mode .welcome-card h2{color:#edf5f1}
    body.dusk-mode .sb-acc-item.open{background:rgba(27,51,43,.76)}
    body.dusk-mode .conversation-item.active,body.dusk-mode .conversation-item:hover,body.dusk-mode .sb-acc-trigger:hover,body.dusk-mode .sb-link:hover{background:#1b332b}

    @media(prefers-reduced-motion:reduce){
        .msc-chat *{animation:none!important;transition-duration:.01ms!important;scroll-behavior:auto!important}
    }


    /* ======================================================================
       CLEAN CHAT COMPOSITION V2
       The application shell already owns navigation. The old always-visible
       conversation rail created a double-sidebar layout, so it is now an
       on-demand drawer while the conversation becomes the visual focus.
       ====================================================================== */
    body:has(.msc-chat) .content-wrapper {
        max-width:none !important;
        width:100% !important;
        padding:0 !important;
        margin:0 !important;
    }

    .chat-wrapper.msc-chat {
        position:relative;
        display:block;
        height:100dvh;
        min-height:620px;
        background:
            radial-gradient(circle at 92% 4%, rgba(216,235,226,.6), transparent 28rem),
            linear-gradient(180deg,#fbfcfa 0%,#f6f9f7 100%);
    }

    .conversation-sidebar {
        position:absolute;
        inset:0 auto 0 0;
        z-index:70;
        width:min(340px,calc(100% - 28px));
        min-width:0;
        transform:translateX(calc(-100% - 24px));
        transition:transform .34s var(--mc-ease);
        border-right:1px solid var(--mc-line);
        border-radius:0 24px 24px 0;
        background:rgba(255,255,255,.97);
        box-shadow:24px 0 70px rgba(16,63,50,.15);
        backdrop-filter:blur(22px);
        -webkit-backdrop-filter:blur(22px);
    }
    .chat-wrapper.rail-open .conversation-sidebar { transform:translateX(0); }
    .conversation-sidebar .sb-profile { display:none; }

    .rail-head {
        height:72px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
        padding:0 18px 0 20px;
        border-bottom:1px solid var(--mc-line);
    }
    .rail-head-copy { min-width:0; }
    .rail-eyebrow {
        display:block;
        margin-bottom:3px;
        color:var(--mc-emerald);
        font-size:9px;
        font-weight:850;
        letter-spacing:.13em;
        text-transform:uppercase;
    }
    .rail-title {
        margin:0;
        color:var(--mc-forest);
        font-size:15px;
        font-weight:820;
        letter-spacing:-.02em;
    }
    .rail-close {
        width:36px;height:36px;flex:0 0 auto;
        display:grid;place-items:center;
        border:1px solid var(--mc-line);
        border-radius:11px;
        background:#fff;
        color:#687d74;
        cursor:pointer;
        transition:all .18s ease;
    }
    .rail-close:hover { background:var(--mc-mint);color:var(--mc-forest); }
    .rail-close svg { width:17px;height:17px; }

    .chat-rail-overlay {
        position:absolute;
        inset:0;
        z-index:60;
        border:0;
        padding:0;
        background:rgba(8,29,22,.18);
        backdrop-filter:blur(2px);
        -webkit-backdrop-filter:blur(2px);
        opacity:0;
        pointer-events:none;
        transition:opacity .24s ease;
    }
    .chat-wrapper.rail-open .chat-rail-overlay { opacity:1;pointer-events:auto; }

    .chat-main {
        width:100%;
        height:100%;
        min-width:0;
        display:flex;
        flex-direction:column;
        overflow:hidden;
        background:transparent;
    }

    .chat-header {
        min-height:76px;
        padding:12px clamp(18px,2.3vw,34px);
        border-bottom:1px solid rgba(16,63,50,.09);
        background:rgba(251,252,250,.9);
        box-shadow:0 1px 0 rgba(255,255,255,.75) inset;
    }
    .header-left { gap:12px;min-width:0; }
    .header-right { gap:10px; }
    .history-btn {
        height:40px;
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:0 12px;
        border:1px solid var(--mc-line);
        border-radius:12px;
        background:#fff;
        color:#536b61;
        font:inherit;
        font-size:11px;
        font-weight:780;
        cursor:pointer;
        box-shadow:0 6px 18px rgba(16,63,50,.04);
        transition:all .18s ease;
    }
    .history-btn:hover { background:var(--mc-mint);color:var(--mc-forest);transform:translateY(-1px); }
    .history-btn svg { width:16px;height:16px; }

    .header-title h1 { font-size:15px; }
    .header-title p { margin-top:3px;font-size:10.5px; }

    .chat-wellness-chip {
        min-height:34px;
        display:inline-flex;
        align-items:center;
        gap:7px;
        padding:0 10px;
        border:1px solid rgba(35,107,85,.13);
        border-radius:999px;
        background:#eef7f2;
        color:var(--mc-emerald);
        font-size:10px;
        font-weight:800;
        white-space:nowrap;
    }
    .chat-wellness-chip::before {
        content:"";
        width:6px;height:6px;border-radius:50%;
        background:var(--mc-emerald-soft);
        box-shadow:0 0 0 4px rgba(77,157,123,.10);
    }

    .messages-outer {
        position:relative;
        flex:1;
        min-height:0;
        overflow-y:auto;
        scroll-padding-bottom:140px;
    }
    .messages-container {
        width:min(900px,calc(100% - 64px));
        margin:0 auto;
        padding:38px 0 48px;
    }

    .message { gap:12px;margin-bottom:24px; }
    .message-avatar {
        width:34px;height:34px;
        border-radius:11px;
        box-shadow:none;
    }
    .message.ai .message-avatar { border-radius:12px; }
    .message-content { max-width:min(78%,680px); }
    .message.user .message-content { max-width:min(72%,620px); }
    .message-bubble {
        padding:15px 17px;
        border-color:rgba(16,63,50,.105);
        border-radius:18px 18px 18px 7px;
        background:rgba(255,255,255,.96);
        color:#30463c;
        font-size:14px;
        line-height:1.68;
        box-shadow:0 10px 30px rgba(16,63,50,.055);
    }
    .message.user .message-bubble {
        border-radius:18px 18px 7px 18px;
        background:linear-gradient(145deg,#103f32,#174f3f);
        box-shadow:0 12px 30px rgba(16,63,50,.16);
    }
    .message-meta {
        gap:7px;
        margin-top:7px;
        padding:0 2px;
        color:#8ca096;
        font-size:10px;
    }
    .emotion-badge {
        min-height:22px;
        padding:3px 8px;
        font-size:9.5px;
    }

    .welcome-card {
        max-width:660px;
        margin:6vh auto 24px;
        padding:34px 34px 30px;
        border:1px solid rgba(16,63,50,.10);
        border-radius:24px;
        background:rgba(255,255,255,.9);
        box-shadow:0 24px 70px rgba(16,63,50,.08);
    }
    .welcome-card h2 { font-size:25px;letter-spacing:-.035em; }
    .welcome-card p { font-size:13px;line-height:1.7; }

    #typingIndicator .message-bubble { padding:13px 16px !important; }

    .input-area {
        padding:12px clamp(18px,2.3vw,34px) 20px;
        border-top:0;
        background:linear-gradient(180deg,rgba(246,249,247,0),rgba(246,249,247,.94) 28%,#f6f9f7 72%);
        backdrop-filter:none;
    }
    .input-wrapper { width:min(860px,100%);margin:0 auto; }
    .input-container {
        min-height:58px;
        padding:8px 8px 8px 18px;
        border:1px solid rgba(16,63,50,.15);
        border-radius:19px;
        background:#fff;
        box-shadow:0 16px 46px rgba(16,63,50,.10);
        transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease;
    }
    .input-container:focus-within {
        border-color:rgba(35,107,85,.40);
        box-shadow:0 18px 54px rgba(16,63,50,.13),0 0 0 4px rgba(35,107,85,.055);
        transform:translateY(-1px);
    }
    .chat-input {
        min-height:38px;
        max-height:150px;
        padding:9px 0;
        font-size:13.5px;
        line-height:1.5;
    }
    .send-button { width:44px;height:44px;border-radius:14px; }
    .send-button svg { width:18px;height:18px; }
    .input-hint { margin-top:7px;font-size:9.5px;color:#91a39a; }
    .input-safety-note { display:inline-flex;align-items:center;gap:5px;margin-left:8px;color:#6e8178;font-weight:700; }
    .input-safety-note::before { content:"";width:4px;height:4px;border-radius:50%;background:var(--mc-gold); }

    .sb-accordion { padding:10px 12px 16px; }
    .sb-acc-item { border-radius:15px; }
    .sb-acc-trigger { min-height:48px;font-size:11.5px; }
    .conversation-title { font-size:10.5px; }
    .conversation-time { font-size:9px; }

    @media(max-width:900px){
        .chat-wrapper.msc-chat { height:100dvh; }
        .chat-header { min-height:70px;padding-inline:16px; }
        .messages-container { width:min(760px,calc(100% - 32px));padding-top:28px; }
        .message-content { max-width:82%; }
        .message.user .message-content { max-width:78%; }
        .ai-back-link span,.history-btn span { display:none; }
        .history-btn { width:40px;padding:0;justify-content:center; }
        .chat-wellness-chip { display:none; }
        .input-area { padding:10px 14px 14px; }
    }
    @media(max-width:560px){
        .chat-wrapper.msc-chat { height:100dvh;min-height:520px; }
        .chat-header { min-height:62px;padding:8px 10px; }
        .ai-back-link { display:none; }
        .history-btn { width:36px;height:36px;border-radius:11px; }
        .header-title h1 { font-size:12.5px; }
        .header-title p { display:none; }
        .end-conversation-btn { width:36px;height:36px;padding:0;justify-content:center;font-size:0; }
        .status-indicator { display:none; }
        .messages-container { width:calc(100% - 22px);padding:20px 0 28px; }
        .message { gap:8px;margin-bottom:18px; }
        .message-avatar { width:29px;height:29px;border-radius:10px; }
        .message-content,.message.user .message-content { max-width:86%; }
        .message-bubble { padding:12px 13px;font-size:12.5px;line-height:1.62; }
        .message-meta { font-size:8.5px; }
        .input-area { padding:8px 8px 10px; }
        .input-container { min-height:52px;padding-left:13px;border-radius:16px; }
        .chat-input { font-size:12.5px; }
        .send-button { width:40px;height:40px;border-radius:12px; }
        .input-hint { display:none; }
    }

    body.dusk-mode .conversation-sidebar { background:rgba(21,42,35,.98); }
    body.dusk-mode .rail-head { border-color:rgba(237,245,241,.09); }
    body.dusk-mode .rail-title { color:#edf5f1; }
    body.dusk-mode .rail-close,
    body.dusk-mode .history-btn { background:#152a23;color:#dbe9e2;border-color:rgba(237,245,241,.11); }
    body.dusk-mode .chat-header { background:rgba(15,30,25,.94); }
    body.dusk-mode .chat-wellness-chip { background:#1b332b;color:#a9d7c3;border-color:rgba(237,245,241,.09); }

    @view-transition { navigation: auto; }
    :root { background: #f8faf7; }
    ::view-transition-group(root) { perspective: 1200px; transform-style: preserve-3d; }
    ::view-transition-old(root) {
        animation: mindcare-card-out 150ms cubic-bezier(.4,0,1,1) both;
        transform-origin: left center;
        backface-visibility: hidden;
    }
    ::view-transition-new(root) {
        animation: mindcare-card-in 260ms cubic-bezier(.22,1,.36,1) both;
        transform-origin: right center;
        backface-visibility: hidden;
    }
    @keyframes mindcare-card-out { from { opacity:1; transform:translateX(0) rotateY(0) scale(1); } to { opacity:0; transform:translateX(-28px) rotateY(-4deg) scale(.985); } }
    @keyframes mindcare-card-in { from { opacity:0; transform:translateX(28px) rotateY(4deg) scale(.985); } to { opacity:1; transform:translateX(0) rotateY(0) scale(1); } }
    .message.ai .message-avatar.magi-message-avatar { border-radius:50%; }
    @media (prefers-reduced-motion: reduce) {
        ::view-transition-old(root), ::view-transition-new(root) { animation:none !important; transform:none !important; }
    }</style>

<div class="chat-wrapper msc-chat">
    <!-- Sidebar -->
    <div class="conversation-sidebar">
        <div class="rail-head">
            <div class="rail-head-copy">
                <span class="rail-eyebrow">AI Companion</span>
                <p class="rail-title">History & wellness</p>
            </div>
            <button type="button" class="rail-close" onclick="toggleChatRail(false)" aria-label="Close history panel">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Fixed top: user profile --}}
        <div class="sb-profile">
            <div class="sb-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <div class="sb-user-info">
                <p class="sb-user-name">{{ auth()->user()->name }}</p>
                <p class="sb-user-meta">{{ auth()->user()->program ?? 'Student' }}</p>
            </div>
        </div>

        {{-- Scrollable accordion body --}}
        <div class="sb-accordion">

            {{-- ── Accordion: Wellness Score ── --}}
            <div class="sb-acc-item" id="acc-wellness">
                <button class="sb-acc-trigger" onclick="sbToggle('wellness')">
                    <span class="sb-acc-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    </span>
                    <span class="sb-acc-label">Wellness Score</span>
                    <span class="sb-acc-preview" id="sbScorePreview">—</span>
                    <svg class="sb-acc-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div class="sb-acc-body" id="body-wellness">
                    <div class="sb-wellness-inner" id="sbWellnessCard">
                        <div class="sb-score-row">
                            <span class="sb-score-num" id="sbScoreNum">—</span>
                            <span class="sb-score-denom">/100</span>
                            <span class="sb-score-badge" id="sbScoreBadge">Loading...</span>
                        </div>
                        <div class="sb-bar-track">
                            <div class="sb-bar-fill" id="sbBarFill" style="width:0%"></div>
                            <div class="sb-bar-marker" style="left:30%"></div>
                            <div class="sb-bar-marker" style="left:55%"></div>
                            <div class="sb-bar-marker sb-bar-marker-red" style="left:75%"></div>
                        </div>
                        <div class="sb-bar-scale"><span>OK</span><span>Watch</span><span>Risk</span><span>!</span></div>
                        <p class="sb-wellness-desc" id="sbWellnessDesc">Calculating...</p>
                        <p class="sb-wellness-note" id="sbWellnessNote" style="display:none"></p>
                        <a href="{{ route('student.insights') }}" class="sb-wellness-link">View full insights →</a>
                    </div>
                </div>
            </div>

            {{-- ── Accordion: Conversations ── --}}
            <div class="sb-acc-item" id="acc-conversations">
                <button class="sb-acc-trigger" onclick="sbToggle('conversations')">
                    <span class="sb-acc-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    </span>
                    <span class="sb-acc-label">Conversations</span>
                    <svg class="sb-acc-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div class="sb-acc-body" id="body-conversations">
                    <div class="sb-conv-inner">
                        <button class="new-chat-btn" onclick="startNewConversation()">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            New Conversation
                        </button>
                        <div class="conversations-list">
                            @if(isset($recentSessions) && $recentSessions->count() > 0)
                                @foreach($recentSessions as $session)
                                    <div class="conversation-item {{ isset($currentSession) && $currentSession->id === $session->id ? 'active' : '' }}"
                                         data-session-id="{{ $session->id }}"
                                         onclick="loadConversation({{ $session->id }})">
                                        <div class="conversation-content">
                                            <div class="conversation-title">{{ $session->title }}</div>
                                            <div class="conversation-time">{{ $session->last_message_at->diffForHumans() }}</div>
                                        </div>
                                        <button class="conversation-delete-btn" onclick="hideConversation(event, {{ $session->id }})" title="Remove from sidebar">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                @endforeach
                            @else
                                <div style="padding:0.75rem;text-align:center;color:var(--text-muted);font-size:0.78rem;">No conversations yet</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Accordion: Quick Links ── --}}
            <div class="sb-acc-item" id="acc-links">
                <button class="sb-acc-trigger" onclick="sbToggle('links')">
                    <span class="sb-acc-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                    </span>
                    <span class="sb-acc-label">Quick Links</span>
                    <svg class="sb-acc-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div class="sb-acc-body" id="body-links">
                    <div class="sb-links-inner">
                        <a href="{{ route('student.counselor-directory') }}" class="sb-link">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Find a Counselor
                        </a>
                        <a href="{{ route('student.appointments') }}" class="sb-link">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            My Appointments
                        </a>
                        <a href="{{ route('student.insights') }}" class="sb-link">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            View Insights
                        </a>
                        <a href="{{ route('student.counselor-chat') }}" class="sb-link">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                            Talk to Counselor
                        </a>
                    </div>
                </div>
            </div>

        </div>{{-- /sb-accordion --}}
    </div>

    <button type="button" class="chat-rail-overlay" onclick="toggleChatRail(false)" aria-label="Close history panel"></button>

    <!-- Main chat area -->
    <div class="chat-main">

        <!-- Chat header -->
        <div class="chat-header">
            <div class="header-left">
                <a href="{{ route('home') }}" class="ai-back-link" aria-label="Back to {{ config('branding.name') }} home">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m15 18-6-6 6-6"/></svg><span>{{ config('branding.name') }}</span>
                </a>
                <button type="button" class="history-btn" onclick="toggleChatRail(true)" aria-label="Open conversation history">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12a9 9 0 109-9 9.75 9.75 0 00-6.74 2.74L3 8m0-5v5h5M12 7v5l3 2"/></svg>
                    <span>History</span>
                </button>
                <x-magi-avatar size="md" state="idle" animated label="MAGI AI companion" />
                <div class="header-title">
                    <h1>{{ config('branding.assistant_name') }}</h1>
                    <p>Your {{ config('branding.assistant_name') }} wellness companion</p>
                </div>
            </div>
            <div class="header-right">
                <span class="chat-wellness-chip" id="chatWellnessChip">Wellness —</span>
                <button class="end-conversation-btn" onclick="endConversation()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
                    </svg>
                    End
                </button>
                <div class="status-indicator">
                    <span class="status-dot"></span>
                    <span>Online</span>
                </div>
            </div>
        </div>

        <!-- Scrollable messages area -->
        <div class="messages-outer" id="messagesOuter">
            <div class="messages-container" id="messagesContainer">

                <!-- Welcome card - HIDDEN BY DEFAULT FOR NEW CHATS -->
                <div class="welcome-card" id="welcomeCard" style="display: none;">
                    <x-magi-avatar size="lg" class="welcome-icon" state="idle" animated data-intro="true" label="MAGI, your wellness companion" />
                    <h2 id="welcomeTitle"></h2>
                    <p id="welcomeText"></p>
                </div>

                <!-- Quick actions - HIDDEN BY DEFAULT FOR NEW CHATS -->
                <div class="quick-actions" id="quickActions" style="display: none;">
                    <button class="quick-btn" onclick="quickMessage('I feel happy and excited today!')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm-.375 0h.008v.015h-.008V9.75zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75zm-.375 0h.008v.015h-.008V9.75z" /></svg>
                        Feeling happy
                    </button>
                    <button class="quick-btn" onclick="quickMessage('I feel sad and down today')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 16.318A4.486 4.486 0 0012.016 15a4.486 4.486 0 00-3.198 1.318M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Feeling sad
                    </button>
                    <button class="quick-btn" onclick="quickMessage('I am feeling anxious and worried')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                        Feeling anxious
                    </button>
                    <button class="quick-btn" onclick="quickMessage('I am stressed about school')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
                        School stress
                    </button>
                    <button class="quick-btn" onclick="quickMessage('I need someone to talk to')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>
                        Need to talk
                    </button>
                </div>

                <!-- Previous messages from DB -->
                @if(isset($recentEntries) && $recentEntries->count() > 0)
                    @foreach($recentEntries as $entry)
                        <div class="message user">
                            <div class="message-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                            <div class="message-content">
                                <div class="message-bubble">{{ $entry->entry_text }}</div>
                                <div class="message-meta">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    {{ $entry->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                        <div class="message ai">
                            <div class="message-avatar magi-message-avatar">
                    <x-magi-avatar size="sm" state="idle" animated />
                            </div>
                            <div class="message-content">
                                <div class="message-bubble assistant-message" data-ai-message="{{ base64_encode($entry->ai_response) }}"></div>
                                <div class="message-meta">
                                    <span class="emotion-badge {{ $entry->detected_emotion }}" title="User message emotion">
                                        @if($entry->detected_emotion === 'happy' || $entry->detected_emotion === 'joy')
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        @elseif($entry->detected_emotion === 'sad' || $entry->detected_emotion === 'sadness')
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 16.318A4.486 4.486 0 0012.016 15a4.486 4.486 0 00-3.198 1.318M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        @elseif($entry->detected_emotion === 'anxious' || $entry->detected_emotion === 'anxiety')
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        @endif
                                        {{ ucfirst($entry->detected_emotion) }} &middot; AI-estimated
                                    </span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    {{ $entry->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif

                <!-- Typing indicator -->
                <div class="message ai" id="typingIndicator" style="display:none;">
                    <div class="message-avatar magi-message-avatar">
                        <x-magi-avatar size="sm" state="thinking" animated />
                    </div>
                    <div class="message-content">
                        <div class="message-bubble" >
                            <div class="typing-indicator">
                                <span class="typing-dot"></span>
                                <span class="typing-dot"></span>
                                <span class="typing-dot"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <template id="magiAvatarSmTemplate"><x-magi-avatar size="sm" state="idle" animated /></template>
            </div><!-- /messages-container -->
        </div><!-- /messages-outer -->

        <!-- Input area -->
        <div class="input-area">
            <div class="input-wrapper">
                @if ($errors->any())
                    <div class="error-banner">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif
                <div class="error-banner" id="chatErrorBanner" role="alert" aria-live="polite" style="display:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                    <span id="chatErrorText"></span>
                    <a id="chatPrivacySettingsLink" class="chat-error-link" href="{{ route('student.privacy') }}#privacy" style="display:none;">Privacy Settings</a>
                </div>
                <form action="{{ route('student.store-entry') }}" method="POST" id="chatForm">
                    @csrf
                    <div class="input-container">
                        <textarea
                            name="entry_text"
                            class="chat-input"
                            placeholder="Share what's on your mind..."
                            rows="1"
                            required
                            maxlength="5000"
                            id="chatInput"
                        >{{ old('entry_text') }}</textarea>
                        <input type="hidden" name="is_anonymous" value="1">
                        <input type="hidden" name="session_id" id="sessionIdInput" value="{{ isset($currentSession) ? $currentSession->id : '' }}">
                        <button type="submit" class="send-button" id="sendButton">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                        </button>
                    </div>
                </form>
                <p class="input-hint">Press Enter to send &nbsp;&bull;&nbsp; Shift+Enter for new line <span class="input-safety-note">Private reflection, not emergency support.</span></p>
            </div>
        </div>

    </div><!-- /chat-main -->
</div><!-- /chat-wrapper -->


<script>
    let currentSessionId = {{ isset($currentSession) ? $currentSession->id : 'null' }};
    let aiConsentRequired = false;
    let retryPending = null;
    
    // ===== CACHE DOM ELEMENTS IMMEDIATELY =====
    const chatInput = document.getElementById('chatInput');
    const chatForm = document.getElementById('chatForm');
    const sendButton = document.getElementById('sendButton');
    const messagesContainer = document.getElementById('messagesContainer');
    const messagesOuter = document.getElementById('messagesOuter');
    const typingIndicator = document.getElementById('typingIndicator');
    const magiAvatarSmTemplate = document.getElementById('magiAvatarSmTemplate');
    console.log('[DOM] Elements cached:', { chatInput: !!chatInput, messagesContainer: !!messagesContainer, messagesOuter: !!messagesOuter });

    // Keep the frame and breathing animations limited to avatars near the chat viewport.
    function observeVisibleMagiAvatars() {
        const selector = '.magi-avatar[data-animated="true"]';
        if (!messagesContainer || !('IntersectionObserver' in window)) {
            document.querySelectorAll(selector).forEach(avatar => avatar.classList.add('is-visible'));
            return;
        }

        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => entry.target.classList.toggle('is-visible', entry.isIntersecting));
        }, { root: messagesOuter || null, rootMargin: '72px 0px', threshold: 0.01 });

        const scan = node => {
            if (node.nodeType !== 1) return;
            if (node.matches(selector)) observer.observe(node);
            node.querySelectorAll(selector).forEach(avatar => observer.observe(avatar));
        };

        scan(messagesContainer);
        if ('MutationObserver' in window) {
            const mutations = new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(scan)));
            mutations.observe(messagesContainer, { childList: true, subtree: true });
        }
    }

    observeVisibleMagiAvatars();

    function magiAvatarMarkup(state = 'idle') {
        const allowedStates = ['idle', 'thinking', 'responding', 'error'];
        const safeState = allowedStates.includes(state) ? state : 'idle';
        const avatar = magiAvatarSmTemplate?.content?.firstElementChild?.cloneNode(true);
        if (!avatar) return '<span class="magi-avatar magi-avatar--sm" role="img" aria-label="MAGI AI companion"></span>';
        avatar.dataset.state = safeState;
        return avatar.outerHTML;
    }

    function setMagiState(state) {
        const allowedStates = ['idle', 'thinking', 'responding', 'error'];
        // The title emblem is intentionally static; request states belong to the live typing mascot.
        const activeTypingAvatar = messagesContainer?.querySelector('.typing-indicator [data-magi-avatar]');
        if (!activeTypingAvatar) return;
        activeTypingAvatar.dataset.state = allowedStates.includes(state) ? state : 'idle';
    }

    function returnMagiAvatarToIdle(messageElement) {
        const avatar = messageElement?.querySelector('[data-magi-avatar]');
        if (!avatar) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) avatar.dataset.state = 'idle';
    }

    document.addEventListener('animationend', event => {
        if (event.animationName !== 'magi-avatar-frames') return;
        const avatar = event.target.closest?.('[data-magi-avatar][data-state="responding"]');
        if (avatar) avatar.dataset.state = 'idle';
    });

    // The canvas sprite renderer emits a cycle event instead of CSS animationend.
    document.addEventListener('magi:sprite-cycle', event => {
        const avatar = event.target.closest?.('[data-magi-avatar][data-state="responding"]');
        if (avatar) avatar.dataset.state = 'idle';
    });

    // ===== DEFINE UTILITY FUNCTIONS IMMEDIATELY =====
    function scrollToBottom(smooth = true) {
        if (messagesOuter) {
            messagesOuter.scrollTo({ top: messagesOuter.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
        }
    }

    function toggleChatRail(forceOpen = null) {
        const wrapper = document.querySelector('.chat-wrapper.msc-chat');
        if (!wrapper) return;
        const shouldOpen = forceOpen === null ? !wrapper.classList.contains('rail-open') : !!forceOpen;
        wrapper.classList.toggle('rail-open', shouldOpen);
        document.body.style.overflow = shouldOpen && window.innerWidth <= 900 ? 'hidden' : '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') toggleChatRail(false);
    });

    document.addEventListener('DOMContentLoaded', function() {
        renderPersistedAssistantMessages();
        console.log('[DEBUG] DOMContentLoaded fired');
        console.log('[DEBUG] messagesContainer:', messagesContainer);
        console.log('[DEBUG] currentSessionId:', currentSessionId);
        
        scrollToBottom(false);
        loadTodaySession();
        loadWellnessScore();
        sbToggle('conversations');
        
        console.log('[DOMReady] Chat initialization started with sessionId:', currentSessionId);
    });

    // ── Wellness Bar ─────────────────────────────────────────────
    async function loadWellnessScore() {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
            const res = await fetch('/student/api/wellness-score', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });
            const data = await res.json();
            if (data.success) updateWellnessBar(data);
        } catch(e) { /* silent fail */ }
    }

    function updateWellnessBar(data) {
        const fill    = document.getElementById('sbBarFill');
        const score   = document.getElementById('sbScoreNum');
        const badge   = document.getElementById('sbScoreBadge');
        const desc    = document.getElementById('sbWellnessDesc');
        const preview = document.getElementById('sbScorePreview');
        const headerChip = document.getElementById('chatWellnessChip');

        if (!fill) return;

        const note = document.getElementById('sbWellnessNote');

        fill.style.width       = data.score + '%';
        fill.style.background  = data.bar_color;
        score.textContent      = data.score;
        score.style.color      = data.bar_color;
        badge.textContent      = data.bar_label;
        badge.style.background = data.bar_color + '22';
        badge.style.color      = data.bar_color;
        badge.style.borderColor= data.bar_color + '44';
        desc.textContent       = data.description;

        if (note) {
            if (data.appointment && data.appointment.exists) {
                note.textContent = data.appointment.message;
                note.style.display = 'block';
            } else {
                note.style.display = 'none';
            }
        }

        // Update the preview chip visible on the collapsed trigger
        if (preview) {
            preview.textContent  = data.score + '/100';
            preview.style.color  = data.bar_color;
            preview.style.background = data.bar_color + '18';
        }
        if (headerChip) {
            headerChip.textContent = 'Wellness ' + data.score + '/100';
            headerChip.style.color = data.bar_color;
            headerChip.style.borderColor = data.bar_color + '33';
            headerChip.style.background = data.bar_color + '12';
        }

        // Auto-open wellness accordion if at risk
        if (data.score >= 55) {
            const item = document.getElementById('acc-wellness');
            if (item && !item.classList.contains('open')) sbToggle('wellness');
        }
    }

    // ── Accordion toggle ─────────────────────────────────────────
    function sbToggle(name) {
        const item = document.getElementById('acc-' + name);
        if (!item) return;
        const isOpen = item.classList.contains('open');
        // Close all
        document.querySelectorAll('.sb-acc-item.open').forEach(el => el.classList.remove('open'));
        // Open clicked if it was closed
        if (!isOpen) item.classList.add('open');
    }
    // ─────────────────────────────────────────────────────────────

    async function loadTodaySession() {
        try {
            console.log('[LOAD_SESSION] Function called with currentSessionId:', currentSessionId);
            
            // If no session yet (new chat), initialize with greeting
            if (!currentSessionId || currentSessionId === 'null') {
                console.log('[LOAD_SESSION] ✅ No session ID detected, calling initializeNewChat()');
                await initializeNewChat();
                return;
            }
            
            console.log('[LOAD_SESSION] Session ID exists:', currentSessionId);
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
            const response = await fetch(`/api/ai/sessions/${currentSessionId}/messages`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });
            const data = await response.json();
            console.log('[LOAD_SESSION] Response:', data);
            
            if (data.success && data.messages && data.messages.length > 0) {
                console.log('[LOAD_SESSION] Found', data.messages.length, 'messages');
                // Clear conversation history and rebuild from DB
                conversationHistory = [];
                
                // Only remove old message bubbles, keep welcome card and quick actions
                const oldMessages = messagesContainer.querySelectorAll('.message');
                oldMessages.forEach(msg => msg.remove());
                
                data.messages.forEach(msg => {
                    // Add to conversation history for context
                    conversationHistory.push({
                        user: msg.user_message,
                        ai: msg.ai_response,
                        emotion: msg.emotion,
                        confidence: msg.confidence
                    });
                    const userHtml = `
                        <div class="message user">
                            <div class="message-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                            <div class="message-content">
                                <div class="message-bubble">${escapeHtml(msg.user_message)}</div>
                                <div class="message-meta">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    ${formatTime(msg.created_at)}
                                </div>
                            </div>
                        </div>`;
                    const emotionIcon = getEmotionIcon(msg.emotion);
                    const aiHtml = `
                        <div class="message ai">
                            <div class="message-avatar magi-message-avatar">${magiAvatarMarkup('idle')}</div>
                            <div class="message-content">
                                <div class="message-bubble">${renderAssistantMessage(msg.ai_response)}</div>
                                <div class="message-meta">
                                    <span class="emotion-badge ${msg.emotion}" title="AI-estimated emotion label; not a clinical measurement.">${emotionIcon} ${msg.emotion.charAt(0).toUpperCase()+msg.emotion.slice(1)} &middot; AI-estimated</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    ${formatTime(msg.created_at)}
                                </div>
                            </div>
                        </div>`;
                    messagesContainer.insertAdjacentHTML('beforeend', userHtml);
                    messagesContainer.insertAdjacentHTML('beforeend', aiHtml);
                });
                scrollToBottom(false);
            } else {
                // Empty session - show AI greeting with memory of past conversations
                console.log('[LOAD_SESSION] ✅ Empty session, calling initializeNewChat() with memory');
                await initializeNewChat();
            }
        } catch (error) { 
            console.error('[LOAD_SESSION] ❌ Error loading session:', error); 
        }
    }

    // NEW: Initialize new chat with AI greeting
    async function initializeNewChat() {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
            console.log('[CHAT_INIT] Starting initialization with currentSessionId:', currentSessionId);
            console.log('[CHAT_INIT] messagesContainer element:', messagesContainer ? 'FOUND' : 'NOT FOUND');
            
            // HIDE WELCOME CARD first
            const welcomeCard = document.querySelector('.welcome-card');
            if (welcomeCard) {
                welcomeCard.style.display = 'none';
                console.log('[CHAT_INIT] Welcome card hidden');
            }
            
            // HIDE QUICK ACTIONS
            const quickActions = document.querySelector('.quick-actions');
            if (quickActions) {
                quickActions.style.display = 'none';
                console.log('[CHAT_INIT] Quick actions hidden');
            }
            
            // Clear existing messages
            const oldMessages = messagesContainer.querySelectorAll('.message');
            console.log('[CHAT_INIT] Removing old messages:', oldMessages.length);
            oldMessages.forEach(msg => msg.remove());
            
            // Show typing animation while fetching greeting
            showTyping();
            scrollToBottom(false);
            
            // If no session ID, create one first
            let sessionId = currentSessionId;
            if (!sessionId || sessionId === 'null') {
                console.log('[CHAT_INIT] No session ID provided, creating new session...');
                const createResponse = await fetch('/api/ai/sessions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                const createData = await createResponse.json();
                console.log('[CHAT_INIT] Create session response:', createData);
                if (createData.success && createData.session_id) {
                    sessionId = createData.session_id;
                    currentSessionId = sessionId;
                    console.log('[CHAT_INIT] Created new session:', sessionId);
                } else {
                    console.error('[CHAT_INIT] Failed to create session:', createData.error);
                    hideTyping();
                    setMagiState('error');
                    return;
                }
            }
            
            console.log('[CHAT_INIT] About to call /api/ai/initialize-session with sessionId:', sessionId);
            const response = await fetch('/api/ai/initialize-session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ session_id: sessionId })
            });
            
            console.log('[CHAT_INIT] Initialize response status:', response.status);
            const data = await response.json();
            console.log('[CHAT_INIT] Initialize response data:', data);

            if (response.status === 403 && data.code === 'AI_CONSENT_REQUIRED') {
                setAIConsentRequired();
                setMagiState('idle');
                return;
            }
            
            if (data.success && data.greeting) {
                setMagiState('responding');
                console.log('[CHAT_INIT] ✅ Got greeting successfully');
                
                // Display AI greeting as first message
                const aiGreetingHtml = `
                    <div class="message ai">
                        <div class="message-avatar magi-message-avatar">${magiAvatarMarkup('responding')}</div>
                        <div class="message-content">
                            <div class="message-bubble">${renderAssistantMessage(data.greeting)}</div>
                            <div class="message-meta">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Just now
                            </div>
                        </div>
                    </div>`;
                
                console.log('[CHAT_INIT] Generated greeting HTML, length:', aiGreetingHtml.length);
                console.log('[CHAT_INIT] messagesContainer exists:', !!messagesContainer);
                
                // Remove typing indicator
                hideTyping();
                
                // Add AI greeting
                console.log('[CHAT_INIT] About to insert greeting HTML...');
                messagesContainer.insertAdjacentHTML('beforeend', aiGreetingHtml);
                console.log('[CHAT_INIT] ✅ Greeting HTML inserted');
                
                // Verify it was inserted
                const newMessage = messagesContainer.querySelector('.message.ai');
                console.log('[CHAT_INIT] Verify: Message inserted?', !!newMessage);
                returnMagiAvatarToIdle(newMessage);
                setMagiState('idle');
                
                scrollToBottom(false);
                
                console.log('[CHAT_INIT] ✅ AI greeting displayed:', data.greeting.substring(0, 50));
            } else {
                console.warn('[CHAT_INIT] ❌ Failed to get greeting:', data.error || 'Unknown error');
                console.warn('[CHAT_INIT] Full response:', data);
                hideTyping();
                setMagiState('error');
            }
        } catch (error) {
            console.error('[CHAT_INIT] ❌ Error initializing chat:', error);
            console.error('[CHAT_INIT] Stack trace:', error.stack);
            hideTyping();
            setMagiState('error');
            // Silently continue - user can still type
        }
    }

    function formatTime(timestamp) {
        const date = new Date(timestamp);
        const diffMins = Math.floor((new Date() - date) / 60000);
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffMins < 1440) return `${Math.floor(diffMins/60)}h ago`;
        return date.toLocaleDateString();
    }

    function getEmotionIcon(emotion) {
        const icons = {
            'happy': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            'happiness': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            'joy': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            'sad': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 16.318A4.486 4.486 0 0012.016 15a4.486 4.486 0 00-3.198 1.318M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            'sadness': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 16.318A4.486 4.486 0 0012.016 15a4.486 4.486 0 00-3.198 1.318M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            'anxious': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>',
            'anxiety': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>',
            'angry': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            'anger': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            'neutral': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            'grief': '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7.5-4.35-9-9.25C1.7 7.52 4.1 4.5 7.3 4.5c1.83 0 3.48 1 4.7 2.6 1.22-1.6 2.87-2.6 4.7-2.6 3.2 0 5.6 3.02 4.3 7.25C19.5 16.65 12 21 12 21z" /></svg>'
        };
        return icons[emotion] || icons['neutral'];
    }


    function emotionEvidenceLabel(source, uncertain) {
        // Keep live and restored history labels consistent. Provenance and
        // uncertainty remain available in API/diagnostic metadata; neither is
        // a calibrated percentage suitable for the student-facing badge.
        return 'AI-estimated';
    }
    let isSubmitting = false;
    let conversationHistory = [];  // Track conversation history for context-aware responses

    chatInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 150) + 'px';
    });

    window.quickMessage = function(message) {
        chatInput.value = message;
        chatInput.focus();
        chatInput.style.height = 'auto';
        chatInput.style.height = chatInput.scrollHeight + 'px';
    }

    function showTyping() { 
        // This state is tied to an actual greeting or chat request.
        setMagiState('thinking');
        const typingHtml = `
            <div class="typing-indicator" role="status" aria-live="polite">
                ${magiAvatarMarkup('thinking')}
                <div class="typing-content">
                    <span class="magi-typing-label">MAGI is thinking…</span>
                    <div class="typing-dots" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        `;
        
        // Clear previous typing indicator if any
        const existingTyping = messagesContainer.querySelector('.typing-indicator');
        if (existingTyping) existingTyping.remove();
        
        messagesContainer.insertAdjacentHTML('beforeend', typingHtml);
        scrollToBottom();
    }
    
    function hideTyping() { 
        const typingIndicator = messagesContainer.querySelector('.typing-indicator');
        if (typingIndicator) typingIndicator.remove();
    }

    function addUserMessage(text) {
        const userInitial = '{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}';
        const html = `
            <div class="message user is-new">
                <div class="message-avatar">${userInitial}</div>
                <div class="message-content">
                    <div class="message-bubble">${escapeHtml(text)}</div>
                    <div class="message-meta">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Just now
                    </div>
                </div>
            </div>`;
        if (typingIndicator && typingIndicator.parentNode) {
            typingIndicator.insertAdjacentHTML('beforebegin', html);
        } else {
            messagesContainer.insertAdjacentHTML('beforeend', html);
        }
        scrollToBottom();
    }

    function addAIMessage(response, emotion, confidence, evidence = {}) {
        const existing = document.querySelectorAll('.message.ai .message-bubble');
        for (let m of existing) {
            if (m.textContent.trim() === response.trim()) return;
        }
        
        console.log('[ADDAI_MESSAGE]', {
            'emotion_param': emotion,
            'confidence_param': confidence,
            'response_length': response?.length
        });
        
        const emotionIcon = getEmotionIcon(emotion);
        const displayText = emotion.charAt(0).toUpperCase()+emotion.slice(1);
        const evidenceLabel = emotionEvidenceLabel(evidence.source, evidence.uncertain);
        console.log('[EMOTION_DISPLAY]', {
            'emotion': emotion,
            'displayText': displayText,
            'icon_exists': !!emotionIcon,
            'emotion_class': 'emotion-badge ' + emotion
        });
        
        const html = `
            <div class="message ai is-new">
                <div class="message-avatar magi-message-avatar">${magiAvatarMarkup('responding')}</div>
                <div class="message-content">
                    <div class="message-bubble">${renderAssistantMessage(response)}</div>
                    <div class="message-meta">
                        <span class="emotion-badge ${emotion}" title="AI-estimated emotion label; not a clinical measurement.">${emotionIcon} ${displayText} &middot; ${evidenceLabel}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:11px;height:11px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Just now
                    </div>
                </div>
            </div>`;
        if (typingIndicator && typingIndicator.parentNode) {
            typingIndicator.insertAdjacentHTML('beforebegin', html);
        } else {
            messagesContainer.insertAdjacentHTML('beforeend', html);
        }
        const newAiMessages = messagesContainer.querySelectorAll('.message.ai.is-new');
        returnMagiAvatarToIdle(newAiMessages[newAiMessages.length - 1]);
        hideTyping();
        scrollToBottom();
    }

    // Referral URLs are generated by Laravel. This renderer never consumes
    // model-provided HTML or destinations.
    function addCounselorReferral(referral) {
        if (!referral?.offered || !Array.isArray(referral.actions) || !referral.actions.length) return;
        const card = document.createElement('div');
        card.className = 'message ai counselor-referral';
        const content = document.createElement('div');
        content.className = 'message-content';
        const label = document.createElement('div');
        label.className = 'message-bubble';
        label.textContent = referral.lookup_outcome === 'no_eligible_counselors'
            ? 'Counseling support is not currently listed as available. You can still use the request page to ask for follow-up.'
            : 'If you would like professional support, you can choose one of these options.';
        content.appendChild(label);
        referral.actions.forEach(action => {
            try {
                const url = new URL(action.url, window.location.origin);
                if (url.origin !== window.location.origin) return;
                const link = document.createElement('a');
                link.href = url.pathname + url.search;
                link.className = 'sb-link';
                link.textContent = action.label;
                link.style.margin = '8px 8px 0 0';
                content.appendChild(link);
            } catch (_) { /* Ignore malformed metadata. */ }
        });
        const avatar = document.createElement('div');
        avatar.className = 'message-avatar magi-message-avatar';
        avatar.innerHTML = magiAvatarMarkup('idle');
        card.appendChild(avatar);
        card.appendChild(content);
        messagesContainer.appendChild(card);
        scrollToBottom();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/\n/g, '<br>');
    }

    // Escape model text first, then add only a deliberately small formatting subset.
    // Model output can never create raw HTML, links, buttons, or event handlers.
    // Normalize only an unambiguous sequential inline list returned by the model.
    function normalizeInlineNumberedList(text) {
        const source = String(text || '').replace(/\r\n?/g, '\n');
        if (source.includes('\n')) return source;
        const marker = /(^|\s)(\d{1,2})[.)]\s+/g;
        const matches = [];
        let found;
        while ((found = marker.exec(source)) !== null) {
            const number = Number(found[2]);
            if (number < 1 || number > 20) continue;
            matches.push({ number, start: found.index + found[1].length, contentStart: marker.lastIndex });
        }
        if (matches.length < 3 || matches[0].number !== 1) return source;
        for (let i = 1; i < matches.length; i += 1) {
            if (matches[i].number !== matches[i - 1].number + 1) return source;
        }
        const prefix = source.slice(0, matches[0].start).trim();
        const items = matches.map((item, index) => source.slice(item.contentStart, index + 1 < matches.length ? matches[index + 1].start : source.length).trim());
        if (items.some(item => !item || /^https?:\/\//i.test(item))) return source;
        const headingLike = prefix.length > 0 && prefix.length <= 140 && /:\s*\S/.test(prefix);
        const intro = headingLike ? `## ${prefix}` : prefix;
        return `${intro}${intro ? '\n\n' : ''}${items.map((item, index) => `${index + 1}. ${item}`).join('\n')}`;
    }

    function renderAssistantMessage(text) {
        text = normalizeInlineNumberedList(text);
        const escape = value => { const node = document.createElement('div'); node.textContent = value; return node.innerHTML; };
        const inline = value => escape(value).replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>').replace(/(?<!\*)\*([^*\n]+)\*(?!\*)/g, '<em>$1</em>');
        const lines = String(text || '').replace(/\r\n?/g, '\n').split('\n');
        const output = []; let paragraph = []; let listType = null; let listItems = [];
        const flushParagraph = () => { if (paragraph.length) output.push(`<p>${paragraph.map(inline).join('<br>')}</p>`); paragraph = []; };
        const flushList = () => { if (!listType) return; output.push(`<${listType}>${listItems.map(item => `<li>${inline(item)}</li>`).join('')}</${listType}>`); listType = null; listItems = []; };
        lines.forEach(line => {
            const heading = line.match(/^\s{0,3}(#{1,3})\s+(.+)\s*$/), ordered = line.match(/^\s*\d+[.)]\s+(.+)\s*$/), bullet = line.match(/^\s*[-*\u2022]\s+(.+)\s*$/);
            if (heading) { flushParagraph(); flushList(); output.push(`<h4 class="ai-heading">${inline(heading[2])}</h4>`); }
            else if (ordered || bullet) { flushParagraph(); const type = ordered ? 'ol' : 'ul'; if (listType && listType !== type) flushList(); listType = type; listItems.push((ordered || bullet)[1]); }
            else if (!line.trim()) { flushParagraph(); flushList(); }
            else { flushList(); paragraph.push(line); }
        });
        flushParagraph(); flushList(); return output.join('') || '<p></p>';
    }

    function decodeStoredAssistantMessage(encoded) {
        try { return new TextDecoder().decode(Uint8Array.from(atob(encoded), char => char.charCodeAt(0))); } catch (_) { return ''; }
    }

    function renderPersistedAssistantMessages() {
        document.querySelectorAll('.assistant-message[data-ai-message]').forEach(bubble => {
            bubble.innerHTML = renderAssistantMessage(decodeStoredAssistantMessage(bubble.dataset.aiMessage));
            bubble.removeAttribute('data-ai-message');
        });
    }
    // Keep the browser deadline aligned with the bounded Laravel-to-Flask timeout.
    const CHAT_REQUEST_TIMEOUT_MS = {{ (int) config('services.mindcare_ml.chat.browser_timeout_ms', 210000) }};

    function newChatRequestId() {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();
        return 'chat-' + Date.now() + '-' + Math.random().toString(16).slice(2);
    }

    function hideChatError() {
        const banner = document.getElementById('chatErrorBanner');
        const textNode = document.getElementById('chatErrorText');
        const privacyLink = document.getElementById('chatPrivacySettingsLink');
        if (banner) banner.style.display = 'none';
        if (textNode) textNode.textContent = '';
        if (privacyLink) privacyLink.style.display = 'none';
    }

    function showChatError(message, options = {}) {
        const banner = document.getElementById('chatErrorBanner');
        const textNode = document.getElementById('chatErrorText');
        const privacyLink = document.getElementById('chatPrivacySettingsLink');

        if (banner && textNode) {
            textNode.textContent = message;
            banner.style.display = 'flex';
            if (privacyLink) privacyLink.style.display = options.showPrivacyLink ? 'inline' : 'none';
            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }

        console.error(message);
    }

    function setAIConsentRequired() {
        aiConsentRequired = true;
        hideTyping();
        if (chatInput) {
            chatInput.disabled = true;
            chatInput.placeholder = 'Enable AI recommendations in Privacy Settings to continue.';
        }
        if (sendButton) sendButton.disabled = true;
        showChatError('AI Companion is disabled because AI recommendations are not enabled in your privacy settings.', { showPrivacyLink: true });
    }

    function resetSendButton() {
        sendButton.disabled = aiConsentRequired;
        sendButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>`;
        isSubmitting = false;
    }

    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (aiConsentRequired) return false;
        const message = chatInput.value.trim();
        if (!message || isSubmitting) return false;
        const isRetry = retryPending && retryPending.message === message;
        const requestId = isRetry ? retryPending.requestId : newChatRequestId();
        hideChatError();
        isSubmitting = true;
        sendButton.disabled = true;
        sendButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="animation:spin 1s linear infinite;width:15px;height:15px"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>`;
        if (!isRetry) addUserMessage(message);
        chatInput.value = '';
        chatInput.style.height = 'auto';
        showTyping();
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
        const userId = {{ auth()->user()->id }};
        
        // Local LLM generation can exceed one minute on cold/CPU-bound runs.
        // Keep a finite client timeout, but allow the backend enough time to finish.
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), CHAT_REQUEST_TIMEOUT_MS);
        
        fetch('/api/ai/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ 
                message: message,
                user_id: userId,
                session_id: currentSessionId,
                request_id: requestId
            }),
            signal: controller.signal
        })
        .then(r => {
            clearTimeout(timeoutId);
            
            // Check for HTTP error status
            if (!r.ok) {
                if (r.status === 403) {
                    return r.json().then(data => {
                        if (data.code === 'AI_CONSENT_REQUIRED') {
                            const consentError = new Error(data.error || 'AI recommendations are disabled in your privacy settings.');
                            consentError.code = 'AI_CONSENT_REQUIRED';
                            throw consentError;
                        }
                        throw new Error(data.error || ('HTTP 403: ' + r.statusText));
                    }).catch(parseError => {
                        if (parseError && parseError.code === 'AI_CONSENT_REQUIRED') throw parseError;
                        throw new Error('HTTP 403: ' + r.statusText);
                    });
                }
                // Server returned an error but check if it has JSON body
                if (r.status === 503) {
                    return r.json().then(data => {
                        throw new Error(`Server error (503): ${data.error || 'AI system unavailable'}`);
                    }).catch(parseError => {
                        throw new Error(`Server error (503): AI system unavailable - ${r.statusText}`);
                    });
                } else if (r.status === 500) {
                    return r.json().then(data => {
                        throw new Error(`Server error (500): ${data.error || 'Internal error'}`);
                    }).catch(parseError => {
                        throw new Error(`Server error (500): ${r.statusText}`);
                    });
                } else if (r.status === 422) {
                    return r.json().then(data => {
                        throw new Error(`Validation error: ${data.message || 'Invalid request'}`);
                    }).catch(parseError => {
                        throw new Error(`Validation error (422): ${r.statusText}`);
                    });
                } else {
                    throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                }
            }
            
            return r.json().then(data => {
                // Verify response structure
                if (!data || typeof data !== 'object') {
                    throw new Error('Invalid response format: expected JSON object');
                }
                return data;
            });
        })
        .then(data => {
            // Validate success response
            if (!data.success || data.available === false) {
                const errorMsg = data.error || data.message || 'Unknown error occurred';
                throw new Error(errorMsg);
            }
            
            // Validate required fields
            if (!data.response || typeof data.response !== 'string' || !data.response.trim()) {
                throw new Error('No valid response from AI system');
            }

            setMagiState('responding');
            
            const emotionStr = data.emotion || 'neutral';
            
            // DIAGNOSTIC: Log emotion data from response
            console.log('[EMOTION_EXTRACTION]', {
                'data.emotion': data.emotion,
                'data.analysis.emotion': data.analysis?.emotion,
                'emotionStr': emotionStr,
                'full_data': data
            });
            
            // Backend returns confidence as decimal (0-1), convert to percentage for display
            let confidence = 0.8; // default
            if (data.analysis?.confidence) {
                confidence = data.analysis.confidence;
                // If it's already a decimal (0-1), use as-is for percentage calculation
                if (confidence <= 1) confidence = confidence;
                else confidence = confidence / 100; // If it's 0-100, convert to 0-1
            } else if (data.confidence) {
                confidence = data.confidence;
                if (confidence <= 1) confidence = confidence;
                else confidence = confidence / 100;
            }
            
            console.log('[EMOTION_BEFORE_DISPLAY]', {
                'emotion': emotionStr,
                'confidence': confidence,
                'response_start': data.response?.substring(0, 50)
            });
            
            addAIMessage(data.response, emotionStr, confidence, {
                source: data.analysis?.method || data.emotion_source,
                uncertain: data.analysis?.uncertain === true || data.uncertain === true,
            });
            setMagiState('idle');
            addCounselorReferral(data.counselor_referral);
            
            retryPending = null;

            // Update session ID if new
            if (data.session_id) {
                currentSessionId = data.session_id;
            }
            
            // Add this message pair to conversation history for context
            conversationHistory.push({
                user: message,
                ai: data.response,
                emotion: emotionStr,
                confidence: confidence
            });
            
            // Update wellness bar after each message
            loadWellnessScore();
        })
        .catch(error => { 
            clearTimeout(timeoutId);
            console.error('Chat error:', error);
            hideTyping();
            
            // Distinguish between error types without using a blocking browser alert.
            let errorMsg = 'Failed to send message. Please try again.';
            if (error.code === 'AI_CONSENT_REQUIRED') {
                setAIConsentRequired();
                setMagiState('idle');
            } else if (error.name === 'AbortError') {
                setMagiState('error');
                errorMsg = 'The AI is taking longer than expected. Your message may still be processing on the server. Please wait a moment before retrying.';
            } else if (error instanceof Error) {
                setMagiState('error');
                // Check if this is an AI unavailable error
                if (error.message.includes('503') || error.message.includes('AI Companion is currently unavailable')) {
                    errorMsg = 'AI Companion is currently unavailable. The ML/LLM service is offline or not responding. Please try again once the AI service is running.';
                } else {
                    errorMsg = `Error: ${error.message}`;
                }
            } else {
                setMagiState('error');
            }

            retryPending = { message, requestId };
            chatInput.value = message;
            chatInput.dispatchEvent(new Event('input'));
            showChatError(errorMsg + ' Your message is still ready to retry safely.');
        })
        .finally(() => {
            // ✅ FIX 1: Always reset button state, whether request succeeded or failed/timed out
            // This ensures the loading spinner is cleared in ALL cases
            clearTimeout(timeoutId);
            resetSendButton();
        });
        return false;
    });

    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); chatForm.dispatchEvent(new Event('submit', { cancelable: true })); }
    });

    window.startNewConversation = function() {
        if (confirm('Start a new conversation? Your current conversation will be saved in the sidebar.')) {
            // Do a full page navigation to create new conversation
            // This ensures the page reloads with the updated sidebar (filtering out hidden conversations)
            window.location.href = '{{ route("student.new-conversation") }}';
        }
    }

    window.loadConversation = function(sessionId) { window.location.href = '{{ route("student.ai-chat") }}/' + sessionId; }

    window.endConversation = function() {
        if (confirm('End this conversation and start fresh?\n\nThis conversation will be saved in your history.')) {
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
            
            fetch('{{ route("student.end-conversation") }}', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ session_id: currentSessionId })
            })
            .then(r => {
                if (r.status === 419) {
                    alert('Session expired. Please refresh the page and try again.');
                    window.location.reload();
                    return null;
                }
                return r.json();
            })
            .then(data => { 
                if (data && data.success) { 
                    window.location.href = '{{ route("student.ai-chat") }}'; 
                } else if (data) {
                    alert('Error: ' + (data.message || 'Failed to end conversation'));
                }
            })
            .catch(error => { 
                console.error('Error:', error); 
                alert('Failed to end conversation. Please try again.');
            });
        }
    }

    window.hideConversation = function(event, sessionId) {
        event.stopPropagation(); // Prevent triggering loadConversation
        
        if (confirm('Remove this conversation from your sidebar?\n\nIt will still be visible in your history/journal.')) {
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
            const conversationItem = document.querySelector(`[data-session-id="${sessionId}"]`);
            
            fetch(`/student/conversation/${sessionId}/hide`, {
                method: 'DELETE',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(r => {
                if (r.status === 419) {
                    alert('Session expired. Please refresh and try again.');
                    window.location.reload();
                    return null;
                }
                return r.json();
            })
            .then(data => {
                if (data && data.success) {
                    // Fade out and remove the conversation item
                    conversationItem.style.opacity = '0';
                    conversationItem.style.transform = 'translateX(-10px)';
                    setTimeout(() => {
                        conversationItem.remove();
                        // Show "No conversations" message if list is now empty
                        const listContainer = document.querySelector('.conversations-list');
                        if (listContainer.children.length === 0) {
                            listContainer.innerHTML = '<div style="padding:0.75rem;text-align:center;color:var(--text-muted);font-size:0.78rem;">No conversations yet</div>';
                        }
                    }, 200);
                } else if (data) {
                    alert('Error: ' + (data.message || 'Failed to hide conversation'));
                }
            })
            .catch(error => { 
                console.error('Fetch error:', error);
                alert('Failed to remove conversation. Please try again.');
            });
        }
    } 
</script>

<script>
    // Typewriter effect for welcome message
    const titleText = "Hey {{ auth()->user()->name }}!";
    const descText = "Hi! I'm MAGI, your wellness companion—a safe, judgment-free space to share your thoughts and feelings. How are you feeling today?";
    
    function typeWriter(element, text, speed, callback) {
        let i = 0;
        element.textContent = '';
        element.style.opacity = '1';
        
        function type() {
            if (i < text.length) {
                element.textContent += text.charAt(i);
                i++;
                setTimeout(type, speed);
            } else if (callback) {
                callback();
            }
        }
        type();
    }
    
    // Start typewriter animation after page loads
    document.addEventListener('DOMContentLoaded', function() {
        renderPersistedAssistantMessages();
        const titleEl = document.getElementById('welcomeTitle');
        const textEl = document.getElementById('welcomeText');
        
        // Type title first, then description
        setTimeout(() => {
            typeWriter(titleEl, titleText, 50, () => {
                setTimeout(() => {
                    typeWriter(textEl, descText, 30);
                }, 200);
            });
        }, 800);
    });
</script>


@endsection
