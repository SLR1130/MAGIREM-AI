@extends('layouts.app')

@section('title', 'Student Profile - Anonymous')

@section('content')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Inter:wght@400;500;600&display=swap" onload="this.onload=null;this.rel='stylesheet'">

<div class="starry-background">
    <div class="stars-layer stars-layer-1"></div>
    <div class="stars-layer stars-layer-2"></div>
    <div class="nebula-glow"></div>
</div>

<div class="profile-content">

    {{-- Back --}}
    <a href="{{ route('counselor.students') }}" class="back-link">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Students
    </a>

    {{-- Header Card --}}
    <div class="profile-header-card">
        <div class="profile-header-left">
            <div class="profile-big-avatar">S</div>
            <div>
                <h1 class="profile-name">Student #{{ $student->student_id ?? 'Unknown' }}</h1>
                <p class="profile-meta">Anonymous &bull; Privacy Protected</p>
                <p class="profile-email">🔒 Information Protected</p>
            </div>
        </div>
        <div class="profile-header-actions">
            <a href="{{ route('counselor.chat', $student->id) }}" class="action-btn-primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                Message Student
            </a>
            <a href="{{ route('counselor.appointments') }}" class="action-btn-secondary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Schedule Appointment
            </a>
        </div>
    </div>

    {{-- Risk Score + Emotion Summary Row --}}
    <div class="profile-stats-row">


        {{-- Emotion Breakdown --}}
        <div class="profile-emotion-card">
            <div class="risk-card-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                All-Time Emotion Breakdown
            </div>
            @php
                $emotionColors = [
                    'happy'=>'#10b981','joy'=>'#10b981','excited'=>'#10b981',
                    'neutral'=>'#3b82f6','calm'=>'#3b82f6',
                    'sad'=>'#8b5cf6','sadness'=>'#8b5cf6','depressed'=>'#ec4899',
                    'anxious'=>'#f59e0b','anxiety'=>'#f59e0b',
                    'angry'=>'#ef4444','anger'=>'#ef4444',
                ];
                $totalEntries = $emotionSummary->sum('cnt');
            @endphp
            @forelse($emotionSummary as $item)
            @php
                $pct = $totalEntries > 0 ? round(($item->cnt / $totalEntries) * 100) : 0;
                $eColor = $emotionColors[strtolower($item->detected_emotion)] ?? '#6b7280';
            @endphp
            <div class="emotion-row">
                <span class="emotion-name">{{ ucfirst($item->detected_emotion) }}</span>
                <div class="emotion-bar-track">
                    <div class="emotion-bar-fill" style="width: {{ $pct }}%; background: {{ $eColor }}"></div>
                </div>
                <span class="emotion-count" style="color: {{ $eColor }}">{{ $item->cnt }}</span>
            </div>
            @empty
            <p style="color:rgba(255,255,255,0.4);text-align:center;padding:1rem">No entries yet</p>
            @endforelse
            <p class="emotion-total">Total: {{ $totalEntries }} entries</p>
        </div>

        {{-- Daily Breakdown (last 7 days) --}}
        <div class="profile-daily-card">
            <div class="risk-card-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                7-Day Activity
            </div>
            @if(empty($risk['daily_breakdown']))
                <p style="color:rgba(255,255,255,0.4);text-align:center;padding:1rem">No activity in the last 7 days</p>
            @else
                @foreach($risk['daily_breakdown'] as $day => $dayData)
                @php
                    $dayScore = max(0, $dayData['score']);
                    $dayPct   = min(100, round(($dayScore / 100) * 100));
                    $dayColor = $dayPct >= 75 ? '#ef4444' : ($dayPct >= 55 ? '#f59e0b' : ($dayPct >= 30 ? '#3b82f6' : '#10b981'));
                    $topEmotions = array_count_values($dayData['emotions']);
                    arsort($topEmotions);
                    $topEmotion = array_key_first($topEmotions) ?? 'neutral';
                @endphp
                <div class="daily-row">
                    <span class="daily-date">{{ $day }}</span>
                    <div class="daily-bar-track">
                        <div class="daily-bar-fill" style="width: {{ $dayPct }}%; background: {{ $dayColor }}"></div>
                    </div>
                    <span class="daily-emotion" style="color: {{ $dayColor }}">{{ ucfirst($topEmotion) }}</span>
                    <span class="daily-count">{{ $dayData['count'] }}x</span>
                </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Journal Entries --}}
    <div class="profile-entries-card">
        <div class="entries-header">
            <h2 class="entries-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                Journal Entries
            </h2>
            <span class="entries-count">{{ $entries->count() }} total</span>
        </div>

        @forelse($entries as $entry)
        @php
            $entryColor = $emotionColors[strtolower($entry->detected_emotion ?? 'neutral')] ?? '#6b7280';
            $isCrisis   = in_array($entry->crisis_level, ['critical','severe','moderate']);
        @endphp
        <div class="entry-item {{ $isCrisis ? 'entry-crisis' : '' }}" style="border-left: 3px solid {{ $entryColor }}">
            <div class="entry-item-header">
                <span class="entry-emotion-badge" style="background: {{ $entryColor }}22; color: {{ $entryColor }}; border: 1px solid {{ $entryColor }}44">
                    {{ ucfirst($entry->detected_emotion ?? 'neutral') }}
                </span>
                @if($isCrisis)
                <span class="entry-crisis-badge">{{ strtoupper($entry->crisis_level) }}</span>
                @endif
                <span class="entry-date">{{ $entry->created_at->format('M d, Y h:i A') }}</span>
            </div>
            <p class="entry-text-content">{{ $entry->entry_text }}</p>
            @if($entry->ai_response)
            <div class="entry-ai-response">
                <span class="entry-ai-label">AI Response:</span>
                {{ Str::limit($entry->ai_response, 200) }}
            </div>
            @endif
        </div>
        @empty
        <div style="text-align:center;padding:3rem;color:rgba(255,255,255,0.4)">
            <svg style="width:2.5rem;height:2.5rem;margin:0 auto 0.75rem;display:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            <p>No journal entries yet</p>
        </div>
        @endforelse
    </div>

