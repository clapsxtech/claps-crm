# Sheets → application architecture review

**Implementation follow-up:** the [temporary Sheets bridge](../sheets-transition.md) now adds three isolated snapshot tables, a read workspace, validation/review, and a Sheets-authority mode. The 45-table audit below remains the pre-bridge baseline; current schema verification reports 48 ERP tables and three foreign keys in the new snapshot tables. Existing canonical constraint gaps remain unresolved. The bridge is not activated live.

**Review date: 26 October 2026. Verdict: not ready for complete spreadsheet replacement or production cutover.** The application has a substantial, tested operational foundation. It still needs enforceable data relationships, completed invoice and recruitment integration, source reconciliation, and production-environment acceptance. Passing local tests does not close those gaps.

This review concerns the current working tree, including pre-existing uncommitted changes, based on commit `9a1d720`. It does not assert that the reviewed code is deployed. Original Sheets were read only. No production database, deployment, payment, or outbound message was changed.

**Confirmed source-ownership decision:** the user selected the application as the authoritative record for attendance and payments after cutover. Sheets will remain read-only history/exports. See the [cutover authority decision](source-authority.md). The cutoff instant, reconciliation and remaining release gates still need completion.

## Open the diagrams

**[Open the diagram viewer](diagrams.html)** for all five diagrams with fit-width and zoom controls. It works locally without external services.

- [Spreadsheet business ER diagram](sheets-domain-erd.svg) — reconstructed entities and intended cardinalities; not a literal table-per-tab design.
- [Verified workbook dependency map](sheet-dependencies.svg) — arrows point from source workbook to consuming workbook; orange nodes are additional upstream dependencies.
- [Application ER: people, sales and recruitment](application-people-sales.svg).
- [Application ER: scheduling, attendance and assessments](application-delivery.svg).
- [Application ER: finance, tasks and migration](application-finance-operations.svg).

The three application diagrams use actual table/column names. **Their dotted connectors are logical relationships inferred from migrations and services, not enforced SQL foreign keys.** PK labels are physical primary keys; nullable and unique references determine the shown cardinality. Repeated entities connect the domain diagrams. Actor/unit links are omitted where they would obscure the core flow. `erp_teacher_units.teacher_id` is unique: the current schema assigns a teacher to at most one unit. Candidate→teacher is drawn as potentially many because `teacher_id` lacks a unique constraint, even though approval code tries to prevent that condition. Invoice and template tables appear because they exist, not because their complete workflows exist.

Editable `.mmd` sources are beside each SVG. [Application schema](application-schema.json) includes all 45 ERP tables, columns and indexes. [Logical relationship catalogue](logical-relationships.json) is machine-readable.

## Evidence and limits

The 14 supplied links resolve to **12 unique workbooks**, containing **189 tabs**, of which **183 are visible** and six hidden. Metadata was inspected for all tabs. Visible-tab reads covered rows 1–5, capped at 180 columns; seven invoice layouts were additionally read through their bottom, at most 45 rows. Therefore very wide incentive/advertising histories are only partly sampled. Hidden templates received metadata inspection only.

The samples exposed **3,148 formula cells**, **135 literal `IMPORTRANGE` references**, and **39 distinct directed workbook pairs**. They contained **230 cells displaying error strings**. Those are sample counts, not workbook-wide error rates; `#N/A` can also mean an empty filtered result. A workbook-level dependency cycle does not prove a circular cell formula.

All five newly discovered dependencies were metadata-verified. Accounts and old teacher registration received bounded header reads; the other three were not exhaustively inspected. Dependency discovery stops at these direct references. Apps Script, form automation, Gallabox configuration, manual copy/paste, and indirect/dynamic formula references are outside this evidence. Grid row capacities are not record counts. No full-population uniqueness, orphan, monetary-total, or historical migration certification was performed.

