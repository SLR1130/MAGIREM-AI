@extends('layouts.app')

@section('title', 'Messages · MAGIREM AI')

@section('content')
<style>
.cm-page,.cm-page *,.cm-page *::before,.cm-page *::after{box-sizing:border-box}
.cm-page{--forest:#103F32;--emerald:#236B55;--mint:#E6F1EB;--ivory:#F8FAF7;--gold:#F4C84E;--text:#263A33;--muted:#71837A;--line:rgba(16,63,50,.10);--danger:#B95E57;width:100%;max-width:1460px;margin:0 auto;padding:12px 20px 36px;color:var(--text);font-family:Inter,"Plus Jakarta Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.cm-head{margin-bottom:16px}.cm-eyebrow{display:inline-flex;align-items:center;gap:9px;margin-bottom:7px;color:var(--emerald);font-size:10px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}.cm-eyebrow::before{content:"";width:20px;height:2px;border-radius:999px;background:var(--gold)}
.cm-head h1{margin:0;color:var(--forest);font-size:clamp(30px,3vw,44px);line-height:1;letter-spacing:-.045em;font-weight:850}.cm-head p{margin:9px 0 0;color:var(--muted);font-size:11px;line-height:1.55}
.cm-card{overflow:hidden;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:0 14px 38px rgba(16,63,50,.055)}
.cm-card-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid var(--line);background:radial-gradient(circle at 100% 0%,rgba(230,241,235,.65),transparent 18rem),#fff}.cm-card-head strong{color:var(--forest);font-size:11px}.cm-card-head span{color:var(--muted);font-size:8px}
.cm-list{padding:8px}.cm-link{display:block;color:inherit!important;text-decoration:none}.cm-item{display:grid;grid-template-columns:44px minmax(0,1fr) auto;align-items:center;gap:11px;padding:11px 12px;border:1px solid transparent;border-radius:12px;transition:.16s ease}.cm-link+.cm-link{margin-top:4px}.cm-item:hover{background:var(--ivory);border-color:var(--line);transform:translateX(2px)}
.cm-avatar{width:44px;height:44px;display:grid;place-items:center;border-radius:12px;background:var(--mint);color:var(--forest);font-size:11px;font-weight:850}.cm-content{min-width:0}.cm-topline{display:flex;align-items:center;gap:7px;min-width:0}.cm-name{overflow:hidden;color:var(--forest);font-size:10px;font-weight:820;white-space:nowrap;text-overflow:ellipsis}.cm-new{display:inline-flex;align-items:center;min-height:21px;padding:0 7px;border-radius:999px;background:#FFF2F0;color:var(--danger);font-size:7px;font-weight:850;white-space:nowrap}
.cm-preview{margin:4px 0 0;overflow:hidden;color:var(--muted);font-size:8.5px;white-space:nowrap;text-overflow:ellipsis}.cm-time{color:#86958E;font-size:7.5px;white-space:nowrap}
.cm-empty{padding:74px 20px;text-align:center}.cm-empty-icon{width:52px;height:52px;margin:0 auto 12px;display:grid;place-items:center;border-radius:15px;background:var(--mint);color:var(--forest)}.cm-empty-icon svg{width:23px;height:23px}.cm-empty strong{display:block;color:var(--forest);font-size:12px}.cm-empty p{margin:6px 0 0;color:var(--muted);font-size:9px}
@media(max-width:650px){.cm-page{padding-inline:12px}.cm-item{grid-template-columns:40px minmax(0,1fr)}.cm-time{grid-column:2;margin-top:-4px}.cm-avatar{width:40px;height:40px}}
</style>

<div class="cm-page">
    <header class="cm-head">
        <span class="cm-eyebrow">Private communication</span>
        <h1>Messages</h1>
        <p>Continue confidential conversations with students and identify unread threads without visual clutter.</p>
    </header>

    <section class="cm-card">
        <div class="cm-card-head">
            <strong>Conversations</strong>
            <span>{{ $conversations->count() }} active {{ $conversations->count() === 1 ? 'thread' : 'threads' }}</span>
        </div>

        @if($conversations->count() > 0)
            <div class="cm-list">
                @foreach($conversations as $studentId => $messages)
                    @php
                        $student = $messages->first()->student;
                        $lastMessage = $messages->last();
                        $unreadCount = $messages->where('is_read', false)->where('counselor_id', auth()->id())->count();
                    @endphp
                    <a href="{{ route('counselor.chat', $studentId) }}" class="cm-link">
                        <article class="cm-item">
                            <span class="cm-avatar">S</span>
                            <div class="cm-content">
                                <div class="cm-topline">
                                    <span class="cm-name">Student #{{ $student->student_id ?? 'Unknown' }}</span>
                                    @if($unreadCount > 0)<span class="cm-new">{{ $unreadCount }} new</span>@endif
                                </div>
                                <p class="cm-preview">{{ \Illuminate\Support\Str::limit($lastMessage->message, 120) }}</p>
                            </div>
                            <time class="cm-time">{{ $lastMessage->created_at->diffForHumans() }}</time>
                        </article>
                    </a>
                @endforeach
            </div>
        @else
            <div class="cm-empty">
                <span class="cm-empty-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4-4 7-9 7-1.4 0-2.8-.25-4-.72L3 19l1.3-3.4A6.3 6.3 0 013 12c0-4 4-7 9-7s9 3 9 7z"/></svg></span>
                <strong>No messages yet</strong>
                <p>Student conversations will appear here when a message thread exists.</p>
            </div>
        @endif
    </section>
</div>
@endsection
