const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {JSDOM} = require('jsdom');

async function attendance() {
 const dom = new JSDOM(`<form id="markUploadForm"><input name="_token" value="csrf"><input name="submission_key" value="same-key"><select id="teacher_name"><option>Teacher</option></select><input id="password" value="test"><select id="student_name"></select><input id="teacher_code"><input id="student_code"><button id="verify-teacher" type="button">Verify</button><button type="submit">Save</button></form><span id="verification-status"></span><div id="error-alert"></div><div id="successModal"></div>`, {runScripts:'outside-only',url:'https://example.test'});
 dom.window.eval(fs.readFileSync('public/browser-vendor/jquery.min.js','utf8'));
 const $ = dom.window.jQuery, calls=[];
 $.fn.select2 = $.fn.modal = function() {return this;};
 $.ajax = options => {const request={options,aborted:false,abort(){this.aborted=true;options.error?.({},'abort');options.complete?.();}};calls.push(request);return request;};
 const source=fs.readFileSync('resources/views/teacherhourupload.blade.php','utf8').split("@section('scripts')")[1].match(/<script>([\s\S]*?)<\/script>/)[1];
 dom.window.eval(source.replace(/\{\{ route\("([^"]+)"\) \}\}/g,'/$1'));
 await new Promise(resolve => setTimeout(resolve,15));
 return {dom,$,calls};
}

test('attendance verification reports failure, retries, and invalidates assignments while typing',async()=>{
 const {dom,$,calls}=await attendance();
 $('#verify-teacher').trigger('click');
 assert.equal(calls.length,1);
 calls[0].options.error({responseJSON:{message:'Invalid identity'}},'error');calls[0].options.complete();
 assert.equal($('#error-alert').text(),'Invalid identity');
 assert.equal($('#verify-teacher').prop('disabled'),false);
 $('#verify-teacher').trigger('click');
 calls[1].options.success({teacher_code:'T1',students:[{student_name:'Student',student_code:'S1'}]});calls[1].options.complete();
 $('#student_name').val('Student').trigger('change');
 assert.equal($('#student_code').val(),'S1');
 $('#password').val('changed').trigger('input');
 assert.equal($('#student_code').val(),'');assert.equal($('#teacher_code').val(),'');
 assert.equal($('#student_name option').length,1);
 dom.window.close();
});

test('attendance ignores repeated submits, preserves retry key and locks acknowledged saves',async()=>{
 const {dom,$,calls}=await attendance();
 $('#markUploadForm').trigger('submit');$('#markUploadForm').trigger('submit');
 assert.equal(calls.length,1);
 calls[0].options.error({responseJSON:{message:'Try again'}});calls[0].options.complete();
 assert.equal($('[name=submission_key]').val(),'same-key');
 assert.equal($('button[type=submit]').prop('disabled'),false);
 $('#markUploadForm').trigger('submit');
 assert.equal(calls.length,2);assert.match(calls[1].options.data,/submission_key=same-key/);
 calls[1].options.success({success:true});calls[1].options.complete();
 $('#markUploadForm').trigger('submit');assert.equal(calls.length,2);
 assert.equal($('button[type=submit]').prop('disabled'),true);
 dom.window.close();
});

test('attendance report clears rows immediately and ignores stale responses after credential edits',async()=>{
 const dom=new JSDOM('<select id="teacher_name"><option>Teacher</option></select><input id="dob" value="test"><input id="daterange"><select id="student_name"><option value="all">All</option></select><button id="verify-attendance"></button><span id="attendanceMessage"></span><table class="data-table"></table><span id="hour-total"></span>',{runScripts:'outside-only',url:'https://example.test'});
 dom.window.eval(fs.readFileSync('public/browser-vendor/jquery.min.js','utf8'));
 const $=dom.window.jQuery,calls=[];let config,rows=[],draw=0;
 $.fn.select2=$.fn.daterangepicker=function(){return this;};
 const reload=()=>config.ajax({draw:++draw},response=>{rows=response.data;});
 $.fn.DataTable=function(options){config=options;reload();return {ajax:{reload}};};
 $.ajax=options=>{const request={options,abort(){options.error?.({},'abort');options.complete?.();}};calls.push(request);return request;};
 let source=fs.readFileSync('resources/views/hourreport.blade.php','utf8').match(/<script>\s*([\s\S]*?)<\/script>/)[1];
 source=source.replace(/var admin = .*?;/,'var admin = false;').replace(/@json\(csrf_token\(\)\)/g,'"csrf"').replace(/@json\(route\(.*?\)\)/g,'"/report"');
 dom.window.eval(source);await new Promise(resolve=>setTimeout(resolve,15));
 $('#verify-attendance').trigger('click');calls[0].options.success({students:[{student_name:'Student'}]});calls[0].options.complete();
 const response=calls[1].options;
 response.success({recordsFiltered:1,data:[{hour_taken:'1.25'}]});assert.equal(rows.length,1);assert.match($('#hour-total').text(),/1.25 h/);
 $('#dob').val('different').trigger('input');assert.equal(rows.length,0);assert.equal($('#hour-total').text(),'');
 response.success({recordsFiltered:1,data:[{hour_taken:'5'}]});assert.equal(rows.length,0);
 dom.window.close();
});
