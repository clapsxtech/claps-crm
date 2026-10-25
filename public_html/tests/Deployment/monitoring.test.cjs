const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const ts = require('typescript');
const source = ts.transpileModule(fs.readFileSync('src/monitoring.ts','utf8'),{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022}}).outputText;
const output = {};
vm.runInNewContext(source,{exports:output,Date,Promise,Number});
const db = (failed,uncertain) => ({prepare:sql=>sql,batch:async queries=>{assert.equal(queries.length,2);assert.ok(queries.every(q=>q.startsWith('SELECT')));return [{results:[{count:failed}]},{results:[{count:uncertain}]}];}});
test('scheduled monitoring reports health and counts without changing records',async()=>{
 const report=await output.checkOperations(db(0,0),async()=>Response.json({status:'ok'}));
 assert.equal(report.status,'ok');assert.equal(report.failed_jobs,0);
});
test('scheduled monitoring flags failures and uncertain events',async()=>{
 assert.equal((await output.checkOperations(db(1,0),async()=>Response.json({status:'ok'}))).status,'warning');
 assert.equal((await output.checkOperations(db(0,2),async()=>Response.json({status:'ok'}))).status,'warning');
 assert.equal((await output.checkOperations(db(0,0),async()=>Response.json({status:'error'},{status:503}))).status,'error');
});
test('monitoring propagates database and invalid readiness failures',async()=>{
 await assert.rejects(output.checkOperations({prepare:s=>s,batch:async()=>{throw Error('unavailable');}},async()=>Response.json({status:'ok'})));
 await assert.rejects(output.checkOperations(db(0,0),async()=>new Response('not json')));
});
