import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function setup(initial) {
    class Node {
        constructor(tagName = 'div') { this.tagName = tagName; this.listeners = {}; this.childNodes = []; this.dataset = {}; this.attributes = {}; this.hidden = false; this.disabled = false; this.textContent = ''; }
        addEventListener(type, listener) { (this.listeners[type] ||= []).push(listener); }
        async emit(type, event = {}) { await Promise.all((this.listeners[type] || []).map(listener => listener({ target: this, key: '', ...event }))); }
        setAttribute(name, value) { this.attributes[name] = String(value); }
        getAttribute(name) { return this.attributes[name]; }
        append(...nodes) { this.childNodes.push(...nodes); }
        replaceChildren(...nodes) { this.childNodes = nodes; }
        contains(target) { return target === this || this.childNodes.some(node => node.contains?.(target)); }
        focus() { document.activeElement = this; }
        set innerHTML(_) { throw new Error('Notification UI must not insert HTML'); }
    }
    const names = ['root', 'toggle', 'panel', 'badge', 'list', 'count', 'status', 'readAll', 'close'];
    const nodes = Object.fromEntries(names.map(name => [name, new Node()]));
    nodes.panel.hidden = true;
    nodes.root.dataset = { listUrl: '/student/api/notifications', readUrl: '/student/api/notifications/__ID__/read', readAllUrl: '/student/api/notifications/read-all', deleteUrl: '/student/api/notifications/__ID__' };
    nodes.root.querySelector = selector => ({
        '[data-notice-toggle]': nodes.toggle, '[data-notice-panel]': nodes.panel, '[data-notice-badge]': nodes.badge,
        '[data-notice-list]': nodes.list, '[data-notice-count]': nodes.count, '[data-notice-status]': nodes.status,
        '[data-notice-read-all]': nodes.readAll, '[data-notice-close]': nodes.close,
    }[selector]);
    nodes.root.childNodes = [nodes.toggle, nodes.panel];
    nodes.panel.childNodes = [nodes.list];
    const document = new Node();
    document.visibilityState = 'visible';
    document.querySelector = selector => selector === '[data-notification-center]' ? nodes.root : selector === 'meta[name="csrf-token"]' ? { content: 'synthetic-csrf' } : null;
    document.createElement = tagName => new Node(tagName);
    document.createElementNS = (_namespace, tagName) => new Node(tagName);
    let store = initial.map(item => ({ ...item }));
    const requests = [];
    let failNext = false;
    let holdNextGet = false;
    let releaseGet = null;
    let timer;
    const fetch = async (url, options) => {
        requests.push({ url, ...options });
        if (failNext) { failNext = false; return { ok: false }; }
        const method = options.method;
        if (method === 'GET') {
            const payload = { notifications: store.map(item => ({ ...item })), unreadCount: store.filter(item => !item.is_read).length };
            const response = { ok: true, json: async () => payload };
            if (holdNextGet) { holdNextGet = false; return new Promise(resolve => { releaseGet = () => resolve(response); }); }
            return response;
        }
        assert.equal(options.headers['X-CSRF-TOKEN'], 'synthetic-csrf');
        if (url.endsWith('/read-all')) store.forEach(item => { item.is_read = true; });
        else if (method === 'DELETE') store = store.filter(item => String(item.id) !== url.split('/').at(-1));
        else { const id = url.split('/').at(-2); const item = store.find(notice => String(notice.id) === id); if (item) item.is_read = true; }
        return { ok: true, json: async () => ({ success: true }) };
    };
    let destination = null;
    const window = { fetch, clearTimeout() {}, setTimeout(callback) { timer = callback; return 1; }, location: { origin: 'http://localhost:8000', assign(value) { destination = value; } } };
    const script = readFileSync(new URL('../../public/js/notification-center.js', import.meta.url), 'utf8');
    vm.runInNewContext(script, { window, document, navigator: { serviceWorker: null }, fetch, URL, AbortController, Date, Number, String, setInterval(callback) { timer = callback; } });
    const settle = () => new Promise(resolve => setImmediate(resolve));
    return { nodes, requests, settle, poll: () => timer(), fail() { failNext = true; }, holdGet() { holdNextGet = true; }, releaseGet() { releaseGet(); }, get destination() { return destination; }, get store() { return store; } };
}

