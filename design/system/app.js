/* ==========================================================================
   ClapsLearn ERP — prototype runtime
   Each screen is a plain HTML file containing only its <main> content.
   This script wraps it in the app shell and wires up every interaction
   pattern declared through data-attributes, so screens stay declarative.
   ========================================================================== */
(() => {
  const ICONS = {
    home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/><path d="M10 20v-6h4v6"/>',
    inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    funnel: '<path d="M3 4h18l-7 8.5V19l-4 2v-8.5z"/>',
    spark: '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/>',
    chart: '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
    users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    grad: '<path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>',
    calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    checkCircle: '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    book: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5V21h16"/>',
    wallet: '<path d="M20 12V8H6a2 2 0 0 1 0-4h12v4"/><path d="M4 6v12a2 2 0 0 0 2 2h14v-4"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>',
    receipt: '<path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 1 .7V2l-3 2-3-2-3 2-3-2-3 2z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    key: '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.3-9.3M17 6l3 3M14.5 8.5l2 2"/>',
    activity: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    database: '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
    upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v12"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    bell: '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    chevronRight: '<path d="m9 18 6-6-6-6"/>',
    chevronLeft: '<path d="m15 18-6-6 6-6"/>',
    chevronDown: '<path d="m6 9 6 6 6-6"/>',
    chevronsUpDown: '<path d="m7 15 5 5 5-5M7 9l5-5 5 5"/>',
    arrowUp: '<path d="M12 19V5M5 12l7-7 7 7"/>',
    arrowRight: '<path d="M5 12h14M12 5l7 7-7 7"/>',
    trendUp: '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
    trendDown: '<path d="m22 17-8.5-8.5-5 5L2 7"/><path d="M16 17h6v-6"/>',
    more: '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
    filter: '<path d="M4 6h16M7 12h10M10 18h4"/>',
    sliders: '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
    phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
    message: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    alert: '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
    edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
    copy: '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    printer: '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
    send: '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
    swap: '<path d="M16 3l4 4-4 4M20 7H4M8 21l-4-4 4-4M4 17h16"/>',
    sidebar: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
    moon: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
    rows: '<path d="M3 6h18M3 12h18M3 18h18"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    flame: '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14 0-5.5 3-7 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
    video: '<path d="m23 7-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/>',
    refresh: '<path d="M21 2v6h-6M3 22v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L21 8M3 16l2.64 2.36A9 9 0 0 0 20.49 15"/>',
    building: '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
    star: '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>',
    eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    target: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
    command: '<path d="M18 3a3 3 0 0 0-3 3v12a3 3 0 1 0 3-3H6a3 3 0 1 0 3 3V6a3 3 0 1 0-3 3h12a3 3 0 0 0 0-6z"/>',
    layers: '<path d="m12 2 10 5-10 5L2 7z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/>',
  };
  const icon = (name, cls = '') => `<svg class="icon ${cls}" viewBox="0 0 24 24" aria-hidden="true">${ICONS[name] || ''}</svg>`;
  window.icon = icon;

  /* ---- Information architecture (rethought) ----
     Global (cross-unit) → Unit work grouped by job-to-be-done → Admin.
     `roles` mirrors TeamPermissions so the role switcher can preview access. */
  const NAV = [
    { label: null, items: [
      { id: 'home', text: 'Today', icon: 'home', href: 'dashboard.html' },
      { id: 'tasks', text: 'My tasks', icon: 'inbox', href: 'tasks.html', count: 7 },
    ]},
    { label: 'Growth', items: [
      { id: 'pipeline', text: 'Pipeline', icon: 'funnel', href: 'pipeline.html', count: 12, roles: ['manager', 'sales', 'readonly'] },
      { id: 'sales', text: 'Sales report', icon: 'chart', href: 'sales-report.html', roles: ['manager', 'sales', 'readonly'] },
    ]},
    { label: 'People', items: [
      { id: 'students', text: 'Students', icon: 'grad', href: 'students.html', roles: ['manager', 'academics', 'readonly'] },
      { id: 'teachers', text: 'Teachers', icon: 'users', href: 'teachers.html', roles: ['manager', 'academics', 'sales', 'readonly'] },
      { id: 'matching', text: 'Teacher matching', icon: 'link', href: 'matching.html', roles: ['manager', 'academics', 'sales', 'readonly'] },
    ]},
    { label: 'Teaching', items: [
      { id: 'timetable', text: 'Timetable', icon: 'calendar', href: 'timetable.html', roles: ['manager', 'academics', 'readonly'] },
      { id: 'attendance', text: 'Attendance', icon: 'checkCircle', href: 'attendance.html', count: 18, roles: ['manager', 'academics', 'readonly'] },
      { id: 'teacher', text: 'Teacher portal', icon: 'book', href: 'teacher-portal.html' },
    ]},
    { label: 'Money', items: [
      { id: 'money', text: 'Ledger & receipts', icon: 'wallet', href: 'finance.html', roles: ['manager', 'finance', 'readonly'] },
      { id: 'requests', text: 'Payment requests', icon: 'send', href: 'payment-requests.html', roles: ['manager', 'finance', 'readonly'] },
      { id: 'statement', text: 'Parent statements', icon: 'file', href: 'statement.html', roles: ['manager', 'finance', 'readonly'] },
    ]},
    { label: 'Admin', items: [
      { id: 'team', text: 'Team & access', icon: 'key', href: 'team.html', roles: ['manager'] },
      { id: 'ops', text: 'Operations & audit', icon: 'activity', href: 'operations.html', roles: ['manager'] },
      { id: 'migration', text: 'Migration & exports', icon: 'database', href: 'migration.html', roles: ['manager'] },
      { id: 'security', text: 'Account security', icon: 'shield', href: 'security.html' },
    ]},
  ];
  const ROLES = { manager: 'Team manager', academics: 'Academics', finance: 'Finance', sales: 'Sales', readonly: 'Read-only' };

  const store = {
    get: (k, d) => { try { return localStorage.getItem('cl:' + k) ?? d; } catch { return d; } },
    set: (k, v) => { try { localStorage.setItem('cl:' + k, v); } catch {} },
  };

  /* ================= Shell ================= */
  function buildShell() {
    const body = document.body;
    const main = body.querySelector('main');
    if (!main || body.dataset.shell === 'none') return;
    const page = body.dataset.page;
    const role = store.get('role', 'manager');
    const crumbs = (body.dataset.crumbs || '').split('/').filter(Boolean);

    const navHtml = NAV.map(g => {
      const items = g.items.filter(i => !i.roles || i.roles.includes(role));
      if (!items.length) return '';
      return `<div class="nav-group">${g.label ? `<div class="nav-label t-overline">${g.label}</div>` : ''}${items.map(i => `
        <a class="nav-item" href="${i.href}" data-tip="${i.text}" data-tip-side="right" ${i.id === page ? 'aria-current="page"' : ''}>
          ${icon(i.icon)}<span class="nav-text">${i.text}</span>${i.count ? `<span class="count ${i.id === 'tasks' ? 'accent' : ''}">${i.count}</span>` : ''}
        </a>`).join('')}</div>`;
    }).join('');

    const shell = document.createElement('div');
    shell.className = 'shell';
    shell.dataset.collapsed = store.get('collapsed', 'false');
    shell.innerHTML = `
      <a href="#content" class="sr-only">Skip to content</a>
      <aside class="sidebar" aria-label="Primary">
        <div class="sidebar-head">
          <div class="brand-mark">cl</div>
          <div class="brand-name">ClapsLearn<small>Operations</small></div>
        </div>
        <div style="padding: 0 var(--s-3) var(--s-2)">
          <button class="user-chip" data-menu="unit-menu" style="border:1px solid var(--border-default)">
            <span class="avatar sm" style="--av-bg:var(--violet-50);--av-fg:var(--violet-700);border-radius:6px">KT</span>
            <span class="grow nav-text truncate strong t-small">Kochi Tuition</span>
            <span class="nav-text">${icon('chevronsUpDown', 'icon-sm')}</span>
          </button>
        </div>
        <nav class="sidebar-scroll">${navHtml}</nav>
        <div class="sidebar-foot">
          <button class="user-chip" data-menu="user-menu">
            <span class="avatar" data-presence="online">AA</span>
            <span class="grow stack-xs" style="gap:0;min-width:0"><span class="t-small strong truncate">Amnas Ahamed</span><span class="t-caption truncate">${ROLES[role]}</span></span>
            ${icon('chevronsUpDown', 'icon-sm')}
          </button>
        </div>
      </aside>
      <div class="main">
        <header class="topbar">
          <button class="btn btn-ghost btn-icon mobile-only" data-action="nav-open" aria-label="Open navigation">${icon('rows')}</button>
          <button class="btn btn-ghost btn-icon desktop-only" data-action="collapse" data-tip="Toggle sidebar" data-kbd="[" aria-label="Toggle sidebar">${icon('sidebar')}</button>
          <nav class="breadcrumbs desktop-only" aria-label="Breadcrumb">
            <a href="dashboard.html">Kochi Tuition</a>${crumbs.map((c, i) => `<span class="sep">/</span>${i === crumbs.length - 1 ? `<span style="color:var(--fg-default)">${c}</span>` : `<a href="#">${c}</a>`}`).join('')}
          </nav>
          <div class="grow"></div>
          <button class="search-trigger" data-action="cmdk">${icon('search', 'icon-sm')}<span>Search students, leads, receipts…</span><span class="kbd">⌘K</span></button>
          <button class="btn btn-ghost btn-icon" data-action="theme" data-tip="Toggle theme" aria-label="Toggle theme">${icon(document.documentElement.dataset.theme === 'dark' ? 'sun' : 'moon')}</button>
          <button class="btn btn-ghost btn-icon has-dot" data-menu="notif-menu" data-tip="Notifications" aria-label="Notifications">${icon('bell')}</button>
        </header>
        <div id="content"></div>
      </div>
      <div class="toaster" role="status" aria-live="polite"></div>`;
    main.classList.add('page', 'page-enter');
    body.prepend(shell);
    shell.querySelector('#content').appendChild(main);

    window.addEventListener('scroll', () => {
      shell.querySelector('.topbar').dataset.scrolled = window.scrollY > 4;
    }, { passive: true });
  }

  /* Menus are declared once here and opened with data-menu="id" */
  const MENUS = {
    'user-menu': () => {
      const role = store.get('role', 'manager');
      return `<div class="menu-label t-overline">Preview as role</div>
        ${Object.entries(ROLES).map(([k, v]) => `<button class="menu-item" data-role="${k}">${icon(k === role ? 'check' : 'user')}${v}</button>`).join('')}
        <div class="menu-sep"></div>
        <button class="menu-item" data-action="density">${icon('rows')}Toggle density<span class="kbd">D</span></button>
        <a class="menu-item" href="security.html">${icon('shield')}Account security</a>
        <div class="menu-sep"></div>
        <button class="menu-item danger">${icon('logout')}Sign out</button>`;
    },
    'unit-menu': () => `<div class="menu-label t-overline">Business units</div>
      <button class="menu-item">${icon('check')}Kochi Tuition<span class="kbd">1</span></button>
      <button class="menu-item">${icon('building')}Gulf Online<span class="kbd">2</span></button>
      <button class="menu-item">${icon('building')}Quran Academy<span class="kbd">3</span></button>
      <div class="menu-sep"></div><button class="menu-item">${icon('plus')}New business unit</button>`,
    'notif-menu': () => `<div class="row between" style="padding:var(--s-2) var(--s-2) var(--s-1)"><span class="t-small strong">Notifications</span><button class="btn btn-link t-caption">Mark all read</button></div>
      <div class="list" style="width:340px;margin:0 calc(var(--s-1)*-1)">
        ${[['alert', 'warning', '3 attendance entries need review', 'Duplicate check · 4m'], ['wallet', 'success', '₹12,500 received from Fathima R.', 'Receipt CL-2291 · 22m'], ['funnel', 'info', 'Lead transferred to you: Arjun M.', 'From Shahana · 1h'], ['calendar', '', 'Rahul K. marked unavailable Fri', 'Timetable · 3h']]
          .map(([ic, tone, t, m]) => `<button class="list-item" style="padding:var(--s-3)"><span class="tl-dot ${tone}">${icon(ic)}</span><span class="grow"><span class="t-small" style="display:block">${t}</span><span class="t-caption">${m}</span></span></button>`).join('')}
      </div>`,
    'row-menu': () => `<button class="menu-item">${icon('eye')}Open<span class="kbd">↵</span></button>
      <button class="menu-item">${icon('edit')}Edit<span class="kbd">E</span></button>
      <button class="menu-item">${icon('copy')}Duplicate</button>
      <button class="menu-item">${icon('swap')}Transfer to colleague</button>
      <div class="menu-sep"></div>
      <button class="menu-item danger" data-action="confirm-delete">${icon('trash')}Archive</button>`,
  };

  /* ================= Floating layer helpers ================= */
  let openMenu = null;
  function closeMenu() {
    if (!openMenu) return;
    openMenu.el.remove();
    openMenu.trigger.setAttribute('aria-expanded', 'false');
    openMenu = null;
  }
  function placeFloating(el, anchor, side = 'bottom') {
    const r = anchor.getBoundingClientRect();
    const w = el.offsetWidth, h = el.offsetHeight, pad = 8;
    let x, y;
    if (side === 'right') { x = r.right + pad; y = r.top + r.height / 2 - h / 2; }
    else if (side === 'top') { x = r.left + r.width / 2 - w / 2; y = r.top - h - pad; }
    else { x = r.left; y = r.bottom + 6; if (x + w > innerWidth - pad) x = r.right - w; if (y + h > innerHeight - pad) { y = r.top - h - 6; el.style.transformOrigin = 'bottom left'; } }
    el.style.left = Math.max(pad, Math.min(x, innerWidth - w - pad)) + 'px';
    el.style.top = Math.max(pad, y) + 'px';
  }
  function toggleMenu(trigger) {
    const id = trigger.dataset.menu;
    if (openMenu && openMenu.trigger === trigger) return closeMenu();
    closeMenu();
    const tpl = MENUS[id] ? MENUS[id]() : document.getElementById(id)?.innerHTML;
    if (!tpl) return;
    const el = document.createElement('div');
    el.className = 'menu'; el.setAttribute('role', 'menu'); el.innerHTML = tpl;
    document.body.appendChild(el);
    enhance(el);
    placeFloating(el, trigger);
    trigger.setAttribute('aria-expanded', 'true');
    openMenu = { el, trigger };
    el.querySelector('.menu-item')?.focus({ preventScroll: true });
  }

  /* Tooltips: 400ms intent delay, instant once one is already showing */
  let tipEl = null, tipTimer = null, tipWarm = false;
  function showTip(t) {
    const shell = document.querySelector('.shell');
    if (t.dataset.tipSide === 'right' && shell?.dataset.collapsed !== 'true') return;
    hideTip();
    tipEl = document.createElement('div');
    tipEl.className = 'tooltip';
    tipEl.innerHTML = `${t.dataset.tip}${t.dataset.kbd ? `<span class="kbd">${t.dataset.kbd}</span>` : ''}`;
    document.body.appendChild(tipEl);
    placeFloating(tipEl, t, t.dataset.tipSide || 'top');
    tipWarm = true;
  }
  function hideTip() { clearTimeout(tipTimer); tipEl?.remove(); tipEl = null; }

  /* ================= Toasts ================= */
  function toast(msg, { undo, duration = 4200, iconName = 'checkCircle' } = {}) {
    const host = document.querySelector('.toaster') || document.body.appendChild(Object.assign(document.createElement('div'), { className: 'toaster' }));
    const el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = `${icon(iconName)}<div class="grow">${msg}</div>${undo ? '<button class="btn btn-sm">Undo</button>' : ''}<button class="btn btn-sm btn-icon" aria-label="Dismiss">${icon('x', 'icon-sm')}</button><span class="timer" style="animation-duration:${duration}ms"></span>`;
    host.appendChild(el);
    const leave = () => { if (el.dataset.leaving) return; el.dataset.leaving = ''; el.addEventListener('animationend', () => el.remove(), { once: true }); };
    el.querySelector('.timer').addEventListener('animationend', leave);
    const btns = el.querySelectorAll('.btn');
    btns[btns.length - 1].onclick = leave;
    if (undo) btns[0].onclick = () => { undo(); leave(); };
  }
  window.toast = toast;

  /* ================= Overlays (dialog / drawer / cmdk) ================= */
  let lastFocus = null;
  function openOverlay(html, cls = '') {
    lastFocus = document.activeElement;
    const ov = document.createElement('div');
    ov.className = 'overlay ' + cls;
    ov.innerHTML = html;
    ov.addEventListener('mousedown', e => { if (e.target === ov) closeOverlay(ov); });
    document.body.appendChild(ov);
    document.body.style.overflow = 'hidden';
    setTimeout(() => ov.querySelector('input, [autofocus], button')?.focus(), 30);
    return ov;
  }
  function closeOverlay(ov = document.querySelector('.overlay:last-of-type')) {
    if (!ov || ov.dataset.closing !== undefined) return;
    ov.dataset.closing = '';
    setTimeout(() => { ov.remove(); if (!document.querySelector('.overlay')) document.body.style.overflow = ''; lastFocus?.focus?.(); }, 160);
  }
  window.closeOverlay = closeOverlay;
  function openTemplate(id, kind) {
    const tpl = document.getElementById(id);
    if (!tpl) return false;
    const ov = openOverlay(tpl.innerHTML, kind === 'drawer' ? 'drawer-overlay' : '');
    enhance(ov);
    return true;
  }

  const CMDK_ITEMS = [
    ...NAV.flatMap(g => g.items.map(i => ({ group: 'Go to', text: i.text, icon: i.icon, href: i.href, hint: g.label || '' }))),
    { group: 'Create', text: 'New enquiry', icon: 'plus', hint: 'N then E', action: () => toast('Enquiry form opened') },
    { group: 'Create', text: 'Record payment', icon: 'wallet', hint: 'N then P', action: () => toast('Payment form opened') },
    { group: 'Create', text: 'Schedule a demo', icon: 'video', hint: 'N then D', action: () => toast('Demo scheduler opened') },
    { group: 'Students', text: 'Fathima Rahman · Grade 9 CBSE', icon: 'user', href: 'student.html', hint: 'CL-1042' },
    { group: 'Students', text: 'Aarav Nair · Grade 7 ICSE', icon: 'user', href: 'student.html', hint: 'CL-1031' },
    { group: 'Students', text: 'Zainab Ali · Grade 11 State', icon: 'user', href: 'student.html', hint: 'CL-0987' },
    { group: 'Leads', text: 'Arjun Menon · Maths enquiry', icon: 'funnel', href: 'pipeline.html', hint: 'Hot' },
    { group: 'Receipts', text: 'CL-2291 · ₹12,500', icon: 'receipt', href: 'receipt.html', hint: 'Today' },
  ];
  function openCmdk() {
    if (document.querySelector('.cmdk')) return;
    const ov = openOverlay(`<div class="cmdk" role="dialog" aria-label="Command menu">
      <div class="cmdk-input">${icon('search')}<input placeholder="Type a command or search…" aria-label="Search" /><span class="kbd">esc</span></div>
      <div class="cmdk-list" role="listbox"></div>
      <div class="cmdk-foot"><span><span class="kbd">↑</span><span class="kbd">↓</span>Navigate</span><span><span class="kbd">↵</span>Open</span><span><span class="kbd">esc</span>Close</span></div></div>`);
    const input = ov.querySelector('input'), list = ov.querySelector('.cmdk-list');
    let active = 0, results = [];
    const render = () => {
      const q = input.value.trim().toLowerCase();
      results = CMDK_ITEMS.filter(i => !q || (i.text + i.hint + i.group).toLowerCase().includes(q));
      if (!results.length) { list.innerHTML = `<div class="empty" style="padding:var(--s-8)"><div class="empty-art">${icon('search')}</div><div class="strong">No results for “${input.value}”</div><p class="t-small">Try a student code like CL-1042 or a receipt number.</p></div>`; return; }
      active = Math.min(active, results.length - 1);
      let lastGroup = '';
      list.innerHTML = results.map((r, idx) => {
        const head = r.group !== lastGroup ? `<div class="cmdk-group t-overline">${r.group}</div>` : '';
        lastGroup = r.group;
        return `${head}<button class="cmdk-item" data-i="${idx}" ${idx === active ? 'data-active="true"' : ''}>${icon(r.icon)}<span class="truncate">${r.text}</span><span class="hint">${r.hint}</span></button>`;
      }).join('');
    };
    const go = idx => { const r = results[idx]; if (!r) return; closeOverlay(ov); r.href ? (location.href = r.href) : r.action?.(); };
    input.addEventListener('input', () => { active = 0; render(); });
    input.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        active = (active + (e.key === 'ArrowDown' ? 1 : -1) + results.length) % results.length;
        render();
        list.querySelector('[data-active]')?.scrollIntoView({ block: 'nearest' });
      }
      if (e.key === 'Enter') go(active);
    });
    list.addEventListener('mousemove', e => { const b = e.target.closest('.cmdk-item'); if (b && +b.dataset.i !== active) { active = +b.dataset.i; list.querySelectorAll('.cmdk-item').forEach(x => x.toggleAttribute('data-active', x === b)); } });
    list.addEventListener('click', e => { const b = e.target.closest('.cmdk-item'); if (b) go(+b.dataset.i); });
    render();
  }

  /* ================= Enhancers (run on page + inside overlays) ================= */
  function moveThumb(seg) {
    const on = seg.querySelector('[aria-pressed="true"]'); const thumb = seg.querySelector('.thumb');
    if (on && thumb) { thumb.style.width = on.offsetWidth + 'px'; thumb.style.transform = `translateX(${on.offsetLeft}px)`; }
  }
  function moveInk(tabs) {
    const on = tabs.querySelector('[aria-selected="true"]'); const ink = tabs.querySelector('.ink');
    if (on && ink) { ink.style.width = on.offsetWidth + 'px'; ink.style.transform = `translateX(${on.offsetLeft}px)`; }
  }
  function countUp(el) {
    const target = parseFloat(el.dataset.count); const prefix = el.dataset.prefix || ''; const suffix = el.dataset.suffix || '';
    const fmt = n => prefix + Math.round(n).toLocaleString('en-IN') + suffix;
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) { el.textContent = fmt(target); return; }
    const t0 = performance.now(), dur = 900;
    const step = now => { const p = Math.min(1, (now - t0) / dur); el.textContent = fmt(target * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(step); };
    requestAnimationFrame(step);
  }
  function enhance(root = document) {
    root.querySelectorAll('.segmented').forEach(seg => {
      if (!seg.querySelector('.thumb')) seg.insertAdjacentHTML('afterbegin', '<span class="thumb"></span>');
      requestAnimationFrame(() => moveThumb(seg));
    });
    root.querySelectorAll('.tabs').forEach(tabs => {
      if (!tabs.querySelector('.ink')) tabs.insertAdjacentHTML('beforeend', '<span class="ink"></span>');
      requestAnimationFrame(() => moveInk(tabs));
    });
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { countUp(e.target); io.unobserve(e.target); } }));
      root.querySelectorAll('[data-count]').forEach(el => io.observe(el));
    }
    root.querySelectorAll('[data-icon]').forEach(el => {
      const style = el.getAttribute('style');
      el.outerHTML = icon(el.dataset.icon, el.className).replace('<svg ', `<svg ${style ? `style="${style.replace(/"/g, '&quot;')}" ` : ''}`);
    });
    root.querySelectorAll('.bar').forEach((b, i) => b.style.setProperty('--i', i));
    /* Affix-aware padding: input text always starts 8px after ₹ / +91 / any prefix */
    requestAnimationFrame(() => root.querySelectorAll('.input-group > .affix + .input').forEach(inp => {
      const w = inp.previousElementSibling.offsetWidth;
      if (w) inp.style.paddingLeft = `calc(var(--s-3) + ${w}px + var(--s-2))`;
    }));
    root.querySelectorAll('.avatar-stack .avatar').forEach((a, i) => a.style.setProperty('--i', i));
    /* Deterministic avatar hue from initials, from a curated set that sits well beside the brand */
    const HUES = [158, 172, 198, 214, 236, 262, 286, 322, 14, 32];
    root.querySelectorAll('.avatar').forEach(a => {
      const t = a.textContent.trim();
      if (!t || t.startsWith('+') || a.dataset.hue === 'none') return;
      let h = 0; for (const ch of t) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
      a.style.setProperty('--h', HUES[h % HUES.length]);
    });
  }

  /* Table selection + bulk bar */
  function syncSelection(table) {
    const boxes = [...table.querySelectorAll('tbody .sel input')];
    const all = table.querySelector('thead .sel input');
    const n = boxes.filter(b => b.checked).length;
    boxes.forEach(b => b.closest('tr').setAttribute('aria-selected', b.checked));
    if (all) { all.checked = n === boxes.length && n > 0; all.indeterminate = n > 0 && n < boxes.length; }
    const bar = document.getElementById(table.dataset.bulk);
    if (bar) { bar.dataset.open = n > 0; const c = bar.querySelector('[data-selected-count]'); if (c) c.textContent = n; }
  }

  /* Kanban drag (pointer events → works with mouse, pen and touch) */
  function initBoard(board) {
    let drag = null;
    board.addEventListener('pointerdown', e => {
      const card = e.target.closest('.deal');
      if (!card || e.button !== 0 || e.target.closest('button, a')) return;
      const r = card.getBoundingClientRect();
      drag = { card, dx: e.clientX - r.left, dy: e.clientY - r.top, x0: e.clientX, y0: e.clientY, ghost: null, w: r.width };
    });
    window.addEventListener('pointermove', e => {
      if (!drag) return;
      if (!drag.ghost) {
        if (Math.hypot(e.clientX - drag.x0, e.clientY - drag.y0) < 5) return;
        drag.ghost = drag.card.cloneNode(true);
        drag.ghost.classList.add('ghost'); drag.ghost.style.width = drag.w + 'px';
        document.body.appendChild(drag.ghost);
        drag.card.dataset.dragging = 'true';
        document.body.style.userSelect = 'none';
      }
      drag.ghost.style.left = e.clientX - drag.dx + 'px';
      drag.ghost.style.top = e.clientY - drag.dy + 'px';
      const lane = document.elementFromPoint(e.clientX, e.clientY)?.closest('.lane');
      board.querySelectorAll('.lane').forEach(l => l.dataset.drop = l === lane);
      if (lane) {
        const after = [...lane.querySelectorAll('.deal:not([data-dragging])')].find(c => { const b = c.getBoundingClientRect(); return e.clientY < b.top + b.height / 2; });
        after ? lane.insertBefore(drag.card, after) : lane.appendChild(drag.card);
      }
    });
    window.addEventListener('pointerup', () => {
      if (!drag) return;
      const { card, ghost } = drag; drag = null;
      document.body.style.userSelect = '';
      board.querySelectorAll('.lane').forEach(l => l.dataset.drop = 'false');
      if (!ghost) return;
      ghost.remove(); card.removeAttribute('data-dragging');
      card.classList.remove('landed'); void card.offsetWidth; card.classList.add('landed');
      board.querySelectorAll('.lane').forEach(l => { const c = l.querySelector('.lane-head .count'); if (c) c.textContent = l.querySelectorAll('.deal').length; });
      const stage = card.closest('.lane').querySelector('.lane-head .t-small').textContent;
      toast(`<b>${card.querySelector('.deal-title').textContent}</b> moved to ${stage}`, { undo: () => toast('Move undone', { iconName: 'refresh' }) });
    });
  }

  function toggleDensity() {
    const d = document.documentElement;
    d.dataset.density = d.dataset.density === 'compact' ? 'comfortable' : 'compact';
    store.set('density', d.dataset.density);
    toast(`Density: ${d.dataset.density}`, { iconName: 'rows', duration: 2000 });
  }

  /* ================= Global event delegation ================= */
  document.addEventListener('click', e => {
    const t = e.target;
    const menuTrigger = t.closest('[data-menu]');
    if (menuTrigger) { e.stopPropagation(); return toggleMenu(menuTrigger); }
    if (openMenu && !t.closest('.menu')) closeMenu();

    const roleBtn = t.closest('[data-role]');
    if (roleBtn) { store.set('role', roleBtn.dataset.role); location.reload(); return; }

    const act = t.closest('[data-action]');
    if (act) {
      const a = act.dataset.action;
      const shell = document.querySelector('.shell');
      if (a === 'collapse') { shell.dataset.collapsed = shell.dataset.collapsed !== 'true'; store.set('collapsed', shell.dataset.collapsed); hideTip(); }
      if (a === 'nav-open') shell.dataset.navOpen = 'true';
      if (a === 'cmdk') openCmdk();
      if (a === 'theme') { const d = document.documentElement; d.dataset.theme = d.dataset.theme === 'dark' ? 'light' : 'dark'; store.set('theme', d.dataset.theme); if (act.querySelector('svg')) act.innerHTML = icon(d.dataset.theme === 'dark' ? 'sun' : 'moon'); }
      if (a === 'density') { closeMenu(); toggleDensity(); }
      if (a === 'close') closeOverlay(act.closest('.overlay'));
      if (a === 'confirm-delete') {
        closeMenu();
        /* Copy is overridable per trigger: data-confirm-title / -body / -action / -toast */
        const c = act.dataset;
        const verb = c.confirmAction || 'Archive';
        openOverlay(`<div class="dialog" role="alertdialog"><div class="dialog-head"><span class="kpi-icon" style="background:var(--danger-subtle);color:var(--danger-fg)">${icon('trash')}</span><div class="stack-xs grow"><div class="t-subhead">${c.confirmTitle || 'Archive this record?'}</div><p class="muted t-small">${c.confirmBody || 'It will be hidden from lists and reports. You can restore it from Operations &amp; audit within 30 days.'}</p></div></div><div class="dialog-foot"><button class="btn" data-action="close">Cancel</button><button class="btn btn-danger" data-action="close" data-toast="${c.confirmToast || 'Record archived'}" data-undo>${verb}</button></div></div>`);
      }
    }

    const opener = t.closest('[data-open]');
    if (opener) openTemplate(opener.dataset.open, opener.dataset.kind);

    const seg = t.closest('.segmented button');
    if (seg) { seg.parentElement.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', b === seg)); moveThumb(seg.parentElement); }

    const chip = t.closest('.chip[aria-pressed]');
    if (chip) chip.setAttribute('aria-pressed', chip.getAttribute('aria-pressed') !== 'true');

    const tab = t.closest('.tabs [role="tab"]');
    if (tab) {
      const tabs = tab.closest('.tabs');
      tabs.querySelectorAll('[role="tab"]').forEach(x => { x.setAttribute('aria-selected', x === tab); const p = document.getElementById(x.getAttribute('aria-controls')); if (p) p.hidden = x !== tab; });
      moveInk(tabs);
    }

    const att = t.closest('.att button');
    if (att) {
      const grp = att.parentElement, prev = grp.querySelector('[aria-pressed="true"]');
      grp.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', b === att));
      const name = att.closest('[data-student]')?.dataset.student || 'Student';
      const label = { P: 'present', A: 'absent', L: 'late' }[att.dataset.v];
      toast(`${name} marked <b>${label}</b>`, { duration: 3000, undo: () => grp.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', b === prev)) });
      document.dispatchEvent(new CustomEvent('attendance-change'));
    }

    const sort = t.closest('.th-sort');
    if (sort) {
      const next = sort.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
      sort.closest('tr').querySelectorAll('.th-sort').forEach(s => s.removeAttribute('aria-sort'));
      sort.setAttribute('aria-sort', next);
      const th = sort.closest('th'), idx = [...th.parentElement.children].indexOf(th);
      const tbody = sort.closest('table').tBodies[0];
      const val = r => { const c = r.children[idx]; const v = c.dataset.v ?? c.textContent.trim(); return isNaN(+v) ? v.toLowerCase() : +v; };
      [...tbody.rows].sort((a, b) => (val(a) > val(b) ? 1 : -1) * (next === 'ascending' ? 1 : -1)).forEach(r => tbody.appendChild(r));
    }

    const copy = t.closest('[data-copy]');
    if (copy) {
      navigator.clipboard?.writeText(copy.dataset.copy);
      copy.dataset.copied = 'true';
      if (!copy.dataset.orig) {
        copy.dataset.orig = copy.innerHTML;
        copy.style.minWidth = copy.offsetWidth + 'px'; /* no layout shift while the label swaps */
        copy.innerHTML = icon('check', 'icon-sm') + ' Copied';
        setTimeout(() => { copy.innerHTML = copy.dataset.orig; delete copy.dataset.orig; copy.dataset.copied = 'false'; }, 1400);
      }
    }

    const toastBtn = t.closest('[data-toast]');
    if (toastBtn && !toastBtn.matches('[data-async]')) toast(toastBtn.dataset.toast, toastBtn.hasAttribute('data-undo') ? { undo: () => toast('Restored', { iconName: 'refresh' }) } : {});

    /* Async button: loading → success → reset */
    const asyncBtn = t.closest('[data-async]');
    if (asyncBtn) {
      e.preventDefault();
      asyncBtn.dataset.loading = 'true';
      setTimeout(() => {
        asyncBtn.dataset.loading = 'false';
        const html = asyncBtn.innerHTML;
        asyncBtn.dataset.state = 'success';
        asyncBtn.innerHTML = icon('check') + (asyncBtn.dataset.done || 'Saved');
        if (asyncBtn.dataset.toast) toast(asyncBtn.dataset.toast);
        setTimeout(() => { asyncBtn.innerHTML = html; asyncBtn.dataset.state = ''; if (asyncBtn.closest('.overlay') && asyncBtn.hasAttribute('data-close')) closeOverlay(asyncBtn.closest('.overlay')); }, 1100);
      }, 900);
    }

    if (openMenu && t.closest('.menu .menu-item')) closeMenu();

    if (t.closest('.shell[data-nav-open="true"]') && !t.closest('.sidebar') && !t.closest('[data-action="nav-open"]')) document.querySelector('.shell').dataset.navOpen = 'false';
  });

  document.addEventListener('change', e => {
    const box = e.target.closest('.sel input');
    if (!box) return;
    const table = box.closest('table');
    if (box.closest('thead')) table.querySelectorAll('tbody .sel input').forEach(b => (b.checked = box.checked));
    syncSelection(table);
  });

  document.addEventListener('pointerover', e => {
    const t = e.target.closest('[data-tip]');
    if (!t) return;
    clearTimeout(tipTimer);
    tipTimer = setTimeout(() => showTip(t), tipWarm ? 0 : 400);
    t.addEventListener('pointerleave', () => { hideTip(); setTimeout(() => (tipWarm = !!tipEl), 300); }, { once: true });
  });

  /* Keyboard: ⌘K palette, [ collapse, D density, Esc close, arrow-keys in menus */
  document.addEventListener('keydown', e => {
    const typing = e.target.matches('input, textarea, select, [contenteditable]');
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openCmdk(); return; }
    if (e.key === 'Escape') { if (openMenu) { const tr = openMenu.trigger; closeMenu(); tr.focus(); } else closeOverlay(); return; }
    if (openMenu && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
      e.preventDefault();
      const items = [...openMenu.el.querySelectorAll('.menu-item')]; const i = items.indexOf(document.activeElement);
      items[(i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus();
      return;
    }
    if (typing || e.metaKey || e.ctrlKey || e.altKey) return;
    if (e.key === '[') document.querySelector('[data-action="collapse"]')?.click();
    if (e.key === 'd') toggleDensity();
    if (e.key === '/') { e.preventDefault(); openCmdk(); }
  });

  window.addEventListener('resize', () => { document.querySelectorAll('.segmented').forEach(moveThumb); document.querySelectorAll('.tabs').forEach(moveInk); closeMenu(); });

  /* ================= Boot ================= */
  const qs = new URLSearchParams(location.search);
  document.documentElement.dataset.theme = qs.get('theme') || store.get('theme', matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.dataset.density = store.get('density', 'comfortable');
  const boot = () => {
    document.body.insertAdjacentHTML('beforeend', '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs><linearGradient id="spark-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#13906f" stop-opacity=".18"/><stop offset="1" stop-color="#13906f" stop-opacity="0"/></linearGradient></defs></svg>');
    buildShell();
    enhance();
    document.querySelectorAll('.board').forEach(initBoard);
    document.querySelectorAll('table[data-bulk]').forEach(syncSelection);
    document.dispatchEvent(new Event('app-ready'));
    /* Deep links for review/QA: ?click=<selector>[,<selector>…] replays clicks after boot */
    const clicks = new URLSearchParams(location.search).get('click');
    if (clicks) clicks.split(',').reduce((p, sel) => p.then(() => new Promise(r => setTimeout(() => { document.querySelector(sel)?.click(); r(); }, 250))), Promise.resolve());
  };
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', boot) : boot();
})();
