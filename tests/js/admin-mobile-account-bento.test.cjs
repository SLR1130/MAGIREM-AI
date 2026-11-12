const assert = require('node:assert/strict');
const fs = require('node:fs');
const postcss = require('postcss');

const template = fs.readFileSync('resources/views/admin/partials/workspace-polish.blade.php', 'utf8');
const css = template.match(/<style>([\s\S]*?)<\/style>/)?.[1];
assert.ok(css, 'Admin workspace styles must exist.');
const root = postcss.parse(css);
const media = width => root.nodes.find(node => node.type === 'atrule' && node.name === 'media' && node.params === `(max-width:${width}px)`);
function value(parent, selector, property) {
    assert.ok(parent, 'The expected breakpoint must exist.');
    const rule = parent.nodes.find(node => node.type === 'rule' && node.selector === selector);
    assert.ok(rule, `Missing rule: ${selector}`);
    return rule.nodes.find(node => node.type === 'decl' && node.prop === property)?.value;
}

const phone = media(700);
assert.equal(value(phone, '.admin-users .au-summary', 'grid-template-columns'), 'repeat(2,minmax(0,1fr))');
assert.equal(value(phone, 'body.admin-layout .avi .avi-counts', 'grid-template-columns'), 'repeat(3,minmax(0,1fr))');
assert.equal(value(media(360), 'body.admin-layout .avi .avi-counts', 'grid-template-columns'), 'repeat(2,minmax(0,1fr))');
assert.equal(value(media(760), '.avi .avi-back', 'display'), 'none');

const verification = fs.readFileSync('resources/views/admin/account-verification/index.blade.php', 'utf8');
assert.match(verification, /class="avi-btn avi-btn--light avi-back"[^>]*aria-label="Back to users"/);
assert.match(verification, /class="avi-btn avi-btn--light avi-back"[^>]*route\('admin\.users'\)/);
assert.match(verification, /aria-label="Back to users"[^>]*><svg/);
console.log('Users and verification mobile bento and accessible desktop back icon passed.');
