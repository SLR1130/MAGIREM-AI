@php
    $noticeRole = auth()->user()?->role;
@endphp
@if(in_array($noticeRole, ['student', 'counselor', 'admin'], true))
<div class="magi-notice" data-notification-center
     data-list-url="{{ route($noticeRole . '.notifications.get', [], false) }}"
     data-read-url="{{ route($noticeRole . '.notifications.read', ['id' => '__ID__'], false) }}"
     data-read-all-url="{{ route($noticeRole . '.notifications.read-all', [], false) }}"
     data-delete-url="{{ route($noticeRole . '.notifications.delete', ['id' => '__ID__'], false) }}"
     data-home-url="{{ route($noticeRole . '.dashboard', [], false) }}"
     data-icon-url="{{ asset('assets/branding/magirem-pwa-192.png') }}">
    <button type="button" class="magi-notice__toggle" data-notice-toggle aria-label="Notifications" aria-expanded="false" aria-controls="magi-notice-panel">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M15 17h5l-1.4-1.4a2 2 0 0 1-.6-1.4V11a6 6 0 0 0-4-5.7V5a2 2 0 1 0-4 0v.3A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1"/></svg>
        <span class="magi-notice__badge" data-notice-badge hidden></span>
    </button>
    <section class="magi-notice__panel" id="magi-notice-panel" aria-labelledby="magi-notice-title" data-notice-panel hidden>
        <div class="magi-notice__header">
            <div><span class="magi-notice__eyebrow">YOUR UPDATES</span><h2 id="magi-notice-title">Notifications</h2><p data-notice-count>Checking for updates…</p></div>
            <button type="button" class="magi-notice__close" data-notice-close aria-label="Close notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="m18 6-12 12M6 6l12 12"/></svg></button>
        </div>
        <div class="magi-notice__toolbar">
            <span>Latest 20 updates</span>
            <div class="magi-notice__toolbar-actions">
                <button type="button" data-notice-browser-alerts hidden>Enable browser alerts</button>
                <button type="button" data-notice-read-all disabled>Mark all as read</button>
            </div>
        </div>
        <p class="magi-notice__status" data-notice-status role="status" aria-live="polite"></p>
        <div class="magi-notice__list" data-notice-list aria-busy="false"><div class="magi-notice__empty">Loading your notifications…</div></div>
    </section>
</div>
@once
    <link rel="stylesheet" href="{{ asset('css/notification-center.css') }}?v={{ filemtime(public_path('css/notification-center.css')) }}">
    <script src="{{ asset('js/notification-center.js') }}?v={{ filemtime(public_path('js/notification-center.js')) }}" defer></script>
@endonce
@endif
