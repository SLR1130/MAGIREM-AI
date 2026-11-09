@extends('layouts.app')

@section('title', 'Students · MAGIREM AI')

@section('content')
<style>
.cstudents,
.cstudents *,
.cstudents *::before,
.cstudents *::after { box-sizing:border-box; }

.cstudents {
    --forest:#103F32;
    --forest-2:#174F3F;
    --emerald:#236B55;
    --mint:#E6F1EB;
    --mint-2:#D8EBE2;
    --ivory:#F8FAF7;
    --gold:#F4C84E;
    --text:#263A33;
    --muted:#71837A;
    --line:rgba(16,63,50,.10);

    width:100%;
    max-width:1480px;
    margin:0 auto;
    padding:12px 20px 34px;
    color:var(--text);
    font-family:Inter,"Plus Jakarta Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}

.cstudents a { text-decoration:none; }

.cstudents-head {
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:22px;
    margin-bottom:16px;
}

.cstudents-eyebrow {
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

.cstudents-eyebrow::before {
    content:"";
    width:20px;
    height:2px;
    border-radius:999px;
    background:var(--gold);
}

.cstudents-head h1 {
    margin:0;
    color:var(--forest);
    font-size:clamp(31px,3vw,46px);
    line-height:1;
    letter-spacing:-.045em;
    font-weight:850;
}

.cstudents-head p {
    max-width:720px;
    margin:9px 0 0;
    color:var(--muted);
    font-size:11px;
    line-height:1.6;
}

.cstudents-total {
    min-width:120px;
    padding:12px 14px;
    border:1px solid var(--line);
    border-radius:12px;
    background:#fff;
}

.cstudents-total strong {
    display:block;
    color:var(--forest);
    font-size:23px;
    line-height:1;
    letter-spacing:-.03em;
}

.cstudents-total span {
    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:7.5px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.06em;
}

.cstudents-intro {
    display:grid;
    grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr);
    gap:12px;
    margin-bottom:12px;
}

.cstudents-guide,
.cstudents-quick {
    border:1px solid var(--line);
    border-radius:16px;
    background:#fff;
    overflow:hidden;
}

.cstudents-guide {
    padding:18px;
    background:
        radial-gradient(circle at 100% 0%,rgba(216,235,226,.72),transparent 18rem),
        #fff;
}

.cstudents-guide small {
    color:var(--emerald);
    font-size:8px;
    font-weight:850;
    letter-spacing:.09em;
    text-transform:uppercase;
}

.cstudents-guide h2 {
    margin:7px 0 0;
    color:var(--forest);
    font-size:18px;
    line-height:1.2;
    letter-spacing:-.02em;
}

.cstudents-guide p {
    max-width:700px;
    margin:7px 0 0;
    color:var(--muted);
    font-size:8.8px;
    line-height:1.55;
}

.cstudents-quick {
    padding:15px;
}

.cstudents-quick strong {
    display:block;
    color:var(--forest);
    font-size:9.5px;
}

.cstudents-quick p {
    margin:5px 0 0;
    color:var(--muted);
    font-size:7.8px;
    line-height:1.5;
}

.cstudents-quick-actions {
    display:flex;
    gap:7px;
    margin-top:11px;
}

.cstudents-quick-actions a {
    flex:1;
    min-height:34px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--line);
    border-radius:9px;
    background:var(--ivory);
    color:var(--forest)!important;
    font-size:7.5px;
    font-weight:800;
}

.cstudents-directory {
    border:1px solid var(--line);
    border-radius:17px;
    background:#fff;
    box-shadow:0 12px 34px rgba(16,63,50,.045);
    overflow:hidden;
}

.cstudents-directory-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:13px 15px;
    border-bottom:1px solid var(--line);
}

.cstudents-directory-head strong {
    color:var(--forest);
    font-size:10px;
}

.cstudents-directory-head span {
    color:var(--muted);
    font-size:7.5px;
}

.cstudents-grid {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:9px;
    padding:10px;
}

.cstudent-card {
    min-width:0;
    padding:12px;
    border:1px solid var(--line);
    border-radius:12px;
    background:#fff;
    transition:.16s ease;
}

.cstudent-card:hover {
    transform:translateY(-2px);
    background:var(--ivory);
    box-shadow:0 8px 20px rgba(16,63,50,.05);
}

.cstudent-top {
    display:flex;
    align-items:center;
    gap:9px;
}

.cstudent-avatar {
    width:39px;
    height:39px;
    flex:0 0 39px;
    display:grid;
    place-items:center;
    border-radius:11px;
    background:var(--mint);
    color:var(--forest);
    font-size:10px;
    font-weight:850;
}

.cstudent-copy {
    min-width:0;
    flex:1;
}

.cstudent-copy strong {
    display:block;
    overflow:hidden;
    color:var(--forest);
    font-size:9.5px;
    font-weight:820;
    white-space:nowrap;
    text-overflow:ellipsis;
}

.cstudent-copy span {
    display:block;
    margin-top:2px;
    color:var(--muted);
    font-size:7.3px;
}

