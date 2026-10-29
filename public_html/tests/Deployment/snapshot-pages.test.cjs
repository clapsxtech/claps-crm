const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
const { webcrypto } = require('node:crypto');

const exportsObject = {};
vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../../src/storage.ts'), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText, { exports: exportsObject, Response, URL, TextEncoder, crypto: webcrypto });

function harness() {
    const fixture = Array.from({length:4505}, (_,n)=>({_erp_d1_rowid:(n+1)*2, name:`row-${n}`}));
    const db = {
        withSession(value) { assert.equal(value, 'first-primary');return this; },
        prepare(sql) {
            return { sql, params:[], bind(...params) { this.params=params;return this; },
                async all() { return {results:[{name:'accounts'}]}; } };
        },
        async batch(statements) {
            return statements.map(s=>{
                assert.match(s.sql,/WHERE rowid>\? ORDER BY rowid LIMIT \d+$/);
                const limit=Number(s.sql.match(/LIMIT (\d+)$/)[1]);
                return {results:fixture.filter(r=>r._erp_d1_rowid>s.params[0]).slice(0,limit)};
            });
        },
    };
    return async (payload, authorized=true) => exportsObject.storageRequest(new Request('https://example.test/__cloudflare/database/rows_batch', {
        method:'POST',headers:authorized?{Authorization:'Bearer synthetic-test-token'}:{},body:JSON.stringify(payload),
    }), { DB:db, INTERNAL_TOKEN:'synthetic-test-token' });
}

test('snapshot batches advance by actual rowid, including sparse continuation pages', async()=>{
    const call=harness();let cursor=-1;let total=0;
    for(const expected of [2000,2000,505]) {
        const response=await call({tables:['accounts'],cursors:{accounts:cursor},limit:2000});
        assert.equal(response.status,200);
        const page=(await response.json()).pages.accounts;
        assert.equal(page.rows.length,expected);
        assert.equal(page.more,expected===2000);
        assert.ok(page.rows[0]._erp_d1_rowid>cursor);
        cursor=page.rows.at(-1)._erp_d1_rowid;total+=page.rows.length;
    }
    assert.equal(total,4505);
    assert.equal(cursor,9010);
});

test('snapshot paging remains authenticated, bounded and backward compatible',async()=>{
    const call=harness();
    assert.equal((await call({tables:['accounts']},false)).status,401);
    for(const payload of [
        {tables:['erp_d1_revision']}, {tables:['accounts'],limit:2001},
        {tables:['accounts'],limit:0}, {tables:['accounts'],cursors:{accounts:-2}},
        {tables:['accounts'],cursors:[]}, {tables:['accounts'],cursors:{accounts:1.5}},
    ]) assert.equal((await call(payload)).status,400);
    const page=(await (await call({tables:['accounts']})).json()).pages.accounts;
    assert.equal(page.rows.length,200);
    assert.equal(page.more,true);
});
