{{-- Shared navigation for regular student pages. Insights deliberately keeps the legacy analytics sidebar. --}}
@php
    $exploreActive = request()->routeIs(
        'student.insights',
        'student.coping-toolkit',
        'student.resources',
        'student.resources.search',
        'student.resources.show'
    );
@endphp
<style id="minsuanscore-student-header">
    body.student-header-layout {
        display: block;
        min-height: 100dvh;
        background:
            radial-gradient(circle at 92% 0, rgba(230, 241, 235, .92), transparent 31rem),
            #f8faf7;
    }

    body.student-header-layout .app-main {
        display: block;
        min-height: calc(100dvh - 76px);
        padding: 0;
        overflow: visible;
        background: transparent;
    }

    body.student-header-layout .content-wrapper {
        width: min(100%, 1440px);
        max-width: 1440px;
        margin: 0 auto;
        box-sizing: border-box;
        padding: 32px 36px 56px;
    }

    .student-topbar {
        position: sticky;
        top: 0;
        z-index: 1000;
        min-height: 76px;
        border-bottom: 1px solid rgba(16, 63, 50, .1);
        background: rgba(255, 255, 255, .94);
        box-shadow: 0 8px 28px rgba(16, 63, 50, .04);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
    }

    .student-topbar-inner {
        width: min(100%, 1540px);
        min-height: 76px;
        margin: 0 auto;
        padding: 0 28px;
        display: flex;
        align-items: center;
        gap: 22px;
    }

    .student-topbar-brand {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        flex: 0 0 auto;
        color: #103f32;
        text-decoration: none;
    }

    .student-topbar-mark,
    .student-topbar-avatar {
        display: grid;
        place-items: center;
        overflow: hidden;
        color: #fff;
        background: #103f32;
        box-shadow: 0 8px 20px rgba(16, 63, 50, .16);
    }

    .student-topbar-mark {
        width: 38px;
        height: 38px;
        border-radius: 12px;
    }

    .student-topbar-mark svg { width: 26px; height: 26px; }
    .student-topbar-brand-copy { display: grid; gap: 1px; line-height: 1.1; }
    .student-topbar-brand-copy strong { font-size: 14px; letter-spacing: -.02em; }
    .student-topbar-brand-copy small { color: #789087; font-size: 10px; font-weight: 700; }

    .student-topnav {
        display: flex;
        align-items: center;
        gap: 3px;
        min-width: 0;
        flex: 1 1 auto;
    }

    .student-topnav a,
    .student-more-nav summary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        padding: 0 9px;
        border: 1px solid transparent;
        border-radius: 10px;
        color: #597067;
        background: transparent;
        font: inherit;
        font-size: 11px;
        font-weight: 760;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        transition: color .18s ease, background .18s ease, border-color .18s ease;
    }

    .student-topnav a:hover,
    .student-topnav a:focus-visible,
    .student-more-nav summary:hover,
    .student-more-nav summary:focus-visible {
        color: #103f32;
        border-color: rgba(35, 107, 85, .12);
        background: #e6f1eb;
        outline: none;
    }

    .student-topnav a.is-active,
    .student-more-nav summary.is-active {
        color: #103f32;
        background: #e6f1eb;
        border-color: rgba(35, 107, 85, .14);
    }

    .student-more-nav { position: relative; }
    .student-more-nav summary { list-style: none; gap: 5px; }
    .student-more-nav summary::-webkit-details-marker { display: none; }
    .student-more-nav summary svg { width: 14px; height: 14px; transition: transform .18s ease; }
    .student-more-nav[open] summary svg { transform: rotate(180deg); }
    .student-more-menu {
        position: absolute;
        top: calc(100% + 9px);
        left: 0;
        z-index: 1001;
        min-width: 210px;
        padding: 7px;
        border: 1px solid rgba(16, 63, 50, .12);
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 18px 40px rgba(16, 63, 50, .14);
    }
    .student-more-menu a { justify-content: flex-start; width: 100%; padding: 0 11px; }

    .student-topbar-actions { display: flex; align-items: center; gap: 8px; flex: 0 0 auto; }
    .student-topbar-icon,
    .student-topbar-profile {
        position: relative;
        display: inline-grid;
        place-items: center;
        width: 40px;
        height: 40px;
        padding: 0;
        border: 1px solid rgba(16, 63, 50, .1);
        border-radius: 12px;
        color: #31574a;
        background: #fff;
        cursor: pointer;
        transition: transform .18s ease, background .18s ease, border-color .18s ease;
    }
    .student-topbar-icon:hover,
    .student-topbar-icon:focus-visible,
    .student-topbar-profile:hover,
    .student-topbar-profile:focus-visible,
    .student-topbar-profile.is-account-open {
        color: #103f32;
        border-color: rgba(35, 107, 85, .22);
        background: #e6f1eb;
        outline: none;
        transform: translateY(-1px);
    }
    .student-topbar-icon svg { width: 19px; height: 19px; }
    .student-topbar-avatar { width: 29px; height: 29px; border-radius: 9px; font-size: 10px; font-weight: 850; }
    .student-topbar-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .student-topbar-badge {
        position: absolute;
        right: -5px;
        top: -5px;
        display: grid;
        place-items: center;
        min-width: 17px;
        height: 17px;
        padding: 0 4px;
        border: 2px solid #fff;
        border-radius: 999px;
        color: #103f32;
        background: #f4c84e;
        font-size: 9px;
        font-weight: 850;
    }

    .student-topbar-menu { display: none; }


    @media (max-width: 900px) {
        .student-topbar { position: sticky; }
        .student-topbar-inner { min-height: 68px; padding: 0 16px; gap: 12px; }
        .student-topbar-actions { margin-left: auto; }
        .student-topbar-menu { display: inline-grid; }
        .student-topnav {
            position: absolute;
            top: calc(100% + 1px);
            left: 0;
            right: 0;
            display: none;
            max-height: calc(100dvh - 69px);
            padding: 12px 16px 18px;
            overflow-y: auto;
            border-bottom: 1px solid rgba(16, 63, 50, .12);
            background: rgba(255, 255, 255, .98);
            box-shadow: 0 18px 34px rgba(16, 63, 50, .12);
        }
        .student-topbar.is-menu-open .student-topnav { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .student-topnav a,
        .student-more-nav summary { justify-content: flex-start; min-height: 44px; padding: 0 12px; }

        .student-more-nav { grid-column: 1 / -1; }
        .student-more-nav summary { width: 100%; }
        .student-more-menu { position: static; margin-top: 6px; box-shadow: none; background: #f8faf7; }
        body.student-header-layout .content-wrapper { padding: 24px 20px 42px; }
    }

    @media (max-width: 540px) {
        .student-topbar-inner { padding: 0 12px; }
        .student-topbar-brand-copy small { display: none; }
        .student-topbar-brand-copy strong { font-size: 13px; }
        .student-topbar-mark { width: 35px; height: 35px; border-radius: 11px; }
        .student-topbar-actions { gap: 6px; }
        .student-topbar-icon,
        .student-topbar-profile { width: 38px; height: 38px; }
        body.student-header-layout .content-wrapper { padding: 18px 12px 34px; }
    }

    /* Premium motion layer: subtle, purposeful feedback for navigation controls. */
    .student-topbar { transition: min-height .25s ease, box-shadow .25s ease, background .25s ease; }
    .student-topbar.is-scrolled { box-shadow: 0 12px 34px rgba(16,63,50,.10); background: rgba(255,255,255,.98); }
    .student-topbar-brand:hover .student-topbar-mark,
    .student-topbar-brand:focus-visible .student-topbar-mark { transform: rotate(-8deg) scale(1.04); box-shadow: 0 10px 24px rgba(16,63,50,.23); }
    .student-topbar-mark { transition: transform .28s cubic-bezier(.22,1,.36,1), box-shadow .28s ease; }
    .student-topbar-brand-copy strong { transition: color .18s ease; }
    .student-topbar-brand:hover .student-topbar-brand-copy strong { color: #236b55; }
    .student-topnav a, .student-more-nav summary { position:relative; overflow:hidden; }
    .student-topnav a::after, .student-more-nav summary::after { content:""; position:absolute; left:11px; right:11px; bottom:4px; height:2px; border-radius:99px; background:#f4c84e; transform:scaleX(0); transform-origin:center; transition:transform .2s ease; }
    .student-topnav a:hover::after, .student-topnav a:focus-visible::after, .student-topnav a.is-active::after, .student-more-nav summary.is-active::after { transform:scaleX(1); }
    .student-more-menu { opacity:0; visibility:hidden; transform:translateY(-6px) scale(.98); transform-origin:top left; transition:opacity .18s ease, transform .18s ease, visibility .18s ease; }
    .student-more-nav[open] .student-more-menu { opacity:1; visibility:visible; transform:translateY(0) scale(1); }
    .student-more-menu a { transition:background .16s ease, transform .16s ease; }
    .student-more-menu a:hover, .student-more-menu a:focus-visible { transform:translateX(2px); }
    .student-topbar-icon:hover svg { animation:headerBellNudge .45s ease both; }
    .student-topbar-profile:hover .student-topbar-avatar { transform:scale(1.06); }
    .student-topbar-avatar { transition:transform .18s ease; }
    @keyframes headerBellNudge { 0%,100%{transform:rotate(0)} 35%{transform:rotate(-10deg)} 70%{transform:rotate(8deg)} }
    @media (max-width:900px) {
        .student-topnav { display:grid; visibility:hidden; opacity:0; transform:translateY(-8px); pointer-events:none; transition:opacity .2s ease, transform .2s ease, visibility .2s ease; }
        .student-topbar.is-menu-open .student-topnav { display:grid; visibility:visible; opacity:1; transform:translateY(0); pointer-events:auto; }
    }
    @media (prefers-reduced-motion:reduce) {
        .student-topbar, .student-topbar *, .student-topbar *::before, .student-topbar *::after { animation:none !important; transition:none !important; }
    }    @view-transition { navigation: auto; }
    :root { background: #f8faf7; }
    ::view-transition-group(root) { perspective: 1200px; transform-style: preserve-3d; }
    ::view-transition-old(root) {
        animation: mindcare-card-out 150ms cubic-bezier(.4,0,1,1) both;
        transform-origin: left center;
        backface-visibility: hidden;
    }
    ::view-transition-new(root) {
        animation: mindcare-card-in 260ms cubic-bezier(.22,1,.36,1) both;
        transform-origin: right center;
        backface-visibility: hidden;
    }
    @keyframes mindcare-card-out { from { opacity:1; transform:translateX(0) rotateY(0) scale(1); } to { opacity:0; transform:translateX(-28px) rotateY(-4deg) scale(.985); } }
    @keyframes mindcare-card-in { from { opacity:0; transform:translateX(28px) rotateY(4deg) scale(.985); } to { opacity:1; transform:translateX(0) rotateY(0) scale(1); } }
    @media (prefers-reduced-motion: reduce) {
        ::view-transition-old(root), ::view-transition-new(root) { animation:none !important; transform:none !important; }
    }</style>

<header class="student-topbar" data-student-header>
    <div class="student-topbar-inner">
        <a class="student-topbar-brand" href="{{ route('home') }}" aria-label="{{ config('branding.name') }} home">
            <span class="student-topbar-mark" aria-hidden="true">
                <x-brand-logo variant="emblem" alt="" />
            </span>
            <span class="student-topbar-brand-copy"><strong>{{ config('branding.name') }}</strong><small>Student Wellness</small></span>
        </a>

        <button type="button" class="student-topbar-icon student-topbar-menu" data-student-nav-toggle aria-label="Open navigation" aria-controls="studentTopNavigation" aria-expanded="false">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <nav class="student-topnav" id="studentTopNavigation" aria-label="Student navigation">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'is-active' : '' }}">Home</a>
            <a href="{{ route('student.ai-chat') }}" class="{{ request()->routeIs('student.ai-chat', 'student.ai-companion') ? 'is-active' : '' }}">AI Companion</a>
            <a href="{{ route('student.journal') }}" class="{{ request()->routeIs('student.journal', 'student.entries', 'student.create-entry') ? 'is-active' : '' }}">Journal</a>
            <a href="{{ route('student.appointments') }}" class="{{ request()->routeIs('student.appointments', 'student.request-appointment', 'student.counseling-requests') ? 'is-active' : '' }}">Appointments</a>
            <a href="{{ route('student.community') }}" class="{{ request()->routeIs('student.community') ? 'is-active' : '' }}">Community</a>
            <details class="student-more-nav">
                <summary class="{{ $exploreActive ? 'is-active' : '' }}">Explore <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m6 9 6 6 6-6"/></svg></summary>
                <div class="student-more-menu">
                    <a href="{{ route('student.insights') }}" class="{{ request()->routeIs('student.insights') ? 'is-active' : '' }}">Insights</a>
                    <a href="{{ route('student.coping-toolkit') }}" class="{{ request()->routeIs('student.coping-toolkit') ? 'is-active' : '' }}">Coping Toolkit</a>
                    <a href="{{ route('student.resources') }}" class="{{ request()->routeIs('student.resources', 'student.resources.search', 'student.resources.show') ? 'is-active' : '' }}">Wellness Resources</a>
                </div>
            </details>
        </nav>

        <div class="student-topbar-actions">
            @include('components.notification-dropdown')
            <button type="button" class="student-topbar-profile" data-account-menu-open aria-label="Open account menu">
                <span class="student-topbar-avatar">
                    @if(auth()->user()->profile_picture)<img src="{{ asset(auth()->user()->profile_picture) }}" alt="{{ auth()->user()->name }}">@else{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}@endif
                </span>
            </button>
        </div>
    </div>
</header>

<script id="minsuanscore-student-header-script">
    document.addEventListener('DOMContentLoaded', function () {
        const header = document.querySelector('[data-student-header]');
        const toggle = document.querySelector('[data-student-nav-toggle]');
        if (!header || !toggle) return;
        const closeMenu = function () { header.classList.remove('is-menu-open'); toggle.setAttribute('aria-expanded', 'false'); };
        toggle.addEventListener('click', function () {
            const opening = !header.classList.contains('is-menu-open');
            header.classList.toggle('is-menu-open', opening);
            toggle.setAttribute('aria-expanded', String(opening));
        });
        const explore = header.querySelector('.student-more-nav');
        const closeExplore = function () { if (explore) explore.removeAttribute('open'); };
        header.querySelectorAll('a').forEach(function (link) { link.addEventListener('click', function () { closeMenu(); closeExplore(); }); });
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { closeMenu(); closeExplore(); } });
        document.addEventListener('click', function (event) { if (!header.contains(event.target)) { closeMenu(); closeExplore(); } });
        window.addEventListener('scroll', function () { header.classList.toggle('is-scrolled', window.scrollY > 8); }, { passive: true });
    });
</script>
