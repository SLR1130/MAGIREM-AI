@extends('layouts.app')

@section('title', 'Counselor Dashboard · MAGIREM AI')

@section('content')
<style>
.cdash,
.cdash *,
.cdash *::before,
.cdash *::after { box-sizing: border-box; }

.cdash {
    --forest:#103F32;
    --forest-2:#174F3F;
    --emerald:#236B55;
    --mint:#E6F1EB;
    --mint-2:#D8EBE2;
    --ivory:#F8FAF7;
    --white:#FFFFFF;
    --gold:#F4C84E;
    --text:#263A33;
    --muted:#71837A;
    --soft:#91A098;
    --line:rgba(16,63,50,.10);
    --shadow:0 16px 44px rgba(16,63,50,.06);
    --danger:#B95E57;
    --danger-bg:#FFF2F0;
    --warning:#B98422;
    --warning-bg:#FFF8E8;
    --blue:#477A8C;
    --blue-bg:#EDF5F7;
    --success:#2F8767;
    --success-bg:#EDF8F3;

    width:100%;
    max-width:1480px;
    margin:0 auto;
    padding:12px 20px 34px;
    color:var(--text);
    font-family:Inter,"Plus Jakarta Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}

.cdash a { text-decoration:none; }

.cdash-head {
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:24px;
    margin-bottom:16px;
}

.cdash-eyebrow {
    display:inline-flex;
    align-items:center;
    gap:9px;
    margin-bottom:7px;
    color:var(--emerald);
    font-size:10px;
    font-weight:850;
    letter-spacing:.14em;
    text-transform:uppercase;
}

.cdash-eyebrow::before {
    content:"";
    width:20px;
    height:2px;
    border-radius:999px;
    background:var(--gold);
}

.cdash-head h1 {
    margin:0;
    color:var(--forest);
    font-size:clamp(31px,3vw,46px);
    line-height:1;
    letter-spacing:-.045em;
    font-weight:850;
}

.cdash-head p {
    max-width:760px;
    margin:9px 0 0;
    color:var(--muted);
    font-size:11px;
    line-height:1.6;
}

.cdash-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.cdash-btn {
    min-height:40px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:0 13px;
    border:1px solid var(--line);
    border-radius:11px;
    background:#fff;
    color:var(--forest)!important;
    font-size:9px;
    font-weight:820;
    transition:.16s ease;
}

.cdash-btn:hover {
    transform:translateY(-1px);
    box-shadow:0 8px 20px rgba(16,63,50,.06);
}

.cdash-btn.primary {
    border-color:var(--forest);
    background:var(--forest);
    color:#fff!important;
}

.cdash-btn svg {
    width:15px;
    height:15px;
}

/* top summary */
.cdash-summary {
    display:grid;
    grid-template-columns:minmax(0,1.3fr) minmax(280px,.7fr);
    gap:12px;
    margin-bottom:12px;
}

.cdash-welcome {
    position:relative;
    overflow:hidden;
    padding:21px 22px;
    border:1px solid var(--line);
    border-radius:18px;
    background:
        radial-gradient(circle at 100% 0%,rgba(216,235,226,.78),transparent 22rem),
        #fff;
    box-shadow:var(--shadow);
}

.cdash-welcome::after {
    content:"";
    position:absolute;
    width:250px;
    height:250px;
    right:-120px;
    bottom:-160px;
    border:1px solid rgba(35,107,85,.10);
    border-radius:50%;
}

.cdash-welcome > * {
    position:relative;
    z-index:1;
}

.cdash-welcome-label {
    color:var(--emerald);
    font-size:8px;
    font-weight:850;
    letter-spacing:.12em;
    text-transform:uppercase;
}

.cdash-welcome h2 {
    max-width:680px;
    margin:7px 0 0;
    color:var(--forest);
    font-size:22px;
    line-height:1.15;
    letter-spacing:-.025em;
    font-weight:830;
}

.cdash-welcome p {
    max-width:720px;
    margin:7px 0 0;
    color:var(--muted);
    font-size:9.5px;
    line-height:1.6;
}

.cdash-priority-note {
    padding:18px;
    border:1px solid rgba(185,94,87,.12);
    border-radius:18px;
    background:var(--danger-bg);
}

.cdash-priority-note small {
    display:flex;
    align-items:center;
    gap:6px;
    color:var(--danger);
    font-size:8px;
    font-weight:850;
    letter-spacing:.08em;
    text-transform:uppercase;
}

.cdash-priority-note small svg {
    width:14px;
    height:14px;
}

.cdash-priority-note strong {
    display:block;
    margin-top:9px;
    color:#7C413C;
    font-size:23px;
    line-height:1;
    letter-spacing:-.03em;
}

.cdash-priority-note p {
    margin:5px 0 0;
    color:#875A55;
    font-size:8.5px;
    line-height:1.5;
}

/* stat row */
.cdash-stats {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:9px;
    margin-bottom:12px;
}

.cdash-stat {
    min-width:0;
    padding:14px;
    border:1px solid var(--line);
    border-radius:14px;
    background:#fff;
}

.cdash-stat-icon {
    width:31px;
    height:31px;
    display:grid;
    place-items:center;
    border-radius:9px;
    background:var(--mint);
    color:var(--forest);
}

.cdash-stat-icon svg {
    width:15px;
    height:15px;
}

.cdash-stat.danger .cdash-stat-icon {
    background:var(--danger-bg);
    color:var(--danger);
}

.cdash-stat.warning .cdash-stat-icon {
    background:var(--warning-bg);
    color:var(--warning);
}

.cdash-stat.info .cdash-stat-icon {
    background:var(--blue-bg);
    color:var(--blue);
}

.cdash-stat strong {
    display:block;
    margin-top:9px;
    color:var(--forest);
    font-size:23px;
    line-height:1;
    letter-spacing:-.035em;
    font-weight:850;
}

.cdash-stat span {
    display:block;
    margin-top:5px;
    color:var(--forest);
    font-size:8.5px;
    font-weight:800;
}

.cdash-stat small {
    display:block;
    margin-top:3px;
    color:var(--muted);
    font-size:7.5px;
}

/* primary navigation tabs */
.cdash-tabs {
    position:sticky;
    top:0;
    z-index:20;
    display:flex;
    gap:5px;
    margin-bottom:12px;
    padding:5px;
    border:1px solid var(--line);
    border-radius:13px;
    background:rgba(255,255,255,.94);
    backdrop-filter:blur(14px);
    box-shadow:0 8px 24px rgba(16,63,50,.035);
}

.cdash-tab {
    flex:1;
    min-height:39px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    border:0;
    border-radius:9px;
    background:transparent;
    color:var(--muted);
    font:inherit;
    font-size:8.5px;
    font-weight:820;
    cursor:pointer;
}

.cdash-tab svg {
    width:14px;
    height:14px;
}

.cdash-tab:hover {
    background:var(--ivory);
    color:var(--forest);
}

.cdash-tab.active {
    background:var(--mint);
    color:var(--forest);
}

.cdash-tab-badge {
    min-width:19px;
    height:19px;
    display:grid;
    place-items:center;
    padding:0 5px;
    border-radius:999px;
    background:#fff;
    color:var(--forest);
    font-size:6.8px;
    font-weight:850;
}

.cdash-tab-panel { display:none; }
.cdash-tab-panel.active {
    display:block;
    animation:cdashFade .18s ease;
}

@keyframes cdashFade {
    from { opacity:0; transform:translateY(4px); }
    to { opacity:1; transform:none; }
}

/* shared panels */
.cdash-grid-main {
    display:grid;
    grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);
    gap:12px;
    align-items:start;
}

