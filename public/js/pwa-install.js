(() => {
    'use strict';

    const panel = document.querySelector('[data-pwa-install]');
    if (!panel) return;
    const action = panel.querySelector('[data-pwa-install-action]');
    const dismiss = panel.querySelector('[data-pwa-install-dismiss]');
    const help = panel.querySelector('[data-pwa-install-help]');
    const mobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const ios = /iPhone|iPad|iPod/i.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    if (!mobile || standalone) return;

    let promptEvent = null;
    let dismissed = false;
    panel.hidden = false;

    if (!window.isSecureContext) {
        action.hidden = true;
        help.textContent = 'Installation requires an HTTPS connection. Open MAGIREM AI using its secure website address.';
        help.hidden = false;
    }

    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        promptEvent = event;
    });
    window.addEventListener('appinstalled', () => { panel.hidden = true; promptEvent = null; });
    dismiss.addEventListener('click', () => { dismissed = true; panel.hidden = true; });
    action.addEventListener('click', async () => {
        if (!window.isSecureContext || dismissed) return;
        if (promptEvent) {
            const current = promptEvent;
            promptEvent = null;
            try {
                const choice = await current.prompt();
                if (choice && choice.outcome === 'accepted') panel.hidden = true;
            } catch {
                help.textContent = 'Open your browser menu and look for Install app or Add to Home screen.';
                help.hidden = false;
            }
            return;
        }
        help.textContent = ios
            ? 'Tap Share in your browser, then choose Add to Home Screen.'
            : 'Open your browser menu and choose Install app or Add to Home screen, if available.';
        help.hidden = false;
    });
})();
