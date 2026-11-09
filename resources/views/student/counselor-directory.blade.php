@extends('layouts.app')

@section('title', 'Counselor Directory · MAGIREM AI')

@section('content')

<style>
.cd-page,
.cd-page *,
.cd-page *::before,
.cd-page *::after { box-sizing: border-box; }

.cd-page {
    --cd-forest:#103F32;
    --cd-forest-2:#174F3F;
    --cd-emerald:#236B55;
    --cd-mint:#E6F1EB;
    --cd-mint-2:#D8EBE2;
    --cd-ivory:#F8FAF7;
    --cd-white:#FFFFFF;
    --cd-gold:#F4C84E;
    --cd-text:#263A33;
    --cd-muted:#71837A;
    --cd-soft:#91A098;
    --cd-line:rgba(16,63,50,.10);
    --cd-shadow:0 18px 48px rgba(16,63,50,.07);

    width:100%;
    max-width:1500px;
    margin:0 auto;
    padding:10px 18px 26px;
    color:var(--cd-text);
    font-family:Inter,"Plus Jakarta Sans",ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}

.cd-head {
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:24px;
    margin-bottom:16px;
}

.cd-eyebrow {
    display:inline-flex;
    align-items:center;
    gap:9px;
    margin-bottom:7px;
    color:var(--cd-emerald);
    font-size:10px;
    font-weight:850;
    letter-spacing:.14em;
    text-transform:uppercase;
}

.cd-eyebrow::before {
    content:"";
    width:20px;
    height:2px;
    border-radius:999px;
    background:var(--cd-gold);
}

.cd-head h1 {
    margin:0;
    color:var(--cd-forest);
    font-size:clamp(31px,3vw,46px);
    line-height:1;
    letter-spacing:-.045em;
    font-weight:840;
}

.cd-head p {
    max-width:760px;
    margin:10px 0 0;
    color:var(--cd-muted);
    font-size:12px;
    line-height:1.65;
}

.cd-actions {
    display:flex;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
}

.cd-btn {
    min-height:40px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:0 13px;
    border:1px solid var(--cd-line);
    border-radius:11px;
    background:#fff;
    color:var(--cd-forest)!important;
    text-decoration:none;
    font-size:10px;
    font-weight:800;
    transition:.18s ease;
}

.cd-btn:hover {
    transform:translateY(-1px);
    box-shadow:0 9px 22px rgba(16,63,50,.06);
}

.cd-btn.primary {
    border-color:var(--cd-forest);
    background:var(--cd-forest);
    color:#fff!important;
}

.cd-btn.primary:hover { background:var(--cd-forest-2); }

.cd-btn svg {
    width:15px;
    height:15px;
}

.cd-hero {
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:minmax(0,1.15fr) minmax(260px,.85fr);
    gap:28px;
    align-items:center;
    margin-bottom:18px;
    padding:26px 28px;
    border:1px solid var(--cd-line);
    border-radius:20px;
    background:
        radial-gradient(circle at 91% 20%,rgba(216,235,226,.72),transparent 21rem),
        #fff;
    box-shadow:var(--cd-shadow);
}

.cd-hero::after {
    content:"";
    position:absolute;
    right:-86px;
    bottom:-120px;
    width:300px;
    height:300px;
    border:1px solid rgba(35,107,85,.10);
    border-radius:50%;
}

.cd-kicker {
    display:block;
    margin-bottom:8px;
    color:var(--cd-emerald);
    font-size:9px;
    font-weight:850;
    letter-spacing:.13em;
    text-transform:uppercase;
}

.cd-hero h2 {
    max-width:720px;
    margin:0;
    color:var(--cd-forest);
    font-size:clamp(24px,2.4vw,36px);
    line-height:1.08;
    letter-spacing:-.035em;
    font-weight:820;
}

.cd-hero p {
    max-width:700px;
    margin:10px 0 0;
    color:var(--cd-muted);
    font-size:11px;
    line-height:1.65;
}

.cd-hero-stats {
    position:relative;
    z-index:2;
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:8px;
}

.cd-stat {
    padding:15px;
    border:1px solid rgba(16,63,50,.08);
    border-radius:14px;
    background:rgba(248,250,247,.9);
}

.cd-stat span {
    display:block;
    color:#83948C;
    font-size:8px;
    font-weight:800;
    letter-spacing:.08em;
    text-transform:uppercase;
}

.cd-stat strong {
    display:block;
    margin-top:5px;
    color:var(--cd-forest);
    font-size:24px;
    line-height:1;
    font-weight:840;
}

