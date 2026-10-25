const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');

const root = path.join(__dirname, '../..');

function deleteHarness() {
    const handlers = new Map();
    const events = [];
    const pending = [];
    const state = {};
    const button = { disabled: false, prop(name, value) { this[name] = value; return this; } };
    const form = {
        data(name, value) { if (arguments.length === 2) state[name] = value; return state[name]; },
        find() { return button; },
        attr() { return '/test-records/123'; },
    };
    const modal = {
        load(href, callback) { events.push(['load', href]); callback.call(this, '', this.loadStatus || 'success'); return this; },
        modal(action) { events.push(['modal', action]); return this; },
    };
    const table = { DataTable() { return { ajax: { reload(...args) { events.push(['reload', ...args]); } } }; } };
    const document = {};
    function $(target) {
        if (target === document) return { on(event, selector, callback) { handlers.set(`${event}:${selector}`, callback); } };
        if (target === 'div.modal_class' || target === '.modal_class') return modal;
        if (target === '.data-table') return { each(callback) { callback.call(table); } };
        return target;
    }
    $.fn = { dataTable: { isDataTable: () => true } };
    $.ajax = (options) => pending.push(options);
    const source = fs.readFileSync(path.join(root, 'resources/views/admin/layouts/scripts.blade.php'), 'utf8')
        .match(/<script>([\s\S]*?)<\/script>/)[1].replace(/{{\s*csrf_token\(\)\s*}}/g, 'test-csrf');
    vm.runInNewContext(source, { $, document, toastr: {
        success(message) { events.push(['success', message]); },
        error(message) { events.push(['error', message]); },
    } });
    return {
        events, pending, button, modal,
        click() { handlers.get('click:button.btn-modal').call({ data: () => '/test-confirmation' }); },
        submit() { handlers.get('submit:form#removeForm').call(form, { preventDefault() {} }); },
        resolve(result) { pending.at(-1).success(result); pending.at(-1).complete(); },
        reject(result = {}) { pending.at(-1).error(result); pending.at(-1).complete(); },
    };
}

test('delete accepts the current success boolean and refreshes without losing the page', () => {
    const h = deleteHarness();
    h.click(); h.submit();
    assert.equal(h.button.disabled, true);
    assert.equal(h.pending[0].method, 'delete');
    assert.equal(h.pending[0].data._token, 'test-csrf');
    h.resolve({ success: true, msg: 'Deleted' });
    assert.deepEqual(h.events.slice(-3), [['modal', 'hide'], ['success', 'Deleted'], ['reload', null, false]]);
    assert.equal(h.button.disabled, false);
});

test('delete supports legacy status boolean but not truthy strings or contradictory success', () => {
    for (const [result, expected] of [
        [{ status: true }, 'success'],
        [{ success: 'true' }, 'error'],
        [{ status: 'true' }, 'error'],
        [{ success: false, status: true }, 'error'],
        [null, 'error'],
    ]) {
        const h = deleteHarness(); h.submit(); h.resolve(result);
        assert.ok(h.events.some(([kind]) => kind === expected));
        assert.equal(h.button.disabled, false);
        if (expected === 'error') assert.ok(!h.events.some(([kind]) => kind === 'reload'));
    }
});

test('network and server errors keep the modal retryable and re-enable the button', () => {
    for (const response of [{}, { responseJSON: { msg: 'Access denied' } }]) {
        const h = deleteHarness(); h.click(); h.submit(); h.reject(response);
        assert.equal(h.button.disabled, false);
        assert.ok(h.events.some(([kind]) => kind === 'error'));
        assert.ok(!h.events.some(([kind, action]) => kind === 'modal' && action === 'hide'));
        h.submit(); assert.equal(h.pending.length, 2);
        h.resolve({ success: true });
        assert.equal(h.button.disabled, false);
    }
});

test('logical failures keep the modal open and duplicate submits send only one request', () => {
    const h = deleteHarness(); h.submit(); h.submit();
    assert.equal(h.pending.length, 1);
    h.resolve({ success: false, msg: 'Cannot delete an active record' });
    assert.ok(h.events.some(([kind, msg]) => kind === 'error' && msg === 'Cannot delete an active record'));
    assert.ok(!h.events.some(([kind]) => kind === 'reload'));
    h.submit(); assert.equal(h.pending.length, 2);
});

