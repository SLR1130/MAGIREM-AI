import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('public/js/home-magi-chat.js', 'utf8');

function setup(mobile) {
    const handlers = {};
    const timers = new Map();
    let nextTimer = 0;
    let heroBottom = 900;
    const makeNode = () => ({
        hidden: false, textContent: '', disabled: false, dataset: {},
        classList: { add() {}, remove() {} },
        addEventListener(name, fn) { this[name] = fn; },
        removeEventListener() {}, setAttribute() {}, focus() {},
    });
    const panel = makeNode(); panel.hidden = true;
    const nudge = makeNode(); nudge.hidden = true;
    const nodes = { '#home-magi-panel': panel, '[data-magi-nudge]': nudge };
    const root = makeNode();
    root.dataset = { aiEnabled: 'true' };
    root.querySelector = key => nodes[key] ||= makeNode();
    root.querySelectorAll = () => [];
    const hero = { getBoundingClientRect: () => ({ bottom: heroBottom }) };
    const doc = { readyState: 'complete', hidden: false, querySelector: key => {
        if (key === '[data-home-magi]') return root;
        if (key === '.msc-home-stage') return hero;
        return null;
    } };
    const window = {
        matchMedia: query => ({ matches: query.includes('max-width') ? mobile : true }),
        setTimeout(fn, delay) { const id = ++nextTimer; timers.set(id, { fn, delay }); return id; },
        clearTimeout(id) { timers.delete(id); },
        addEventListener(name, fn) { handlers[name] = fn; },
        removeEventListener(name) { delete handlers[name]; },
    };
    vm.runInNewContext(source, { document: doc, window, sessionStorage: { getItem: () => null, setItem() {} } });
    const runTimer = delay => {
        const entry = [...timers.entries()].find(([, timer]) => timer.delay === delay);
        assert.ok(entry, `Expected ${delay}ms timer`);
        timers.delete(entry[0]); entry[1].fn();
    };
    return { nudge, panel, nodes, handlers, runTimer, movePastHero: () => { heroBottom = 0; } };
}

test('mobile greeting waits until the hero is out of view and then hides', () => {
    const page = setup(true);
    page.runTimer(1800);
    assert.equal(page.nudge.hidden, true);
    page.movePastHero();
    page.handlers.scroll();
    assert.equal(page.nudge.hidden, false);
    page.runTimer(8000);
    assert.equal(page.nudge.hidden, true);
    page.nodes['[data-magi-toggle]'].click();
    assert.equal(page.panel.hidden, false);
});

test('desktop keeps its original proactive greeting', () => {
    const page = setup(false);
    page.runTimer(1800);
    assert.equal(page.nudge.hidden, false);
});
