const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'resources/views/erp/workflow-script.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
function harness() {
    const pending = [], timers = [];
    const node = () => ({ textContent:'', hidden:false, attributes:{}, listeners:{}, setAttribute(k,v){this.attributes[k]=v;}, getAttribute(k){return this.attributes[k]||null;}, removeAttribute(k){delete this.attributes[k];}, addEventListener(k,v){this.listeners[k]=v;}, insertAdjacentElement(where,node){this.next=node;} });
    const search = node(); search.value = '';
    const select = node(); select.dataset = {url:'/lookup'}; select.value = '7';
    select.options = [{value:'7',textContent:'Student Seven'}]; select.selectedIndex = 0;
    select.parentElement = {querySelector:()=>search};
    select.replaceChildren = (...options) => {select.options=options;}; select.add = option=>select.options.push(option);
    const document = {title:'ERP',getElementById:()=>null,createElement:node,querySelectorAll:selector=>selector==='.entity-picker'?[select]:[]};
    vm.runInNewContext(source, {document, window:{location:{origin:'https://example.test',hash:''},addEventListener(){}}, URL, Option:function(text,value){this.textContent=text;this.value=value;}, fetch:()=>new Promise((resolve,reject)=>pending.push({resolve,reject})), setTimeout:fn=>{timers.push(fn);return timers.length;}, clearTimeout(){}});
    const settle = () => new Promise(resolve=>setImmediate(resolve));
    return {select,search,pending,timers,settle, status:select.next, async resolve(index,rows){pending[index].resolve({ok:true,json:()=>Promise.resolve(rows)});await settle();}};
}
test('entity search retains selected records missing from a filtered page', async()=>{
    const h=harness(); await h.resolve(0,[{id:8,label:'Student Eight'}]);
    assert.equal(h.select.value,'7'); assert.equal(h.select.options.find(o=>o.value==='7').textContent,'Student Seven');
    assert.match(h.status.textContent,/previous selection is kept/);
});
test('lookup failures preserve options and provide an explicit retry',async()=>{
    const h=harness();h.pending[0].reject(new Error('offline'));await h.settle();
    assert.equal(h.select.value,'7');assert.equal(h.select.options.length,1);assert.equal(h.status.next.hidden,false);
    h.status.next.listeners.click();await h.resolve(1,[{id:7,label:'Verified Student Seven'}]);
    assert.equal(h.status.next.hidden,true);assert.equal(h.select.options.find(o=>o.value==='7').textContent,'Verified Student Seven');
});
test('typing invalidates stale lookup responses before debounce fires',async()=>{
    const h=harness();h.search.value='new';h.search.listeners.input();await h.resolve(0,[{id:9,label:'Stale'}]);
    assert.equal(h.select.options.length,1);h.timers[0]();await h.resolve(1,[{id:7,label:'Current'}]);
    assert.equal(h.select.options.find(o=>o.value==='7').textContent,'Current');
});
test('malformed lookup responses are retryable without losing the selected record',async()=>{
    const h=harness();await h.resolve(0,{message:'not an array'});assert.equal(h.select.value,'7');assert.equal(h.status.next.hidden,false);
});
test('form recovery is action and immutable identity scoped and excludes secrets',()=>{
    assert.match(source,/old\._erp_form === key && !recoveredForm/);
    assert.match(source,/control\.type === 'password' \|\| control\.type === 'file'/);
    assert.match(source,/input:not\(\[type="hidden"\]\)/);
    const partial=fs.readFileSync(path.join(root,'resources/views/erp/workflow-script.blade.php'),'utf8');
    assert.match(partial,/'_token','password','password_confirmation','current_password'/);
    assert.doesNotMatch(source,/localStorage|sessionStorage|innerHTML/);
});
test('queues provide narrow-screen cell labels and errors have a focusable summary',()=>{
    const queue=fs.readFileSync(path.join(root,'resources/views/erp/work-queues.blade.php'),'utf8');
    ['Student and class','Teacher','Attended','Review','Next action'].forEach(label=>assert.ok(queue.includes(`data-label="${label}"`)));
    const layout=fs.readFileSync(path.join(root,'resources/views/erp/layout.blade.php'),'utf8');
    assert.match(layout,/id="error-summary" tabindex="-1"/);assert.match(layout,/Skip to main content/);
});

