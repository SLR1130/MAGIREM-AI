@extends('layouts.app')

@section('title', 'Admin Dashboard · MAGIREM AI')

@section('content')
<style>
.admin-dash,
.admin-dash *,
.admin-dash *::before,
.admin-dash *::after { box-sizing:border-box; }

.admin-dash {
    --forest:#103F32;
    --forest-2:#174F3F;
    --emerald:#236B55;
    --mint:#E6F1EB;
    --mint-2:#D9ECE3;
    --ivory:#F8FAF7;
    --white:#FFFFFF;
    --gold:#F4C84E;
    --text:#263A33;
    --muted:#71837A;
    --soft:#92A39A;
    --line:rgba(16,63,50,.10);
    --danger:#B95E57;
    --danger-bg:#FFF2F0;
    --blue:#4B7E8F;
    --blue-bg:#EDF5F7;
    --shadow:0 16px 44px rgba(16,63,50,.055);

    width:100%;
    max-width:1480px;
    margin:0 auto;
    padding:18px 22px 36px;
    color:var(--text);
    font-family:Inter,"Plus Jakarta Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}

.admin-dash a { text-decoration:none; }

.adm-head {
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:24px;
    margin-bottom:16px;
}

.adm-eyebrow {
    display:inline-flex;
    align-items:center;
    gap:9px;
    margin-bottom:8px;
    color:var(--emerald);
    font-size:10px;
    font-weight:850;
    letter-spacing:.14em;
    text-transform:uppercase;
}
.adm-eyebrow::before {
    content:"";
    width:20px;
    height:2px;
    border-radius:999px;
    background:var(--gold);
}
.adm-head h1 {
    margin:0;
    color:var(--forest);
    font-size:clamp(34px,3.2vw,48px);
    line-height:1;
    letter-spacing:-.045em;
    font-weight:850;
}
.adm-head p {
    max-width:780px;
    margin:10px 0 0;
    color:var(--muted);
    font-size:11px;
    line-height:1.6;
}
.adm-date {
    min-width:190px;
    padding:12px 14px;
    border:1px solid var(--line);
    border-radius:13px;
    background:#fff;
}
.adm-date strong {
    display:block;
    color:var(--forest);
    font-size:10px;
    font-weight:820;
}
.adm-date span {
    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:8px;
}

.adm-hero {
    display:grid;
    grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr);
    gap:12px;
    margin-bottom:12px;
}
.adm-hero-main,
.adm-health {
    border:1px solid var(--line);
    border-radius:18px;
    box-shadow:var(--shadow);
}
.adm-hero-main {
    position:relative;
    overflow:hidden;
    padding:22px;
    background:
        radial-gradient(circle at 100% 0%,rgba(216,235,226,.8),transparent 23rem),
        #fff;
}
.adm-hero-main::after {
    content:"";
    position:absolute;
    width:260px;
    height:260px;
    right:-130px;
    bottom:-165px;
    border:1px solid rgba(35,107,85,.11);
    border-radius:50%;
}
.adm-hero-main > * { position:relative; z-index:1; }
.adm-hero-main small {
    color:var(--emerald);
    font-size:8px;
    font-weight:850;
    letter-spacing:.1em;
    text-transform:uppercase;
}
.adm-hero-main h2 {
    max-width:710px;
    margin:8px 0 0;
    color:var(--forest);
    font-size:23px;
    line-height:1.15;
    letter-spacing:-.025em;
    font-weight:830;
}
.adm-hero-main p {
    max-width:740px;
    margin:8px 0 0;
    color:var(--muted);
    font-size:9px;
    line-height:1.65;
}
.adm-health {
    padding:19px;
    background:#fff;
}
.adm-health-top {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}
.adm-health-label {
    display:flex;
    align-items:center;
    gap:7px;
    color:var(--forest);
    font-size:9px;
    font-weight:820;
}
.adm-health-dot {
    width:9px;
    height:9px;
    border-radius:50%;
    background:#36A36F;
    box-shadow:0 0 0 4px rgba(54,163,111,.10);
}
.adm-health-badge {
    min-height:24px;
    display:inline-flex;
    align-items:center;
    padding:0 8px;
    border-radius:999px;
    background:#EDF8F3;
    color:#2F8767;
    font-size:7px;
    font-weight:850;
}
.adm-health strong {
    display:block;
    margin-top:13px;
    color:var(--forest);
    font-size:26px;
    line-height:1;
    letter-spacing:-.04em;
}
.adm-health p {
    margin:7px 0 0;
    color:var(--muted);
    font-size:8px;
    line-height:1.55;
}