.cdash-grid-two {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}

.cdash-panel {
    min-width:0;
    border:1px solid var(--line);
    border-radius:16px;
    background:#fff;
    box-shadow:0 9px 28px rgba(16,63,50,.04);
    overflow:hidden;
}

.cdash-panel-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:13px 15px;
    border-bottom:1px solid var(--line);
}

.cdash-panel-title {
    display:flex;
    align-items:center;
    gap:8px;
    color:var(--forest);
    font-size:10px;
    font-weight:830;
}

.cdash-panel-title svg {
    width:15px;
    height:15px;
    color:var(--emerald);
}

.cdash-pill {
    min-height:24px;
    display:inline-flex;
    align-items:center;
    padding:0 8px;
    border-radius:999px;
    background:var(--mint);
    color:var(--forest);
    font-size:7px;
    font-weight:850;
}

.cdash-pill.danger {
    background:var(--danger-bg);
    color:var(--danger);
}

.cdash-panel-body {
    padding:12px 14px 14px;
}

/* overview priority queue */
.cdash-alert-list {
    display:grid;
    gap:7px;
}

.cdash-alert {
    padding:11px;
    border:1px solid rgba(185,94,87,.13);
    border-left:3px solid var(--danger);
    border-radius:11px;
    background:var(--danger-bg);
}

.cdash-alert-top {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}

.cdash-person {
    display:flex;
    align-items:center;
    gap:9px;
    min-width:0;
}

