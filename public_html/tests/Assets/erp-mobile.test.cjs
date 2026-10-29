const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync('resources/js/erp-shell.js', 'utf8');
function setup(html) {
    const dom = new JSDOM(html, { runScripts:'outside-only', pretendToBeVisual:true });
    dom.window.matchMedia = () => ({ matches:true, addEventListener() {} });
    dom.window.eval(source);
    return dom;
}
test('phone cards preserve form controls, table semantics and empty states', () => {
    const dom = setup(`<table class="mobile-records"><thead><tr><th>Name</th><th>Action</th></tr></thead><tbody><tr><td>Asha</td><td><form><input name="lead_id" value="42"><button>Save</button></form></td></tr><tr><td colspan="2">No more records</td></tr></tbody></table><table id="report"><thead><tr><th>Month</th></tr></thead></table>`);
    const d = dom.window.document;
    assert.equal(d.querySelectorAll('.mobile-field-label').length, 2);
    assert.equal(d.querySelector('td[colspan] .mobile-field-label'), null);
    assert.equal(d.querySelector('input').value, '42');
    assert.equal(d.querySelectorAll('form').length, 1);
    assert.equal(d.querySelector('table').getAttribute('role'), 'table');
    assert.equal(d.querySelector('th').scope, 'col');
    assert.equal(d.querySelector('.mobile-field-label').getAttribute('aria-hidden'), 'true');
    assert.equal(d.querySelector('#report').classList.contains('mobile-ready'), false);
    dom.window.close();
});
test('irregular report tables are not assigned misleading card labels', () => {
    const dom = setup('<table class="mobile-records"><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>A</td><td>B</td><td>C</td></tr></tbody></table>');
    assert.equal(dom.window.document.querySelector('.mobile-ready'), null);
    dom.window.close();
});
test('both menu controls open navigation and Escape restores focus to the initiating control', () => {
    const dom = setup('<button class="nav-backdrop" hidden></button><aside class="app-sidebar"><a href="#home">Home</a></aside><div class="app-body"><button class="menu-toggle">Menu</button><button data-navigation-toggle>Menu</button></div>');
    const d = dom.window.document, bottom = d.querySelector('[data-navigation-toggle]');
    bottom.click();
    assert.equal(d.querySelector('.app-body').inert, true);
    assert.equal(d.querySelector('.app-sidebar').inert, false);
    assert.equal(d.querySelector('.menu-toggle').getAttribute('aria-expanded'), 'true');
    d.dispatchEvent(new dom.window.KeyboardEvent('keydown', { key:'Escape' }));
    assert.equal(d.activeElement, bottom);
    assert.equal(d.querySelector('.app-body').inert, false);
    assert.equal(d.querySelector('.app-sidebar').inert, true);
    assert.equal(bottom.getAttribute('aria-expanded'), 'false');
    dom.window.close();
});
test('native validation reveals fields inside nested collapsed forms', () => {
    const dom = setup('<details><summary>New record</summary><form><details><summary>More</summary><input required></details></form></details>');
    dom.window.document.querySelector('input').dispatchEvent(new dom.window.Event('invalid'));
    assert.equal([...dom.window.document.querySelectorAll('details')].every(d => d.open), true);
    dom.window.close();
});
test('closed workflow forms defer lookups until all containing disclosures open', async () => {
    const dom = new JSDOM('<script id="erp-old-input" type="application/json">{}</script><details id="outer"><summary>Actions</summary><details id="inner"><summary>Assign</summary><form method="post"><label>Teacher<input class="entity-search"><select name="teacher_id" class="entity-picker" data-url="/lookup"><option value="">Choose</option></select></label></form></details></details>', {url:'https://example.test',runScripts:'outside-only'});
    const calls = [];
    dom.window.fetch = async url => { calls.push(url); return {ok:true,json:async()=>[{id:7,label:'Meera'}]}; };
    const script = fs.readFileSync('resources/views/erp/workflow-script.blade.php','utf8').match(/<script>\s*([\s\S]*?)<\/script>/)[1];
    dom.window.eval(script);
    assert.equal(calls.length, 0);
    const outer = dom.window.document.getElementById('outer'), inner = dom.window.document.getElementById('inner');
    outer.open = true; outer.dispatchEvent(new dom.window.Event('toggle'));
    assert.equal(calls.length, 0);
    inner.open = true; inner.dispatchEvent(new dom.window.Event('toggle'));
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(calls.length, 1);
    assert.equal(dom.window.document.querySelector('select').options[1].textContent, 'Meera');
    inner.open = false; inner.open = true; inner.dispatchEvent(new dom.window.Event('toggle'));
    assert.equal(calls.length, 1);
    dom.window.close();
});
test('an action shortcut reopens its form when the URL hash is already selected', () => {
    const dom = new JSDOM('<a href="#action-new-enquiry">New enquiry</a><details id="action-new-enquiry"><summary>New enquiry</summary></details>', {url:'https://example.test/#action-new-enquiry',runScripts:'outside-only'});
    dom.window.HTMLElement.prototype.scrollIntoView = function () {};
    const script = fs.readFileSync('resources/views/erp/workflow-script.blade.php','utf8').match(/<script>\s*([\s\S]*?)<\/script>/)[1];
    dom.window.eval(script);
    const details = dom.window.document.querySelector('details');
    assert.equal(details.open, true);
    details.open = false;
    dom.window.document.querySelector('a').click();
    assert.equal(details.open, true);
    assert.equal(dom.window.document.activeElement, details.querySelector('summary'));
    dom.window.close();
});
test('CRM date shortcuts update only the associated date field without submitting', () => {
    const dom = setup('<form><input type="date" name="next_due_on"><button type="button" data-crm-date="2026-10-27" data-crm-field="next_due_on">Tomorrow</button></form><form><input type="date" name="next_due_on" value="2026-12-01"></form>');
    const d=dom.window.document;
    let changed=0, submitted=0;
    d.querySelector('input').addEventListener('change',()=>changed++);
    d.querySelector('form').addEventListener('submit',()=>submitted++);
    d.querySelector('button').click();
    assert.equal(d.querySelector('input').value,'2026-10-27');
    assert.equal(d.querySelectorAll('input')[1].value,'2026-12-01');
    assert.equal(changed,1); assert.equal(submitted,0);
    assert.equal(d.activeElement,d.querySelector('input'));
    dom.window.close();
});
