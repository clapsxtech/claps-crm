(() => {
    const toggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.querySelector('.nav-backdrop');
    const content = document.querySelector('.app-body');
    if (!toggle || !sidebar || !backdrop) return;
    const mobile = window.matchMedia('(max-width: 1000px)');
    let open = false;
    const setOpen = (value, returnFocus = false) => {
        open = value;
        document.body.classList.toggle('nav-open', value);
        toggle.setAttribute('aria-expanded', String(value));
        toggle.setAttribute('aria-label', value ? 'Close navigation' : 'Open navigation');
        backdrop.hidden = !value;
        sidebar.inert = mobile.matches && !value;
        if (content) content.inert = mobile.matches && value;
        if (value) sidebar.querySelector('a')?.focus();
        else if (returnFocus) toggle.focus();
    };
    toggle.addEventListener('click', () => setOpen(!open, open));
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
