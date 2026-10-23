const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

// Dependency-free DOM/event seam. The same application source is also exercised with real jQuery/JSDOM.
function harness() {
    const document = {};
    const handlers = [];
    const pending = [];
    const events = [];
    const forms = [];
    const modals = [];
    let completeHandler;
    let errorHandler;
    function node(kind, attributes = {}) {
        return { kind, attributes, data: {}, classes: new Set(), html: kind === 'button' ? 'SAVE' : '', disabled: false, parent: null };
    }
    function dispatch(event, target, ...args) {
        for (const handler of [...handlers]) {
            if (handler.event.split('.')[0] !== event.split('.')[0]) continue;
            if (handler.selector === 'form:not([data-staff-profile])' && target.staff) continue;
            if (handler.selector === 'form[data-staff-profile]' && !target.staff) continue;
            handler.callback.call(target, { preventDefault() {} }, ...args);
        }
    }
    class Collection {
        constructor(items = []) { this.items = items; this.length = items.length; items.forEach((item, index) => this[index] = item); }
        on(event, selector, callback) { handlers.push({ event, selector, callback }); return this; }
        off(event, selector) {
            for (let i = handlers.length - 1; i >= 0; i--) {
                if (handlers[i].event === event && handlers[i].selector === selector) handlers.splice(i, 1);
            }
            return this;
        }
        ajaxComplete(callback) { completeHandler = callback; return this; }
        ajaxError(callback) { errorHandler = callback; return this; }
        each(callback) { this.items.forEach((item, index) => callback.call(item, index, item)); return this; }
        first() { return new Collection(this.items.slice(0, 1)); }
        filter(callback) { return typeof callback === 'function' ? new Collection(this.items.filter((item, index) => callback.call(item, index, item))) : this; }
        data(key, value) {
            if (arguments.length === 1) return this.items[0] && this.items[0].data[key];
            return this.each(function () { this.data[key] = value; });
        }
        removeData(key) { return this.each(function () { delete this.data[key]; }); }
        attr(key) { return this.items[0] && this.items[0].attributes[key]; }
        prop(key, value) {
            if (arguments.length === 1) return this.items[0] && this.items[0][key];
            return this.each(function () { this[key] = value; });
        }
        html(value) {
            if (arguments.length === 0) return this.items[0] && this.items[0].html;
            return this.each(function () {
                this.html = value;
                if (this.kind === 'modal' && this.form) { this.form.parent = null; this.form = null; }
            });
        }
        addClass(value) { return this.each(function () { this.classes.add(value); }); }
        removeClass(value) { return this.each(function () { this.classes.delete(value); }); }
        closest(selector) {
            const found = [];
            this.each(function () {
                let current = this;
                while (current) {
                    if ((selector === '.modal' && current.kind === 'modal') || (selector === 'form[data-staff-profile]' && current.kind === 'form' && current.staff)) {
                        found.push(current); break;
                    }
                    current = current.parent;
                }
            });
            return new Collection(found);
        }
        find(selector) {
            const found = [];
            this.each(function () {
                if (this.kind === 'form' && selector.startsWith('button[type="submit"]')) found.push(this.button);
                if (this.kind === 'modal' && selector === '.modal-content') found.push({ kind: 'content', parent: this });
            });
            return new Collection(found);
        }
        appendTo() { this.each(function () { if (this.kind === 'modal') modals.push(this); }); return this; }
        modal(action) {
            return this.each(function () {
                events.push(['modal', action, this]); this.visible = action === 'show';
                if (action === 'hide') dispatch('hidden.bs.modal', this);
            });
        }
        remove() {
            return this.each(function () {
                if (this.parent && this.parent.form) { this.parent.form.parent = null; this.parent.form = null; }
            });
        }
        trigger(event, args = []) {
            events.push([event, ...args]);
            return this;
        }
        text() { return ''; }
        wrap() { return this; }
    }
    function $(target) {
        if (typeof target === 'function') { target(); return new Collection(); }
        if (target instanceof Collection) return target;
        if (target === document) return new Collection([document]);
        if (target === '.global-modal') return new Collection(modals.filter(modal => modal.global));
        if (target === '.btn.is-loading') return new Collection(forms.map(form => form.button).filter(button => button.classes.has('is-loading')));
        if (typeof target === 'string' && target.startsWith('<div class="modal')) {
            const modal = node('modal'); modal.global = true; return new Collection([modal]);
        }
        if (typeof target === 'string') return new Collection();
        return new Collection(target ? [target] : []);
    }
    $.contains = (ancestor, child) => {
        for (let current = child.parent; current; current = current.parent) if (current === ancestor) return true;
        return false;
    };
    function finish(request, successful, response = {}, status = 'error') {
        if (request.done) return;
        request.done = true;
        if (successful) request.options.success(response);
        else {
            request.options.error(response, status);
            errorHandler({}, response, request.options);
        }
        if (request.options.complete) request.options.complete();
        if (completeHandler) completeHandler();
    }
    $.ajax = options => {
        const request = { options, done: false, abort() { finish(this, false, { status: 0 }, 'abort'); } };
        pending.push(request); return request;
    };
    const toastr = {
        success(message) { events.push(['success', message]); },
        error(message) { events.push(['error', message]); },
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../resources/js/app.js'), 'utf8'), {
        require() {}, $, document, window: { toastr }, FormData: class { constructor(form) { this.form = form; } },
    });
    function form(modal = null, staff = true) {
        if (!modal) { modal = node('modal'); modal.visible = true; modals.push(modal); }
        const item = node('form', { action: '/admin/users/2' }); item.staff = staff; item.parent = modal;
        item.button = node('button'); item.button.parent = item; modal.form = item;
        forms.push(item); return item;
    }
    return {
        events, pending, modals, form,
        submit(item) { dispatch('submit', item); },
        close(modal) { $(modal).modal('hide'); },
        unrelatedComplete() { completeHandler(); },
        resolve(index, response) { finish(pending[index], true, response); },
        reject(index, response = {}) { finish(pending[index], false, response); },
        globalError(xhr, settings = {}) { errorHandler({}, xhr, settings); },
        openProfile() { const button = node('button'); button.data.href = '/admin/profile'; dispatch('click', button); },
    };
}

