const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');

test('date picker initializes without a separately loaded Moment script and preserves page jQuery', () => {
    const dom = new JSDOM('<input id="dates">', {runScripts:'outside-only', url:'https://example.test'});
    dom.window.eval(fs.readFileSync('public/browser-vendor/jquery.min.js','utf8'));
    const jquery = dom.window.jQuery;
    dom.window.eval(fs.readFileSync('public/js/report-dates.js','utf8'));
    assert.equal(dom.window.jQuery, jquery);
    assert.equal(typeof dom.window.moment, 'function');
    jquery('#dates').daterangepicker({ startDate:'10/01/2026', endDate:'10/25/2026' });
    const picker = jquery('#dates').data('daterangepicker');
    assert.equal(picker.startDate.format('YYYY-MM-DD'), '2026-10-01');
    assert.equal(picker.endDate.format('YYYY-MM-DD'), '2026-10-25');
    picker.remove();
    dom.window.close();
});