.cd-section-head {
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:18px;
    margin:22px 2px 12px;
}

.cd-section-head h3 {
    margin:0;
    color:var(--cd-forest);
    font-size:18px;
    font-weight:820;
    letter-spacing:-.02em;
}

.cd-section-head p {
    margin:5px 0 0;
    color:var(--cd-muted);
    font-size:10px;
}

.cd-count-pill {
    display:inline-flex;
    align-items:center;
    gap:6px;
    min-height:30px;
    padding:0 10px;
    border-radius:999px;
    background:var(--cd-mint);
    color:var(--cd-forest);
    font-size:8.5px;
    font-weight:800;
}

.cd-grid {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
}

.cd-card {
    min-width:0;
    display:flex;
    flex-direction:column;
    overflow:hidden;
    border:1px solid var(--cd-line);
    border-radius:16px;
    background:#fff;
    box-shadow:0 8px 24px rgba(16,63,50,.045);
    transition:.18s ease;
}

.cd-card:hover {
    transform:translateY(-3px);
    border-color:rgba(35,107,85,.18);
    box-shadow:0 16px 36px rgba(16,63,50,.08);
}

.cd-card-top {
    padding:16px 16px 13px;
    display:flex;
    align-items:center;
    gap:12px;
    border-bottom:1px solid rgba(16,63,50,.07);
    background:
        radial-gradient(circle at 100% 0%,rgba(216,235,226,.70),transparent 10rem),
        #FBFCFB;
}

.cd-avatar {
    width:50px;
    height:50px;
    flex:0 0 50px;
    display:grid;
    place-items:center;
    overflow:hidden;
    border-radius:14px;
    background:var(--cd-mint);
    color:var(--cd-forest);
    font-size:14px;
    font-weight:850;
}

.cd-avatar img {
    width:100%;
    height:100%;
    object-fit:cover;
}

.cd-card-name {
    min-width:0;
    flex:1;
}

.cd-card-name h4 {
    margin:0;
    overflow:hidden;
    color:var(--cd-forest);
    font-size:12px;
    font-weight:820;
    white-space:nowrap;
    text-overflow:ellipsis;
}

.cd-card-name p {
    margin:4px 0 0;
    overflow:hidden;
    color:var(--cd-muted);
    font-size:8.5px;
    white-space:nowrap;
    text-overflow:ellipsis;
}

.cd-status {
    margin-top:7px;
    display:inline-flex;
    align-items:center;
    gap:5px;
    min-height:24px;
    padding:0 8px;
    border-radius:999px;
    background:#EEF4F1;
    color:var(--cd-forest);
    font-size:7.8px;
    font-weight:800;
}

.cd-status-dot {
    width:6px;
    height:6px;
    border-radius:50%;
    background:#9BABA3;
}