- [Workbook/tab inventory](tab-inventory.csv): every tab, exact gid, grid capacity and sampling coverage.
- [Formula lineage](formula-lineage.csv): source range, consuming tab/cell, and deep link for every observed literal cross-workbook import.
- [Source evidence](source-evidence.json): metadata, lineage and sampled error coordinates, without copied student, teacher, contact, or bank records.
- [Source-to-application mapping and join contracts](mapping.md).

## What the workbooks actually represent

**Student registration and teacher registration are the identity hubs.** Student codes use `CCA…`; teacher codes use `CT…`. Names, family phone numbers, row numbers and concatenated names are not safe primary keys. VIEW TEACHER imports HR teacher registration and its available-teacher view; treat it as a projection. EXAM RESULTS `MASTER` and Master 2025 represent teacher–student assignments and rate/hour summaries, not independent people.

**Scheduling and delivery are different facts.** TT 2025 contains weekly day blocks, duration, teacher, subject and exceptional/rescheduled classes. DUPLICATE `DATA` contains source attendance ID, teacher code, student code, date and hours. Master 2025 and EXAM RESULTS both consume that attendance feed. Timetables do not prove attendance; monthly summaries must not generate charges a second time.

**The invoice workbook is a presentation layer over an upstream ledger.** `AFTER INVOICE!B12/G12/B13/G19` looks up an invoice number in its imported LEDGER. `BEFORE INVOICE` looks up student details and requests an amount. This does not by itself establish a tax-invoice lifecycle. Keep payment requests, receipts, commercial invoices, lesson charges and salary payments distinct.

**Tasks and dashboards are mostly projections.** AA/BB task rows, task templates, checklists and performance points are business records; monthly totals, due queues, first-class lists, birthday lists and message templates are views. A row present in a current task sheet and backup is not two tasks.

### Additional upstream workbooks

