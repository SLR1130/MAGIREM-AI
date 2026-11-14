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
        min-height: calc(100dvh - 84px);
        padding: 0;
        overflow: visible;
        background: transparent;
    }

    body.student-header-layout .content-wrapper {
        width: min(100%, 1440px);
        max-width: 1440px;
        margin: 0 auto;
        box-sizing: border-box;
        padding: 24px 32px 56px;
    }

    /* ============================================================
       MODERN FLOATING ISLAND NAVBAR (DESKTOP / LAPTOP)
       ============================================================ */
    .student-topbar {
        position: sticky;
        top: 14px;
        z-index: 1000;
        width: min(1440px, calc(100% - 32px));
        margin: 14px auto 0;
        padding: 6px 14px;
        border: 1px solid rgba(16, 63, 50, .12);
        border-radius: 9999px;
        background: rgba(255, 255, 255, .88);
        box-shadow: 0 16px 38px -6px rgba(16, 63, 50, .09), 0 4px 14px -2px rgba(0, 0, 0, .04), inset 0 1px 0 rgba(255, 255, 255, .95);
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        transition: all .28s cubic-bezier(.16, 1, .3, 1);
    }

    .student-topbar.is-scrolled {
        top: 10px;
        background: rgba(255, 255, 255, .96);
        border-color: rgba(16, 63, 50, .18);
        box-shadow: 0 20px 42px -6px rgba(16, 63, 50, .14), 0 6px 18px -2px rgba(0, 0, 0, .05);
    }

    .student-topbar-inner {
        width: 100%;
        min-height: 52px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    /* Brand identity */
    .student-topbar-brand {
        display: inline-flex;
        align-items: center;
        gap: 11px;
        flex: 0 0 auto;
        color: #103f32;
        text-decoration: none;
    }

    .student-topbar-mark {
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        color: #f4c84e;
        background: #103f32;
        box-shadow: 0 8px 20px rgba(16, 63, 50, .16);
        transition: transform .25s ease, box-shadow .25s ease;
    }

    .student-topbar-brand:hover .student-topbar-mark {
        transform: rotate(-6deg) scale(1.05);
        box-shadow: 0 12px 26px rgba(16, 63, 50, .24);
    }

    .student-topbar-mark svg { width: 24px; height: 24px; }
    .student-topbar-brand-copy { display: grid; gap: 1px; line-height: 1.1; }
    .student-topbar-brand-copy strong { font-size: 15px; font-weight: 850; letter-spacing: -.03em; color: #103f32; }
    .student-topbar-brand-copy small { color: #768f83; font-size: 10px; font-weight: 750; letter-spacing: .02em; }

    /* Modern Desktop Nav with Names + Icons */
    .student-topnav {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 4px;
        background: rgba(16, 63, 50, .035);
        border: 1px solid rgba(16, 63, 50, .06);
        border-radius: 9999px;
    }

    .student-topnav a,
    .student-more-nav summary {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 38px;
        padding: 0 14px;
        border-radius: 9999px;
        color: #436254;
        font: inherit;
        font-size: 13px;
        font-weight: 750;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        position: relative;
        transition: all .24s cubic-bezier(.34, 1.56, .64, 1);
        border: none;
        background: transparent;
        list-style: none;
    }

    .student-topnav a::-webkit-details-marker,
    .student-more-nav summary::-webkit-details-marker { display: none; }

    /* Nav icon wrapper & glowing highlight effect */
    .student-topnav .nav-icon-wrap,
    .student-more-nav .nav-icon-wrap {
        display: inline-grid;
        place-items: center;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: rgba(16, 63, 50, .05);
        color: #236b55;
        flex-shrink: 0;
        transition: all .26s cubic-bezier(.34, 1.56, .64, 1);
    }

    .student-topnav .nav-icon-wrap svg,
    .student-more-nav .nav-icon-wrap svg {
        width: 14.5px;
        height: 14.5px;
    }

    /* Hover effect: highlights icon and pill background */
    .student-topnav a:hover,
    .student-topnav a:focus-visible,
    .student-more-nav summary:hover,
    .student-more-nav summary:focus-visible {
        color: #103f32;
        background: #ffffff;
        box-shadow: 0 6px 18px rgba(16, 63, 50, .09);
        transform: translateY(-1.5px);
        outline: none;
    }

    .student-topnav a:hover .nav-icon-wrap,
    .student-topnav a:focus-visible .nav-icon-wrap,
    .student-more-nav summary:hover .nav-icon-wrap,
    .student-more-nav summary:focus-visible .nav-icon-wrap {
        background: #103f32;
        color: #f4c84e;
        transform: scale(1.15) rotate(-5deg);
        box-shadow: 0 4px 10px rgba(16, 63, 50, .22);
    }

    /* Active link style */
    .student-topnav a.is-active,
    .student-more-nav summary.is-active {
        color: #ffffff;
        background: linear-gradient(135deg, #103f32, #1d5f4c);
        box-shadow: 0 8px 20px rgba(16, 63, 50, .20);
    }

    .student-topnav a.is-active .nav-icon-wrap,
    .student-more-nav summary.is-active .nav-icon-wrap {
        background: rgba(255, 255, 255, .22);
        color: #f4c84e;
    }

    /* Explore dropdown */
    .student-more-nav { position: relative; }
    .student-more-nav summary .chevron-icon {
        width: 12px;
        height: 12px;
        margin-left: -2px;
        transition: transform .22s ease;
    }
    .student-more-nav[open] summary .chevron-icon { transform: rotate(180deg); }

    .student-more-menu {
        position: absolute;
        top: calc(100% + 10px);
        left: 50%;
        transform: translateX(-50%) translateY(4px);
        z-index: 1001;
        min-width: 250px;
        padding: 8px;
        border: 1px solid rgba(16, 63, 50, .14);
        border-radius: 18px;
        background: rgba(255, 255, 255, .98);
        box-shadow: 0 20px 45px rgba(16, 63, 50, .16), 0 4px 12px rgba(0, 0, 0, .05);
        backdrop-filter: blur(20px);
        display: flex;
        flex-direction: column;
        gap: 3px;
        animation: dropMenuIn .2s cubic-bezier(.22, 1, .36, 1) forwards;
    }

    @keyframes dropMenuIn {
        from { opacity: 0; transform: translateX(-50%) translateY(10px) scale(.97); }
        to { opacity: 1; transform: translateX(-50%) translateY(0) scale(1); }
    }

    .student-more-menu a {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 9px 12px;
        border-radius: 12px;
        color: #2b473d;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        transition: all .2s ease;
        background: transparent;
        box-shadow: none;
        transform: none;
    }

    .student-more-menu a .menu-icon-wrap {
        display: inline-grid;
        place-items: center;
        width: 32px;
        height: 32px;
        border-radius: 10px;
        background: rgba(16, 63, 50, .06);
        color: #236b55;
        flex-shrink: 0;
        transition: all .22s ease;
    }

    .student-more-menu a .menu-icon-wrap svg { width: 17px; height: 17px; }

    .student-more-menu a:hover {
        background: #f1f7f4;
        color: #103f32;
        transform: translateX(3px);
    }

    .student-more-menu a:hover .menu-icon-wrap {
        background: #103f32;
        color: #f4c84e;
        transform: scale(1.08);
    }

    .student-more-menu a.is-active {
        background: #e6f1eb;
        color: #103f32;
        border-left: 3px solid #236b55;
    }

    .student-more-menu a.is-active .menu-icon-wrap {
        background: #236b55;
        color: #ffffff;
    }

    /* Topbar Actions */
    .student-topbar-actions { display: flex; align-items: center; gap: 8px; flex: 0 0 auto; }
    .student-topbar-actions .magi-notice__toggle {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: 1px solid rgba(16, 63, 50, .14);
        background: #ffffff;
        color: #236b55;
        box-shadow: 0 4px 12px rgba(16, 63, 50, .06);
        transition: all .22s cubic-bezier(.34, 1.56, .64, 1);
    }
    .student-topbar-actions .magi-notice__toggle:hover,
    .student-topbar-actions .magi-notice__toggle[aria-expanded=true] {
        background: #103f32;
        color: #f4c84e;
        border-color: #103f32;
        transform: translateY(-1.5px) scale(1.05);
        box-shadow: 0 8px 20px rgba(16, 63, 50, .22);
    }
    .student-topbar-actions .magi-notice__toggle:hover svg {
        animation: headerBellShake .4s ease;
    }
    @keyframes headerBellShake {
        0%, 100% { transform: rotate(0); }
        25% { transform: rotate(-12deg); }
        75% { transform: rotate(12deg); }
    }
    .student-topbar-profile {
        position: relative;
        display: inline-grid;
        place-items: center;
        width: 42px;
        height: 42px;
        padding: 0;
        border: 1px solid rgba(16, 63, 50, .14);
        border-radius: 50%;
        color: #31574a;
        background: #ffffff;
        cursor: pointer;
        transition: transform .22s ease, background .22s ease, border-color .22s ease, box-shadow .22s ease;
        box-shadow: 0 4px 12px rgba(16, 63, 50, .06);
    }

    .student-topbar-profile:hover,
    .student-topbar-profile:focus-visible,
    .student-topbar-profile.is-account-open {
        color: #103f32;
        border-color: #236b55;
        background: #e6f1eb;
        outline: none;
        transform: translateY(-1.5px) scale(1.04);
        box-shadow: 0 8px 20px rgba(16, 63, 50, .14);
    }

    .student-topbar-avatar {
        width: 32px;
        height: 32px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        font-size: 11px;
        font-weight: 850;
        background: #103f32;
        color: #ffffff;
        overflow: hidden;
    }

    .student-topbar-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .student-topbar-menu { display: none; }

    /* ============================================================
       MOBILE APP DOCK & ICONIZED MODE ("like a real mobile app")
       ============================================================ */
    .student-mobile-dock {
        display: none;
    }

    @media (max-width: 990px) {
        /* Hide desktop horizontal nav bar */
        .student-topnav { display: none !important; }

        /* Floating compact top header */
        .student-topbar {
            width: calc(100% - 20px);
            top: 8px;
            margin: 8px auto 0;
            padding: 6px 12px;
            border-radius: 24px;
        }

        .student-topbar-inner {
            min-height: 48px;
        }

        body.student-header-layout .content-wrapper {
            padding: 16px 16px 96px; /* Extra bottom padding so dock never obscures content */
        }

        /* Floating native mobile bottom icon dock */
        .student-mobile-dock {
            display: flex;
            position: fixed;
            bottom: 14px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 24px);
            max-width: 440px;
            z-index: 1050;
            border-radius: 9999px;
            background: rgba(16, 63, 50, .94);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, .18);
            box-shadow: 0 16px 42px rgba(16, 63, 50, .36), 0 4px 14px rgba(0, 0, 0, .16), inset 0 1px 0 rgba(255, 255, 255, .25);
            align-items: center;
            justify-content: space-around;
            padding: 6px 8px;
        }

        .dock-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            min-width: 48px;
            min-height: 48px;
            padding: 4px 6px;
            border-radius: 16px;
            color: rgba(255, 255, 255, .72);
            text-decoration: none;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all .22s cubic-bezier(.34, 1.56, .64, 1);
            -webkit-tap-highlight-color: transparent;
        }

        .dock-item .dock-icon {
            display: grid;
            place-items: center;
            width: 26px;
            height: 26px;
            border-radius: 10px;
            transition: all .22s cubic-bezier(.34, 1.56, .64, 1);
        }

        .dock-item .dock-icon svg {
            width: 20px;
            height: 20px;
            stroke-width: 2;
        }

        .dock-item .dock-text {
            font-size: 9.5px;
            font-weight: 750;
            letter-spacing: .02em;
            line-height: 1;
            transition: color .2s ease;
        }

        .dock-item:hover,
        .dock-item:focus-visible {
            color: #ffffff;
            transform: translateY(-2px);
            outline: none;
        }

        .dock-item:hover .dock-icon {
            background: rgba(255, 255, 255, .15);
            color: #f4c84e;
            transform: scale(1.12);
        }

        /* Active dock item highlight */
        .dock-item.is-active {
            color: #f4c84e;
            background: rgba(255, 255, 255, .12);
            box-shadow: 0 4px 14px rgba(0, 0, 0, .2);
            transform: translateY(-2px);
        }

        .dock-item.is-active .dock-icon {
            color: #f4c84e;
            transform: scale(1.1);
        }

        .dock-item.is-active .dock-text {
            color: #ffffff;
            font-weight: 850;
        }

        /* MAGI AI special pulse glow */
        .dock-item--ai .dock-icon {
            background: linear-gradient(135deg, rgba(244, 200, 78, .2), rgba(255, 255, 255, .15));
            border-radius: 50%;
        }

        .dock-item--ai.is-active .dock-icon {
            background: #f4c84e;
            color: #103f32;
        }
    }

    @media (max-width: 540px) {
        .student-topbar {
            width: calc(100% - 16px);
            top: 6px;
            padding: 5px 10px;
        }
        .student-topbar-brand-copy small { display: none; }
        .student-topbar-brand-copy strong { font-size: 13.5px; }
        .student-topbar-mark { width: 36px; height: 36px; border-radius: 11px; }
        .student-topbar-actions { gap: 6px; }
        .student-topbar-profile { width: 38px; height: 38px; }
        .student-mobile-dock {
            bottom: 10px;
            width: calc(100% - 16px);
            padding: 4px 6px;
        }
        .dock-item {
            min-width: 44px;
            min-height: 44px;
            padding: 3px 4px;
        }
        .dock-item .dock-icon svg { width: 19px; height: 19px; }
        .dock-item .dock-text { font-size: 9px; }
        body.student-header-layout .content-wrapper { padding: 14px 12px 90px; }
    }

    /* Mobile Explore Bottom Sheet Modal */
    .student-mobile-explore-sheet {
        position: fixed;
        inset: 0;
        z-index: 1060;
        display: none;
        align-items: flex-end;
        justify-content: center;
    }

    .student-mobile-explore-sheet.is-open {
        display: flex;
    }

    .sheet-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(16, 63, 50, .45);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        animation: fadeIn .2s ease forwards;
    }

    .sheet-panel {
        position: relative;
        width: min(100%, 480px);
        background: #ffffff;
        border-radius: 28px 28px 0 0;
        padding: 16px 20px 28px;
        box-shadow: 0 -12px 40px rgba(16, 63, 50, .24);
        animation: slideUp .26s cubic-bezier(.16, 1, .3, 1) forwards;
        z-index: 2;
    }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }

    .sheet-handle {
        width: 44px;
        height: 5px;
        border-radius: 99px;
        background: #d4e0d9;
        margin: 0 auto 14px;
    }

    .sheet-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(16, 63, 50, .08);
    }

    .sheet-header h3 {
        font-size: 16px;
        font-weight: 800;
        color: #103f32;
        margin: 0;
    }

    .sheet-close-btn {
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: none;
        background: #e6f1eb;
        color: #103f32;
        font-size: 18px;
        cursor: pointer;
    }

    .sheet-grid {
        display: grid;
        gap: 10px;
    }

    .sheet-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 14px;
        border-radius: 16px;
        border: 1px solid rgba(16, 63, 50, .08);
        background: #f8faf7;
        color: #103f32;
        text-decoration: none;
        transition: all .2s ease;
    }

    .sheet-item:hover,
    .sheet-item.is-active {
        background: #e6f1eb;
        border-color: rgba(35, 107, 85, .25);
        transform: translateY(-1px);
    }

    .sheet-icon {
        display: grid;
        place-items: center;
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: #103f32;
        color: #f4c84e;
        flex-shrink: 0;
    }

    .sheet-icon svg { width: 22px; height: 22px; }
    .sheet-copy { display: grid; gap: 2px; }
    .sheet-copy strong { font-size: 14px; font-weight: 800; color: #103f32; }
    .sheet-copy small { font-size: 11px; color: #627b70; font-weight: 600; }

    @media (prefers-reduced-motion:reduce) {
        .student-topbar, .student-topbar *, .student-mobile-dock, .student-mobile-dock * {
            animation: none !important;
            transition: none !important;
        }
    }
</style>

<header class="student-topbar" data-student-header>
    <div class="student-topbar-inner">
        <a class="student-topbar-brand" href="{{ route('home') }}" aria-label="{{ config('branding.name') }} home">
            <span class="student-topbar-mark" aria-hidden="true">
                <x-brand-logo variant="emblem" alt="" />
            </span>
            <span class="student-topbar-brand-copy">
                <strong>{{ config('branding.name') }}</strong>
                <small>Student Wellness</small>
            </span>
        </a>

        <!-- Laptop / Desktop Navigation with Names + Icons & Glowing Hover Highlights -->
        <nav class="student-topnav" id="studentTopNavigation" aria-label="Student navigation">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'is-active' : '' }}" title="Home">
                <span class="nav-icon-wrap" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                </span>
                <span class="nav-label">Home</span>
            </a>

            <a href="{{ route('student.ai-chat') }}" class="{{ request()->routeIs('student.ai-chat', 'student.ai-companion') ? 'is-active' : '' }}" title="AI Companion">
                <span class="nav-icon-wrap" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/></svg>
                </span>
                <span class="nav-label">AI Companion</span>
            </a>

            <a href="{{ route('student.journal') }}" class="{{ request()->routeIs('student.journal', 'student.entries', 'student.create-entry') ? 'is-active' : '' }}" title="Journal">
                <span class="nav-icon-wrap" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </span>
                <span class="nav-label">Journal</span>
            </a>

            <a href="{{ route('student.appointments') }}" class="{{ request()->routeIs('student.appointments', 'student.request-appointment', 'student.counseling-requests') ? 'is-active' : '' }}" title="Appointments">
                <span class="nav-icon-wrap" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
                <span class="nav-label">Appointments</span>
            </a>

            <a href="{{ route('student.community') }}" class="{{ request()->routeIs('student.community') ? 'is-active' : '' }}" title="Community">
                <span class="nav-icon-wrap" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </span>
                <span class="nav-label">Community</span>
            </a>

            <details class="student-more-nav">
                <summary class="{{ $exploreActive ? 'is-active' : '' }}">
                    <span class="nav-icon-wrap" aria-hidden="true">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.24 7.76-2.12 6.36-6.36 2.12 2.12-6.36z"/></svg>
                    </span>
                    <span class="nav-label">Explore</span>
                    <svg class="chevron-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="student-more-menu">
                    <a href="{{ route('student.insights') }}" class="{{ request()->routeIs('student.insights') ? 'is-active' : '' }}">
                        <span class="menu-icon-wrap" aria-hidden="true">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </span>
                        <span>Insights</span>
                    </a>
                    <a href="{{ route('student.coping-toolkit') }}" class="{{ request()->routeIs('student.coping-toolkit') ? 'is-active' : '' }}">
                        <span class="menu-icon-wrap" aria-hidden="true">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </span>
                        <span>Coping Toolkit</span>
                    </a>
                    <a href="{{ route('student.resources') }}" class="{{ request()->routeIs('student.resources', 'student.resources.search', 'student.resources.show') ? 'is-active' : '' }}">
                        <span class="menu-icon-wrap" aria-hidden="true">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </span>
                        <span>Wellness Resources</span>
                    </a>
                </div>
            </details>
        </nav>

        <!-- Right Actions: Notifications + Profile -->
        <div class="student-topbar-actions">
            @include('components.notification-dropdown')
            <button type="button" class="student-topbar-profile" data-account-menu-open aria-label="Open account menu">
                <span class="student-topbar-avatar">
                    @if(auth()->user()->profile_picture)
                        <img src="{{ asset(auth()->user()->profile_picture) }}" alt="{{ auth()->user()->name }}">
                    @else
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    @endif
                </span>
            </button>
        </div>
    </div>
