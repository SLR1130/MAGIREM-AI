@extends('layouts.app')

@section('title', 'Appointments · MAGIREM AI')

@section('content')
<style>
    .msa,
    .msa *,
    .msa *::before,
    .msa *::after { box-sizing:border-box; }

    .msa {
        --a-forest:#103f32;
        --a-forest-2:#174f3f;
        --a-emerald:#236b55;
        --a-mint:#e6f1eb;
        --a-mint-2:#d8ebe2;
        --a-ivory:#f8faf7;
        --a-white:#fff;
        --a-gold:#f4c84e;
        --a-text:#263a33;
        --a-muted:#708179;
        --a-line:rgba(16,63,50,.11);
        --a-line-strong:rgba(16,63,50,.18);
        --a-shadow:0 16px 42px rgba(16,63,50,.07);
        --a-shadow-lg:0 24px 60px rgba(16,63,50,.10);
        --a-ease:cubic-bezier(.22,1,.36,1);
        color:var(--a-text);
        font-family:Inter,"Plus Jakarta Sans",ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
        padding:0 0 36px;
    }

    body:has(.msa) .content-wrapper {
        box-sizing:border-box;
        max-width:1440px !important;
        width:100% !important;
    }

    .msa-head {
        display:flex;
        align-items:flex-end;
        justify-content:space-between;
        gap:24px;
        margin-bottom:20px;
    }

    .msa-eyebrow {
        display:inline-flex;
        align-items:center;
        gap:8px;
        margin-bottom:8px;
        color:var(--a-emerald);
        font-size:10px;
        font-weight:800;
        letter-spacing:.14em;
        text-transform:uppercase;
    }

    .msa-eyebrow::before {
        content:"";
        width:22px;
        height:2px;
        border-radius:999px;
        background:var(--a-gold);
    }

    .msa-head h1 {
        margin:0;
        color:var(--a-forest);
        font-size:clamp(28px,2.6vw,36px);
        line-height:1.03;
        letter-spacing:-.045em;
        font-weight:820;
    }

    .msa-head p {
        max-width:700px;
        margin:7px 0 0;
        color:var(--a-muted);
        font-size:13px;
        line-height:1.7;
    }

    .msa-head-actions {
        display:flex;
        align-items:center;
        gap:9px;
        flex-wrap:wrap;
        justify-content:flex-end;
    }

    .msa-btn {
        min-height:40px;
        display:inline-flex;
        align-items:center;
        gap:8px;
        max-width:100%;
        padding:0 14px;
        border-radius:11px;
        text-decoration:none;
        font-size:10.5px;
        font-weight:800;
        transition:.18s var(--a-ease);
    }

    .msa-btn svg { width:16px;height:16px; }

    .msa-btn-secondary {
        color:var(--a-forest) !important;
        background:#fff;
        border:1px solid var(--a-line);
    }

    .msa-btn-primary {
        color:#fff !important;
        background:var(--a-forest);
        border:1px solid var(--a-forest);
        box-shadow:0 10px 24px rgba(16,63,50,.16);
    }

    .msa-btn:hover { transform:translateY(-1px); }

    .msa-hero {
        position:relative;
        overflow:hidden;
        display:grid;
        grid-template-columns:minmax(0,1.1fr) minmax(280px,.9fr);
        gap:26px;
        align-items:center;
        padding:30px 32px;
        margin-bottom:16px;
        border:1px solid var(--a-line);
        border-radius:21px;
        background:
            radial-gradient(circle at 91% 14%,rgba(216,235,226,.92),transparent 18rem),
            linear-gradient(135deg,#fff 0%,#fcfdfc 58%,#eef6f2 100%);
        box-shadow:var(--a-shadow);
    }

    .msa-hero::after {
        content:"";
        position:absolute;
        right:-58px;
        bottom:-104px;
        width:230px;
        height:230px;
        border-radius:50%;
        border:36px solid rgba(35,107,85,.045);
        pointer-events:none;
    }

    .msa-hero-copy { position:relative;z-index:1;max-width:690px; }
    .msa-kicker {
        color:var(--a-emerald);
        font-size:9.5px;
        font-weight:800;
        letter-spacing:.13em;
        text-transform:uppercase;
    }

    .msa-hero h2 {
        margin:7px 0 0;
        color:var(--a-forest);
        font-size:clamp(25px,2.4vw,36px);
        line-height:1.09;
        letter-spacing:-.035em;
        font-weight:820;
    }

    .msa-hero p {
        margin:12px 0 0;
        color:var(--a-muted);
        font-size:13px;
        line-height:1.72;
    }

    .msa-hero-card {
        position:relative;
        z-index:1;
        padding:18px;
        border:1px solid rgba(16,63,50,.08);
        border-radius:16px;
        background:rgba(255,255,255,.75);
        backdrop-filter:blur(12px);
        -webkit-backdrop-filter:blur(12px);
    }

    .msa-hero-card-icon {
        width:38px;
        height:38px;
        display:grid;
        place-items:center;
        margin-bottom:12px;
        border-radius:12px;
        background:var(--a-mint);
        color:var(--a-forest);
    }

    .msa-hero-card-icon svg { width:18px;height:18px; }
    .msa-hero-card strong { display:block;color:var(--a-forest);font-size:13px; }
    .msa-hero-card p { margin:6px 0 0;font-size:11px;line-height:1.55; }

    .msa-section-head {
        display:flex;
        align-items:flex-end;
        justify-content:space-between;
        gap:18px;
        margin:0 0 13px;
    }

    .msa-section-head h3 {
        margin:5px 0 0;
        color:var(--a-forest);
        font-size:19px;
        line-height:1.2;
        letter-spacing:-.025em;
        font-weight:800;
    }

    .msa-section-head p {
        margin:6px 0 0;
        color:var(--a-muted);
        font-size:11px;
        line-height:1.55;
    }

    .msa-count {
        flex:0 0 auto;
        min-height:29px;
        display:inline-flex;
        align-items:center;
        padding:0 10px;
        border-radius:999px;
        background:var(--a-mint);
        color:var(--a-forest);
        font-size:9.5px;
        font-weight:800;
    }

    .msa-head > *, .msa-card-top > *, .msa-history-row > * { min-width:0; }
    .msa-section { margin-top:8px; }
    .msa-history-section { margin-top:28px; }
    .msa-history-list { display:grid;gap:8px; }
    .msa-history-row { display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:14px;padding:14px 16px;border:1px solid var(--a-line);border-radius:14px;background:#fff; }
    .msa-history-row h4 { margin:4px 0 0;color:var(--a-forest);font-size:13px;line-height:1.3; }
    .msa-history-row p { margin:3px 0 0;color:var(--a-muted);font-size:10.5px;line-height:1.45; }
    .msa-history-date { color:var(--a-muted);font-size:10px;font-weight:750;text-align:right;white-space:nowrap; }
    .msa-history-link { color:var(--a-emerald);font-size:10px;font-weight:800;text-decoration:none;white-space:nowrap; }
    .msa-history-link:hover,.msa-history-link:focus-visible { color:var(--a-forest);text-decoration:underline;outline:none; }
    .msa-support-strip { display:flex;align-items:center;justify-content:space-between;gap:18px;margin-top:28px;padding:16px 18px;border:1px solid var(--a-line);border-radius:15px;background:linear-gradient(90deg,#fff,#f3f8f5); }
    .msa-support-strip h3 { margin:4px 0 0;color:var(--a-forest);font-size:14px;letter-spacing:-.015em; }
    .msa-support-strip p { margin:4px 0 0;color:var(--a-muted);font-size:10.5px;line-height:1.55; }
    .msa-support-actions { display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end; }
    .msa-list {
        display:grid;
        gap:13px;
        margin-bottom:16px;
    }

    .msa-card {
        position:relative;
        overflow:hidden;
        padding:20px;
        border:1px solid var(--a-line);
        border-radius:17px;
        background:#fff;
        box-shadow:0 10px 28px rgba(16,63,50,.055);
        transition:.18s var(--a-ease);
    }

    .msa-card:hover {
        transform:translateY(-1px);
        box-shadow:var(--a-shadow);
    }

    .msa-card::before {
        content:"";
        position:absolute;
        top:0;
        left:0;
        bottom:0;
        width:3px;
        background:var(--a-emerald);
    }

    .msa-card-top {
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:18px;
        margin-bottom:16px;
    }

    .msa-counselor {
        min-width:0;
        display:flex;
        align-items:center;
        gap:12px;
    }

    .msa-counselor-avatar {
        flex:0 0 42px;
        width:42px;
        height:42px;
        display:grid;
        place-items:center;
        border-radius:13px;
        background:var(--a-mint);
        color:var(--a-forest);
        font-size:13px;
        font-weight:850;
    }

    .msa-counselor-copy { min-width:0; }
    .msa-counselor-copy small {
        display:block;
        color:var(--a-emerald);
        font-size:8.8px;
        font-weight:800;
        letter-spacing:.11em;
        text-transform:uppercase;
    }

    .msa-counselor-copy h4 {
        margin:5px 0 0;
        color:var(--a-forest);
        font-size:16px;
        line-height:1.2;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .msa-counselor-copy p {
        margin:4px 0 0;
        color:var(--a-muted);
        font-size:10.5px;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .msa-status {
        flex:0 0 auto;
        min-height:29px;
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:0 10px;
        border-radius:999px;
        background:var(--a-mint);
        color:var(--a-forest);
        font-size:9px;
        font-weight:800;
        text-transform:uppercase;
        letter-spacing:.07em;
    }

    .msa-status::before {
        content:"";
        width:6px;
        height:6px;
        border-radius:50%;
        background:var(--a-emerald);
    }

    .msa-meta-grid {
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:10px;
        margin-bottom:12px;
    }

    .msa-meta {
        min-width:0;
        padding:12px;
        border:1px solid rgba(16,63,50,.07);
        border-radius:12px;
        background:var(--a-ivory);
    }

    .msa-meta span {
        display:block;
        color:var(--a-muted);
        font-size:8.6px;
        font-weight:800;
        letter-spacing:.09em;
        text-transform:uppercase;
    }

    .msa-meta strong {
        display:block;
        margin-top:6px;
        color:var(--a-forest);
        font-size:11px;
        line-height:1.45;
        overflow-wrap:anywhere;
    }

    .msa-note {
        padding:14px 15px;
        border:1px solid rgba(16,63,50,.07);
        border-radius:12px;
        background:linear-gradient(135deg,#f1f8f4,#fff);
    }

    .msa-note-label {
        display:flex;
        align-items:center;
        gap:7px;
        margin-bottom:6px;
        color:var(--a-forest);
        font-size:9px;
        font-weight:800;
        letter-spacing:.09em;
        text-transform:uppercase;
    }

    .msa-note-label svg { width:14px;height:14px; }
    .msa-note p { margin:0;color:var(--a-muted);font-size:11px;line-height:1.65; }

    .msa-card-actions {
        display:flex;
        gap:8px;
        flex-wrap:wrap;
        margin-top:13px;
    }

    .msa-empty {
        display:flex;
        flex-direction:column;
        align-items:center;
        text-align:center;
        padding:46px 22px;
        margin-bottom:16px;
        border:1px dashed var(--a-line-strong);
        border-radius:18px;
        background:linear-gradient(135deg,#fff,#f4f9f6);
    }

    .msa-empty-icon {
        width:52px;
        height:52px;
        display:grid;
        place-items:center;
        margin-bottom:14px;
        border-radius:16px;
        background:var(--a-mint);
        color:var(--a-forest);
    }

    .msa-empty-icon svg { width:23px;height:23px; }
    .msa-empty h3 { margin:0;color:var(--a-forest);font-size:18px;letter-spacing:-.025em; }
    .msa-empty p { max-width:570px;margin:8px 0 15px;color:var(--a-muted);font-size:11.5px;line-height:1.65; }
    .msa-empty-actions { display:flex;gap:8px;flex-wrap:wrap;justify-content:center; }

    .msa-guidance {
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:16px;
    }

    .msa-guide {
        padding:20px;
        border:1px solid var(--a-line);
        border-radius:17px;
        background:#fff;
        box-shadow:var(--a-shadow);
    }

    .msa-guide-head {
        display:flex;
        align-items:center;
        gap:10px;
        margin-bottom:13px;
    }

    .msa-guide-icon {
        width:34px;
        height:34px;
        display:grid;
        place-items:center;
        border-radius:10px;
        background:var(--a-mint);
        color:var(--a-forest);
    }

    .msa-guide-icon svg { width:16px;height:16px; }
    .msa-guide h4 { margin:0;color:var(--a-forest);font-size:13px; }
    .msa-guide ul { margin:0;padding-left:18px;color:var(--a-muted);font-size:10.5px;line-height:1.8; }

    body.dusk-mode .msa {
        --a-ivory:#0f1e19;
        --a-white:#152a23;
        --a-text:#edf5f1;
        --a-muted:#9fb6ab;
        --a-line:rgba(237,245,241,.09);
        --a-line-strong:rgba(237,245,241,.16);
    }

    body.dusk-mode .msa-hero,
    body.dusk-mode .msa-card,
    body.dusk-mode .msa-guide,
    body.dusk-mode .msa-empty { background:#152a23; }

    body.dusk-mode .msa-meta { background:#1b332b; }
    body.dusk-mode .msa-hero-card { background:rgba(21,42,35,.78); }

    body.dusk-mode .msa-head h1,
    body.dusk-mode .msa-hero h2,
    body.dusk-mode .msa-counselor-copy h4,
    body.dusk-mode .msa-meta strong,
    body.dusk-mode .msa-empty h3,
    body.dusk-mode .msa-guide h4 { color:#edf5f1; }

    @media(max-width:1050px) {
        .msa-hero { grid-template-columns:1fr; }
        .msa-meta-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }

    @media(max-width:760px) {
        .msa-head { align-items:flex-start;flex-direction:column; }
        .msa-head-actions { justify-content:flex-start; }
        .msa-card-top { flex-direction:column; }
        .msa-meta-grid { grid-template-columns:1fr; }
        .msa-guidance { grid-template-columns:1fr; }
        .msa-history-row { grid-template-columns:minmax(0,1fr) auto; }
        .msa-history-link { grid-column:1 / -1; }
        .msa-support-strip { align-items:flex-start;flex-direction:column; }
        .msa-support-actions { justify-content:flex-start; }
        .msa-head-actions { width:100%; }
    }

    @media(max-width:520px) {
        .msa-hero { padding:24px 21px; }
        .msa-card,.msa-guide { padding:17px; }
        .msa-head-actions .msa-btn { flex:1 1 170px;justify-content:center; }
        .msa-history-row { grid-template-columns:1fr;gap:7px; }
        .msa-history-date { text-align:left; }
    }

    @media(prefers-reduced-motion:reduce) {
        .msa * { animation:none !important;transition-duration:.01ms !important;scroll-behavior:auto !important; }
    }
</style>

<div class="msa appointments-clean-shell-v2">
    <header class="msa-head">
        <div>
            <h1>Appointments</h1>
            <p>Manage your counseling sessions in one place.</p>
        </div>
        <div class="msa-head-actions">
            <a href="{{ route('student.counselor-directory') }}" class="msa-btn msa-btn-primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a4 4 0 00-4-4h-1M7 20H2v-2a4 4 0 014-4h1m5 6v-2a4 4 0 00-4-4h-1M9 7a3 3 0 106 0 3 3 0 00-6 0z"/></svg>
                Find Counselor
            </a>
        </div>
    </header>

    <section class="msa-section" aria-labelledby="upcomingAppointments">
        <div class="msa-section-head">
            <div>
                <span class="msa-kicker">Upcoming appointments</span>
                <h3 id="upcomingAppointments">Your counseling schedule</h3>
                <p>Confirmed sessions are shown here first.</p>
            </div>
            @if($appointments->count() > 0)<span class="msa-count">{{ $appointments->total() }} {{ $appointments->total() === 1 ? 'session' : 'sessions' }}</span>@endif
        </div>

        @if($appointments->count() > 0)
            <div class="msa-list">
                @foreach($appointments as $appointment)
                    @php
                        $counselorName = $appointment->counselor->name ?? 'Counselor';
                        $initial = strtoupper(substr($counselorName, 0, 1));
                    @endphp
                    <article class="msa-card">
                        <div class="msa-card-top">
                            <div class="msa-counselor">
                                <span class="msa-counselor-avatar">{{ $initial }}</span>
                                <div class="msa-counselor-copy">
                                    <small>Confirmed session</small>
                                    <h4>{{ $counselorName }}</h4>
                                    <p>{{ $appointment->counselor->specialization ?? 'Student Wellness' }}</p>
                                </div>
                            </div>
                            <span class="msa-status">Confirmed</span>
                        </div>
                        <div class="msa-meta-grid">
                            <div class="msa-meta"><span>Date</span><strong>{{ $appointment->appointment_date->format('l, F j, Y') }}</strong></div>
                            <div class="msa-meta"><span>Time</span><strong>{{ $appointment->time_slot }}</strong></div>
                            <div class="msa-meta"><span>Session type</span><strong>{{ ucfirst($appointment->category ?? 'General') }}</strong></div>
                            <div class="msa-meta"><span>Counselor email</span><strong>{{ $appointment->counselor->email ?? 'Not available' }}</strong></div>
                        </div>
                        <div class="msa-note">
                            <div class="msa-note-label"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1zm3 4h8M8 12h8M8 16h5"/></svg>Session notes</div>
                            <p>{{ $appointment->reason ?: 'No notes were added for this appointment.' }}</p>
                        </div>
                        <div class="msa-card-actions">
                            <a href="{{ route('student.counseling-requests') }}" class="msa-btn msa-btn-secondary">View request</a>
                            <a href="{{ route('student.counselor-directory') }}" class="msa-btn msa-btn-secondary">Counselor directory</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <section class="msa-empty">
                <span class="msa-empty-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg></span>
                <h3>No upcoming appointments</h3>
                <p>You do not have a confirmed counseling session on your schedule.</p>
                <div class="msa-empty-actions"><x-ui.button href="{{ route('student.counselor-directory') }}" size="sm" class="msa-btn msa-btn-primary">Find Counselor</x-ui.button></div>
            </section>
        @endif
    </section>

    @if($historyAppointments->isNotEmpty())
        <section class="msa-section msa-history-section" aria-labelledby="appointmentHistory">
            <div class="msa-section-head">
                <div>
                    <span class="msa-kicker">Appointment history</span>
                    <h3 id="appointmentHistory">Earlier sessions and requests</h3>
                    <p>Review the latest status of earlier appointment records.</p>
                </div>
                <a href="{{ route('student.counseling-requests') }}" class="msa-history-link">View all requests</a>
            </div>
            <div class="msa-history-list">
                @foreach($historyAppointments as $historyAppointment)
                    @php
                        $historyDate = $historyAppointment->appointment_date ?: $historyAppointment->preferred_date;
                        $historyStatus = ucfirst(str_replace('_', ' ', $historyAppointment->status ?? 'pending'));
                    @endphp
                    <article class="msa-history-row">
                        <div>
                            <span class="msa-kicker">{{ $historyStatus }}</span>
                            <h4>{{ $historyAppointment->counselor->name ?? 'Counselor request' }}</h4>
                            <p>{{ ucfirst($historyAppointment->category ?? 'General') }}{{ $historyAppointment->time_slot ? ' Â· ' . $historyAppointment->time_slot : '' }}</p>
                        </div>
                        <span class="msa-history-date">{{ $historyDate ? $historyDate->format('M j, Y') : 'Date pending' }}</span>
                        <a href="{{ route('student.counseling-requests') }}" class="msa-history-link">View details</a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="msa-support-strip" aria-labelledby="appointmentSupport">
        <div>
            <span class="msa-kicker">Support information</span>
            <h3 id="appointmentSupport">Need another support option?</h3>
            <p>Browse available counselors or explore practical wellness resources.</p>
        </div>
        <div class="msa-support-actions">
            <a href="{{ route('student.counselor-directory') }}" class="msa-btn msa-btn-secondary">Counselor directory</a>
            <a href="{{ route('student.resources') }}" class="msa-btn msa-btn-secondary">Wellness resources</a>
        </div>
    </section>
</div>
@endsection