.cdash-avatar {
    width:33px;
    height:33px;
    flex:0 0 33px;
    display:grid;
    place-items:center;
    border-radius:9px;
    background:var(--forest);
    color:#fff;
    font-size:9px;
    font-weight:850;
}

.cdash-person-copy {
    min-width:0;
}

.cdash-person-copy strong {
    display:block;
    overflow:hidden;
    color:var(--forest);
    font-size:9px;
    font-weight:820;
    white-space:nowrap;
    text-overflow:ellipsis;
}

.cdash-person-copy span {
    display:block;
    margin-top:2px;
    color:var(--muted);
    font-size:7.2px;
}

.cdash-urgent {
    min-height:22px;
    display:inline-flex;
    align-items:center;
    padding:0 7px;
    border:1px solid rgba(185,94,87,.14);
    border-radius:999px;
    background:#fff;
    color:var(--danger);
    font-size:6.5px;
    font-weight:850;
    text-transform:uppercase;
}

.cdash-alert p {
    margin:8px 0 9px;
    color:#754D48;
    font-size:8px;
    line-height:1.5;
}

.cdash-mini-actions {
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}

.cdash-mini {
    min-height:30px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:0 9px;
    border:1px solid var(--line);
    border-radius:8px;
    background:#fff;
    color:var(--forest)!important;
    font-size:7.3px;
    font-weight:800;
}

.cdash-mini.danger {
    border-color:var(--danger);
    background:var(--danger);
    color:#fff!important;
}

.cdash-preview-foot {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-top:8px;
    padding-top:9px;
    border-top:1px solid var(--line);
    color:var(--muted);
    font-size:7.2px;
}

.cdash-preview-foot strong { color:var(--forest); }

/* quick actions */
.cdash-actions-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:8px;
}

.cdash-action-card {
    min-height:96px;
    display:flex;
    flex-direction:column;
    align-items:flex-start;
    justify-content:space-between;
    padding:12px;
    border:1px solid var(--line);
    border-radius:11px;
    background:var(--ivory);
    color:var(--forest)!important;
    transition:.16s ease;
}

.cdash-action-card:hover {
    transform:translateY(-2px);
    background:#fff;
    box-shadow:0 9px 20px rgba(16,63,50,.055);
}

.cdash-action-icon {
    width:31px;
    height:31px;
    display:grid;
    place-items:center;
    border-radius:9px;
    background:var(--mint);
}

.cdash-action-icon svg {
    width:14px;
    height:14px;
}

.cdash-action-card strong {
    display:block;
    font-size:8.5px;
}

.cdash-action-card span span {
    display:block;
    margin-top:3px;
    color:var(--muted);
    font-size:7.2px;
    line-height:1.4;
}

/* care queue */
.cdash-section-note {
    margin-bottom:10px;
    padding:11px 12px;
    border:1px solid var(--line);
    border-radius:11px;
    background:var(--ivory);
    color:var(--muted);
    font-size:8px;
    line-height:1.55;
}

.cdash-risk-grid {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:9px;
}

.cdash-risk-card {
    padding:12px;
    border:1px solid var(--line);
    border-radius:12px;
    background:#fff;
}

.cdash-risk-head {
    display:flex;
    align-items:center;
    gap:8px;
}

.cdash-risk-copy {
    min-width:0;
    flex:1;
}

.cdash-risk-copy strong {
    display:block;
    overflow:hidden;
    color:var(--forest);
    font-size:8.7px;
    white-space:nowrap;
    text-overflow:ellipsis;
}

.cdash-risk-copy span {
    display:block;
    margin-top:2px;
    color:var(--muted);
    font-size:7px;
}

.cdash-risk-label {
    font-size:6.5px;
    font-weight:850;
    text-transform:uppercase;
}

.cdash-risk-track {
    height:7px;
    margin:11px 0 6px;
    overflow:hidden;
    border-radius:999px;
    background:#EDF1EE;
}

.cdash-risk-fill {
    height:100%;
    border-radius:999px;
}

.cdash-risk-score {
    display:flex;
    align-items:baseline;
    gap:4px;
}

.cdash-risk-score strong {
    font-size:19px;
    line-height:1;
}

.cdash-risk-score span {
    color:var(--muted);
    font-size:7px;
}

.cdash-risk-actions {
    display:flex;
    gap:6px;
    margin-top:9px;
}

.cdash-risk-actions a {
    flex:1;
    min-height:29px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--line);
    border-radius:8px;
    background:var(--ivory);
    color:var(--forest)!important;
    font-size:7px;
    font-weight:800;
}

.cdash-signal-list {
    display:grid;
    gap:7px;
}