const item = (id, attributes = {}) => ({ id, type: 'message', title: 'Update', message: 'Synthetic message', link: '/student/appointments', is_read: false, created_at: '2026-11-10T00:00:00Z', ...attributes });

test('loads accurate unread count and renders untrusted text without inserting HTML', async () => {
    const t = setup([item(1, { title: '<img src=x onerror=alert(1)>' }), item(2, { is_read: true })]);
    await t.settle();
    assert.equal(t.nodes.badge.textContent, '1');
    assert.equal(t.nodes.toggle.getAttribute('aria-label'), 'Notifications, 1 unread');
    assert.equal(t.nodes.list.childNodes.length, 2);
    assert.equal(t.nodes.list.childNodes[0].childNodes[0].childNodes[1].childNodes[0].textContent, '<img src=x onerror=alert(1)>');
    await t.nodes.toggle.emit('click'); await t.settle();
    assert.equal(t.nodes.panel.hidden, false);
    assert.equal(t.nodes.toggle.getAttribute('aria-expanded'), 'true');
});

test('renders notification types and delete action with accessible inline SVG icons', async () => {
    const t = setup([
        item(1, { type: 'appointment' }),
        item(2, { type: 'message' }),
        item(3, { type: 'community_comment' }),
        item(4, { type: 'account' }),
    ]);
    await t.settle();

    for (const row of t.nodes.list.childNodes) {
        const iconWrap = row.childNodes[0].childNodes[0];
        const icon = iconWrap.childNodes[0];
        assert.equal(icon.tagName, 'svg');
        assert.equal(icon.getAttribute('aria-hidden'), 'true');
        assert.equal(icon.getAttribute('stroke'), 'currentColor');
        assert.ok(icon.childNodes.length > 0);
    }

    const remove = t.nodes.list.childNodes[0].childNodes[1];
    assert.equal(remove.getAttribute('aria-label'), 'Delete notification: Update');
    assert.equal(remove.childNodes[0].tagName, 'svg');
    assert.equal(remove.childNodes[0].getAttribute('aria-hidden'), 'true');
});

test('mark read and delete use CSRF and update only after success', async () => {
    const t = setup([item(1), item(2)]); await t.settle();
    await t.nodes.list.childNodes[0].childNodes[0].emit('click');
    assert.equal(t.store[0].is_read, true);
    assert.equal(t.destination, 'http://localhost:8000/student/appointments');
    assert.equal(t.requests.find(request => request.method === 'POST').headers['X-CSRF-TOKEN'], 'synthetic-csrf');
    t.fail();
    await t.nodes.list.childNodes[1].childNodes[1].emit('click');
    assert.equal(t.store.length, 2);
    assert.match(t.nodes.status.textContent, /Could not update/);
    await t.nodes.list.childNodes[1].childNodes[1].emit('click');
    assert.equal(t.store.length, 1);
    assert.equal(t.nodes.badge.hidden, true);
});

test('unsafe external links cannot redirect and mark-all updates the count', async () => {
    const t = setup([item(1, { link: 'https://outside.example/steal' }), item(2)]); await t.settle();
    await t.nodes.list.childNodes[0].childNodes[0].emit('click');
    assert.equal(t.destination, null);
    assert.match(t.nodes.status.textContent, /link is unavailable/);
    await t.nodes.readAll.emit('click');
    assert.equal(t.store.every(notice => notice.is_read), true);
    assert.equal(t.nodes.badge.hidden, true);
    assert.equal(t.nodes.count.textContent, 'You are all caught up');
});

test('refresh failure keeps the last count instead of falsely showing zero', async () => {
    const t = setup([item(1)]); await t.settle();
    t.fail(); t.poll(); await t.settle();
    assert.equal(t.nodes.badge.textContent, '1');
    assert.equal(t.nodes.badge.hidden, false);
    assert.match(t.nodes.status.textContent, /Could not refresh/);
});

test('an older refresh cannot restore a notification after a successful delete', async () => {
    const t = setup([item(1), item(2)]); await t.settle();
    t.holdGet(); t.poll(); await t.settle();
    await t.nodes.list.childNodes[0].childNodes[1].emit('click');
    assert.equal(t.nodes.list.childNodes.length, 1);
    t.releaseGet(); await t.settle();
    assert.equal(t.nodes.list.childNodes.length, 1);
    assert.equal(t.nodes.badge.textContent, '1');
});
