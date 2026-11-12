const assert = require('node:assert/strict');
const fs = require('node:fs');
const postcss = require('postcss');

const template = fs.readFileSync('resources/views/admin/partials/workspace-polish.blade.php', 'utf8');
const css = template.match(/<style>([\s\S]*?)<\/style>/)?.[1];
assert.ok(css, 'The shared admin styles must exist.');
const root = postcss.parse(css);
const mobile = root.nodes.find(node => node.type === 'atrule' && node.name === 'media' && node.params === '(max-width:600px)');
assert.ok(mobile, 'The bento layout must be scoped to a mobile breakpoint.');

function value(selector, property) {
    const rule = mobile.nodes.find(node => node.type === 'rule' && node.selector === selector);
    assert.ok(rule, `Missing mobile rule: ${selector}`);
    return rule.nodes.find(node => node.type === 'decl' && node.prop === property)?.value;
}

assert.equal(value('.admin-dash .adm-stats', 'grid-template-columns'), 'repeat(2,minmax(0,1fr))');
assert.equal(value('.admin-dash .adm-stat:last-child', 'grid-column'), '1 / -1');
assert.equal(value('.admin-dash .adm-tabs', 'grid-template-columns'), 'repeat(3,minmax(0,1fr))');
assert.equal(value('.admin-dash .adm-mix, .admin-dash .adm-shortcuts', 'grid-template-columns'), 'repeat(2,minmax(0,1fr))');
assert.equal(value('.admin-dash .adm-tab', 'min-height'), '44px');
assert.ok(root.nodes.some(node => node.type === 'atrule' && node.params === '(prefers-reduced-motion:reduce)'));
console.log('Admin dashboard mobile bento breakpoint and reduced-motion rules passed.');