test('reopening the modal never registers duplicate submit handlers and load errors surface', () => {
    const h = deleteHarness(); h.click(); h.click(); h.submit();
    assert.equal(h.pending.length, 1);
    h.reject(); h.click(); h.submit(); assert.equal(h.pending.length, 2);
    const failing = deleteHarness(); failing.modal.loadStatus = 'error'; failing.click();
    assert.ok(failing.events.some(([kind]) => kind === 'error'));
    assert.ok(!failing.events.some(([kind]) => kind === 'modal'));
});

function workerHarness(appUrl = 'https://logs.example.test', environment = {}, unavailableProbes = 0) {
    const exports = {};
    let clock = 0, probes = 0;
    // Node requires duplex for a streamed request body; Workers does not.
    class WorkerRequest extends Request {
        constructor(input, init) {
            if (init && init.body) init = { ...init, duplex: 'half' };
            super(input, init);
        }
    }
    const source = ts.transpileModule(fs.readFileSync(path.join(root, 'src/index.ts'), 'utf8'), {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    vm.runInNewContext(source, {
        exports, URL, Headers, Request: WorkerRequest, Response, AbortSignal,
        Date: class extends Date { static now() { return clock; } },
        scheduler: { wait: async (ms) => { clock += ms; } },
        require(name) {
            if (name === './monitoring') return { checkOperations: async () => ({ status: 'ok' }) };
            if (name === './storage') return { storageRequest: async () => null };
            assert.equal(name, 'cloudflare:workers');
            return { DurableObject: class { constructor(ctx, env) { this.ctx = ctx; this.env = env; } } };
        },
    });
    const forwarded = [];
    let startOptions;
    const port = { async fetch(request) {
        if (typeof request === 'string') {
            if (probes++ < unavailableProbes) throw new Error('Container is still booting');
            return new Response('ready');
        }
        forwarded.push(request); return new Response('application response');
    } };
    const container = {
        running: false, images: { base: 'test-image' },
        start(options) {
            // Reject obsolete instance names just as the Containers API does.
            assert.ok(['lite', 'standard-1', 'standard-2', 'standard-3', 'standard-4'].includes(options.instance));
            startOptions = options;
            this.running = true;
        },
        setInactivityTimeout: async () => {},
        getTcpPort(number) { assert.equal(number, 8080); return port; },
    };
    const worker = new exports.LaravelContainer({ container, blockConcurrencyWhile: (cb) => cb() }, { APP_URL: appUrl, ...environment });
    return { worker, forwarded, container, get probes() { return probes; }, get startOptions() { return startOptions; } };
}

test('cold startup tolerates fast connection refusals for more than twenty probes', async () => {
    const h = workerHarness('https://logs.example.test', {}, 50);
    assert.equal((await h.worker.fetch(new Request('https://logs.example.test/erp'))).status, 200);
    assert.equal(h.forwarded.length, 1);
});

test('an unavailable container reaches a bounded readiness deadline without forwarding', async () => {
    const h = workerHarness('https://logs.example.test', {}, Infinity);
    await assert.rejects(h.worker.fetch(new Request('https://logs.example.test/erp')), /did not become ready/);
    assert.equal(h.forwarded.length, 0);
});

test('Worker preserves public Host, query, body and authentication headers over TCP HTTP', async () => {
    const h = workerHarness();
    const response = await h.worker.fetch(new Request('https://logs.example.test/records?day=23', {
        method: 'POST', body: 'value=sample', headers: { Cookie: 'session=test-only', 'Content-Type': 'application/x-www-form-urlencoded' },
    }));
    assert.equal(await response.text(), 'application response');
    const request = h.forwarded[0];
    assert.equal(request.url, 'http://logs.example.test/records?day=23');
    assert.equal(request.headers.get('host'), 'logs.example.test');
    assert.equal(request.headers.get('cookie'), 'session=test-only');
    assert.equal(await request.text(), 'value=sample');
    assert.equal(request.redirect, 'manual');
    assert.equal(h.startOptions.env.PUBLIC_ORIGIN, 'https://logs.example.test');
    assert.equal(h.startOptions.env.TRUSTED_PROXIES, '');
    assert.equal(h.startOptions.env.SESSION_SECURE_COOKIE, 'true');
    assert.equal(h.startOptions.env.CACHE_DRIVER, 'file');
});

test('Worker replaces all client-supplied forwarding origin headers and strips spoofed chains', async () => {
    const h = workerHarness();
    await h.worker.fetch(new Request('https://logs.example.test:8443/login', { headers: {
        Host: 'evil.example', Forwarded: 'proto=http;host=evil.example',
        'X-Forwarded-Host': 'evil.example', 'X-Forwarded-Proto': 'http', 'X-Forwarded-Port': '81',
        'X-Forwarded-For': '203.0.113.99', 'X-Forwarded-Prefix': '/evil',
        'X-Real-IP': '203.0.113.99', 'X-Original-URL': '/evil', 'X-Rewrite-URL': '/evil',
    } }));
    const headers = h.forwarded[0].headers;
    assert.equal(h.forwarded[0].url, 'http://logs.example.test:8443/login');
    assert.equal(headers.get('host'), 'logs.example.test:8443');
    assert.equal(headers.get('x-forwarded-host'), 'logs.example.test:8443');
    assert.equal(headers.get('x-forwarded-proto'), 'https');
    assert.equal(headers.get('x-forwarded-port'), '8443');
    for (const name of ['forwarded', 'x-forwarded-for', 'x-forwarded-prefix', 'x-real-ip', 'x-original-url', 'x-rewrite-url']) {
        assert.equal(headers.get(name), null);
    }
});

test('HTTP local development does not enable canonical HTTPS mode', async () => {
    const h = workerHarness('http://localhost:8787');
    await h.worker.fetch(new Request('http://localhost:8787/health'));
    assert.equal(h.startOptions.env.PUBLIC_ORIGIN, '');
    assert.equal(h.startOptions.env.SESSION_SECURE_COOKIE, 'false');
    assert.equal(h.forwarded[0].url, 'http://localhost:8787/health');
});


test('Worker module configuration is explicit and Sheets remains disabled by default', async () => {
    const defaults = workerHarness();
    await defaults.worker.fetch(new Request('https://logs.example.test/health'));
    assert.equal(defaults.startOptions.env.SHEETS_SYNC_ENABLED, 'false');
    assert.equal(defaults.startOptions.env.GOOGLE_SERVICE_ENABLED, 'false');
    assert.equal(defaults.startOptions.env.GOOGLE_SPREADSHEET_ID, '');
    assert.equal(defaults.startOptions.env.ERP_BUSINESS_ID, '1');
    const configured = workerHarness('https://logs.example.test', {
        SHEETS_SYNC_ENABLED: 'true', GOOGLE_SERVICE_ENABLED: 'true',
        GOOGLE_SPREADSHEET_ID: 'test-sheet-not-a-real-account', ERP_BUSINESS_ID: '7',
    });
    await configured.worker.fetch(new Request('https://logs.example.test/health'));
    assert.equal(configured.startOptions.env.SHEETS_SYNC_ENABLED, 'true');
    assert.equal(configured.startOptions.env.GOOGLE_SERVICE_ENABLED, 'true');
    assert.equal(configured.startOptions.env.GOOGLE_SPREADSHEET_ID, 'test-sheet-not-a-real-account');
    assert.equal(configured.startOptions.env.ERP_BUSINESS_ID, '7');
});


test('warm requests skip readiness probes, but a stopped container is probed again', async () => {
    const h = workerHarness();
    await h.worker.fetch(new Request('https://logs.example.test/tools'));
    assert.equal(h.probes, 1);
    await h.worker.fetch(new Request('https://logs.example.test/tools'));
    assert.equal(h.probes, 1);
    h.container.running = false;
    await h.worker.fetch(new Request('https://logs.example.test/tools'));
    assert.equal(h.probes, 2);
});

test('a failed forwarded mutation is not replayed and invalidates readiness', async () => {
    const h = workerHarness();
    await h.worker.fetch(new Request('https://logs.example.test/tools'));
    const original = h.container.getTcpPort;
    let attempts = 0;
    h.container.getTcpPort = () => ({ fetch: async () => { attempts++; throw new Error('Disconnected'); } });
    await assert.rejects(h.worker.fetch(new Request('https://logs.example.test/save', { method: 'POST', body: 'x=1' })), /Disconnected/);
    assert.equal(attempts, 1);
    h.container.getTcpPort = original;
    await h.worker.fetch(new Request('https://logs.example.test/tools'));
    assert.equal(h.probes, 2);
});