</header>

{{-- Mobile Native App Icon Dock ("like a real mobile app in mobile full icons") --}}
<nav class="student-mobile-dock" id="studentMobileDock" aria-label="Mobile navigation">
    <a href="{{ route('home') }}" class="dock-item {{ request()->routeIs('home') ? 'is-active' : '' }}" title="Home">
        <span class="dock-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        </span>
        <span class="dock-text">Home</span>
    </a>

    <a href="{{ route('student.ai-chat') }}" class="dock-item dock-item--ai {{ request()->routeIs('student.ai-chat', 'student.ai-companion') ? 'is-active' : '' }}" title="AI Companion">
        <span class="dock-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/></svg>
        </span>
        <span class="dock-text">MAGI</span>
    </a>

    <a href="{{ route('student.journal') }}" class="dock-item {{ request()->routeIs('student.journal', 'student.entries', 'student.create-entry') ? 'is-active' : '' }}" title="Journal">
        <span class="dock-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        </span>
        <span class="dock-text">Journal</span>
    </a>

    <a href="{{ route('student.appointments') }}" class="dock-item {{ request()->routeIs('student.appointments', 'student.request-appointment', 'student.counseling-requests') ? 'is-active' : '' }}" title="Appointments">
        <span class="dock-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </span>
        <span class="dock-text">Appts</span>
    </a>

    <a href="{{ route('student.community') }}" class="dock-item {{ request()->routeIs('student.community') ? 'is-active' : '' }}" title="Community">
        <span class="dock-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </span>
        <span class="dock-text">Circle</span>
    </a>

    <button type="button" class="dock-item {{ $exploreActive ? 'is-active' : '' }}" id="mobileExploreTrigger" aria-label="Open Explore" aria-controls="studentMobileExploreSheet">
        <span class="dock-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m16.24 7.76-2.12 6.36-6.36 2.12 2.12-6.36z"/></svg>
        </span>
        <span class="dock-text">Explore</span>
    </button>
