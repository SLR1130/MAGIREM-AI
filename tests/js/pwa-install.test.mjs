import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('public/js/pwa-install.js', 'utf8');

function createPage({ userAgent = 'Android', secure = true, standalone = false } = {}) {
    const windowHandlers = {};
    const actionHandlers = {};
    const dismissHandlers = {};
    const action = { hidden: false, addEventListener: (name, fn) => { actionHandlers[name] = fn; } };
    const dismiss = { addEventListener: (name, fn) => { dismissHandlers[name] = fn; } };
    const help = { hidden: true, textContent: '' };
    const panel = { hidden: true, querySelector: selector => ({
        '[data-pwa-install-action]': action,
        '[data-pwa-install-dismiss]': dismiss,
        '[data-pwa-install-help]': help,
    })[selector] };
    const window = {
        isSecureContext: secure,
        matchMedia: () => ({ matches: standalone }),
        addEventListener: (name, fn) => { windowHandlers[name] = fn; },
    };
    vm.runInNewContext(source, { window, navigator: { userAgent, platform: '', maxTouchPoints: 0 }, document: { querySelector: () => panel } });
    return { panel, action, help, windowHandlers, actionHandlers, dismissHandlers };
}

test('Android install action invokes the real browser prompt', async () => {
    const page = createPage();
    let prevented = false;
    let prompted = false;
    page.windowHandlers.beforeinstallprompt({
        preventDefault: () => { prevented = true; },
        prompt: async () => { prompted = true; return { outcome: 'accepted' }; },
    });
    await page.actionHandlers.click();
    assert.equal(prevented, true);
    assert.equal(prompted, true);
    assert.equal(page.panel.hidden, true);
});

test('iPhone shows explicit Share instructions without claiming installation', async () => {
    const page = createPage({ userAgent: 'iPhone' });
    await page.actionHandlers.click();
    assert.equal(page.panel.hidden, false);
    assert.match(page.help.textContent, /Share.*Add to Home Screen/);
});

test('insecure mobile connections explain HTTPS instead of offering a fake prompt', () => {
    const page = createPage({ secure: false });
    assert.equal(page.action.hidden, true);
    assert.match(page.help.textContent, /HTTPS/);
});

test('the suggestion is dismissible and hidden on desktop or installed apps', () => {
    const page = createPage();
    page.dismissHandlers.click();
    assert.equal(page.panel.hidden, true);
    assert.equal(createPage({ userAgent: 'Desktop' }).panel.hidden, true);
    assert.equal(createPage({ standalone: true }).panel.hidden, true);
});
