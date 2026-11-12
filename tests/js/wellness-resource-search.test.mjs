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
        .replace('@json($guideCount > 0)', 'true')
        .replace('@json($includePublicSources || $publicResultCount > 0)', 'false')
        .replace('@json($includeVideos || $videoResultCount > 0)', 'false');

    class Node {
        constructor() {
            this.listeners = {};
            this.attributes = {};
            this.isConnected = true;
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
        setAttribute(name, value) { this.attributes[name] = String(value); }
        getAttribute(name) { return this.attributes[name] ?? null; }
        removeAttribute(name) { delete this.attributes[name]; }
        append(...nodes) { this.childNodes.push(...nodes); }
        replaceChildren(...nodes) { this.childNodes = nodes; }
        querySelector() { return null; }
        querySelectorAll() { return []; }
        cloneNode() { return new Node(); }
        scrollIntoView() {}
        focus() { this.focused = true; }
        showModal() { this.open = true; }
        close() { this.open = false; this.emit('close'); }
    }

    const names = ['form', 'input', 'web', 'videos', 'button', 'clear', 'local', 'publicGrid', 'videoGrid', 'publicStatus', 'videoStatus', 'status', 'related', 'heading', 'description', 'count', 'tabs', 'empty', 'resultsSection', 'loadingPanel', 'loadingDetail', 'resultsContent', 'featuredAudio', 'videoDialog', 'videoDialogTitle', 'videoFrame', 'videoAttribution', 'videoNote', 'videoSource', 'closeVideo'];
    const nodes = Object.fromEntries(names.map(name => [name, new Node()]));
    nodes.loadingPanel.hidden = true;
    nodes.form.dataset.searchUrl = '/student/resources/search';
    nodes.form.querySelector = selector => ({
        '[name="q"]': nodes.input,
        '[data-public-sources]': nodes.web,
        '[data-video-sources]': nodes.videos,
        '[data-search-button]': nodes.button,
        '[data-clear-search]': nodes.clear,
    }[selector] || null);
    const counters = Object.fromEntries(['all', 'library', 'guides', 'web', 'videos'].map(name => [name, new Node()]));
    const guides = ['stress|academic pressure|burnout', 'sleep|matulog', 'anxiety|worry', 'journal|reflection', 'self-care|wellbeing'].map(keywords => {
        const guide = new Node(); guide.dataset.guideKeywords = keywords; guide.dataset.guideUrl = `https://example.org/${keywords.split('|')[0]}`; return guide;
    });
    const selectors = {
        '[data-wellness-search]': nodes.form,
        '[data-local-results]': nodes.local,
        '[data-public-grid]': nodes.publicGrid,
        '[data-video-grid]': nodes.videoGrid,
        '[data-public-status]': nodes.publicStatus,
        '[data-video-status]': nodes.videoStatus,
        '[data-search-status]': nodes.status,
        '[data-resource-results]': nodes.resultsSection,
        '[data-search-loading]': nodes.loadingPanel,
        '[data-loading-detail]': nodes.loadingDetail,
        '[data-search-content]': nodes.resultsContent,
        '[data-featured-audio-section]': nodes.featuredAudio,
        '[data-video-dialog]': nodes.videoDialog,
        '[data-video-dialog-title]': nodes.videoDialogTitle,
        '[data-video-frame]': nodes.videoFrame,
        '[data-video-attribution]': nodes.videoAttribution,
        '[data-video-note]': nodes.videoNote,
        '[data-video-source]': nodes.videoSource,
        '[data-close-video]': nodes.closeVideo,
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
    document.querySelectorAll = selector => selector === '[data-curated-guide]' ? guides : [];
    document.createElement = () => new Node();
    document.createTextNode = value => ({ textContent: value });
    document.body = new Node();
    document.documentElement = new Node();
    document.visibilityState = 'visible';

    const requests = [];
    const deferred = [];
    let deferNext = false;
    let failNext = false;
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
        if (failNext) { failNext = false; return Promise.reject(new Error('Synthetic network failure')); }
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
        matchMedia: () => ({ matches: false }), addEventListener() {}, scrollTo() {}, scrollY: 0, setTimeout, clearTimeout,
    };
    vm.runInNewContext(script, { window, document, navigator: { onLine: true }, fetch, AbortController, URL, URLSearchParams, setTimeout, clearTimeout, console });
    const settle = () => new Promise(resolve => setImmediate(resolve));
    return {
        nodes, document, requests, deferred, location, settle, guides, counters,
        defer() { deferNext = true; },
        fail() { failNext = true; },
        async runTimers() { const callbacks = [...timers.values()]; timers.clear(); callbacks.forEach(callback => callback()); await settle(); },
    };
}

test('featured audio opens the official player only on click and unloads it on close', () => {
    for (const [provider, id, expected] of [
        ['spotify', '18xva5FyNLPICun7GU3dhc', 'https://open.spotify.com/embed/episode/18xva5FyNLPICun7GU3dhc'],
        ['spotify', '1xXWQnIULL9NgpDG3q6s68', 'https://open.spotify.com/embed/episode/1xXWQnIULL9NgpDG3q6s68'],
        ['nhs', '2163080377001', 'https://players.brightcove.net/79855382001/EkC1XU82e_default/index.html?videoId=2163080377001'],
        ['spotify', '0NAd8OCBQ93Are2orDJRDC', 'https://open.spotify.com/embed/episode/0NAd8OCBQ93Are2orDJRDC'],
    ]) {
        const t = setup();
        const trigger = {dataset: {playerProvider:provider, playerId:id, playerTitle:'Synthetic title', playerSource:'Trusted source'}, href:'https://example.org/source', isConnected:true, focus() { this.focused = true; }};
        let prevented = false;
        assert.equal(t.nodes.videoFrame.childNodes.length, 0, 'No external player is loaded before a click.');
        t.document.emit('click', {preventDefault() { prevented = true; }, target: {closest: selector => selector === '[data-featured-player]' ? trigger : null}});
        assert.equal(prevented, true);
        assert.equal(t.nodes.videoDialog.open, true);
        assert.equal(t.nodes.videoFrame.childNodes[0].src, expected);
        assert.equal(t.nodes.videoSource.href, 'https://example.org/source');
        assert.equal(t.nodes.videoSource.hidden, false);
        if (provider === 'spotify') assert.match(t.nodes.videoFrame.childNodes[0].allow, /encrypted-media/);
        t.nodes.videoDialog.close();
        assert.equal(t.nodes.videoFrame.childNodes.length, 0);
        assert.equal(trigger.focused, true);
    }
});

