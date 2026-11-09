@extends('layouts.app')

@section('title', 'Emergency Resources - MAGIREM AI')

@section('content')
<style>
    .emergency-page { color:#263a33; padding:2rem 0 4rem; }
    .emergency-page * { box-sizing:border-box; }
    .emergency-hero { background:linear-gradient(135deg,#8f1d1d,#dc2626); color:#fff; border-radius:22px; padding:1.5rem 1.7rem; margin-bottom:1.25rem; box-shadow:0 14px 32px rgba(127,29,29,.18); }
    .emergency-hero h1 { margin:0 0 .5rem; font-size:clamp(1.7rem,3vw,2.3rem); }
    .emergency-hero p { margin:0; line-height:1.6; color:#fee2e2; }
    .emergency-callout { background:#fff7ed; border:2px solid #f97316; border-radius:16px; padding:1rem 1.15rem; margin-bottom:1.5rem; color:#7c2d12; line-height:1.55; }
    .emergency-callout strong { display:block; margin-bottom:.25rem; }
    .hotline-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:1rem; }
    .hotline-card { background:#fff; border:1px solid #fecaca; border-top:4px solid #dc2626; border-radius:17px; padding:1.15rem; box-shadow:0 10px 25px rgba(127,29,29,.06); }
    .hotline-card h2 { margin:0 0 .35rem; font-size:1.05rem; color:#991b1b; }
    .hotline-number { display:inline-block; margin:.35rem 0 .55rem; font-size:1.35rem; font-weight:800; color:#7f1d1d; text-decoration:none; }
    .hotline-card p { margin:.35rem 0 0; color:#5d7168; line-height:1.55; }
    .hotline-meta { color:#71837a; font-size:.8rem; margin-top:.55rem; }
    .emergency-section { margin-top:2rem; }
    .emergency-section h2 { color:#103f32; margin:0 0 .35rem; }
    .emergency-section-intro { color:#71837a; margin:0 0 1rem; }
    .configured-card { border-color:#d8ebe2; border-top-color:#236b55; }
    .configured-card h2 { color:#236b55; }
    .safety-note { margin-top:1.5rem; padding:1rem 1.1rem; border-radius:14px; background:#e6f1eb; color:#264a3e; line-height:1.55; }
    @media(max-width:700px){.emergency-page{padding:1rem 0 3rem}.emergency-hero{padding:1.25rem;border-radius:18px}}
</style>

<div class="emergency-page">
    <section class="emergency-hero" aria-labelledby="emergency-title">
        <h1 id="emergency-title">Emergency Resources</h1>
        <p>Immediate help should remain easy to find. This page is separate from community discussions, wellness education, and counselor requests.</p>
    </section>

    <div class="emergency-callout" role="alert">
        <strong>If you may be in immediate danger</strong>
        Contact local emergency services now, go to the nearest emergency department, or ask a trusted person to stay with you. Do not wait for an app response.
    </div>

    <section class="emergency-section" aria-labelledby="local-hotlines-title">
        <h2 id="local-hotlines-title">Philippines contacts</h2>
        <p class="emergency-section-intro">Call the service directly and verify current availability when connected. Contact details can change.</p>
        <div class="hotline-grid">
            @if($hasPhilippineHotlines)
                @foreach($hotlines->filter(fn ($hotline) => strcasecmp((string) ($hotline->country ?? ''), 'Philippines') === 0) as $hotline)
                    @php $phone = $hotline->phone ?? $hotline->phone_number ?? null; @endphp
                    <article class="hotline-card">
                        <h2>{{ $hotline->name }}</h2>
                        @if($phone)<a class="hotline-number" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a>@endif
                        @if($hotline->description)<p>{{ $hotline->description }}</p>@endif
                        @if($hotline->availability)<div class="hotline-meta">Configured availability: {{ $hotline->availability }}</div>@endif
                    </article>
                @endforeach
            @else
                @foreach($fallbackHotlines as $hotline)
                    <article class="hotline-card">
                        <h2>{{ $hotline['name'] }}</h2>
                        <a class="hotline-number" href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline['phone']) }}">{{ $hotline['phone'] }}</a>
                        <p>{{ $hotline['description'] }}</p>
                    </article>
                @endforeach
            @endif
        </div>
    </section>

    @if($hotlines->isNotEmpty() && !$hasPhilippineHotlines)
        <section class="emergency-section" aria-labelledby="configured-title">
            <h2 id="configured-title">Other configured contacts</h2>
            <p class="emergency-section-intro">These entries are retained from the active institutional directory and may be outside the Philippines. Check the country and service details before calling.</p>
            <div class="hotline-grid">
                @foreach($hotlines as $hotline)
                    @php $phone = $hotline->phone ?? $hotline->phone_number ?? null; @endphp
                    <article class="hotline-card configured-card">
                        <h2>{{ $hotline->name }}</h2>
                        @if($phone)<a class="hotline-number" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a>@endif
                        @if($hotline->country)<div class="hotline-meta">Country: {{ $hotline->country }}</div>@endif
                        @if($hotline->description)<p>{{ $hotline->description }}</p>@endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <div class="safety-note">For non-urgent learning materials, visit <a href="{{ route('student.resources') }}">Wellness Resources</a>. To request university counseling, use <a href="{{ route('student.counseling-requests') }}">Counseling Requests</a>. Community posts and coping exercises are not emergency services.</div>
</div>
@endsection