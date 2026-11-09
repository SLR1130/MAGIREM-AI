@extends('layouts.app')

@section('title', 'Counselor Chat')

@section('content')

<style>
.cc-page,
.cc-page *,
.cc-page *::before,
.cc-page *::after {
    box-sizing: border-box;
}

.cc-page {
    --cc-forest: #103F32;
    --cc-forest-2: #174F3F;
    --cc-emerald: #236B55;
    --cc-mint: #E6F1EB;
    --cc-mint-2: #D8EBE2;
    --cc-ivory: #F8FAF7;
    --cc-white: #FFFFFF;
    --cc-gold: #F4C84E;
    --cc-text: #263A33;
    --cc-muted: #71837A;
    --cc-soft: #91A098;
    --cc-line: rgba(16, 63, 50, .10);
    --cc-shadow: 0 20px 55px rgba(16, 63, 50, .08);
    --cc-ease: cubic-bezier(.22, 1, .36, 1);

    width: 100%;
    max-width: 1500px;
    margin: 0 auto;
    padding: 10px 18px 24px;
    color: var(--cc-text);
    font-family: Inter, "Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

/* ---------- PAGE HEADER ---------- */
.cc-page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 16px;
}

.cc-page-head-copy {
    min-width: 0;
}

.cc-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 7px;
    color: var(--cc-emerald);
    font-size: 10px;
    font-weight: 850;
    letter-spacing: .14em;
    text-transform: uppercase;
}

.cc-eyebrow::before {
    content: "";
    width: 20px;
    height: 2px;
    border-radius: 999px;
    background: var(--cc-gold);
}

.cc-page-head h1 {
    margin: 0;
    color: var(--cc-forest);
    font-size: clamp(31px, 3vw, 46px);
    line-height: 1;
    letter-spacing: -.045em;
    font-weight: 840;
}

.cc-page-head p {
    max-width: 760px;
    margin: 10px 0 0;
    color: var(--cc-muted);
    font-size: 12px;
    line-height: 1.65;
}

.cc-page-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
}

.cc-page-action {
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 13px;
    border: 1px solid var(--cc-line);
    border-radius: 11px;
    background: #fff;
    color: var(--cc-forest) !important;
    text-decoration: none;
    font-size: 10px;
    font-weight: 800;
    transition: transform .18s var(--cc-ease), box-shadow .18s var(--cc-ease), background .18s ease;
}

.cc-page-action:hover {
    transform: translateY(-1px);
    background: var(--cc-ivory);
    box-shadow: 0 9px 22px rgba(16, 63, 50, .06);
}

.cc-page-action.primary {
    border-color: var(--cc-forest);
    background: var(--cc-forest);
    color: #fff !important;
}

.cc-page-action.primary:hover {
    background: var(--cc-forest-2);
}

.cc-page-action svg {
    width: 15px;
    height: 15px;
    flex: 0 0 15px;
}

/* ---------- WORKSPACE ---------- */
.cc-workspace {
    display: grid;
    grid-template-columns: 264px minmax(0, 1fr) 294px;
    min-height: 610px;
    height: calc(100dvh - 198px);
    max-height: 835px;
    overflow: hidden;
    border: 1px solid var(--cc-line);
    border-radius: 20px;
    background: #fff;
    box-shadow: var(--cc-shadow);
}

.cc-panel {
    min-width: 0;
    min-height: 0;
    background: #fff;
}

/* ---------- LEFT: COUNSELORS ---------- */
.cc-roster {
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--cc-line);
}

.cc-panel-head {
    height: 64px;
    flex: 0 0 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 0 16px;
    border-bottom: 1px solid var(--cc-line);
    background: #fff;
}

.cc-panel-title {
    display: flex;
    align-items: center;
    gap: 9px;
    color: var(--cc-forest);
    font-size: 11px;
    font-weight: 820;
}

.cc-panel-title svg {
    width: 17px;
    height: 17px;
    color: var(--cc-emerald);
}

.cc-count {
    min-width: 28px;
    height: 28px;
    padding: 0 8px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: var(--cc-mint);
    color: var(--cc-forest);
    font-size: 9px;
    font-weight: 850;
}

.cc-counselor-list {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 10px;
    scrollbar-width: thin;
    scrollbar-color: rgba(16, 63, 50, .16) transparent;
}

.cc-counselor-card {
    appearance: none;
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 6px;
    padding: 10px;
    border: 1px solid transparent;
    border-radius: 12px;
    background: transparent;
    color: inherit;
    text-align: left;
    cursor: pointer;
    transition: background .16s ease, border-color .16s ease, transform .16s var(--cc-ease);
}

.cc-counselor-card:hover {
    background: #F5F8F6;
}

.cc-counselor-card.active {
    border-color: rgba(35, 107, 85, .12);
    background: var(--cc-mint);
}

.cc-counselor-avatar {
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border-radius: 12px;
    background: #EDF5F1;
    color: var(--cc-forest);
    font-size: 11px;
    font-weight: 850;
}

.cc-counselor-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cc-counselor-card.active .cc-counselor-avatar {
    background: var(--cc-forest);
    color: #fff;
}

.cc-counselor-meta {
    min-width: 0;
    flex: 1;
}

