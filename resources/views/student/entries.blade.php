@extends('layouts.app')

@section('title', 'My Entries')

@section('content')
<div class="entries-page">
<style>
    .entries-page{box-sizing:border-box;width:min(100% - 32px, 1380px);margin:0 auto;padding:20px 0 44px;color:#193f35}
    .entries-page *, .entries-page *::before, .entries-page *::after{box-sizing:border-box}

    .journal-header {
        background: linear-gradient(135deg, #175945 0%, #103f32 100%);
        color: white;
        padding: 17px 22px;
        border-radius: 16px;
        margin-bottom: 14px;
        box-shadow: 0 8px 28px rgba(16, 63, 50, 0.12);
    }

    .journal-header h1 {
        margin: 0;
        font-size: 1.65rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.7rem;
    }

    .journal-header p {
        margin: 0.25rem 0 0;
        opacity: 0.9;
        font-size: 0.9rem;
    }

    .journal-header svg {
        width: 25px;
        height: 25px;
    }

    .entries-container {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: start;
        gap: 12px;
    }

    .entry-card {
        min-width: 0;
        border: 1px solid #dce8df;
        border-left: 3px solid #39735e;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 5px 16px rgba(16, 63, 50, 0.055);
        transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
    }

    @media (hover: hover) and (pointer: fine) {
        .entry-card:hover {
            box-shadow: 0 10px 24px rgba(16, 63, 50, 0.11);
            transform: translateY(-2px);
        }
    }

    .entry-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 11px 13px;
        background: linear-gradient(135deg, #fbfcf9 0%, #eaf3ec 100%);
        border-bottom: 1px solid #dcece0;
    }

    .entry-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1 1 auto;
        min-width: 0;
        flex-wrap: wrap;
    }

    .emotion-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, #28745c, #175945);
        color: white;
        padding: 5px 8px;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.76rem;
        white-space: nowrap;
        box-shadow: 0 2px 7px rgba(16, 63, 50, 0.14);
    }

    .emotion-badge svg {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }

    .emotional-score-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 8px;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.72rem;
        white-space: nowrap;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
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
        color: rgba(255,255,255,.78);
        font-size: 0.68rem;
        margin-left: 2px;
        white-space: nowrap;
    }

    .entry-date {
        color: #28745c;
        font-weight: 600;
        font-size: 0.72rem;
        text-align: right;
        line-height: 1.25;
        flex: 0 0 auto;
    }

    .entry-date-day {
        font-size: 1rem;
        display: block;
        font-weight: 700;
    }

    .entry-date-time {
        font-size: 0.75rem;
        opacity: 0.8;
    }

    .entry-content {
        padding: 11px 13px 13px;
    }

    .entry-text {
        color: #374151;
        line-height: 1.55;
        font-size: 0.85rem;
        margin: 0;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .ai-response-section {
        margin-top: 9px;
        padding-top: 9px;
        border-top: 1px solid #dcece0;
    }

    .ai-response-header {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0;
        color: #28745c;
        font-weight: 700;
        font-size: 0.78rem;
        cursor: pointer;
        list-style: none;
        min-height: 38px;
    }

    .ai-response-header:focus-visible {
        outline: 3px solid #d9b653;
        outline-offset: 3px;
        border-radius: 5px;
    }

    .ai-response-header::-webkit-details-marker {
        display: none;
    }

    .ai-response-section .ai-response-header::after {
        content: 'Show';
        margin-left: auto;
        color: #6a8378;
        font-size: 0.68rem;
        font-weight: 700;
    }

    .ai-response-section[open] .ai-response-header::after {
        content: 'Hide';
    }

    .ai-response-header svg {
        width: 16px;
        height: 16px;
    }

    .ai-response-text {
        background: linear-gradient(135deg, #fbfcf9 0%, #eaf3ec 100%);
        margin-top: 6px;
        padding: 10px 12px;
        border-radius: 8px;
        color: #175945;
        line-height: 1.55;
        font-size: 0.82rem;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        border-left: 4px solid #39735e;
    }

    .empty-state {
        text-align: center;
        padding: 2rem 1.5rem;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 12px;
        border: 1px dashed #b9d3c1;
        box-shadow: 0 8px 28px rgba(16, 63, 50, 0.08);
    }

    .empty-state-icon {
        width: 50px;
        height: 50px;
        margin: 0 auto 1rem;
        color: #7fa18f;
    }

    .empty-state-title {
        color: #103f32;
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .empty-state-text {
        color: #6a8378;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }

    .btn-create {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: linear-gradient(135deg, #175945, #103f32);
        color: white;
        min-height: 42px;
        padding: 0 15px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(16, 63, 50, 0.18);
    }

    .btn-create:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(16, 63, 50, 0.24);
    }

    .btn-create svg {
        width: 20px;
        height: 20px;
    }

    .pagination-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 1.5rem 0;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .pagination-nav {
        display: flex;
        align-items: center;
        flex: 0 0 auto;
    }

    .pagination-nav.prev {
        justify-content: center;
    }

    .pagination-nav.next {
        justify-content: center;
    }

    .pagination-center {
        display: flex;
        justify-content: center;
        gap: 0.25rem;
        flex: 0 1 auto;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.35rem;
        list-style: none;
        padding: 0;
        margin: 0;
        flex-wrap: wrap;
    }

    .pagination li {
        display: block;
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
        color: #28745c;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
        border: 1px solid #d4e4d8;
        font-size: 0.9rem;
    }

    .pagination a:hover {
        background: #eaf3ec;
        border-color: #72a78b;
        transform: translateY(-2px);
    }

    .pagination .active span {
        background: linear-gradient(135deg, #175945, #103f32);
        color: white;
        border-color: #175945;
    }

    .pagination .disabled span {
        color: #d1d5db;
        border-color: #e5e7eb;
        cursor: not-allowed;
    }

    .pagination-control {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border: 1px solid #d4e4d8;
        border-radius: 8px;
        color: #28745c;
        background: #fff;
        text-decoration: none;
        transition: background .18s ease, border-color .18s ease, transform .18s ease;
    }

    .pagination-control:hover {
        background: #eaf3ec;
        border-color: #72a78b;
        transform: translateY(-1px);
    }

    .pagination-control.is-disabled {
        color: #aab8b0;
        background: #f7f9f7;
        cursor: not-allowed;
    }

    .pagination-wrapper svg {
        width: 18px;
        height: 18px;
    }

    @media (max-width: 820px) {
        .entries-page { width: min(100% - 24px, 700px); padding: 14px 0 32px; }
        .entries-container { grid-template-columns: 1fr; gap: 10px; }
        .journal-header { padding: 14px 16px; margin-bottom: 10px; border-radius: 13px; }
        .journal-header h1 { font-size: 1.35rem; gap: 8px; }
        .journal-header p { font-size: 0.78rem; line-height: 1.45; }
        .journal-header svg { width: 22px; height: 22px; }
        .entry-header { padding: 9px 10px; gap: 7px; }
        .entry-meta { gap: 6px; }
        .emotion-badge { gap: 4px; padding: 4px 7px; font-size: 0.68rem; }
        .emotion-badge svg { width: 14px; height: 14px; }
        .emotional-score-badge { gap: 4px; padding: 4px 7px; font-size: 0.67rem; }
        .entry-date { font-size: 0.65rem; }
        .entry-date-day { font-size: 0.9rem; }
        .entry-date-time { font-size: 0.63rem; }
        .entry-content { padding: 9px 10px 10px; }
        .entry-text { font-size: 0.8rem; line-height: 1.5; }
        .ai-response-section { margin-top: 6px; padding-top: 6px; }
        .ai-response-header { min-height: 42px; font-size: 0.74rem; }
        .ai-response-text { padding: 9px 10px; font-size: 0.78rem; }
        .pagination-wrapper { margin: 10px 0 12px; }
        .pagination a, .pagination span, .pagination-control { min-width: 44px; width: 44px; height: 44px; }
    }

    @media (max-width: 420px) {
        .entries-page { width: min(100% - 20px, 700px); padding-top: 10px; }
        .journal-header { padding: 12px 13px; }
        .journal-header h1 { font-size: 1.2rem; }
        .entry-header { align-items: flex-start; }
        .entry-meta { gap: 5px; }
        .emotion-badge, .emotional-score-badge { max-width: 100%; }
        .confidence-score { font-size: .62rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        .entries-page *, .entries-page *::before, .entries-page *::after {
            scroll-behavior: auto !important;
            transition-duration: 0.01ms !important;
            animation-duration: 0.01ms !important;
        }
        .entries-page .entry-card:hover, .entries-page .pagination a:hover {
            transform: none;
        }
    }
</style>

<div class="journal-header">
    <h1>
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
        My Entries
    </h1>
    <p>Your private history of saved reflections and MAGI conversations.</p>
</div>

@if($entries->hasPages())
    {{ $entries->render('vendor.pagination.custom') }}
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
                        <details class="ai-response-section">
                            <summary class="ai-response-header">
                                <svg fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"></path></svg>
                                AI Support &amp; Guidance
                            </summary>
                            <div class="ai-response-text">{{ $entry->ai_response }}</div>
                        </details>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($entries->hasPages())
        {{ $entries->render('vendor.pagination.custom') }}
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
</div>
@endsection
