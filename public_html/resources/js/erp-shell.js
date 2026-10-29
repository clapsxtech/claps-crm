(() => {
    const toggles = [...document.querySelectorAll('.menu-toggle, [data-navigation-toggle]')];
    let opener = toggles[0];
    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.querySelector('.nav-backdrop');
    const content = document.querySelector('.app-body');
    if (!toggles.length || !sidebar || !backdrop) return;
    const mobile = window.matchMedia('(max-width: 1000px)');
    let open = false;
    const setOpen = (value, returnFocus = false) => {
        open = value;
        document.body.classList.toggle('nav-open', value);
        toggles.forEach(toggle => {
            toggle.setAttribute('aria-expanded', String(value));
            toggle.setAttribute('aria-label', value ? 'Close navigation' : 'Open navigation');
        });
        backdrop.hidden = !value;
        sidebar.inert = mobile.matches && !value;
        if (content) content.inert = mobile.matches && value;
        if (value) sidebar.querySelector('a')?.focus();
        else if (returnFocus) opener?.focus();
    };
    toggles.forEach(toggle => toggle.addEventListener('click', () => {
        opener = toggle;
        setOpen(!open, open);
    }));
    backdrop.addEventListener('click', () => setOpen(false, true));
    document.addEventListener('keydown', event => {
        if (!open) return;
        if (event.key === 'Escape') { setOpen(false, true); return; }
        if (event.key === 'Tab') {
            const items = [...sidebar.querySelectorAll('a, button, summary')].filter(el => el.getClientRects().length);
            const first = items[0], last = items.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
    mobile.addEventListener('change', () => setOpen(false));
    setOpen(false);
})();

// Keep one set of records and forms. Enhance simple tables into labelled cards on phones.
// Complex reports retain their horizontal table so column relationships stay intact.
(() => {
    document.querySelectorAll('table.mobile-records').forEach(table => {
        const headings = table.tHead?.rows;
        if (!headings || headings.length !== 1) return;
        const columns = [...headings[0].cells];
        if (columns.some(cell => cell.colSpan !== 1 || cell.rowSpan !== 1)) return;
        const rows = [...table.tBodies].flatMap(body => [...body.rows]);
        if (rows.some(row => [...row.cells].some(cell => cell.rowSpan !== 1) ||
            (row.cells.length !== columns.length && !(row.cells.length === 1 && row.cells[0].colSpan > 1)))) return;
        table.setAttribute('role', 'table');
        table.tHead.setAttribute('role', 'rowgroup');
        headings[0].setAttribute('role', 'row');
        columns.forEach(cell => { cell.scope = 'col'; cell.setAttribute('role', 'columnheader'); });
        [...table.tBodies].forEach(body => body.setAttribute('role', 'rowgroup'));
        rows.forEach(row => {
            row.setAttribute('role', 'row');
            [...row.cells].forEach((cell, index) => {
                cell.setAttribute('role', 'cell');
                if (cell.colSpan > 1) return;
                const label = document.createElement('span');
                label.className = 'mobile-field-label';
                label.setAttribute('aria-hidden', 'true');
                label.textContent = columns[index].textContent.trim() || 'Actions';
                cell.prepend(label);
            });
        });
        table.classList.add('mobile-ready');
        table.closest('.scroll')?.classList.add('mobile-record-scroll');
    });
    // A closed disclosure containing an invalid required field must open before native validation focuses it.
    document.addEventListener('invalid', event => {
        for (let parent = event.target.parentElement; parent; parent = parent.parentElement) {
            if (parent.tagName === 'DETAILS') parent.open = true;
        }
    }, true);
})();

// Exact unit-local dates come from the server, avoiding device timezone offsets.
document.addEventListener('click', event => {
    const shortcut = event.target.closest('[data-crm-date]');
    if (!shortcut) return;
    const form = shortcut.closest('form');
    const input = form?.elements.namedItem(shortcut.dataset.crmField);
    if (!(input instanceof HTMLInputElement) || input.type !== 'date') return;
    input.value = shortcut.dataset.crmDate;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    input.focus({ preventScroll: true });
});