test('staff submit runs globally, ignores the generic loading handler and blocks repeated saves', () => {
    const h = harness(); const form = h.form();
    h.submit(form); h.submit(form);
    assert.equal(h.pending.length, 1);
    assert.equal(form.button.disabled, true);
    assert.equal(h.pending[0].options.method, 'POST');
    assert.equal(h.pending[0].options.url, '/admin/users/2');
    h.unrelatedComplete();
    assert.equal(form.button.disabled, true);
    h.submit(form); assert.equal(h.pending.length, 1);
});

test('successful staff saves close their modal, restore loading and emit a table refresh event', () => {
    const h = harness(); const form = h.form(); const modal = form.parent;
    h.submit(form); h.resolve(0, { success: true, msg: 'Saved' });
    assert.equal(modal.visible, false);
    assert.equal(form.button.disabled, false);
    assert.equal(form.button.html, 'SAVE');
    assert.equal(form.data.submitting, false);
    assert.equal(h.events.filter(event => event[0] === 'staff:updated').length, 1);
    assert.equal(h.events.filter(event => event[0] === 'success').length, 1);
});

test('server validation, message-only and network failures give one toast and remain retryable', () => {
    for (const [response, expected] of [
        [{ status: 422, responseJSON: { errors: { password: ['Please confirm the password.'] } } }, 'Please confirm the password.'],
        [{ status: 422, responseJSON: { msg: 'Invalid profile.' } }, 'Invalid profile.'],
        [{ status: 403, responseJSON: { message: 'Access denied.' } }, 'Access denied.'],
        [{ status: 0 }, 'Unable to save the staff profile. Please try again.'],
    ]) {
        const h = harness(); const form = h.form();
        h.submit(form); h.reject(0, response);
        assert.equal(form.button.disabled, false);
        assert.equal(form.parent.visible, true);
        assert.deepEqual(h.events.filter(event => event[0] === 'error'), [['error', expected]]);
        h.submit(form); assert.equal(h.pending.length, 2);
    }
});

test('logical failures do not close a modal or emit an update event', () => {
    for (const response of [null, { success: false, msg: 'Not saved.' }, { success: 'true' }]) {
        const h = harness(); const form = h.form();
        h.submit(form); h.resolve(0, response);
        assert.equal(form.parent.visible, true);
        assert.equal(form.button.disabled, false);
        assert.ok(!h.events.some(event => event[0] === 'staff:updated'));
    }
});

test('completion of a closed form never dismisses a different newly opened profile', () => {
    const h = harness(); const old = h.form(); const modal = old.parent;
    h.submit(old); h.close(modal);
    const current = h.form(modal); modal.visible = true;
    h.resolve(0, { success: true });
    assert.equal(modal.visible, true);
    assert.equal(current.parent, modal);
    assert.equal(h.events.filter(event => event[0] === 'modal' && event[1] === 'hide').length, 1);
});

test('global AJAX errors skip locally handled requests but report unhandled errors', () => {
    const h = harness(); const xhr = { status: 500, responseJSON: { message: 'Server unavailable.' } };
    h.globalError(xhr, { error() {} });
    assert.equal(h.events.filter(event => event[0] === 'error').length, 0);
    h.globalError(xhr);
    assert.deepEqual(h.events.filter(event => event[0] === 'error'), [['error', 'Server unavailable.']]);
});

test('repeated profile opens and close during loading prevent stale content from returning', () => {
    const h = harness(); h.openProfile(); h.openProfile();
    assert.equal(h.pending[0].done, true);
    const modal = h.modals[0];
    h.resolve(1, '<div>Current profile</div>');
    h.pending[0].options.success('<div>Stale profile</div>');
    assert.equal(modal.html, '<div>Current profile</div>');
    h.openProfile(); h.close(modal);
    h.pending[2].options.success('<div>Closed profile</div>');
    assert.notEqual(modal.html, '<div>Closed profile</div>');
    assert.equal(modal.visible, false);
});

test('profile load failures close the loading modal with one error message', () => {
    const h = harness(); h.openProfile();
    h.reject(0, { status: 500, responseJSON: { msg: 'Profile unavailable.' } });
    assert.equal(h.modals[0].visible, false);
    assert.deepEqual(h.events.filter(event => event[0] === 'error'), [['error', 'Profile unavailable.']]);
});

test('non-staff forms retain the original generic loading behavior', () => {
    const h = harness(); const form = h.form(null, false);
    h.submit(form);
    assert.equal(form.button.disabled, true);
    assert.equal(h.pending.length, 0);
    h.unrelatedComplete();
    assert.equal(form.button.disabled, false);
});