.adm-stats {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:9px;
    margin-bottom:12px;
}
.adm-stat {
    min-width:0;
    padding:14px;
    border:1px solid var(--line);
    border-radius:14px;
    background:#fff;
}
.adm-stat-icon {
    width:32px;
    height:32px;
    display:grid;
    place-items:center;
    border-radius:9px;
    background:var(--mint);
    color:var(--forest);
}
.adm-stat-icon.blue { background:var(--blue-bg); color:var(--blue); }
.adm-stat-icon.gold { background:#FFF8E8; color:#A8781E; }
.adm-stat-icon svg { width:20px; height:20px; stroke-width:1.9; }
.adm-stat strong {
    display:block;
    margin-top:10px;
    color:var(--forest);
    font-size:24px;
    line-height:1;
    letter-spacing:-.035em;
    font-weight:850;
}
.adm-stat span {
    display:block;
    margin-top:5px;
    color:var(--forest);
    font-size:8.5px;
    font-weight:820;
}
.adm-stat small {
    display:block;
    margin-top:3px;
    color:var(--muted);
    font-size:7.3px;
}

.adm-tabs {
    position:sticky;
    top:0;
    z-index:20;
    display:flex;
    gap:5px;
    padding:5px;
    margin-bottom:12px;
    border:1px solid var(--line);
    border-radius:13px;
    background:rgba(255,255,255,.94);
    backdrop-filter:blur(14px);
    box-shadow:0 8px 24px rgba(16,63,50,.035);
}
.adm-tab {
    flex:1;
    min-height:40px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    border:0;
    border-radius:9px;
    background:transparent;
    color:var(--muted);
    font:inherit;
    font-size:8.6px;
    font-weight:820;
    cursor:pointer;
}
.adm-tab svg { width:16px; height:16px; stroke-width:1.9; flex:0 0 auto; }
.adm-tab:hover { background:var(--ivory); color:var(--forest); }
.adm-tab.active { background:var(--mint); color:var(--forest); }

.adm-panel { display:none; }
.adm-panel.active { display:block; animation:admFade .18s ease; }
@keyframes admFade {
    from { opacity:0; transform:translateY(4px); }
    to { opacity:1; transform:none; }
}

.adm-grid {
    display:grid;
    grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);
    gap:12px;
    align-items:start;
}
.adm-card {
    border:1px solid var(--line);
    border-radius:16px;
    background:#fff;
    box-shadow:0 10px 30px rgba(16,63,50,.035);
    overflow:hidden;
}
.adm-card-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:13px 15px;
    border-bottom:1px solid var(--line);
}
.adm-card-title {
    display:flex;
    align-items:center;
    gap:8px;
    color:var(--forest);
    font-size:10px;
    font-weight:830;
}
.adm-card-title svg {
    width:17px;
    height:17px;
    flex:0 0 auto;
    color:var(--emerald);
    stroke-width:1.9;
}
.adm-pill {
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
.adm-card-body { padding:13px 15px 15px; }

.adm-mix {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
}
.adm-mix-row {
    padding:13px;
    border:1px solid var(--line);
    border-radius:11px;
    background:var(--ivory);
}
.adm-mix-row strong {
    color:var(--forest);
    font-size:9px;
}
.adm-mix-row p {
    margin:5px 0 10px;
    color:var(--muted);
    font-size:7.4px;
}
.adm-track {
    height:7px;
    overflow:hidden;
    border-radius:999px;
    background:#E9EFEB;
}
.adm-fill {
    height:100%;
    border-radius:999px;
    background:var(--emerald);
}
.adm-fill.gold { background:#D4AC4E; }

.adm-shortcuts {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:8px;
}
.adm-shortcut {
    min-height:100px;
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
.adm-shortcut:hover {
    transform:translateY(-2px);
    background:#fff;
    box-shadow:0 9px 20px rgba(16,63,50,.05);
}
.adm-shortcut-icon {
    width:31px;
    height:31px;
    display:grid;
    place-items:center;
    border-radius:9px;
    background:var(--mint);
}
.adm-shortcut-icon svg { width:18px; height:18px; stroke-width:1.9; }
.adm-shortcut strong { display:block; font-size:8.5px; }
.adm-shortcut span span {
    display:block;
    margin-top:3px;
    color:var(--muted);
    font-size:7.2px;
    line-height:1.4;
}

.adm-activity {
    display:grid;
    gap:7px;
}
.adm-entry {
    display:grid;
    grid-template-columns:38px minmax(0,1fr) auto;
    gap:10px;
    align-items:center;
    padding:10px 11px;
    border:1px solid var(--line);
    border-radius:11px;
    background:var(--ivory);
}
.adm-entry-icon {
    width:38px;
    height:38px;
    display:grid;
    place-items:center;
    border-radius:10px;
    background:var(--mint);
    color:var(--forest);
}
.adm-entry-icon svg { width:19px; height:19px; stroke-width:1.9; }
.adm-entry-copy { min-width:0; }
.adm-entry-copy strong {
    display:block;
    overflow:hidden;
    color:var(--forest);
    font-size:8.8px;
    white-space:nowrap;
    text-overflow:ellipsis;
}
.adm-entry-copy p {
    margin:4px 0 0;
    overflow:hidden;
    color:var(--muted);
    font-size:7.6px;
    line-height:1.45;
    white-space:nowrap;
    text-overflow:ellipsis;
}
.adm-entry-time {
    color:var(--soft);
    font-size:7px;
    white-space:nowrap;
}
.adm-empty {
    padding:50px 18px;
    text-align:center;
    color:var(--muted);
    font-size:8px;
}
.adm-empty strong {
    display:block;
    margin-bottom:4px;
    color:var(--forest);
    font-size:10px;
}

.adm-tools-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
}
.adm-tool {
    min-height:150px;
    position:relative;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    align-items:flex-start;
    justify-content:flex-end;
    padding:16px;
    border:1px solid var(--line);
    border-radius:14px;
    background:#fff;
    color:var(--forest)!important;
    transition:.18s ease;
}
.adm-tool::after {
    content:"";
    position:absolute;
    width:110px;
    height:110px;
    right:-44px;
    top:-44px;
    border-radius:50%;
    background:var(--mint);
    opacity:.8;
}
.adm-tool:hover {
    transform:translateY(-2px);
    box-shadow:0 12px 28px rgba(16,63,50,.06);
}
.adm-tool-icon {
    position:relative;
    z-index:1;
    width:36px;
    height:36px;
    display:grid;
    place-items:center;
    margin-bottom:18px;
    border-radius:10px;
    background:var(--forest);
    color:#fff;
}
.adm-tool-icon svg { width:19px; height:19px; stroke-width:1.9; }
.adm-tool strong {
    position:relative;
    z-index:1;
    font-size:10px;
}
.adm-tool p {
    position:relative;
    z-index:1;
    margin:5px 0 0;
    color:var(--muted);
    font-size:7.7px;
    line-height:1.5;
}

@media(max-width:1050px) {
    .adm-hero,
    .adm-grid { grid-template-columns:1fr; }
    .adm-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media(max-width:700px) {
    .admin-dash { padding-inline:12px; }
    .adm-head { align-items:flex-start; flex-direction:column; }
    .adm-date { width:100%; }
    .adm-stats,
    .adm-mix,
    .adm-shortcuts,
    .adm-tools-grid { grid-template-columns:1fr; }
    .adm-tabs { overflow-x:auto; }
    .adm-tab { min-width:130px; }
    .adm-entry { grid-template-columns:36px minmax(0,1fr); }
    .adm-entry-time { grid-column:2; }
}

/* Calm, purposeful motion for the admin workspace. */
.adm-tab { transition:background-color 180ms ease, color 180ms ease; }
.adm-tab svg,
.adm-shortcut-icon svg,
.adm-tool-icon svg { transition:transform 200ms cubic-bezier(.22,1,.36,1); }
.adm-tab:hover svg,
.adm-tab:focus-visible svg,
.adm-shortcut:hover .adm-shortcut-icon svg,
.adm-shortcut:focus-visible .adm-shortcut-icon svg,
.adm-tool:hover .adm-tool-icon svg,
.adm-tool:focus-visible .adm-tool-icon svg { transform:translateY(-2px); }
.adm-tab:focus-visible,
.adm-shortcut:focus-visible,
.adm-tool:focus-visible { outline:2px solid #B48624; outline-offset:2px; }

@keyframes adminDashboardRise {
    from { opacity:0; transform:translateY(10px); }
    to { opacity:1; transform:translateY(0); }
}
@media (prefers-reduced-motion: no-preference) {
    .admin-dash .adm-head,
    .admin-dash .adm-hero,
    .admin-dash .adm-stat,
    .admin-dash .adm-tabs,
    .admin-dash .adm-panel.active .adm-card,
    .admin-dash .adm-panel.active .adm-tool {
        animation:adminDashboardRise 400ms cubic-bezier(.22,1,.36,1) backwards;
    }
    .admin-dash .adm-hero { animation-delay:40ms; }
    .admin-dash .adm-stat:nth-child(1) { animation-delay:70ms; }
    .admin-dash .adm-stat:nth-child(2) { animation-delay:100ms; }
    .admin-dash .adm-stat:nth-child(3) { animation-delay:130ms; }
    .admin-dash .adm-stat:nth-child(4) { animation-delay:160ms; }
    .admin-dash .adm-tabs { animation-delay:170ms; }
}
@media (prefers-reduced-motion: reduce) {
    .admin-dash .adm-panel.active,
    .admin-dash .adm-shortcut,
    .admin-dash .adm-tool,
    .admin-dash .adm-tab,
    .admin-dash .adm-tab svg,
    .admin-dash .adm-shortcut-icon svg,
    .admin-dash .adm-tool-icon svg { animation:none !important; transition:none !important; }
    .admin-dash .adm-shortcut:hover,
    .admin-dash .adm-tool:hover,
    .admin-dash .adm-tab:hover svg,
    .admin-dash .adm-tab:focus-visible svg,
    .admin-dash .adm-shortcut:hover .adm-shortcut-icon svg,
    .admin-dash .adm-shortcut:focus-visible .adm-shortcut-icon svg,
    .admin-dash .adm-tool:hover .adm-tool-icon svg,
    .admin-dash .adm-tool:focus-visible .adm-tool-icon svg { transform:none; }
}
</style>
@include('admin.partials.workspace-polish')

@php
    $peopleTotal = max(1, $totalStudents + $totalCounselors);
    $studentShare = round(($totalStudents / $peopleTotal) * 100, 1);
    $counselorShare = round(($totalCounselors / $peopleTotal) * 100, 1);
@endphp

<div class="admin-dash">
    <header class="adm-head">
        <div>
            <span class="adm-eyebrow">Administrator workspace</span>
            <h1>Platform control center</h1>
            <p>Review account activity, manage your workspace, and open reports from one place.</p>
        </div>

        <div class="adm-date">
            <strong>{{ now()->format('l, F j, Y') }}</strong>
            <span>System overview</span>
        </div>
    </header>

    <section class="adm-stats">
        <article class="adm-stat">
            <span class="adm-stat-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8"/></svg>
            </span>
            <strong>{{ $totalStudents }}</strong>
            <span>Students</span>
            <small>Registered student accounts</small>
        </article>

        <article class="adm-stat">
            <span class="adm-stat-icon blue">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.36-1.86M17 20H7m10 0v-2a5 5 0 00-10 0v2m8-13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </span>
            <strong>{{ $totalCounselors }}</strong>
            <span>Counselors</span>
            <small>Professional support accounts</small>
        </article>

        <article class="adm-stat">
            <span class="adm-stat-icon gold">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.5v13m0-13C10.7 5.5 9.2 5 7.5 5S4.3 5.5 3 6.5v13c1.3-1 2.8-1.5 4.5-1.5s3.2.5 4.5 1.5m0-13C13.3 5.5 14.8 5 16.5 5S19.7 5.5 21 6.5v13c-1.3-1-2.8-1.5-4.5-1.5s-3.2.5-4.5 1.5"/></svg>
            </span>
            <strong>{{ $totalEntries }}</strong>
            <span>Journal entries</span>
            <small>Total recorded wellbeing entries</small>
        </article>

    </section>

    <nav class="adm-tabs" aria-label="Admin dashboard sections">
        <button type="button" class="adm-tab active" data-adm-tab="overview" aria-pressed="true">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13h6V4H4v9zm10 7h6V11h-6v9zM4 20h6v-3H4v3zm10-13h6V4h-6v3z"/></svg>
            Overview
        </button>
        <button type="button" class="adm-tab" data-adm-tab="activity" aria-pressed="false">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Recent Activity
        </button>
        <button type="button" class="adm-tab" data-adm-tab="tools" aria-pressed="false">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Administration
        </button>
    </nav>

    <section class="adm-panel active" data-adm-panel="overview">
        <div class="adm-grid">
            <section class="adm-card">
                <div class="adm-card-head">
                    <div class="adm-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg>
                        Platform composition
                    </div>
                    <span class="adm-pill">Accounts</span>
                </div>
                <div class="adm-card-body">
                    <div class="adm-mix">
                        <div class="adm-mix-row">
                            <strong>Students · {{ $studentShare }}%</strong>
                            <p>{{ $totalStudents }} registered student accounts.</p>
                            <div class="adm-track"><div class="adm-fill" style="width:{{ $studentShare }}%"></div></div>
                        </div>
                        <div class="adm-mix-row">
                            <strong>Counselors · {{ $counselorShare }}%</strong>
                            <p>{{ $totalCounselors }} counselor accounts.</p>
                            <div class="adm-track"><div class="adm-fill gold" style="width:{{ $counselorShare }}%"></div></div>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="adm-card">
                <div class="adm-card-head">
                    <div class="adm-card-title">Quick access</div>
                </div>
                <div class="adm-card-body">
                    <div class="adm-shortcuts">
                        <a href="{{ route('admin.users') }}" class="adm-shortcut">
                            <span class="adm-shortcut-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8"/></svg></span>
                            <span><strong>Users</strong><span>Manage platform accounts</span></span>
                        </a>
                        <a href="{{ route('admin.reports') }}" class="adm-shortcut">
                            <span class="adm-shortcut-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg></span>
                            <span><strong>Reports</strong><span>Review wellbeing analytics</span></span>
                        </a>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section class="adm-panel" data-adm-panel="activity">
        <section class="adm-card">
            <div class="adm-card-head">
                <div class="adm-card-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Recent emotional entries
                </div>
                <span class="adm-pill">Latest activity</span>
            </div>
            <div class="adm-card-body">
                <div class="adm-activity">
                    @forelse($recentEntries->take(8) as $entry)
                        <article class="adm-entry">
                            <span class="adm-entry-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M16 10h.01M9 15a4 4 0 006 0M12 21a9 9 0 100-18 9 9 0 000 18z"/></svg>
                            </span>
                            <span class="adm-entry-copy">
                                <strong>{{ $entry->user->name ?? 'Unknown User' }} · {{ ucfirst($entry->detected_emotion ?? 'N/A') }}</strong>
                                <p>{{ $entry->entry_text ? \Illuminate\Support\Str::limit($entry->entry_text, 110) : 'No written journal text.' }}</p>
                            </span>
                            <span class="adm-entry-time">{{ $entry->created_at->diffForHumans() }}</span>
                        </article>
                    @empty
                        <div class="adm-empty">
                            <strong>No recent journal activity</strong>
                            New wellbeing entries will appear here.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    </section>

    <section class="adm-panel" data-adm-panel="tools">
        <div class="adm-tools-grid">
            <a href="{{ route('admin.users') }}" class="adm-tool">
                <span class="adm-tool-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8"/></svg></span>
                <strong>Manage users</strong>
                <p>Review, organize, and administer student, counselor, and administrator accounts.</p>
            </a>

            <a href="{{ route('admin.create-user') }}" class="adm-tool">
                <span class="adm-tool-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m7-7H5"/></svg></span>
                <strong>Create user</strong>
                <p>Add a student or counselor account using the existing administration workflow.</p>
            </a>

            <a href="{{ route('admin.reports') }}" class="adm-tool">
                <span class="adm-tool-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg></span>
                <strong>Reports & analytics</strong>
                <p>Open the dedicated trends, distribution, risk, and engagement workspace.</p>
            </a>

            <a href="{{ route('student.community') }}" class="adm-tool">
                <span class="adm-tool-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h8M8 14h5m7-2a8 8 0 01-8 8c-1.4 0-2.7-.35-3.8-.96L4 20l.96-4.2A8 8 0 1120 12z"/></svg></span>
                <strong>Community Circle</strong>
                <p>Open the shared feed, with admin review and pin controls on each post.</p>
            </a>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('[data-adm-tab]');
    const panels = document.querySelectorAll('[data-adm-panel]');

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            const target = button.dataset.admTab;

            buttons.forEach(function (item) {
                item.classList.toggle('active', item === button);
                item.setAttribute('aria-pressed', String(item === button));
            });

            panels.forEach(function (panel) {
                panel.classList.toggle('active', panel.dataset.admPanel === target);
            });
        });
    });
});
</script>
@endsection
