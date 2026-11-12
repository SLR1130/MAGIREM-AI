const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const template = fs.readFileSync('resources/views/admin/reports.blade.php', 'utf8');
const match = template.match(/<script>\s*(document\.addEventListener\('DOMContentLoaded'[\s\S]*?)<\/script>/);
assert.ok(match, 'The report interaction script must exist.');

// Supply synthetic Blade values; no application records or network are used.
const script = match[1]
    .replace(/@foreach\(\$taxonomy->categories\(\) as \$category\)[\s\S]*?@endforeach/g, '')
    .replace('@json($categoryDrilldown->toArray())', JSON.stringify({
        synthetic: { label: 'Synthetic category', emotions: [{ label: 'Synthetic emotion', count: 2 }] },
    }))
    .replace(/@json\([^)]*\)/g, '[]');

function element(dataset = {}) {
    const listeners = {};
    const attributes = {};
    const classes = new Set();
    return {
        dataset, listeners, attributes, hidden: false, children: [], textContent: '',
        classList: {
            toggle(name, on) { if (on) classes.add(name); else classes.delete(name); },
            remove(name) { classes.delete(name); },
        },
        addEventListener(name, fn) { listeners[name] = fn; },
        setAttribute(name, value) { attributes[name] = value; },
        appendChild(child) { this.children.push(child); },
        replaceChildren(child) { this.children = [child]; },
        querySelector() { return element(); },
        scrollIntoView(options) { this.scrollOptions = options; },
    };
}

function runScenario(withCharts) {
    const tabs = ['trends', 'distribution', 'risk', 'engagement'].map(reportTab => element({ reportTab }));
    const panels = ['trends', 'distribution', 'risk', 'engagement'].map(reportPanel => element({ reportPanel }));
    const category = element({ categoryKey: 'synthetic' });
    const close = element();
    const title = element();
    const list = element();
    const drilldown = element();
    drilldown.hidden = true;
    drilldown.querySelector = selector => ({
        '[data-drilldown-title]': title,
        '[data-drilldown-list]': list,
        '[data-drilldown-close]': close,
    })[selector];
    const containers = [element(), element(), element()];
    const canvases = {
        emotionTrendsChart: element(), monthlyTrendChart: element(), emotionDistributionChart: element(),
    };
    const document = {
        addEventListener(name, fn) { if (name === 'DOMContentLoaded') fn(); },
        querySelectorAll(selector) {
            return ({ '[data-report-tab]': tabs, '[data-report-panel]': panels,
                '.ar-chart-wrap': containers, '[data-category-key]': [category] })[selector] || [];
        },
        getElementById(id) { return id === 'emotionCategoryDrilldown' ? drilldown : canvases[id] || null; },
        createElement() { return element(); },
    };
    const createdCharts = [];
    function MockChart(canvas, options) { createdCharts.push({ canvas, options }); this.resize = () => {}; }
    MockChart.defaults = { font: {} };
    const context = { document, window: { matchMedia: () => ({ matches: true }) }, requestAnimationFrame: fn => fn() };
    if (withCharts) context.Chart = MockChart;
    vm.runInNewContext(script, context);

    tabs[1].listeners.click();
    assert.equal(tabs[1].attributes['aria-pressed'], 'true');
    category.listeners.click();
    assert.equal(drilldown.hidden, false);
    assert.equal(category.attributes['aria-expanded'], 'true');
    assert.equal(drilldown.scrollOptions.behavior, 'auto');
    close.listeners.click();
    assert.equal(drilldown.hidden, true);
    assert.equal(category.attributes['aria-expanded'], 'false');
    if (withCharts) {
        assert.equal(createdCharts.length, 3);
    } else {
        assert.ok(containers.every(container => container.children[0].textContent.includes('Charts could not load')));
    }
}

runScenario(false);
runScenario(true);
console.log('Admin reports: chart failure and chart success interactions passed.');
