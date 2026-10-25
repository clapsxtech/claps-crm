const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { JSDOM, VirtualConsole } = require('jsdom');
const root = path.resolve(__dirname, '../..');

test('built browser bundle initializes legacy widgets without runtime errors', async () => {
    const errors = [];
    const virtualConsole = new VirtualConsole();
    virtualConsole.on('jsdomError', error => errors.push(error.message));
    const dom = new JSDOM('<!doctype html><html><body><div class="wrapper"></div><select id="choice"><option>A</option></select><div id="modal" class="modal"><div class="modal-dialog"><div class="modal-content">Modal</div></div></div></body></html>', {
        url: 'https://clapslearn.test/', runScripts: 'outside-only', pretendToBeVisual: true, virtualConsole,
    });
    try {
        dom.window.eval(fs.readFileSync(path.join(root, 'public/js/app.js'), 'utf8'));
        await new Promise(resolve => dom.window.setTimeout(resolve, 30));
        const $ = dom.window.jQuery;
        assert.equal($.fn.jquery, '3.7.1');
        assert.equal(typeof $.fn.modal, 'function');
        assert.equal(typeof $.fn.PushMenu, 'function');
        assert.equal(typeof $.fn.select2, 'function');
        assert.equal(typeof dom.window.axios.get, 'function');
        assert.equal(typeof dom.window._.get, 'function');
        $('#choice').select2();
        assert.equal($('#choice').hasClass('select2-hidden-accessible'), true);
        $('#modal').modal('show');
        assert.equal($('#modal').hasClass('show'), true);
        $('#modal').modal('hide');
        assert.deepEqual(errors, []);
    } finally { dom.window.close(); }
});

test('manifest points to exact compiled assets and fonts are present', () => {
    const manifest = JSON.parse(fs.readFileSync(path.join(root, 'public/mix-manifest.json'), 'utf8'));
    for (const asset of ['js/app.js', 'css/app.css', 'css/toastr.css']) {
        const content = fs.readFileSync(path.join(root, 'public', asset));
        const hash = crypto.createHash('sha256').update(content).digest('hex').slice(0, 20);
        assert.equal(manifest['/' + asset], `/${asset}?id=${hash}`);
        assert.doesNotMatch(content.toString(), /sourceMappingURL=/);
    }
    const css = fs.readFileSync(path.join(root, 'public/css/app.css'), 'utf8');
    assert.match(css, /\.main-sidebar/);
    assert.match(css, /\.modal/);
    assert.match(css, /\.fa-user/);
    assert.doesNotMatch(css, /@import[^;]*(adminlte|toastr)/);
    for (const font of ['fa-solid-900.woff2', 'fa-regular-400.woff2', 'fa-brands-400.woff2']) {
        assert.ok(fs.statSync(path.join(root, 'public/webfonts', font)).size > 1000);
    }
});

test('build no longer depends on Mix, webpack or OpenSSL legacy flags', () => {
    const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json')));
    const lock = JSON.parse(fs.readFileSync(path.join(root, 'package-lock.json')));
    assert.doesNotMatch(JSON.stringify(pkg.scripts), /openssl-legacy-provider|\bmix\b/);
    for (const name of ['laravel-mix', 'webpack', 'sass-loader', 'resolve-url-loader']) {
        assert.equal(lock.packages['node_modules/' + name], undefined);
    }
});

test('locked vendor assets support legacy tables, export registration and date ranges', async () => {
    const dom = new JSDOM('<!doctype html><html><body><input id="dates"><table id="records"><thead><tr><th>Name</th></tr></thead><tbody><tr><td>Example</td></tr></tbody></table></body></html>', { url: 'https://clapslearn.test/', runScripts: 'outside-only', pretendToBeVisual: true });
    try {
        dom.window.setImmediate = setImmediate;
        const load = asset => dom.window.eval(fs.readFileSync(path.join(root, 'public', asset), 'utf8'));
        for (const asset of ['browser-vendor/jquery.min.js', 'browser-vendor/bootstrap.bundle.min.js', 'adminlte/jquery.dataTables.min.js', 'adminlte/dataTables.bootstrap4.min.js', 'adminlte/dataTables.buttons.min.js', 'adminlte/jszip.min.js', 'adminlte/pdfmake.min.js', 'adminlte/vfs_fonts.js', 'adminlte/buttons.html5.min.js', 'browser-vendor/moment.min.js', 'browser-vendor/daterangepicker.js']) load(asset);
        const $ = dom.window.jQuery;
        assert.equal($.fn.dataTable.version, '1.13.11');
        const table = $('#records').DataTable({ dom: 'Bfrtip', buttons: ['copy', 'csv', 'excel', 'pdf'], paging: true });
        assert.equal(table.rows().count(), 1);
        assert.equal(typeof $.fn.dataTable.ext.buttons.pdfHtml5.action, 'function');
        assert.equal(typeof dom.window.pdfMake.createPdf, 'function');
        const pdf = await new Promise(resolve => dom.window.pdfMake.createPdf({ content: 'Synthetic export smoke test' }).getBuffer(resolve));
        assert.ok(pdf.length > 1000);
        assert.equal(dom.window.JSZip.version, '3.10.2');
        const zip = new dom.window.JSZip();
        zip.file('test.txt', 'export smoke');
        assert.ok((await zip.generateAsync({ type: 'uint8array' })).length > 20);
        assert.equal(dom.window.moment.version, '2.31.0');
        $('#dates').daterangepicker({ startDate: '10/01/2026', endDate: '10/25/2026' });
        assert.equal($('#dates').data('daterangepicker').startDate.format('YYYY-MM-DD'), '2026-10-01');
        table.destroy();
    } finally { dom.window.close(); }
});

test('templates do not reintroduce removed vulnerable plugins or floating script CDNs', () => {
    const walk = folder => fs.readdirSync(folder, { withFileTypes: true }).flatMap(e => e.isDirectory() ? walk(path.join(folder, e.name)) : [path.join(folder, e.name)]);
    const views = walk(path.join(root, 'resources/views')).map(p => fs.readFileSync(p, 'utf8')).join('\n');
    for (const obsolete of ['jquery-ui.min.js', 'jquery.validate.min.js', 'jquery.magnific-popup.min.js', 'slick.min.js', 'moment.js/2.29.1', 'jquery-3.6.0', 'npm/daterangepicker/']) assert.equal(views.includes(obsolete), false, obsolete);
    assert.match(views, /escapeHtml: true/);
    assert.doesNotMatch(views, /<script[^>]+src=["'](?:https?:)?\/\//);
    assert.equal(fs.existsSync(path.join(root, 'public/adminlte/jquery-ui.min.js')), false);
});
