const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
test('static packaging excludes application source, uploads, maps, hidden files and symlinks', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'erp-assets-'));
    try {
        for (const dir of ['css','js','fonts','webfonts','browser-vendor','adminlte','core','frontent_form','error','img','zencare','uploads']) fs.mkdirSync(path.join(root,'public',dir),{recursive:true});
        for (const file of ['favicon.ico','robots.txt','style.css','style1.css','index.php','css/main.css','css/leak.php','js/test.js.map','js/.secret.js','uploads/private.png']) fs.writeFileSync(path.join(root,'public',file),'test');
        fs.symlinkSync(path.join(root,'public/index.php'),path.join(root,'public/js/link.js'));
        execFileSync(process.execPath,[path.resolve('scripts/stage-cloudflare-assets.mjs')],{cwd:root});
        assert.ok(fs.existsSync(path.join(root,'.cloudflare-assets/css/main.css')));
        for (const file of ['index.php','css/leak.php','js/test.js.map','js/.secret.js','js/link.js','uploads/private.png']) assert.equal(fs.existsSync(path.join(root,'.cloudflare-assets',file)),false,file);
    } finally { fs.rmSync(root,{recursive:true,force:true}); }
});
