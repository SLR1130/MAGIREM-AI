@extends('layouts.app')

@section('title', 'Counseling requests · MAGIREM AI')

@section('content')
<style>
    .scr, .scr * { box-sizing: border-box; }
    .scr {
        --scr-forest:#103f32;
        --scr-forest-2:#174f3f;
        --scr-emerald:#236b55;
        --scr-mint:#e6f1eb;
        --scr-mint-2:#d8ebe2;
        --scr-ivory:#f7faf7;
        --scr-gold:#f4c84e;
        --scr-text:#263a33;
        --scr-muted:#708179;
        --scr-line:rgba(16,63,50,.12);
        --scr-shadow:0 16px 42px rgba(16,63,50,.07);
        color:var(--scr-text);
        font-family:Inter,"Plus Jakarta Sans",ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
        padding:2px 0 52px;
    }
    body:has(.scr) .content-wrapper { max-width:1460px !important; width:100% !important; }
    .scr-head { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; margin-bottom:18px; }
    .scr-eyebrow { display:inline-flex; align-items:center; gap:8px; color:var(--scr-emerald); font-size:10px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; }
    .scr-eyebrow::before { content:""; width:22px; height:2px; border-radius:99px; background:var(--scr-gold); }
    .scr-head h1 { margin:8px 0 0; color:var(--scr-forest); font-size:clamp(30px,3vw,44px); line-height:1.03; letter-spacing:-.045em; font-weight:820; }
    .scr-head p { max-width:690px; margin:10px 0 0; color:var(--scr-muted); font-size:13px; line-height:1.65; }
    .scr-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:8px; }
    .scr-btn { min-height:40px; display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:0 13px; border-radius:11px; border:1px solid var(--scr-line); text-decoration:none; font-size:10.5px; font-weight:800; transition:.18s ease; }
    .scr-btn:hover { transform:translateY(-1px); }
    .scr-btn svg { width:16px; height:16px; }
    .scr-btn-primary { color:#fff !important; background:var(--scr-forest); border-color:var(--scr-forest); box-shadow:0 10px 24px rgba(16,63,50,.15); }
    .scr-btn-secondary { color:var(--scr-forest) !important; background:#fff; }
    .scr-summary { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; margin-bottom:16px; }
    .scr-summary-card { padding:14px 15px; border:1px solid var(--scr-line); border-radius:15px; background:#fff; box-shadow:var(--scr-shadow); }
    .scr-summary-card strong { display:block; color:var(--scr-forest); font-size:23px; line-height:1; }
    .scr-summary-card span { display:block; margin-top:7px; color:var(--scr-muted); font-size:9px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
    .scr-summary-card.pending { border-top:3px solid #d99918; }
    .scr-summary-card.confirmed { border-top:3px solid #2f8b69; }
    .scr-summary-card.needs_review { border-top:3px solid #c47a2c; }
    .scr-summary-card.completed { border-top:3px solid #3478a6; }
    .scr-summary-card.failed, .scr-summary-card.cancelled { border-top:3px solid #9c6262; }
    .scr-filter { display:flex; align-items:center; gap:7px; overflow-x:auto; padding:4px; margin-bottom:16px; border:1px solid var(--scr-line); border-radius:13px; background:#fff; }
    .scr-filter a { flex:0 0 auto; display:inline-flex; align-items:center; gap:6px; min-height:32px; padding:0 10px; border-radius:9px; color:var(--scr-muted); text-decoration:none; font-size:10px; font-weight:800; white-space:nowrap; }
    .scr-filter a.active { color:var(--scr-forest); background:var(--scr-mint); }
    .scr-filter-count { color:var(--scr-emerald); font-size:9px; }
    .scr-list { display:grid; gap:12px; }
    .scr-card { position:relative; overflow:hidden; padding:18px; border:1px solid var(--scr-line); border-radius:17px; background:#fff; box-shadow:0 10px 28px rgba(16,63,50,.055); }
    .scr-card::before { content:""; position:absolute; inset:0 auto 0 0; width:3px; background:var(--scr-emerald); }
    .scr-card.status-pending::before { background:#d99918; }
    .scr-card.status-needs_review::before { background:#c47a2c; }
    .scr-card.status-completed::before { background:#3478a6; }
    .scr-card.status-failed::before, .scr-card.status-cancelled::before { background:#9c6262; }
    .scr-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:13px; }
    .scr-counselor { min-width:0; display:flex; align-items:center; gap:11px; }
    .scr-avatar { flex:0 0 40px; width:40px; height:40px; display:grid; place-items:center; border-radius:12px; background:var(--scr-mint); color:var(--scr-forest); font-size:13px; font-weight:850; }
    .scr-counselor small { display:block; color:var(--scr-emerald); font-size:8.7px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
    .scr-counselor h2 { margin:4px 0 0; color:var(--scr-forest); font-size:16px; line-height:1.2; letter-spacing:-.02em; }
    .scr-counselor p { margin:4px 0 0; color:var(--scr-muted); font-size:10.5px; }
    .scr-status { flex:0 0 auto; display:inline-flex; align-items:center; gap:6px; min-height:28px; padding:0 10px; border-radius:999px; color:var(--scr-forest); background:var(--scr-mint); font-size:9px; font-weight:850; letter-spacing:.05em; text-transform:uppercase; }
    .scr-status::before { content:""; width:6px; height:6px; border-radius:50%; background:var(--scr-emerald); }
    .scr-status.pending { color:#7e5312; background:#fff4d7; }
    .scr-status.pending::before { background:#d99918; }
    .scr-status.needs_review { color:#7c4b18; background:#fff0dc; }
    .scr-status.needs_review::before { background:#c47a2c; }
    .scr-status.completed { color:#245d86; background:#e5f1fa; }
    .scr-status.completed::before { background:#3478a6; }
    .scr-status.failed, .scr-status.cancelled { color:#7d4d4d; background:#f7eaea; }
    .scr-status.failed::before, .scr-status.cancelled::before { background:#9c6262; }
    .scr-meta { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:9px; margin-bottom:11px; }
    .scr-meta-item { min-width:0; padding:11px; border:1px solid rgba(16,63,50,.07); border-radius:11px; background:var(--scr-ivory); }
    .scr-meta-item span { display:block; color:var(--scr-muted); font-size:8.3px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
    .scr-meta-item strong { display:block; margin-top:5px; color:var(--scr-forest); font-size:11px; line-height:1.45; overflow-wrap:anywhere; }
    .scr-note { padding:12px 13px; border:1px solid rgba(16,63,50,.07); border-radius:11px; background:linear-gradient(135deg,#f1f8f4,#fff); }
    .scr-note-label { margin-bottom:5px; color:var(--scr-forest); font-size:8.8px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
    .scr-note p { margin:0; color:var(--scr-muted); font-size:11px; line-height:1.6; overflow-wrap:anywhere; }
    .scr-card-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:12px; }
    .scr-helper { margin:0; color:var(--scr-muted); font-size:10.5px; line-height:1.5; }
    .scr-card-links { display:flex; gap:7px; flex-wrap:wrap; }
    .scr-link { min-height:33px; display:inline-flex; align-items:center; justify-content:center; padding:0 10px; border-radius:9px; color:var(--scr-forest) !important; background:var(--scr-mint); text-decoration:none; font-size:9.5px; font-weight:800; }
    .scr-link.muted { color:var(--scr-muted) !important; background:#f2f5f3; cursor:default; }
    .scr-empty { display:flex; flex-direction:column; align-items:center; text-align:center; padding:48px 22px; border:1px dashed rgba(16,63,50,.2); border-radius:18px; background:linear-gradient(135deg,#fff,#f4f9f6); }
    .scr-empty-icon { width:52px; height:52px; display:grid; place-items:center; margin-bottom:13px; border-radius:16px; background:var(--scr-mint); color:var(--scr-forest); }
    .scr-empty-icon svg { width:23px; height:23px; }
    .scr-empty h2 { margin:0; color:var(--scr-forest); font-size:19px; letter-spacing:-.025em; }
    .scr-empty p { max-width:560px; margin:8px 0 16px; color:var(--scr-muted); font-size:11.5px; line-height:1.65; }
    .scr-empty-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:center; }
    .scr-info { margin-top:16px; padding:17px 19px; border:1px solid var(--scr-line); border-radius:15px; background:linear-gradient(135deg,#f1f8f4,#fff); }
    .scr-info strong { display:block; color:var(--scr-forest); font-size:12px; }
    .scr-info p { margin:6px 0 0; color:var(--scr-muted); font-size:10.5px; line-height:1.6; }
    body.dusk-mode .scr { --scr-ivory:#0f1e19; --scr-text:#edf5f1; --scr-muted:#9fb6ab; --scr-line:rgba(237,245,241,.1); }
    body.dusk-mode .scr-summary-card, body.dusk-mode .scr-filter, body.dusk-mode .scr-card, body.dusk-mode .scr-empty { background:#152a23; }
    body.dusk-mode .scr-meta-item { background:#1b332b; }
    body.dusk-mode .scr-note, body.dusk-mode .scr-info { background:#183229; }
    body.dusk-mode .scr-head h1, body.dusk-mode .scr-summary-card strong, body.dusk-mode .scr-counselor h2, body.dusk-mode .scr-meta-item strong, body.dusk-mode .scr-note-label, body.dusk-mode .scr-empty h2, body.dusk-mode .scr-info strong { color:#edf5f1; }
    @media(max-width:980px) { .scr-summary { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    @media(max-width:700px) { .scr-head { align-items:flex-start; flex-direction:column; } .scr-actions { justify-content:flex-start; } .scr-summary { grid-template-columns:repeat(2,minmax(0,1fr)); } .scr-card-head { flex-direction:column; } .scr-meta { grid-template-columns:1fr; } }
    @media(max-width:430px) { .scr-summary { grid-template-columns:1fr 1fr; } .scr-card, .scr-empty { padding:15px; } .scr-card-foot { align-items:flex-start; flex-direction:column; } .scr-card-links, .scr-link { width:100%; } }
</style>

@php
    $statusCards = [
        'pending' => 'Pending approval',
        'confirmed' => 'Confirmed',
        'needs_review' => 'Needs review',
        'completed' => 'Completed',
        'failed' => 'Not held',
    ];
@endphp

<div class="scr">
    <header class="scr-head">
        <div>
            <span class="scr-eyebrow">Human support, on your terms</span>
            <h1>Counseling requests</h1>
            <p>Review your requests and appointments in one place. Statuses reflect the authorized appointment lifecycle; an overdue session is shown as Needs review until a counselor verifies its outcome.</p>
        </div>
        <div class="scr-actions">
            <a href="{{ route('student.counselor-directory') }}" class="scr-btn scr-btn-secondary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a4 4 0 00-4-4h-1M7 20H2v-2a4 4 0 014-4h1m5 6v-2a4 4 0 00-4-4H7m5 6h5v-2a4 4 0 00-4-4h-1M9 7a3 3 0 106 0 3 3 0 00-6 0z"/></svg>
                Find a counselor
            </a>
            <a href="{{ route('student.request-appointment') }}" class="scr-btn scr-btn-primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14M5 12h14"/></svg>
                Request appointment
            </a>
        </div>
    </header>

    <section class="scr-summary" aria-label="Appointment summary">
        <div class="scr-summary-card"><strong>{{ $requests instanceof \Illuminate\Support\Collection ? $requests->count() : 0 }}</strong><span>{{ $activeFilter === 'all' ? 'Showing requests' : 'Showing this filter' }}</span></div>
        @foreach($statusCards as $status => $label)
            <div class="scr-summary-card {{ $status }}"><strong>{{ $statusCounts->get($status, 0) }}</strong><span>{{ $label }}</span></div>
        @endforeach
    </section>

    <nav class="scr-filter" aria-label="Filter counseling requests">
        @foreach($allowedFilters as $filter)
            @php
                $filterLabel = $filter === 'all' ? 'All requests' : ($statusCards[$filter] ?? ucfirst(str_replace('_', ' ', $filter)));
                $filterCount = $filter === 'all' ? $statusCounts->sum() : $statusCounts->get($filter, 0);
            @endphp
            <a href="{{ route('student.counseling-requests', ['status' => $filter]) }}" class="{{ $activeFilter === $filter ? 'active' : '' }}">
                {{ $filterLabel }} <span class="scr-filter-count">{{ $filterCount }}</span>
            </a>
        @endforeach
    </nav>

    @if($requests->isEmpty())
        <section class="scr-empty">
            <span class="scr-empty-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2v12a2 2 0 01-2 2z"/></svg>
            </span>
            <h2>{{ $activeFilter === 'all' ? 'No counseling requests yet' : 'No requests in this status' }}</h2>
            <p>{{ $activeFilter === 'all' ? 'When you are ready, browse the counselor directory or submit an appointment request. You remain in control of whether and when you seek professional support.' : 'Try another status filter, or request a new appointment when you feel ready.' }}</p>
            <div class="scr-empty-actions">
                <a href="{{ route('student.counselor-directory') }}" class="scr-btn scr-btn-secondary">Browse counselors</a>
                <a href="{{ route('student.request-appointment') }}" class="scr-btn scr-btn-primary">Request appointment</a>
            </div>
        </section>
    @else
        <section class="scr-list" aria-label="Counseling requests">
            @foreach($requests as $request)
                @php
                    $status = $request->display_status;
                    $counselorName = $request->counselor->name ?? 'Awaiting counselor assignment';
                    $initial = strtoupper(substr($counselorName, 0, 1));
                    $scheduledAt = $request->scheduled_at;
                @endphp
                <article class="scr-card status-{{ $status }}" id="appointment-{{ $request->id }}">
                    <div class="scr-card-head">
                        <div class="scr-counselor">
                            <span class="scr-avatar" aria-hidden="true">{{ $initial }}</span>
                            <div>
                                <small>{{ $request->category ? ucfirst($request->category) : 'Counseling request' }}</small>
                                <h2>{{ $counselorName }}</h2>
                                <p>Requested {{ optional($request->created_at)->timezone(config('appointments.timezone', config('app.timezone')))->format('M j, Y g:i A') }}</p>
                            </div>
                        </div>
                        <span class="scr-status {{ $status }}">{{ $request->display_status_label }}</span>
                    </div>

                    <div class="scr-meta">
                        <div class="scr-meta-item"><span>Scheduled</span><strong>{{ $scheduledAt ? $scheduledAt->timezone(config('appointments.timezone', config('app.timezone')))->format('M j, Y g:i A') : 'To be scheduled' }}</strong></div>
                        <div class="scr-meta-item"><span>Category</span><strong>{{ ucfirst($request->category ?? 'General support') }}</strong></div>
                        <div class="scr-meta-item"><span>Stored status</span><strong>{{ ucfirst(str_replace('_', ' ', $request->status)) }}</strong></div>
                    </div>

                    <div class="scr-note">
                        <div class="scr-note-label">Your request</div>
                        <p>{{ $request->description ?: ($request->reason ?: ($request->notes ?: 'No additional concern details were provided.')) }}</p>
                    </div>

                    <div class="scr-card-foot">
                        <p class="scr-helper">{{ $request->status_note }}</p>
                        <div class="scr-card-links">
                            @if($request->can_message)
                                <a class="scr-link" href="{{ route('student.counselor-chat') }}">Message counselor</a>
                            @elseif($status === 'needs_review')
                                <span class="scr-link muted">Messaging paused for review</span>
                            @elseif($status === 'completed')
                                <span class="scr-link muted">History is read-only</span>
                            @elseif($status === 'failed')
                                <a class="scr-link" href="{{ route('student.request-appointment') }}">Request another</a>
                            @elseif($status === 'cancelled')
                                <a class="scr-link" href="{{ route('student.counselor-directory') }}">Find another counselor</a>
                            @else
                                <span class="scr-link muted">Awaiting approval</span>
                            @endif

                            @if($request->can_call)
                                <span class="scr-link">Video call available now</span>
                            @elseif($status === 'confirmed')
                                <span class="scr-link muted">Video call opens during the session</span>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    <aside class="scr-info">
        <strong>Your privacy and choices</strong>
        <p>Appointment updates contain only scheduling information. AI conversations, crisis details, diagnoses, and private counseling records are not included. Messaging and video access remain limited to the authorized appointment window and outcome.</p>
    </aside>
</div>
@endsection