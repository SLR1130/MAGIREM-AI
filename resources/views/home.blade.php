@extends('layouts.app')

@section('title', config('branding.name') . ' · Student Wellness')

@section('content')
@if(!auth()->check() || !auth()->user()->isStudent())
    <div class="msc-public-page" id="mscPublicLanding">
        <header class="msc-public-header" data-public-header>
            <div class="msc-public-header__inner">
                <a class="msc-brand" href="{{ route('home') }}" aria-label="{{ config('branding.name') }} home">
                    <span class="msc-brand__mark" aria-hidden="true"><x-brand-logo variant="emblem" alt="" /></span>
                    <span class="msc-brand__copy"><strong>{{ config('branding.name') }}</strong><small>Student Wellness</small></span>
                </a>
                <nav class="msc-public-nav" id="mscPublicNav" aria-label="Public navigation">
                    <a class="is-active" href="#mscPublicTop" data-nav-item="home" title="Home">
                        <span class="nav-icon-wrap" aria-hidden="true">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        </span>
                        <span class="nav-label">Home</span>
                    </a>
                    <a href="#mscPublicFeatures" data-nav-item="features" title="What we offer">
                        <span class="nav-icon-wrap" aria-hidden="true">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                        </span>
                        <span class="nav-label">What we offer</span>
                    </a>
                    <a href="#mscPublicSupport" data-nav-item="support" title="Wellness support">
                        <span class="nav-icon-wrap" aria-hidden="true">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </span>
                        <span class="nav-label">Wellness support</span>
                    </a>
                </nav>
                <div class="msc-public-actions">
                    @auth
                        @if(auth()->user()->isCounselor())
                            <a class="msc-home-btn msc-home-btn--outline" href="{{ route('counselor.dashboard') }}" title="Counselor Dashboard">
                                <span class="nav-icon-wrap" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg></span>
                                <span class="nav-label">Dashboard</span>
                            </a>
                        @elseif(auth()->user()->isAdmin())
                            <a class="msc-home-btn msc-home-btn--outline" href="{{ route('admin.dashboard') }}" title="Admin Dashboard">
                                <span class="nav-icon-wrap" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></span>
                                <span class="nav-label">Dashboard</span>
                            </a>
                        @endif
                    @else
                        <a class="msc-home-btn msc-home-btn--outline" href="{{ route('login') }}" title="Sign in">
                            <span class="nav-icon-wrap" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg></span>
                            <span class="nav-label">Sign in</span>
                        </a>
                        <a class="msc-home-btn msc-home-btn--primary" href="{{ route('register') }}" title="Create account">
                            <span class="nav-icon-wrap" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg></span>
                            <span class="nav-label">Create account</span>
                            <span class="btn-arrow" aria-hidden="true">&rarr;</span>
                        </a>
                    @endauth
                    <button class="msc-public-menu" id="mscPublicMenu" type="button" aria-controls="mscPublicNav" aria-expanded="false"><span aria-hidden="true">&#9776;</span><span class="msc-sr-only">Menu</span></button>
                </div>
            </div>
        </header>

        <main id="mscPublicTop" class="msc-home-page msc-home-page--public">
            <section class="msc-home-stage" aria-labelledby="mscPublicHeroTitle">
                @include('partials.home-botanical-art')
                <div class="msc-home-shell">
                    <div class="msc-home-hero">
                        <div class="msc-home-copy" data-msc-reveal style="--msc-reveal-order:0">
                            <div class="msc-home-copy__rotating" data-msc-hero-copy aria-live="off">
                                <span class="msc-home-eyebrow" data-msc-hero-eyebrow>A wellness space for MinSU students</span>
                                <h1 id="mscPublicHeroTitle"><span data-msc-hero-line-one>Student life</span><br><span data-msc-hero-line-two>gets heavy.</span><br><em data-msc-hero-italic>Start here.</em></h1>
                                <p class="msc-home-description" data-msc-hero-description>Talk with MAGI, write in your journal, or connect with Guidance support—one step at a time.</p>
                            </div>
                            @include('partials.home-hero-controls')
                            <div class="msc-home-actions">
                                <a class="msc-home-btn msc-home-btn--primary" href="{{ route('register') }}">Begin your space <span aria-hidden="true">&rarr;</span></a>
                                <a class="msc-home-btn msc-home-btn--outline" href="{{ route('login') }}">Sign in</a>
                            </div>
                            <p class="msc-home-note"><span aria-hidden="true">&#10047;</span> At your pace. On your terms. One day at a time.</p>
                            <a class="msc-home-scroll-cue" href="#mscPublicPathways"><span>Explore your space</span><span aria-hidden="true">&darr;</span></a>
                        </div>
                    </div>
                    @include('partials.home-feature-cards', ['guest' => true])
                </div>
            </section>
            @include('partials.home-pathways-section', ['guest' => true])
            @include('partials.home-support-section', ['guest' => true])
            @include('partials.home-closing-cta', ['guest' => true])
        </main>
        <footer class="msc-home-footer" id="mscPublicFooter" aria-label="MAGIREM AI site footer">
            <div class="msc-home-footer__inner">
                <div class="msc-home-footer__main">
                    <a class="msc-home-footer__identity" href="{{ route('home') }}" aria-label="{{ config('branding.name') }} home">
                        <span class="msc-home-footer__mark"><x-brand-logo variant="emblem" alt="" /></span>
                        <span class="msc-home-footer__wordmark">
                            <strong>{{ config('branding.name') }}</strong>
                            <small>{{ config('branding.tagline') }}</small>
                        </span>
                    </a>

                    <p class="msc-home-footer__description">{{ config('branding.academic_title') }}</p>

                    <nav class="msc-home-footer__nav" aria-label="Footer navigation">
                        <span class="msc-home-footer__nav-title">Explore</span>
                        <a href="#mscPublicTop">Home</a>
                        <a href="{{ route('login') }}">Sign In</a>
                        <a href="{{ route('register') }}">Student Registration</a>
                    </nav>
                </div>

                <div class="msc-home-footer__bottom">
                    <span>&copy; {{ now()->year }} {{ config('branding.name') }}. All rights reserved.</span>
                    <span>Developed for the Mindoro State University student wellness initiative.</span>
                </div>
            </div>
        </footer>
    </div>
