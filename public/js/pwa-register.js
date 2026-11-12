(() => {
    'use strict';

    const script = document.currentScript;
    if (!window.isSecureContext || !('serviceWorker' in navigator) || !script) return;

    const workerUrl = new URL(script.dataset.workerUrl, window.location.href);
    if (workerUrl.origin !== window.location.origin) return;

    window.addEventListener('load', () => {
        navigator.serviceWorker.register(workerUrl.href).catch(() => {
            // The normal website remains available when registration fails.
        });
    });
})();
