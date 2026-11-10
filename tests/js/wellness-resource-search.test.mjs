import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// Execute the real inline search code with a minimal DOM and fake providers.
// No student accounts, database, or external network requests are used.
function setup() {
    const blade = readFileSync(new URL('../../resources/views/student/resources.blade.php', import.meta.url), 'utf8');
    const start = blade.lastIndexOf('<script>') + '<script>'.length;
    const end = blade.indexOf('</script>', start);
    assert.ok(start >= '<script>'.length && end > start, 'search script exists');
    const script = blade.slice(start, end)
        .replace('@json($includePublicSources || $publicResultCount > 0)', 'false')
        .replace('@json($includeVideos || $videoResultCount > 0)', 'false');

    class Node {
        constructor() {
            this.listeners = {};
            this.dataset = {};
            this.childNodes = [];
            this.classList = { add() {}, remove() {}, toggle() {} };
            this.value = '';
            this.checked = false;
            this.disabled = false;
            this.hidden = false;
            this.textContent = '';
        }
        addEventListener(type, callback) { (this.listeners[type] ||= []).push(callback); }
        emit(type, event = {}) { for (const listener of this.listeners[type] || []) listener({ preventDefault() {}, ...event }); }
        setAttribute() {}
        removeAttribute() {}
        append(...nodes) { this.childNodes.push(...nodes); }
        replaceChildren(...nodes) { this.childNodes = nodes; }
        querySelector() { return null; }
        querySelectorAll() { return []; }
        cloneNode() { return new Node(); }
        scrollIntoView() {}
        focus() {}
    }

    const names = ['form', 'input', 'web', 'videos', 'button', 'clear', 'local', 'publicGrid', 'videoGrid', 'publicStatus', 'videoStatus', 'status', 'related', 'heading', 'description', 'count', 'tabs', 'empty'];
    const nodes = Object.fromEntries(names.map(name => [name, new Node()]));
    nodes.form.dataset.searchUrl = '/student/resources/search';
    nodes.form.querySelector = selector => ({
        '[name="q"]': nodes.input,
        '[data-public-sources]': nodes.web,
        '[data-video-sources]': nodes.videos,
        '[data-search-button]': nodes.button,
        '[data-clear-search]': nodes.clear,
    }[selector] || null);
    const counters = Object.fromEntries(['all', 'library', 'web', 'videos'].map(name => [name, new Node()]));
    const selectors = {
        '[data-wellness-search]': nodes.form,
        '[data-local-results]': nodes.local,
        '[data-public-grid]': nodes.publicGrid,
        '[data-video-grid]': nodes.videoGrid,
        '[data-public-status]': nodes.publicStatus,
        '[data-video-status]': nodes.videoStatus,
        '[data-search-status]': nodes.status,
        '[data-related-topics]': nodes.related,
        '#results-title': nodes.heading,
        '[data-results-description]': nodes.description,
        '[data-result-count]': nodes.count,
        '[data-source-tabs]': nodes.tabs,
        '[data-overall-empty]': nodes.empty,
        ...Object.fromEntries(Object.entries(counters).map(([name, node]) => [`[data-tab-count="${name}"]`, node])),
    };
    const document = new Node();
    document.querySelector = selector => selectors[selector] || null;
    document.querySelectorAll = () => [];
    document.createElement = () => new Node();
    document.createTextNode = value => ({ textContent: value });
    document.body = new Node();
    document.documentElement = new Node();
    document.visibilityState = 'visible';

    const requests = [];
    const deferred = [];
    let deferNext = false;
    function responseFor(url) {
        const params = new URL(url, 'http://localhost').searchParams;
        const query = params.get('q') || '';
        const web = Boolean(query && params.get('public_sources') === '1');
        const videos = Boolean(query && params.get('videos') === '1');
        return { ok: true, json: async () => ({
            query, public_requested: web, videos_requested: videos,
            local: { items: [], pagination: { total: 0 } }, public: [], videos: [],
            counts: { library: 0, web: 0, videos: 0, all: 0 },
            errors: [], provider_errors: { web: [], videos: [] },
            providers: { web: { available: web }, videos: { available: videos } }, related_topics: [],
        }) };
    }
    const fetch = (url, options) => {
        requests.push({ url: new URL(url, 'http://localhost'), signal: options.signal });
        if (deferNext) {
            deferNext = false;
            return new Promise(resolve => deferred.push(() => resolve(responseFor(url))));
        }
        return Promise.resolve(responseFor(url));
    };
    let timerId = 0;
    const timers = new Map();
    const setTimeout = callback => { const id = ++timerId; timers.set(id, callback); return id; };
    const clearTimeout = id => timers.delete(id);
    const location = { href: 'http://localhost/student/resources', origin: 'http://localhost' };
    const window = {
        fetch, AbortController, location,
        history: { pushState(_a, _b, url) { location.href = String(url); }, replaceState(_a, _b, url) { location.href = String(url); } },
        matchMedia: () => ({ matches: false }), addEventListener() {}, setTimeout, clearTimeout,
    };
    vm.runInNewContext(script, { window, document, navigator: { onLine: true }, fetch, AbortController, URL, URLSearchParams, setTimeout, clearTimeout, console });
    const settle = () => new Promise(resolve => setImmediate(resolve));
    return {
        nodes, document, requests, deferred, location, settle,
        defer() { deferNext = true; },
        async runTimers() { const callbacks = [...timers.values()]; timers.clear(); callbacks.forEach(callback => callback()); await settle(); },
    };
}