.cc-counselor-name {
    display: block;
    overflow: hidden;
    color: var(--cc-forest);
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.cc-counselor-specialty {
    display: block;
    margin-top: 3px;
    overflow: hidden;
    color: #809087;
    font-size: 8px;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.cc-status-line {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 5px;
    color: #819189;
    font-size: 8px;
    font-weight: 650;
}

.cc-status-dot {
    width: 6px;
    height: 6px;
    flex: 0 0 6px;
    border-radius: 50%;
    background: #9BABA3;
}

.cc-status-dot.online { background: #39A879; }
.cc-status-dot.away { background: #D3AA36; }
.cc-status-dot.busy { background: #B8665E; }

.cc-roster-empty {
    height: 100%;
    min-height: 260px;
    display: grid;
    place-items: center;
    padding: 26px 18px;
}

.cc-roster-empty-inner {
    max-width: 195px;
    text-align: center;
}

.cc-empty-icon {
    width: 46px;
    height: 46px;
    margin: 0 auto 12px;
    display: grid;
    place-items: center;
    border-radius: 14px;
    background: var(--cc-mint);
    color: var(--cc-forest);
}

.cc-empty-icon svg {
    width: 21px;
    height: 21px;
}

.cc-roster-empty strong {
    display: block;
    color: var(--cc-forest);
    font-size: 10px;
    font-weight: 800;
}

.cc-roster-empty p {
    margin: 6px 0 0;
    color: var(--cc-muted);
    font-size: 8.5px;
    line-height: 1.55;
}

.cc-roster-actions {
    flex: 0 0 auto;
    padding: 10px;
    border-top: 1px solid var(--cc-line);
    background: #fff;
}

.cc-roster-link {
    min-height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: 100%;
    padding: 0 10px;
    border: 1px solid transparent;
    border-radius: 10px;
    text-decoration: none;
    font-size: 9px;
    font-weight: 800;
}

.cc-roster-link svg {
    width: 14px;
    height: 14px;
}

.cc-roster-link.primary {
    border-color: var(--cc-forest);
    background: var(--cc-forest);
    color: #fff;
}

.cc-roster-link.danger {
    margin-top: 6px;
    border-color: rgba(155, 78, 72, .12);
    background: #FFF4F3;
    color: #9B4E48;
}

/* ---------- CENTER: CHAT ---------- */
.cc-chat {
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: #FBFCFB;
}

.cc-chat-head {
    height: 64px;
    flex: 0 0 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 0 16px;
    border-bottom: 1px solid var(--cc-line);
    background: #fff;
}

.cc-chat-person {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.cc-chat-avatar {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border-radius: 12px;
    background: var(--cc-mint);
    color: var(--cc-forest);
    font-size: 11px;
    font-weight: 850;
}

.cc-chat-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cc-chat-copy {
    min-width: 0;
}

.cc-chat-copy h3 {
    margin: 0;
    overflow: hidden;
    color: var(--cc-forest);
    font-size: 11px;
    font-weight: 820;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.cc-chat-copy p {
    margin: 3px 0 0;
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--cc-muted);
    font-size: 8.5px;
}

.cc-chat-copy p .cc-status-dot {
    width: 5px;
    height: 5px;
}

.cc-profile-btn {
    min-height: 33px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 9px;
    border: 1px solid var(--cc-line);
    border-radius: 9px;
    background: var(--cc-ivory);
    color: var(--cc-forest);
    font: inherit;
    font-size: 8.5px;
    font-weight: 800;
    cursor: pointer;
}

.cc-profile-btn:disabled {
    opacity: .45;
    cursor: not-allowed;
}

.cc-profile-btn svg {
    width: 13px;
    height: 13px;
}

.cc-conversation {
    position: relative;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

.cc-empty-state {
    position: absolute;
    inset: 0;
    z-index: 1;
    display: grid;
    place-items: center;
    padding: 30px;
    background:
        radial-gradient(circle at 84% 10%, rgba(216, 235, 226, .42), transparent 23rem),
        #FBFCFB;
}

.cc-empty-card {
    max-width: 350px;
    text-align: center;
}

.cc-empty-orb {
    width: 58px;
    height: 58px;
    margin: 0 auto 15px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(35, 107, 85, .10);
    border-radius: 18px;
    background: var(--cc-mint);
    color: var(--cc-forest);
    box-shadow: 0 14px 32px rgba(16, 63, 50, .07);
}

.cc-empty-orb svg {
    width: 25px;
    height: 25px;
}

.cc-empty-card h3 {
    margin: 0;
    color: var(--cc-forest);
    font-size: 14px;
    font-weight: 820;
    letter-spacing: -.02em;
}

.cc-empty-card p {
    margin: 8px auto 0;
    color: var(--cc-muted);
    font-size: 9.5px;
    line-height: 1.6;
}

.cc-empty-actions {
    margin-top: 15px;
    display: flex;
    justify-content: center;
    gap: 7px;
    flex-wrap: wrap;
}

.cc-empty-actions a {
    min-height: 33px;
    display: inline-flex;
    align-items: center;
    padding: 0 10px;
    border: 1px solid var(--cc-line);
    border-radius: 9px;
    background: #fff;
    color: var(--cc-forest);
    font-size: 8.5px;
    font-weight: 800;
}

.cc-messages {
    height: 100%;
    min-height: 0;
    overflow-y: auto;
    padding: 18px 20px 20px;
    background:
        radial-gradient(circle at 90% 5%, rgba(216, 235, 226, .34), transparent 22rem),
        #FBFCFB;
}

.cc-message {
    display: flex;
    margin-bottom: 11px;
}

.cc-message.own {
    justify-content: flex-end;
}

.cc-bubble-row {
    max-width: min(78%, 640px);
    display: flex;
    align-items: flex-end;
    gap: 7px;
}

.cc-message.own .cc-bubble-row {
    flex-direction: row-reverse;
}

.cc-bubble-avatar {
    width: 28px;
    height: 28px;
    flex: 0 0 28px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: var(--cc-mint);
    color: var(--cc-forest);
    font-size: 8.5px;
    font-weight: 850;
}

.cc-bubble-avatar.own {
    background: var(--cc-forest);
    color: #fff;
}

.cc-bubble {
    padding: 10px 12px;
    border: 1px solid var(--cc-line);
    border-radius: 13px 13px 13px 4px;
    background: #fff;
    color: var(--cc-text);
    box-shadow: 0 5px 14px rgba(16, 63, 50, .04);
    font-size: 9.8px;
    line-height: 1.55;
    word-break: break-word;
}

.cc-message.own .cc-bubble {
    border-color: var(--cc-forest);
    border-radius: 13px 13px 4px 13px;
    background: var(--cc-forest);
    color: #fff;
}

.cc-bubble-meta {
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 4px;
    opacity: .6;
    font-size: 7.5px;
}

.cc-bubble-meta svg {
    width: 9px;
    height: 9px;
}

.cc-chat-compose {
    flex: 0 0 auto;
    padding: 10px 12px 12px;
    border-top: 1px solid var(--cc-line);
    background: #fff;
}

.cc-toolbar {
    position: relative;
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 8px;
}

.cc-tool {
    width: 31px;
    height: 31px;
    display: grid;
    place-items: center;
    border: 1px solid var(--cc-line);
    border-radius: 9px;
    background: var(--cc-ivory);
    color: var(--cc-forest);
    cursor: pointer;
}

.cc-tool:hover {
    background: var(--cc-mint);
}

.cc-tool svg {
    width: 14px;
    height: 14px;
}

.cc-emoji-picker {
    position: absolute;
    left: 0;
    bottom: calc(100% + 8px);
    z-index: 80;
    width: 264px;
    display: none;
    grid-template-columns: repeat(6, 1fr);
    gap: 4px;
    padding: 8px;
    border: 1px solid var(--cc-line);
    border-radius: 13px;
    background: #fff;
    box-shadow: 0 18px 44px rgba(16, 63, 50, .14);
}

.cc-emoji-picker.open {
    display: grid;
}

.cc-emoji-picker button {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 9px;
    background: transparent;
    font-size: 18px;
    cursor: pointer;
}

.cc-emoji-picker button:hover {
    background: var(--cc-ivory);
}

.cc-file-preview {
    display: none;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    padding: 8px 10px;
    border: 1px solid rgba(35, 107, 85, .10);
    border-radius: 10px;
    background: var(--cc-mint);
    color: var(--cc-forest);
    font-size: 8.8px;
}

.cc-file-preview svg {
    width: 14px;
    height: 14px;
}

.cc-file-preview span {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.cc-file-preview button {
    border: 0;
    background: transparent;
    color: #9B4E48;
    cursor: pointer;
}

.cc-message-form {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    align-items: end;
}

.cc-message-form textarea {
    width: 100%;
    min-height: 42px;
    max-height: 120px;
    padding: 11px 12px;
    border: 1px solid var(--cc-line);
    border-radius: 11px;
    background: var(--cc-ivory);
    color: var(--cc-text);
    font: inherit;
    font-size: 10px;
    line-height: 1.45;
    resize: none;
}

.cc-message-form textarea:focus {
    outline: none;
    border-color: rgba(35, 107, 85, .38);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(35, 107, 85, .07);
}

.cc-message-form textarea::placeholder {
    color: #94A39C;
}

.cc-send {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 0 14px;
    border: 0;
    border-radius: 11px;
    background: var(--cc-forest);
    color: #fff;
    font: inherit;
    font-size: 9px;
    font-weight: 820;
    cursor: pointer;
}

.cc-send:hover {
    background: var(--cc-forest-2);
}

.cc-send svg {
    width: 14px;
    height: 14px;
}

/* ---------- RIGHT: INFO ---------- */
.cc-info {
    display: flex;
    flex-direction: column;
    border-left: 1px solid var(--cc-line);
    background: #fff;
}

.cc-info-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 12px;
}

.cc-profile-card,
.cc-schedule-card {
    margin: 0 0 10px;
    padding: 14px;
    border: 1px solid var(--cc-line);
    border-radius: 14px;
    background: var(--cc-ivory);
}

.cc-profile-summary {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 13px;
}

.cc-info-avatar {
    width: 46px;
    height: 46px;
    flex: 0 0 46px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border-radius: 14px;
    background: var(--cc-mint);
    color: var(--cc-forest);
    font-size: 12px;
    font-weight: 850;
}

.cc-info-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cc-info-name {
    min-width: 0;
}

.cc-info-name strong {
    display: block;
    overflow: hidden;
    color: var(--cc-forest);
    font-size: 11px;
    font-weight: 820;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.cc-info-name span {
    display: block;
    margin-top: 3px;
    overflow: hidden;
    color: var(--cc-muted);
    font-size: 8.5px;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.cc-info-grid {
    border-top: 1px solid rgba(16, 63, 50, .07);
}

.cc-info-row {
    min-height: 34px;
    display: grid;
    grid-template-columns: 88px minmax(0, 1fr);
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid rgba(16, 63, 50, .06);
}

.cc-info-row:last-child {
    border-bottom: 0;
}

.cc-info-label {
    color: #84938C;
    font-size: 8px;
    font-weight: 750;
}

.cc-info-value {
    min-width: 0;
    overflow-wrap: anywhere;
    color: var(--cc-forest);
    font-size: 8.3px;
    font-weight: 800;
    text-align: right;
}

.cc-card-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 8px;
    color: var(--cc-forest);
}

.cc-card-title-icon {
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    display: grid;
    place-items: center;
    border-radius: 11px;
    background: var(--cc-mint);
}

.cc-card-title-icon svg {
    width: 16px;
    height: 16px;
}

.cc-card-title-copy strong {
    display: block;
    font-size: 10px;
    font-weight: 820;
}

.cc-card-title-copy span {
    display: block;
    margin-top: 2px;
    color: var(--cc-muted);
    font-size: 8px;
}

.cc-schedule-card p {
    margin: 9px 0 12px;
    color: var(--cc-muted);
    font-size: 8.8px;
    line-height: 1.55;
}

.cc-schedule-card a {
    min-height: 36px;
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border-radius: 9px;
    background: var(--cc-forest);
    color: #fff !important;
    font-size: 8.5px;
    font-weight: 800;
}

.cc-schedule-card a svg {
    width: 13px;
    height: 13px;
}

/* ---------- MODALS ---------- */
.cc-modal {
    position: fixed;
    inset: 0;
    z-index: 8000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(8, 24, 19, .62);
    backdrop-filter: blur(8px);
}

.cc-modal.open {
    display: flex;
}

.cc-modal-panel {
    width: min(420px, 94vw);
    max-height: 84vh;
    overflow: auto;
    border: 1px solid rgba(255,255,255,.38);
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 28px 80px rgba(8, 24, 19, .25);
}

.cc-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 17px;
    border-bottom: 1px solid var(--cc-line);
}

.cc-modal-head strong {
    color: var(--cc-forest);
    font-size: 13px;
}

.cc-modal-close {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 9px;
    background: var(--cc-ivory);
    color: var(--cc-forest);
    cursor: pointer;
}

.cc-modal-close svg {
    width: 16px;
    height: 16px;
}

.cc-modal-body {
    padding: 17px;
}

.cc-modal-profile {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
}

.cc-modal-avatar {
    width: 58px;
    height: 58px;
    display: grid;
    place-items: center;
    border-radius: 16px;
    background: var(--cc-mint);
    color: var(--cc-forest);
    font-size: 16px;
    font-weight: 850;
}

.cc-modal-profile h3 {
    margin: 0;
    color: var(--cc-forest);
    font-size: 14px;
}

.cc-modal-profile p {
    margin: 4px 0 0;
    color: var(--cc-muted);
    font-size: 9px;
}

.cc-image-panel {
    width: min(980px, 94vw);
    max-height: 86vh;
    display: grid;
    place-items: center;
}

.cc-image-panel img {
    max-width: 100%;
    max-height: 78vh;
    border-radius: 14px;
}

/* ---------- RESPONSIVE ---------- */
@media (max-width: 1220px) {
    .cc-workspace {
        grid-template-columns: 240px minmax(0, 1fr);
    }

    .cc-info {
        display: none;
    }
}

@media (max-width: 820px) {
    .cc-page {
        padding-inline: 12px;
    }

    .cc-page-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .cc-page-actions {
        justify-content: flex-start;
    }

    .cc-workspace {
        grid-template-columns: 1fr;
        height: calc(100dvh - 205px);
    }

    .cc-roster {
        display: none;
    }

    .cc-chat {
        border-radius: 20px;
    }
}
</style>

<div class="cc-page">
    <header class="cc-page-head">
        <div class="cc-page-head-copy">
            <span class="cc-eyebrow">Professional support</span>
            <h1>Counselor Chat</h1>
            <p>Connect privately with a university counselor, review availability, and move from conversation to scheduled support in one calm workspace.</p>
        </div>

        <div class="cc-page-actions">
            <a href="{{ route('student.counselor-directory') }}" class="cc-page-action">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                </svg>
                Counselor directory
            </a>

            <a href="{{ route('student.request-appointment') }}" class="cc-page-action primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                </svg>
                Request appointment
            </a>
        </div>
    </header>

    <section class="cc-workspace">
        <!-- COUNSELOR ROSTER -->
        <aside class="cc-panel cc-roster">
            <div class="cc-panel-head">
                <div class="cc-panel-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                    </svg>
                    Counselors
                </div>
                <span class="cc-count">{{ $counselors->count() }}</span>
            </div>

            <div class="cc-counselor-list">
                @forelse($counselors as $c)
                    <button
                        type="button"
                        class="cc-counselor-card"
                        data-counselor-id="{{ $c->id }}"
                        data-name="{{ $c->name }}"
                        data-status="{{ $c->status ?? 'offline' }}"
                        data-specialization="{{ $c->specialization ?? 'General counseling' }}"
                        data-experience="{{ $c->years_of_experience ? $c->years_of_experience . ' years' : 'Not specified' }}"
                        data-availability="{{ is_string($c->availability_schedule ?? null) ? $c->availability_schedule : 'By schedule' }}"
                        data-email="{{ $c->email ?? 'Not available' }}"
                        data-avatar="{{ $c->profile_picture ? asset($c->profile_picture) : '' }}"
                        onclick="selectCounselor(this)"
                    >
                        <span class="cc-counselor-avatar">
                            @if($c->profile_picture)
                                <img src="{{ asset($c->profile_picture) }}" alt="{{ $c->name }}">
                            @else
                                {{ strtoupper(substr($c->name, 0, 1)) }}
                            @endif
                        </span>

                        <span class="cc-counselor-meta">
                            <span class="cc-counselor-name">{{ $c->name }}</span>
                            <span class="cc-counselor-specialty">{{ $c->specialization ?? 'General counseling' }}</span>
                            <span class="cc-status-line">
                                <span class="cc-status-dot {{ strtolower($c->status ?? 'offline') }}"></span>
                                {{ ucfirst($c->status ?? 'offline') }}
                            </span>
                        </span>
                    </button>
                @empty
                    <div class="cc-roster-empty">
                        <div class="cc-roster-empty-inner">
                            <div class="cc-empty-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8"/>
                                </svg>
                            </div>
                            <strong>No counselors available</strong>
                            <p>Check the directory or request a scheduled session. Available counselors will appear here.</p>
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="cc-roster-actions">
                <a href="{{ route('student.counselor-directory') }}" class="cc-roster-link primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-5-5m2-6a8 8 0 11-16 0 8 8 0 0116 0z"/>
                    </svg>
                    Browse directory
                </a>

                <a href="{{ route('student.emergency-resources') }}" class="cc-roster-link danger">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3m0 4h.01M10.3 4.4L2.9 17.2A2 2 0 004.6 20h14.8a2 2 0 001.7-2.8L13.7 4.4a2 2 0 00-3.4 0z"/>
                    </svg>
                    Emergency resources
                </a>
            </div>
        </aside>

        <!-- CHAT -->
        <section class="cc-panel cc-chat">
            <header class="cc-chat-head">
                <div class="cc-chat-person">
                    <div class="cc-chat-avatar" id="chatAvatar">—</div>
                    <div class="cc-chat-copy">
                        <h3 id="chatName">Choose a counselor</h3>
                        <p>
                            <span class="cc-status-dot" id="chatStatusDot"></span>
                            <span id="chatStatus">Not selected</span>
                        </p>
                    </div>
                </div>

                <button class="cc-profile-btn" id="viewCounselorProfileBtn" type="button" onclick="openCounselorProfile()" disabled>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    View profile
                </button>
            </header>

            <div class="cc-conversation">
                <div class="cc-empty-state" id="emptyState">
                    <div class="cc-empty-card">
                        <div class="cc-empty-orb">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4-4 7-9 7-1.4 0-2.8-.25-4-.72L3 19l1.3-3.4A6.3 6.3 0 013 12c0-4 4-7 9-7s9 3 9 7z"/>
                            </svg>
                        </div>

                        @if($counselors->isEmpty())
                            <h3>No counselor is available right now</h3>
                            <p>You can still browse the counselor directory or request a session for another time.</p>
                        @else
                            <h3>Select a counselor to begin</h3>
                            <p>Choose a counselor from the left panel to open your private conversation and review their availability.</p>
                        @endif

                        <div class="cc-empty-actions">
                            <a href="{{ route('student.counselor-directory') }}">Browse directory</a>
                            <a href="{{ route('student.request-appointment') }}">Request session</a>
                        </div>
                    </div>
                </div>

                <div class="cc-messages" id="messagesArea" style="display:none;"></div>
            </div>

            <div class="cc-chat-compose" id="inputArea" style="display:none;">
                <div class="cc-toolbar">
                    <button class="cc-tool" type="button" onclick="document.getElementById('fileInput').click()" title="Attach file">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.2 7l-6.6 6.6a2 2 0 102.8 2.8l6.4-6.6a4 4 0 00-5.6-5.6L5.8 10.8a6 6 0 108.4 8.4L20.5 13"/>
                        </svg>
                    </button>

                    <button class="cc-tool" type="button" onclick="toggleEmojiPicker()" title="Add emoji">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </button>

                    <button class="cc-tool" type="button" onclick="document.getElementById('imageInput').click()" title="Add image">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.6-4.6a2 2 0 012.8 0L16 16m-2-2l1.6-1.6a2 2 0 012.8 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </button>

                    <div class="cc-emoji-picker" id="emojiPicker">
                        @foreach(['😊','😢','😂','❤️','👍','🙏','😍','🎉','😔','😭','😡','🤔','😴','💪','🌟','🤗','😌','✨'] as $emoji)
                            <button type="button" onclick="insertEmoji('{{ $emoji }}')">{{ $emoji }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="cc-file-preview" id="filePreview">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5l5 5v11a2 2 0 01-2 2z"/>
                    </svg>
                    <span id="fileName"></span>
                    <button type="button" onclick="clearFile()">×</button>
                </div>

                <form id="messageForm" class="cc-message-form" onsubmit="sendMessage(event)">
                    @csrf
                    <input type="hidden" id="counselorId" name="counselor_id">
                    <input type="file" id="fileInput" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt" hidden onchange="handleFileSelect(this)">
                    <input type="file" id="imageInput" name="attachment" accept="image/*" hidden onchange="handleFileSelect(this)">

                    <textarea id="messageInput" name="message" rows="1" placeholder="Write a private message..."></textarea>

                    <button type="submit" class="cc-send">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                        </svg>
                        Send
                    </button>
                </form>
            </div>
        </section>

        <!-- COUNSELOR INFO -->
        <aside class="cc-panel cc-info">
            <div class="cc-panel-head">
                <div class="cc-panel-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 12a4 4 0 100-8 4 4 0 000 8zm7 9a7 7 0 00-14 0"/>
                    </svg>
                    Counselor details
                </div>
            </div>

            <div class="cc-info-scroll">
                <div class="cc-profile-card">
                    <div class="cc-profile-summary">
                        <div class="cc-info-avatar" id="infoAvatar">—</div>
                        <div class="cc-info-name">
                            <strong id="infoName">No counselor selected</strong>
                            <span id="infoSpecialization">Select someone from the list</span>
                        </div>
                    </div>

                    <div class="cc-info-grid">
                        <div class="cc-info-row">
                            <span class="cc-info-label">Status</span>
                            <span class="cc-info-value" id="infoStatus">—</span>
                        </div>
                        <div class="cc-info-row">
                            <span class="cc-info-label">Experience</span>
                            <span class="cc-info-value" id="infoExperience">—</span>
                        </div>
                        <div class="cc-info-row">
                            <span class="cc-info-label">Availability</span>
                            <span class="cc-info-value" id="infoAvailability">—</span>
                        </div>
                        <div class="cc-info-row">
                            <span class="cc-info-label">Email</span>
                            <span class="cc-info-value" id="infoEmail">—</span>
                        </div>
                    </div>
                </div>

                <div class="cc-schedule-card">
                    <div class="cc-card-title">
                        <span class="cc-card-title-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                            </svg>
                        </span>
                        <span class="cc-card-title-copy">
                            <strong>Schedule a session</strong>
                            <span>Private counseling appointment</span>
                        </span>
                    </div>

                    <p>If you prefer a scheduled conversation, request a counseling appointment at a time that works for you.</p>

                    <a href="{{ route('student.request-appointment') }}">
                        Request appointment
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12h14m-6-6l6 6-6 6"/>
                        </svg>
                    </a>
                </div>
            </div>
        </aside>
    </section>

    <!-- COUNSELOR PROFILE MODAL -->
    <div class="cc-modal" id="counselorProfileModal" aria-hidden="true">
        <div class="cc-modal-panel">
            <div class="cc-modal-head">
                <strong>Counselor profile</strong>
                <button class="cc-modal-close" type="button" onclick="closeCounselorProfile()" aria-label="Close profile">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="cc-modal-body">
                <div class="cc-modal-profile">
                    <div class="cc-modal-avatar" id="modalCounselorAvatar">—</div>
                    <div>
                        <h3 id="modalCounselorName">Counselor</h3>
                        <p id="modalCounselorSpecialization">Professional support</p>
                    </div>
                </div>

                <div class="cc-info-grid">
                    <div class="cc-info-row">
                        <span class="cc-info-label">Status</span>
                        <span class="cc-info-value" id="modalCounselorStatus">—</span>
                    </div>
                    <div class="cc-info-row">
                        <span class="cc-info-label">Experience</span>
                        <span class="cc-info-value" id="modalCounselorExperience">—</span>
                    </div>
                    <div class="cc-info-row">
                        <span class="cc-info-label">Availability</span>
                        <span class="cc-info-value" id="modalCounselorAvailability">—</span>
                    </div>
                    <div class="cc-info-row">
                        <span class="cc-info-label">Email</span>
                        <span class="cc-info-value" id="modalCounselorEmail">—</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- IMAGE MODAL -->
    <div class="cc-modal" id="imageModal" aria-hidden="true">
        <div class="cc-image-panel">
            <img id="modalImage" src="" alt="Attachment preview">
        </div>
    </div>
</div>

<script>
let selectedCounselorId = null;
let selectedCounselorName = '';
let selectedCounselorData = null;
let selectedFile = null;

function safeText(value) {
    return value == null || value === '' ? '—' : String(value);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char];
    });
}

function selectCounselor(button) {
    if (!button) return;

    selectedCounselorId = Number(button.dataset.counselorId);
    selectedCounselorName = button.dataset.name || 'Counselor';

    selectedCounselorData = {
        id: selectedCounselorId,
        name: selectedCounselorName,
        status: button.dataset.status || 'offline',
        specialization: button.dataset.specialization || 'General counseling',
        experience: button.dataset.experience || 'Not specified',
        availability: button.dataset.availability || 'By schedule',
        email: button.dataset.email || 'Not available',
        avatar: button.dataset.avatar || ''
    };

    document.querySelectorAll('.cc-counselor-card').forEach(function (item) {
        item.classList.remove('active');
    });
    button.classList.add('active');

    const emptyState = document.getElementById('emptyState');
    if (emptyState) emptyState.style.display = 'none';

    document.getElementById('messagesArea').style.display = 'block';
    document.getElementById('inputArea').style.display = 'block';

    renderCounselorData(selectedCounselorData);

    document.getElementById('counselorId').value = selectedCounselorId;
    document.getElementById('viewCounselorProfileBtn').disabled = false;

    loadMessages(selectedCounselorId);
    
    // Reset signature to force first poll to render
    lastTimelineSignature = null;
}

function renderCounselorData(data) {
    const initial = (data.name || 'C').charAt(0).toUpperCase();

    const chatAvatar = document.getElementById('chatAvatar');
    if (data.avatar) {
        chatAvatar.innerHTML = '<img src="' + escapeHtml(data.avatar) + '" alt="">';
    } else {
        chatAvatar.textContent = initial;
    }

    document.getElementById('chatName').textContent = data.name;
    document.getElementById('chatStatus').textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);

    const chatDot = document.getElementById('chatStatusDot');
    chatDot.className = 'cc-status-dot ' + String(data.status).toLowerCase();

    const infoAvatar = document.getElementById('infoAvatar');
    if (data.avatar) {
        infoAvatar.innerHTML = '<img src="' + escapeHtml(data.avatar) + '" alt="">';
    } else {
        infoAvatar.textContent = initial;
    }

    document.getElementById('infoName').textContent = data.name;
    document.getElementById('infoSpecialization').textContent = data.specialization;
    document.getElementById('infoStatus').textContent = safeText(data.status.charAt(0).toUpperCase() + data.status.slice(1));
    document.getElementById('infoExperience').textContent = safeText(data.experience);
    document.getElementById('infoAvailability').textContent = safeText(data.availability);
    document.getElementById('infoEmail').textContent = safeText(data.email);
}

function formatDuration(seconds) {
    if (!seconds || seconds < 0) return '';
    if (seconds < 60) return seconds + ' sec';
    if (seconds < 3600) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return secs > 0 ? mins + ' min ' + secs + ' sec' : mins + ' min';
    }
    const hours = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    return mins > 0 ? hours + ' hr ' + mins + ' min' : hours + ' hr';
}

function loadMessages(id) {
    fetch('/student/api/counselor/' + id + '/messages')
        .then(function (response) { return response.json(); })
        .then(function (data) {
            const timeline = data.timeline || data.messages || [];
            window.timelineCache = timeline;
            lastTimelineSignature = null; // Reset signature
            renderTimeline(timeline);
        })
        .catch(err => console.error('[LOAD_ERROR]', err));
}

function renderTimeline(timeline) {
    const container = document.getElementById('messagesArea');
    if (!container) return;

    // Check if we should scroll to bottom
    const shouldScroll = container.scrollHeight - container.scrollTop <= container.clientHeight + 120;

    if (!timeline || timeline.length === 0) {
        container.innerHTML =
            '<div class="cc-empty-card" style="margin:70px auto 0;">' +
                '<h3>No messages or calls yet</h3>' +
                '<p>Start the conversation when you feel ready.</p>' +
            '</div>';
        return;
    }

    let html = '';

    timeline.forEach(function (item) {
        if (item.type === 'message') {
            const own = Number(item.sender_id) === {{ auth()->id() }};
            
            // Handle unsent messages (tombstones)
            if (item.is_unsent) {
                html +=
                    '<div class="cc-message" style="margin:1rem auto;max-width:500px;padding:1rem;background:var(--cc-soft);border:1px solid var(--cc-line);border-radius:12px;text-align:center;color:var(--cc-muted);font-size:13px;font-style:italic;">' +
                        (own ? 'You unsent this message' : 'This message was unsent') +
                        '<div style="font-size:11px;color:var(--cc-muted);margin-top:6px;opacity:0.7;">' +
                            new Date(item.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) +
                        '</div>' +
                    '</div>';
                return; // Skip rendering the actual message
            }
            
            let content = escapeHtml(item.message || '').replace(/\n/g, '<br>');
            let messageClass = own ? 'own' : '';
            if (item.sending) messageClass += ' message-sending';

            if (item.attachment_path || item.attachment_url) {
                // Prefer attachment_url from server, fallback to attachment_path (blob for optimistic)
                let path = item.attachment_url || item.attachment_path;
                
                // If no attachment_url but we have a path, construct it (for older records)
                if (!item.attachment_url && item.attachment_path && !item.attachment_path.startsWith('blob:')) {
                    path = '/storage/' + String(item.attachment_path).replace(/^\/+/, '');
                }
                
                const name = escapeHtml(item.attachment_name || 'Attachment');
                const isImage = item.attachment_type && item.attachment_type.startsWith('image/');

                if (isImage) {
                    content +=
                        '<div style="margin-top:8px">' +
                            '<img src="' + path + '" alt="' + name + '" ' +
                            'style="display:block;max-width:220px;max-height:220px;border-radius:10px;cursor:pointer" ' +
                            (path.startsWith('blob:') ? '' : 'onclick="openImageModal(\'' + path.replace(/'/g, "\\'") + '\')"') +
                            '>' +
                        '</div>';
                } else {
                    content +=
                        '<div style="margin-top:8px">' +
                            '<a href="' + path + '" download="' + name + '" style="color:inherit;text-decoration:underline">' +
                                name +
                            '</a>' +
                        '</div>';
                }
            }

            // Add send status
            let statusHtml = '';
            if (own && item.sending) {
                statusHtml = '<div class="message-status">Sending...</div>';
            } else if (own && item.failed) {
                statusHtml = '<div class="message-status" style="color:#ef4444;">Failed to send · <button style="background:none;border:none;color:#ef4444;cursor:pointer;text-decoration:underline;font-size:0.75rem;padding:0">Retry</button></div>';
            } else if (own && item.id && !item.sending && !item.failed) {
                statusHtml = '<div class="message-status">✓ Sent</div>';
            }
            
            // Three-dot menu button for messages with IDs
            let menuHtml = '';
            if (item.id) {
                const menuBtnClass = own ? 'own' : '';
                menuHtml = `
                    <button
                        class="message-menu-btn ${menuBtnClass}"
                        onclick="openMessageMenu(${item.id}, ${JSON.stringify({sender_id: item.sender_id}).replace(/"/g, '&quot;')}, event)"
                        style="opacity:0;transition:opacity 0.15s ease;cursor:pointer;background:none;border:none;font-size:16px;color:var(--cc-muted);padding:0;margin-left:4px;position:relative;z-index:1"
                        title="Message options"
                    >⋮</button>
                `;
            }

            html +=
                '<div class="cc-message ' + messageClass + ' message-new" ' +
                'onmouseenter="this.querySelector(\'.message-menu-btn\')?.style.setProperty(\'opacity\', \'1\')" ' +
                'onmouseleave="if (!this.querySelector(\'.message-menu\')) this.querySelector(\'.message-menu-btn\')?.style.setProperty(\'opacity\', \'0\')">' +
                    '<div class="cc-bubble-row">' +
                        '<div class="cc-bubble-avatar ' + (own ? 'own' : '') + '">' +
                            (own ? @json(strtoupper(substr(auth()->user()->name, 0, 1))) : escapeHtml((selectedCounselorName || 'C').charAt(0).toUpperCase())) +
                        '</div>' +
                        '<div style="position:relative;">' +
                            '<div class="cc-bubble">' +
                                content +
                                '<div class="cc-bubble-meta">' +
                                    '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>' +
                                    '</svg>' +
                                    '<span>' + new Date(item.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + '</span>' +
                                    menuHtml +
                                '</div>' +
                                statusHtml +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        } else if (item.type === 'video_call') {
            // Render video call event card
            const isCallerMe = Number(item.caller_id) === {{ auth()->id() }};
            let statusText = '';
            
            if (item.status === 'declined') {
                statusText = isCallerMe ? 'Cancelled' : 'Missed video call';
            } else if (item.status === 'accepted' && item.ended_at) {
                const duration = formatDuration(item.duration_seconds);
                statusText = 'Call ended · ' + duration;
            } else if (item.status === 'ringing' || (item.status === 'ended' && !item.accepted_at)) {
                statusText = isCallerMe ? 'Cancelled' : 'Missed video call';
            }
            
            html +=
                '<div class="message-new" style="margin:1.5rem auto;max-width:400px;padding:1rem;background:var(--mc-mint);border:1px solid var(--mc-border);border-radius:0.75rem;text-align:center;color:var(--mc-forest)">' +
                    '<div style="margin-bottom:0.5rem">' +
                        '<svg style="width:2rem;height:2rem;margin:0 auto;display:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 10l4.6-2.3A1 1 0 0121 8.6v6.8a1 1 0 01-1.4.9L15 14M5 6h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/>' +
                        '</svg>' +
                    '</div>' +
                    '<div style="font-weight:800;font-size:0.875rem;margin-bottom:0.25rem">' +
                        (isCallerMe ? 'You started a video call' : 'Incoming video call') +
                    '</div>' +
                    '<div style="font-size:0.8125rem;color:var(--mc-muted);margin-bottom:0.5rem">' +
                        statusText +
                    '</div>' +
                    '<div style="font-size:0.75rem;color:var(--mc-muted);margin-top:0.5rem">' +
                        new Date(item.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) +
                    '</div>' +
                '</div>';
        }
    });

    container.innerHTML = html;
    
    // Scroll to bottom if needed
    if (shouldScroll) {
        container.scrollTop = container.scrollHeight;
    }
}

function handleFileSelect(input) {
    if (!input.files || !input.files[0]) return;

    selectedFile = input.files[0];
    document.getElementById('fileName').textContent = selectedFile.name;
    document.getElementById('filePreview').style.display = 'flex';
}

function clearFile() {
    selectedFile = null;
    document.getElementById('fileInput').value = '';
    document.getElementById('imageInput').value = '';
    document.getElementById('filePreview').style.display = 'none';
}

function sendMessage(event) {
    event.preventDefault();

    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();

    if (!message && !selectedFile) {
        console.warn('[STUDENT_SEND] No message or file');
        return;
    }
    if (!selectedCounselorId) {
        console.warn('[STUDENT_SEND] No counselor selected');
        return;
    }

    // CRITICAL: Capture file BEFORE any clearing
    const fileToSend = selectedFile;

    // Create optimistic message bubble
    const tempId = 'temp-' + Date.now();
    const optimisticMessage = {
        id: tempId,
        temp_id: tempId,
        type: 'message',
        sender_id: {{ auth()->id() }},
        message: message,
        attachment_path: fileToSend ? URL.createObjectURL(fileToSend) : null,
        attachment_name: fileToSend ? fileToSend.name : null,
        attachment_type: fileToSend ? fileToSend.type : null,
        created_at: new Date().toISOString(),
        sending: true
    };

    // Store optimistic message
    if (!window.timelineCache) window.timelineCache = [];
    window.timelineCache.push(optimisticMessage);

    // Render immediately
    renderTimeline(window.timelineCache);
    
    // Clear input UI ONLY (NOT selectedFile global yet)
    messageInput.value = '';
    document.getElementById('fileInput').value = '';
    document.getElementById('imageInput').value = '';
    document.getElementById('filePreview').style.display = 'none';

    const formData = new FormData();
    formData.append('_token', document.querySelector('#messageForm input[name="_token"]').value);
    formData.append('counselor_id', selectedCounselorId);

    if (message) formData.append('message', message);
    if (fileToSend instanceof File) formData.append('attachment', fileToSend, fileToSend.name);

    console.log('[ATTACHMENT_DEBUG]', {
        fileToSend: fileToSend,
        fileName: fileToSend?.name,
        fileType: fileToSend?.type,
        fileSize: fileToSend?.size,
        formDataHasAttachment: formData.has('attachment')
    });
    
    // Log FormData entries
    for (const [key, value] of formData.entries()) {
        console.log('[FORMDATA]', key, value instanceof File ? {name: value.name, type: value.type, size: value.size} : value);
    }

    fetch('{{ route("student.send-message") }}', {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(function (response) { 
        console.log('[STUDENT_RESPONSE]', response.status);
        return response.json().catch(() => ({ success: false, message: `HTTP ${response.status}` }));
    })
    .then(function (data) {
        console.log('[STUDENT_RESULT]', data);
        if (!data.success) {
            console.error('[STUDENT_ERROR]', data.message);
            // Mark as failed - DO NOT clear file
            const optIndex = window.timelineCache.findIndex(m => m.temp_id === tempId);
            if (optIndex !== -1) {
                window.timelineCache[optIndex].sending = false;
                window.timelineCache[optIndex].failed = true;
            }
            lastTimelineSignature = null; // Force re-render
            renderTimeline(window.timelineCache);
            alert('Failed to send message: ' + (data.message || 'Unknown error'));
            return;
        }

        // Clear file ONLY after success
        selectedFile = null;
        
        // Replace optimistic with real message
        const cache = window.timelineCache;
        const optIndex = cache.findIndex(m => m.temp_id === tempId);
        if (optIndex !== -1) {
            // Use attachment_url from backend if available
            const realMessage = data.message;
            if (realMessage.attachment_path && !realMessage.attachment_url) {
                realMessage.attachment_url = '/storage/' + realMessage.attachment_path;
            }
            cache[optIndex] = realMessage;
        }
        lastTimelineSignature = null; // Force re-render
        renderTimeline(cache);
    })
    .catch(function(err) {
        console.error('[STUDENT_EXCEPTION]', err);
        // Mark as failed - DO NOT clear file
        const optIndex = window.timelineCache.findIndex(m => m.temp_id === tempId);
        if (optIndex !== -1) {
            window.timelineCache[optIndex].sending = false;
            window.timelineCache[optIndex].failed = true;
        }
        lastTimelineSignature = null; // Force re-render
        renderTimeline(window.timelineCache);
        alert('Error sending message: ' + err.message);
    });
}

function toggleEmojiPicker() {
    document.getElementById('emojiPicker').classList.toggle('open');
}

function insertEmoji(emoji) {
    const input = document.getElementById('messageInput');
    input.value += emoji;
    input.focus();
    document.getElementById('emojiPicker').classList.remove('open');
}

function openCounselorProfile() {
    if (!selectedCounselorData) return;

    const initial = selectedCounselorData.name.charAt(0).toUpperCase();

    document.getElementById('modalCounselorAvatar').textContent = initial;
    document.getElementById('modalCounselorName').textContent = selectedCounselorData.name;
    document.getElementById('modalCounselorSpecialization').textContent = selectedCounselorData.specialization;
    document.getElementById('modalCounselorStatus').textContent = safeText(selectedCounselorData.status);
    document.getElementById('modalCounselorExperience').textContent = safeText(selectedCounselorData.experience);
    document.getElementById('modalCounselorAvailability').textContent = safeText(selectedCounselorData.availability);
    document.getElementById('modalCounselorEmail').textContent = safeText(selectedCounselorData.email);

    const modal = document.getElementById('counselorProfileModal');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
}

function closeCounselorProfile() {
    const modal = document.getElementById('counselorProfileModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

function openImageModal(src) {
    document.getElementById('modalImage').src = src;
    const modal = document.getElementById('imageModal');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

window.addEventListener('load', function () {
    const first = document.querySelector('.cc-counselor-card');
    if (first) selectCounselor(first);
    startContinuousPolling();
});

// ========== REALTIME POLLING ==========
let messagePollingInterval = null;
let refreshBusy = false;
let lastTimelineSignature = null;

// Continuous polling with change detection
function startContinuousPolling() {
    clearInterval(messagePollingInterval);
    messagePollingInterval = setInterval(function() {
        if (selectedCounselorId && !document.hidden) {
            refreshStudentConversation();
        }
    }, 1200); // Poll every 1.2 seconds
}

async function refreshStudentConversation() {
    if (refreshBusy || !selectedCounselorId || document.hidden) return;
    
    refreshBusy = true;
    try {
        const response = await fetch('/student/api/counselor/' + selectedCounselorId + '/messages');
        const data = await response.json();
        const timeline = data.timeline || data.messages || [];
        
        // Change detection: only render if timeline changed
        const newSignature = JSON.stringify(timeline.map(t => t.id));
        if (lastTimelineSignature !== newSignature) {
            lastTimelineSignature = newSignature;
            renderTimeline(timeline);
        }
    } catch (err) {
        console.error('[REFRESH_ERROR]', err);
    } finally {
        refreshBusy = false;
    }
}

// Listen for video call updates
window.addEventListener('minsuans:video-call-updated', function() {
    if (selectedCounselorId) {
        console.log('[VIDEO_CALL_UPDATE] Refreshing messages');
        lastTimelineSignature = null; // Force refresh on next poll
        refreshStudentConversation();
    }
});

// ========== ANIMATION STYLES ==========
const animationStyle = document.createElement('style');
animationStyle.textContent = `
    @keyframes messageEnter {
        from {
            opacity: 0;
            transform: translateY(8px) scale(0.98);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    .message-new {
        animation: messageEnter 0.22s ease-out;
    }
    
    .message-sending {
        opacity: 0.7;
    }
    
    .message-status {
        font-size: 0.75rem;
        color: var(--mc-muted, #888);
        margin-top: 0.25rem;
    }
`;
document.head.appendChild(animationStyle);

// ========== UI EVENT LISTENERS ==========
document.addEventListener('click', function (event) {
    const picker = document.getElementById('emojiPicker');
    const emojiButton = event.target.closest('.cc-tool[onclick*="toggleEmojiPicker"]');

    if (picker && !picker.contains(event.target) && !emojiButton) {
        picker.classList.remove('open');
    }
});

document.getElementById('counselorProfileModal').addEventListener('click', function (event) {
    if (event.target === this) closeCounselorProfile();
});

document.getElementById('imageModal').addEventListener('click', function (event) {
    if (event.target === this) closeImageModal();
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeCounselorProfile();
        closeImageModal();
        document.getElementById('emojiPicker').classList.remove('open');
    }
});

// ========== MESSAGE DELETE/UNSEND ==========
async function deleteMessageForMe(messageId) {
    const endpoint = `/student/api/messages/${messageId}/delete-for-me`;
    
    try {
        const response = await fetch(endpoint, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to delete message');
        }
        
        // Refresh timeline
        lastTimelineSignature = null;
        loadMessages(selectedCounselorId);
    } catch (error) {
        console.error('Delete failed:', error);
        alert('Failed to delete message: ' + error.message);
    }
}

async function unsendMessage(messageId) {
    if (!confirm('Unsend this message for everyone? This cannot be undone.')) return;
    
    const endpoint = `/student/api/messages/${messageId}/unsend`;
    
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to unsend message');
        }
        
        // Refresh timeline
        lastTimelineSignature = null;
        loadMessages(selectedCounselorId);
    } catch (error) {
        console.error('Unsend failed:', error);
        alert('Failed to unsend message: ' + error.message);
    }
}

// Open three-dot menu for message
function openMessageMenu(messageId, messageData, event) {
    event.stopPropagation();
    
    // Close any existing menus
    const existingMenus = document.querySelectorAll('.message-menu');
    existingMenus.forEach(m => m.remove());
    
    const isOwn = messageData.sender_id === {{ auth()->id() }};
    
    const menu = document.createElement('div');
    menu.className = 'message-menu';
    menu.style.cssText = `
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 4px;
        background: white;
        border: 1px solid var(--cc-line);
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(16,63,50,0.12);
        z-index: 1000;
        min-width: 180px;
        overflow: hidden;
    `;
    
    // Delete for me option (always available)
    const deleteBtn = document.createElement('button');
    deleteBtn.style.cssText = `
        display: block;
        width: 100%;
        padding: 10px 14px;
        border: none;
        background: none;
        text-align: left;
        color: var(--cc-text);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s ease;
        border-bottom: 1px solid var(--cc-line);
    `;
    deleteBtn.textContent = 'Delete for me';
    deleteBtn.onmouseover = () => deleteBtn.style.background = 'var(--cc-mint-2)';
    deleteBtn.onmouseout = () => deleteBtn.style.background = 'none';
    deleteBtn.onclick = () => {
        menu.remove();
        deleteMessageForMe(messageId);
    };
    menu.appendChild(deleteBtn);
    
    // Unsend option (only for own messages)
    if (isOwn) {
        const unsendBtn = document.createElement('button');
        unsendBtn.style.cssText = `
            display: block;
            width: 100%;
            padding: 10px 14px;
            border: none;
            background: none;
            text-align: left;
            color: #B94A48;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease;
        `;
        unsendBtn.textContent = 'Unsend for everyone';
        unsendBtn.onmouseover = () => unsendBtn.style.background = 'rgba(185, 74, 72, 0.08)';
        unsendBtn.onmouseout = () => unsendBtn.style.background = 'none';
        unsendBtn.onclick = () => {
            menu.remove();
            unsendMessage(messageId);
        };
        menu.appendChild(unsendBtn);
    }
    
    // Close menu when clicking outside
    const closeMenu = () => {
        menu.remove();
        document.removeEventListener('click', closeMenu);
    };
    setTimeout(() => document.addEventListener('click', closeMenu), 0);
    
    event.target.parentElement.appendChild(menu);
}</script>

@include('components.video-call-overlay')

@endsection