@else
    <main class="msc-home-page msc-home-page--student" aria-labelledby="mscStudentHeroTitle">
        <section class="msc-home-stage">
            @include('partials.home-botanical-art')
            <div class="msc-home-shell">
                <div class="msc-home-hero">
                    <div class="msc-home-copy" data-msc-reveal style="--msc-reveal-order:0">
                        <div class="msc-home-copy__rotating" data-msc-hero-copy aria-live="off">
                            <span class="msc-home-eyebrow" data-msc-hero-eyebrow>A wellness space for MinSU students</span>
                            <h1 id="mscStudentHeroTitle"><span data-msc-hero-line-one>Student life</span><br><span data-msc-hero-line-two>gets heavy.</span><br><em data-msc-hero-italic>Start here.</em></h1>
                                <p class="msc-home-description" data-msc-hero-description>Talk with MAGI, write in your journal, or connect with Guidance support—one step at a time.</p>
                            </div>
                            @include('partials.home-hero-controls')
                            <div class="msc-home-actions">
                            <a class="msc-home-btn msc-home-btn--primary" href="{{ route('student.ai-chat') }}">Talk to AI Companion <span aria-hidden="true">&rarr;</span></a>
                            <a class="msc-home-btn msc-home-btn--outline" href="{{ route('student.journal') }}">Open my Journal</a>
                        </div>
                            <p class="msc-home-note"><span aria-hidden="true">&#10047;</span> At your pace. On your terms. One day at a time.</p>
                            <a class="msc-home-scroll-cue" href="#mscStudentPathways"><span>Explore your space</span><span aria-hidden="true">&darr;</span></a>
                    </div>
                </div>
                @include('partials.home-feature-cards', ['guest' => false])
                @if($homeUpcomingAppointment)
                    <aside class="msc-home-appointment-note" aria-label="Upcoming counseling appointment" data-msc-reveal>
                        <span class="msc-home-appointment-note__icon" aria-hidden="true">&#128197;</span>
                        <span><strong>Your next confirmed session</strong><small>{{ $homeUpcomingAppointment->counselor->name ?? 'Your counselor' }} &middot; {{ $homeUpcomingAppointment->appointment_date->format('M j, Y') }}{{ $homeUpcomingAppointment->time_slot ? ' at ' . $homeUpcomingAppointment->time_slot : '' }}</small></span>
                        <a href="{{ route('student.appointments') }}">View appointments <span aria-hidden="true">&rarr;</span></a>
                    </aside>
                @endif
            </div>
        </section>
        @include('partials.home-pathways-section', ['guest' => false])
        @include('partials.home-support-section', ['guest' => false])
        @include('partials.home-closing-cta', ['guest' => false])
    </main>
    @include('student.partials.home-magi-recommendations')
@endif
@endsection
