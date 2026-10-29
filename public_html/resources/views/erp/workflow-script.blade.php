@php($erpOldInput = $errors->any() ? \Illuminate\Support\Arr::except(session()->getOldInput(), ['_token','password','password_confirmation','current_password']) : [])
<script type="application/json" id="erp-old-input">@json($erpOldInput)</script>
<script>
(function () {
    'use strict';
    var oldNode = document.getElementById('erp-old-input');
    var old = oldNode ? JSON.parse(oldNode.textContent) : {};
    var recoveredForm = null;
    function reveal(element) {
        for (var parent = element.parentElement; parent; parent = parent.parentElement) {
            if (parent.tagName === 'DETAILS') parent.open = true;
        }
    }
    function revealAction() {
        var id = window.location.hash.slice(1);
        if (!/^action-[a-z-]+$/.test(id)) return;
        var target = document.getElementById(id);
        if (target && target.tagName === 'DETAILS') {
            target.open = true;
            target.scrollIntoView();
            var summary = target.querySelector('summary');
            if (summary) summary.focus({preventScroll:true});
        }
    }
    document.querySelectorAll('form[method="post"]').forEach(function (form, index) {
        // Include immutable row identities so a failed queue action cannot populate another record.
        var identity = Array.from(form.querySelectorAll('input[type="hidden"]'))
            .filter(function (input) { return input.name !== '_token' && input.name !== '_erp_form'; })
            .map(function (input) { return [input.name, input.value]; });
        var key = JSON.stringify([new URL(form.action, window.location.origin).pathname, identity]);
        var marker = document.createElement('input');
        marker.type = 'hidden'; marker.name = '_erp_form'; marker.value = key; form.appendChild(marker);
        var restore = old._erp_form === key && !recoveredForm;
        if (restore) { recoveredForm = form; reveal(form); }
        form.querySelectorAll('input:not([type="hidden"]),select,textarea').forEach(function (control, fieldIndex) {
            if (!control.id) control.id = 'erp-field-' + index + '-' + fieldIndex;
            var label = control.closest('label');
            // A lookup's visible label names its select; the search has its own explicit accessible name.
            if (label && !control.classList.contains('entity-search')) label.htmlFor = control.id;
            if (!restore || !control.name || control.type === 'password' || control.type === 'file') return;
            var name = control.name.replace(/\[\]$/, '');
            if (!Object.prototype.hasOwnProperty.call(old, name)) {
                if (control.type === 'checkbox') control.checked = false;
                return;
            }
            var value = old[name];
            if (control.type === 'checkbox' || control.type === 'radio') {
                control.checked = (Array.isArray(value) ? value : [value]).map(String).includes(control.value);
            } else if (value === null || typeof value !== 'object') {
                if (control.classList.contains('entity-picker') && value) {
                    control.add(new Option('Previously selected record #' + String(value) + ' — verify before saving', String(value)));
                }
                control.value = value === null ? '' : String(value);
            }
        });
    });
    var summary = document.getElementById('error-summary');
    if (summary) {
        if (recoveredForm) summary.querySelectorAll('[data-error-field]').forEach(function (item, index) {
            var name = item.dataset.errorField.replace(/\.\d+$/, '');
            var control = Array.from(recoveredForm.elements).find(function (field) {
                return field.type !== 'hidden' && (field.name === name || field.name === name + '[]');
            });
            if (!control) return;
            var message = item.textContent;
            var link = document.createElement('a'); link.href = '#' + control.id; link.textContent = message;
            link.addEventListener('click', function (event) { event.preventDefault(); reveal(control); control.focus(); });
            item.replaceChildren(link);
            var error = document.createElement('span'); error.id = 'erp-field-error-' + index;
            error.className = 'field-error'; error.textContent = message;
            control.insertAdjacentElement('afterend', error);
            control.setAttribute('aria-invalid', 'true');
            control.setAttribute('aria-describedby', ((control.getAttribute('aria-describedby') || '') + ' ' + error.id).trim());
        });
        document.title = 'Error: ' + document.title;
        summary.focus();
    } else revealAction();
    window.addEventListener('hashchange', revealAction);
    window.addEventListener('click', function (event) {
        var link = event.target.closest('a[href^="#action-"]');
        if (link && link.getAttribute('href') === window.location.hash) {
            event.preventDefault(); revealAction();
        }
    });
    document.querySelectorAll('.entity-picker').forEach(function (select, index) {
        var search = select.parentElement.querySelector('.entity-search'), timer, sequence = 0, started = false;
        var label = select.closest ? select.closest('label') : null;
        if (label) {
            var group = document.createElement('div'); group.className = 'entity-field';
            label.insertAdjacentElement('beforebegin', group); group.appendChild(label);
            // Keep one control per label; search and retry have independent accessible names.
            Array.from(label.children).forEach(function (child) { group.appendChild(child); });
            label.htmlFor = select.id;
        }
        var status = document.createElement('span'); status.className = 'lookup-status';
        status.id = 'lookup-status-' + index; status.setAttribute('role', 'status');
        select.insertAdjacentElement('afterend', status);
        select.setAttribute('aria-describedby', ((select.getAttribute('aria-describedby') || '') + ' ' + status.id).trim());
        var retry = document.createElement('button'); retry.type = 'button'; retry.className = 'lookup-retry';
        retry.textContent = 'Retry record search'; retry.hidden = true; status.insertAdjacentElement('afterend', retry);
        function load() {
            started = true;
            var ticket = ++sequence, url = new URL(select.dataset.url, window.location.origin);
            url.searchParams.set('q', search ? search.value : '');
            status.textContent = 'Searching records…'; retry.hidden = true; select.setAttribute('aria-busy', 'true');
            fetch(url.toString(), {headers:{Accept:'application/json'},credentials:'same-origin'})
                .then(function (response) { if (!response.ok) throw new Error('Lookup unavailable'); return response.json(); })
                .then(function (rows) {
                    if (ticket !== sequence) return;
                    if (!Array.isArray(rows) || rows.some(function (row) { return !row || !['string','number'].includes(typeof row.id) || typeof row.label !== 'string'; })) throw new Error('Invalid lookup response');
                    var current = select.value, selected = select.options[select.selectedIndex];
                    var label = selected ? selected.textContent : 'Previously selected record #' + current;
                    select.replaceChildren(new Option('Choose a record', ''));
                    rows.forEach(function (row) { select.add(new Option(row.label, String(row.id))); });
                    var retained = current && !rows.some(function (row) { return String(row.id) === current; });
                    if (retained) select.add(new Option(label, current));
                    select.value = current;
                    status.textContent = (rows.length ? rows.length + ' matching records.' : 'No matching records. Try another name, code or reference.') + (retained ? ' Your previous selection is kept; verify it before saving.' : '');
                    select.removeAttribute('aria-busy');
                }).catch(function () {
                    if (ticket !== sequence) return;
                    status.textContent = 'Record search unavailable. Your selection is unchanged. Retry the search.';
                    retry.hidden = false; select.removeAttribute('aria-busy');
                });
        }
        if (search) search.addEventListener('input', function () {
            // Invalidate in-flight results immediately, not only when the debounce finishes.
            ++sequence; clearTimeout(timer); timer = setTimeout(load, 200);
        });
        retry.addEventListener('click', load);
        // Closed forms do not need lookup requests until the user opens them.
        var disclosures = [];
        for (var ancestor = select.parentElement; ancestor; ancestor = ancestor.parentElement) {
            if (ancestor.tagName === 'DETAILS') disclosures.push(ancestor);
        }
        function loadWhenRevealed() {
            if (!started && disclosures.every(function (details) { return details.open; })) load();
        }
        disclosures.forEach(function (details) { details.addEventListener('toggle', loadWhenRevealed); });
        loadWhenRevealed();
    });
})();
</script>
