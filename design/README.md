# ClapsLearn ERP — Design System v2

A from-scratch redesign of the ERP as a design system plus a clickable prototype. It's plain HTML and CSS with a small runtime and no build step: open `index.html` in a browser.

Nothing in `public_html/` is touched. This folder is the spec the Blade views get ported to.

```
design/
├── index.html          hub: links to every screen
├── foundations.html    colour, type, spacing, layout, radius, elevation, motion
├── components.html     every component in every state
├── system/
│   ├── tokens.css      the only place raw values live (light/dark, comfortable/compact)
│   ├── components.css  primitives, composites and domain components
│   └── app.js          shell, icons, nav/IA, and all interaction behaviour
└── screens/            one HTML file per screen, containing only <main> content
```

## Information architecture

The old sidebar had 12 flat unit pages plus a separate workspace section. The new one groups them by the job being done:

| Group | Page | Replaces (old label) | Roles |
|---|---|---|---|
| — | Today | Overview + "Your workspace" | all |
| — | My tasks | My assigned tasks, Follow-ups | all |
| Growth | Pipeline | Admissions & CRM | manager, sales, read-only |
| Growth | Sales report | Sales activity | manager, sales, read-only |
| People | Students | Students & teachers (students half) | manager, academics, read-only |
| People | Teachers | Students & teachers (teachers half) + Teacher recruitment | manager, academics, sales, read-only |
| People | Teacher matching | Teacher matching | manager, academics, sales, read-only |
| Teaching | Timetable | Timetable | manager, academics, read-only |
| Teaching | Attendance | Attendance + work queues | manager, academics, read-only |
| Teaching | Teacher portal | My lessons / Teacher workspace | teachers |
| Money | Ledger & receipts | Money & receipts | manager, finance, read-only |
| Money | Payment requests | (inside Money) | manager, finance, read-only |
| Money | Parent statements | Tuition → Monthly parent statement | manager, finance, read-only |
| Admin | Team & access | Team access + Teams & accounts | manager |
| Admin | Operations & audit | Operations & audit | manager |
| Admin | Migration & exports | Migration & exports, From Sheets | manager |
| Admin | Account security | Account security | all |

Academics (assessment results, subject eligibility) lives in the Student profile and Teacher pages rather than having its own page.

Role visibility mirrors `TeamPermissions.php`. In the prototype, use the account menu → **Preview as role** to see each role's navigation.

## Principles

1. **Spacing is proximity.** Space inside a group is always smaller than space between groups: 4–12px within a component, 16–24px between components, 32px between page regions. Components use role tokens (`--gap-stack`, `--pad-card`), never raw values, so density changes globally.
2. **One edge.** Card headers, bodies, table first and last cells, and list rows all inset by `--pad-card`, so every left edge in a column lines up. Ghost buttons at a card edge get a negative margin so their label lines up rather than their hit area.
3. **Numbers line up.** Money and counts are right-aligned with tabular figures and Indian grouping (`₹1,23,456`). Sign and colour together show direction.
4. **Status means one thing.** Each status string maps to one tone across every module (see the table in `components.html#badge`).
5. **Motion explains change.** Entering elements decelerate and exiting ones accelerate. Only toggles and confirmations spring. Everything respects `prefers-reduced-motion`.
6. **Optimistic by default.** Reversible actions apply immediately and offer undo. Destructive ones confirm in a dialog.
7. **Accessible.** Contrast is checked against WCAG (status text ≥ 5.4:1, muted text ≥ 4.6:1). Every control has a visible focus ring, and touch targets on mobile surfaces are ≥ 44px.

## Visual craft

- **Surfaces:** hairline alpha borders (`--border-card`), a two-layer soft shadow and a 1px top highlight. The canvas has a faint brand-tinted glow.
- **Brand moment:** one `.hero-panel` per page at most (deep green mesh, masked grid, glass rows). Everything else stays quiet.
- **Type:** Inter with optical sizing. Display sizes use −0.028em tracking. Stat values use proportional figures and table columns use tabular figures.
- **Avatars:** a deterministic hue from the initials (curated set of 10), drawn as a two-stop gradient. It is computed in `app.js`.
- **Charts** (`charts.js`): `data-chart="area|donut|heatmap"` with `data-series` JSON. The categorical palette is `--series-1..4` (green, blue, orange, violet); the dataviz validator passes it in light and dark (adjacent CVD ΔE ≥ 18, normal ΔE ≥ 19, ≥ 3:1 on surface). The sequential ramp is `--seq-0..5`. Each chart has a crosshair or per-mark tooltip, arrow-key focus and a visually hidden data table.

## Prototype runtime

Screens are declarative. `app.js` wires up behaviour from these attributes:

| Attribute | Behaviour |
|---|---|
| `body[data-page][data-crumbs]` | shell, active nav item, breadcrumbs (`data-shell="none"` opts out) |
| `data-icon="name"` | replaced with the inline SVG icon |
| `data-menu="id"` | opens a built-in menu or a `<template id>` as a popover |
| `data-open="tpl-id" data-kind="drawer"` | opens a template as a dialog or drawer |
| `data-async data-done data-toast data-close` | loading → success → toast → (close overlay) |
| `data-toast data-undo` | toast, with an undo action |
| `data-count data-prefix` | KPI count-up when scrolled into view |
| `data-tip data-kbd` | tooltip with a shortcut hint |
| `data-copy` | copy to clipboard with inline confirmation |
| `data-action="confirm-delete"` + `data-confirm-title/-body/-action/-toast` | destructive confirm dialog |
| `table[data-bulk=id]` + `.sel` checkboxes | row selection, indeterminate header, bulk bar |
| `.th-sort` | client-side column sort |
| `.board` / `.lane` / `.deal` | pointer-based kanban drag and drop |
| `.att` | P/A/L attendance marker (emits an `attendance-change` event) |

Inline page scripts run before the deferred `app.js`. Wrap any code that uses `icon()`, `toast()` or `closeOverlay()` in `document.addEventListener('app-ready', …)`. For review, `?theme=dark` forces a theme and `?click=<selector>[,<selector>]` replays clicks after boot, e.g. `pipeline.html?click=.deal-title` opens the lead drawer.

Shortcuts: `⌘K` or `/` opens search, `[` collapses the sidebar, `D` switches density, `Esc` closes the top layer.

## Porting into Laravel

1. Copy `tokens.css` and `components.css` into `public_html/resources/css/`, replacing `erp.css`. Every class has the same name in the prototype and in production.
2. Turn the shell in `app.js` (`buildShell`, `NAV`) into `erp/layout.blade.php`, generating `NAV` from the existing route and permission checks.
3. Port the interaction section of `app.js` (everything after `buildShell`) into `erp-shell.js`. It has no dependencies.
4. Port one screen at a time: each `screens/*.html` `<main>` maps to an existing `erp/*.blade.php` view, as listed in the IA table above.

## Claude Design

To push this system into a Claude Design (claude.ai/design) project, start `/design-sync` yourself in Claude Code and point it at this folder. Syncing only runs when you start it. Note that standalone Claude Design closes on 14 December 2026; after that it lives inside Claude.
