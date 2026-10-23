# Business workflow coverage and cutover boundaries

The operational app is organized around one-on-one teaching assignments and dated lessons, not spreadsheet tabs. AA and BB retain separate teacher pools and explicit staff access.

| Current workbook | Application workflow | Completion boundary |
| --- | --- | --- |
| MASTER attendance/hours | Dated lessons → teacher attendance → admin import → student consumption; separate approved teacher accrual | No projected timetable hours charged as attendance |
| VIEW TEACHER | Teacher registry, eligibility, assignments, agreed-rate snapshots, earned/paid/net statement | Already-paid settlements are bookkeeping, never transfers |
| EXAM RESULT/fees/dues | Assessments, balance and collection queue, payment requests, receipt allocation/correction | No tax claims or automatic debt-driven class stopping |
| TT2025 | Bilateral availability and consent, recurring versions, one-off demo/extra, exceptions and rescheduling lineage | No messaging without human action; no silent cancellation |
| Student registration | Stable registry, registration-month priority, profile/status/class-link updates, follow-up history | Historical identity conflicts block migration |
| Duplicate verification | Gallabox reply recorded, genuine/error decision, audited financial compensation | No automatic teacher messages or silent deletion |
| Invoice generator | Nonfinancial payment request and ordinary received-payment acknowledgement | Not a GST/tax invoice or collection integration |
| Task manager | Same-unit assignment, due dates, status, checklist and follow-up history | No invented task-point/payroll incentive policy |

## Release sequence

1. Finish operational UI and bookkeeping workflows with synthetic end-to-end tests.
2. Verify SQLite-default and MariaDB-default configurations, native concurrent financial writes, authorization and import rollback/replay.
3. Publish reviewed code only to main, preserving history.
4. Use a separately hosted test deployment for browser/mobile acceptance.
5. Approve normalized source mapping, opening balances, cutoff and exact preview digest before any real import. Original Sheets remain read-only. Historical consumption is archived and never charged again on top of opening balances.
6. Reconcile source counts/amounts and obtain operational acceptance before production cutover.

Code completeness is not a claim that hosting, real-data migration or business cutover has happened. No automatic payout, refund, third-party message, credential grant or source Sheet write is included.
