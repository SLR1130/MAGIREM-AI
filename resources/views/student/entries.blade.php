@extends('layouts.app')

@section('title', 'My Journal')

@section('content')
<!-- Animated Starry Background -->
<div class="starry-background">
    <div class="stars-layer stars-layer-1"></div>
    <div class="stars-layer stars-layer-2"></div>
    <div class="stars-layer stars-layer-3"></div>
    <div class="nebula-glow"></div>
</div>

<style>
    /* ===== ANIMATED STARRY BACKGROUND ===== */
    .starry-background {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        background: linear-gradient(180deg, 
            #0f0b1a 0%,
            #1a0f2e 25%,
            #2d1b4e 50%,
            #1a0f2e 75%,
            #0f0b1a 100%
        );
        overflow: hidden;
    }

    .nebula-glow {
        position: absolute;
        width: 100%;
        height: 100%;
        background: 
            radial-gradient(ellipse at 20% 30%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
            radial-gradient(ellipse at 80% 70%, rgba(167, 139, 250, 0.12) 0%, transparent 50%),
            radial-gradient(ellipse at 50% 50%, rgba(196, 181, 253, 0.08) 0%, transparent 60%);
        animation: nebulaPulse 20s ease-in-out infinite;
    }

    @keyframes nebulaPulse {
        0%, 100% { opacity: 0.6; transform: scale(1); }
        50% { opacity: 0.9; transform: scale(1.1); }
    }

    .stars-layer {
        position: absolute;
        width: 100%;
        height: 100%;
        background-repeat: repeat;
        background-size: 1000px 1000px;
    }

    .stars-layer-1 {
        background-image: 
            radial-gradient(2px 2px at 20% 30%, rgba(255, 255, 255, 0.9), transparent),
            radial-gradient(2px 2px at 60% 70%, rgba(196, 181, 253, 0.8), transparent),
            radial-gradient(1px 1px at 50% 50%, rgba(167, 139, 250, 0.7), transparent),
            radial-gradient(1px 1px at 80% 10%, rgba(255, 255, 255, 0.8), transparent),
            radial-gradient(2px 2px at 90% 60%, rgba(196, 181, 253, 0.9), transparent);
        animation: starsFloat 60s linear infinite;
    }

    .stars-layer-2 {
        background-image: 
            radial-gradient(3px 3px at 40% 20%, rgba(255, 255, 255, 0.95), transparent),
            radial-gradient(2px 2px at 80% 80%, rgba(196, 181, 253, 0.85), transparent),
            radial-gradient(2px 2px at 10% 40%, rgba(167, 139, 250, 0.8), transparent);
        animation: starsFloat 90s linear infinite reverse;
    }

    .stars-layer-3 {
        background-image: 
            radial-gradient(4px 4px at 50% 30%, rgba(255, 255, 255, 1), transparent),
            radial-gradient(3px 3px at 85% 40%, rgba(196, 181, 253, 0.95), transparent);
        animation: starsFloat 120s linear infinite, starsTwinkle 3s ease-in-out infinite;
    }

    @keyframes starsFloat {
        0% { transform: translateY(0) translateX(0); }
        100% { transform: translateY(-1000px) translateX(100px); }
    }

    @keyframes starsTwinkle {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .journal-header {
        background: linear-gradient(135deg, #8b5cf6 0%, #5b21b6 100%);
        color: white;
        padding: 2rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        box-shadow: 0 8px 32px rgba(139, 92, 246, 0.2);
    }

    .journal-header h1 {
        margin: 0;
        font-size: 2rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .journal-header p {
        margin: 0.5rem 0 0 0;
        opacity: 0.9;
        font-size: 1rem;
    }

    .journal-header svg {
        width: 32px;
        height: 32px;
    }

    .entries-container {
        display: grid;
        gap: 1.5rem;
    }

    .entry-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(139, 92, 246, 0.2);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border-left: 5px solid #8b5cf6;
        border: 1px solid rgba(255, 255, 255, 0.3);
        animation: slideIn 0.4s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .entry-card:hover {
        box-shadow: 0 16px 48px rgba(139, 92, 246, 0.25);
        transform: translateY(-6px);
    }

    .entry-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 1.5rem;
        background: linear-gradient(135deg, #f9f7ff 0%, #f3e8ff 100%);
        border-bottom: 1px solid #e9d5ff;
    }

    .entry-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 1;
    }

    .emotion-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
    }

    .emotion-badge svg {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
    }

    .emotional-score-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .emotional-score-badge.positive {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .emotional-score-badge.negative {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .emotional-score-badge.neutral {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        color: white;
    }

    .confidence-score {
        color: #6b7280;
        font-size: 0.85rem;
        margin-left: 0.5rem;
    }

    .entry-date {
        color: #8b5cf6;
        font-weight: 600;
        font-size: 0.9rem;
        text-align: right;
    }

    .entry-date-day {
        font-size: 1.5rem;
        display: block;
        font-weight: 700;
    }

    .entry-date-time {
        font-size: 0.75rem;
        opacity: 0.8;
    }

    .entry-content {
        padding: 1.5rem;
    }

    .entry-text {
        color: #374151;
        line-height: 1.8;
        font-size: 0.95rem;
        margin: 0;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .ai-response-section {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 2px solid #e9d5ff;
    }

    .ai-response-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
        color: #8b5cf6;
        font-weight: 700;
        font-size: 0.95rem;
    }

    .ai-response-header svg {
        width: 20px;
        height: 20px;
    }

    .ai-response-text {
        background: linear-gradient(135deg, #f9f7ff 0%, #f3e8ff 100%);
        padding: 1rem;
        border-radius: 8px;
        color: #5b21b6;
        line-height: 1.7;
        font-size: 0.95rem;
        border-left: 4px solid #8b5cf6;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        border: 2px dashed #ddd6fe;
        box-shadow: 0 8px 32px rgba(139, 92, 246, 0.15);
    }

    .empty-state-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 1.5rem;
        color: #c4b5fd;
    }

    .empty-state-title {
        color: #5b21b6;
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .empty-state-text {
        color: #8b5cf6;
        margin-bottom: 2rem;
        font-size: 1rem;
    }

    .btn-create {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
    }

    .btn-create:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
    }

    .btn-create svg {
        width: 20px;
        height: 20px;
    }

    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 3rem;
        gap: 2rem;
        flex-wrap: wrap;
    }

    .pagination-nav {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .pagination-nav.prev {
        justify-content: flex-start;
        flex: 1;
    }

    .pagination-nav.next {
        justify-content: flex-end;
        flex: 1;
    }

    .pagination-center {
        display: flex;
        justify-content: center;
        gap: 0.25rem;
        flex: 1;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.25rem;
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .pagination li {
        display: inline-block;
    }

    .pagination a,
    .pagination span {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        height: 40px;
        padding: 0;
        border-radius: 8px;
        color: #8b5cf6;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
        border: 2px solid #ddd6fe;
        font-size: 0.9rem;
    }

    .pagination a:hover {
        background: #f3e8ff;
        border-color: #8b5cf6;
        transform: translateY(-2px);
    }

    .pagination .active span {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
        border-color: #8b5cf6;
    }

    .pagination .disabled span {
        color: #d1d5db;
        border-color: #e5e7eb;
        cursor: not-allowed;
    }

    .pagination svg {
        width: 18px;
        height: 18px;
    }

    @media (max-width: 768px) {
        .pagination-wrapper {
            flex-direction: column;
            gap: 1.5rem;
        }

        .pagination-nav.prev,
        .pagination-nav.next {
            flex: none;
            width: 100%;
        }

        .pagination-center {
            flex: none;
            width: 100%;
        }
    }

    @media (max-width: 768px) {
        .journal-header h1 {
            font-size: 1.5rem;
        }

        .entry-header {
            flex-direction: column;
            gap: 1rem;
        }

        .entry-date {
            text-align: left;
        }

        .emotion-badge {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }
    }
</style>

<div class="journal-header">
    <h1>
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
        My Journal
    </h1>
    <p>Reflect on your emotional journey and track your growth over time</p>
</div>

@if($entries->hasPages())
    <div class="pagination-wrapper" style="margin-bottom: 2rem;">
        {{ $entries->render('vendor.pagination.custom') }}
    </div>
@endif

@if($entries->count() > 0)
    <div class="entries-container">
        @foreach($entries as $entry)
            <div class="entry-card">
                <div class="entry-header">
                    <div class="entry-meta">
                    <div class="emotion-badge">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            @switch(strtolower($entry->detected_emotion))
                                @case('happy')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 9h.01M15 9h.01"></path>
                                @break
                                @case('sad')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 9h.01M15 9h.01"></path>
                                @break
                                @case('anxious')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                @break
                                @case('angry')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m-6-4v10m12-10v10M7 8h10M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path>
                                @break
                                @case('calm')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 9h.01M15 9h.01"></path>
                                @break
                                @case('excited')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 9h.01M15 9h.01"></path>
                                @break
                                @case('stressed')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                @break
                                @default
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3a9 9 0 110 18 9 9 0 010-18zM12 7a2 2 0 100 4 2 2 0 000-4zm0 9a5 5 0 100-10 5 5 0 000 10z"></path>
                            @endswitch
                        </svg>
                        <span>{{ ucfirst($entry->detected_emotion) }}</span>
                        <span class="confidence-score" title="AI-estimated emotion label; not a clinical measurement.">(AI-estimated)</span>
                    </div>
                    
                    @if($entry->weighted_score !== null)
                        <div class="emotional-score-badge @if($entry->weighted_score > 0) positive @elseif($entry->weighted_score < 0) negative @else neutral @endif">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span>Score: {{ number_format((float) $entry->weighted_score, 1) }}</span>
                        </div>
                    @endif
                    </div>
                    <div class="entry-date">
                        <span class="entry-date-day">{{ $entry->created_at->format('d') }}</span>
                        <span class="entry-date-time">{{ $entry->created_at->format('M Y') }}</span>
                    </div>
                </div>

                <div class="entry-content">
                    <p class="entry-text">{{ $entry->entry_text }}</p>

                    @if($entry->ai_response)
                        <div class="ai-response-section">
                            <div class="ai-response-header">
                                <svg fill="currentColor" viewBox="0 0 24 24"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"></path></svg>
                                AI Support & Guidance
                            </div>
                            <div class="ai-response-text">{!! $entry->ai_response !!}</div>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($entries->hasPages())
        <div class="pagination-wrapper">
            {{ $entries->render('vendor.pagination.custom') }}
        </div>
    @endif
@else
    <div class="empty-state">
        <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
        <div class="empty-state-title">No Entries Yet</div>
        <p class="empty-state-text">Start your journaling journey by creating your first entry</p>
        <a href="{{ route('student.create-entry') }}" class="btn-create">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Create First Entry
        </a>
    </div>
@endif
@endsection
