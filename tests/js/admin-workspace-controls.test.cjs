const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function element(dataset = {}) {
    const listeners = {};
    const attributes = {};
    const classes = new Set();
    return {
        dataset, listeners, attributes, style: {}, value: '',
        classList: {
            toggle(name, on) { const next = on === undefined ? !classes.has(name) : Boolean(on); if (next) classes.add(name); else classes.delete(name); return next; },
            contains(name) { return classes.has(name); }, remove(name) { classes.delete(name); },
        },
        addEventListener(name, fn) { listeners[name] = fn; },
        setAttribute(name, value) { attributes[name] = value; },
    };
}

function execute(path, selectors, ids = {}) {
    const template = fs.readFileSync(path, 'utf8');
    const match = template.match(/<script>\s*(document\.addEventListener\('DOMContentLoaded'[\s\S]*?)<\/script>/);
    assert.ok(match, `${path} must contain the interaction script.`);
    const document = {
        addEventListener(name, fn) { if (name === 'DOMContentLoaded') fn(); },
        querySelectorAll(selector) { return selectors[selector] || []; },
        getElementById(id) { return ids[id] || null; },
    };
    vm.runInNewContext(match[1], { document });
}

const dashboardTabs = ['overview', 'activity', 'tools'].map(admTab => element({ admTab }));
const dashboardPanels = ['overview', 'activity', 'tools'].map(admPanel => element({ admPanel }));
execute('resources/views/admin/dashboard.blade.php', {
    '[data-adm-tab]': dashboardTabs, '[data-adm-panel]': dashboardPanels,
});
dashboardTabs[1].listeners.click();
assert.equal(dashboardTabs[1].attributes['aria-pressed'], 'true');
assert.equal(dashboardTabs[0].attributes['aria-pressed'], 'false');
assert.equal(dashboardPanels[1].classList.contains('active'), true);
assert.equal(dashboardPanels[0].classList.contains('active'), false);

const roles = ['all', 'student', 'counselor', 'admin'].map(userRole => element({ userRole }));
const rows = [element({ role: 'student', search: 'synthetic student' }), element({ role: 'counselor', search: 'synthetic counselor' })];
const input = element();
const empty = element();
execute('resources/views/admin/users.blade.php', {
    '[data-user-role]': roles, '[data-user-row]': rows,
}, { adminUserSearch: input, adminUsersEmpty: empty });
roles[2].listeners.click();
assert.equal(roles[2].attributes['aria-pressed'], 'true');
assert.equal(rows[0].style.display, 'none');
assert.equal(rows[1].style.display, '');
input.value = 'no match';
input.listeners.input();
assert.equal(empty.style.display, 'block');
input.value = 'counselor';
input.listeners.input();
assert.equal(empty.style.display, 'none');
assert.equal(rows[1].style.display, '');

const layout = fs.readFileSync('resources/views/layouts/app.blade.php', 'utf8');
const navigation = layout.match(/\/\/ ===== SIDEBAR TOGGLE \(Mobile\) =====([\s\S]*?)\/\/ ===== USER DROPDOWN TOGGLE =====/);
assert.ok(navigation, 'The existing mobile navigation controls must exist.');
const sidebar = element();
const overlay = element();
const menu = element();
const handlers = {};
sidebar.contains = target => target === 'sidebar';
menu.contains = target => target === 'menu';
menu.hasAttribute = name => name === 'aria-controls';
menu.focus = () => { menu.focused = true; };
const document = {
    getElementById(id) { return id === 'appSidebar' ? sidebar : id === 'sidebarOverlay' ? overlay : null; },
    querySelector(selector) { return selector.startsWith('.header-mobile-menu') ? menu : null; },
    addEventListener(name, fn) { handlers[name] = fn; },
};
const context = { document };
vm.runInNewContext(navigation[1], context);
context.toggleSidebar();
assert.equal(sidebar.classList.contains('mobile-open'), true);
assert.equal(overlay.classList.contains('active'), true);
assert.equal(menu.attributes['aria-expanded'], 'true');
handlers.click({ target: 'outside' });
assert.equal(sidebar.classList.contains('mobile-open'), false);
assert.equal(menu.attributes['aria-expanded'], 'false');
context.toggleSidebar();
handlers.keydown({ key: 'Escape' });
assert.equal(sidebar.classList.contains('mobile-open'), false);
assert.equal(overlay.classList.contains('active'), false);
assert.equal(menu.focused, true);
console.log('Admin dashboard tabs, directory filters, and mobile navigation passed.');