.cdash-signal {
    padding:10px 11px;
    border:1px solid var(--line);
    border-radius:10px;
    background:var(--ivory);
}

.cdash-signal-top {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
}

.cdash-signal p {
    margin:7px 0 0;
    color:var(--muted);
    font-size:7.8px;
    line-height:1.5;
}

.cdash-signal a {
    display:inline-flex;
    margin-top:7px;
    color:var(--emerald)!important;
    font-size:7.3px;
    font-weight:800;
}

.cdash-empty {
    padding:42px 18px;
    text-align:center;
    color:var(--muted);
    font-size:8px;
}

.cdash-empty-icon {
    width:42px;
    height:42px;
    margin:0 auto 9px;
    display:grid;
    place-items:center;
    border-radius:12px;
    background:var(--mint);
    color:var(--forest);
}

.cdash-empty-icon svg {
    width:18px;
    height:18px;
}

.cdash-empty strong {
    display:block;
    margin-bottom:4px;
    color:var(--forest);
    font-size:9.5px;
}

/* analytics */
.cdash-analytics-intro {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:18px;
    margin-bottom:10px;
    padding:12px 14px;
    border:1px solid var(--line);
    border-radius:12px;
    background:var(--ivory);
}

.cdash-analytics-intro strong {
    color:var(--forest);
    font-size:9.5px;
}

.cdash-analytics-intro p {
    max-width:720px;
    margin:4px 0 0;
    color:var(--muted);
    font-size:7.8px;
    line-height:1.55;
}

.cdash-charts-top {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:9px;
}

.cdash-charts-bottom {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:9px;
    margin-top:9px;
}

.cdash-chart {
    min-width:0;
    padding:13px;
    border:1px solid var(--line);
    border-radius:12px;
    background:#fff;
}

.cdash-chart h3 {
    margin:0;
    color:var(--forest);
    font-size:9px;
    font-weight:820;
}

.cdash-chart p {
    margin:4px 0 8px;
    color:var(--muted);
    font-size:7.2px;
}

.cdash-chart-wrap {
    position:relative;
    height:205px;
}

.cdash-charts-bottom .cdash-chart-wrap {
    height:235px;
}

@media(max-width:1100px) {
    .cdash-summary,
    .cdash-grid-main,
    .cdash-grid-two { grid-template-columns:1fr; }
    .cdash-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .cdash-risk-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .cdash-charts-top { grid-template-columns:1fr; }
    .cdash-charts-bottom { grid-template-columns:1fr; }
}

@media(max-width:700px) {
    .cdash { padding-inline:12px; }
    .cdash-head { align-items:flex-start; flex-direction:column; }
    .cdash-stats { grid-template-columns:1fr; }
    .cdash-tabs { overflow-x:auto; }
    .cdash-tab { min-width:135px; }
    .cdash-actions-grid { grid-template-columns:1fr; }
    .cdash-risk-grid { grid-template-columns:1fr; }
}
</style>

