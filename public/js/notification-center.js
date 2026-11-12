(() => {
    'use strict';

    const root = document.querySelector('[data-notification-center]');
    if (!root || !window.fetch) return;
    const toggle = root.querySelector('[data-notice-toggle]');
    const panel = root.querySelector('[data-notice-panel]');
    const badge = root.querySelector('[data-notice-badge]');
    const list = root.querySelector('[data-notice-list]');
    const count = root.querySelector('[data-notice-count]');
    const status = root.querySelector('[data-notice-status]');
    const readAll = root.querySelector('[data-notice-read-all]');
    const close = root.querySelector('[data-notice-close]');
    const browserAlerts = root.querySelector('[data-notice-browser-alerts]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let current = [];
    let unread = 0;
    let loaded = false;
    let busy = false;
    let refreshSequence = 0;
    let controller = null;
    let lastSuccessfulRefreshAt = null;
    let pollTimer = null;
    const pageStartedAt = Date.now();

    function element(tag, className, value) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (value !== undefined) node.textContent = String(value ?? '');
        return node;
    }
    function iconSvg(kind) {
        const shapes = {
            appointment: [
                ['rect', { x: '3', y: '4', width: '18', height: '17', rx: '2' }],
                ['line', { x1: '16', y1: '2', x2: '16', y2: '6' }],
                ['line', { x1: '8', y1: '2', x2: '8', y2: '6' }],
                ['line', { x1: '3', y1: '10', x2: '21', y2: '10' }],
                ['path', { d: 'm9 15 2 2 4-4' }],
            ],
            message: [['path', { d: 'M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z' }]],
            community: [
                ['path', { d: 'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2' }],
                ['circle', { cx: '10', cy: '7', r: '4' }],
                ['path', { d: 'M20 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75' }],
            ],
            account: [
                ['path', { d: 'M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z' }],
                ['path', { d: 'm9 12 2 2 4-4' }],
            ],
            bell: [
                ['path', { d: 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9' }],
                ['path', { d: 'M10 21h4' }],
            ],
            close: [['path', { d: 'm18 6-12 12M6 6l12 12' }]],
        };
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        [['viewBox', '0 0 24 24'], ['width', '18'], ['height', '18'], ['fill', 'none'], ['stroke', 'currentColor'], ['stroke-width', '1.8'], ['stroke-linecap', 'round'], ['stroke-linejoin', 'round'], ['aria-hidden', 'true'], ['focusable', 'false']]
            .forEach(([name, value]) => svg.setAttribute(name, value));
        (shapes[kind] || shapes.bell).forEach(([tag, attributes]) => {
            const shape = document.createElementNS('http://www.w3.org/2000/svg', tag);
            Object.entries(attributes).forEach(([name, value]) => shape.setAttribute(name, value));
            svg.append(shape);
        });
        return svg;
    }
    function notificationIcon(type) {
        if (type === 'appointment') return 'appointment';
        if (type === 'message') return 'message';
        if (['community_reaction', 'community_comment', 'community_reply', 'community'].includes(type)) return 'community';
        if (['account', 'system'].includes(type)) return 'account';
        return 'bell';
    }
    function safeDestination(link) {
        if (typeof link !== 'string' || !link.trim()) return null;
        try {
            const url = new URL(link, window.location.origin);
            return url.origin === window.location.origin && ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password ? url.href : null;
        } catch (_) { return null; }
    }
    function timeLabel(value) {
        const date = new Date(value);
        if (!value || Number.isNaN(date.getTime())) return '';
        const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;
        return date.toLocaleDateString();
    }
    function updateBrowserAlertControl() {
        if (!browserAlerts) return;
        if (!window.isSecureContext || !('Notification' in window)) {
            browserAlerts.hidden = true;
            return;
        }
        browserAlerts.hidden = false;
        if (Notification.permission === 'granted') {
            browserAlerts.textContent = 'Browser alerts enabled';
            browserAlerts.disabled = true;
            browserAlerts.setAttribute('aria-pressed', 'true');
            browserAlerts.title = 'Manage notification permission in your browser site settings.';
        } else if (Notification.permission === 'denied') {
            browserAlerts.textContent = 'Browser alerts blocked';
            browserAlerts.disabled = true;
            browserAlerts.setAttribute('aria-pressed', 'false');
            browserAlerts.title = 'Allow notifications for this site in your browser settings.';
        } else {
            browserAlerts.textContent = 'Enable browser alerts';
            browserAlerts.disabled = false;
            browserAlerts.setAttribute('aria-pressed', 'false');
            browserAlerts.removeAttribute('title');
        }
    }
    function browserAlertTitle(item) {
        if (item?.type === 'appointment') return 'Appointment update';
        if (item?.type === 'message') return 'New message';
        if (['community_reaction', 'community_comment', 'community_reply', 'community'].includes(item?.type)) return 'Community activity';
        if (['account', 'system'].includes(item?.type)) return 'Account update';
        return 'MAGIREM AI update';
    }
    async function sendBrowserAlert(item) {
        if (!window.isSecureContext || !('Notification' in window) || Notification.permission !== 'granted') return;
        const alertKey = /^\d+$/.test(String(item?.id)) ? `magirem-alerted-${item.id}` : null;
        if (alertKey) {
            try {
                if (sessionStorage.getItem(alertKey)) return;
                sessionStorage.setItem(alertKey, '1');
            } catch (_) { /* Browser alerting remains optional when storage is restricted. */ }
        }
        const destination = safeDestination(root.dataset.homeUrl);
        const options = {
            body: 'Open MAGIREM AI to review your update.',
            icon: root.dataset.iconUrl || undefined,
            tag: /^\d+$/.test(String(item?.id)) ? `magirem-notice-${item.id}` : 'magirem-account-update',
            renotify: false,
            data: { url: destination || window.location.origin + '/' },
        };
        try {
            if ('serviceWorker' in navigator) {
                const registration = await navigator.serviceWorker.ready;
                if (registration.showNotification) {
                    await registration.showNotification(browserAlertTitle(item), options);
                    return;
                }
            }
            const notification = new Notification(browserAlertTitle(item), options);
            notification.onclick = () => {
                window.focus();
                notification.close();
                setOpen(true);
            };
        } catch (_) {
            // Browser alerts are optional; the in-app notification center remains available.
        }
    }
    function schedulePoll() {
        window.clearTimeout(pollTimer);
        const hidden = document.visibilityState !== 'visible';
        const canAlertInBackground = window.isSecureContext && 'Notification' in window && Notification.permission === 'granted';
        if (hidden && !canAlertInBackground) return;
        pollTimer = window.setTimeout(async () => {
            if (!busy) await refresh({ preserveFocus: true });
            schedulePoll();
        }, hidden ? 30000 : 15000);
    }
    function setCount(value) {
        unread = Number.isFinite(Number(value)) ? Math.max(0, Math.floor(Number(value))) : 0;
        badge.hidden = unread === 0;
        badge.textContent = unread > 99 ? '99+' : String(unread);
        toggle.setAttribute('aria-label', unread ? `Notifications, ${unread} unread` : 'Notifications');
        count.textContent = unread ? `${unread} unread` : 'You are all caught up';
        readAll.disabled = busy || unread === 0;
    }
    async function request(url, method = 'GET', signal) {
        const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (method !== 'GET') {
            if (!csrf) throw new Error('Missing request token');
            headers['X-CSRF-TOKEN'] = csrf;
        }
        const response = await fetch(url, { method, headers, credentials: 'same-origin', cache: 'no-store', signal });
        if (!response.ok) throw new Error('Notification request failed');
        const data = await response.json();
        if (!data || typeof data !== 'object') throw new Error('Invalid notification response');
        return data;
    }
    function endpoint(template, id) {
        const value = String(id);
        if (!/^\d+$/.test(value)) throw new Error('Invalid notification identifier');
        return template.replace('__ID__', encodeURIComponent(value));
    }
    function render() {
        list.replaceChildren();
        if (!current.length) {
            list.append(element('div', 'magi-notice__empty', 'No notifications yet. Updates will appear here.'));
            return;
        }
        current.forEach(item => {
            if (!/^\d+$/.test(String(item.id))) return;
            const row = element('article', `magi-notice__item${item.is_read ? '' : ' is-unread'}`);
            const main = element('button', 'magi-notice__item-main');
            main.type = 'button';
            main.dataset.noticeId = String(item.id);
            main.dataset.noticeAction = 'open';
            main.setAttribute('aria-label', `${item.is_read ? 'Open' : 'Mark as read and open'} notification: ${item.title || 'Update'}`);
            const icon = element('span', 'magi-notice__icon');
            icon.append(iconSvg(notificationIcon(item.type)));
            icon.setAttribute('aria-hidden', 'true');
            const body = element('span', 'magi-notice__body');
            body.append(element('span', 'magi-notice__title', item.title || 'Update'));
            body.append(element('span', 'magi-notice__message', item.message || ''));
            const time = element('time', 'magi-notice__time', timeLabel(item.created_at));
            if (item.created_at) time.dateTime = item.created_at;
            body.append(time); main.append(icon, body);
            main.addEventListener('click', () => openNotification(item));
            const remove = element('button', 'magi-notice__remove');
            remove.append(iconSvg('close'));
            remove.type = 'button';
            remove.dataset.noticeId = String(item.id);
            remove.dataset.noticeAction = 'delete';
            remove.setAttribute('aria-label', `Delete notification: ${item.title || 'Update'}`);
            remove.addEventListener('click', async () => {
                if (await mutate(endpoint(root.dataset.deleteUrl, item.id), 'DELETE')) await refresh();
            });
            row.append(main, remove); list.append(row);
        });
    }
    async function refresh({ showLoading = false, preserveFocus = false } = {}) {
        const sequence = ++refreshSequence;
        if (controller) controller.abort();
        controller = new AbortController();
        if (showLoading && !loaded) list.setAttribute('aria-busy', 'true');
        try {
            const data = await request(root.dataset.listUrl, 'GET', controller.signal);
            if (sequence !== refreshSequence || busy) return;
            if (!Array.isArray(data.notifications) || !Number.isInteger(data.unreadCount) || data.unreadCount < 0) throw new Error('Invalid notification response');
            const wasLoaded = loaded;
            const previousIds = new Set(current.map(item => String(item.id)));
            const refreshCutoff = lastSuccessfulRefreshAt === null ? null : lastSuccessfulRefreshAt - 60000;
            const newUnread = data.notifications.filter(item => {
                if (item.is_read || previousIds.has(String(item.id))) return false;
                const createdAt = new Date(item.created_at).getTime();
                if (!Number.isFinite(createdAt)) return false;
                // A transaction often redirects back to this page after writing
                // the notification. Alert for recent first-load events too; the
                // session key prevents a refresh from repeating the OS alert.
                return wasLoaded
                    ? (refreshCutoff === null || createdAt >= refreshCutoff)
                    : createdAt >= pageStartedAt - 60000;
            });
            current = data.notifications;
            loaded = true;
            lastSuccessfulRefreshAt = Date.now();
            setCount(data.unreadCount);
            status.textContent = '';
            const focused = preserveFocus && list.contains(document.activeElement)
                ? { id: document.activeElement.dataset.noticeId, action: document.activeElement.dataset.noticeAction }
                : null;
            render();
            if (newUnread.length) {
                for (const item of newUnread) await sendBrowserAlert(item);
            }
            if (focused?.id && /^\d+$/.test(focused.id) && ['open', 'delete'].includes(focused.action)) {
                list.querySelector(`[data-notice-id="${focused.id}"][data-notice-action="${focused.action}"]`)?.focus();
            }
        } catch (error) {
            if (sequence !== refreshSequence || error.name === 'AbortError') return;
            status.textContent = 'Could not refresh notifications. Please try again.';
            if (!loaded) { count.textContent = 'Updates unavailable'; list.replaceChildren(element('div', 'magi-notice__empty', 'Notifications could not be loaded.')); }
        } finally {
            if (sequence === refreshSequence) { controller = null; list.setAttribute('aria-busy', 'false'); }
        }
    }
    async function mutate(url, method) {
        if (busy) return false;
        busy = true;
        ++refreshSequence;
        if (controller) controller.abort();
        controller = null;
        readAll.disabled = true;
        status.textContent = '';
        try {
            const data = await request(url, method);
            if (data.success !== true) throw new Error('Notification action failed');
            return true;
        } catch (_) {
            status.textContent = 'Could not update this notification. Please try again.';
            return false;
        } finally { busy = false; readAll.disabled = unread === 0; }
    }
    async function openNotification(item) {
        const destination = safeDestination(item.link);
        if (!item.is_read) {
            const success = await mutate(endpoint(root.dataset.readUrl, item.id), 'POST');
            if (!success) return;
        }
        if (destination) window.location.assign(destination);
        else {
            await refresh();
            if (item.link) status.textContent = 'This notification link is unavailable.';
        }
    }
    function setOpen(open, returnFocus = false) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (open) refresh({ showLoading: true });
        else if (returnFocus) toggle.focus();
    }
    toggle.addEventListener('click', () => setOpen(panel.hidden));
    close.addEventListener('click', () => setOpen(false, true));
    browserAlerts?.addEventListener('click', async () => {
        if (!window.isSecureContext || !('Notification' in window)) return;
        try {
            const permission = await Notification.requestPermission();
            updateBrowserAlertControl();
            status.textContent = permission === 'granted'
                ? 'Browser alerts are enabled. Keep MAGIREM AI open to receive alerts for new activity.'
                : 'Browser alerts were not enabled. In-app updates will continue while you use MAGIREM AI.';
            schedulePoll();
        } catch (_) {
            status.textContent = 'Browser alerts are unavailable. In-app notifications will continue to work.';
        }
    });
    readAll.addEventListener('click', async () => { if (await mutate(root.dataset.readAllUrl, 'POST')) await refresh(); });
    document.addEventListener('click', event => { if (!panel.hidden && !root.contains(event.target)) setOpen(false); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) setOpen(false, true); });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && !busy) refresh({ preserveFocus: true });
        schedulePoll();
    });
    navigator.serviceWorker?.addEventListener('message', event => {
        if (event.data?.type === 'MAGIREM_OPEN_NOTIFICATIONS') setOpen(true);
    });
    updateBrowserAlertControl();
    refresh({ showLoading: true }).finally(schedulePoll);
})();
