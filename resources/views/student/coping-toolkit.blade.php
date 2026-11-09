@extends('layouts.app')

@section('title', 'Coping Toolkit - MAGIREM AI')

@push('head')
    @vite(['resources/css/coping-toolkit-experience.css', 'resources/js/coping-toolkit-runner.js'])
@endpush

@section('content')
@php
    $categoryLabel = fn ($category) => ucwords(str_replace(['-', '_'], ' ', (string) $category));
    $activityIcon = function ($activity) {
        $category = mb_strtolower((string) data_get($activity, 'category', ''), 'UTF-8');
        $name = mb_strtolower((string) data_get($activity, 'name', ''), 'UTF-8');
        if (str_contains($name, 'journal') || str_contains($category, 'journal')) return 'journal';
        if (str_contains($category, 'breath')) return 'breathing';
        if (str_contains($category, 'ground')) return 'grounding';
        if (str_contains($category, 'stress')) return 'stress';
        if (str_contains($category, 'mindful')) return 'mindfulness';
        if (str_contains($category, 'physical')) return 'movement';
        return 'general';
    };
    $baseSearchParams = array_filter([
        'q' => $copingSearchQuery !== '' ? $copingSearchQuery : null,
        'public_sources' => $includePublicSources ? 1 : null,
    ], fn ($value) => $value !== null);
@endphp

