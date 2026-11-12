import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('resources/js/minsuanscore.js', 'utf8');

function setup({ reduced = false, scrollableMain = false } = {}) {
    const makeNode = (names = []) => {
        const classes = new Set(names);
        const attributes = new Map();
        const node = {
            handlers: {}, dataset: {}, properties: new Map(), parentElement: null,
            scrollHeight: 1800, clientHeight: 800, overflowY: 'visible',
            classList: {
                add(...items) { items.forEach(item => classes.add(item)); },
                remove(...items) { items.forEach(item => classes.delete(item)); },
                contains(item) { return classes.has(item); },
                toggle(item, force = !classes.has(item)) { force ? classes.add(item) : classes.delete(item); return force; },
            },
            addEventListener(name, fn) { (this.handlers[name] ||= []).push(fn); },
            removeEventListener(name, fn) { this.handlers[name] = (this.handlers[name] || []).filter(item => item !== fn); },
            fire(name, event = {}) { (this.handlers[name] || []).forEach(fn => fn(event)); },
            dispatchEvent(event) { this.fire(event.type, event); return true; },
            setAttribute(name, value) { attributes.set(name, String(value)); },
            getAttribute(name) { return attributes.get(name) ?? null; },
            hasAttribute(name) { return attributes.has(name); },
            matches() { return false; },
            getBoundingClientRect() { return { left: 0, top: 120, width: 800, height: 600, bottom: 720 }; },
            querySelector() { return null; }, querySelectorAll() { return []; },
        };
        node.style = { setProperty: (name, value) => node.properties.set(name, value), removeProperty: name => node.properties.delete(name) };
        return node;
    };

    const documentElement = makeNode();
    const body = makeNode();
    const main = makeNode(['app-main']);
    const home = makeNode(['msc-home-page', 'msc-home-page--student']);
    const stage = makeNode(['msc-home-stage']);
    const art = makeNode();
    const pause = makeNode();
    const reveal = makeNode();
    main.overflowY = scrollableMain ? 'auto' : 'visible';
    main.parentElement = body;
    home.parentElement = main;
    stage.parentElement = home;
    art.parentElement = stage;
    body.parentElement = documentElement;
    art.closest = selector => selector === '.msc-home-stage' ? stage : selector.includes('.msc-home-page') ? home : null;
    stage.closest = selector => selector.includes('.msc-home-page') ? home : null;
    home.closest = selector => selector.includes('.msc-home-page') ? home : null;
    for (const node of [art, stage, home]) node.querySelector = selector => selector === '[data-msc-scene-pause]' ? pause : null;

    const frames = new Map();
    let frameId = 0;
    const query = { matches: reduced, addEventListener() {} };
    const observers = [];
    class Observer {
        constructor(callback, options) { this.callback = callback; this.options = options; this.observed = new Set(); this.unobserved = new Set(); observers.push(this); }
        observe(node) { this.observed.add(node); }
        unobserve(node) { this.observed.delete(node); this.unobserved.add(node); }
        disconnect() { this.observed.clear(); }
        trigger(isIntersecting) { this.callback([{ target: reveal, isIntersecting }]); }
    }
    const window = makeNode();
    Object.assign(window, {
        innerHeight: 800,
        matchMedia: value => value.includes('prefers-reduced-motion') ? query : { matches: true },
        requestAnimationFrame(fn) { const id = ++frameId; frames.set(id, fn); return id; },
        cancelAnimationFrame(id) { frames.delete(id); },
        getComputedStyle: node => ({ overflowY: node.overflowY }),
        IntersectionObserver: Observer,
    });
    const document = {
        documentElement, body, hidden: false, activeElement: null, scrollingElement: documentElement,
        addEventListener() {}, getElementById() { return null; },
        querySelector(selector) { return selector === '.msc-home-page' ? home : selector === '.app-main' ? main : null; },
        querySelectorAll(selector) {
            if (selector === '[data-msc-hero-art]') return [art];
            if (selector === '[data-msc-reveal]') return [reveal];
            return [];
        },
    };
    class CustomEvent { constructor(type) { this.type = type; } }
    vm.runInNewContext(source, { window, document, CustomEvent, IntersectionObserver: Observer, getComputedStyle: window.getComputedStyle });
    const flush = () => { const pending = [...frames.values()]; frames.clear(); pending.forEach(fn => fn()); };
    flush();
    return { window, main, home, stage, art, pause, reveal, observers, documentElement, flush };
}

test('visible-overflow app main uses actual document scrolling for home motion', () => {
    const page = setup();
    assert.ok(page.window.handlers.scroll?.length, 'Document scrolling must control ambient scene visibility.');
    assert.equal(page.main.handlers.scroll?.length || 0, 0, 'A non-scrolling app-main must not swallow the scroll subscription.');
    assert.equal(page.observers[0].options.root, null);
    assert.equal(page.documentElement.classList.contains('msc-home-smooth-scroll'), true);
});

test('a genuinely scrollable ancestor remains the home scroll root', () => {
    const page = setup({ scrollableMain: true });
    assert.ok(page.main.handlers.scroll?.length);
    assert.equal(page.observers[0].options.root, page.main);
});

test('blended background photograph stays still on pointer movement and scrolling', () => {
    const page = setup();
    page.art.fire('pointermove', { pointerType: 'mouse', clientX: 720, clientY: 270 });
    page.flush();
    page.window.fire('scroll');
    page.flush();
    assert.equal(page.art.properties.size, 0, 'No perspective or translation is applied to the background.');
    assert.equal(page.art.dataset.mscSceneVisible, 'true');
});

test('reduced motion blocks scene transforms and reveals content immediately', () => {
    const page = setup({ reduced: true });
    page.art.fire('pointermove', { pointerType: 'mouse', clientX: 720, clientY: 270 });
    page.flush();
    assert.ok([undefined, '0deg'].includes(page.art.properties.get('--msc-art-tilt-y')));
    assert.equal(page.art.dataset.mscSceneVisible, 'false');
    assert.equal(page.reveal.classList.contains('is-visible'), true);
    assert.equal(page.documentElement.classList.contains('msc-home-smooth-scroll'), false);
});

test('pause motion stops ambient background and keeps the explicit pressed state', () => {
    const page = setup();
    page.pause.fire('click');
    page.flush();
    assert.equal(page.home.dataset.mscMotionPaused, 'true');
    assert.equal(page.pause.getAttribute('aria-pressed'), 'true');
    page.art.fire('pointermove', { pointerType: 'mouse', clientX: 720, clientY: 270 });
    page.window.fire('scroll');
    page.flush();
    assert.equal(page.art.dataset.mscSceneVisible, 'false');
    page.pause.fire('click');
    page.flush();
    assert.equal(page.art.dataset.mscSceneVisible, 'true');
    assert.equal(page.pause.getAttribute('aria-pressed'), 'false');
});

test('ambient layers stop when the full-bleed hero leaves the viewport', () => {
    const page = setup();
    page.art.getBoundingClientRect = () => ({ top: -900, bottom: -100, width: 1440, height: 800 });
    page.window.fire('scroll');
    page.flush();
    assert.equal(page.art.dataset.mscSceneVisible, 'false');
});

test('scroll reveals run once instead of hiding content again when it leaves view', () => {
    const page = setup();
    page.observers[0].trigger(true);
    assert.equal(page.reveal.classList.contains('is-visible'), true);
    assert.equal(page.observers[0].unobserved.has(page.reveal), true);
    page.observers[0].trigger(false);
    assert.equal(page.reveal.classList.contains('is-visible'), true);
});
