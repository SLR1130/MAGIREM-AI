@extends('layouts.app')

@section('title', 'All Notifications')

@section('content')
<style>
    .notif-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        gap: 16px;
        flex-wrap: wrap;
    }
    .notif-page-title {
        font-size: 22px;
        font-weight: 750;
        color: var(--color-text-primary);
        margin: 0;
    }
    .notif-page-sub {
        font-size: 13px;
        color: var(--color-text-secondary);
        margin: 4px 0 0;
    }
    .notif-mark-all-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        border: 1px solid var(--color-border-base);
        border-radius: 10px;
        background: #fff;
        color: var(--color-primary);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all .18s;
        text-decoration: none;
    }
    .notif-mark-all-btn:hover { background: #eaf4ed; border-color: #a9cab8; }
    .notif-mark-all-btn:disabled { opacity: .5; cursor: default; }

    .notif-list { display: flex; flex-direction: column; gap: 10px; }
    .notif-card {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 16px 18px;
        border-radius: 14px;
        border: 1px solid var(--color-border-light);
        background: #fff;
        transition: box-shadow .15s;
        position: relative;
    }
    .notif-card.is-unread { background: #edf6ee; border-color: #c2dfc9; }
    .notif-card:hover { box-shadow: 0 4px 16px rgba(16,63,50,.07); }
    .notif-card-icon {
        width: 42px; height: 42px; border-radius: 12px;
        background: #deeee2; color: #166148;
        display: grid; place-items: center; flex: none;
    }
    .notif-card-icon svg { width: 20px; height: 20px; }
    .notif-card-body { flex: 1; min-width: 0; }
    .notif-card-title { font-size: 14px; font-weight: 700; color: var(--color-text-primary); margin: 0 0 4px; }
    .notif-card-msg { font-size: 13px; color: var(--color-text-secondary); margin: 0 0 6px; line-height: 1.5; }
    .notif-card-time { font-size: 11px; color: var(--color-text-tertiary); }
    .notif-card-actions { display: flex; gap: 8px; align-items: center; flex: none; }
    .notif-delete-btn {
        width: 34px; height: 34px; border-radius: 9px;
        border: 0; background: transparent; color: #71867a;
        cursor: pointer; display: grid; place-items: center; transition: all .15s;
    }
    .notif-delete-btn:hover { background: #f9e9e5; color: #a34436; }
    .notif-delete-btn svg { width: 16px; height: 16px; }
    .notif-unread-dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: #2b8a5e; flex: none; margin-top: 6px;
    }
    .notif-empty-state {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 72px 24px; text-align: center;
        background: #fff; border-radius: 16px; border: 1px solid var(--color-border-light);
    }
    .notif-empty-state svg { opacity: .3; width: 56px; height: 56px; margin-bottom: 16px; }
    .notif-empty-state h3 { margin: 0 0 8px; font-size: 16px; font-weight: 700; color: var(--color-text-primary); }
    .notif-empty-state p { margin: 0; color: var(--color-text-secondary); font-size: 13px; }
    .notif-status-bar {
        padding: 10px 16px; background: #fff7f3; border: 1px solid #f2ded6;
        border-radius: 10px; color: #9d4435; font-size: 13px; margin-bottom: 16px;
        display: none;
    }
    .notif-status-bar.show { display: block; }
    .notif-pagination { margin-top: 24px; display: flex; justify-content: center; }
    .notif-pagination .pagination { gap: 4px; display: flex; list-style: none; padding: 0; margin: 0; }
    .notif-pagination .page-item .page-link {
        padding: 7px 14px; border-radius: 9px; border: 1px solid var(--color-border-base);
        background: #fff; color: var(--color-primary); font-size: 13px; font-weight: 600;
        text-decoration: none; display: block; transition: all .15s;
    }
    .notif-pagination .page-item.active .page-link { background: #17634c; color: #fff; border-color: #17634c; }
    .notif-pagination .page-item.disabled .page-link { opacity: .4; cursor: default; }
    .notif-pagination .page-item .page-link:hover:not(.disabled) { background: #eaf4ed; }
</style>

<div class="notif-page-header">
    <div>
        <h1 class="notif-page-title">All Notifications</h1>
        <p class="notif-page-sub">
            @if($unreadCount > 0)
                {{ $unreadCount }} unread · {{ $notifications->total() }} total
            @else
                {{ $notifications->total() }} total notifications
            @endif
        </p>
    </div>
    @if($unreadCount > 0)
    <button type="button" class="notif-mark-all-btn" id="markAllReadBtn">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        Mark all as read
    </button>
    @endif
</div>

<div class="notif-status-bar" id="notifStatusBar"></div>

@if($notifications->isEmpty())
<div class="notif-empty-state">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4"
              d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
    </svg>
    <h3>No notifications yet</h3>
    <p>Updates and alerts will appear here.</p>
</div>
@else
<div class="notif-list" id="notifList">
    @foreach($notifications as $notif)
    <div class="notif-card {{ $notif->is_read ? '' : 'is-unread' }}" id="notif-row-{{ $notif->id }}">
        @if(!$notif->is_read)
        <div class="notif-unread-dot"></div>
        @endif
        <div class="notif-card-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                @if(in_array($notif->type, ['account','system']))
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11zM9 12l2 2 4-4"/>
                @elseif($notif->type === 'appointment')
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2zM9 15l2 2 4-4"/>
                @elseif($notif->type === 'message')
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M21 11.5a8.4 8.4 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.4 8.4 0 01-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.4 8.4 0 013.8-.9h.5a8.5 8.5 0 018 8z"/>
                @else
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M18 8a6 6 0 00-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
                @endif
            </svg>
        </div>
        <div class="notif-card-body">
            <p class="notif-card-title">{{ $notif->title ?? 'Notification' }}</p>
            <p class="notif-card-msg">{{ $notif->message ?? '' }}</p>
            <span class="notif-card-time">{{ $notif->created_at->diffForHumans() }}</span>
        </div>
        <div class="notif-card-actions">
            <button class="notif-delete-btn" data-delete-id="{{ $notif->id }}" title="Delete notification" aria-label="Delete notification">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m18 6-12 12M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
    @endforeach
</div>

<div class="notif-pagination">
    {{ $notifications->links() }}
</div>
@endif

<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const statusBar = document.getElementById('notifStatusBar');

    function showStatus(msg, isError = true) {
        statusBar.textContent = msg;
        statusBar.classList.add('show');
        setTimeout(() => statusBar.classList.remove('show'), isError ? 6000 : 3000);
    }

    async function apiCall(url, method) {
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
        };
        const res = await fetch(url, { method, headers, credentials: 'same-origin', cache: 'no-store' });
        if (!res.ok) throw new Error('Request failed');
        const data = await res.json();
        if (!data || data.success !== true) throw new Error('Action failed');
        return data;
    }

    // Delete individual notification
    document.querySelectorAll('[data-delete-id]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const id = btn.dataset.deleteId;
            const row = document.getElementById('notif-row-' + id);
            btn.disabled = true;
            try {
                await apiCall('/admin/api/notifications/' + id, 'DELETE');
                if (row) {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 320);
                }
            } catch (_) {
                btn.disabled = false;
                showStatus('Could not delete notification. Please try again.', true);
            }
        });
    });

    // Mark all as read
    const markAllBtn = document.getElementById('markAllReadBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', async function () {
            markAllBtn.disabled = true;
            try {
                await apiCall('/admin/api/notifications/read-all', 'POST');
                document.querySelectorAll('.notif-card.is-unread').forEach(function (card) {
                    card.classList.remove('is-unread');
                    const dot = card.querySelector('.notif-unread-dot');
                    if (dot) dot.remove();
                });
                markAllBtn.style.display = 'none';
                showStatus('All notifications marked as read.', false);
            } catch (_) {
                markAllBtn.disabled = false;
                showStatus('Could not mark all as read. Please try again.', true);
            }
        });
    }
})();
</script>
@endsection
