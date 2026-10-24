# Weekly timetable acceptance

Verified on 24 October 2026 using isolated synthetic records only.

- Full SQLite-default suite: 290 tests / 2074 assertions passed.
- Full MariaDB 10.11-default suite: 290 tests / 2074 assertions passed; isolated service tests retain their own SQLite connections.
- 26 new HTTP cases / 185 assertions cover week and midnight overlap, both DST transitions, unit-local default date, named filters, escaped output, access isolation, supported date bounds, historical statuses, navigation filters, the 250-row incomplete-view warning and unchanged ERP/outbox rows.
- Existing 18 Node interaction/Worker tests passed. No financial or concurrency implementation changed.
- Actual cloud-browser interaction on the synthetic application verified combined filters, Next/Previous preserving filters, empty week notice, keyboard Enter opening lesson details with visible focus, and a planning-action anchor opening/highlighting the intended form without submitting it.
- At 1180px the board uses three readable columns. An ordinary native cloud browser window was resized to an actual 490px CSS viewport: one 428px calendar column, document scrollWidth 490px, no page-wide horizontal overflow, stacked filters and readable cards. This is narrow-desktop evidence, not real-mobile-device acceptance; denied device emulation was not retried.

A cramped seven-column presentation at 1180px was found during review and fixed by raising the responsive breakpoint. The updated layout was rechecked. No forms, financial actions, production migration, external messages or Sheet writes were submitted.

Remaining: intended-host/browser/device acceptance, broader workflow gaps in ERP_GAP_TRACKER.md, and separately authorized real-data reconciliation/cutover.
