@extends('layouts.app')

@section('title', 'MAGI Insights · ' . config('branding.name'))

@section('content')
<style id="magi-insights-v4-styles">
    .insights-clean-shell-v3 {
        --mi-forest: #103f32; --mi-emerald: #236b55; --mi-mint: #e9f4ed;
        --mi-gold: #d6a93c; --mi-ink: #203b31; --mi-muted: #6b8177;
        --mi-line: rgba(16, 63, 50, .12); --mi-shadow: 0 18px 44px rgba(16, 63, 50, .08);
        width: min(100%, 1220px); margin: 0 auto; padding: 30px 30px 76px; color: var(--mi-ink); box-sizing: border-box;
    }
    .insights-clean-shell-v3 *, .insights-clean-shell-v3 *::before, .insights-clean-shell-v3 *::after { box-sizing: border-box; }
    .insights-clean-shell-v3 button, .insights-clean-shell-v3 input, .insights-clean-shell-v3 select { font: inherit; }
    .mi-pagebar-v3 { display:flex; align-items:end; justify-content:space-between; gap:24px; margin-bottom:22px; }
    .mi-pagebar-copy-v3 { max-width:760px; min-width:0; }
    .mi-pagebar-eyebrow-v3 { display:inline-flex; align-items:center; gap:8px; margin-bottom:10px; color:var(--mi-emerald); font-size:11px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; }
    .mi-pagebar-eyebrow-v3::before { content:""; width:22px; height:2px; border-radius:999px; background:var(--mi-gold); }
    .mi-pagebar-v3 h1 { margin:0; color:var(--mi-forest); font-size:clamp(2rem,4vw,3.1rem); line-height:1.02; letter-spacing:-.045em; }
    .mi-pagebar-v3 p { margin:10px 0 0; color:var(--mi-muted); font-size:1rem; line-height:1.65; max-width:680px; }
    .mi-tabs { position:sticky; top:14px; z-index:20; display:flex; gap:6px; width:max-content; max-width:100%; margin:0 0 22px; padding:6px; overflow-x:auto; background:rgba(255,255,255,.9); border:1px solid var(--mi-line); border-radius:16px; box-shadow:0 8px 22px rgba(16,63,50,.06); backdrop-filter:blur(12px); }
    .mi-tab { display:inline-flex; align-items:center; gap:8px; min-height:42px; padding:0 15px; border:0; border-radius:11px; background:transparent; color:var(--mi-muted); font-size:.88rem; font-weight:750; white-space:nowrap; cursor:pointer; transition:background .2s ease,color .2s ease,transform .2s ease; }
    .mi-tab:hover { color:var(--mi-forest); background:var(--mi-mint); }
    .mi-tab.active { color:#fff; background:var(--mi-forest); box-shadow:0 7px 18px rgba(16,63,50,.16); }
    .mi-tab:focus-visible,.mi-action:focus-visible,.mi-periods button:focus-visible,.mi-history-search:focus-visible,.mi-sort:focus-visible { outline:3px solid rgba(214,169,60,.45); outline-offset:2px; }
    .mi-tab svg { width:17px; height:17px; flex:0 0 auto; }
    .insight-section { display:none; animation:mi-fade-in .28s ease both; }
    .insight-section.active { display:block; }
    @keyframes mi-fade-in { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }
    .mi-hero { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:24px; padding:clamp(22px,4vw,36px); margin-bottom:18px; overflow:hidden; border:1px solid #d9e9dd; border-radius:24px; background:radial-gradient(circle at 92% 12%,rgba(214,169,60,.15),transparent 180px),linear-gradient(135deg,#f5fbf6,#eef7f0 58%,#fffdf7); box-shadow:var(--mi-shadow); }
    .mi-hero-copy { max-width:720px; }
    .mi-eyebrow,.mi-card-kicker { display:block; margin-bottom:8px; color:var(--mi-emerald); font-size:.72rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
    .mi-hero h2 { margin:0; color:var(--mi-forest); font-size:clamp(1.55rem,3vw,2.25rem); letter-spacing:-.035em; line-height:1.12; }
    .mi-hero p { margin:11px 0 0; color:var(--mi-muted); line-height:1.7; max-width:640px; }
    .mi-hero-visual { display:grid; place-items:center; width:104px; height:104px; border-radius:30px; background:rgba(255,255,255,.86); border:1px solid #d9e9dd; box-shadow:0 14px 28px rgba(16,63,50,.1); }
    .mi-hero-visual img { width:78px; height:78px; object-fit:contain; }
    .mi-action { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px; padding:0 16px; border:1px solid transparent; border-radius:11px; color:#fff; background:var(--mi-forest); font-size:.86rem; font-weight:800; text-decoration:none; cursor:pointer; transition:transform .2s ease,background .2s ease,box-shadow .2s ease; }
    .mi-action:hover { transform:translateY(-2px); background:var(--mi-emerald); box-shadow:0 10px 22px rgba(16,63,50,.16); }
    .mi-action--secondary { color:var(--mi-forest); background:#fff; border-color:#cfe1d6; }
    .mi-action--secondary:hover { background:var(--mi-mint); }
    .mi-hero-actions,.mi-recommendation-actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:18px; }
    .mi-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
    .mi-metric { min-width:0; padding:18px; border:1px solid var(--mi-line); border-radius:18px; background:#fff; box-shadow:0 9px 24px rgba(16,63,50,.045); }
    .mi-metric-label { display:block; color:var(--mi-muted); font-size:.76rem; font-weight:750; }
    .mi-metric-value { display:block; margin-top:8px; color:var(--mi-forest); font-size:1.75rem; font-weight:850; letter-spacing:-.04em; line-height:1; overflow-wrap:anywhere; }
    .mi-metric-note { display:block; margin-top:7px; color:var(--mi-muted); font-size:.77rem; line-height:1.45; }
    .mi-main-grid { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(280px,.8fr); gap:16px; }
    .mi-overview-grid { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr); gap:16px; }
    .mi-overview-card { min-width:0; padding:clamp(18px,3vw,25px); border:1px solid var(--mi-line); border-radius:20px; background:#fff; box-shadow:var(--mi-shadow); }
    .mi-overview-card h3 { margin:0; color:var(--mi-forest); font-size:1.1rem; letter-spacing:-.02em; }
    .mi-overview-card p { color:var(--mi-muted); line-height:1.6; }
    .mi-overview-greeting { display:flex; align-items:flex-end; justify-content:space-between; gap:18px; margin-bottom:16px; }
    .mi-overview-greeting h2 { margin:0; color:var(--mi-forest); font-size:clamp(1.55rem,3vw,2.25rem); letter-spacing:-.035em; line-height:1.1; }
    .mi-overview-greeting p { margin:8px 0 0; }
    .mi-overview-snapshot { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-top:16px; }
    .mi-overview-snapshot article { min-width:0; padding:14px; border:1px solid #e0ede3; border-radius:14px; background:#f7fbf8; }
    .mi-overview-snapshot span { display:block; color:var(--mi-muted); font-size:.76rem; font-weight:750; }
    .mi-overview-snapshot strong { display:block; margin-top:7px; color:var(--mi-forest); font-size:1.35rem; overflow-wrap:anywhere; }
    .mi-overview-snapshot small { display:block; margin-top:5px; color:var(--mi-muted); line-height:1.4; }
    .mi-overview-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; margin-top:16px; }
    .mi-overview-actions a { display:flex; align-items:center; justify-content:center; min-height:42px; padding:0 10px; border:1px solid #cfe1d6; border-radius:10px; color:var(--mi-emerald); background:#fff; font-size:.8rem; font-weight:800; text-align:center; text-decoration:none; }
    .mi-overview-actions a:hover { color:var(--mi-forest); background:var(--mi-mint); }
    .mi-overview-list { display:grid; gap:9px; margin-top:14px; }
    .mi-overview-list-item { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:11px 12px; border-radius:12px; background:#f7fbf8; }
    .mi-overview-list-item strong { color:var(--mi-ink); font-size:.84rem; overflow-wrap:anywhere; }
    .mi-overview-list-item span { flex:0 0 auto; color:var(--mi-muted); font-size:.74rem; }
    .mi-card,.mi-modern-card { min-width:0; padding:clamp(18px,3vw,25px); border:1px solid var(--mi-line); border-radius:20px; background:#fff; box-shadow:var(--mi-shadow); }
    .mi-card-head { display:flex; align-items:start; justify-content:space-between; gap:12px; margin-bottom:16px; }
    .mi-card h3 { margin:0; color:var(--mi-forest); font-size:1.1rem; letter-spacing:-.02em; }
    .mi-card p { color:var(--mi-muted); line-height:1.6; }
    .mi-chip { display:inline-flex; align-items:center; min-height:29px; padding:0 10px; border-radius:99px; background:var(--mi-mint); color:var(--mi-emerald); font-size:.76rem; font-weight:800; white-space:nowrap; }
    .mi-emotion-list { display:grid; gap:12px; }
    .mi-emotion-label { display:flex; align-items:center; justify-content:space-between; gap:12px; color:var(--mi-ink); font-size:.82rem; font-weight:700; }
    .mi-emotion-label b { color:var(--mi-emerald); font-size:.75rem; }
    .mi-emotion-track { height:8px; margin-top:7px; overflow:hidden; border-radius:99px; background:#edf3ee; }
    .mi-emotion-fill { display:block; height:100%; min-width:4px; border-radius:inherit; background:linear-gradient(90deg,#75ad8f,#236b55); }
    .mi-empty { display:grid; gap:6px; place-items:start; padding:22px; border:1px dashed #cfe1d6; border-radius:15px; background:#f8fcf9; }
    .mi-empty strong { color:var(--mi-forest); }
    .mi-empty span { color:var(--mi-muted); font-size:.9rem; }
    .mi-empty a,.mi-modern-link { color:#12664b; font-weight:800; text-decoration:none; }
    .mi-empty a:hover,.mi-modern-link:hover { text-decoration:underline; }
    .mi-appointment { padding:15px; border-radius:14px; background:#f6fbf7; border:1px solid #dcebe0; }
    .mi-appointment small { display:block; color:var(--mi-emerald); font-size:.74rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
    .mi-appointment strong { display:block; margin-top:8px; color:var(--mi-forest); }
    .mi-appointment p { margin:6px 0 0; font-size:.88rem; }
    .mi-appointment-actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:17px; }
    .mi-appointment-actions a { color:#12664b; font-size:.82rem; font-weight:800; text-decoration:none; }
    .mi-appointment-actions a.primary { padding:9px 12px; color:#fff; border-radius:9px; background:var(--mi-forest); }
    .mi-section-head { display:flex; align-items:end; justify-content:space-between; gap:18px; margin-bottom:16px; }
    .mi-section-head h2 { margin:0; color:var(--mi-forest); font-size:clamp(1.5rem,3vw,2rem); letter-spacing:-.035em; }
    .mi-section-head p { margin:7px 0 0; color:var(--mi-muted); line-height:1.6; }
    .mi-periods { display:flex; flex-wrap:wrap; gap:7px; margin:16px 0 20px; }
    .mi-periods button { padding:8px 11px; border:1px solid #cfe1d6; border-radius:9px; color:var(--mi-emerald); background:#fff; font-size:.78rem; font-weight:750; cursor:pointer; }
    .mi-periods button[aria-pressed="true"] { color:#fff; border-color:var(--mi-forest); background:var(--mi-forest); }
    .mi-chart-wrap { min-width:0; overflow-x:auto; padding:6px 0 2px; }
    .mi-chart { display:flex; align-items:end; gap:clamp(8px,2vw,18px); min-height:190px; min-width:520px; padding:16px 6px 2px; border-bottom:1px solid #dce9e1; }
    .mi-bar-group { display:flex; flex:1 0 48px; min-width:48px; height:170px; flex-direction:column; align-items:center; justify-content:end; color:var(--mi-muted); font-size:.7rem; }
    .mi-bar-group strong { color:var(--mi-forest); font-size:.74rem; }
    .mi-bar { width:min(36px,70%); min-height:3px; margin-top:5px; border-radius:8px 8px 3px 3px; background:linear-gradient(180deg,#82bea0,#236b55); transition:height .3s ease; }
    .mi-bar-group span { margin-top:7px; white-space:nowrap; }
    .mi-dist { display:flex; flex-wrap:wrap; gap:7px; margin-top:17px; }
    .mi-dist .mi-chip { background:#f1f8f2; }
    .mi-note { margin:12px 0 0; font-size:.82rem; }
    .chart-empty-state { margin:13px 0 0; color:var(--mi-muted); font-size:.9rem; }
    .mi-pattern { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-top:16px; }
    .mi-pattern article { padding:15px; border-radius:14px; background:#f7fbf8; border:1px solid #e0ede3; }
    .mi-pattern strong { display:block; color:var(--mi-forest); font-size:1.05rem; }
    .mi-pattern span { display:block; margin-top:5px; color:var(--mi-muted); font-size:.78rem; line-height:1.45; }
    .mi-history-toolbar { display:grid; grid-template-columns:minmax(0,1fr) auto auto; gap:9px; margin-bottom:17px; }
    .mi-history-search,.mi-sort { width:100%; min-height:42px; padding:0 12px; border:1px solid #cfe1d6; border-radius:10px; color:var(--mi-ink); background:#fff; }
    .mi-history-search::placeholder { color:#8aa096; }
    .mi-history { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
    .mi-history-card { display:flex; min-width:0; flex-direction:column; padding:17px; border:1px solid #dce9e1; border-radius:15px; background:#fff; transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease; }
    .mi-history-card:hover { transform:translateY(-2px); border-color:#aacdb7; box-shadow:0 11px 24px rgba(16,63,50,.07); }
    .mi-history-card[hidden] { display:none; }
    .mi-history-card h3 { margin:0; color:var(--mi-forest); font-size:1rem; overflow-wrap:anywhere; }
    .mi-history-card time { display:block; margin-top:7px; color:var(--mi-muted); font-size:.76rem; }
    .mi-history-card p { display:-webkit-box; margin:12px 0 16px; overflow:hidden; color:var(--mi-muted); font-size:.86rem; line-height:1.55; -webkit-box-orient:vertical; -webkit-line-clamp:3; }
    .mi-history-card .mi-modern-link { margin-top:auto; font-size:.82rem; }
    .mi-history-empty { padding:32px; border:1px dashed #cfe1d6; border-radius:15px; text-align:center; background:#f8fcf9; }
    .mi-history-empty strong { display:block; color:var(--mi-forest); }
    .mi-history-empty p { margin:7px 0 15px; color:var(--mi-muted); }
    .mi-magi-panel { display:grid; grid-template-columns:minmax(0,.85fr) minmax(0,1.15fr); gap:16px; margin-bottom:16px; }
    .mi-magi-intro { display:flex; min-height:240px; flex-direction:column; justify-content:space-between; padding:24px; border-radius:20px; color:#fff; background:radial-gradient(circle at 85% 10%,rgba(244,200,78,.2),transparent 150px),linear-gradient(145deg,#103f32,#236b55); box-shadow:var(--mi-shadow); }
    .mi-magi-intro h2 { margin:0; color:#fff; font-size:1.65rem; letter-spacing:-.03em; }
    .mi-magi-intro p { margin:9px 0 0; color:rgba(255,255,255,.78); line-height:1.6; }
    .mi-magi-intro img { width:66px; height:66px; margin-bottom:18px; object-fit:contain; filter:drop-shadow(0 9px 15px rgba(0,0,0,.16)); }
    .mi-magi-intro .mi-action { color:var(--mi-forest); background:#fff; }
    .mi-magi-intro .mi-action:hover { background:#f4fbf5; }
    .mi-recommendation-card { min-width:0; padding:22px; border:1px solid #dce9e1; border-radius:20px; background:#fff; box-shadow:var(--mi-shadow); }
    .mi-recommendation-card h3 { margin:0; color:var(--mi-forest); font-size:1.08rem; }
    .mi-recommendation-card > p { margin:7px 0 16px; color:var(--mi-muted); font-size:.88rem; line-height:1.55; }
    .mi-recommendation-card .magi-recommendations { color:var(--mi-ink); }
    .mi-recommendation-card .magi-recommendations__item { border-color:#dce9e1; background:#fbfefb; }
    .mi-recommendation-card .magi-recommendations__item h3 { font-size:.98rem; }
    .mi-recommendation-card .magi-recommendations__refresh { color:#fff; border-color:var(--mi-forest); background:var(--mi-forest); font-weight:800; }
    .mi-recommendation-card .magi-recommendations__refresh:hover { background:var(--mi-emerald); }
    .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    @media (max-width:980px) { .insights-clean-shell-v3 { padding:24px 20px 58px; } .mi-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } .mi-main-grid,.mi-overview-grid,.mi-magi-panel { grid-template-columns:1fr; } }
    @media (max-width:640px) { .insights-clean-shell-v3 { padding:18px 12px 48px; } .mi-pagebar-v3 { margin-bottom:17px; } .mi-pagebar-v3 p { font-size:.9rem; } .mi-tabs { top:8px; width:100%; border-radius:13px; } .mi-tab { flex:1 0 auto; justify-content:center; padding:0 11px; font-size:.77rem; } .mi-tab svg { display:none; } .mi-hero { grid-template-columns:1fr; padding:21px; } .mi-hero-visual { width:78px; height:78px; border-radius:22px; } .mi-hero-visual img { width:58px; height:58px; } .mi-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; } .mi-metric { padding:14px; border-radius:14px; } .mi-metric-value { font-size:1.4rem; } .mi-card,.mi-overview-card,.mi-modern-card,.mi-recommendation-card { padding:16px; border-radius:16px; } .mi-section-head,.mi-overview-greeting { display:block; } .mi-pattern { grid-template-columns:1fr; } .mi-history-toolbar { grid-template-columns:1fr; } .mi-history { grid-template-columns:1fr; } .mi-magi-intro { min-height:215px; padding:20px; } }
    @media (prefers-reduced-motion:reduce) { .insights-clean-shell-v3 *,.insights-clean-shell-v3 *::before,.insights-clean-shell-v3 *::after { animation-duration:.01ms !important; transition-duration:.01ms !important; scroll-behavior:auto !important; } }
</style>

<div class="insights-clean-shell-v3" data-insights-page>
    <header class="mi-pagebar-v3"><div class="mi-pagebar-copy-v3"><span class="mi-pagebar-eyebrow-v3">Student wellness</span><h1>MAGI Insights</h1><p>Thoughtful patterns, conversation history, and resources in one calm space—grounded in what you have chosen to record.</p></div></header>
    <nav class="mi-tabs" role="tablist" aria-label="Insights sections">
        <button id="overview-tab" type="button" class="mi-tab active" data-mi-section="overview" aria-selected="true" aria-controls="section-overview"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 11.5 12 4l8 7.5V20a1 1 0 0 1-1 1h-5v-6h-4v6H5a1 1 0 0 1-1-1z"/></svg>Overview</button>
        <button id="trends-tab" type="button" class="mi-tab" data-mi-section="statistics" aria-selected="false" aria-controls="section-statistics"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V5m0 14h16M8 16v-4m4 4V8m4 8v-6"/></svg>Trends</button>
        <button id="conversations-tab" type="button" class="mi-tab" data-mi-section="conversations" aria-selected="false" aria-controls="section-conversations"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.4-4 8-9 8a10 10 0 01-4.25-.95L3 20l1.4-3.72A7.3 7.3 0 013 12c0-4.4 4-8 9-8s9 3.6 9 8z"/></svg>Conversations</button>
        <button id="recommendations-tab" type="button" class="mi-tab" data-mi-section="recommendations" aria-selected="false" aria-controls="section-recommendations"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v18m9-9H3m15.36-6.36L5.64 18.36m0-12.72 12.72 12.72"/></svg>AI Insights</button>
    </nav>

    <section id="section-overview" class="insight-section active" role="tabpanel" aria-labelledby="overview-tab">
        <div class="mi-overview-greeting"><div><span class="mi-pagebar-eyebrow-v3">Your space at a glance</span><h2>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}.</h2><p>{{ now()->format('l, F j') }} · A private view of what you have recorded.</p></div><a class="mi-action" href="{{ route('student.ai-chat') }}">Talk to MAGI <span aria-hidden="true">→</span></a></div>
        <div class="mi-overview-grid">
            <article class="mi-overview-card"><div class="mi-card-head"><div><span class="mi-card-kicker">Today’s recorded labels</span><h3>Notice what is present</h3></div><span class="mi-chip">{{ $todayMessages }} {{ $todayMessages === 1 ? 'entry' : 'entries' }}</span></div>@if($todayMessages > 0)<div class="mi-overview-snapshot">@foreach($todayEmotionCounts as $emotion => $count)@if($count > 0)<article><span>{{ ucfirst($emotion) }}</span><strong>{{ $count }}</strong><small>recorded label{{ $count === 1 ? '' : 's' }}</small></article>@endif @endforeach</div><p class="mi-note">These are labels saved with your own entries, not a diagnosis or a measure of your wellbeing.</p>@else<div class="mi-empty"><strong>No entries recorded today</strong><span>Your overview will reflect your activity when you choose to write or chat.</span><a href="{{ route('student.ai-chat') }}">Start a conversation <span aria-hidden="true">→</span></a></div>@endif</article>
            <article class="mi-overview-card"><div class="mi-card-head"><div><span class="mi-card-kicker">Support context</span><h3>What is next</h3></div><span class="mi-chip" aria-hidden="true">●</span></div>@if($upcomingAppointment)<div class="mi-appointment"><small>Confirmed appointment</small><strong>{{ optional($upcomingAppointment->counselor)->name ?? 'Your counselor' }}</strong><p>{{ optional($upcomingAppointment->appointment_date)->format('l, F j \a\t g:i A') }}</p></div>@else<div class="mi-appointment"><small>No upcoming session</small><strong>Your schedule is clear.</strong><p>Request professional support whenever you feel ready.</p></div>@endif @if($mentalStatus)<div class="mi-overview-snapshot"><article><span>Latest signal</span><strong>{{ $mentalStatus['score'] }}/100</strong><small>Non-clinical wellbeing indicator · {{ $mentalStatus['status'] }}</small></article><article><span>Recorded emotion</span><strong>{{ ucfirst($mentalStatus['emotion'] ?? 'Not available') }}</strong><small>From the latest usage signal</small></article></div>@endif<div class="mi-appointment-actions"><a href="{{ route('student.appointments') }}">View appointments</a><a href="{{ route('student.counselor-directory') }}" class="primary">Find a counselor</a></div></article>
        </div>
        <div class="mi-overview-grid" style="margin-top:16px"><article class="mi-overview-card"><div class="mi-card-head"><div><span class="mi-card-kicker">Recent activity</span><h3>Your latest entries</h3></div><a class="mi-modern-link" href="{{ route('student.journal') }}">Open journal <span aria-hidden="true">→</span></a></div>@if($recentEntries->isNotEmpty())<div class="mi-overview-list">@foreach($recentEntries->take(3) as $entry)<div class="mi-overview-list-item"><strong>{{ \Illuminate\Support\Str::limit($entry->entry_text, 90) }}</strong><span>{{ $entry->created_at->diffForHumans() }}</span></div>@endforeach</div>@else<div class="mi-empty"><strong>No recent activity yet</strong><span>Write a private reflection or talk with MAGI when you are ready.</span></div>@endif</article><article class="mi-overview-card"><div class="mi-card-head"><div><span class="mi-card-kicker">Quick actions</span><h3>Keep moving at your pace</h3></div></div><div class="mi-overview-actions"><a href="{{ route('student.journal') }}">Write in journal</a><a href="{{ route('student.coping-toolkit') }}">Open coping toolkit</a><a href="{{ route('student.resources') }}">Browse resources</a><a href="{{ route('student.emergency-resources') }}">Emergency support</a></div></article></div>
    </section>

    <section id="section-statistics" class="insight-section" role="tabpanel" aria-labelledby="trends-tab" hidden>
        <div class="mi-hero"><div class="mi-hero-copy"><span class="mi-eyebrow">Your wellness trends</span><h2>Notice what you are recording, at your own pace.</h2><p>Explore activity patterns and recorded labels without turning them into a diagnosis or a score of your wellbeing.</p><div class="mi-hero-actions"><a class="mi-action" href="{{ route('student.ai-chat') }}">Talk to MAGI <span aria-hidden="true">→</span></a><a class="mi-action mi-action--secondary" href="{{ route('student.journal') }}">Write a reflection</a></div></div><div class="mi-hero-visual" aria-hidden="true"><img src="{{ asset('assets/branding/magi-avatar-static.png') }}" alt=""></div></div>
        <div class="mi-metrics" aria-label="Recorded activity summary"><article class="mi-metric"><span class="mi-metric-label">Recorded observations</span><strong class="mi-metric-value">{{ $totalEntries }}</strong><span class="mi-metric-note">Messages and reflections recorded</span></article><article class="mi-metric"><span class="mi-metric-label">Active days</span><strong class="mi-metric-value">{{ $activeDaysLastSeven }}</strong><span class="mi-metric-note">In the last seven days</span></article><article class="mi-metric"><span class="mi-metric-label">Most recorded label</span><strong class="mi-metric-value" style="font-size:1.18rem">{{ $dominantEmotion ? ucfirst($dominantEmotion) : 'Not enough data' }}</strong><span class="mi-metric-note">Recorded label, not a clinical measure</span></article><article class="mi-metric"><span class="mi-metric-label">This week</span><strong class="mi-metric-value">{{ $thisWeek }}</strong><span class="mi-metric-note">{{ $thisWeek === 1 ? 'conversation' : 'conversations' }}</span></article></div>
        <div class="mi-main-grid"><article class="mi-card"><div class="mi-card-head"><div><span class="mi-card-kicker">Emotion activity timeline</span><h3>Recorded observations over time</h3></div><span class="mi-chip">{{ $totalConversations }} {{ $totalConversations === 1 ? 'conversation' : 'conversations' }}</span></div><p class="mi-note"><strong>Total Conversations:</strong> {{ $totalConversations }} · Counts reflect labels saved with your own entries. Unknown or missing labels are not filled in.</p>@if(($todayMessages ?? 0) === 0)<p class="mi-note">No emotional signals yet today.</p>@endif<div class="mi-periods" role="group" aria-label="Trend time period"><button type="button" data-trend-period="day" aria-pressed="true">Daily · 7 days</button><button type="button" data-trend-period="week" aria-pressed="false">Weekly · 12 weeks</button><button type="button" data-trend-period="month" aria-pressed="false">Monthly · 12 months</button></div><p id="mi-trend-empty" class="chart-empty-state" @if($trendObservations->isNotEmpty()) hidden @endif>Not enough recorded emotion observations yet. Your chart will appear when observations are available.</p><div class="mi-chart-wrap"><div id="mi-observation-chart" class="mi-chart" aria-label="Recorded observations by time period"></div></div><div id="mi-observation-distribution" class="mi-dist" aria-label="Recorded emotion distribution"></div></article><article class="mi-card"><div class="mi-card-head"><div><span class="mi-card-kicker">Support context</span><h3>What’s coming up</h3></div><span class="mi-chip" aria-hidden="true">●</span></div>@if($upcomingAppointment)<div class="mi-appointment"><small>Confirmed appointment</small><strong>{{ optional($upcomingAppointment->counselor)->name ?? 'Your counselor' }}</strong><p>{{ optional($upcomingAppointment->appointment_date)->format('l, F j \a\t g:i A') }}</p></div>@else<div class="mi-appointment"><small>No upcoming session</small><strong>Your schedule is clear.</strong><p>You can request professional support whenever you feel ready.</p></div>@endif<div class="mi-appointment-actions"><a href="{{ route('student.appointments') }}">View appointments</a><a href="{{ route('student.counselor-directory') }}" class="primary">Find a counselor</a></div></article></div>
        <div class="mi-pattern" aria-label="Activity patterns"><article><strong>{{ $activeDaysLastSeven }}</strong><span>active {{ $activeDaysLastSeven === 1 ? 'day' : 'days' }} in the last seven</span></article><article><strong>{{ $thisWeekMessages }}</strong><span>{{ $thisWeekMessages === 1 ? 'message' : 'messages' }} recorded this week</span></article><article><strong>{{ $todayMessages }}</strong><span>{{ $todayMessages === 1 ? 'message' : 'messages' }} recorded today</span></article></div>
    </section>

    <section id="section-conversations" class="insight-section" role="tabpanel" aria-labelledby="conversations-tab" hidden><div class="mi-section-head"><div><span class="mi-pagebar-eyebrow-v3">Private archive</span><h2>Your conversations</h2><p>Revisit meaningful moments and continue where you left off.</p></div><a class="mi-action mi-action--secondary" href="{{ route('student.ai-chat') }}">New conversation <span aria-hidden="true">→</span></a></div><div class="mi-modern-card">@if($insightSessions->isNotEmpty())<div class="mi-history-toolbar"><label class="sr-only" for="mi-history-search">Search conversations</label><input id="mi-history-search" class="mi-history-search" type="search" placeholder="Search titles and messages"><label class="sr-only" for="mi-history-date">Filter by date</label><input id="mi-history-date" class="mi-history-search" type="date" aria-label="Filter by date"><label class="sr-only" for="mi-history-sort">Sort conversations</label><select id="mi-history-sort" class="mi-sort" aria-label="Sort conversations"><option value="recent">Most recent</option><option value="oldest">Oldest</option></select></div>@endif<div class="mi-history" id="mi-history-list">@forelse($insightSessions as $session)@php($firstMessage = $session->entries->first())<article class="mi-history-card" data-history-card data-history-date="{{ optional($session->last_message_at ?? $session->created_at)->format('Y-m-d') }}" data-history-time="{{ optional($session->last_message_at ?? $session->created_at)->timestamp ?? 0 }}"><h3>{{ $session->title ?: 'Conversation' }}</h3><time datetime="{{ optional($session->last_message_at ?? $session->created_at)->toIso8601String() }}">{{ optional($session->last_message_at ?? $session->created_at)->format('M j, Y · g:i A') }}</time>@if($firstMessage)<p>{{ \Illuminate\Support\Str::limit($firstMessage->entry_text, 180) }}</p>@else<p>No message preview available.</p>@endif<a class="mi-modern-link" href="{{ route('student.ai-chat', ['session' => $session->id]) }}">Open conversation <span aria-hidden="true">→</span></a></article>@empty<div class="mi-history-empty"><strong>No conversations yet</strong><p>When you chat with MAGI, your private conversation archive will appear here.</p><a class="mi-action" href="{{ route('student.ai-chat') }}">Talk to MAGI <span aria-hidden="true">→</span></a></div>@endforelse</div>@if($insightSessions->isNotEmpty())<p id="mi-history-no-match" class="mi-history-empty" hidden>No conversations match your search.</p>@endif</div></section>

    <section id="section-recommendations" class="insight-section" role="tabpanel" aria-labelledby="recommendations-tab" hidden><div class="mi-section-head"><div><span class="mi-pagebar-eyebrow-v3">Personalized support</span><h2>MAGI Insights</h2><p>Thoughtful resources and suggestions based on the conversations you choose to share.</p></div></div><div class="mi-magi-panel"><article class="mi-magi-intro"><div><img src="{{ asset('assets/branding/magi-avatar-static.png') }}" alt="MAGI mascot"><h2>A quieter way to find support.</h2><p>MAGI can look for relevant resources in the wellness library when you give current permission. Opening this tab never starts an analysis by itself.</p></div><div class="mi-recommendation-actions"><a class="mi-action" href="{{ route('student.ai-chat') }}">Talk to MAGI <span aria-hidden="true">→</span></a><a class="mi-action mi-action--secondary" href="{{ route('student.resources') }}">Explore resources</a></div></article><article class="mi-recommendation-card"><h3>Recommended for you</h3><p>Only validated resources from the current wellness catalog appear here.</p>@include('student.partials.recommendation-availability', ['recommendationState' => $insightRecommendationState])</article></div></section>
</div>

<script>
(() => {
    const root = document.querySelector('[data-insights-page]'); if (!root) return;
    const sections = ['overview','statistics','conversations','recommendations'];
    const showSection = (sectionName) => { const section = sections.includes(sectionName) ? sectionName : 'overview'; root.querySelectorAll('.insight-section').forEach((panel) => { const active = panel.id === `section-${section}`; panel.classList.toggle('active', active); panel.hidden = !active; }); root.querySelectorAll('.mi-tab').forEach((tab) => { const active = tab.dataset.miSection === section; tab.classList.toggle('active', active); tab.setAttribute('aria-selected', String(active)); }); if (history.replaceState) history.replaceState(null, '', `#${section}`); };
    window.showSection = showSection; root.querySelectorAll('.mi-tab').forEach((tab) => tab.addEventListener('click', () => showSection(tab.dataset.miSection)));
    const observations = @json($trendObservations->map(fn($entry) => ['date' => $entry->created_at->format('Y-m-d'), 'emotion' => $entry->detected_emotion])->values());
    const chart = root.querySelector('#mi-observation-chart'), dist = root.querySelector('#mi-observation-distribution'), empty = root.querySelector('#mi-trend-empty');
    const draw = (period) => { if (!chart || !dist || !empty) return; const now = new Date(); now.setHours(0,0,0,0); const bucketCount = period === 'day' ? 7 : 12; const buckets = []; for (let index = bucketCount - 1; index >= 0; index -= 1) { let start = new Date(now); if (period === 'day') start.setDate(start.getDate() - index); if (period === 'week') start.setDate(start.getDate() - ((start.getDay()+6)%7) - (index*7)); if (period === 'month') start = new Date(now.getFullYear(), now.getMonth()-index, 1); const end = new Date(start); if (period === 'day') end.setDate(end.getDate()+1); if (period === 'week') end.setDate(end.getDate()+7); if (period === 'month') end.setMonth(end.getMonth()+1); buckets.push({start,end,items:[]}); } observations.forEach((item) => { const date = new Date(`${item.date}T12:00:00`); const bucket = buckets.find((candidate) => date >= candidate.start && date < candidate.end); if (bucket) bucket.items.push(item); }); const active = buckets.some((bucket) => bucket.items.length > 0); empty.hidden = active; chart.replaceChildren(); dist.replaceChildren(); chart.hidden = !active; dist.hidden = !active; if (!active) return; const max = Math.max(1,...buckets.map((bucket) => bucket.items.length)); buckets.forEach((bucket) => { const group = document.createElement('div'); group.className='mi-bar-group'; const number=document.createElement('strong'); number.textContent=String(bucket.items.length); const bar=document.createElement('div'); bar.className='mi-bar'; bar.style.height=`${Math.max(3,bucket.items.length/max*125)}px`; const label=document.createElement('span'); label.textContent=bucket.start.toLocaleDateString(undefined,{month:'short',...(period==='month'?{year:'2-digit'}:{day:'numeric'})}); const description=`${label.textContent}: ${bucket.items.length} recorded observation${bucket.items.length===1?'':'s'}`; group.setAttribute('aria-label',description); bar.title=description; group.append(number,bar,label); chart.append(group); }); const counts={}; buckets.flatMap((bucket)=>bucket.items).forEach((item)=>{counts[item.emotion]=(counts[item.emotion]||0)+1;}); Object.entries(counts).sort((a,b)=>b[1]-a[1]).forEach(([label,count])=>{const chip=document.createElement('span');chip.className='mi-chip';chip.textContent=`${label}: ${count}`;dist.append(chip);}); };
    root.querySelectorAll('[data-trend-period]').forEach((button) => button.addEventListener('click', () => { root.querySelectorAll('[data-trend-period]').forEach((item) => item.setAttribute('aria-pressed',String(item===button))); draw(button.dataset.trendPeriod); })); draw('day');
    const search=root.querySelector('#mi-history-search'), dateFilter=root.querySelector('#mi-history-date'), sort=root.querySelector('#mi-history-sort'), list=root.querySelector('#mi-history-list'), noMatch=root.querySelector('#mi-history-no-match');
    const filterHistory = () => { if (!list) return; const term=(search?.value||'').trim().toLocaleLowerCase(), date=dateFilter?.value||'', cards=[...list.querySelectorAll('[data-history-card]')]; cards.sort((a,b)=>{const diff=Number(a.dataset.historyTime||0)-Number(b.dataset.historyTime||0); return sort?.value==='oldest'?diff:-diff;}).forEach((card)=>list.append(card)); let shown=0; cards.forEach((card)=>{const visible=card.textContent.toLocaleLowerCase().includes(term)&&(!date||card.dataset.historyDate===date); card.hidden=!visible; if(visible)shown+=1;}); if(noMatch) noMatch.hidden=shown!==0; };
    search?.addEventListener('input',filterHistory); dateFilter?.addEventListener('change',filterHistory); sort?.addEventListener('change',filterHistory); const hash=window.location.hash.replace('#',''); if(sections.includes(hash)) showSection(hash); else showSection('overview');
})();
</script>
@endsection