.cstudent-private {
    min-height:22px;
    display:inline-flex;
    align-items:center;
    padding:0 7px;
    border-radius:999px;
    background:#F1F6F3;
    color:var(--emerald);
    font-size:6.5px;
    font-weight:850;
}

.cstudent-divider {
    height:1px;
    margin:11px 0;
    background:var(--line);
}

.cstudent-note {
    min-height:38px;
    color:var(--muted);
    font-size:7.7px;
    line-height:1.5;
}

.cstudent-actions {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:6px;
    margin-top:10px;
}

.cstudent-action {
    min-height:32px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    border:1px solid var(--line);
    border-radius:8px;
    background:var(--ivory);
    color:var(--forest)!important;
    font-size:7px;
    font-weight:800;
}

.cstudent-action.primary {
    border-color:var(--forest);
    background:var(--forest);
    color:#fff!important;
}

.cstudent-action svg {
    width:12px;
    height:12px;
}

.cstudents-empty {
    padding:68px 20px;
    text-align:center;
}

.cstudents-empty-icon {
    width:50px;
    height:50px;
    margin:0 auto 12px;
    display:grid;
    place-items:center;
    border-radius:14px;
    background:var(--mint);
    color:var(--forest);
}

.cstudents-empty-icon svg {
    width:22px;
    height:22px;
}

.cstudents-empty strong {
    display:block;
    color:var(--forest);
    font-size:11px;
}

.cstudents-empty p {
    margin:6px 0 0;
    color:var(--muted);
    font-size:8px;
}

.cstudents-pagination {
    padding:12px 15px;
    border-top:1px solid var(--line);
    background:var(--ivory);
}

@media(max-width:1050px) {
    .cstudents-intro { grid-template-columns:1fr; }
    .cstudents-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
}

@media(max-width:700px) {
    .cstudents { padding-inline:12px; }
    .cstudents-head { align-items:flex-start; flex-direction:column; }
    .cstudents-grid { grid-template-columns:1fr; }
}
</style>

<div class="cstudents">
    <header class="cstudents-head">
        <div>
            <span class="cstudents-eyebrow">Student care</span>
            <h1>Students</h1>
            <p>This page is now a care directory rather than another alert list. Open a student record for longitudinal context, or move directly into a private conversation.</p>
        </div>

        <div class="cstudents-total">
            <strong>{{ $students->total() }}</strong>
            <span>student profiles</span>
        </div>
    </header>

    <section class="cstudents-intro">
        <div class="cstudents-guide">
            <small>Care directory</small>
            <h2>A student profile is the longitudinal view. Alerts are only signals.</h2>
            <p>Use this directory when you need the student’s broader counseling context rather than a single crisis event. The page deliberately avoids repeating the dashboard’s alert-card design.</p>
        </div>

        <aside class="cstudents-quick">
            <strong>Work from context</strong>
            <p>Open a profile to review available student information, or message the student directly when follow-up is the next step.</p>
            <div class="cstudents-quick-actions">
                <a href="{{ route('counselor.dashboard') }}">Dashboard</a>
                <a href="{{ route('counselor.messages') }}">Messages</a>
            </div>
        </aside>
    </section>

    <section class="cstudents-directory">
        <div class="cstudents-directory-head">
            <strong>Student directory</strong>
            <span>{{ $students->count() }} shown on this page</span>
        </div>

        @if($students->count() > 0)
            <div class="cstudents-grid">
                @foreach($students as $student)
                    <article class="cstudent-card">
                        <div class="cstudent-top">
                            <span class="cstudent-avatar">S</span>

                            <span class="cstudent-copy">
                                <strong>Student #{{ $student->student_id ?? 'Unknown' }}</strong>
                                <span>University student record</span>
                            </span>

                            <span class="cstudent-private">Private</span>
                        </div>

                        <div class="cstudent-divider"></div>

                        <div class="cstudent-note">
                            Open the student profile for the detailed wellness and counseling context already available in the system.
                        </div>

                        <div class="cstudent-actions">
                            <a href="{{ route('counselor.student-profile', $student->id) }}" class="cstudent-action primary">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12C3.8 8 7.5 5 12 5s8.2 3 9.5 7c-1.3 4-5 7-9.5 7S3.8 16 2.5 12z"/></svg>
                                Open profile
                            </a>

                            <a href="{{ route('counselor.chat', $student->id) }}" class="cstudent-action">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4-4 7-9 7-1.4 0-2.8-.25-4-.72L3 19l1.3-3.4A6.3 6.3 0 013 12c0-4 4-7 9-7s9 3 9 7z"/></svg>
                                Message
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="cstudents-pagination">
                {{ $students->links() }}
            </div>
        @else
            <div class="cstudents-empty">
                <span class="cstudents-empty-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8"/></svg>
                </span>
                <strong>No students found</strong>
                <p>Student profiles will appear here when they are available to your counselor account.</p>
            </div>
        @endif
    </section>
</div>
@endsection