test('invalid featured player metadata never creates an arbitrary iframe', () => {
    for (const [playerProvider, playerId] of [['spotify', '../../untrusted'], ['nhs', '2163080377002'], ['other', '2163080377001']]) {
        const t = setup();
        const trigger = {dataset: {playerProvider, playerId, playerTitle:'Invalid'}, href:'https://example.org/source'};
        let prevented = false;
        t.document.emit('click', {preventDefault() { prevented = true; }, target: {closest: selector => selector === '[data-featured-player]' ? trigger : null}});
        assert.equal(prevented, false, 'The safe ordinary source link remains the fallback.');
        assert.equal(t.nodes.videoFrame.childNodes.length, 0);
    }
});

test('pending search shows a visible source-specific loading state and restores results on success', async () => {
    const t = setup();
    t.nodes.web.checked = true; t.nodes.videos.checked = true;
    t.nodes.input.value = 'sleep'; t.defer(); t.nodes.form.emit('submit');
    assert.equal(t.nodes.loadingPanel.hidden, false);
    assert.equal(t.nodes.featuredAudio.hidden, true);
    assert.equal(t.nodes.resultsContent.hidden, true);
    assert.equal(t.nodes.resultsSection.getAttribute('aria-busy'), 'true');
    assert.equal(t.nodes.button.getAttribute('aria-label'), 'Searching resources');
    assert.match(t.nodes.loadingDetail.textContent, /MAGIREM AI library, trusted web articles, videos and podcasts/);
    assert.match(t.nodes.status.className, /loading/);
    t.deferred.shift()(); await t.settle();
    assert.equal(t.nodes.loadingPanel.hidden, true);
    assert.equal(t.nodes.resultsContent.hidden, false);
    assert.equal(t.nodes.featuredAudio.hidden, true, 'Examples are separate from an active search.');
    assert.equal(t.nodes.resultsSection.getAttribute('aria-busy'), null);
    assert.equal(t.nodes.button.disabled, false);
    assert.equal(t.nodes.button.getAttribute('aria-label'), 'Search resources');
});

test('failed search hides loading, preserves previous results, and reports an error', async () => {
    const t = setup();
    const previous = new t.nodes.local.constructor();
    t.nodes.local.append(previous);
    t.nodes.input.value = 'stress'; t.fail(); t.nodes.form.emit('submit');
    await t.settle();
    assert.equal(t.nodes.loadingPanel.hidden, true);
    assert.equal(t.nodes.resultsContent.hidden, false);
    assert.equal(t.nodes.featuredAudio.hidden, false, 'Examples return if the new search fails.');
    assert.equal(t.nodes.local.childNodes[0], previous);
    assert.match(t.nodes.status.textContent, /previous results are still available/i);
    assert.match(t.nodes.status.className, /error/);
    assert.equal(t.nodes.button.disabled, false);
});

test('slow search explains the delay and cancellation removes the loading state', async () => {
    const t = setup();
    t.nodes.web.checked = true; t.nodes.input.value = 'stress'; t.defer(); t.nodes.form.emit('submit');
    await t.runTimers();
    assert.match(t.nodes.status.textContent, /Still searching/);
    t.nodes.input.value = 'sleep'; t.nodes.input.emit('input');
    assert.equal(t.nodes.loadingPanel.hidden, true);
    assert.equal(t.nodes.resultsContent.hidden, false);
    assert.equal(t.nodes.resultsSection.getAttribute('aria-busy'), null);
    t.deferred.shift()(); await t.settle();
    assert.equal(t.nodes.loadingPanel.hidden, true);
    assert.match(t.nodes.status.textContent, /Press Search/);
});

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
    assert.equal(t.guides[0].hidden, false);
    assert.equal(t.guides[1].hidden, true);
    assert.equal(t.counters.guides.textContent, '1');
});

test('topic selections show matching trusted guides without enabling external search', async () => {
    for (const [topic, index] of [['Stress', 0], ['Sleep', 1], ['Anxiety', 2], ['Journaling', 3], ['Self-care', 4], ['Academic Pressure', 0], ['Burnout', 0]]) {
        const t = setup();
        const node = { dataset: { topic } };
        t.document.emit('click', { target: { closest: selector => selector === '[data-topic]' ? node : null } });
        await t.settle();
        assert.equal(t.guides[index].hidden, false, `${topic} has a selected guide`);
        assert.equal(t.counters.guides.textContent, '1');
        assert.equal(t.counters.all.textContent, '1');
        assert.equal(t.requests[0].url.searchParams.has('public_sources'), false);
        assert.equal(t.requests[0].url.searchParams.has('videos'), false);
    }
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
    assert.equal(t.nodes.featuredAudio.hidden, false, 'Examples return when the search is cleared.');
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
