{{-- Shared visual treatment for the existing admin Blade screens. --}}
<style>
body.admin-layout {
    --aw-forest: #103f32;
    --aw-ink: #243d34;
    --aw-muted: #536c60;
    --aw-line: #dce8e0;
    --aw-surface: #fff;
    --aw-soft: #f5f9f6;
    --aw-focus: #9b741d;
    --aw-shadow: 0 8px 28px rgba(16, 63, 50, .045);
}
.admin-dash, .admin-users, .admin-reports, .avi, .avd, .admin-create-user {
    max-width:1480px; margin-inline:auto; padding:28px clamp(16px, 2.4vw, 36px) 52px;
    color:var(--aw-ink); font-size:14px; line-height:1.5;
}
.adm-head, .au-head, .ar-head, .avi-head, .avd-head, .acu-header { gap:20px; margin-bottom:24px; }
.adm-head h1, .au-head h1, .ar-head h1, .avi h1, .avd h1, .acu-header h1 {
    font-size:clamp(30px, 2.6vw, 40px); line-height:1.12; letter-spacing:-.035em;
}
.adm-head p, .au-head p, .ar-head p, .avi-head p, .avd-head p, .acu-header p {
    max-width:68ch; margin-top:8px; color:var(--aw-muted); font-size:14px; line-height:1.6;
}
.adm-eyebrow, .au-eyebrow, .ar-eyebrow, .avi-kicker, .avd-kicker, .acu-eyebrow {
    font-size:11px; letter-spacing:.12em;
}
.adm-date, .ar-scope { min-width:0; padding:14px 17px; border-color:var(--aw-line); border-radius:14px; background:var(--aw-surface); }
.adm-date strong, .ar-scope strong { font-size:13px; }
.adm-date span, .ar-scope span { color:var(--aw-muted); font-size:12px; }