const {JSDOM} = require('jsdom');
function recoveryDom(old) {
    const form = id => `<details><summary>Edit ${id}</summary><form method="post" action="/mutate/student-update"><input type="hidden" name="student_id" value="${id}"><input type="hidden" name="_token" value="fresh-token"><label>Name<input name="name" value="Default ${id}"></label><label>Teacher<input class="entity-search" aria-label="Search teachers"><select class="entity-picker" data-url="/lookup" name="teacher_id"><option value="">Choose</option></select></label><label><input type="checkbox" name="clear_fields[]" value="notes">Clear notes</label><button>Save</button></form></details>`;
    const dom = new JSDOM(`<title>ERP</title><div id="error-summary" tabindex="-1"><ul><li data-error-field="name">Enter a name</li></ul></div>${form(7)}${form(8)}<script type="application/json" id="erp-old-input"></script>`, {url:'https://example.test',runScripts:'outside-only'});
    dom.window.document.getElementById('erp-old-input').textContent=JSON.stringify(old);
    dom.window.fetch=()=>Promise.resolve({ok:true,json:()=>Promise.resolve([{id:1,label:'Other teacher'}])});
    dom.window.eval(source);return dom;
}
const recovered = {_erp_form:JSON.stringify(['/mutate/student-update',[['student_id','7']]]),name:'Recovered name',teacher_id:'42',clear_fields:['notes'],_token:'stale-token',student_id:'8'};
test('real DOM recovery restores only the matching row and never overwrites hidden identities or CSRF',async()=>{
    const dom=recoveryDom(recovered);await new Promise(resolve=>setImmediate(resolve));const d=dom.window.document, forms=d.querySelectorAll('form');
    assert.equal(forms[0].elements.name.value,'Recovered name');assert.equal(forms[1].elements.name.value,'Default 8');
    assert.equal(forms[0].elements.student_id.value,'7');assert.equal(forms[0].elements._token.value,'fresh-token');
    assert.equal(forms[0].elements.teacher_id.value,'42');assert.equal(forms[1].elements.teacher_id.value,'');
    assert.equal(forms[0].querySelector('[type=checkbox]').checked,true);assert.equal(forms[1].querySelector('[type=checkbox]').checked,false);
    assert.equal(d.querySelectorAll('details')[0].open,true);assert.equal(d.querySelectorAll('details')[1].open,false);dom.window.close();
});
test('real DOM validation summary focuses and links to the recovered field with inline error',()=>{
    const dom=recoveryDom(recovered),d=dom.window.document;
    assert.equal(d.activeElement.id,'error-summary');assert.equal(d.title,'Error: ERP');
    d.querySelector('#error-summary a').click();assert.equal(d.activeElement.name,'name');
    assert.equal(d.activeElement.getAttribute('aria-invalid'),'true');assert.equal(d.getElementById(d.activeElement.getAttribute('aria-describedby')).textContent,'Enter a name');
    const select=d.querySelector('select');assert.equal(d.querySelector('label[for="'+select.id+'"]').htmlFor,select.id);dom.window.close();
});
test('unknown or stale form identity does not apply input to any form',()=>{
    const dom=recoveryDom({...recovered,_erp_form:'unrecognized'}),forms=dom.window.document.querySelectorAll('form');
    assert.equal(forms[0].elements.name.value,'Default 7');assert.equal(forms[1].elements.name.value,'Default 8');assert.equal(dom.window.document.querySelector('#error-summary a'),null);dom.window.close();
});