| Dependency discovered in formulas | Evidence | Consequence |
|---|---|---|
| [Accounts & Monthly data 2.0](https://docs.google.com/spreadsheets/d/1_iu_uws25LtXJlkHZDP678rF0MhvgkgLIlSuyRCLIXU/edit) | Invoice GENERATOR `LEDGER!A1/B1/F1/H1/J1`; HR `BR3/BT3`; EXAM RESULTS `STUDENT!H2` | Include in payment, refund and teacher-balance reconciliation. Its Ledger has credit/debit, account, narration and conversion-rate fields. Do not infer currency semantics from those labels alone. |
| [old Teacher Registration Form (Responses)](https://docs.google.com/spreadsheets/d/1yc9ul7sMTYnJLDTw-zmCfDzfna2_nEtZF2B6-cOYEa8/edit) | CLAPS TEACHER `TEACHER REGISTRATION!A1` | Historical recruitment identity provenance. |
| [TT new Vers 5](https://docs.google.com/spreadsheets/d/1aUzOJOtozI6HBfwAjvZNR6hrUvR2m4WsOGZUvxtIhLo/edit) | HR `TIME TABLE!AL1`; task timetable imports | Determine effective version/date before combining schedules. |
| [Demo Time & Status 2.0](https://docs.google.com/spreadsheets/d/1Gs69vRNfAgLNZaZEFtaifkbV9eAotq_i2qVEVoml1i4/edit) | VIEW TEACHER helper imports; exact cells in lineage CSV | Demo metrics have an upstream dependency beyond the supplied sales links. |
| [Teacher Availability Confirmation Form (Responses) 1](https://docs.google.com/spreadsheets/d/1WrSdVyMXhHN9PghLZgzNFTAmoonsBl4IpWdvpyME34A/edit) | HR helper imports; exact cells in lineage CSV | Availability confirmation provenance must be reconciled before automatic allocation. |

## Release findings and required corrections

### P1 — Database integrity is not enforced across ERP entities

Applying all ten D1 migration files to disposable SQLite produces **45 ERP tables and zero declared ERP foreign keys**. All 45 have INSERT/UPDATE/DELETE revision triggers, which is good for adapter consistency but does not enforce business relationships. Synthetic direct inserts accepted an orphan profile, a profile attached across teams, negative attendance referencing a nonexistent lesson, and duplicate task source identity. Application service validation prevents many ordinary bad writes, but importers, maintenance paths and future code can bypass it.

Evidence: `application-schema.json`, core/canonical migrations, and `scripts/review-erp-schema.py`. An empty `PRAGMA foreign_key_check` result cannot validate relationships that were never declared.

**Close:** profile existing data; quarantine orphans and scope mismatches; add foreign keys and appropriate checks in both Laravel and D1 schemas. Use composite references where unit agreement matters, for example `(student_id, unit_id)` → students `(id, unit_id)` with a matching parent unique key. Cover lesson→enrollment/parties, attendance→lesson, ledger→attendance/parties/reversal, profiles, allocations and task items. Use restrictive deletion for financial history. Rebuild SQLite tables where required without losing indexes or revision triggers. Test attempted orphan, cross-unit, negative, duplicate and invalid-state writes directly against both schemas. D1 supports FK enforcement; it is not a platform limitation ([Cloudflare foreign-key documentation](https://developers.cloudflare.com/d1/sql-api/foreign-keys/)).

### P1 — Invoice tables exist; invoice issuance and settlement are incomplete

`erp_invoices`, `erp_invoice_lines`, and nullable `erp_ledger.invoice_id` exist. Current application references are migrations and export projections; no invoice issue/void/line-total/settlement service was found. `MoneyOperations` implements payment requests, receipt allocation, corrections and salary-payment recording. Those are valuable but do not use the invoice tables. One ledger `invoice_id` alone cannot represent one receipt allocated across multiple invoices.

**Close:** agree which documents the business needs; implement issue/finalize/void rules, immutable line/rate/billing snapshots, number allocation, explicit receipt-to-invoice allocation, reversal/credit handling and exact minor-unit arithmetic. Do not post charges once at attendance and again at invoice issue. Prove split/partial/overpayment, replay, reversal and concurrent issuance behavior. Reconcile historical receipts against the newly identified Accounts workbook before claiming invoice replacement.

### P1 — Recruitment has two disconnected candidate models

`TuitionWorkflows::candidate/interview` uses unit-scoped `erp_candidates` and `erp_candidate_interviews`. `TeacherRecruitment` and `TeacherSheetImporter` use business-scoped `erp_teacher_candidates`, sources and activities, with canonical teacher linkage. There is no relationship joining the old interview record to the shared candidate/teacher. `erp_teacher_enquiries` is another intake table without a candidate reference.

**Close:** make the shared candidate the canonical person; retain source records and structured interviews as children. Map existing candidates/enquiries through reviewed identities, never phone alone. Preserve interview history, decision maker, decision time, ratings and source provenance; use an explicit transition and approval workflow. Archive or route the old mutation path only after migration. Test cross-team directory access separately from access to sensitive application/interview data. Decide whether one approved candidate per teacher is required and enforce it appropriately.

### P1 — CSV import could cross team boundaries; targeted code fix included

Before this review, `SheetCsvImporter` looked up a student or teacher globally by code, then inserted or overwrote a profile using the requested target unit. An existing person in another team could be linked to a new-team profile; an overwrite could change another team's identity fields. The same-code/different-name case was not rejected.

**Fixed locally:** preflight all existing student/teacher targets before chunk commits; recheck inside chunk transactions; reject ownership/profile-team/name conflicts with and without overwrite. Comparison allows only case/whitespace normalization. Six added regression scenarios pass on SQLite and the D1 compatibility adapter. The existing same-source replay tests still pass. This is not a deployment or retrospective cleanup of existing records. The importer remains resumable by chunk, not globally atomic, and still needs source-header validation and full reconciliation.

### P1 — Real-data migration and reconciliation are not complete

The enrichment importer explicitly excludes monthly attendance summaries, timetable summaries, duplicate rows and invoice templates from financial posting. That is appropriate, but it means enrichment is not complete migration. Full source-key uniqueness, retained historical IDs, opening student credit/debt and teacher payables have not been reconciled in this review. Existing `Reporting::reconciliation` correctly returns `source_reconciled=false`.

**Close:** implement the confirmed application authority for attendance and payments, settle ownership of remaining entities and choose a precise cutoff instant; stage immutable dated snapshots, row digests and stable source IDs; resolve CT/CCA collisions and unmatched subject assignments; migrate history without reposting it; reconcile counts, durations and signed opening balances per team/person/currency. Explicitly define refund and adjustment signs. Keep exceptions in a review queue. Re-run the same batch and prove zero extra effects. Verify every rejected row is accounted for and every source balance agrees to the approved rounding policy. Sheets become read-only history/exports after cutover; they must not overwrite accepted application records.

### P1 — Task import identity changes when task completion changes

`SheetCsvImporter::tasks` hashes date, label, type, owner, description and **completion date** into `source_external_id`. Editing a completion date changes identity and can create another task. Conversely, two legitimate identical-looking tasks can collapse. The migration creates a nonunique source index. Templates inferred from observed task history also omit the full template/version semantics in `New Task `; task rows have no template reference, and imported owners remain textual notes with `assigned_to=null`. The current importer only handles AA task history, not all BB/admin task layouts.

**Close:** store immutable source-system/workbook/gid/row identity separately from mutable content digest; add a unique source identity; explicitly match current and backup copies; map staff IDs; version point rules and template checklists; link tasks to the applicable version. Test a task moving open→complete→corrected without changing identity, two same-looking separate tasks, both departments and repeated/concurrent imports.

### P2 — Rates, assessments and matching dimensions need explicit source mapping

SALARY and PAYMENT STRUCTURE are matrices by syllabus, section, grade/category and special course. Existing enrollments/lessons correctly snapshot integer student/teacher rates. There is no canonical effective-dated rate-card table. Sheet rates can have recurring decimals (for example `208.3333333`); rounding an hourly rate before multiplying can differ from rounding a final charge. `MARKLIST` contains chapter and exam mode; `erp_marks` currently has assessment/date/score/maximum/notes rather than those explicit dimensions.

**Close:** define precision and rounding with finance, preserve source rate text and approved converted rate, model effective dates/rate provenance, and explicitly map chapter/exam mode or document an accepted combined field. Never infer subject from only a teacher–student pair; obtain timetable/assessment evidence and quarantine ambiguity. Add parity examples for fractional hours, historical rate changes and special fees.

### P2 — Scope, availability and privacy policy need final acceptance

The app is explicitly one organization; `ERP_BUSINESS_ID` does not make unscoped legacy tables safe for multiple organizations. Teacher membership is single-unit even though the HR directory is shared. Validate that this matches AA/BB operations; do not remove the unique constraint without changing scheduling and financial ownership. Source timetable helpers contain fixed country offsets; preserve IANA zones and dated occurrences instead. Invoice GENERATOR metadata uses Europe/London while the other supplied workbooks use Asia/Calcutta, so serial dates cannot share an unqualified timezone assumption.

Read-only/team-manager exports include student-profile DOB and family fields. Some legacy verification flows still rely on DOB-like credentials. Define field-level HR/sales/finance visibility and retention; finish account-based teacher access before treating a DOB as authentication. Recruitment importer allowlisting of bank/identity/family data is a useful existing control. Do not add those sensitive fields to the general teacher directory to achieve spreadsheet parity.

### P1 — Production capacity, restore and release gates remain unproven for this version

The D1 adapter uses a local working copy, whole-database revision conflict checks and atomic remote commits; all ERP revision triggers are present in the reviewed SQL. Local compatibility tests are not live D1 acceptance. The code limits a commit to 900 mutations and an expanded statement to 95,000 bytes. Those are **application guardrails**, not a claim about current Cloudflare maximum batch size ([current D1 limits](https://developers.cloudflare.com/d1/platform/limits/)).

The checked-in GitHub workflow is manual `workflow_dispatch`, so it is not an automatic pull-request release gate. Earlier performance reports cover small samples and different code versions. No fresh full-size import, concurrent workload, production-config container build, real D1/R2 restore, or end-to-end browser acceptance was run here.

**Close:** test the actual supported PHP/container and Node 22 build; run real D1 atomicity/retry/conflict tests in an isolated environment; test a representative dataset and expected concurrent staff/teachers; set latency/error objectives, RPO and RTO; restore D1 and R2 into isolation and verify balances/references; configure alert ownership and scheduler/outbox behavior; make applicable checks required for the release branch. Do not load-test or restore over production as part of this audit.

## Verified strengths

- Stable-code people model, explicit enrollment, eligibility, availability, agreement, lesson, attendance and marks entities already exist.
- Attendance posting uses explicit administrative gates; student charges and teacher accrual are separate. Financial events use integer paise, unique event keys/references, frozen lesson rates and compensating reversals.
- Membership and role checks, unit-scoped lookups/exports, idempotent scheduling/payment behaviors, migration cutoff protections, audited mutations and revision-guarded D1 commits have meaningful test coverage.
- Full reviewed D1 SQL includes revision triggers on every ERP table. A source-of-truth decision can build on the existing design rather than discarding it.

## Validation performed

| Check | Result and scope |
|---|---|
| PHP suite after importer fix | **456 tests, 3,934 assertions passed**, native PHP 8.5.12, isolated SQLite. |
| Same suite through D1 adapter | **456 tests, 3,934 assertions passed**, synthetic SQLite gateway, not remote D1. |
| Added importer regressions + existing importer tests | **8 tests, 45 assertions passed** on both adapters. Six new tests cover cross-team ownership, same-code identity mismatch, inconsistent profile scope and safe replay. |
| JavaScript + TypeScript | **52 tests passed**; `tsc --noEmit` passed, also repeated under the required Node 22 executable. |
| Dependency audits | Composer: no advisories or abandoned packages reported; npm: zero reported vulnerabilities at review time. Not a penetration test. |
| Recovery rehearsal | Existing synthetic SQLite recovery drill passed all 14 checks. Not a live D1/R2 disaster-recovery test. |
| D1 migration reconstruction | Ten SQL migrations applied in memory; 45 ERP tables; zero ERP FKs; no missing ERP revision triggers. |
| Constraint bypass probes | Four invalid-data scenarios accepted directly by the reconstructed schema; demonstrates the integrity gap. |

The native PHP suite used PHP 8.5; production-container behavior and the workflow's PHP 8.2/8.4 matrix were not established by that run. No blanket “production ready” conclusion is justified by these checks.

## Cutover acceptance sequence

1. **Ownership and policy:** attendance/payment authority is confirmed as the application; name owners for sales, HR, admin and finance, and settle remaining entity ownership, AA/BB teacher ownership, cutoff, rounding, and document semantics.
2. **Identity and constraints:** profile all source data; resolve duplicate/ambiguous CT/CCA codes; harden references and scopes; close the task identity issue; compare Laravel/D1 schema behavior.
3. **Workflow completion:** integrate recruitment/interviews; implement the required invoice/settlement lifecycle; map rate cards, both departments' task histories and remaining assessment fields.
4. **Migration rehearsal:** immutable snapshot → validated staging → dry-run mapping → isolated import → count/hour/balance reconciliation → replay → restore rehearsal. Any unexplained difference blocks cutover.
5. **Operational acceptance:** actual runtime build, realistic D1 capacity/conflict tests, browser/mobile role-based journeys, monitoring, alert owner and backup/restore objectives.
6. **Controlled cutover:** freeze legacy writes at the agreed instant; migrate the final delta once; obtain departmental reconciliation acceptance; enable application writes; retain read-only Sheets and a rollback plan that preserves all new ledger events.

No cutover date is approved by this review. The safest next implementation work is database integrity and invoice/recruitment completion, followed by a full source-data rehearsal.
