const assert = require('node:assert/strict');
const fs = require('node:fs');
const postcss = require('postcss');

const template = fs.readFileSync('resources/views/admin/users.blade.php', 'utf8');
const styles = [...template.matchAll(/<style>([\s\S]*?)<\/style>/g)];
const floating = styles.find(match => match[1].includes('compact floating action'));
assert.ok(floating, 'The mobile floating-action styles must exist on the Users page.');
const root = postcss.parse(floating[1]);
const mobile = root.nodes.find(node => node.type === 'atrule' && node.name === 'media' && node.params === '(max-width:700px)');
assert.ok(mobile, 'The floating action must be scoped to mobile.');

function value(selector, property) {
    const rule = mobile.nodes.find(node => node.type === 'rule' && node.selector === selector);
    assert.ok(rule, `Missing mobile rule: ${selector}`);
    return rule.nodes.find(node => node.type === 'decl' && node.prop === property)?.value;
}

assert.equal(value('.admin-users .au-create', 'position'), 'fixed');
assert.equal(value('.admin-users .au-create', 'width'), 'auto');
assert.equal(value('.admin-users .au-create', 'min-height'), '48px');
assert.match(value('.admin-users .au-create', 'bottom'), /safe-area-inset-bottom/);
assert.match(value('.admin-users', 'padding-bottom'), /safe-area-inset-bottom/);
assert.equal(value('.admin-users .au-head', 'animation'), 'none');
assert.equal((template.match(/route\('admin\.create-user'\)/g) || []).length, 1);
assert.ok(root.nodes.some(node => node.type === 'atrule' && node.params.includes('prefers-reduced-motion:reduce')));
console.log('Admin Users mobile floating create action passed.');