<div class="cdash">
    <header class="cdash-head">
        <div>
            <span class="cdash-eyebrow">Counselor workspace</span>
            <h1>Welcome back, {{ auth()->user()->name }}</h1>
            <p>{{ now()->format('l, F j, Y') }} · Review priority concerns first, then move into student care, communication, and trend analysis.</p>
        </div>

        <div class="cdash-actions">
            <a href="{{ route('counselor.messages') }}" class="cdash-btn">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4-4 7-9 7-1.4 0-2.8-.25-4-.72L3 19l1.3-3.4A6.3 6.3 0 013 12c0-4 4-7 9-7s9 3 9 7z"/></svg>
                Messages
            </a>
            <a href="{{ route('counselor.appointments') }}" class="cdash-btn primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg>
                Appointments
            </a>
        </div>
    </header>

    <section class="cdash-summary">
        <div class="cdash-welcome">
            <span class="cdash-welcome-label">Today’s counseling picture</span>
            <h2>Keep urgent care visible without turning the whole dashboard into an alert feed.</h2>
            <p>The overview is intentionally compact. Detailed risk monitoring and analytics live in dedicated tabs so daily counselor work stays focused.</p>
        </div>

        <aside class="cdash-priority-note">
            <small>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3m0 4h.01M10.3 4.4L2.9 17.2A2 2 0 004.6 20h14.8a2 2 0 001.7-2.8L13.7 4.4a2 2 0 00-3.4 0z"/></svg>
                Priority queue
            </small>
            <strong>{{ $activeCrisisAlerts->count() }}</strong>
            <p>active crisis {{ $activeCrisisAlerts->count() === 1 ? 'alert' : 'alerts' }} currently in the system.</p>
        </aside>
    </section>

    <section class="cdash-stats">
        <article class="cdash-stat">
            <span class="cdash-stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8"/></svg></span>
            <strong>{{ $totalStudents }}</strong>
            <span>Students</span>
            <small>Current student population</small>
        </article>

        <article class="cdash-stat danger">
            <span class="cdash-stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3m0 4h.01M10.3 4.4L2.9 17.2A2 2 0 004.6 20h14.8a2 2 0 001.7-2.8L13.7 4.4a2 2 0 00-3.4 0z"/></svg></span>
            <strong>{{ $activeCrisisAlerts->count() }}</strong>
            <span>Active crisis alerts</span>
            <small>Review in Care Queue</small>
        </article>

        <article class="cdash-stat warning">
            <span class="cdash-stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01M4.9 19h14.2a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.17 16A2 2 0 004.9 19z"/></svg></span>
            <strong>{{ $crisisEntries->count() }}</strong>
            <span>Critical entries</span>
            <small>High-priority journal signals</small>
        </article>

        <article class="cdash-stat info">
            <span class="cdash-stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4-4 7-9 7-1.4 0-2.8-.25-4-.72L3 19l1.3-3.4A6.3 6.3 0 013 12c0-4 4-7 9-7s9 3 9 7z"/></svg></span>
            <strong>{{ $unreadMessages }}</strong>
            <span>Unread messages</span>
            <small>Waiting in counselor inbox</small>
        </article>
    </section>

    <nav class="cdash-tabs" aria-label="Counselor dashboard sections">
        <button type="button" class="cdash-tab active" data-cdash-tab="overview">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13h6V4H4v9zm10 7h6V11h-6v9zM4 20h6v-3H4v3zm10-13h6V4h-6v3z"/></svg>
            Overview
        </button>

        <button type="button" class="cdash-tab" data-cdash-tab="care">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3m0 4h.01M10.3 4.4L2.9 17.2A2 2 0 004.6 20h14.8a2 2 0 001.7-2.8L13.7 4.4a2 2 0 00-3.4 0z"/></svg>
            Care Queue
            @if($activeCrisisAlerts->count() > 0)
                <span class="cdash-tab-badge">{{ $activeCrisisAlerts->count() }}</span>
            @endif
        </button>

        <button type="button" class="cdash-tab" data-cdash-tab="analytics">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg>
            Analytics
        </button>
    </nav>

    {{-- OVERVIEW --}}
    <section class="cdash-tab-panel active" data-cdash-panel="overview">
        <div class="cdash-grid-main">
            <section class="cdash-panel">
                <div class="cdash-panel-head">
                    <div class="cdash-panel-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3m0 4h.01M10.3 4.4L2.9 17.2A2 2 0 004.6 20h14.8a2 2 0 001.7-2.8L13.7 4.4a2 2 0 00-3.4 0z"/></svg>
                        Priority preview
                    </div>
                    <span class="cdash-pill danger">Latest 4</span>
                </div>

                <div class="cdash-panel-body">
                    @if($activeCrisisAlerts->isEmpty())
                        <div class="cdash-empty">
                            <span class="cdash-empty-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg></span>
                            <strong>No active crisis alerts</strong>
                            Current crisis queue is clear.
                        </div>
                    @else
                        {{-- The full incident cards are rendered once in Care Queue. --}}
                        <div class="cdash-section-note">
                            <strong>{{ $activeCrisisAlerts->count() }} active crisis {{ $activeCrisisAlerts->count() === 1 ? 'alert' : 'alerts' }}</strong>
                            <span>The latest assigned incidents are available in the Care Queue.</span>
                            <button type="button" class="cdash-mini" data-open-cdash-tab="care">Open care queue</button>
                        </div>
                    @endif

                    @if($activeCrisisAlerts->count() > 4)
                        <div class="cdash-preview-foot">
                            <span>Showing <strong>4</strong> of <strong>{{ $activeCrisisAlerts->count() }}</strong> active alerts.</span>
                            <button type="button" class="cdash-mini" data-open-cdash-tab="care">View queue</button>
                        </div>
                    @endif
                </div>
            </section>

            <aside class="cdash-panel">
                <div class="cdash-panel-head">
                    <div class="cdash-panel-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Quick actions
                    </div>
                </div>

                <div class="cdash-panel-body">
                    <div class="cdash-actions-grid">
                        <a href="{{ route('counselor.students') }}" class="cdash-action-card">
                            <span class="cdash-action-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8"/></svg></span>
                            <span><strong>Students</strong><span>Open student care directory</span></span>
                        </a>

                        <a href="{{ route('counselor.messages') }}" class="cdash-action-card">
                            <span class="cdash-action-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4-4 7-9 7-1.4 0-2.8-.25-4-.72L3 19l1.3-3.4A6.3 6.3 0 013 12c0-4 4-7 9-7s9 3 9 7z"/></svg></span>
                            <span><strong>Messages</strong><span>Respond to student conversations</span></span>
                        </a>

                        <a href="{{ route('counselor.appointments') }}" class="cdash-action-card">
                            <span class="cdash-action-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg></span>
                            <span><strong>Appointments</strong><span>Review and manage sessions</span></span>
                        </a>

                        <a href="{{ route('student.community') }}" class="cdash-action-card">
                            <span class="cdash-action-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h8M8 14h5m7-2a8 8 0 01-8 8c-1.4 0-2.7-.35-3.8-.96L4 20l.96-4.2A8 8 0 1120 12z"/></svg></span>
                            <span><strong>Community Circle</strong><span>Read and share as a counselor</span></span>
                        </a>

                        <button type="button" class="cdash-action-card" data-open-cdash-tab="analytics" style="font:inherit;cursor:pointer;text-align:left">
                            <span class="cdash-action-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg></span>
                            <span><strong>Analytics</strong><span>Open trends and distributions</span></span>
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    {{-- CARE QUEUE --}}
    <section class="cdash-tab-panel" data-cdash-panel="care">
        <div class="cdash-section-note">
            This workspace separates operational review from analytics. Crisis alerts and journal risk signals remain visible here without dominating the everyday Overview tab.
        </div>

        <section class="cdash-panel" style="margin-bottom:12px">
            <div class="cdash-panel-head">
                <div class="cdash-panel-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3m0 4h.01M10.3 4.4L2.9 17.2A2 2 0 004.6 20h14.8a2 2 0 001.7-2.8L13.7 4.4a2 2 0 00-3.4 0z"/></svg>
                    Active crisis alerts
                </div>
                <span class="cdash-pill danger">{{ $activeCrisisAlerts->count() }} active</span>
            </div>

            <div class="cdash-panel-body">
                @php $careAlerts = $activeCrisisAlerts->take(8); @endphp

                <div class="cdash-alert-list">
                    @forelse($careAlerts as $alert)
                        <article class="cdash-alert" data-alert-id="{{ $alert->id }}">
                            <div class="cdash-alert-top">
                                <div class="cdash-person">
                                    <span class="cdash-avatar">S</span>
                                    <span class="cdash-person-copy">
                                        <strong>Student #{{ $alert->student->student_id ?? 'identifier unavailable' }}</strong>
                                        <span>{{ $alert->created_at->diffForHumans() }}</span>
                                    </span>
                                </div>
                                <span class="cdash-urgent">{{ strtoupper($alert->crisis_level ?? 'unknown') }}</span>
                            </div>

                            @if(!empty($alert->message_content))
                                <p>{{ \Illuminate\Support\Str::limit($alert->message_content, 180) }}</p>
                            @endif

                            <div class="cdash-mini-actions">
                                <a href="{{ route('counselor.chat', $alert->student_id) }}" class="cdash-mini danger">Contact student</a>
                                <a href="{{ route('counselor.appointments') }}" class="cdash-mini">Appointments</a>
                            </div>
                        </article>
                    @empty
                        <div class="cdash-empty">
                            <span class="cdash-empty-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg></span>
                            <strong>No active crisis alerts</strong>
                            Current crisis queue is clear.
                        </div>
                    @endforelse
                </div>

                @if($activeCrisisAlerts->count() > 8)
                    <div class="cdash-preview-foot">
                        <span>Showing the latest <strong>8</strong> of <strong>{{ $activeCrisisAlerts->count() }}</strong> active alerts.</span>
                        <span>Full data remains unchanged.</span>
                    </div>
                @endif
            </div>
        </section>


        <div class="cdash-grid-two">
            <section class="cdash-panel">
                <div class="cdash-panel-head">
                    <div class="cdash-panel-title">Critical journal entries</div>
                    <span class="cdash-pill danger">{{ $crisisEntries->count() }}</span>
                </div>

                <div class="cdash-panel-body">
                    <div class="cdash-signal-list">
                        @forelse($crisisEntries->take(6) as $entry)
                            <article class="cdash-signal">
                                <div class="cdash-signal-top">
                                    <div class="cdash-person">
                                        <span class="cdash-avatar">S</span>
                                        <span class="cdash-person-copy">
                                            <strong>Student #{{ $entry->user->student_id ?? 'Unknown' }}</strong>
                                            <span>{{ $entry->created_at->diffForHumans() }}</span>
                                        </span>
                                    </div>
                                    <span class="cdash-urgent">{{ strtoupper($entry->crisis_level) }}</span>
                                </div>
                                <p>{{ \Illuminate\Support\Str::limit($entry->entry_text, 125) }}</p>
                                <a href="{{ route('counselor.student-profile', $entry->user_id) }}">View profile →</a>
                            </article>
                        @empty
                            <div class="cdash-empty"><strong>No critical entries</strong>No current journal entries are listed here.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="cdash-panel">
                <div class="cdash-panel-head">
                    <div class="cdash-panel-title">Needs attention</div>
                    <span class="cdash-pill">{{ $needsAttention->count() }}</span>
                </div>

                <div class="cdash-panel-body">
                    <div class="cdash-signal-list">
                        @forelse($needsAttention->take(6) as $entry)
                            <article class="cdash-signal">
                                <div class="cdash-person">
                                    <span class="cdash-avatar">S</span>
                                    <span class="cdash-person-copy">
                                        <strong>Student #{{ $entry->user->student_id ?? 'Unknown' }}</strong>
                                        <span>{{ $entry->created_at->diffForHumans() }}</span>
                                    </span>
                                </div>
                                <p>{{ \Illuminate\Support\Str::limit($entry->entry_text, 110) }}</p>
                                <a href="{{ route('counselor.chat', $entry->user_id) }}">Message student →</a>
                            </article>
                        @empty
                            <div class="cdash-empty"><strong>No entries needing attention</strong>The current review list is clear.</div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </section>

    {{-- ANALYTICS --}}
    <section class="cdash-tab-panel" data-cdash-panel="analytics">
        <div class="cdash-analytics-intro">
            <div>
                <strong>Analytics & insights</strong>
                <p>Charts live in their own workspace so the operational dashboard remains compact. These visualizations use the exact same backend datasets already provided to the page.</p>
            </div>
            <span class="cdash-pill">Student emotional data</span>
        </div>

        <div class="cdash-charts-top">
            <article class="cdash-chart">
                <h3>Emotion distribution</h3>
                <p>All-time detected emotion mix</p>
                <div class="cdash-chart-wrap"><canvas id="emotionPieChart"></canvas></div>
            </article>

            <article class="cdash-chart">
                <h3>Wellness status</h3>
                <p>Students needing attention compared with students doing well</p>
                <div class="cdash-chart-wrap"><canvas id="wellnessDoughnut"></canvas></div>
            </article>

            <article class="cdash-chart">
                <h3>Crisis levels</h3>
                <p>Current severity distribution</p>
                <div class="cdash-chart-wrap"><canvas id="crisisBarChart"></canvas></div>
            </article>
        </div>

        <div class="cdash-charts-bottom">
            <article class="cdash-chart">
                <h3>7-day emotion trend</h3>
                <p>Daily emotion counts across the last week</p>
                <div class="cdash-chart-wrap"><canvas id="emotionLineChart"></canvas></div>
            </article>

            <article class="cdash-chart">
                <h3>Monthly journal activity</h3>
                <p>Emotional journal entries during the last six months</p>
                <div class="cdash-chart-wrap"><canvas id="monthlyBarChart"></canvas></div>
            </article>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabButtons = Array.from(document.querySelectorAll('[data-cdash-tab]'));
    const tabPanels = Array.from(document.querySelectorAll('[data-cdash-panel]'));

    function openDashboardTab(name) {
        tabButtons.forEach(function (button) {
            button.classList.toggle('active', button.dataset.cdashTab === name);
        });

        tabPanels.forEach(function (panel) {
            panel.classList.toggle('active', panel.dataset.cdashPanel === name);
        });

        if (name === 'analytics') {
            requestAnimationFrame(function () {
                window.dispatchEvent(new Event('resize'));
            });
        }
    }

    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            openDashboardTab(button.dataset.cdashTab);
        });
    });

    document.querySelectorAll('[data-open-cdash-tab]').forEach(function (button) {
        button.addEventListener('click', function () {
            openDashboardTab(button.dataset.openCdashTab);
            document.querySelector('.cdash-tabs')?.scrollIntoView({ behavior:'smooth', block:'start' });
        });
    });

    if (typeof Chart === 'undefined') return;

    Chart.defaults.color = '#71837A';
    Chart.defaults.borderColor = 'rgba(16,63,50,.08)';
    Chart.defaults.font.family = 'Inter, sans-serif';

    const emotionColors = {
        happy:'#2F8767',
        sad:'#6D8790',
        anxious:'#D1A33B',
        angry:'#B95E57',
        neutral:'#7A9A88',
        depressed:'#8B6B78'
    };

    const emotionData = @json($emotionDistribution);
    const emotionLabels = Object.keys(emotionData).map(function (emotion) {
        return emotion.charAt(0).toUpperCase() + emotion.slice(1);
    });
    const emotionValues = Object.values(emotionData);
    const emotionPieColors = Object.keys(emotionData).map(function (emotion) {
        return emotionColors[emotion] || '#91A098';
    });

    new Chart(document.getElementById('emotionPieChart'), {
        type:'doughnut',
        data:{
            labels:emotionLabels,
            datasets:[{
                data:emotionValues,
                backgroundColor:emotionPieColors,
                borderColor:'#fff',
                borderWidth:2
            }]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            cutout:'62%',
            plugins:{
                legend:{
                    position:'bottom',
                    labels:{ padding:10, boxWidth:9, font:{ size:9 } }
                }
            }
        }
    });

    new Chart(document.getElementById('wellnessDoughnut'), {
        type:'doughnut',
        data:{
            labels:['Needs Attention','Doing Well'],
            datasets:[{
                data:[{{ $studentsNeedingHelp }}, {{ $studentsOk }}],
                backgroundColor:['#B95E57','#2F8767'],
                borderColor:'#fff',
                borderWidth:2
            }]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            cutout:'68%',
            plugins:{
                legend:{
                    position:'bottom',
                    labels:{ padding:10, boxWidth:9, font:{ size:9 } }
                }
            }
        }
    });

    const crisisData = @json($crisisDistribution);
    const crisisColors = {
        critical:'#B95E57',
        severe:'#D08C3C',
        moderate:'#477A8C',
        mild:'#2F8767'
    };

    new Chart(document.getElementById('crisisBarChart'), {
        type:'bar',
        data:{
            labels:Object.keys(crisisData).map(function (level) {
                return level.charAt(0).toUpperCase() + level.slice(1);
            }),
            datasets:[{
                data:Object.values(crisisData),
                backgroundColor:Object.keys(crisisData).map(function (level) {
                    return crisisColors[level] || '#91A098';
                }),
                borderRadius:6,
                borderSkipped:false
            }]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            plugins:{ legend:{ display:false } },
            scales:{
                x:{ grid:{ display:false } },
                y:{
                    beginAtZero:true,
                    ticks:{ precision:0 },
                    grid:{ color:'rgba(16,63,50,.07)' }
                }
            }
        }
    });

    const last7DaysLabels = @json($last7DaysLabels);
    const emotionTrends = @json($emotionTrends);
    const lineEmotions = ['happy','sad','anxious','angry','neutral'];

    new Chart(document.getElementById('emotionLineChart'), {
        type:'line',
        data:{
            labels:last7DaysLabels,
            datasets:lineEmotions.map(function (emotion) {
                return {
                    label:emotion.charAt(0).toUpperCase() + emotion.slice(1),
                    data:emotionTrends[emotion] || [],
                    borderColor:emotionColors[emotion] || '#91A098',
                    backgroundColor:'transparent',
                    borderWidth:2,
                    pointRadius:3,
                    tension:.36,
                    fill:false
                };
            })
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            interaction:{ mode:'index', intersect:false },
            plugins:{
                legend:{
                    position:'bottom',
                    labels:{ padding:9, boxWidth:9, font:{ size:9 } }
                }
            },
            scales:{
                x:{ grid:{ color:'rgba(16,63,50,.05)' } },
                y:{
                    beginAtZero:true,
                    ticks:{ precision:0 },
                    grid:{ color:'rgba(16,63,50,.07)' }
                }
            }
        }
    });

    const monthlyLabels = @json($monthlyLabels);
    const monthlyData = @json($monthlyData);

    new Chart(document.getElementById('monthlyBarChart'), {
        type:'bar',
        data:{
            labels:monthlyLabels,
            datasets:[{
                label:'Journal Entries',
                data:monthlyData,
                backgroundColor:'rgba(35,107,85,.72)',
                borderColor:'#236B55',
                borderWidth:1,
                borderRadius:6,
                borderSkipped:false
            }]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            plugins:{ legend:{ display:false } },
            scales:{
                x:{ grid:{ display:false } },
                y:{
                    beginAtZero:true,
                    ticks:{ precision:0 },
                    grid:{ color:'rgba(16,63,50,.07)' }
                }
            }
        }
    });
});
</script>
@endsection
