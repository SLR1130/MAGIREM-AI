@extends('layouts.app')

@section('title', 'Appointments · MAGIREM AI')

@section('content')
<style>
.ca-page,.ca-page *,.ca-page *::before,.ca-page *::after{box-sizing:border-box}
.ca-page{--forest:#103F32;--forest2:#174F3F;--emerald:#236B55;--mint:#E6F1EB;--ivory:#F8FAF7;--gold:#F4C84E;--text:#263A33;--muted:#71837A;--line:rgba(16,63,50,.10);--danger:#B95E57;--danger-bg:#FFF2F0;--warn:#B98422;--warn-bg:#FFF8E8;--blue:#477A8C;--blue-bg:#EDF5F7;--ok:#2F8767;--ok-bg:#EDF8F3;width:100%;max-width:1460px;margin:0 auto;padding:12px 20px 36px;color:var(--text);font-family:Inter,"Plus Jakarta Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.ca-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:16px}.ca-eyebrow{display:inline-flex;align-items:center;gap:9px;margin-bottom:7px;color:var(--emerald);font-size:10px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}.ca-eyebrow::before{content:"";width:20px;height:2px;border-radius:999px;background:var(--gold)}
.ca-head h1{margin:0;color:var(--forest);font-size:clamp(30px,3vw,44px);line-height:1;letter-spacing:-.045em;font-weight:850}.ca-head p{margin:9px 0 0;color:var(--muted);font-size:11px;line-height:1.55}
.ca-back{min-height:40px;display:inline-flex;align-items:center;gap:7px;padding:0 12px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--forest)!important;text-decoration:none;font-size:9px;font-weight:800}.ca-back svg{width:14px;height:14px}
.ca-alert{display:flex;align-items:center;gap:8px;margin-bottom:12px;padding:11px 13px;border:1px solid rgba(47,135,103,.14);border-radius:11px;background:var(--ok-bg);color:var(--ok);font-size:9px;font-weight:700}.ca-alert svg{width:15px;height:15px}
.ca-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-bottom:13px}.ca-stat{padding:14px;border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:0 7px 22px rgba(16,63,50,.035)}
.ca-stat-icon{width:32px;height:32px;display:grid;place-items:center;border-radius:9px;background:var(--mint);color:var(--forest)}.ca-stat-icon svg{width:15px;height:15px}.ca-stat.warn .ca-stat-icon{background:var(--warn-bg);color:var(--warn)}.ca-stat.info .ca-stat-icon{background:var(--blue-bg);color:var(--blue)}.ca-stat.ok .ca-stat-icon{background:var(--ok-bg);color:var(--ok)}
.ca-stat strong{display:block;margin-top:9px;color:var(--forest);font-size:22px;line-height:1;font-weight:850}.ca-stat span{display:block;margin-top:4px;color:var(--muted);font-size:8px}
.ca-tabs{display:flex;gap:5px;margin-bottom:12px;padding:5px;border:1px solid var(--line);border-radius:12px;background:#fff}.ca-tab{flex:1;min-height:37px;display:flex;align-items:center;justify-content:center;gap:6px;border:0;border-radius:9px;background:transparent;color:var(--muted);font:inherit;font-size:8.5px;font-weight:800;cursor:pointer}.ca-tab:hover{background:var(--ivory);color:var(--forest)}.ca-tab.active{background:var(--mint);color:var(--forest)}
.ca-tab-badge{min-width:20px;height:20px;display:grid;place-items:center;padding:0 5px;border-radius:999px;background:#fff;color:var(--forest);font-size:7px;font-weight:850}.ca-content{display:none}.ca-content.active{display:block;animation:caFade .18s ease}@keyframes caFade{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:none}}
.ca-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.ca-card{position:relative;overflow:hidden;padding:13px;border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:0 7px 22px rgba(16,63,50,.035)}.ca-card.pending{border-top:3px solid var(--warn)}.ca-card.confirmed{border-top:3px solid var(--blue)}.ca-card.today{border-top-color:var(--ok)}
.ca-card-head{display:flex;align-items:center;gap:9px;margin-bottom:10px}.ca-avatar{width:36px;height:36px;flex:0 0 36px;display:grid;place-items:center;border-radius:10px;background:var(--mint);color:var(--forest);font-size:10px;font-weight:850}.ca-card-copy{min-width:0;flex:1}.ca-card-copy strong{display:block;overflow:hidden;color:var(--forest);font-size:9.5px;white-space:nowrap;text-overflow:ellipsis}.ca-card-copy span{display:block;margin-top:2px;color:var(--muted);font-size:7.5px}
.ca-status{min-height:23px;display:inline-flex;align-items:center;padding:0 8px;border-radius:999px;font-size:7px;font-weight:850;text-transform:uppercase}.ca-status.pending{background:var(--warn-bg);color:var(--warn)}.ca-status.confirmed{background:var(--blue-bg);color:var(--blue)}.ca-status.completed{background:var(--ok-bg);color:var(--ok)}.ca-status.cancelled{background:var(--danger-bg);color:var(--danger)}.ca-status.failed{background:var(--danger-bg);color:var(--danger)}.ca-status.review{background:var(--warn-bg);color:var(--warn)}
.ca-today{position:absolute;top:0;right:0;padding:4px 8px;border-radius:0 11px 0 9px;background:var(--ok);color:#fff;font-size:6.5px;font-weight:850;letter-spacing:.08em}
.ca-reason{margin-bottom:9px;padding:9px;border:1px solid rgba(185,94,87,.12);border-radius:10px;background:var(--danger-bg);color:#754844;font-size:8px;line-height:1.5}.ca-reason strong{color:var(--danger)}
.ca-detail{display:flex;align-items:center;gap:6px;margin-top:6px;color:var(--muted);font-size:8px}.ca-detail svg{width:12px;height:12px;flex:0 0 12px;color:var(--emerald)}
.ca-notes{margin:9px 0 0;padding:9px;border-radius:9px;background:var(--ivory);color:var(--muted);font-size:8px;line-height:1.45}.ca-ago{margin-top:7px;color:#95A39C;font-size:7px}
.ca-confirm-box{margin-top:11px;padding-top:11px;border-top:1px solid var(--line)}.ca-form-row{display:grid;grid-template-columns:1.2fr .8fr;gap:7px}.ca-field label{display:block;margin-bottom:5px;color:var(--forest);font-size:7.5px;font-weight:800}.ca-field input{width:100%;min-height:36px;padding:0 9px;border:1px solid var(--line);border-radius:8px;background:var(--ivory);color:var(--text);font:inherit;font-size:8px}.ca-field input:focus{outline:none;border-color:rgba(35,107,85,.35);background:#fff;box-shadow:0 0 0 3px rgba(35,107,85,.06)}
.ca-actions{display:flex;gap:6px;margin-top:8px}.ca-action{flex:1;min-height:34px;display:inline-flex;align-items:center;justify-content:center;gap:6px;border-radius:8px;font:inherit;font-size:7.8px;font-weight:800;cursor:pointer;text-decoration:none}.ca-action.confirm{border:1px solid var(--forest);background:var(--forest);color:#fff}.ca-action.complete{border:1px solid var(--ok);background:var(--ok);color:#fff}.ca-action.cancel{border:1px solid rgba(185,94,87,.16);background:var(--danger-bg);color:var(--danger)}.ca-action.message{flex:0 0 34px;border:1px solid var(--line);background:var(--ivory);color:var(--forest)}.ca-action svg{width:12px;height:12px}
.ca-datetime{display:flex;align-items:center;gap:9px;margin-bottom:9px;padding:10px;border:1px solid rgba(71,122,140,.14);border-radius:10px;background:var(--blue-bg)}.ca-datetime svg{width:16px;height:16px;color:var(--blue)}.ca-datetime strong{display:block;color:var(--forest);font-size:8.5px}.ca-datetime span{display:block;margin-top:2px;color:var(--blue);font-size:7.5px}
.ca-list{display:grid;gap:7px}.ca-list-item{display:grid;grid-template-columns:36px minmax(0,1fr) auto;align-items:center;gap:10px;padding:11px 12px;border:1px solid var(--line);border-radius:11px;background:#fff}.ca-list-copy strong{display:block;color:var(--forest);font-size:9px}.ca-list-copy span{display:block;margin-top:3px;color:var(--muted);font-size:7.5px}
.ca-empty{padding:68px 20px;border:1px solid var(--line);border-radius:15px;background:#fff;text-align:center;color:var(--muted);font-size:9px}.ca-empty svg{width:34px;height:34px;margin:0 auto 9px;color:var(--emerald)}
@media(max-width:1100px){.ca-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.ca-page{padding-inline:12px}.ca-head{align-items:flex-start;flex-direction:column}.ca-stats{grid-template-columns:repeat(2,1fr)}.ca-tabs{overflow-x:auto}.ca-tab{min-width:120px}.ca-grid{grid-template-columns:1fr}.ca-form-row{grid-template-columns:1fr}}
</style>

<div class="ca-page">
    <header class="ca-head">
        <div>
            <span class="ca-eyebrow">Schedule management</span>
            <h1>Appointments</h1>
            <p>{{ now()->format('l, F j, Y') }} · Review requests, confirm sessions, and keep counseling schedules easy to scan.</p>
        </div>
        <a href="{{ route('counselor.dashboard') }}" class="ca-back">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 19l-7-7 7-7"/></svg>
            Dashboard
        </a>
    </header>

    @if(session('success'))
        <div class="ca-alert"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>{{ session('success') }}</div>
    @endif

    <section class="ca-stats">
        <article class="ca-stat"><span class="ca-stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg></span><strong>{{ $todayCount }}</strong><span>Today</span></article>
        <article class="ca-stat info"><span class="ca-stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span><strong>{{ $upcomingCount }}</strong><span>Upcoming</span></article>
        <article class="ca-stat warn"><span class="ca-stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3m0 4h.01M10.3 4.4L2.9 17.2A2 2 0 004.6 20h14.8a2 2 0 001.7-2.8L13.7 4.4a2 2 0 00-3.4 0z"/></svg></span><strong>{{ $pending->count() }}</strong><span>Pending requests</span></article>
        <article class="ca-stat ok"><span class="ca-stat-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg></span><strong>{{ $completed->count() }}</strong><span>Completed</span></article>
    </section>

    <div class="ca-tabs">
        <button type="button" class="ca-tab active" onclick="switchTab('pending', this)">Pending @if($pending->count())<span class="ca-tab-badge">{{ $pending->count() }}</span>@endif</button>
        <button type="button" class="ca-tab" onclick="switchTab('confirmed', this)">Confirmed @if($confirmed->count())<span class="ca-tab-badge">{{ $confirmed->count() }}</span>@endif</button>
        <button type="button" class="ca-tab" onclick="switchTab('review', this)">Needs review @if($needsReview->count())<span class="ca-tab-badge">{{ $needsReview->count() }}</span>@endif</button>
        <button type="button" class="ca-tab" onclick="switchTab('failed', this)">Failed @if($failed->count())<span class="ca-tab-badge">{{ $failed->count() }}</span>@endif</button>
        <button type="button" class="ca-tab" onclick="switchTab('completed', this)">Completed</button>
        <button type="button" class="ca-tab" onclick="switchTab('cancelled', this)">Cancelled</button>
    </div>

    <section id="tab-pending" class="ca-content active">
        @if($pending->isEmpty())
            <div class="ca-empty"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg><div>No pending appointment requests.</div></div>
        @else
            <div class="ca-grid">
                @foreach($pending as $appt)
                    <article class="ca-card pending">
                        <div class="ca-card-head">
                            <span class="ca-avatar">S</span>
                            <span class="ca-card-copy"><strong>Student #{{ $appt->student->student_id ?? ('record ' . $appt->student_id) }}</strong><span>Anonymous student</span></span>
                            <span class="ca-status pending">Pending</span>
                        </div>
                        @if($appt->reason)<div class="ca-reason"><strong>Reason:</strong> {{ $appt->reason }}</div>@endif
                        @if($appt->category)<div class="ca-detail"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5l8 8-8 8-8-8V7a4 4 0 014-4z"/></svg>{{ ucfirst($appt->category) }}</div>@endif
                        @if($appt->preferred_date)<div class="ca-detail"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg>Preferred {{ \Carbon\Carbon::parse($appt->preferred_date)->format('M d, Y') }}</div>@endif
                        @if($appt->time_slot)<div class="ca-detail"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ $appt->time_slot }}</div>@endif
                        @if($appt->notes)<div class="ca-notes">{{ \Illuminate\Support\Str::limit($appt->notes, 120) }}</div>@endif
                        <div class="ca-ago">Requested {{ $appt->created_at->diffForHumans() }}</div>

                        <div class="ca-confirm-box">
                            <form action="{{ route('counselor.appointments.confirm', $appt->id) }}" method="POST">
                                @csrf
                                <div class="ca-form-row">
                                    <div class="ca-field">
                                        <label>Set date & time</label>
                                        <input type="datetime-local" name="appointment_date" required min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ $appt->preferred_date ? \Carbon\Carbon::parse($appt->preferred_date)->format('Y-m-d\TH:i') : '' }}">
                                    </div>
                                    <div class="ca-field">
                                        <label>Notes</label>
                                        <input type="text" name="counselor_notes" placeholder="Optional note">
                                    </div>
                                </div>
                                <div class="ca-actions"><button type="submit" class="ca-action confirm">Confirm appointment</button></div>
                            </form>
                            <form action="{{ route('counselor.appointments.cancel', $appt->id) }}" method="POST">
                                @csrf
                                <div class="ca-actions" style="margin-top:6px"><button type="submit" class="ca-action cancel" onclick="return confirm('Decline this request?')">Decline request</button></div>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section id="tab-confirmed" class="ca-content">
        @if($confirmed->isEmpty())
            <div class="ca-empty"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M5 13l4 4L19 7"/></svg><div>No confirmed appointments yet.</div></div>
        @else
            <div class="ca-grid">
                @foreach($confirmed as $appt)
                    @php $apptDate = \Carbon\Carbon::parse($appt->appointment_date); @endphp
                    <article class="ca-card confirmed {{ $apptDate->isToday() ? 'today' : '' }}">
                        @if($apptDate->isToday())<span class="ca-today">TODAY</span>@endif
                        <div class="ca-card-head">
                            <span class="ca-avatar">S</span>
                            <span class="ca-card-copy"><strong>Student #{{ $appt->student->student_id ?? ('record ' . $appt->student_id) }}</strong><span>Anonymous student</span></span>
                            <span class="ca-status confirmed">Confirmed</span>
                        </div>
                        <div class="ca-datetime"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg><div><strong>{{ $apptDate->format('l, F j, Y') }}</strong><span>{{ $apptDate->format('g:i A') }}</span></div></div>
                        @if($appt->category)<div class="ca-detail">{{ ucfirst($appt->category) }}</div>@endif
                        @if($appt->notes)<div class="ca-notes">{{ \Illuminate\Support\Str::limit($appt->notes, 100) }}</div>@endif
                        @if($appt->counselor_notes)<div class="ca-notes">{{ $appt->counselor_notes }}</div>@endif

                        <div class="ca-actions">
                            <form action="{{ route('counselor.appointments.complete', $appt->id) }}" method="POST" style="flex:1">
                                @csrf
                                <select name="attendance_evidence" required aria-label="Attendance evidence" style="width:100%;min-height:32px;margin-bottom:6px;border:1px solid var(--line);border-radius:8px;background:var(--ivory);font:inherit;font-size:8px">
                                    <option value="">Attendance evidence…</option>
                                    <option value="online_call">Linked completed video call</option>
                                    <option value="manual_verification">Manual verification</option>
                                    <option value="external_verification">External verification</option>
                                </select>
                                <input type="text" name="counselor_notes" placeholder="Verification note (required unless linked call)" style="width:100%;min-height:32px;padding:0 8px;border:1px solid var(--line);border-radius:8px;background:var(--ivory);font:inherit;font-size:8px">
                                <button type="submit" class="ca-action complete" style="width:100%;margin-top:6px">Mark completed (verified)</button>
                            </form>
                            <a href="{{ route('counselor.chat', $appt->student_id) }}" class="ca-action message" aria-label="Message student">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4-4 7-9 7-1.4 0-2.8-.25-4-.72L3 19l1.3-3.4A6.3 6.3 0 013 12c0-4 4-7 9-7s9 3 9 7z"/></svg>
                            </a>
                            <form action="{{ route('counselor.appointments.cancel', $appt->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="ca-action cancel" onclick="return confirm('Cancel this appointment?')">Cancel</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section id="tab-review" class="ca-content">
        @if($needsReview->isEmpty())
            <div class="ca-empty"><div>No overdue appointments need review.</div></div>
        @else
            <div class="ca-grid">
                @foreach($needsReview as $appt)
                    <article class="ca-card pending">
                        <div class="ca-card-head"><span class="ca-avatar">S</span><span class="ca-card-copy"><strong>Student #{{ $appt->student->student_id ?? ('record ' . $appt->student_id) }}</strong><span>Scheduled {{ optional($appt->appointment_date)->format('M d, Y g:i A') }}</span></span><span class="ca-status review">Needs review</span></div>
                        <div class="ca-notes">No attendance conclusion has been inferred. Record only a verified outcome after checking the appointment.</div>
                        <form action="{{ route('counselor.appointments.outcome', $appt->id) }}" method="POST" class="ca-confirm-box">
                            @csrf
                            <select name="outcome" required style="width:100%;min-height:34px;border:1px solid var(--line);border-radius:8px;background:var(--ivory);font:inherit;font-size:8px"><option value="needs_review">Keep as needs review</option><option value="student_no_show">Student no-show</option><option value="counselor_no_show">Counselor no-show</option><option value="technical_problem">Technical problem</option><option value="other">Other</option></select>
                            <input type="text" name="reason" required placeholder="Verified reason" style="width:100%;min-height:34px;margin-top:6px;padding:0 8px;border:1px solid var(--line);border-radius:8px;background:var(--ivory);font:inherit;font-size:8px">
                            <button type="submit" class="ca-action cancel" style="width:100%;margin-top:7px">Record outcome</button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section id="tab-failed" class="ca-content">
        @if($failed->isEmpty())
            <div class="ca-empty"><div>No failed or unresolved appointments.</div></div>
        @else
            <div class="ca-list">
                @foreach($failed as $appt)
                    <article class="ca-list-item"><span class="ca-avatar">S</span><span class="ca-list-copy"><strong>Student #{{ $appt->student->student_id ?? ('record ' . $appt->student_id) }}</strong><span>Scheduled {{ optional($appt->appointment_date)->format('M d, Y g:i A') }} · {{ \Illuminate\Support\Str::headline($appt->session_outcome) }} @if($appt->outcome_reason) · {{ $appt->outcome_reason }} @endif</span></span><span class="ca-status failed">{{ \Illuminate\Support\Str::headline($appt->session_outcome) }}</span></article>
                @endforeach
            </div>
        @endif
    </section>
    <section id="tab-completed" class="ca-content">
        @if($completed->isEmpty())
            <div class="ca-empty"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M5 13l4 4L19 7"/></svg><div>No completed appointments yet.</div></div>
        @else
            <div class="ca-list">
                @foreach($completed as $appt)
                    @php $apptDate = \Carbon\Carbon::parse($appt->appointment_date); @endphp
                    <article class="ca-list-item">
                        <span class="ca-avatar">S</span>
                        <span class="ca-list-copy"><strong>Student #{{ $appt->student->student_id ?? ('record ' . $appt->student_id) }}</strong><span>{{ $apptDate->format('M d, Y g:i A') }} · {{ ucfirst($appt->category ?? 'General') }} @if($appt->counselor_notes) · {{ $appt->counselor_notes }} @endif</span></span>
                        <span class="ca-status completed">Completed</span>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section id="tab-cancelled" class="ca-content">
        @if($cancelled->isEmpty())
            <div class="ca-empty"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M6 18L18 6M6 6l12 12"/></svg><div>No cancelled appointments.</div></div>
        @else
            <div class="ca-list">
                @foreach($cancelled as $appt)
                    <article class="ca-list-item">
                        <span class="ca-avatar">S</span>
                        <span class="ca-list-copy"><strong>Student #{{ $appt->student->student_id ?? ('record ' . $appt->student_id) }}</strong><span>{{ $appt->appointment_date ? \Carbon\Carbon::parse($appt->appointment_date)->format('M d, Y') : 'Date not set' }} · {{ ucfirst($appt->category ?? 'General') }}</span></span>
                        <span class="ca-status cancelled">Cancelled</span>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.ca-content').forEach(function (panel) { panel.classList.remove('active'); });
    document.querySelectorAll('.ca-tab').forEach(function (tab) { tab.classList.remove('active'); });
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>
@endsection
