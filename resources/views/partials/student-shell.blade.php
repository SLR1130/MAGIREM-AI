@auth
@if(auth()->user()->isStudent())
<style id="minsuanscore-student-shell-unified">
    /*
     * UNIFIED STUDENT SHELL
     * Apply once in layouts/app.blade.php for ALL authenticated student pages.
     *
     * Desktop student pages:
     * - no global top header
     * - no top profile/avatar
     * - no bottom sidebar profile
     * - fixed 288px sidebar: brand + navigation only
     * - page content begins cleanly beside sidebar
     *
     * Exclusions:
     * - custom homepage (.msw-home) keeps its own full layout
     *
     * Mobile/tablet:
     * - header remains only as navigation utility (hamburger/notification)
     * - title/subtitle/profile duplication is hidden
     */

    :root {
        --msc-student-sidebar: 288px;
        --msc-student-mobile-header: 60px;
    }

    /* Do not interfere with the custom homepage. */
    body.app-layout:not(:has(.msw-home)) {
        display:block !important;
        min-height:100dvh !important;
        overflow-x:hidden !important;
        background:#f8faf7 !important;
    }

    /* ==========================================================
       SIDEBAR — ALL AUTHENTICATED STUDENT PAGES
       ========================================================== */
    body.app-layout:not(:has(.msw-home)) .app-sidebar {
        position:fixed !important;
        top:0 !important;
        left:0 !important;
        bottom:0 !important;

        width:var(--msc-student-sidebar) !important;
        min-width:var(--msc-student-sidebar) !important;
        max-width:var(--msc-student-sidebar) !important;

        height:100dvh !important;
        min-height:100dvh !important;

        display:flex !important;
        flex-direction:column !important;

        margin:0 !important;
        overflow:hidden !important;
        box-sizing:border-box !important;

        background:#fff !important;
        border-right:1px solid rgba(16,63,50,.09) !important;
        z-index:100 !important;
    }

    body.app-layout:not(:has(.msw-home)) .app-sidebar,
    body.app-layout:not(:has(.msw-home)) .app-sidebar * {
        box-sizing:border-box !important;
    }

    body.app-layout:not(:has(.msw-home)) .sidebar-brand,
    body.app-layout:not(:has(.msw-home)) .sidebar-header {
        flex:0 0 auto !important;
        min-width:0 !important;
        max-width:100% !important;
        overflow:hidden !important;
    }

    /* Navigation owns all remaining sidebar height. */
    body.app-layout:not(:has(.msw-home)) .sidebar-nav {
        flex:1 1 auto !important;
        min-height:0 !important;

        width:100% !important;
        max-width:100% !important;

        margin:0 !important;
        padding:8px 0 22px !important;

        overflow-y:auto !important;
        overflow-x:hidden !important;

        scrollbar-width:thin !important;
        overscroll-behavior:contain;
    }

    /*
     * IMPORTANT:
     * Never use width:100% together with horizontal margins.
     * Each nav row is explicitly constrained INSIDE the sidebar.
     */
    body.app-layout:not(:has(.msw-home)) .sidebar-link {
        position:relative !important;

        display:flex !important;
        align-items:center !important;
        gap:12px !important;

        width:calc(100% - 20px) !important;
        min-width:0 !important;
        max-width:calc(100% - 20px) !important;

        height:44px !important;
        min-height:44px !important;

        margin:2px 10px !important;
        padding:0 12px !important;

        overflow:hidden !important;
        white-space:nowrap !important;
        box-sizing:border-box !important;
    }

    body.app-layout:not(:has(.msw-home)) .sidebar-link > svg,
    body.app-layout:not(:has(.msw-home)) .sidebar-link-icon {
        flex:0 0 20px !important;
        width:20px !important;
        height:20px !important;
        min-width:20px !important;
        max-width:20px !important;
    }

    body.app-layout:not(:has(.msw-home)) .sidebar-link-label {
        display:block !important;
        flex:1 1 auto !important;

        width:auto !important;
        min-width:0 !important;
        max-width:100% !important;

        overflow:hidden !important;
        text-overflow:ellipsis !important;
        white-space:nowrap !important;
    }

    body.app-layout:not(:has(.msw-home)) .sidebar-section-title,
    body.app-layout:not(:has(.msw-home)) .sidebar-section-label {
        display:block !important;

        width:auto !important;
        min-width:0 !important;
        max-width:calc(100% - 32px) !important;

        margin-left:16px !important;
        margin-right:16px !important;

        overflow:hidden !important;
        text-overflow:ellipsis !important;
        white-space:nowrap !important;
    }

    /* ==========================================================
       DESKTOP — REMOVE SHARED TOP HEADER
       ========================================================== */
    @media (min-width:1025px) {
        body.app-layout:not(:has(.msw-home)) .app-header {
            display:none !important;
            visibility:hidden !important;
            height:0 !important;
            min-height:0 !important;
            overflow:hidden !important;
            pointer-events:none !important;
        }

        /*
         * Suppress any profile/notification controls that older shell patches
         * may have moved outside .app-header.
         */
        body.app-layout:not(:has(.msw-home)) .student-floating-notification-v2,
        body.app-layout:not(:has(.msw-home)) .header-profile,
        body.app-layout:not(:has(.msw-home)) .header-user,
        body.app-layout:not(:has(.msw-home)) .user-dropdown,
        body.app-layout:not(:has(.msw-home)) .profile-dropdown,
        body.app-layout:not(:has(.msw-home)) [data-user-menu],
        body.app-layout:not(:has(.msw-home)) [data-profile-trigger] {
            display:none !important;
        }

        body.app-layout:not(:has(.msw-home)) .app-main {
            position:relative !important;

            width:calc(100% - var(--msc-student-sidebar)) !important;
            min-width:0 !important;
            max-width:none !important;
            min-height:100dvh !important;

            margin:0 0 0 var(--msc-student-sidebar) !important;
            padding:0 !important;

            overflow-x:hidden !important;
            box-sizing:border-box !important;

            background:
                radial-gradient(circle at 91% 6%,rgba(216,235,226,.45),transparent 28rem),
                #f8faf7 !important;
        }

        body.app-layout:not(:has(.msw-home)) .content-wrapper {
            width:100% !important;
            min-width:0 !important;
            max-width:1460px !important;

            margin:0 auto !important;
            padding:30px 28px 54px !important;

            box-sizing:border-box !important;
            overflow:visible !important;
        }

        /*
         * AI Chat is immersive and already owns its own internal canvas.
         * Remove the generic page padding there.
         */
        body:has(.msc-chat).app-layout .content-wrapper {
            width:100% !important;
            max-width:none !important;
            margin:0 !important;
            padding:0 !important;
        }

        body:has(.msc-chat).app-layout .app-main {
            overflow:hidden !important;
        }

        /*
         * Insights V3 already contains its own carefully centered content width,
         * so do not double-pad it.
         */
        body:has(.insights-clean-shell-v3).app-layout .content-wrapper {
            max-width:none !important;
            margin:0 !important;
            padding:0 !important;
        }
    }

    /* ==========================================================
       MOBILE / TABLET
       Keep the app header only for hamburger / notification utility.
       ========================================================== */
    @media (max-width:1024px) {
        body.app-layout:not(:has(.msw-home)) .app-sidebar {
            width:min(86vw,288px) !important;
            min-width:min(86vw,288px) !important;
            max-width:min(86vw,288px) !important;
        }

        body.app-layout:not(:has(.msw-home)) .app-header {
            position:fixed !important;
            top:0 !important;
            left:0 !important;
            right:0 !important;

            width:100% !important;
            height:var(--msc-student-mobile-header) !important;
            min-height:var(--msc-student-mobile-header) !important;

            margin:0 !important;
            padding:0 12px !important;

            display:flex !important;
            visibility:visible !important;
            align-items:center !important;

            box-sizing:border-box !important;
            z-index:90 !important;
        }

        body.app-layout:not(:has(.msw-home)) .app-main {
            width:100% !important;
            min-width:0 !important;
            max-width:none !important;

            margin-left:0 !important;
            padding-top:var(--msc-student-mobile-header) !important;

            box-sizing:border-box !important;
            overflow-x:hidden !important;
        }

        body.app-layout:not(:has(.msw-home)) .content-wrapper {
            width:100% !important;
            min-width:0 !important;
            max-width:none !important;

            margin:0 !important;
            padding:20px 16px 42px !important;

            box-sizing:border-box !important;
        }

        /*
         * Hide all duplicated textual/profile pieces in the mobile app shell.
         * Page content provides its own title.
         */
        body.app-layout:not(:has(.msw-home)) .app-header .header-title,
        body.app-layout:not(:has(.msw-home)) .app-header .header-subtitle,
        body.app-layout:not(:has(.msw-home)) .app-header .page-title,
        body.app-layout:not(:has(.msw-home)) .app-header .page-subtitle,
        body.app-layout:not(:has(.msw-home)) .app-header .user-dropdown,
        body.app-layout:not(:has(.msw-home)) .app-header .header-user,
        body.app-layout:not(:has(.msw-home)) .app-header .header-profile,
        body.app-layout:not(:has(.msw-home)) .app-header .profile-dropdown,
        body.app-layout:not(:has(.msw-home)) .app-header [data-user-menu],
        body.app-layout:not(:has(.msw-home)) .app-header [data-profile-trigger] {
            display:none !important;
        }

        /* Chat still owns the full viewport. */
        body:has(.msc-chat).app-layout .app-header {
            display:none !important;
        }

        body:has(.msc-chat).app-layout .app-main {
            padding-top:0 !important;
        }

        body:has(.msc-chat).app-layout .content-wrapper {
            padding:0 !important;
        }

        /* Insights V3 keeps its own mobile spacing. */
        body:has(.insights-clean-shell-v3).app-layout .content-wrapper {
            padding:0 !important;
        }
    }

    @media (max-width:640px) {
        body.app-layout:not(:has(.msw-home)) .content-wrapper {
            padding-left:12px !important;
            padding-right:12px !important;
        }
    }

    /* STUDENT PROFILE BLOCK AT SIDEBAR BOTTOM */
    .sidebar-footer {
        flex: 0 0 auto;
        width: 100%;
        padding: 10px 12px 12px;
        border-top: 1px solid rgba(16, 63, 50, 0.08);
        background: #ffffff;
        box-sizing: border-box;
        position: relative;
        z-index: 50;
        pointer-events: auto !important;
    }

    .sidebar-user {
        width: 100%;
        min-width: 0;
        display: grid;
        grid-template-columns: 40px minmax(0, 1fr) 16px;
        align-items: center;
        gap: 10px;
        margin: 0;
        padding: 8px 9px;
        border: 0;
        border-radius: 12px;
        background: transparent;
        color: #263A33;
        font-family: inherit;
        text-align: left;
        cursor: pointer !important;
        transition: background .18s ease, transform .18s ease, box-shadow .18s ease;
        position: relative;
        z-index: 51;
        pointer-events: auto !important;
    }

    .sidebar-user * {
        pointer-events: none;
    }

    .sidebar-user:hover {
        background: #E6F1EB;
        box-shadow: 0 6px 18px rgba(16, 63, 50, 0.05);
    }

    .sidebar-user:active {
        transform: scale(.985);
    }

    .sidebar-avatar {
        width: 40px;
        height: 40px;
        min-width: 40px;
        display: grid;
        place-items: center;
        overflow: hidden;
        border-radius: 12px;
        background: linear-gradient(145deg, #103F32, #236B55);
        color: #ffffff;
        font-size: 13px;
        font-weight: 800;
    }

    .sidebar-avatar img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .sidebar-user-info {
        display: block;
        min-width: 0;
        overflow: hidden;
    }

    .sidebar-user-name,
    .sidebar-user-role {
        display: block;
        width: 100%;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sidebar-user-name {
        color: #103F32;
        font-size: 11.5px;
        line-height: 1.3;
        font-weight: 750;
    }

    .sidebar-user-role {
        margin-top: 2px;
        color: #819188;
        font-size: 9.5px;
        line-height: 1.3;
        font-weight: 600;
    }

    .sidebar-profile-chevron {
        width: 15px;
        height: 15px;
        color: #87988F;
        transition: color .18s ease, transform .18s ease;
    }

    .sidebar-user:hover .sidebar-profile-chevron {
        color: #103F32;
        transform: translateX(2px);
    }
</style>

<script id="minsuanscore-student-shell-unified-script">
(function () {
    function normalizeStudentShell() {
        const nav = document.querySelector('.sidebar-nav');

        /* Always begin with MAIN visible on a fresh page. */
        if (nav && !nav.dataset.shellUnifiedInitialized) {
            nav.scrollTop = 0;
            nav.dataset.shellUnifiedInitialized = '1';
        }

        /*
         * Remove top-profile controls even when their class names differ from
         * the known selectors. Keep hamburger/notification controls intact.
         */
        const header = document.querySelector('.app-header');
        if (!header) return;

        header.querySelectorAll('button, a').forEach(function (control) {
            const onclick = control.getAttribute('onclick') || '';
            const aria = (control.getAttribute('aria-label') || '').toLowerCase();
            const title = (control.getAttribute('title') || '').toLowerCase();

            const isProfile =
                onclick.includes('toggleUserDropdown') ||
                onclick.includes('openProfileModal') ||
                aria.includes('profile') ||
                title === 'profile';

            const isNotification =
                onclick.includes('toggleNotifications') ||
                aria.includes('notification');

            const isMenu =
                onclick.includes('toggleSidebar') ||
                aria.includes('menu');

            if (isProfile && !isNotification && !isMenu) {
                const wrapper =
                    control.closest('.user-dropdown') ||
                    control.closest('.header-user') ||
                    control.closest('.header-profile') ||
                    control.closest('.profile-dropdown');

                (wrapper || control).style.display = 'none';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', normalizeStudentShell, { once:true });
    } else {
        normalizeStudentShell();
    }
})();
</script>
@endif
@endauth
