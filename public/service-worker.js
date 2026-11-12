'use strict';

// Deliberately network-only: no student pages, messages, or API responses are
// stored in Cache Storage. An offline navigation receives only this generic page.
const offlinePage = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#103f32"><title>Offline · MAGIREM AI</title>
<style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f6f3e8;color:#103f32;font-family:system-ui,sans-serif}main{max-width:480px;background:#fff;padding:32px;border-radius:24px;box-shadow:0 12px 36px #103f3218}h1{font-size:1.6rem}p{line-height:1.6}button{border:0;border-radius:999px;background:#103f32;color:#fff;padding:12px 20px;font:inherit;cursor:pointer}</style></head>
<body><main><p>MAGIREM AI</p><h1>You’re offline</h1><p>Reconnect to the internet to access your account and wellness resources. Your private information is not stored for offline use.</p><button type="button" onclick="location.reload()">Try again</button></main></body></html>`;

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET' || event.request.mode !== 'navigate') return;
    event.respondWith(fetch(event.request).catch(() => new Response(offlinePage, {
        status: 503,
        headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' },
    })));
});

// Browser alerts are raised only after the signed-in page's user-opted-in
// notification poll discovers a new server-side notification. This is not a
// Web Push subscription and will not wake a closed browser/app.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const dataUrl = event.notification.data && event.notification.data.url;
    let destination = new URL(self.registration.scope);
    try {
        const candidate = new URL(dataUrl || self.registration.scope);
        if (candidate.origin === self.location.origin) destination = candidate;
    } catch (_) {
        // Fall back to this app's own scope for malformed notification data.
    }

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) {
            if (new URL(client.url).origin !== self.location.origin) continue;
            await client.focus();
            client.postMessage({ type: 'MAGIREM_OPEN_NOTIFICATIONS' });
            return;
        }
        await self.clients.openWindow(destination.href);
    })());
});
