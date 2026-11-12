<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $pageTitle = trim($__env->yieldContent('title'));
        $brandName = config('branding.name');
        if ($pageTitle === '') {
            $pageTitle = $brandName;
        } elseif (stripos($pageTitle, $brandName) === false) {
            $pageTitle .= ' · ' . $brandName;
        }
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="application-name" content="{{ config('branding.name') }}">
    <meta name="theme-color" content="#103f32">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="MAGIREM AI">
    <script defer src="{{ asset('js/pwa-register.js') }}" data-worker-url="{{ asset('service-worker.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/pwa-install.css') }}">
    <script defer src="{{ asset('js/pwa-install.js') }}"></script>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset(config('branding.assets.favicon')) }}">
    <link rel="icon" type="image/x-icon" href="{{ asset(config('branding.assets.favicon_ico')) }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset(config('branding.assets.touch_icon')) }}">
    
    <!-- Fonts: Inter (Primary) + Plus Jakarta Sans (Supporting) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Design Tokens (Global Theme System) -->
    <link rel="stylesheet" href="{{ asset('css/design-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/branding.css') }}">
    <link rel="stylesheet" href="{{ asset('css/magirem-responsive.css') }}">

    <!-- Shared UI primitives (kept separate from the legacy global stylesheet) -->
    @vite(['resources/css/minsuanscore.css', 'resources/js/minsuanscore.js'])
    
    <style>
        /* ============================================================
           GLOBAL RESET & BASE STYLES
           ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-family-sans);
            background-color: var(--color-background);
            color: var(--color-text-primary);
            min-height: 100vh;
        }

        :root {
            --sidebar-width: 256px;
            --header-height: 72px;
        }

        /* Subtle background gradient (very low opacity) */
        body.app-layout::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(
                ellipse 1200px 600px at 50% 0%,
                rgba(35, 107, 85, 0.02) 0%,
                rgba(77, 157, 123, 0.01) 40%,
                transparent 100%
            );
            pointer-events: none;
            z-index: 1;
        }

        /* ============================================================
           LAYOUT GRID - DESKTOP & TABLET
           ============================================================ */

        body.app-layout {
            display: grid;
            grid-template-columns: var(--sidebar-width) 1fr;
            grid-template-rows: var(--header-height) 1fr;
            min-height: 100vh;
        }

        .app-sidebar {
            grid-column: 1;
            grid-row: 1 / -1;
        }

        .app-header {
            grid-column: 1 / -1;
            grid-row: 1;
        }

        .app-main {
            grid-column: 2;
            grid-row: 2;
            overflow-y: auto;
        }

        /* ============================================================
           GUEST / UNAUTHENTICATED LAYOUT
           ============================================================ */

        body.guest-layout {
            width: 100%;
            min-width: 100%;
            max-width: none;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: block !important;
            overflow-x: hidden;
            background: #F8FAF7;
        }

        body.guest-layout::before {
            display: none !important;
        }

        body.guest-layout .guest-main {
            display: block;
            width: 100%;
            min-width: 100%;
            max-width: none;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow: visible;
        }

        body.guest-layout .guest-main > * {
            width: 100%;
            max-width: none;
            min-width: 0;
        }

        body:not(:has(.app-sidebar)) {
            margin: 0;
            padding: 0;
        }

        .guest-main {
            width: 100%;
            min-width: 100%;
            max-width: none;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: block;
            overflow-x: hidden;
        }

        .guest-main > * {
            width: 100%;
            max-width: none;
        }

        /* Mobile: Responsive off-canvas drawer */
        @media (max-width: 1024px) {
            body.app-layout {
                grid-template-columns: 1fr;
                grid-template-rows: var(--header-height) 1fr;
            }

            .app-sidebar {
                grid-column: 1;
                grid-row: 1 / -1;
                position: fixed;
                left: 0;
                top: 0;
                width: var(--sidebar-width);
                height: 100vh;
                transform: translateX(-100%);
                z-index: 1050;
                transition: transform 280ms cubic-bezier(0.22, 1, 0.36, 1);
                box-shadow: var(--shadow-lg);
                overflow-y: auto;
            }

            .app-sidebar.mobile-open {
                transform: translateX(0);
            }

            .app-main {
                grid-column: 1;
            }
        }

        /* ============================================================
           SIDEBAR - PREMIUM WELLNESS SaaS DESIGN
           ============================================================ */

        .app-sidebar {
            background-color: var(--color-surface);
            border-right: 1px solid var(--color-border-light);
            display: flex;
            flex-direction: column;
            position: relative;
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            overflow: hidden;
            box-sizing: border-box;
        }

        .sidebar-brand {
            padding: 20px 16px;
            border-bottom: 1px solid var(--color-border-light);
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--color-text-primary);
            transition: background-color var(--transition-fast);
            height: auto;
            min-width: 0;
        }

        .sidebar-brand:hover {
            background-color: var(--color-surface-hover);
        }

        .sidebar-brand-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, #236B55 0%, #4D9D7B 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-white);
            font-weight: 700;
            font-size: 18px;
        }

        .sidebar-brand-text h3 {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: var(--color-text-primary);
            white-space: nowrap;
            letter-spacing: -0.01em;
            font-family: var(--font-family-sans);
        }

        .sidebar-brand-text p {
            margin: 2px 0 0 0;
            font-size: 10px;
            color: var(--color-text-tertiary);
            white-space: nowrap;
            font-weight: 500;
            font-family: var(--font-family-sans);
        }

        .sidebar-nav {
            flex: 1;
            width: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 12px 0;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        .sidebar-section {
            padding: 0;
            margin: 12px 0;
        }

        .sidebar-section:first-child {
            margin-top: 4px;
        }

        .sidebar-section-title {
            padding: 6px 20px;
            font-size: 10px;
            font-weight: 700;
            color: var(--color-text-tertiary);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            user-select: none;
            font-family: var(--font-family-sans);
        }

        .sidebar-item {
            padding: 0;
            margin: 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            margin: 0 12px;
            color: var(--color-text-secondary);
            text-decoration: none;
            border-radius: var(--radius-base);
            height: 44px;
            transition: all var(--transition-fast);
            font-size: 14px;
            font-weight: 500;
            position: relative;
            border-left: 3px solid transparent;
            font-family: var(--font-family-sans);
            width: calc(100% - 24px);
            max-width: calc(100% - 24px);
            min-width: 0;
            box-sizing: border-box;
        }

        .sidebar-link:hover {
            background-color: var(--color-surface-muted);
            color: var(--color-primary);
        }

        .sidebar-link.active {
            color: var(--color-primary);
            background-color: rgba(35, 107, 85, 0.08);
            font-weight: 600;
            border-left-color: var(--color-primary);
        }

        .sidebar-link svg {
            width: 20px;
            height: 20px;
            stroke-width: 1.5;
            flex-shrink: 0;
        }

        .sidebar-link-label {
            flex: 1;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-footer {
            padding: 12px;
            border-top: 1px solid var(--color-border-light);
            margin-top: auto;
            width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: var(--radius-base);
            cursor: pointer;
            transition: background-color var(--transition-fast);
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        .sidebar-user:hover {
            background-color: var(--color-surface-hover);
        }

        .sidebar-avatar {
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #236B55 0%, #4D9D7B 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-white);
            font-weight: 700;
            font-size: 14px;
            overflow: hidden;
            border: 2px solid var(--color-surface-muted);
        }

        .sidebar-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-user-info {
            flex: 1;
            min-width: 0;
        }

        .sidebar-user-name {
            font-size: 12px;
            font-weight: 600;
            color: var(--color-text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: var(--font-family-sans);
        }

        .sidebar-user-role {
            font-size: 10px;
            color: var(--color-text-tertiary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: var(--font-family-sans);
        }



        /* ============================================================
           ADMIN WORKSPACE GEOMETRY
           Fixes long Reports/Users pages overlapping the admin sidebar.
           Desktop: sidebar + header stay fixed in the grid, app-main scrolls.
           ============================================================ */

        body.admin-layout {
            height: 100dvh;
            min-height: 100dvh;
            overflow: hidden;

            grid-template-columns: var(--sidebar-width) minmax(0, 1fr);
            grid-template-rows: var(--header-height) minmax(0, 1fr);

            background:
                radial-gradient(circle at 92% 4%, rgba(216,235,226,.34), transparent 30rem),
                #F8FAF7;
        }

        body.admin-layout .app-sidebar {
            grid-column: 1;
            grid-row: 1 / -1;

            position: relative;
            align-self: stretch;

            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);

            height: 100dvh;
            min-height: 0;

            overflow: hidden;
            z-index: 1020;
        }

        body.admin-layout .app-header {
            grid-column: 2;
            grid-row: 1;

            position: relative;
            align-self: stretch;

            width: 100%;
            min-width: 0;
            height: var(--header-height);
            min-height: var(--header-height);

            overflow: visible;
            z-index: 1000;
        }

        body.admin-layout .app-main {
            grid-column: 2;
            grid-row: 2;

            width: 100%;
            min-width: 0;
            min-height: 0;
            height: 100%;

            margin: 0;
            padding: 0;

            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-gutter: stable;

            background: transparent;
        }

        body.admin-layout .content-wrapper {
            width: 100%;
            max-width: none;
            min-width: 0;

            margin: 0;
            padding: 0;

            overflow: visible;
        }

        body.admin-layout .admin-sidebar-nav {
            flex: 1 1 0;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        body.admin-layout .admin-sidebar-footer {
            position: relative;
            flex: 0 0 auto;
            margin-top: auto;
            z-index: 3;
        }

        @media (max-width: 1024px) {
            body.admin-layout {
                display: block;
                height: auto;
                min-height: 100dvh;
                overflow-x: hidden;
                overflow-y: auto;
                padding-top: 64px;
            }

            body.admin-layout .app-sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;

                width: min(86vw, var(--sidebar-width));
                min-width: min(86vw, var(--sidebar-width));
                max-width: min(86vw, var(--sidebar-width));
                height: 100dvh;

                transform: translateX(-100%);
                transition: transform 280ms cubic-bezier(.22,1,.36,1);
                z-index: 1050;
            }

            body.admin-layout .app-sidebar.mobile-open {
                transform: translateX(0);
            }

            body.admin-layout .app-header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;

                width: 100%;
                height: 64px;
                min-height: 64px;

                z-index: 1000;
            }

            body.admin-layout .app-main {
                width: 100%;
                min-height: calc(100dvh - 64px);
                height: auto;

                overflow: visible;
            }
        }

        /* ============================================================
           ADMIN SIDEBAR â€” FIGMA-LEVEL ROLE SHELL
           Scoped to .admin-sidebar so Student/Counselor are untouched.
           ============================================================ */

        .app-sidebar.admin-sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            height: 100vh;
            background:
                linear-gradient(180deg, rgba(230, 241, 235, 0.24) 0, rgba(255,255,255,0) 125px),
                #FFFFFF;
            border-right: 1px solid rgba(16, 63, 50, 0.09);
            overflow: hidden;
            z-index: 1020;
        }

        .admin-sidebar-brand {
            min-height: var(--header-height);
            height: var(--header-height);
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 0 17px;
            flex: 0 0 var(--header-height);
            border-bottom: 1px solid rgba(16, 63, 50, 0.08);
            text-decoration: none;
            color: #103F32;
            background: rgba(255,255,255,0.94);
            transition: background-color 160ms ease;
        }

        .admin-sidebar-brand:hover {
            background: #F8FAF7;
        }

        .admin-sidebar-brand-mark {
            width: 40px;
            height: 40px;
            min-width: 40px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: #103F32;
            box-shadow: 0 8px 18px rgba(16,63,50,.10);
        }

        .admin-sidebar-brand-mark svg {
            width: 27px;
            height: 27px;
            display: block;
        }

        .admin-sidebar-brand-copy {
            min-width: 0;
            display: flex;
            flex-direction: column;
            line-height: 1.05;
        }

        .admin-sidebar-brand-copy strong {
            overflow: hidden;
            color: #103F32;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: -0.025em;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .admin-sidebar-brand-copy small {
            margin-top: 5px;
            overflow: hidden;
            color: #789087;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .admin-sidebar .admin-sidebar-nav {
            flex: 1 1 auto;
            min-height: 0;
            width: 100%;
            padding: 14px 0 12px;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(16,63,50,.12) transparent;
        }

        .admin-sidebar .sidebar-section {
            margin: 0 0 17px;
        }

        .admin-sidebar .sidebar-section:first-child {
            margin-top: 0;
        }

        .admin-sidebar .sidebar-section-title {
            padding: 5px 20px 8px;
            color: #617B6E;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .14em;
        }

        .admin-sidebar .sidebar-item + .sidebar-item {
            margin-top: 3px;
        }

        .admin-sidebar .sidebar-link {
            width: calc(100% - 20px);
            max-width: calc(100% - 20px);
            min-height: 46px;
            height: auto;
            margin: 0 10px;
            padding: 0 12px;
            gap: 11px;
            border: 0;
            border-left: 2px solid transparent;
            border-radius: 10px;
            color: #4D6A5D;
            font-size: 13px;
            font-weight: 600;
            transition:
                background-color 150ms ease,
                color 150ms ease,
                transform 150ms ease;
        }

        .admin-sidebar .sidebar-link:hover {
            background: #F4F8F5;
            color: #103F32;
            transform: translateX(1px);
        }

        .admin-sidebar .sidebar-link.active {
            background: #EAF3EE;
            color: #103F32;
            border-left-color: #236B55;
            box-shadow: inset 0 0 0 1px rgba(35,107,85,.025);
            font-weight: 750;
        }

        .admin-sidebar .sidebar-link svg {
            width: 19px;
            height: 19px;
            stroke-width: 1.9;
            color: #6E8B82;
        }

        .admin-sidebar .sidebar-link.active svg {
            color: #174F3F;
        }

        body.admin-layout .admin-header-context {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
            color: #60796d;
            font-size: 13px;
            line-height: 1.4;
        }

        body.admin-layout .admin-header-context strong {
            overflow: hidden;
            color: #103F32;
            font-size: 14px;
            font-weight: 750;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 520px) {
            body.admin-layout .admin-header-context > span:first-child { display: none; }
            body.admin-layout .admin-header-context > span[aria-hidden] { display: none; }
        }

        .admin-sidebar-note {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: auto 10px 0;
            padding: 10px;
            border: 1px solid rgba(16,63,50,.08);
            border-radius: 11px;
            background: #F8FAF7;
        }

        .admin-sidebar-note-icon {
            width: 30px;
            height: 30px;
            min-width: 30px;
            display: grid;
            place-items: center;
            border-radius: 9px;
            background: #E6F1EB;
            color: #236B55;
        }

        .admin-sidebar-note-icon svg {
            width: 17px;
            height: 17px;
            stroke-width: 1.9;
        }

        .admin-sidebar-note > span:last-child {
            min-width: 0;
        }

        .admin-sidebar-note strong,
        .admin-sidebar-note small {
            display: block;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .admin-sidebar-note strong {
            color: #103F32;
            font-size: 11px;
            font-weight: 800;
        }

        .admin-sidebar-note small {
            margin-top: 3px;
            color: #81948A;
            font-size: 10px;
        }

        .admin-sidebar-footer {
            flex: 0 0 auto;
            padding: 10px;
            border-top: 1px solid rgba(16,63,50,.08);
            background: rgba(255,255,255,.98);
        }

        .admin-sidebar-user {
            width: 100%;
            display: grid;
            grid-template-columns: 36px minmax(0,1fr) 31px;
            gap: 9px;
            align-items: center;
            padding: 7px 8px;
            border: 1px solid rgba(16,63,50,.08);
            border-radius: 11px;
            background: #F8FAF7;
        }

        .admin-sidebar-avatar {
            width: 36px;
            height: 36px;
            min-width: 36px;
            display: grid;
            place-items: center;
            overflow: hidden;
            border-radius: 10px;
            background: #174F3F;
            color: #FFFFFF;
            font-size: 11px;
            font-weight: 800;
        }

        .admin-sidebar-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .admin-sidebar-user-copy {
            min-width: 0;
        }

        .admin-sidebar-user-copy strong,
        .admin-sidebar-user-copy small {
            display: block;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .admin-sidebar-user-copy strong {
            color: #103F32;
            font-size: 11px;
            font-weight: 800;
        }

        .admin-sidebar-user-copy small {
            margin-top: 3px;
            color: #82968E;
            font-size: 10px;
        }

        .admin-sidebar-logout-form {
            width: 31px;
            height: 31px;
            margin: 0;
        }

        .admin-sidebar-logout {
            width: 31px;
            height: 31px;
            display: grid;
            place-items: center;
            padding: 0;
            border: 1px solid rgba(185,94,87,.12);
            border-radius: 9px;
            background: #FFF7F6;
            color: #B95E57;
            cursor: pointer;
            transition: background-color 150ms ease, transform 150ms ease;
        }

        .admin-sidebar-logout:hover {
            background: #FCE9E7;
            transform: translateY(-1px);
        }

        .admin-sidebar-logout svg {
            width: 16px;
            height: 16px;
            stroke-width: 1.9;
        }

        /* Admin-only motion: navigation remains readable and usable without animation. */
        .admin-sidebar .sidebar-link {
            position: relative;
            transition: background-color 200ms ease, color 200ms ease,
                        box-shadow 200ms ease, transform 200ms cubic-bezier(.22,1,.36,1);
        }

        .admin-sidebar .sidebar-link::after {
            content: "";
            position: absolute;
            left: 0;
            top: 11px;
            bottom: 11px;
            width: 3px;
            border-radius: 3px;
            background: #D3A83F;
            transform: scaleY(0);
            transform-origin: center;
            transition: transform 200ms cubic-bezier(.22,1,.36,1);
            pointer-events: none;
        }

        .admin-sidebar .sidebar-link:hover::after,
        .admin-sidebar .sidebar-link:focus-visible::after,
        .admin-sidebar .sidebar-link.active::after {
            transform: scaleY(1);
        }

        .admin-sidebar .sidebar-link svg {
            transition: color 200ms ease, transform 200ms cubic-bezier(.22,1,.36,1);
        }

        .admin-sidebar .sidebar-link:hover svg,
        .admin-sidebar .sidebar-link:focus-visible svg {
            color: #174F3F;
            transform: translateX(2px) scale(1.05);
        }

        .admin-sidebar-brand-mark {
            transition: transform 220ms cubic-bezier(.22,1,.36,1), box-shadow 220ms ease;
        }

        .admin-sidebar-brand:hover .admin-sidebar-brand-mark,
        .admin-sidebar-brand:focus-visible .admin-sidebar-brand-mark {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16,63,50,.16);
        }

        .admin-sidebar .sidebar-link:focus-visible,
        .admin-sidebar-brand:focus-visible,
        .admin-sidebar-logout:focus-visible {
            outline: 2px solid #B48624;
            outline-offset: 2px;
        }

        @keyframes adminSidebarReveal {
            from { opacity: 0; transform: translateX(-8px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @media (min-width: 1025px) and (prefers-reduced-motion: no-preference) {
            body.admin-layout .admin-sidebar-brand,
            body.admin-layout .admin-sidebar .sidebar-item,
            body.admin-layout .admin-sidebar-note,
            body.admin-layout .admin-sidebar-footer {
                animation: adminSidebarReveal 360ms cubic-bezier(.22,1,.36,1) backwards;
            }
            body.admin-layout .admin-sidebar .sidebar-item:nth-of-type(2) { animation-delay: 40ms; }
            body.admin-layout .admin-sidebar .sidebar-item:nth-of-type(3) { animation-delay: 75ms; }
            body.admin-layout .admin-sidebar .sidebar-item:nth-of-type(4) { animation-delay: 110ms; }
            body.admin-layout .admin-sidebar-note,
            body.admin-layout .admin-sidebar-footer { animation-delay: 120ms; }
        }

        @media (max-width: 1024px) {
            .app-sidebar.admin-sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                height: 100dvh;
                z-index: 1050;
            }

            /* The header account menu provides account details and logout on mobile. */
            body.admin-layout .admin-sidebar-footer {
                display: none;
            }

            body.admin-layout .app-sidebar:not(.mobile-open) {
                visibility: hidden;
                transition: transform 280ms cubic-bezier(.22,1,.36,1), visibility 0s linear 280ms;
            }

            body.admin-layout .app-sidebar.mobile-open {
                visibility: visible;
                transition: transform 280ms cubic-bezier(.22,1,.36,1), visibility 0s;
            }

            body.admin-layout .sidebar-overlay {
                display: block;
                visibility: hidden;
                opacity: 0;
                pointer-events: none;
                animation: none;
                transition: opacity 240ms ease, visibility 0s linear 240ms;
            }

            body.admin-layout .sidebar-overlay.active {
                visibility: visible;
                opacity: 1;
                pointer-events: auto;
                transition: opacity 240ms ease, visibility 0s;
            }
        }

        @media (max-width: 1024px) and (prefers-reduced-motion: no-preference) {
            body.admin-layout .admin-sidebar.mobile-open .sidebar-item {
                animation: adminSidebarReveal 320ms cubic-bezier(.22,1,.36,1) backwards;
            }
            body.admin-layout .admin-sidebar.mobile-open .sidebar-item:nth-of-type(2) { animation-delay: 40ms; }
            body.admin-layout .admin-sidebar.mobile-open .sidebar-item:nth-of-type(3) { animation-delay: 75ms; }
            body.admin-layout .admin-sidebar.mobile-open .sidebar-item:nth-of-type(4) { animation-delay: 110ms; }
        }

        @media (prefers-reduced-motion: reduce) {
            body.admin-layout .app-sidebar,
            body.admin-layout .sidebar-overlay,
            body.admin-layout .admin-sidebar-brand,
            body.admin-layout .admin-sidebar-brand-mark,
            body.admin-layout .admin-sidebar .sidebar-item,
            body.admin-layout .admin-sidebar .sidebar-link,
            body.admin-layout .admin-sidebar .sidebar-link::after,
            body.admin-layout .admin-sidebar .sidebar-link svg,
            body.admin-layout .admin-sidebar-note,
            body.admin-layout .admin-sidebar-footer,
            body.admin-layout .admin-sidebar-logout {
                animation: none !important;
                transition: none !important;
            }

            body.admin-layout .admin-sidebar .sidebar-link:hover,
            body.admin-layout .admin-sidebar .sidebar-link:hover svg,
            body.admin-layout .admin-sidebar .sidebar-link:focus-visible svg,
            body.admin-layout .admin-sidebar-brand:hover .admin-sidebar-brand-mark,
            body.admin-layout .admin-sidebar-brand:focus-visible .admin-sidebar-brand-mark,
            body.admin-layout .admin-sidebar-logout:hover {
                transform: none;
            }
        }


        /* ============================================================
           HEADER - CLEAN HORIZONTAL BAR
           ============================================================ */

        .app-header {
            background-color: var(--color-surface);
            border-bottom: 1px solid var(--color-border-light);
            padding: 0 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-shrink: 0;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
            min-width: 0;
        }

        .header-mobile-menu {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--color-text-primary);
            padding: 8px;
            width: 44px;
            height: 44px;
            border-radius: var(--radius-base);
            transition: background-color var(--transition-fast);
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .header-mobile-menu:hover {
            background-color: var(--color-surface-hover);
        }

        .header-mobile-menu svg {
            width: 24px;
            height: 24px;
            stroke-width: 2;
        }

        @media (max-width: 1024px) {
            .header-mobile-menu {
                display: flex;
            }
        }

        .header-title {
            flex: 1;
            min-width: 0;
        }

        .header-title h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--color-text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            letter-spacing: -0.01em;
            font-family: var(--font-family-sans);
        }

        .header-title p {
            margin: 2px 0 0 0;
            font-size: 12px;
            color: var(--color-text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: var(--font-family-sans);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .header-button {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--color-text-primary);
            padding: 8px;
            width: 44px;
            height: 44px;
            border-radius: var(--radius-base);
            transition: background-color var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .header-button:hover {
            background-color: var(--color-surface-hover);
        }

        .header-button:focus-visible {
            outline: none;
            box-shadow: var(--shadow-focus);
        }

        .header-button svg {
            width: 20px;
            height: 20px;
            stroke-width: 1.5;
        }

        .notification-badge {
            position: absolute;
            top: 6px;
            right: 6px;
            background-color: var(--color-danger);
            color: var(--color-white);
            font-size: 9px;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: var(--radius-full);
            min-width: 18px;
            text-align: center;
            box-shadow: var(--shadow-sm);
        }

        .header-user-menu {
            position: relative;
        }

        .header-user-button {
            display: flex;
            align-items: center;
            gap: 8px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--color-text-primary);
            padding: 6px;
            border-radius: var(--radius-base);
            transition: background-color var(--transition-fast);
            height: 44px;
        }

        .header-user-button:hover {
            background-color: var(--color-surface-hover);
        }

        .header-user-button:focus-visible {
            outline: none;
            box-shadow: var(--shadow-focus);
        }

        .header-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #236B55 0%, #4D9D7B 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-white);
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid var(--color-surface-muted);
        }

        .header-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background-color: var(--color-surface);
            border: 1px solid var(--color-border-base);
            border-radius: var(--radius-lg);
            min-width: 220px;
            box-shadow: var(--shadow-lg);
            display: none;
            flex-direction: column;
            z-index: 1001;
            overflow: hidden;
            animation: slideDown 220ms cubic-bezier(0.22, 1, 0.36, 1);
        }

        .user-dropdown-menu.active {
            display: flex;
        }

        .user-dropdown-header {
            padding: 16px;
            border-bottom: 1px solid var(--color-border-light);
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .user-dropdown-header-info {
            flex: 1;
        }

        .user-dropdown-name {
            font-weight: 600;
            color: var(--color-text-primary);
            font-size: 13px;
            font-family: var(--font-family-sans);
        }

        .user-dropdown-role {
            font-size: 11px;
            color: var(--color-text-secondary);
            margin-top: 2px;
            font-family: var(--font-family-sans);
        }

        .user-dropdown-item {
            padding: 12px 16px;
            color: var(--color-text-primary);
            text-decoration: none;
            transition: background-color var(--transition-fast);
            border: none;
            background: none;
            cursor: pointer;
            width: 100%;
            text-align: left;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-family-sans);
        }

        .user-dropdown-item:hover {
            background-color: var(--color-surface-hover);
            color: var(--color-primary);
        }

        .user-dropdown-logout {
            padding: 12px 16px;
            color: var(--color-danger);
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-size: 13px;
            transition: all var(--transition-fast);
            border-top: 1px solid var(--color-border-light);
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-family-sans);
        }

        .user-dropdown-logout:hover {
            background-color: var(--color-danger);
            color: var(--color-white);
        }

        /* Compact account-menu icons.
           Without explicit SVG dimensions the browser can render the logout
           icon at its intrinsic replaced-element size, making the menu huge. */
        .user-dropdown-item svg,
        .user-dropdown-logout svg {
            width: 16px !important;
            height: 16px !important;
            min-width: 16px !important;
            max-width: 16px !important;
            min-height: 16px !important;
            max-height: 16px !important;
            flex: 0 0 16px !important;
            display: block !important;
            stroke-width: 1.8;
        }

        .user-dropdown-logout {
            min-height: 42px;
            padding: 10px 14px;
            justify-content: flex-start;
            line-height: 1;
        }

        .user-dropdown-menu form {
            margin: 0;
        }

        /* ============================================================
           NOTIFICATION PANEL
           ============================================================ */

        .notification-panel {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background-color: var(--color-surface);
            border-left: 1px solid var(--color-border-base);
            box-shadow: var(--shadow-xl);
            z-index: 1000;
            transition: right 280ms cubic-bezier(0.22, 1, 0.36, 1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .notification-panel.active {
            right: 0;
        }

        .notification-panel-header {
            padding: 20px;
            border-bottom: 1px solid var(--color-border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .notification-panel-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--color-text-primary);
            font-family: var(--font-family-sans);
        }

        .notification-count-text {
            font-size: 11px;
            color: var(--color-text-secondary);
            font-weight: 500;
            display: block;
            margin-top: 4px;
            font-family: var(--font-family-sans);
        }

        .notification-panel-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--color-text-secondary);
            transition: all var(--transition-fast);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-base);
        }

        .notification-panel-close:hover {
            background-color: var(--color-surface-hover);
            color: var(--color-text-primary);
        }

        .notification-panel-content {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }

        .notification-item {
            display: flex;
            gap: 12px;
            padding: 12px;
            background-color: var(--color-surface-hover);
            border: 1px solid var(--color-border-light);
            border-radius: var(--radius-base);
            margin-bottom: 12px;
            transition: all var(--transition-fast);
            cursor: pointer;
        }

        .notification-item:hover {
            background-color: var(--color-surface-muted);
            border-color: var(--color-border-base);
        }

        .notification-item-icon {
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: var(--radius-base);
            background-color: rgba(35, 107, 85, 0.1);
            color: var(--color-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notification-item-icon svg {
            width: 18px;
            height: 18px;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-content h4 {
            margin: 0 0 4px 0;
            font-size: 13px;
            font-weight: 600;
            color: var(--color-text-primary);
            font-family: var(--font-family-sans);
        }

        .notification-content p {
            margin: 0 0 8px 0;
            font-size: 12px;
            color: var(--color-text-secondary);
            line-height: 1.5;
            font-family: var(--font-family-sans);
        }

        .notification-time {
            font-size: 11px;
            color: var(--color-text-tertiary);
            font-family: var(--font-family-sans);
        }

        .notification-delete-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--color-text-tertiary);
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: var(--radius-base);
            transition: all var(--transition-fast);
            flex-shrink: 0;
        }

        .notification-delete-btn:hover {
            background-color: var(--color-danger-light);
            color: var(--color-danger);
        }

        .notification-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 20px;
            text-align: center;
        }

        .notification-empty svg {
            opacity: 0.4;
            margin-bottom: 16px;
            width: 48px;
            height: 48px;
        }

        .notification-empty p {
            margin: 0;
            color: var(--color-text-primary);
            font-size: 13px;
            font-weight: 600;
            font-family: var(--font-family-sans);
        }

        .notification-empty span {
            color: var(--color-text-secondary);
            font-size: 12px;
            display: block;
            margin-top: 8px;
            font-family: var(--font-family-sans);
        }

        /* ============================================================
           MAIN CONTENT AREA
           ============================================================ */

        .app-main {
            padding: 32px;
            background-color: var(--color-background);
            overflow-y: auto;
        }

        .content-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            animation: fadeIn 300ms ease-out;
        }

        /* ============================================================
           MOBILE OVERLAY
           ============================================================ */

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 1040;
            animation: fadeIn 200ms ease-out;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* ============================================================
           RESPONSIVE DESIGN BREAKPOINTS
           ============================================================ */

        @media (max-width: 1024px) {
            .app-main {
                padding: 24px 20px;
            }
        }

        @media (max-width: 768px) {
            :root {
                --header-height: 64px;
            }

            .app-main {
                padding: 16px;
            }

            .app-header {
                padding: 0 16px;
            }

            .header-title h1 {
                font-size: 16px;
            }

            .header-title p {
                display: none;
            }

            .header-right {
                gap: 4px;
            }

            .notification-panel {
                width: 100%;
                right: -100%;
            }

            .sidebar-user-name {
                display: none;
            }
        }

        @media (max-width: 640px) {
            :root {
                --header-height: 60px;
            }

            .app-header {
                padding: 0 12px;
            }

            .header-left {
                gap: 8px;
            }

            .sidebar-brand-text h3 {
                display: none;
            }

            .sidebar-brand-text p {
                display: none;
            }
        }

        /* ============================================================
           MODAL STYLING
           ============================================================ */

        .modal {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            display: none;
            align-items: center;
            justify-content: center;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 2000;
            animation: fadeIn 200ms ease-out;
            padding: 16px;
            visibility: hidden;
            opacity: 0;
            pointer-events: none;
            transition: opacity 200ms ease, visibility 200ms ease;
        }

        .modal.active,
        .modal.show {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        .modal-content {
            background-color: var(--color-surface);
            border: 1px solid var(--color-border-base);
            border-radius: var(--radius-2xl);
            padding: 32px;
            max-width: 500px;
            width: 100%;
            box-shadow: var(--shadow-xl);
            animation: slideUp 300ms cubic-bezier(0.22, 1, 0.36, 1);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--color-border-light);
        }

        .modal-header h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
            color: var(--color-text-primary);
            font-family: var(--font-family-sans);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--color-text-secondary);
            transition: all var(--transition-fast);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-base);
        }

        .modal-close:hover {
            background-color: var(--color-surface-hover);
            color: var(--color-text-primary);
        }

        /* ============================================================
           PROFESSIONAL PROFILE MODAL STYLING
           ============================================================ */

        .profile-modal-content {
            background: #ffffff;
            border: 1px solid rgba(16, 63, 50, 0.15);
            border-radius: 18px;
            padding: 0;
            max-width: 480px;
            box-shadow: 0 20px 60px rgba(16, 63, 50, 0.12);
            overflow: hidden;
        }

        .profile-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 28px;
            border-bottom: 1px solid rgba(16, 63, 50, 0.1);
            background: linear-gradient(135deg, rgba(16, 63, 50, 0.02), rgba(35, 107, 85, 0.02));
        }

        .profile-modal-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #103F32;
            letter-spacing: -0.3px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #103F32;
            transition: all 0.2s ease;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            opacity: 0.6;
        }

        .modal-close:hover {
            background-color: rgba(16, 63, 50, 0.08);
            opacity: 1;
        }

        .profile-form {
            padding: 0;
        }

        .profile-modal-errors,
        .profile-modal-success {
            padding: 16px 28px;
            border-radius: 0;
            border-bottom: 1px solid rgba(16, 63, 50, 0.1);
        }

        .profile-modal-errors {
            background-color: rgba(239, 68, 68, 0.08);
        }

        .error-message {
            color: #DC2626;
            font-size: 13px;
            margin: 4px 0;
        }

        .profile-modal-success {
            background-color: rgba(16, 185, 129, 0.08);
            color: #059669;
            font-size: 13px;
            font-weight: 500;
        }

        /* Profile Photo Section */
        .profile-photo-section {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 28px;
            background: linear-gradient(135deg, rgba(230, 241, 235, 0.5), rgba(255, 255, 255, 0.8));
            border-bottom: 1px solid rgba(16, 63, 50, 0.1);
        }

        .profile-photo-wrapper {
            position: relative;
            flex-shrink: 0;
        }

        .profile-photo {
            width: 100px;
            height: 100px;
            border-radius: 14px;
            background: linear-gradient(135deg, #236B55, #103F32);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 44px;
            font-weight: 700;
            color: white;
            overflow: hidden;
            border: 2px solid rgba(16, 63, 50, 0.1);
        }

        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-photo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
        }

        .photo-upload-btn {
            position: absolute;
            bottom: -8px;
            right: -8px;
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #236B55, #103F32);
            border: 3px solid #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(16, 63, 50, 0.2);
        }

        .photo-upload-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(16, 63, 50, 0.3);
        }

        .photo-upload-btn svg {
            width: 20px;
            height: 20px;
            color: white;
            stroke-width: 2.5;
        }

        .profile-photo-info {
            flex: 1;
        }

        .profile-photo-name {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #103F32;
            letter-spacing: -0.2px;
        }

        .profile-photo-role {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: rgba(16, 63, 50, 0.6);
            text-transform: capitalize;
        }

        .profile-divider {
            height: 1px;
            background: rgba(16, 63, 50, 0.08);
        }

        /* Form Section */
        .profile-section {
            padding: 24px 28px;
        }

        .profile-section-title {
            margin: 0 0 18px 0;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: rgba(16, 63, 50, 0.5);
        }

        .profile-form-group {
            margin-bottom: 18px;
        }

        .profile-form-group:last-child {
            margin-bottom: 0;
        }

        .profile-form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #103F32;
            margin-bottom: 8px;
        }

        .profile-form-group input,
        .profile-form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid rgba(16, 63, 50, 0.15);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            color: #103F32;
            transition: all 0.2s ease;
            background: #ffffff;
        }

        .profile-form-group input:focus,
        .profile-form-group textarea:focus {
            outline: none;
            border-color: #236B55;
            box-shadow: 0 0 0 3px rgba(35, 107, 85, 0.1);
        }

        .profile-form-group textarea {
            resize: vertical;
            min-height: 90px;
        }

        .form-help-text {
            display: block;
            font-size: 12px;
            color: rgba(16, 63, 50, 0.6);
            margin-top: 6px;
        }

        /* Action Buttons */
        .profile-actions {
            display: flex;
            gap: 12px;
            padding: 20px 28px;
            border-top: 1px solid rgba(16, 63, 50, 0.08);
        }

        .btn-save,
        .btn-cancel,
        .btn-logout {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .btn-save {
            background: #103F32;
            color: white;
            box-shadow: 0 4px 12px rgba(16, 63, 50, 0.2);
        }

        .btn-save:hover {
            background: #0a2818;
            box-shadow: 0 6px 16px rgba(16, 63, 50, 0.3);
            transform: translateY(-1px);
        }

        .btn-save:active {
            transform: translateY(0);
        }

        .btn-cancel {
            background: transparent;
            color: #103F32;
            border: 1px solid rgba(16, 63, 50, 0.2);
        }

        .btn-cancel:hover {
            background: rgba(16, 63, 50, 0.05);
            border-color: rgba(16, 63, 50, 0.3);
        }

        .btn-logout {
            width: 100%;
            padding: 14px 20px;
            background: transparent;
            color: #DC2626;
            border: 1px solid rgba(220, 38, 38, 0.3);
            margin-top: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-logout:hover {
            background: rgba(220, 38, 38, 0.08);
            border-color: rgba(220, 38, 38, 0.5);
        }

        /* ============================================================
           PROFILE SECTION
           ============================================================ */

        .profile-avatar-section {
            text-align: center;
            margin-bottom: 32px;
            position: relative;
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #236B55 0%, #4D9D7B 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: var(--color-white);
            margin: 0 auto 20px;
            border: 4px solid var(--color-surface-muted);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            transform: translate(8px, 8px);
            background-color: var(--color-primary);
            border: 4px solid var(--color-surface);
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-fast);
            box-shadow: var(--shadow-md);
        }

        .profile-avatar-upload:hover {
            background-color: #1A4D3E;
            transform: translate(8px, 8px) scale(1.1);
        }

        .profile-avatar-upload input {
            display: none;
        }

        .profile-avatar-upload svg {
            width: 20px;
            height: 20px;
            color: var(--color-white);
            stroke-width: 1.5;
        }

        .profile-avatar-section p {
            margin: 0;
            color: var(--color-text-secondary);
            font-size: 13px;
            font-weight: 500;
            font-family: var(--font-family-sans);
        }

        /* ============================================================
           FORM STYLING
           ============================================================ */

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--color-text-primary);
            font-weight: 600;
            font-size: 13px;
            font-family: var(--font-family-sans);
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--color-border-base);
            border-radius: var(--radius-base);
            transition: all var(--transition-fast);
            background-color: var(--color-surface);
            color: var(--color-text-primary);
            font-family: var(--font-family-sans);
            font-size: 14px;
        }

        input:hover,
        textarea:hover,
        select:hover {
            border-color: var(--color-border-medium);
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: var(--shadow-focus);
        }

        input::placeholder,
        textarea::placeholder {
            color: var(--color-text-tertiary);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        input:invalid,
        textarea:invalid,
        select:invalid {
            border-color: var(--color-danger);
        }

        /* ============================================================
           ANIMATIONS & KEYFRAMES
           ============================================================ */

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ============================================================
           SCROLLBAR STYLING
           ============================================================ */

        .app-sidebar::-webkit-scrollbar,
        .app-main::-webkit-scrollbar,
        .notification-panel-content::-webkit-scrollbar {
            width: 8px;
        }

        .app-sidebar::-webkit-scrollbar-track,
        .app-main::-webkit-scrollbar-track,
        .notification-panel-content::-webkit-scrollbar-track {
            background-color: transparent;
        }

        .app-sidebar::-webkit-scrollbar-thumb,
        .app-main::-webkit-scrollbar-thumb,
        .notification-panel-content::-webkit-scrollbar-thumb {
            background-color: var(--color-border-base);
            border-radius: 4px;
        }

        .app-sidebar::-webkit-scrollbar-thumb:hover,
        .app-main::-webkit-scrollbar-thumb:hover,
        .notification-panel-content::-webkit-scrollbar-thumb:hover {
            background-color: var(--color-border-medium);
        }

        * {
            scrollbar-color: var(--color-border-base) transparent;
            scrollbar-width: thin;
        }

        /* ============================================================
           ACCOUNT POPOVER STYLING
           ============================================================ */
        .account-popover {
            position: fixed;
            width: 270px;
            padding: 8px;
            border: 1px solid rgba(16, 63, 50, 0.10);
            border-radius: 16px;
            background: #FFFFFF;
            box-shadow: 0 18px 45px rgba(16, 63, 50, 0.14);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: translateY(6px) scale(0.98);
            transition: opacity 0.16s ease, transform 0.16s ease, visibility 0.16s ease;
            z-index: 10000;
        }

        .account-popover.is-open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transform: translateY(0) scale(1);
        }

        .account-popover-item {
            width: 100%;
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr) 16px;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border: 0;
            border-radius: 12px;
            background: transparent;
            text-align: left;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.18s ease;
        }

        .account-popover-item:hover {
            background: #F3F8F5;
        }

        .account-popover-item-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: #E6F1EB;
            color: #103F32;
            flex-shrink: 0;
        }

        .account-popover-item-icon svg {
            width: 18px;
            height: 18px;
        }

        .account-popover-item-text {
            min-width: 0;
        }

        .account-popover-item-text strong {
            display: block;
            color: #103F32;
            font-size: 12px;
            font-weight: 750;
        }

        .account-popover-item-text small {
            display: block;
            margin-top: 3px;
            color: #809087;
            font-size: 10px;
            line-height: 1.4;
        }

        .account-popover-chevron {
            width: 14px;
            height: 14px;
            color: #90A098;
            flex-shrink: 0;
        }

        .account-popover-separator {
            height: 1px;
            margin: 4px 0;
            background: rgba(16, 63, 50, 0.08);
        }

        .account-popover-logout {
            grid-template-columns: 38px minmax(0, 1fr);
            color: #9E3C3C;
        }

        .account-popover-logout .account-popover-item-icon {
            background: #FFF1F0;
            color: #A64444;
        }

        .account-popover-logout .account-popover-item-text strong {
            color: #A64444;
        }

        .account-popover-form {
            width: 100%;
            margin: 0;
            padding: 0;
            border: 0;
        }

        /* Active state for trigger button */
        button[data-account-menu-open].is-account-open {
            background: #E6F1EB;
        }

        /* ============================================================
           COUNSELOR SHELL â€” PROFESSIONAL WORKSPACE
           Scoped only to authenticated counselor pages.
           ============================================================ */
        body.counselor-layout {
            --sidebar-width: 272px;
            --header-height: 72px;

            /* Lock the counselor workspace to the viewport.
               Only .app-main scrolls; the sidebar/header stay stable. */
            height: 100dvh;
            min-height: 100dvh;
            overflow: hidden;

            grid-template-columns: var(--sidebar-width) minmax(0, 1fr);
            grid-template-rows: var(--header-height) minmax(0, 1fr);

            background:
                radial-gradient(circle at 92% 4%, rgba(216, 235, 226, 0.42), transparent 30rem),
                #F8FAF7;
        }

        body.counselor-layout::before {
            background:
                radial-gradient(circle at 84% 0%, rgba(35, 107, 85, 0.045), transparent 38rem);
        }

        body.counselor-layout .app-sidebar {
            grid-column: 1;
            grid-row: 1 / -1;
            position: relative;
            align-self: stretch;

            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);

            height: 100dvh;
            min-height: 0;

            background: rgba(255, 255, 255, 0.98);
            border-right: 1px solid rgba(16, 63, 50, 0.08);
            box-shadow: 12px 0 34px rgba(16, 63, 50, 0.025);

            overflow: hidden;
            z-index: 100;
        }

        body.counselor-layout .app-header {
            grid-column: 2;
            grid-row: 1;
            position: relative;
            align-self: stretch;

            width: 100%;
            min-width: 0;
            height: var(--header-height);
            min-height: var(--header-height);

            padding: 0 26px;

            background: rgba(255, 255, 255, 0.94);
            border-bottom: 1px solid rgba(16, 63, 50, 0.08);
            box-shadow: 0 8px 28px rgba(16, 63, 50, 0.025);
            backdrop-filter: blur(16px);

            overflow: visible;
            z-index: 90;
        }

        body.counselor-layout .app-main {
            grid-column: 2;
            grid-row: 2;

            width: 100%;
            min-width: 0;
            min-height: 0;
            height: 100%;

            margin: 0;
            padding: 0;

            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-gutter: stable;

            background: transparent;
        }

        body.counselor-layout .content-wrapper {
            width: 100%;
            max-width: none;
            min-width: 0;
            margin: 0;
            padding: 0;
        }

        .counselor-brand {
            flex: 0 0 88px;
            min-height: 88px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 18px;
            color: #103F32;
            text-decoration: none;
            background: #FFFFFF;
            border-bottom: 1px solid rgba(16, 63, 50, 0.08);
        }

        .counselor-brand:hover {
            background: #FBFCFB;
        }

        .counselor-brand-mark {
            width: 46px;
            height: 46px;
            min-width: 46px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: #103F32;
            box-shadow: 0 10px 22px rgba(16, 63, 50, 0.13);
        }

        .counselor-brand-mark svg {
            width: 27px;
            height: 27px;
        }

        .counselor-brand-copy {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .counselor-brand-copy strong {
            overflow: hidden;
            color: #103F32;
            font-size: 16px;
            line-height: 1.05;
            font-weight: 750;
            letter-spacing: -0.35px;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .counselor-brand-copy small {
            margin-top: 6px;
            overflow: hidden;
            color: #71837A;
            font-size: 8px;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 1.7px;
            text-transform: uppercase;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        body.counselor-layout .sidebar-nav {
            flex: 1 1 0;
            min-height: 0;
            width: 100%;

            padding: 16px 0 18px;

            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        body.counselor-layout .sidebar-section {
            margin: 0 0 18px;
        }

        body.counselor-layout .sidebar-section:last-child {
            margin-bottom: 0;
        }

        body.counselor-layout .sidebar-section-title {
            padding: 0 20px;
            margin: 0 0 8px;
            color: #8AA099;
            font-size: 9px;
            line-height: 1;
            font-weight: 750;
            letter-spacing: 0.13em;
        }

        body.counselor-layout .sidebar-link {
            width: calc(100% - 20px);
            max-width: calc(100% - 20px);
            height: 46px;
            min-height: 46px;
            margin: 2px 10px;
            padding: 0 13px;
            gap: 12px;
            border: 1px solid transparent;
            border-left: 0;
            border-radius: 11px;
            color: #68848C;
            background: transparent;
            font-size: 13px;
            font-weight: 600;
            overflow: hidden;
            transition:
                background-color 170ms ease,
                border-color 170ms ease,
                color 170ms ease,
                transform 170ms ease;
        }

        body.counselor-layout .sidebar-link:hover {
            background: #F5F8F6;
            border-color: rgba(16, 63, 50, 0.055);
            color: #103F32;
            transform: translateX(1px);
        }

        body.counselor-layout .sidebar-link.active {
            background: linear-gradient(90deg, #E6F1EB 0%, #EFF6F2 100%);
            border-color: rgba(16, 63, 50, 0.05);
            color: #103F32;
            font-weight: 700;
        }

        body.counselor-layout .sidebar-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 8px;
            bottom: 8px;
            width: 3px;
            border-radius: 0 999px 999px 0;
            background: #103F32;
        }

        body.counselor-layout .sidebar-link svg {
            width: 19px;
            height: 19px;
            min-width: 19px;
            stroke-width: 1.8;
        }

        .counselor-sidebar-footer {
            position: relative;
            flex: 0 0 auto;

            width: 100%;
            margin-top: auto;
            padding: 10px 12px 12px;

            border-top: 1px solid rgba(16, 63, 50, 0.08);
            background: #FFFFFF;

            z-index: 2;
        }

        .counselor-sidebar-user {
            width: 100%;
            min-height: 60px;
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr);
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border: 1px solid rgba(16, 63, 50, 0.07);
            border-radius: 13px;
            background: #F8FAF7;
        }

        .counselor-sidebar-avatar {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            overflow: hidden;
            border-radius: 11px;
            background: #103F32;
            color: #FFFFFF;
            font-size: 12px;
            font-weight: 750;
        }

        .counselor-sidebar-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .counselor-sidebar-user-copy {
            min-width: 0;
        }

        .counselor-sidebar-user-copy strong,
        .counselor-sidebar-user-copy span {
            display: block;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .counselor-sidebar-user-copy strong {
            color: #103F32;
            font-size: 11px;
            font-weight: 700;
        }

        .counselor-sidebar-user-copy span {
            margin-top: 3px;
            color: #7D8F86;
            font-size: 9px;
        }

        .counselor-header-context {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .counselor-header-kicker {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #7D8F86;
            font-size: 9px;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .counselor-header-kicker strong {
            color: #236B55;
            font-weight: 750;
        }

        .counselor-header-current {
            margin-top: 5px;
            color: #103F32;
            font-size: 15px;
            line-height: 1.05;
            font-weight: 750;
            letter-spacing: -0.2px;
        }

        .counselor-status-chip {
            height: 30px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 0 10px;
            border: 1px solid rgba(35, 107, 85, 0.10);
            border-radius: 999px;
            background: #F2F8F5;
            color: #103F32;
            font-size: 10px;
            font-weight: 650;
        }

        .counselor-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #36A36F;
            box-shadow: 0 0 0 3px rgba(54, 163, 111, 0.10);
        }

        body.counselor-layout .header-right {
            gap: 10px;
        }

        body.counselor-layout .header-user-button {
            min-width: 44px;
            padding: 5px 7px;
            gap: 9px;
            border: 1px solid transparent;
        }

        body.counselor-layout .header-user-button:hover {
            border-color: rgba(16, 63, 50, 0.08);
            background: #F5F8F6;
        }

        body.counselor-layout .header-user-avatar {
            width: 34px;
            height: 34px;
            min-width: 34px;
            border: 2px solid #E6F1EB;
            background: #236B55;
        }

        .counselor-header-user-copy {
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
        }

        .counselor-header-user-copy strong {
            max-width: 145px;
            overflow: hidden;
            color: #103F32;
            font-size: 11px;
            line-height: 1.1;
            font-weight: 700;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .counselor-header-user-copy small {
            margin-top: 3px;
            color: #81938A;
            font-size: 8px;
            line-height: 1;
        }

        .counselor-header-chevron {
            width: 14px;
            height: 14px;
            color: #81938A;
        }

        body.counselor-layout .user-dropdown-menu {
            min-width: 240px;
            border: 1px solid rgba(16, 63, 50, 0.10);
            border-radius: 14px;
            box-shadow: 0 18px 48px rgba(16, 63, 50, 0.13);
        }

        @media (max-width: 1024px) {
            body.counselor-layout {
                display: block;
                height: auto;
                min-height: 100dvh;
                overflow-x: hidden;
                overflow-y: auto;
                padding-top: 62px;
            }

            body.counselor-layout .app-sidebar {
                position: fixed;
                left: 0;
                top: 0;
                width: min(86vw, 272px);
                min-width: min(86vw, 272px);
                max-width: min(86vw, 272px);
                height: 100dvh;
                transform: translateX(-100%);
                transition: transform 280ms cubic-bezier(0.22, 1, 0.36, 1);
                z-index: 1050;
            }

            body.counselor-layout .app-sidebar.mobile-open {
                transform: translateX(0);
            }

            body.counselor-layout .app-header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                width: 100%;
                height: 62px;
                min-height: 62px;
                padding: 0 14px;
                z-index: 1000;
            }

            body.counselor-layout .app-main {
                width: 100%;
                min-height: calc(100dvh - 62px);
                height: auto;
                overflow: visible;
            }
        }

        @media (max-width: 720px) {
            .counselor-status-chip,
            .counselor-header-user-copy,
            .counselor-header-chevron {
                display: none;
            }

            .counselor-header-kicker {
                display: none;
            }

            .counselor-header-current {
                margin-top: 0;
                font-size: 13px;
            }
        }
    </style>
    @stack('head')
</head>
@php
    // Student shell appears only inside student application routes.
    $showStudentShell = auth()->check()
        && auth()->user()->isStudent()
        && request()->routeIs('student.*');

    // Account status is an authenticated, full-width workspace. It is not a
    // guest page, but it does not need the student sidebar/header shell.
    $showAccountStatusShell = auth()->check()
        && request()->routeIs('account.status');

    // Insights is the only student workspace that retains the analytics sidebar.
    $showStudentInsightsSidebar = $showStudentShell && request()->routeIs('student.insights');
    // AI Chat has its own focused workspace header and closed-by-default history drawer.
    $showStudentHeader = ($showStudentShell
        && !$showStudentInsightsSidebar
        && !request()->routeIs('student.ai-chat'))
        || (auth()->check() && auth()->user()->isStudent() && request()->routeIs('home'));

    // Counselor/admin shell flags keep each role scoped to its own workspace.
    $showCounselorShell = auth()->check()
        && auth()->user()->isCounselor()
        && (request()->routeIs('counselor.*') || request()->routeIs('student.community*'));

    $showAdminShell = auth()->check()
        && auth()->user()->isAdmin()
        && (request()->routeIs('admin.*') || request()->routeIs('student.community*'));

    // Route-aware counselor header copy.
    $counselorHeaderTitle = 'Counselor Workspace';
    $counselorHeaderSection = 'Workspace';

    if ($showCounselorShell) {
        if (request()->routeIs('counselor.dashboard')) {
            $counselorHeaderTitle = 'Dashboard';
            $counselorHeaderSection = 'Overview';
        } elseif (request()->routeIs('counselor.students', 'counselor.student-profile')) {
            $counselorHeaderTitle = 'Students';
            $counselorHeaderSection = 'Student Care';
        } elseif (request()->routeIs('counselor.messages', 'counselor.chat')) {
            $counselorHeaderTitle = 'Messages';
            $counselorHeaderSection = 'Communication';
        } elseif (request()->routeIs('counselor.appointments*')) {
            $counselorHeaderTitle = 'Appointments';
            $counselorHeaderSection = 'Schedule';
        } elseif (request()->routeIs('student.community*')) {
            $counselorHeaderTitle = 'Community Circle';
            $counselorHeaderSection = 'Shared space';
        }
    }

    // Home keeps its public landing treatment, while signed-in students use the shared top header.
    if ($showAccountStatusShell) {
        $bodyClass = 'account-status-layout';
    } elseif (request()->routeIs('home')) {
        $bodyClass = (auth()->check() && auth()->user()->isStudent())
            ? 'student-header-layout'
            : 'guest-layout';
    } elseif ($showStudentShell) {
        $bodyClass = $showStudentInsightsSidebar ? 'app-layout' : 'student-header-layout';
    } elseif ($showCounselorShell) {
        $bodyClass = 'app-layout counselor-layout';
    } elseif ($showAdminShell) {
        $bodyClass = 'app-layout admin-layout';
    } elseif (auth()->check()) {
        $bodyClass = 'app-layout';
    } else {
        $bodyClass = 'guest-layout';
    }
@endphp

<body class="{{ $bodyClass }}">
    @auth
    @if($showStudentInsightsSidebar)
        @include('partials.student-shell')
    @endif

    <!-- Sidebar Overlay (Mobile) -->
    @if($showStudentInsightsSidebar || $showCounselorShell || $showAdminShell)
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    @endif

    <!-- Sidebar Navigation -->
    @if($showStudentInsightsSidebar || $showCounselorShell || $showAdminShell)
    <aside class="app-sidebar {{ $showAdminShell ? 'admin-sidebar' : '' }}" id="appSidebar">
        @if($showStudentInsightsSidebar)
            @include('partials.student-sidebar')

        @elseif($showCounselorShell)
            <!-- Counselor Brand -->
            <a href="{{ route('counselor.dashboard') }}" class="counselor-brand" aria-label="{{ config('branding.name') }} Counselor Dashboard">
                <span class="counselor-brand-mark" aria-hidden="true">
                    <x-brand-logo variant="emblem" alt="" />
                </span>
                <span class="counselor-brand-copy">
                    <strong>{{ config('branding.name') }}</strong>
                    <small>{{ config('branding.assistant_name') }} support platform</small>
                </span>
            </a>

            <nav class="sidebar-nav" aria-label="Counselor workspace navigation">
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Workspace</div>

                    <div class="sidebar-item">
                        <a href="{{ route('counselor.dashboard') }}"
                           class="sidebar-link {{ request()->routeIs('counselor.dashboard') ? 'active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M4 13h6V4H4v9zm10 7h6V11h-6v9zM4 20h6v-3H4v3zm10-13h6V4h-6v3z"/>
                            </svg>
                            <span class="sidebar-link-label">Dashboard</span>
                        </a>
                    </div>

                    <div class="sidebar-item">
                        <a href="{{ route('counselor.students') }}"
                           class="sidebar-link {{ request()->routeIs('counselor.students', 'counselor.student-profile') ? 'active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8m11 18v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                            </svg>
                            <span class="sidebar-link-label">Students</span>
                        </a>
                    </div>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Communication</div>

                    <div class="sidebar-item">
                        <a href="{{ route('counselor.messages') }}"
                           class="sidebar-link {{ request()->routeIs('counselor.messages', 'counselor.chat') ? 'active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4-4 7-9 7-1.4 0-2.8-.25-4-.72L3 19l1.3-3.4A6.3 6.3 0 013 12c0-4 4-7 9-7s9 3 9 7z"/>
                            </svg>
                            <span class="sidebar-link-label">Messages</span>
                        </a>
                    </div>

                    <div class="sidebar-item">
                        <a href="{{ route('student.community') }}"
                           class="sidebar-link {{ request()->routeIs('student.community*') ? 'active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h8M8 14h5m7-2a8 8 0 01-8 8c-1.4 0-2.7-.35-3.8-.96L4 20l.96-4.2A8 8 0 1120 12z"/>
                            </svg>
                            <span class="sidebar-link-label">Community Circle</span>
                        </a>
                    </div>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Schedule</div>

                    <div class="sidebar-item">
                        <a href="{{ route('counselor.appointments') }}"
                           class="sidebar-link {{ request()->routeIs('counselor.appointments*') ? 'active' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M8 7V3m8 4V3M5 10h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                            </svg>
                            <span class="sidebar-link-label">Appointments</span>
                        </a>
                    </div>
                </div>
            </nav>

            <div class="counselor-sidebar-footer">
                <div class="counselor-sidebar-user">
                    <span class="counselor-sidebar-avatar">
                        @if(auth()->user()->profile_picture)
                            <img src="{{ asset(auth()->user()->profile_picture) }}" alt="{{ auth()->user()->name }}">
                        @else
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        @endif
                    </span>
                    <span class="counselor-sidebar-user-copy">
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>University Counselor</span>
                    </span>
                </div>
            </div>

        @elseif($showAdminShell)
            <!-- =========================================================
                 ADMIN SIDEBAR â€” SINGLE ROLE-SPECIFIC SHELL
                 ========================================================= -->
            <a href="{{ route('admin.dashboard') }}"
               class="admin-sidebar-brand"
               aria-label="{{ config('branding.name') }} Administrator Dashboard">
                <span class="admin-sidebar-brand-mark" aria-hidden="true">
                    <x-brand-logo variant="emblem" alt="" />
                </span>

                <span class="admin-sidebar-brand-copy">
                    <strong>{{ config('branding.name') }}</strong>
                    <small>Admin Console</small>
                </span>
            </a>

            <nav class="sidebar-nav admin-sidebar-nav" aria-label="Administrator navigation">
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Workspace</div>

                    <div class="sidebar-item">
                        <a href="{{ route('admin.dashboard') }}"
                           class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                      d="M4 13h6V4H4v9zm10 7h6V11h-6v9zM4 20h6v-3H4v3zm10-13h6V4h-6v3z"/>
                            </svg>
                            <span class="sidebar-link-label">Dashboard</span>
                        </a>
                    </div>

                    <div class="sidebar-item">
                        <a href="{{ route('student.community') }}"
                           class="sidebar-link {{ request()->routeIs('student.community*') ? 'active' : '' }}" @if(request()->routeIs('student.community*')) aria-current="page" @endif>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h8M8 14h5m7-2a8 8 0 01-8 8c-1.4 0-2.7-.35-3.8-.96L4 20l.96-4.2A8 8 0 1120 12z"/>
                            </svg>
                            <span class="sidebar-link-label">Community Circle</span>
                        </a>
                    </div>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Administration</div>

                    <div class="sidebar-item">
                        <a href="{{ route('admin.users') }}"
                           class="sidebar-link {{ request()->routeIs('admin.users', 'admin.create-user') ? 'active' : '' }}" @if(request()->routeIs('admin.users', 'admin.create-user')) aria-current="page" @endif>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                      d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8m11 18v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                            </svg>
                            <span class="sidebar-link-label">Users</span>
                        </a>
                    </div>

                    @if(auth()->user()?->canReviewAccountVerification())
                        <div class="sidebar-item">
                            <a href="{{ route('admin.account-verification.index') }}"
                               class="sidebar-link {{ request()->routeIs('admin.account-verification.*') ? 'active' : '' }}" @if(request()->routeIs('admin.account-verification.*')) aria-current="page" @endif>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                          d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11zm-3-10 2 2 4-4"/>
                                </svg>
                                <span class="sidebar-link-label">Account verification</span>
                            </a>
                        </div>
                    @endif

                    <div class="sidebar-item">
                        <a href="{{ route('admin.reports') }}"
                           class="sidebar-link {{ request()->routeIs('admin.reports') ? 'active' : '' }}" @if(request()->routeIs('admin.reports')) aria-current="page" @endif>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                      d="M4 19V9m5 10V5m5 14v-7m5 7V3"/>
                            </svg>
                            <span class="sidebar-link-label">Reports</span>
                        </a>
                    </div>

                </div>

                <div class="admin-sidebar-note" aria-label="Administrator workspace">
                    <span class="admin-sidebar-note-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <span>
                        <strong>System workspace</strong>
                        <small>Administrative access</small>
                    </span>
                </div>
            </nav>

            <div class="admin-sidebar-footer">
                <div class="admin-sidebar-user">
                    <span class="admin-sidebar-avatar">
                        @if(auth()->user()->profile_picture)
                            <img src="{{ asset(auth()->user()->profile_picture) }}"
                                 alt="{{ auth()->user()->name }}">
                        @else
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        @endif
                    </span>

                    <span class="admin-sidebar-user-copy">
                        <strong>{{ auth()->user()->name }}</strong>
                        <small>Administrator</small>
                    </span>

                    <form method="POST"
                          action="{{ route('logout') }}"
                          class="admin-sidebar-logout-form">
                        @csrf
                        <button type="submit"
                                class="admin-sidebar-logout"
                                title="Logout"
                                aria-label="Logout">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </aside>
    @endif

    <!-- Header -->
    @if($showStudentHeader)
        @include('partials.student-header')
    @elseif($showStudentInsightsSidebar || $showCounselorShell || $showAdminShell)
    <header class="app-header">
        <div class="header-left">
            <button type="button" class="header-mobile-menu" onclick="toggleSidebar()" title="Menu" aria-label="Open navigation" @if($showAdminShell) aria-controls="appSidebar" aria-expanded="false" @endif>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            @if($showCounselorShell)
                <div class="counselor-header-context">
                    <div class="counselor-header-kicker">
                        <strong>Counselor Workspace</strong>
                        <span>/</span>
                        <span>{{ $counselorHeaderSection }}</span>
                    </div>
                    <div class="counselor-header-current">{{ $counselorHeaderTitle }}</div>
                </div>
            @elseif($showAdminShell)
                @php
                    $adminHeaderSection = match (true) {
                        request()->routeIs('admin.dashboard') => 'Dashboard',
                        request()->routeIs('student.community*') => 'Community Circle',
                        request()->routeIs('admin.create-user') => 'Create user',
                        request()->routeIs('admin.users') => 'Users',
                        request()->routeIs('admin.account-verification.*') => 'Account verification',
                        request()->routeIs('admin.reports') => 'Reports',
                        default => 'Overview',
                    };
                @endphp
                <div class="admin-header-context" aria-label="Current admin section">
                    <span>Admin workspace</span><span aria-hidden="true">/</span><strong>{{ $adminHeaderSection }}</strong>
                </div>
            @else
                <div class="header-title">
                    <h1>@yield('header_title', config('branding.name'))</h1>
                    @yield('header_subtitle')
                </div>
            @endif
        </div>

        <div class="header-right">
            @include('components.notification-dropdown')

            @if($showCounselorShell)
                <span class="counselor-status-chip">
                    <span class="counselor-status-dot"></span>
                    Available
                </span>
            @endif

            <div class="header-user-menu">
                <button class="header-user-button" onclick="toggleUserDropdown()" title="Profile" aria-label="Open account menu">
                    <div class="header-user-avatar">
                        @if(auth()->user()->profile_picture)
                            <img src="{{ asset(auth()->user()->profile_picture) }}" alt="Profile">
                        @else
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        @endif
                    </div>

                    @if($showCounselorShell)
                        <span class="counselor-header-user-copy">
                            <strong>{{ auth()->user()->name }}</strong>
                            <small>Counselor</small>
                        </span>
                        <svg class="counselor-header-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9l6 6 6-6"/>
                        </svg>
                    @endif
                </button>

                <div class="user-dropdown-menu" id="userDropdownMenu">
                    <div class="user-dropdown-header">
                        <div class="header-user-avatar">
                            @if(auth()->user()->profile_picture)
                                <img src="{{ asset(auth()->user()->profile_picture) }}" alt="Profile">
                            @else
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            @endif
                        </div>
                        <div class="user-dropdown-header-info">
                            <div class="user-dropdown-name">{{ auth()->user()->name }}</div>
                            <div class="user-dropdown-role">{{ ucfirst(auth()->user()->role) }}</div>
                        </div>
                    </div>

                    @if(auth()->user()->isStudent())
                    <button type="button" class="user-dropdown-item" data-profile-open>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        View Profile
                    </button>
                    @endif

                    <form action="{{ route('logout') }}" method="POST" style="width: 100%;">
                        @csrf
                        <button type="submit" class="user-dropdown-logout">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>
    @endif


    <!-- ==================== SHARED STUDENT ACCOUNT POPOVER ==================== -->
    @if(auth()->user()->isStudent())
    <div id="accountPopover" class="account-popover" aria-hidden="true">
        <button type="button" class="account-popover-item" data-account-settings>
            <span class="account-popover-item-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zm7.4-3.5a7.57 7.57 0 00-.12-1.32l2-1.56-2-3.46-2.45.98a7.2 7.2 0 00-2.28-1.32L14.2 2.7h-4l-.36 2.62a7.2 7.2 0 00-2.28 1.32l-2.45-.98-2 3.46 2 1.56A7.57 7.57 0 005 12c0 .45.04.89.12 1.32l-2 1.56 2 3.46 2.45-.98a7.2 7.2 0 002.28 1.32l.36 2.62h4l.36-2.62a7.2 7.2 0 002.28-1.32l2.45.98 2-3.46-2-1.56c.08-.43.12-.87.12-1.32z"/>
                </svg>
            </span>
            <span class="account-popover-item-text">
                <strong>Settings</strong>
                <small>Profile and privacy settings</small>
            </span>
            <svg class="account-popover-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 18l6-6-6-6"/>
            </svg>
        </button>

        <div class="account-popover-separator"></div>

        <form method="POST" action="{{ route('logout') }}" class="account-popover-form">
            @csrf
            <button type="submit" class="account-popover-item account-popover-logout">
                <span class="account-popover-item-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M10 17l5-5-5-5m5 5H3m9-9h6a2 2 0 012 2v14a2 2 0 01-2 2h-6"/>
                    </svg>
                </span>
                <span class="account-popover-item-text">
                    <strong>Logout</strong>
                    <small>Sign out</small>
                </span>
            </button>
        </form>
    </div>
    @endif

    @if(auth()->user()->isStudent())
    <!-- Student Profile Modal -->
    <div id="profileModal" class="modal" style="z-index: 9999;">
        <div class="modal-content profile-modal-content">
            <div class="profile-modal-header">
                <h2>My Profile</h2>
                <button type="button" class="modal-close" data-profile-close aria-label="Close profile">Ã—</button>
            </div>

            <!-- Display Errors -->
            @if ($errors->any())
                <div class="profile-modal-errors">
                    @foreach ($errors->all() as $error)
                        <div class="error-message">{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <!-- Display Success -->
            @if (session('success'))
                <div class="profile-modal-success">
                    {{ session('success') }}
                </div>
            @endif
            
            <form action="{{ route('student.profile.update') }}" method="POST" enctype="multipart/form-data" class="profile-form">
                @csrf
                @method('PUT')
                
                <!-- Profile Photo Section -->
                <div class="profile-photo-section">
                    <div class="profile-photo-wrapper">
                        <div class="profile-photo" id="avatarDisplay">
                            @if(auth()->user()->profile_picture)
                                <img src="{{ asset(auth()->user()->profile_picture) }}" alt="Profile Photo">
                            @else
                                <div class="profile-photo-placeholder">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                            @endif
                        </div>
                        <label for="profilePicture" class="photo-upload-btn" title="Change profile picture">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </label>
                        <input type="file" id="profilePicture" name="profile_picture" accept="image/jpeg,image/png,image/webp,image/jpg" onchange="previewImage(event)" style="display: none;">
                    </div>
                    <div class="profile-photo-info">
                        <p class="profile-photo-name">{{ auth()->user()->name }}</p>
                        <p class="profile-photo-role">{{ ucfirst(auth()->user()->role) }}</p>
                    </div>
                </div>

                <div class="profile-divider"></div>

                <!-- Personal Information Section -->
                <div class="profile-section">
                    <h3 class="profile-section-title">Personal Information</h3>

                    <div class="profile-form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="{{ old('name', auth()->user()->name) }}" required placeholder="Enter your full name">
                    </div>

                    <div class="profile-form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email', auth()->user()->email) }}" required placeholder="Enter your email">
                    </div>

                    <div class="profile-form-group">
                        <label for="student_id">Student ID</label>
                        <input type="text" id="student_id" name="student_id" value="{{ old('student_id', auth()->user()->student_id) }}" placeholder="Your student ID">
                    </div>

                    <div class="profile-form-group">
                        <label for="year_level">Year Level</label>
                        <input type="text" id="year_level" name="year_level" value="{{ old('year_level', auth()->user()->year_level) }}" placeholder="e.g., 1st Year, 2nd Year">
                    </div>

                    <div class="profile-form-group">
                        <label for="program">Program</label>
                        <input type="text" id="program" name="program" value="{{ old('program', auth()->user()->program) }}" placeholder="Your degree program">
                    </div>

                    <div class="profile-form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="3" placeholder="Tell us about yourself..." maxlength="500">{{ old('bio', auth()->user()->bio) }}</textarea>
                        <small class="form-help-text"><span id="charCount">0</span>/500 characters</small>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="profile-actions">
                    <button type="submit" class="btn-save">Save Changes</button>
                    <button type="button" class="btn-cancel" data-profile-close>Cancel</button>
                </div>

                <div class="profile-divider"></div>

                <!-- Logout -->
                <button type="submit" class="btn-logout" form="studentProfileLogoutForm">Logout</button>
            </form>

            <form id="studentProfileLogoutForm" method="POST" action="{{ route('logout') }}" style="display:none;">
                @csrf
            </form>
        </div>
    </div>

    @endif

    <!-- Main Content -->
    @if($showStudentShell || $showCounselorShell || $showAdminShell || $showAccountStatusShell)
    <main class="app-main{{ $showAccountStatusShell ? ' account-status-main' : '' }}">
        <div class="content-wrapper">
            @yield('content')
        </div>
    </main>
    @else
    <main class="guest-main">
        @yield('content')
    </main>
    @endif

    @else
    <!-- Unauthenticated Users: Show Content Without Shell -->
    <main class="guest-main">
        @yield('content')
    </main>

    @endauth

    <script>
        // ===== SIDEBAR TOGGLE (Mobile) =====
        function toggleSidebar() {
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');

            if (!sidebar) return;

            const isOpen = sidebar.classList.toggle('mobile-open');

            if (overlay) {
                overlay.classList.toggle('active', isOpen);
            }

            const adminToggle = document.querySelector('.header-mobile-menu[aria-controls="appSidebar"]');
            if (adminToggle) {
                adminToggle.setAttribute('aria-expanded', String(isOpen));
                adminToggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
            }
        }

        // Close sidebar when clicking overlay
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuBtn = document.querySelector('.header-mobile-menu');
            
            if (
                sidebar &&
                overlay &&
                !sidebar.contains(event.target) &&
                (!menuBtn || !menuBtn.contains(event.target))
            ) {
                if (sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                    if (menuBtn?.hasAttribute('aria-controls')) {
                        menuBtn.setAttribute('aria-expanded', 'false');
                        menuBtn.setAttribute('aria-label', 'Open navigation');
                    }
                }
            }
        });

        document.addEventListener('keydown', function(event) {
            const sidebar = document.getElementById('appSidebar');
            const menuBtn = document.querySelector('.header-mobile-menu[aria-controls="appSidebar"]');
            if (event.key !== 'Escape' || !menuBtn || !sidebar?.classList.contains('mobile-open')) return;
            sidebar.classList.remove('mobile-open');
            document.getElementById('sidebarOverlay')?.classList.remove('active');
            menuBtn.setAttribute('aria-expanded', 'false');
            menuBtn.setAttribute('aria-label', 'Open navigation');
            menuBtn.focus();
        });

        // ===== USER DROPDOWN TOGGLE =====
        function toggleUserDropdown() {
            const menu = document.getElementById('userDropdownMenu');
            if (menu) {
                menu.classList.toggle('active');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.header-user-menu');
            const userDropdownMenu = document.getElementById('userDropdownMenu');
            
            if (userMenu && !userMenu.contains(event.target)) {
                if (userDropdownMenu) {
                    userDropdownMenu.classList.remove('active');
                }
            }
        });


        // ===== PROFILE MODAL =====
        window.openProfileModal = function () {
            const modal = document.getElementById('profileModal');
            if (!modal) {
                console.error('[Profile] #profileModal was not found.');
                return;
            }
            
            // Set all visibility properties directly
            modal.removeAttribute('hidden');
            modal.style.setProperty('display', 'flex', 'important');
            modal.style.setProperty('visibility', 'visible', 'important');
            modal.style.setProperty('opacity', '1', 'important');
            modal.style.setProperty('pointer-events', 'auto', 'important');
            
            // Add classes for CSS-based state
            modal.classList.add('active');
            modal.classList.add('show');
            
            // Accessibility
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('profile-modal-open');
            
            // Close dropdown if open
            const dropdown = document.getElementById('userDropdownMenu');
            if (dropdown) {
                dropdown.classList.remove('active');
            }
            
            // Initialize bio counter (safe call)
            if (typeof window.updateBioCharCounter === 'function') {
                try {
                    window.updateBioCharCounter();
                } catch (e) {
                    console.warn('[Profile] Bio counter error:', e);
                }
            }
        };

        window.closeProfileModal = function () {
            const modal = document.getElementById('profileModal');
            if (!modal) return;
            
            // Remove state classes
            modal.classList.remove('active');
            modal.classList.remove('show');
            
            // Set all visibility properties to hidden
            modal.style.setProperty('display', 'none', 'important');
            modal.style.setProperty('visibility', 'hidden', 'important');
            modal.style.setProperty('opacity', '0', 'important');
            modal.style.setProperty('pointer-events', 'none', 'important');
            
            // Accessibility
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('profile-modal-open');
        };

        // ===== SHARED PROFILE TRIGGER LISTENER =====
        document.addEventListener('click', function (event) {
            const opener = event.target.closest('[data-profile-open]');
            if (opener) {
                event.preventDefault();
                event.stopPropagation();
                window.openProfileModal();
                return;
            }
            
            const closer = event.target.closest('[data-profile-close]');
            if (closer) {
                event.preventDefault();
                event.stopPropagation();
                window.closeProfileModal();
                return;
            }
        });

        // ===== BACKDROP CLOSE =====
        document.addEventListener('click', function (event) {
            const modal = document.getElementById('profileModal');
            if (modal && event.target === modal) {
                window.closeProfileModal();
            }
        });

        // ===== ESCAPE KEY CLOSE =====
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('profileModal');
                if (modal && modal.getAttribute('aria-hidden') === 'false') {
                    window.closeProfileModal();
                }
            }
        });

        // Bio character counter
        function updateBioCharCounter() {
            const bioTextarea = document.getElementById('bio');
            const charCount = document.getElementById('charCount');
            if (bioTextarea && charCount) {
                bioTextarea.addEventListener('input', function() {
                    charCount.textContent = this.value.length;
                });
                charCount.textContent = bioTextarea.value.length;
            }
        }


        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                // Validate file type
                const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
                if (!validTypes.includes(file.type)) {
                    alert('Please upload a valid image (JPEG, PNG, or WebP)');
                    return;
                }

                // Validate file size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Image size must be less than 2MB');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatarDisplay = document.getElementById('avatarDisplay');
                    avatarDisplay.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                };
                reader.readAsDataURL(file);
            }
        }

        // ===== ACCOUNT POPOVER FUNCTIONS =====
        window.openAccountPopover = function (trigger) {
            const popover = document.getElementById('accountPopover');
            if (!popover || !trigger) {
                console.error('[Account] accountPopover or trigger not found.');
                return;
            }

            // Add is-account-open to trigger
            if (trigger) {
                trigger.classList.add('is-account-open');
            }

            popover.classList.add('is-open');
            popover.setAttribute('aria-hidden', 'false');

            // Determine position based on trigger location
            const triggerRect = trigger.getBoundingClientRect();
            const isSidebar = trigger.closest('.app-sidebar') !== null;

            if (isSidebar) {
                // Sidebar positioning: above the trigger
                popover.classList.add('from-sidebar');
                popover.classList.remove('from-home');
                const popoverHeight = popover.offsetHeight;
                const left = Math.max(12, triggerRect.left + 10);
                const top = Math.max(12, triggerRect.top - popoverHeight - 8);
                popover.style.left = left + 'px';
                popover.style.top = top + 'px';
            } else {
                // Homepage positioning: below the trigger
                popover.classList.add('from-home');
                popover.classList.remove('from-sidebar');
                const popoverWidth = popover.offsetWidth;
                const left = Math.min(window.innerWidth - popoverWidth - 16, triggerRect.right - popoverWidth);
                const top = triggerRect.bottom + 8;
                popover.style.left = left + 'px';
                popover.style.top = top + 'px';
            }
        };

        window.closeAccountPopover = function () {
            const popover = document.getElementById('accountPopover');
            if (!popover) return;

            // Remove is-account-open from all triggers
            document.querySelectorAll('[data-account-menu-open].is-account-open').forEach(function (trigger) {
                trigger.classList.remove('is-account-open');
            });

            popover.classList.remove('is-open');
            popover.setAttribute('aria-hidden', 'true');
            popover.style.left = '';
            popover.style.top = '';
        };

        // ===== ACCOUNT MENU TRIGGER LISTENER =====
        document.addEventListener('click', function (event) {
            const opener = event.target.closest('[data-account-menu-open]');
            if (opener) {
                event.preventDefault();
                event.stopPropagation();
                const popover = document.getElementById('accountPopover');
                if (popover && popover.classList.contains('is-open') && 
                    opener.classList.contains('is-account-open')) {
                    // If popover is already open and we click the same trigger, close it
                    window.closeAccountPopover();
                } else {
                    // Otherwise open it
                    window.openAccountPopover(opener);
                }
                return;
            }

            // Close popover if clicking outside
            const popover = document.getElementById('accountPopover');
            if (popover && popover.classList.contains('is-open')) {
                if (!popover.contains(event.target)) {
                    window.closeAccountPopover();
                }
            }
        });

        // ===== ACCOUNT POPOVER SETTINGS & LOGOUT =====
        document.addEventListener('click', function (event) {
            const settings = event.target.closest('[data-account-settings]');
            if (settings) {
                event.preventDefault();
                event.stopPropagation();
                window.closeAccountPopover();
                window.location.href = @json(route('student.settings'));
            }
        });

        // ===== ACCOUNT POPOVER ESCAPE KEY =====
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            const popover = document.getElementById('accountPopover');
            if (popover && popover.classList.contains('is-open')) {
                window.closeAccountPopover();
            }
        });
    </script>
    @include('partials.pwa-install')
</body>
</html>