<style>
    .coping-page{--cp-ink:#143e32;--cp-green:#0c5946;--cp-mint:#eaf4ed;--cp-gold:#d8aa32;--cp-muted:#687b72;max-width:1440px;margin:0 auto;padding:1.1rem clamp(.9rem,2.3vw,2rem) 3.5rem;color:#29473c;font-family:inherit}
    .coping-page *{box-sizing:border-box}
    .coping-page .visually-hidden{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    .coping-page a:focus-visible,.coping-page button:focus-visible,.coping-page input:focus-visible,.coping-page summary:focus-visible{outline:3px solid rgba(216,170,50,.85);outline-offset:3px}
    .coping-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:1.2rem;margin:0 0 1rem;padding:1.15rem clamp(1.1rem,2.4vw,2rem);border:1px solid #d9e7dd;border-radius:18px;background:linear-gradient(112deg,#f0f7ef 0%,#fff 72%);box-shadow:0 8px 24px rgba(13,69,51,.055)}
    .coping-kicker{display:inline-flex;align-items:center;gap:.55rem;color:#34705b;font-size:.69rem;font-weight:850;letter-spacing:.14em;text-transform:uppercase}.coping-kicker:before{width:1.3rem;height:2px;background:var(--cp-gold);content:""}
    .coping-hero h1{margin:.32rem 0 .3rem;color:var(--cp-ink);font-family:Georgia,'Times New Roman',serif;font-size:clamp(1.65rem,3vw,2.25rem);letter-spacing:-.035em;line-height:1.12}
    .coping-hero p{max-width:760px;margin:0;color:#63766d;font-size:.9rem;line-height:1.5}
    .coping-safety{max-width:260px;padding:.7rem .85rem;border-left:3px solid var(--cp-gold);border-radius:0 10px 10px 0;background:#fff9e9;color:#63531f;font-size:.77rem;line-height:1.45}
    .coping-safety strong{display:block;margin-bottom:.15rem;color:#4e431c;font-size:.78rem}
    .coping-search{margin:0 0 1.35rem;padding:.75rem .85rem;border:1px solid #d8e6dc;border-radius:15px;background:#fff;box-shadow:0 10px 26px rgba(11,62,49,.075)}
    .coping-search-main{display:flex;align-items:center;gap:.65rem;min-height:3.05rem;padding:0 .75rem;border:1px solid #d3e2d8;border-radius:11px;background:#fbfdfb}
    .coping-search-icon{width:1.2rem;height:1.2rem;flex:0 0 auto;fill:none;stroke:#47715f;stroke-width:2}
    .coping-search input{width:100%;min-width:0;min-height:3rem;border:0;outline:0;background:transparent;color:#24483a;font:inherit;font-size:.95rem}
    .coping-search input::placeholder{color:#87958d}
    .coping-search-submit{display:grid;width:3rem;height:3rem;flex:0 0 3rem;place-items:center;border:0;border-radius:9px;background:#0b503f;color:#fff;cursor:pointer;transition:background .18s ease,transform .18s ease}
    .coping-search-submit:hover{background:#08735c;transform:translateY(-1px)}.coping-search-submit svg{width:1.25rem;height:1.25rem;fill:none;stroke:currentColor;stroke-width:2.2}
    .coping-search-options{display:flex;align-items:center;justify-content:space-between;gap:.8rem;flex-wrap:wrap;padding:.62rem .25rem .05rem;color:#6e7e75;font-size:.76rem}
    .coping-public-optin{display:flex;align-items:flex-start;gap:.55rem;max-width:850px;cursor:pointer;line-height:1.45}.coping-public-optin input{width:1rem;height:1rem;flex:0 0 auto;margin:.12rem 0 0;accent-color:#0b684f}.coping-public-optin strong{color:#345a49}.coping-clear{color:#176148;font-weight:800;text-decoration:none}.coping-clear:hover{text-decoration:underline}
    .coping-section{margin:1.5rem 0 0}.coping-section-head{display:flex;align-items:end;justify-content:space-between;gap:1rem;margin:0 0 .8rem}.coping-section h2{margin:0;color:var(--cp-ink);font-family:Georgia,'Times New Roman',serif;font-size:clamp(1.3rem,2.2vw,1.7rem);letter-spacing:-.025em;line-height:1.15}.coping-section-intro{margin:.28rem 0 0;color:#718178;font-size:.85rem;line-height:1.45}.coping-result-count{flex:0 0 auto;color:#426c57;font-size:.82rem;font-weight:800}
    .activity-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.85rem}.activity-card{display:flex;min-width:0;min-height:100%;flex-direction:column;padding:1rem;border:1px solid #dce8df;border-radius:15px;background:#fff;box-shadow:0 7px 20px rgba(11,62,49,.055);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}.activity-card:hover{transform:translateY(-2px);border-color:#b4d0bd;box-shadow:0 12px 25px rgba(11,62,49,.1)}
    .activity-card-top{display:flex;align-items:flex-start;gap:.7rem}.activity-icon{display:grid;width:2.65rem;height:2.65rem;flex:0 0 auto;place-items:center;border:1px solid #d7e9dc;border-radius:12px;background:#eaf4ed;color:#1c7255;font-size:1.25rem;font-weight:750}.activity-title-wrap{min-width:0;flex:1}.activity-card h3{margin:.08rem 0 0;color:#123f32;font-family:Georgia,'Times New Roman',serif;font-size:1.05rem;line-height:1.3;overflow-wrap:anywhere}
    .activity-icon:before{font-size:1.35rem;line-height:1}.activity-icon-breathing:before{content:"\25cc"}.activity-icon-grounding:before{content:"\25ce"}.activity-icon-journal:before{content:"\270e"}.activity-icon-stress:before{content:"\223f"}.activity-icon-mindfulness:before{content:"\2727"}.activity-icon-movement:before{content:"\2197"}.activity-icon-general:before{content:"\2726"}
    .activity-meta{display:flex;flex-wrap:wrap;gap:.35rem;margin:.7rem 0 .55rem}.activity-meta span{display:inline-flex;align-items:center;min-height:1.5rem;padding:.2rem .55rem;border:1px solid #dbe9df;border-radius:999px;background:#f2f7f2;color:#3c6a54;font-size:.69rem;font-weight:800}.activity-meta .activity-duration{background:#fff8e8;border-color:#f1e2b5;color:#6a571e}
    .activity-card p{margin:.2rem 0;color:#63766d;font-size:.84rem;line-height:1.52}.activity-card details{margin-top:.65rem;border-top:1px solid #e8eee9;padding-top:.55rem}.activity-card summary{color:#236b55;font-size:.8rem;font-weight:800;cursor:pointer}.activity-card details p{margin:.55rem 0 0;white-space:pre-line;overflow-wrap:anywhere}
    .activity-card-actions{display:flex;align-items:center;gap:.5rem;margin-top:auto;padding-top:.85rem}.exercise-button{display:inline-flex;align-items:center;justify-content:center;min-height:2.55rem;width:100%;border:1px solid #0b503f;border-radius:9px;padding:.55rem .8rem;background:#0b503f;color:#fff;font:inherit;font-size:.82rem;font-weight:850;cursor:pointer;transition:background .16s ease}.exercise-button:hover{background:#08735c}.activity-unavailable{color:#728178;font-size:.76rem;font-weight:700}
    .coping-empty{padding:1rem 1.1rem;border:1px dashed #b8d1c0;border-radius:13px;background:#f7faf6;color:#61766b;font-size:.9rem;line-height:1.5}.coping-category-filters{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;margin:.8rem 0 0}.category-filter-label{margin-right:.2rem;color:#516e5e;font-size:.76rem;font-weight:850}.category-chip{display:inline-flex;align-items:center;min-height:2rem;padding:.34rem .68rem;border:1px solid #d4e3d8;border-radius:999px;background:#fff;color:#315d49;font-size:.75rem;font-weight:750;text-decoration:none;transition:background .16s,border-color .16s}.category-chip:hover,.category-chip[aria-current="true"]{border-color:#0b503f;background:#0b503f;color:#fff}
    .related-resource-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem}.related-resource-card{min-width:0;padding:.9rem;border:1px solid #dce7de;border-radius:13px;background:#f9fbf8}.related-resource-card h3{margin:.3rem 0;color:#163f33;font-size:.98rem;line-height:1.35}.related-resource-card p{margin:.35rem 0;color:#68786f;font-size:.8rem;line-height:1.45}.resource-source-label{color:#22654d;font-size:.69rem;font-weight:850;letter-spacing:.04em;text-transform:uppercase}.resource-link{display:inline-flex;margin-top:.45rem;color:#0d634c;font-size:.8rem;font-weight:850;text-decoration:underline;text-underline-offset:2px}.resource-attribution{font-size:.69rem!important}
    .coping-status{margin:.7rem 0 0;padding:.65rem .8rem;border:1px solid #e1e8de;border-radius:10px;background:#f8faf7;color:#64756c;font-size:.8rem;line-height:1.45}.coping-status.error{border-color:#e9c8c0;background:#fff8f6;color:#7b4538}.coping-status.notice{border-color:#ecdba8;background:#fff9e8;color:#67531a}
    .coping-footer{margin-top:2rem;padding:.9rem 1rem;border:1px solid #efdfae;border-radius:12px;background:#fff9e9;color:#62541f;font-size:.82rem;line-height:1.5}.coping-footer a{color:#175a45;font-weight:850;text-decoration:underline;text-underline-offset:2px}
    @media(max-width:1000px){.activity-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.related-resource-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:700px){.coping-page{padding:1rem .85rem 2.5rem}.coping-hero{grid-template-columns:1fr;gap:.65rem;padding:1rem 1.05rem;border-radius:14px}.coping-safety{max-width:none;padding:.55rem .7rem}.coping-search{padding:.6rem}.coping-search-options{align-items:flex-start}.coping-search-main{gap:.45rem;padding:0 .55rem}.coping-search-submit{width:2.8rem;height:2.8rem;flex-basis:2.8rem}.activity-grid,.related-resource-grid{grid-template-columns:1fr}.activity-card{padding:.9rem}.coping-section-head{align-items:flex-start;flex-direction:column;gap:.25rem}.coping-result-count{white-space:normal}}
    @media(max-width:390px){.coping-page{padding-inline:.65rem}.coping-search-options{font-size:.72rem}.category-chip{font-size:.71rem;padding-inline:.58rem}}
    @media(prefers-reduced-motion:reduce){.activity-card,.category-chip,.exercise-button{transition:none}}
</style>

<main class="coping-page" id="coping-toolkit">
    <header class="coping-hero" aria-labelledby="coping-title">
        <div>
            <span class="coping-kicker">Student wellness - guided practices</span>
            <h1 id="coping-title">Find a practice for this moment.</h1>
            <p>Search practical activities, or browse the full toolkit. Choose what feels comfortable and move at your own pace.</p>
        </div>
        <aside class="coping-safety"><strong>Go at your pace</strong>These activities are general wellness support, not treatment or a diagnosis. Stop any practice that feels uncomfortable.</aside>
    </header>

    <form class="coping-search" method="get" action="{{ route('student.coping-toolkit') }}" role="search" data-coping-search>
        @if($copingCategory !== '')<input type="hidden" name="category" value="{{ $copingCategory }}">@endif
        <div class="coping-search-main">
            <svg class="coping-search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.6"></circle><path d="m16 16 4.5 4.5"></path></svg>
            <label class="visually-hidden" for="coping-query">Search coping activities</label>
            <input id="coping-query" type="search" name="q" value="{{ $copingSearchQuery }}" maxlength="120" placeholder="Try breathing, grounding, journaling or focus" autocomplete="off">
            <button class="coping-search-submit" type="submit" aria-label="Search coping activities" title="Search coping activities">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.6"></circle><path d="m16 16 4.5 4.5"></path></svg>
            </button>
        </div>
        <div class="coping-search-options">
            <label class="coping-public-optin"><input type="checkbox" name="public_sources" value="1" @checked($includePublicSources)><span><strong>Include MedlinePlus</strong>Only this topic will be sent for informational results. Avoid names or personal, journal, appointment, or crisis details. Articles are not guided activities.</span></label>
            @if($copingSearchQuery !== '' || $copingCategory !== '' || $includePublicSources)<a class="coping-clear" href="{{ route('student.coping-toolkit') }}">Clear search</a>@endif
        </div>
    </form>

    @if($hasCopingSearch)
        <section class="coping-section" aria-labelledby="matching-title" aria-live="polite">
            <div class="coping-section-head">
                <div><h2 id="matching-title">Guided activities</h2><p class="coping-section-intro">Only existing activities with matching names, descriptions, categories or practice keywords appear here.</p></div>
                <span class="coping-result-count">{{ $searchedStrategies->count() }} {{ $searchedStrategies->count() === 1 ? 'activity' : 'activities' }} found</span>
            </div>
            @if($searchedStrategies->isNotEmpty())
                <div class="activity-grid">
                    @foreach($searchedStrategies as $activity)
                        @include('student.partials.coping-activity-card', ['activity' => $activity, 'activityIcon' => $activityIcon, 'categoryLabel' => $categoryLabel])
                    @endforeach
                </div>
            @else
                <div class="coping-empty">No guided activity matched this search{{ $copingSearchQuery !== '' ? ' for "' . $copingSearchQuery . '"' : '' }}. Try a different term or choose a category below.</div>
            @endif
        </section>

        <nav class="coping-category-filters" aria-label="Filter coping activities by category">
            <span class="category-filter-label">Categories:</span>
            <a class="category-chip" href="{{ route('student.coping-toolkit', $baseSearchParams) }}" @if($copingCategory === '') aria-current="true" @endif>All</a>
            @foreach($activityCategories as $category)
                @php $categoryParams = $baseSearchParams + ['category' => $category]; @endphp
                <a class="category-chip" href="{{ route('student.coping-toolkit', $categoryParams) }}" @if($copingCategory === $category) aria-current="true" @endif>{{ $categoryLabel($category) }}</a>
            @endforeach
        </nav>

        @if($searchData && $includePublicSources && $searchData['mapping_note'])<p class="coping-status notice" role="status">{{ $searchData['mapping_note'] }}</p>@endif
        @if($searchData && $includePublicSources)
            @foreach($searchData['errors'] as $error)<p class="coping-status {{ str_contains(mb_strtolower($error), 'temporarily unavailable') || str_contains(mb_strtolower($error), 'not configured') ? 'error' : 'notice' }}" role="status">{{ $error }}</p>@endforeach
            @if($searchData['remote']->isNotEmpty())
                <section class="coping-section" aria-labelledby="related-resources-title">
                    <div class="coping-section-head"><div><h2 id="related-resources-title">Related wellness resources</h2><p class="coping-section-intro">Informational MedlinePlus results are separate from guided practices.</p></div><span class="coping-result-count">{{ $searchData['remote']->count() }} {{ $searchData['remote']->count() === 1 ? 'resource' : 'resources' }}</span></div>
                    <div class="related-resource-grid">
                        @foreach($searchData['remote'] as $result)
                            <article class="related-resource-card"><span class="resource-source-label">{{ $result['provider'] }}</span><h3>{{ $result['title'] }}</h3>@if($result['summary'])<p>{{ $result['summary'] }}</p>@endif<p class="resource-attribution">{{ $result['attribution'] }}</p><a class="resource-link" href="{{ $result['url'] }}" target="_blank" rel="noopener noreferrer">Open official source</a></article>
                        @endforeach
                    </div>
                </section>
            @elseif(empty($searchData['errors']))
                <p class="coping-status">MedlinePlus returned no related articles for this topic. Guided activity results above are unchanged.</p>
            @endif
        @endif

        @if($relatedStrategies->isNotEmpty())
            <section class="coping-section" aria-labelledby="related-practices-title">
                <div class="coping-section-head"><div><h2 id="related-practices-title">Related practices</h2><p class="coping-section-intro">Optional starting points based on your latest check-in. Choose what feels useful; this is not a clinical assessment.</p></div></div>
                <div class="activity-grid">@foreach($relatedStrategies as $activity)@include('student.partials.coping-activity-card', ['activity' => $activity, 'activityIcon' => $activityIcon, 'categoryLabel' => $categoryLabel])@endforeach</div>
            </section>
        @endif
    @else
        <nav class="coping-category-filters" aria-label="Browse coping activities by category">
            <span class="category-filter-label">Browse by category:</span>
            @foreach($activityCategories as $category)
                @php $categoryParams = ['category' => $category]; @endphp
                <a class="category-chip" href="{{ route('student.coping-toolkit', $categoryParams) }}">{{ $categoryLabel($category) }}</a>
            @endforeach
        </nav>
        @if($relatedStrategies->isNotEmpty())
            <section class="coping-section" aria-labelledby="suggested-title">
                <div class="coping-section-head"><div><h2 id="suggested-title">Optional starting points</h2><p class="coping-section-intro">Based on your latest check-in, if available. These are suggestions only—choose what feels useful to you.</p></div></div>
                <div class="activity-grid">@foreach($relatedStrategies as $activity)@include('student.partials.coping-activity-card', ['activity' => $activity, 'activityIcon' => $activityIcon, 'categoryLabel' => $categoryLabel])@endforeach</div>
            </section>
        @endif
    @endif

    @if($otherActivities->isNotEmpty())
        <section class="coping-section" aria-labelledby="activities-title">
            <div class="coping-section-head"><div><h2 id="activities-title">{{ $hasCopingSearch ? 'Other guided activities' : 'Guided activities' }}</h2><p class="coping-section-intro">Real practices from the local toolkit. Open any steps before starting.</p></div></div>
            <div class="activity-grid">@foreach($otherActivities as $activity)@include('student.partials.coping-activity-card', ['activity' => $activity, 'activityIcon' => $activityIcon, 'categoryLabel' => $categoryLabel])@endforeach</div>
        </section>
    @elseif($allStrategies->isEmpty())
        <p class="coping-status">No additional database activities are available yet. The core practices above remain available.</p>
    @endif

    <aside class="coping-footer">If a practice feels uncomfortable, stop it. You can request support through <a href="{{ route('student.counseling-requests') }}">Counseling Requests</a>. For immediate danger, use <a href="{{ route('student.emergency-resources') }}">Emergency Resources</a>.</aside>
</main>

@include('student.partials.coping-practice-runner')
@endsection
