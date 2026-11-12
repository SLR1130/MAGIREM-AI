const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/create-user.blade.php'), 'utf8');
const start = source.indexOf('    function updateRoleFields() {');
const end = source.indexOf("    roleSelect.addEventListener('change', updateRoleFields);", start);
assert.ok(start >= 0 && end > start, 'role update function is present');

function checkRole(role) {
    const ids = Object.fromEntries(['student_id', 'program', 'year_level', 'specialization', 'years_of_experience', 'availability_schedule', 'status'].map(id => [id, {}]));
    const inputs = [{}, {}];
    const toggle = { checked: false };
    const context = {
        roleSelect: { value: role }, roleCard: {}, studentPanel: {}, counselorPanel: {},
        verificationCard: { querySelectorAll: () => [toggle, {}] }, verificationToggle: toggle,
        securityCard: {}, adminPasswordFields: { querySelectorAll: () => inputs },
        emailLabel: {}, emailInput: {}, emailHelp: {}, invitationMethod: null,
        gmailInput: null, gmailConfirmation: null, gmailRequiredMark: null,
        counselorAuthorization: null, studentSet: {}, counselorSet: {}, emptyState: {}, emptyMessage: {},
        document: { getElementById: id => ids[id] },
    };
    vm.runInNewContext(source.slice(start, end) + '\nupdateRoleFields();', context);
    const applicant = role === 'student' || role === 'counselor';
    assert.equal(context.roleCard.hidden, !applicant);
    assert.equal(context.verificationCard.hidden, !applicant);
    assert.equal(context.securityCard.hidden, role !== 'admin');
    assert.equal(inputs[0].disabled, role !== 'admin');
    assert.equal(inputs[0].required, role === 'admin');
    assert.equal(context.studentSet.disabled, role !== 'student');
    assert.equal(context.counselorSet.disabled, role !== 'counselor');
    assert.equal(ids.student_id.required, role === 'student');
    assert.equal(ids.specialization.required, role === 'counselor');
    assert.equal(context.emailLabel.textContent, role === 'admin' ? 'Administrator sign-in email' : applicant ? 'Institutional sign-in email' : 'Sign-in email');
}

['', 'student', 'counselor', 'admin'].forEach(checkRole);
assert.match(source, /\.admin-create-user \[hidden\]\s*\{\s*display:\s*none\s*!important/);
assert.match(source, /@media\s*\(max-width:\s*700px\)[\s\S]*?\.admin-create-user \.acu-basic-grid[\s\S]*?repeat\(2,\s*minmax\(0,\s*1fr\)\)/);
assert.match(source, /@media\s*\(max-width:\s*360px\)[\s\S]*?grid-template-columns:\s*minmax\(0,\s*1fr\)/);
console.log('Admin create-user mobile layout and role-field checks passed.');
