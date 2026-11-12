import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function worker(fetchImpl) {
    const handlers = {};
    const context = {
        self: { addEventListener: (name, handler) => { handlers[name] = handler; } },
        fetch: fetchImpl,
        Response,
    };
    vm.runInNewContext(readFileSync('public/service-worker.js', 'utf8'), context);
    return handlers;
}

test('online navigations return the actual network response', async () => {
    const original = new Response('network result');
    const handlers = worker(async () => original);
    let response;
    handlers.fetch({ request: { method: 'GET', mode: 'navigate' }, respondWith: value => { response = value; } });
    assert.equal(await response, original);
});

test('offline navigation returns a generic no-store page without user data', async () => {
    const handlers = worker(async () => { throw new TypeError('offline'); });
    let response;
    handlers.fetch({ request: { method: 'GET', mode: 'navigate' }, respondWith: value => { response = value; } });
    const result = await response;
    assert.equal(result.status, 503);
    assert.equal(result.headers.get('cache-control'), 'no-store');
    assert.match(await result.text(), /You’re offline/);
});

test('API, image, and mutation requests are not intercepted or cached', () => {
    const handlers = worker(() => { throw new Error('unexpected fetch'); });
    for (const request of [
        { method: 'GET', mode: 'cors' },
        { method: 'GET', mode: 'no-cors' },
        { method: 'POST', mode: 'navigate' },
    ]) {
        let intercepted = false;
        handlers.fetch({ request, respondWith: () => { intercepted = true; } });
        assert.equal(intercepted, false);
    }
});

test('registration requires a secure context and a same-origin worker', async () => {
    const source = readFileSync('public/js/pwa-register.js', 'utf8');
    async function attempt(secure, workerUrl) {
        let onLoad;
        const registrations = [];
        vm.runInNewContext(source, {
            URL,
            document: { currentScript: { dataset: { workerUrl } } },
            window: { isSecureContext: secure, location: { href: 'https://example.test/' , origin: 'https://example.test' }, addEventListener: (_, fn) => { onLoad = fn; } },
            navigator: { serviceWorker: { register: async url => { registrations.push(url); } } },
        });
        if (onLoad) onLoad();
        return registrations;
    }
    assert.deepEqual(await attempt(false, '/service-worker.js'), []);
    assert.deepEqual(await attempt(true, 'https://other.test/service-worker.js'), []);
    assert.deepEqual(await attempt(true, '/service-worker.js'), ['https://example.test/service-worker.js']);
});
