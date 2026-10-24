# ERP gap closure tracker

Code-only work, using synthetic local fixtures. Main publication is authorized; production deployment, real-data migration, source Sheet writes, external messages and money movement are not part of this work. Each stage needs tests and review before publication. Completion of code does not establish operational cutover.

| Stage | Scope | Acceptance | State |
| --- | --- | --- | --- |
| 1 | Visual weekly timetable | Named unit-scoped lessons; timezone and DST-correct overlap; bounded results with explicit overflow; preserved filters/week navigation; responsive and keyboard-usable; no financial writes | Verified: 290 tests / 2074 assertions on SQLite and MariaDB-default; 18 Node checks; cloud-browser desktop and 490px narrow-window acceptance |
| 2 | Partial-day exceptions and planning usability | Explicit local date/time boundaries; overlap/collision checks and existing-lesson protection; full-day compatibility; DST validation; AA/BB isolation | Implemented and verified; 18 native concurrency scenarios and synthetic browser interval/replay checks passed |
| 3 | Action-oriented daily queues and rupee UX | Named actionable records; pending import distinct from teacher approval; decimal INR input parsed without floating point; money storage remains integer paise; idempotent operations preserved | Implemented and verified; synthetic browser import/approval separation and exact INR checks passed |
| 4 | Migration opening balances and historical teacher dues | Explicit signed opening semantics and UTC cutoff; preview/digest/reconciliation; synthetic repeat/rollback/cutoff tests; history never charged again; no implicit payment | Implemented and verified with synthetic fixtures; real source mapping and approved opening totals remain gated |
| 5 | Continuous integration and supported framework | Reproducible synthetic CI; dependency/framework compatibility audit; supported upgrade with full regression and explicit remaining security findings | Manual-only CI prepared; isolated Laravel 12 upgrade passes baseline regression and PHP audit; integrated upgrade and Node security remediation pending |

## Non-code release gates

- Confirm source mapping, historical opening totals and an exact cutoff with business owners. No invented opening balances or historical teacher amounts.
- Approve each real migration preview digest, database target, backup and reconciliation. Source Sheets remain read-only.
- Validate deployment engine/version and hosting, real-device accessibility/mobile acceptance and operational signoff.
- Production hosting, credential grants and cutover need their own authorization. Published code is not deployed code.

## Timetable details

The weekly board is a read-only projection, not an availability editor or drag-and-drop calendar. It includes all lesson statuses, including rescheduled originals, and uses actual dated lessons rather than projecting recurring agreements. Overnight lessons appear on both days. More than 250 matching lessons produces a prominent incomplete-view warning; filtering is required to inspect the remainder. Action links open existing audited workflows, where the operator explicitly chooses the record and submits the change. No implicit attendance, student charge or teacher accrual occurs on navigation.

## Follow-on product and operations backlog

These are separate from the five audited-gap stages above. Implement only grounded business rules; do not silently expand staff access.

- Unified student profile: read-only unit-admin overview linking named assignments, timetable, attendance, balance/receipt history and follow-ups. Reuse current access boundaries and separate student charges from teacher earnings.
- Guided staff workflow: context-preserving navigation and explicit next actions, without inventing approval or messaging automation. Known current issue: server validation collapses the form details and clears entity selections; preserve form context safely in a focused follow-up.
- Finer permissions: requires an approved role/action/data-access matrix. Existing explicit unit-admin and own-teacher/task access stays in force until reviewed.
- Backup/restore: prepare documented, synthetic restore drills and integrity checks. Real backup destinations, retention, credentials and production restore need explicit operational choices and authorization.
- Monitoring: define health/error/audit indicators and synthetic verification. Hosting-specific alert channels, access, budgets and operational ownership remain unconfigured.
- Real-data reconciliation: approved source mapping, opening totals, cutoff, preview digest and target acceptance remain release gates. Code tests and rehearsal do not certify the original Sheets.