</nav>

{{-- Mobile Explore Bottom Sheet --}}
<div class="student-mobile-explore-sheet" id="studentMobileExploreSheet" aria-hidden="true">
    <div class="sheet-backdrop" data-sheet-close></div>
    <div class="sheet-panel">
        <div class="sheet-handle"></div>
        <div class="sheet-header">
            <h3>Explore MAGIREM</h3>
            <button type="button" class="sheet-close-btn" data-sheet-close aria-label="Close explore">&times;</button>
        </div>
        <div class="sheet-grid">
            <a href="{{ route('student.insights') }}" class="sheet-item {{ request()->routeIs('student.insights') ? 'is-active' : '' }}">
                <span class="sheet-icon" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </span>
                <div class="sheet-copy">
                    <strong>Insights</strong>
                    <small>Self-reflection analytics & mood patterns</small>
                </div>
            </a>
            <a href="{{ route('student.coping-toolkit') }}" class="sheet-item {{ request()->routeIs('student.coping-toolkit') ? 'is-active' : '' }}">
                <span class="sheet-icon" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </span>
                <div class="sheet-copy">
                    <strong>Coping Toolkit</strong>
                    <small>Guided grounding & breathing exercises</small>
                </div>
            </a>
            <a href="{{ route('student.resources') }}" class="sheet-item {{ request()->routeIs('student.resources', 'student.resources.search', 'student.resources.show') ? 'is-active' : '' }}">
                <span class="sheet-icon" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </span>
                <div class="sheet-copy">
                    <strong>Wellness Resources</strong>
                    <small>Verified self-help guides & contacts</small>
                </div>
            </a>
        </div>
    </div>