.cd-status-dot.online { background:#39A879; }
.cd-status-dot.away { background:#D3AA36; }
.cd-status-dot.busy { background:#B8665E; }

.cd-card-body {
    flex:1;
    padding:14px 16px;
}

.cd-info-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
}

.cd-info {
    min-width:0;
    padding:10px;
    border-radius:11px;
    background:var(--cd-ivory);
}

.cd-info span {
    display:flex;
    align-items:center;
    gap:6px;
    color:#83948C;
    font-size:7.5px;
    font-weight:800;
    letter-spacing:.05em;
    text-transform:uppercase;
}

.cd-info span svg {
    width:12px;
    height:12px;
    color:var(--cd-emerald);
}

.cd-info strong {
    display:block;
    margin-top:5px;
    overflow:hidden;
    color:var(--cd-forest);
    font-size:8.8px;
    line-height:1.45;
    font-weight:780;
    text-overflow:ellipsis;
}

.cd-bio {
    margin:10px 0 0;
    min-height:52px;
    padding:11px;
    border-left:3px solid var(--cd-mint-2);
    border-radius:10px;
    background:#FBFCFB;
    color:var(--cd-muted);
    font-size:8.8px;
    line-height:1.6;
}

.cd-card-actions {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:7px;
    padding:0 16px 16px;
}

.cd-card-action {
    min-height:36px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    padding:0 9px;
    border:1px solid var(--cd-line);
    border-radius:9px;
    background:#fff;
    color:var(--cd-forest);
    text-decoration:none;
    font:inherit;
    font-size:8.5px;
    font-weight:800;
    cursor:pointer;
}

.cd-card-action.primary {
    border-color:var(--cd-forest);
    background:var(--cd-forest);
    color:#fff;
}

.cd-card-action svg {
    width:13px;
    height:13px;
}

.cd-empty {
    grid-column:1/-1;
    padding:70px 28px;
    border:1px solid var(--cd-line);
    border-radius:18px;
    background:#fff;
    text-align:center;
}

.cd-empty-icon {
    width:56px;
    height:56px;
    display:grid;
    place-items:center;
    margin:0 auto 14px;
    border-radius:16px;
    background:var(--cd-mint);
    color:var(--cd-forest);
}

.cd-empty-icon svg {
    width:25px;
    height:25px;
}

.cd-empty h3 {
    margin:0;
    color:var(--cd-forest);
    font-size:14px;
    font-weight:820;
}

.cd-empty p {
    max-width:440px;
    margin:7px auto 0;
    color:var(--cd-muted);
    font-size:9.5px;
    line-height:1.6;
}

.cd-guide {
    margin-top:18px;
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
}

.cd-guide-card {
    padding:15px;
    border:1px solid var(--cd-line);
    border-radius:14px;
    background:#fff;
}

.cd-guide-icon {
    width:36px;
    height:36px;
    display:grid;
    place-items:center;
    margin-bottom:10px;
    border-radius:11px;
    background:var(--cd-mint);
    color:var(--cd-forest);
}

.cd-guide-icon svg {
    width:16px;
    height:16px;
}

.cd-guide-card strong {
    display:block;
    color:var(--cd-forest);
    font-size:10px;
}

.cd-guide-card p {
    margin:5px 0 0;
    color:var(--cd-muted);
    font-size:8.5px;
    line-height:1.55;
}

/* MODAL */
.cd-modal {
    position:fixed;
    inset:0;
    z-index:8000;
    display:none;
    align-items:center;
    justify-content:center;
    padding:24px;
    background:rgba(8,24,19,.62);
    backdrop-filter:blur(8px);
}

.cd-modal.open { display:flex; }

.cd-modal-card {
    width:min(560px,94vw);
    max-height:88vh;
    overflow:auto;
    border-radius:18px;
    background:#fff;
    box-shadow:0 30px 90px rgba(8,24,19,.28);
}

.cd-modal-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:17px 18px;
    border-bottom:1px solid var(--cd-line);
}

.cd-modal-head h2 {
    margin:0;
    color:var(--cd-forest);
    font-size:14px;
    font-weight:820;
}

.cd-modal-close {
    width:34px;
    height:34px;
    display:grid;
    place-items:center;
    border:0;
    border-radius:9px;
    background:var(--cd-ivory);
    color:var(--cd-forest);
    cursor:pointer;
}

.cd-modal-close svg {
    width:16px;
    height:16px;
}

.cd-modal-body {
    padding:18px;
}

.cd-selected-counselor {
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:16px;
    padding:11px;
    border:1px solid rgba(35,107,85,.10);
    border-radius:12px;
    background:var(--cd-mint);
}

.cd-selected-counselor span:first-child {
    width:38px;
    height:38px;
    display:grid;
    place-items:center;
    border-radius:11px;
    background:var(--cd-forest);
    color:#fff;
    font-size:11px;
    font-weight:850;
}

.cd-selected-counselor strong {
    display:block;
    color:var(--cd-forest);
    font-size:10px;
}

.cd-selected-counselor small {
    display:block;
    margin-top:2px;
    color:var(--cd-muted);
    font-size:8px;
}

.cd-form {
    display:grid;
    gap:13px;
}

.cd-field label {
    display:block;
    margin-bottom:6px;
    color:var(--cd-forest);
    font-size:9px;
    font-weight:800;
}

.cd-field select,
.cd-field textarea,
.cd-field input {
    width:100%;
    border:1px solid var(--cd-line);
    border-radius:10px;
    background:var(--cd-ivory);
    color:var(--cd-text);
    font:inherit;
    font-size:10px;
}

.cd-field select,
.cd-field input {
    min-height:40px;
    padding:0 11px;
}

.cd-field textarea {
    min-height:115px;
    padding:10px 11px;
    line-height:1.5;
    resize:vertical;
}

.cd-field select:focus,
.cd-field textarea:focus,
.cd-field input:focus {
    outline:none;
    border-color:rgba(35,107,85,.38);
    background:#fff;
    box-shadow:0 0 0 3px rgba(35,107,85,.07);
}

.cd-modal-actions {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
    margin-top:3px;
}

.cd-submit,
.cd-cancel {
    min-height:40px;
    border-radius:10px;
    font:inherit;
    font-size:9px;
    font-weight:800;
    cursor:pointer;
}

.cd-submit {
    border:1px solid var(--cd-forest);
    background:var(--cd-forest);
    color:#fff;
}

.cd-cancel {
    border:1px solid var(--cd-line);
    background:#fff;
    color:var(--cd-forest);
}

@media(max-width:1120px) {
    .cd-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
}

@media(max-width:760px) {
    .cd-page { padding-inline:12px; }
    .cd-head { align-items:flex-start; flex-direction:column; }
    .cd-actions { justify-content:flex-start; }
    .cd-hero { grid-template-columns:1fr; }
    .cd-grid { grid-template-columns:1fr; }
    .cd-guide { grid-template-columns:1fr; }
}

@media(max-width:520px) {
    .cd-card-actions,
    .cd-modal-actions { grid-template-columns:1fr; }
}
</style>

<div class="cd-page">
    <header class="cd-head">
        <div>
            <span class="cd-eyebrow">Professional support</span>
            <h1>Counselor Directory</h1>
            <p>Find the university counselor whose availability and focus fit your needs, then continue to private chat or submit a counseling request.</p>
        </div>

        <div class="cd-actions">
            <a href="{{ route('student.counselor-chat') }}" class="cd-btn">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4-4 7-9 7-1.4 0-2.8-.25-4-.72L3 19l1.3-3.4A6.3 6.3 0 013 12c0-4 4-7 9-7s9 3 9 7z"/>
                </svg>
                Counselor chat
            </a>

            <a href="{{ route('student.request-appointment') }}" class="cd-btn primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                </svg>
                Request appointment
            </a>
        </div>
    </header>

    <section class="cd-hero">
        <div>
            <span class="cd-kicker">Choose support that feels right</span>
            <h2>Professional help, presented with clarity instead of clutter.</h2>
            <p>Review each counselor’s specialty, current status, experience, and profile before deciding how you want to connect.</p>
        </div>

        <div class="cd-hero-stats">
            <div class="cd-stat">
                <span>Counselors</span>
                <strong>{{ $counselors->count() }}</strong>
            </div>
            <div class="cd-stat">
                <span>Available now</span>
                <strong>{{ $counselors->where('status', 'online')->count() }}</strong>
            </div>
        </div>
    </section>

    <div class="cd-section-head">
        <div>
            <h3>University counselors</h3>
            <p>Choose a counselor to request support or continue to the messaging workspace.</p>
        </div>
        <span class="cd-count-pill">{{ $counselors->count() }} {{ $counselors->count() === 1 ? 'counselor' : 'counselors' }}</span>
    </div>

    <section class="cd-grid">
        @forelse($counselors as $counselor)
            @php
                $status = strtolower($counselor->status ?? 'offline');
                $specialization = $counselor->specialization ?? 'Student wellness counseling';
                $experience = $counselor->years_of_experience
                    ? $counselor->years_of_experience . ' years'
                    : 'Not specified';
                $availability = is_string($counselor->availability_schedule ?? null)
                    ? $counselor->availability_schedule
                    : 'By schedule';
                $bio = $counselor->bio ?: 'University counselor available to support students with personal, academic, and emotional concerns.';
            @endphp

            <article class="cd-card">
                <div class="cd-card-top">
                    <div class="cd-avatar">
                        @if($counselor->profile_picture)
                            <img src="{{ asset($counselor->profile_picture) }}" alt="{{ $counselor->name }}">
                        @else
                            {{ strtoupper(substr($counselor->name, 0, 1)) }}
                        @endif
                    </div>

                    <div class="cd-card-name">
                        <h4>{{ $counselor->name }}</h4>
                        <p>{{ $specialization }}</p>

                        <span class="cd-status">
                            <span class="cd-status-dot {{ $status }}"></span>
                            {{ ucfirst($status) }}
                        </span>
                    </div>
                </div>

                <div class="cd-card-body">
                    <div class="cd-info-grid">
                        <div class="cd-info">
                            <span>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Experience
                            </span>
                            <strong>{{ $experience }}</strong>
                        </div>

                        <div class="cd-info">
                            <span>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                                </svg>
                                Availability
                            </span>
                            <strong>{{ $availability }}</strong>
                        </div>
                    </div>

                    <p class="cd-bio">{{ $bio }}</p>
                </div>

                <div class="cd-card-actions">
                    <a href="{{ route('student.counselor-chat') }}" class="cd-card-action">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4-4 7-9 7-1.4 0-2.8-.25-4-.72L3 19l1.3-3.4A6.3 6.3 0 013 12c0-4 4-7 9-7s9 3 9 7z"/>
                        </svg>
                        Open chat
                    </a>

                    <button
                        type="button"
                        class="cd-card-action primary"
                        data-counselor-id="{{ $counselor->id }}"
                        data-counselor-name="{{ $counselor->name }}"
                        onclick="openRequestModal(this)"
                    >
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14M5 12h14"/>
                        </svg>
                        Request support
                    </button>
                </div>
            </article>
        @empty
            <div class="cd-empty">
                <span class="cd-empty-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8"/>
                    </svg>
                </span>
                <h3>No counselors are listed right now</h3>
                <p>You can still submit a general appointment request or return later when counselor availability has been updated.</p>
                <div style="margin-top:14px">
                    <a href="{{ route('student.request-appointment') }}" class="cd-btn primary">Request appointment</a>
                </div>
            </div>
        @endforelse
    </section>

    <section class="cd-guide">
        <article class="cd-guide-card">
            <span class="cd-guide-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 12a4 4 0 100-8 4 4 0 000 8zm7 9a7 7 0 00-14 0"/>
                </svg>
            </span>
            <strong>Review profiles</strong>
            <p>Compare specialty, experience, availability, and current status before requesting support.</p>
        </article>

        <article class="cd-guide-card">
            <span class="cd-guide-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4-4 7-9 7-1.4 0-2.8-.25-4-.72L3 19l1.3-3.4A6.3 6.3 0 013 12c0-4 4-7 9-7s9 3 9 7z"/>
                </svg>
            </span>
            <strong>Continue privately</strong>
            <p>Move into Counselor Chat whenever a direct conversation is the better next step.</p>
        </article>

        <article class="cd-guide-card">
            <span class="cd-guide-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                </svg>
            </span>
            <strong>Schedule support</strong>
            <p>Submit a counseling request and suggest a preferred date when you want a planned session.</p>
        </article>
    </section>

    <div class="cd-modal" id="requestModal" aria-hidden="true">
        <div class="cd-modal-card">
            <div class="cd-modal-head">
                <h2>Request counseling</h2>
                <button type="button" class="cd-modal-close" onclick="closeRequestModal()" aria-label="Close request form">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form action="{{ route('student.counseling-request') }}" method="POST" class="cd-modal-body cd-form">
                @csrf
                <input type="hidden" id="counselor_id" name="counselor_id">

                <div class="cd-selected-counselor">
                    <span id="requestCounselorInitial">C</span>
                    <span>
                        <strong id="counselor_name">Counselor</strong>
                        <small>Selected counselor</small>
                    </span>
                </div>

                <div class="cd-field">
                    <label for="request_category">Category of concern</label>
                    <select name="category" id="request_category" required>
                        <option value="">Select a category</option>
                        <option value="academic">Academic Stress</option>
                        <option value="anxiety">Anxiety</option>
                        <option value="personal">Personal Issues</option>
                        <option value="emotional">Emotional Concerns</option>
                        <option value="relationships">Relationships</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="cd-field">
                    <label for="request_description">What would you like support with?</label>
                    <textarea name="description" id="request_description" required placeholder="Describe your situation in a few sentences..."></textarea>
                </div>

                <div class="cd-field">
                    <label for="request_preferred_date">Preferred date <span style="color:#83948C;font-weight:600">(optional)</span></label>
                    <input type="date" name="preferred_date" id="request_preferred_date">
                </div>

                <div class="cd-modal-actions">
                    <button type="button" class="cd-cancel" onclick="closeRequestModal()">Cancel</button>
                    <button type="submit" class="cd-submit">Submit request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRequestModal(button) {
    const id = button.dataset.counselorId;
    const name = button.dataset.counselorName || 'Counselor';

    document.getElementById('counselor_id').value = id;
    document.getElementById('counselor_name').textContent = name;
    document.getElementById('requestCounselorInitial').textContent = name.charAt(0).toUpperCase();

    const modal = document.getElementById('requestModal');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
}

function closeRequestModal() {
    const modal = document.getElementById('requestModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

document.getElementById('requestModal').addEventListener('click', function(event) {
    if (event.target === this) closeRequestModal();
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeRequestModal();
});
</script>

@endsection