/* Overview: information first, without simulated system-health indicators. */
.adm-stats { grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-bottom:20px; }
.adm-stat, .au-stat, .ar-stat, .avi-count { min-width:0; padding:20px; border:1px solid var(--aw-line); border-radius:16px; background:var(--aw-surface); box-shadow:var(--aw-shadow); }
.adm-stat .adm-stat-icon { display:grid; place-items:center; width:42px; height:42px; margin:0; border-radius:12px; }
.adm-stat strong, .au-stat strong, .avi-count strong { margin-top:14px; font-size:clamp(25px,2.2vw,31px); line-height:1.1; }
.adm-stat span:not(.adm-stat-icon), .au-stat span, .avi-count span { margin-top:7px; color:var(--aw-ink); font-size:13px; line-height:1.4; letter-spacing:normal; text-transform:none; }
.adm-stat small, .au-stat small { margin-top:4px; color:var(--aw-muted); font-size:12px; line-height:1.5; }
.adm-tabs, .ar-tabs { position:static; gap:5px; margin-bottom:18px; padding:5px; border:1px solid var(--aw-line); border-radius:14px; background:#fff; box-shadow:none; backdrop-filter:none; }
.adm-tab, .ar-tab { min-height:44px; padding:0 14px; font-size:13px; white-space:nowrap; }
.adm-tab svg, .ar-tab svg { width:17px; height:17px; flex:none; }
.adm-grid, .ar-grid-two, .ar-distribution-grid { gap:16px; }
.adm-card, .ar-chart, .au-directory, .avi-table-shell, .avd-card, .acu-card { border-color:var(--aw-line); border-radius:16px; box-shadow:var(--aw-shadow); }
.adm-card-head, .acu-card-head, .avd-card-head { padding:18px 20px; }
.adm-card-title, .au-directory-head strong { font-size:15px; }
.adm-card-body { padding:18px 20px 20px; }
.adm-pill, .ar-pill { min-height:28px; padding-inline:10px; font-size:11px; }
.adm-mix-row { padding:16px; border-radius:12px; }
.adm-mix-row strong, .adm-shortcut strong, .adm-entry-copy strong, .adm-tool strong { font-size:13px; }
.adm-mix-row p, .adm-shortcut span span, .adm-entry-copy p, .adm-tool p { color:var(--aw-muted); font-size:12px; line-height:1.5; }
.adm-shortcut { min-height:112px; padding:16px; }
.adm-shortcut-icon, .adm-tool-icon { width:38px; height:38px; }
.adm-empty, .ar-empty { color:var(--aw-muted); font-size:13px; }
.adm-empty strong, .ar-empty strong { font-size:14px; }
.adm-entry-time { font-size:11px; }

/* User directory and verification queues. */
.au-summary { grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-bottom:20px; }
.au-stat strong { margin-top:0; }
.au-controls { gap:12px; margin-bottom:14px; }
.au-create, .avi-btn, .avd-btn, .acu-button, .acu-back { min-height:44px; padding-inline:16px; font-size:13px; }
.au-search input, .avi-filter input, .avi-filter select { min-height:46px; height:auto; font-size:14px; }
.au-search input { padding-block:10px; }
.au-tabs { gap:4px; padding:5px; }
.au-tab, .avi-tab { min-height:40px; padding:0 12px; font-size:12px; white-space:nowrap; }
.au-tab-count { min-width:22px; height:22px; font-size:11px; }
.au-directory-head { padding:17px 20px; }
.au-directory-head span, .avi-note { color:var(--aw-muted); font-size:12px; line-height:1.5; }
.au-table-wrap, .avi-table-wrap { overflow-x:auto; overscroll-behavior-inline:contain; }
.au-table { min-width:790px; }
.au-table th, .avi-table th { padding:13px 16px; color:#566f63; font-size:11px; }
.au-table td, .avi-table td { padding:15px 16px; font-size:13px; }
.au-person-copy strong, .avi-person strong { font-size:13px; }
.au-person-copy span, .avi-person small, .au-detail { color:var(--aw-muted); font-size:12px; line-height:1.45; }
.au-role, .au-current, .avi-pill, .avd-pill { min-height:26px; padding:5px 10px; font-size:11px; letter-spacing:.02em; }
.au-delete { min-height:38px; padding:0 12px; font-size:12px; }
.au-delete svg { width:15px; height:15px; }
.au-empty-row, .avi-empty { padding:44px 20px; font-size:13px; }
.au-empty-row strong { font-size:15px; }
.au-pagination, .avi-pagination { padding:15px 20px; }
.avi-counts { gap:12px; margin-bottom:20px; }
.avi-count strong { margin-top:0; }
.avi-tabs { gap:8px; margin-bottom:12px; }
.avi-tab b { font-size:11px; }
.avi-filter { gap:12px; padding:16px; margin:16px 0; border-color:var(--aw-line); }
.avi-filter label { font-size:11px; }
.avi .avi-back { width:44px; min-width:44px; height:44px; padding:0; border-radius:12px; }
.avi .avi-back svg { width:19px; height:19px; }
.avi .avi-back:hover { border-color:#a9c9b7; background:#edf6ef; }

/* Keep identity decisions readable without changing the review workflow. */
.avd-grid { gap:16px; }
.avd-card-head h2, .acu-card-head h2 { font-size:16px; }
.avd-card-head p, .acu-card-head p { color:var(--aw-muted); font-size:12px; line-height:1.55; }
.avd-card-body, .avd-disclosure__content { padding:20px; }
.avd-facts { gap:10px; }
.avd-fact { padding:13px; border-color:var(--aw-line); }
.avd-fact small { color:#5d7569; font-size:10px; }
.avd-fact strong { font-size:13px; line-height:1.45; }
.avd-no-doc, .avd-form-help, .avd-alert, .avd-event p, .acu-help, .acu-verification-note, .acu-security-note { color:var(--aw-muted); font-size:12px; line-height:1.6; }
.avd-alert--error { color:#873b36; }.avd-alert--warning { color:#715a18; }
.avd-form { gap:12px; padding:16px; }
.avd-form label, .avd-check, .avd-steps li, .avd-inline-disclosure>summary, .avd-disclosure__summary { font-size:12px!important; line-height:1.55; }
.avd-form input:not([type=checkbox]), .avd-form select, .avd-form textarea { width:100%; min-height:44px; padding:10px 12px; border:1px solid #cbdcd1; border-radius:10px; background:#fbfcfb; color:var(--aw-ink); font:inherit; font-size:14px; }
.avd-form textarea { min-height:88px; }
.avd-event time, .avd-event strong, .avd-delivery, .avd-disclosure__summary small { font-size:12px; }
.avd-disclosure>.avd-disclosure__summary { min-height:52px; padding:15px 20px; }
.avd-dialog button { min-height:44px; font-size:13px; }

/* Reports: preserve charts and drilldowns while giving labels room to breathe. */
.ar-stats { gap:14px; margin-bottom:20px; }
.ar-stat-copy span, .ar-stat-copy small { font-size:12px; line-height:1.5; }
.ar-stat-copy strong { font-size:clamp(22px,2vw,28px); }
.ar-intro { padding:16px 18px; margin-bottom:16px; border-color:var(--aw-line); }
.ar-intro strong, .ar-chart h2 { font-size:15px; }
.ar-intro p, .ar-chart p, .ar-taxonomy-note { color:var(--aw-muted); font-size:12px; line-height:1.55; }
.ar-chart { padding:20px; }
.ar-chart-error { display:grid; place-items:center; height:100%; margin:0; padding:20px; color:var(--aw-muted); font-size:13px; line-height:1.55; text-align:center; }
.ar-chart.large { grid-column:auto; }
.ar-chart.large .ar-chart-wrap { height:300px; }
.ar-emotion-row strong, .ar-student-copy strong, .ar-category-card strong, .ar-drilldown-head strong { font-size:13px; }
.ar-emotion-row span, .ar-student-copy span, .ar-student-count span, .ar-risk p, .ar-category-card small, .ar-drilldown-row, .ar-drilldown-row strong, .ar-drilldown-head button, .ar-period-filter { font-size:12px; }
.ar-risk span, .ar-student-count strong { font-size:13px; }
.ar-category-card { min-height:112px; padding:16px; }
.ar-period-filter select { min-height:40px; font-size:12px; }

/* Consistent keyboard and pointer feedback. */
.admin-dash :is(a,button):focus-visible, .admin-users :is(a,button,input):focus-visible,
.admin-reports :is(a,button,select):focus-visible, .avi :is(a,button,input,select):focus-visible,
.avd :is(a,button,input,select,textarea,summary):focus-visible, .admin-create-user :is(a,button,input,select,textarea):focus-visible,
.au-table-wrap:focus-visible, .avi-table-wrap:focus-visible { outline:3px solid var(--aw-focus); outline-offset:2px; }
.au-create, .au-tab, .au-delete, .avi-btn, .avi-tab, .avd-btn, .ar-tab, .ar-category-card {
    transition:background-color 180ms ease, border-color 180ms ease, color 180ms ease, box-shadow 180ms ease;
}
.au-create:hover, .avi-btn:not(.avi-btn--light):hover, .avd-btn:not(.avd-btn--light):not(.avd-btn--danger):hover { background:#1d604c; }
.au-tab:hover, .avi-tab:not(.active):hover, .ar-tab:not(.active):hover { background:#eef6f0; color:var(--aw-forest); }
.au-table tbody tr, .avi-table tbody tr { transition:background-color 180ms ease; }

@keyframes adminWorkspaceEnter {
    from { opacity:0; transform:translateY(7px); }
    to { opacity:1; transform:translateY(0); }
}
@media (prefers-reduced-motion:no-preference) {
    .admin-users :is(.au-head,.au-summary,.au-controls,.au-directory),
    .admin-reports :is(.ar-head,.ar-stats,.ar-tabs,.ar-panel.active),
    .avi :is(.avi-head,.avi-counts,.avi-filter,.avi-table-shell),
    .avd :is(.avd-head,.avd-grid),
    .admin-create-user :is(.acu-header,.acu-form-grid) {
        animation:adminWorkspaceEnter 320ms cubic-bezier(.22,1,.36,1) backwards;
    }
    .admin-users :is(.au-controls,.au-directory), .admin-reports .ar-panel.active,
    .avi :is(.avi-filter,.avi-table-shell), .avd .avd-grid,
    .admin-create-user .acu-form-grid { animation-delay:60ms; }
}

@media (max-width:1000px) {
    .adm-stats { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .ar-chart.large { grid-column:auto; }
}
@media (max-width:760px) {
    .admin-dash, .admin-users, .admin-reports, .avi, .avd, .admin-create-user { padding:20px 16px 36px; }
    .adm-head, .au-head, .ar-head, .avi-head, .avd-head, .acu-header { align-items:flex-start; flex-direction:column; }
    .adm-stats, .au-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .adm-stats .adm-stat:last-child { grid-column:1 / -1; }
    .adm-tabs, .ar-tabs, .au-tabs, .avi-tabs { flex-wrap:nowrap; overflow-x:auto; overscroll-behavior-inline:contain; }
    .adm-tab, .ar-tab, .au-tab, .avi-tab { flex:0 0 auto; }
    .au-table th:nth-child(3), .au-table td:nth-child(3) { display:table-cell; }
    .avi-filter { grid-template-columns:1fr; }
    .avi-filter .wide { grid-column:auto; }
    .avd-card-body, .avd-disclosure__content { padding:16px; }
    .avi .avi-back { display:none; }
}
@media (max-width:420px) {
    .adm-stats, .au-summary { grid-template-columns:1fr; }
    .adm-stats .adm-stat:last-child { grid-column:auto; }
    .adm-date, .ar-scope { width:100%; }
}
/* Compact bento summaries for the Users and verification screens on phones. */
@media (max-width:700px) {
    .admin-users .au-summary { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-bottom:16px; }
    .admin-users .au-stat { min-height:104px; padding:14px; border-radius:15px; }
    .admin-users .au-stat strong { margin:0; font-size:25px; line-height:1.05; }
    .admin-users .au-stat span { margin-top:7px; font-size:12px; line-height:1.3; }
    .admin-users .au-stat small { margin-top:4px; font-size:11px; line-height:1.4; }
    .admin-users .au-stat:first-child { background:#164b3b; border-color:#164b3b; }
    .admin-users .au-stat:first-child strong, .admin-users .au-stat:first-child span { color:#fff; }
    .admin-users .au-stat:first-child small { color:#dcece3; }

    body.admin-layout .avi .avi-counts { grid-template-columns:repeat(3,minmax(0,1fr)); gap:9px; margin-bottom:16px; }
    .avi .avi-count { min-width:0; min-height:84px; padding:13px 11px; border-radius:14px; }
    .avi .avi-count strong { margin:0; font-size:23px; line-height:1.05; }
    .avi .avi-count span { margin-top:7px; font-size:11px; line-height:1.3; overflow-wrap:normal; }
    .avi .avi-count:first-child { background:#164b3b; border-color:#164b3b; }
    .avi .avi-count:first-child strong, .avi .avi-count:first-child span { color:#fff; }
}
@media (max-width:360px) {
    body.admin-layout .avi .avi-counts { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
/* Mobile-only dashboard bento: preserve the desktop composition and data. */
@media (max-width:600px) {
    .admin-dash { padding:20px 14px 36px; }
    .admin-dash .adm-head { gap:12px; margin-bottom:18px; }
    .admin-dash .adm-head h1 { font-size:clamp(25px,7.2vw,30px); }
    .admin-dash .adm-head p { margin-top:7px; font-size:13px; line-height:1.5; }
    .admin-dash .adm-date { display:flex; flex-wrap:wrap; align-items:center; gap:3px 10px; width:auto; max-width:100%; padding:8px 11px; border-radius:10px; }
    .admin-dash .adm-date strong { font-size:12px; }
    .admin-dash .adm-date span { margin:0; font-size:11px; }
    .admin-dash .adm-stats { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-bottom:14px; }
    .admin-dash .adm-stat { display:flex; flex-direction:column; min-width:0; min-height:138px; padding:14px; border-radius:15px; }
    .admin-dash .adm-stat .adm-stat-icon { width:36px; height:36px; border-radius:10px; }
    .admin-dash .adm-stat .adm-stat-icon svg { width:18px; height:18px; }
    .admin-dash .adm-stat strong { margin:10px 0 0; font-size:25px; line-height:1.05; }
    .admin-dash .adm-stat span:not(.adm-stat-icon) { margin:5px 0 0; font-size:12px; line-height:1.3; }
    .admin-dash .adm-stat small { margin:4px 0 0; font-size:11px; line-height:1.4; }
    .admin-dash .adm-stat:last-child {
        display:grid; grid-column:1 / -1; grid-template-columns:36px minmax(0,1fr) auto;
        grid-template-rows:auto auto; align-items:center; column-gap:12px; row-gap:3px;
        min-height:78px; background:#164b3b; border-color:#164b3b;
    }
    .admin-dash .adm-stat:last-child .adm-stat-icon { grid-column:1; grid-row:1 / 3; background:rgba(255,255,255,.12); color:#f4d684; }
    .admin-dash .adm-stat:last-child strong { grid-column:3; grid-row:1 / 3; margin:0; color:#fff; font-size:27px; }
    .admin-dash .adm-stat:last-child span:not(.adm-stat-icon) { grid-column:2; grid-row:1; margin:0; color:#fff; }
    .admin-dash .adm-stat:last-child small { grid-column:2; grid-row:2; margin:0; color:#dcece3; }
    .admin-dash .adm-tabs { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:3px; overflow:visible; margin-bottom:14px; }
    .admin-dash .adm-tab { min-width:0; min-height:44px; padding:7px 4px; gap:4px; font-size:11px; line-height:1.15; text-align:center; white-space:normal; overflow-wrap:anywhere; }
    .admin-dash .adm-tab svg { width:15px; height:15px; }
    .admin-dash .adm-grid { gap:12px; }
    .admin-dash .adm-card-head { padding:15px; }
    .admin-dash .adm-card-body { padding:14px 15px 16px; }
    .admin-dash .adm-mix, .admin-dash .adm-shortcuts { grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; }
    .admin-dash .adm-mix-row { min-width:0; padding:12px; }
    .admin-dash .adm-mix-row strong { font-size:12px; }
    .admin-dash .adm-mix-row p { font-size:11px; }
    .admin-dash .adm-shortcut { min-width:0; min-height:100px; padding:12px; }
    .admin-dash .adm-shortcut span span { font-size:11px; }
}
@media (prefers-reduced-motion:reduce) {
    .admin-dash *, .admin-users *, .admin-reports *, .avi *, .avd *, .admin-create-user * {
        animation-duration:.01ms !important; animation-iteration-count:1 !important; transition-duration:.01ms !important; scroll-behavior:auto !important;
    }
}
</style>