</div>

<script id="minsuanscore-student-header-script">
    document.addEventListener('DOMContentLoaded', function () {
        const header = document.querySelector('[data-student-header]');
        const explore = header ? header.querySelector('.student-more-nav') : null;
        const closeExplore = function () { if (explore) explore.removeAttribute('open'); };

        if (header) {
            header.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', closeExplore);
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') closeExplore();
            });
            document.addEventListener('click', function (event) {
                if (!header.contains(event.target)) closeExplore();
            });
            window.addEventListener('scroll', function () {
                header.classList.toggle('is-scrolled', window.scrollY > 8);
            }, { passive: true });
        }

        // Mobile Explore Sheet Logic
        const exploreTrigger = document.getElementById('mobileExploreTrigger');
        const exploreSheet = document.getElementById('studentMobileExploreSheet');
        if (exploreTrigger && exploreSheet) {
            const openSheet = function () {
                exploreSheet.classList.add('is-open');
                exploreSheet.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            };
            const closeSheet = function () {
                exploreSheet.classList.remove('is-open');
                exploreSheet.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            };

            exploreTrigger.addEventListener('click', function (e) {
                e.preventDefault();
                openSheet();
            });

            exploreSheet.querySelectorAll('[data-sheet-close]').forEach(function (el) {
                el.addEventListener('click', closeSheet);
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && exploreSheet.classList.contains('is-open')) {
                    closeSheet();
                }
            });
        }
    });
</script>