</div>

<style>
body{font-family:'Inter',sans-serif}
h1,h2,h3,h4{font-family:'Montserrat',sans-serif;font-weight:700}
.starry-background{position:fixed;top:0;left:0;width:100%;height:100%;z-index:-1;background:linear-gradient(180deg,#0f0b1a 0%,#1a0f2e 25%,#2d1b4e 50%,#1a0f2e 75%,#0f0b1a 100%);overflow:hidden}
.nebula-glow{position:absolute;width:100%;height:100%;background:radial-gradient(ellipse at 20% 30%,rgba(139,92,246,0.15) 0%,transparent 50%),radial-gradient(ellipse at 80% 70%,rgba(167,139,250,0.12) 0%,transparent 50%);animation:nebulaPulse 20s ease-in-out infinite}
@keyframes nebulaPulse{0%,100%{opacity:0.6}50%{opacity:0.9}}
.stars-layer{position:absolute;width:100%;height:100%;background-repeat:repeat;background-size:1000px 1000px}
.stars-layer-1{background-image:radial-gradient(2px 2px at 20% 30%,rgba(255,255,255,0.9),transparent),radial-gradient(1px 1px at 60% 70%,rgba(196,181,253,0.8),transparent),radial-gradient(1px 1px at 80% 10%,rgba(255,255,255,0.8),transparent);animation:starsFloat 60s linear infinite}
.stars-layer-2{background-image:radial-gradient(3px 3px at 40% 20%,rgba(255,255,255,0.95),transparent),radial-gradient(2px 2px at 80% 80%,rgba(196,181,253,0.85),transparent);animation:starsFloat 90s linear infinite reverse}
@keyframes starsFloat{0%{transform:translateY(0)}100%{transform:translateY(-1000px)}}

.profile-content{position:relative;z-index:1;padding:2rem;max-width:1300px;margin:0 auto}
.back-link{display:inline-flex;align-items:center;gap:0.5rem;color:rgba(196,181,253,0.8);text-decoration:none;font-size:0.875rem;margin-bottom:1.5rem;transition:color 0.2s}
.back-link:hover{color:#fff}
.back-link svg{width:16px;height:16px}

/* Header */
.profile-header-card{background:rgba(255,255,255,0.05);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:1.75rem;margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;gap:1.5rem;flex-wrap:wrap}
.profile-header-left{display:flex;align-items:center;gap:1.25rem}
.profile-big-avatar{width:4rem;height:4rem;border-radius:50%;background:linear-gradient(135deg,#8b5cf6,#ec4899);display:flex;align-items:center;justify-content:center;font-family:'Montserrat',sans-serif;font-weight:700;font-size:1.5rem;color:#fff;flex-shrink:0}
.profile-name{font-size:1.6rem;font-weight:700;color:#fff;margin:0 0 0.3rem 0;background:linear-gradient(135deg,#e9d5ff,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.profile-meta{font-size:0.85rem;color:rgba(255,255,255,0.6);margin:0 0 0.2rem 0}
.profile-email{font-size:0.8rem;color:rgba(196,181,253,0.6);margin:0}
.profile-header-actions{display:flex;gap:0.75rem;flex-wrap:wrap}
.action-btn-primary{display:flex;align-items:center;gap:0.5rem;padding:0.65rem 1.1rem;background:rgba(139,92,246,0.2);color:#c4b5fd;border:1px solid rgba(139,92,246,0.3);border-radius:10px;text-decoration:none;font-size:0.875rem;font-weight:600;transition:all 0.2s}
.action-btn-primary:hover{background:rgba(139,92,246,0.35)}
.action-btn-primary svg{width:16px;height:16px}
.action-btn-secondary{display:flex;align-items:center;gap:0.5rem;padding:0.65rem 1.1rem;background:rgba(59,130,246,0.15);color:#60a5fa;border:1px solid rgba(59,130,246,0.3);border-radius:10px;text-decoration:none;font-size:0.875rem;font-weight:600;transition:all 0.2s}
.action-btn-secondary:hover{background:rgba(59,130,246,0.3)}
.action-btn-secondary svg{width:16px;height:16px}

/* Stats Row */
.profile-stats-row{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:1.25rem;margin-bottom:1.5rem}
.profile-risk-card,.profile-emotion-card,.profile-daily-card{background:rgba(255,255,255,0.05);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.1);border-radius:14px;padding:1.25rem}
.risk-card-title{display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;font-weight:600;color:rgba(255,255,255,0.7);margin-bottom:1rem}
.risk-card-title svg{width:16px;height:16px;flex-shrink:0}
.risk-period{margin-left:auto;font-size:0.7rem;color:rgba(255,255,255,0.4);font-weight:400}
.risk-big-score{font-family:'Montserrat',sans-serif;font-weight:700;font-size:3rem;line-height:1;margin-bottom:0.5rem}
.risk-big-score span{font-size:1rem;opacity:0.6}
.risk-level-badge{display:inline-block;padding:0.25rem 0.75rem;border-radius:20px;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.75rem}
.risk-bar-track{position:relative;height:10px;background:rgba(255,255,255,0.08);border-radius:10px;overflow:visible}
.risk-bar-fill{height:100%;border-radius:10px;transition:width 0.8s ease}
.risk-marker{position:absolute;top:-3px;width:2px;height:16px;background:rgba(255,255,255,0.2);border-radius:1px}
.risk-bar-scale{display:flex;justify-content:space-between;font-size:0.65rem;color:rgba(255,255,255,0.3);margin-top:0.3rem}
.risk-description{font-size:0.8rem;color:rgba(255,255,255,0.65);margin:0.75rem 0 0.25rem;line-height:1.5}
.risk-entries{font-size:0.75rem;color:rgba(255,255,255,0.4);margin:0}
.risk-entries strong{color:rgba(255,255,255,0.7)}

/* Emotion breakdown */
.emotion-row{display:flex;align-items:center;gap:0.75rem;margin-bottom:0.6rem}
.emotion-name{font-size:0.8rem;color:rgba(255,255,255,0.7);width:70px;flex-shrink:0}
.emotion-bar-track{flex:1;height:8px;background:rgba(255,255,255,0.08);border-radius:6px;overflow:hidden}
.emotion-bar-fill{height:100%;border-radius:6px;transition:width 0.6s ease}
.emotion-count{font-size:0.8rem;font-weight:600;width:30px;text-align:right;flex-shrink:0}
.emotion-total{font-size:0.75rem;color:rgba(255,255,255,0.4);margin:0.75rem 0 0;text-align:right}

/* Daily breakdown */
.daily-row{display:flex;align-items:center;gap:0.6rem;margin-bottom:0.6rem}
.daily-date{font-size:0.75rem;color:rgba(255,255,255,0.5);width:50px;flex-shrink:0}
.daily-bar-track{flex:1;height:8px;background:rgba(255,255,255,0.08);border-radius:6px;overflow:hidden}
.daily-bar-fill{height:100%;border-radius:6px;transition:width 0.6s ease}
.daily-emotion{font-size:0.75rem;font-weight:600;width:60px;flex-shrink:0;text-align:right}
.daily-count{font-size:0.7rem;color:rgba(255,255,255,0.4);width:25px;flex-shrink:0;text-align:right}

/* Entries */
.profile-entries-card{background:rgba(255,255,255,0.05);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.1);border-radius:14px;padding:1.5rem}
.entries-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;padding-bottom:0.875rem;border-bottom:1px solid rgba(255,255,255,0.08)}
.entries-title{font-size:1.1rem;font-weight:700;color:#fff;margin:0;display:flex;align-items:center;gap:0.5rem}
.entries-title svg{width:1.2rem;height:1.2rem;color:#a78bfa}
.entries-count{font-size:0.8rem;color:rgba(255,255,255,0.5);background:rgba(255,255,255,0.06);padding:0.2rem 0.65rem;border-radius:20px}
.entry-item{background:rgba(255,255,255,0.03);border-radius:10px;padding:1rem 1.1rem;margin-bottom:0.75rem;border:1px solid rgba(255,255,255,0.06);transition:background 0.2s}
.entry-item:hover{background:rgba(255,255,255,0.06)}
.entry-crisis{background:rgba(239,68,68,0.06)}
.entry-item-header{display:flex;align-items:center;gap:0.6rem;margin-bottom:0.6rem;flex-wrap:wrap}
.entry-emotion-badge{padding:0.15rem 0.6rem;border-radius:20px;font-size:0.7rem;font-weight:700;text-transform:uppercase}
.entry-crisis-badge{background:rgba(239,68,68,0.2);color:#f87171;border:1px solid rgba(239,68,68,0.3);padding:0.15rem 0.6rem;border-radius:6px;font-size:0.7rem;font-weight:700;text-transform:uppercase}
.entry-date{margin-left:auto;font-size:0.75rem;color:rgba(255,255,255,0.4)}
.entry-text-content{color:rgba(255,255,255,0.8);font-size:0.875rem;margin:0 0 0.5rem;line-height:1.5}
.entry-ai-response{font-size:0.8rem;color:rgba(196,181,253,0.7);background:rgba(139,92,246,0.08);border-radius:6px;padding:0.5rem 0.75rem;border-left:2px solid rgba(139,92,246,0.3)}
.entry-ai-label{font-weight:600;color:#a78bfa;margin-right:0.4rem}

@media(max-width:900px){.profile-stats-row{grid-template-columns:1fr}.profile-header-card{flex-direction:column;align-items:flex-start}}
</style>

@endsection
