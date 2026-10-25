const test=require('node:test');const assert=require('node:assert/strict');const fs=require('node:fs');const {JSDOM}=require('jsdom');
test('teacher forms prevent duplicate submits and recover when navigating back',()=>{
 const dom=new JSDOM('<form method="post"><button type="submit">Save result</button></form>',{runScripts:'outside-only'});
 const script=fs.readFileSync('resources/views/erp/teacher-layout.blade.php','utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
 dom.window.eval(script);const form=dom.window.document.querySelector('form'),button=form.querySelector('button');
 const first=new dom.window.Event('submit',{cancelable:true});form.dispatchEvent(first);assert.equal(first.defaultPrevented,false);assert.equal(button.disabled,true);
 const second=new dom.window.Event('submit',{cancelable:true});form.dispatchEvent(second);assert.equal(second.defaultPrevented,true);
 dom.window.dispatchEvent(new dom.window.Event('pageshow'));assert.equal(button.disabled,false);assert.equal(button.textContent,'Save result');assert.equal(form.dataset.saving,undefined);dom.window.close();
});