test('typing with no opt-in stays local and never enables external sources', async () => {
    const t = setup();
    t.nodes.input.value = 'stress';
    t.nodes.input.emit('input');
    await t.runTimers();
    assert.equal(t.requests.length, 1);
    assert.equal(t.requests[0].url.searchParams.get('q'), 'stress');
    assert.equal(t.requests[0].url.searchParams.has('public_sources'), false);
    assert.equal(t.requests[0].url.searchParams.has('videos'), false);
    assert.equal(t.nodes.web.checked, false);
    assert.equal(t.nodes.videos.checked, false);
});

test('selected sources remain selected for new queries, topic clicks, and clear', async () => {
    const t = setup();
    t.nodes.web.checked = true; t.nodes.web.emit('change');
    t.nodes.videos.checked = true; t.nodes.videos.emit('change');
    t.nodes.input.value = 'stress'; t.nodes.form.emit('submit'); await t.settle();
    assert.equal(t.requests[0].url.searchParams.get('public_sources'), '1');
    assert.equal(t.requests[0].url.searchParams.get('videos'), '1');

    t.nodes.input.value = 'sleep'; t.nodes.input.emit('input'); await t.runTimers();
    assert.equal(t.requests.length, 1, 'nothing is sent while typing with external sources selected');
    assert.match(t.nodes.status.textContent, /Press Search/);
    t.nodes.form.emit('submit'); await t.settle();
    assert.equal(t.requests[1].url.searchParams.get('q'), 'sleep');
    assert.equal(t.requests[1].url.searchParams.get('public_sources'), '1');
    assert.equal(t.requests[1].url.searchParams.get('videos'), '1');

    const topic = { dataset: { topic: 'Anxiety education' } };
    t.document.emit('click', { target: { closest: selector => selector === '[data-topic]' ? topic : null } });
    await t.settle();
    assert.equal(t.requests[2].url.searchParams.get('q'), 'Anxiety education');
    assert.equal(t.requests[2].url.searchParams.get('public_sources'), '1');
    assert.equal(t.requests[2].url.searchParams.get('videos'), '1');

    t.nodes.clear.emit('click'); await t.settle();
    assert.equal(t.nodes.web.checked, true);
    assert.equal(t.nodes.videos.checked, true);
    assert.equal(t.requests[3].url.searchParams.has('public_sources'), false, 'blank clear does not call providers');
    assert.equal(t.requests[3].url.searchParams.has('videos'), false);
    assert.equal(new URL(t.location.href).searchParams.get('public_sources'), '1');
    assert.equal(new URL(t.location.href).searchParams.get('videos'), '1');

    t.nodes.web.checked = false; t.nodes.web.emit('change');
    t.nodes.input.value = 'study'; t.nodes.form.emit('submit'); await t.settle();
    assert.equal(t.requests[4].url.searchParams.has('public_sources'), false);
    assert.equal(t.requests[4].url.searchParams.get('videos'), '1');
});

test('changing the query or source cancels stale in-flight results', async () => {
    const t = setup();
    t.nodes.web.checked = true; t.nodes.web.emit('change');
    t.nodes.input.value = 'stress'; t.defer(); t.nodes.form.emit('submit');
    assert.equal(t.nodes.button.disabled, true);
    t.nodes.input.value = 'sleep'; t.nodes.input.emit('input');
    assert.equal(t.requests[0].signal.aborted, true);
    assert.equal(t.nodes.button.disabled, false);
    t.deferred.shift()(); await t.settle();
    assert.equal(t.nodes.web.checked, true);
    assert.match(t.nodes.status.textContent, /Press Search/);
    assert.doesNotMatch(t.nodes.heading.textContent, /stress/);
    t.nodes.form.emit('submit'); await t.settle();
    assert.equal(t.requests[1].url.searchParams.get('q'), 'sleep');
    assert.equal(t.requests[1].url.searchParams.get('public_sources'), '1');
});
