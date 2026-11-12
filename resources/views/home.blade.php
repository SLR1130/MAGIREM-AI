@extends('layouts.app')

@section('title', config('branding.name') . ' · Student Wellness')

@section('content')
@if(!auth()->check() || !auth()->user()->isStudent())
    <div class="msc-public-page" id="mscPublicLanding">
        <header class="msc-public-header">
            <div class="msc-public-header__inner">
                <a class="msc-brand" href="{{ route('home') }}" aria-label="{{ config('branding.name') }} home">
                    <span class="msc-brand__mark" aria-hidden="true"><x-brand-logo variant="emblem" alt="" /></span>
                    <span class="msc-brand__copy"><strong>{{ config('branding.name') }}</strong><small>Student Wellness</small></span>
                </a>
                <nav class="msc-public-nav" id="mscPublicNav" aria-label="Public navigation">
                    <a class="is-active" href="#mscPublicTop">Home</a>
                    <a href="#mscPublicFeatures">What we offer</a>
                    <a href="#mscPublicSupport">Wellness support</a>
                </nav>
                <div class="msc-public-actions">
                    @auth
                        @if(auth()->user()->isCounselor())
                            <a class="msc-home-btn msc-home-btn--outline" href="{{ route('counselor.dashboard') }}">Dashboard</a>
                        @elseif(auth()->user()->isAdmin())
                            <a class="msc-home-btn msc-home-btn--outline" href="{{ route('admin.dashboard') }}">Dashboard</a>
                        @endif
                    @else
                        <a class="msc-home-btn msc-home-btn--outline" href="{{ route('login') }}">Sign in</a>
                        <a class="msc-home-btn msc-home-btn--primary" href="{{ route('register') }}">Create account <span aria-hidden="true">&rarr;</span></a>
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
