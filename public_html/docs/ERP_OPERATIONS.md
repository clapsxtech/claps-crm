# Timetable-first operations checkpoint

This is an additive operations module, not a completed production migration. Existing records and integrations are unchanged. Original Google Sheets remain read-only. No real student/teacher data is bundled.

## Start in an isolated test deployment

Run normal dependency installation and database migrations against a backed-up **test** database. The new migration creates only `erp_*` tables. Existing accounts are reused, not copied from names. Provision AA and BB explicitly:

```
php artisan erp:provision-unit AA EXISTING_ADMIN_ID --phone=TEAM_PHONE
php artisan erp:provision-unit BB EXISTING_ADMIN_ID --phone=TEAM_PHONE
php artisan erp:grant-membership AA EXISTING_TEACHER_USER_ID teacher
```

These commands are deployment-operator actions, not public routes. Do not grant an account to a unit unless the operator has verified its identity and authorization. Visit `/erp`; the legacy administration sidebar also links to it. Register a teacher with the explicitly provisioned user ID to activate `/erp/teacher`. Unlinked accounts cannot see lessons. Teacher pools are separate and a teacher can belong to exactly one unit.

## Connected workflow

1. Register student with stable code, registration date, grade/syllabus and IANA timezone. Priority is current registration calendar month Super, previous High, all older Medium, using the unit timezone.
2. Register teacher, eligibility, and an effective-dated student-subject assignment with frozen hourly rates in integer INR paise.
3. Record both participants' recurring availability. Propose and confirm the timetable only after admin records both parties' agreement via WhatsApp/call. No message is sent by the application.
4. Use the one-off lesson action for a demo or extra class with a stable reference, matching availability and bilateral confirmation. Demo lessons are always free/unpaid. Generate dated lessons for an explicit bounded date range. Student/teacher collisions and availability are checked. Rescheduling preserves original/replacement links and requires renewed bilateral confirmation. Cancellation never posts a charge. Full-day participant exceptions block scheduling; resolve existing lessons first. End a recurring agreement and create a linked replacement instead of rewriting history.
5. Teacher submits actual minutes for their own finished lesson. Admin may also record it. Future lessons cannot be marked attended. Submission itself has no financial effect.
6. Admin explicitly imports attendance IDs with a stable batch reference. Confirmed business policy posts all valid imported attendance, including duplicate candidates. Individual approval is not a prerequisite for student charging. Financial events are atomic, with database event-key idempotency, and based on frozen hourly rates. Separately, an administrator approves attended classes to accrue teacher earnings at the frozen agreed rate. Import alone never approves teacher pay.
7. Admin records the Gallabox teacher reply. Genuine repeats retain both charges; choose the erroneous attendance ID to reverse its student charge and any approved teacher accrual. Corrections preserve the old events through compensating entries and require a new import for student charges and renewed administrator approval for teacher accrual. No actual teacher communication is sent.
8. Record already-received payments with unique references. Positive credit and negative dues carry forward indefinitely; there is no monthly reset. Print payment acknowledgements, not tax invoices. Admin tasks and assessments are unit-scoped.

The dashboard supports search and independent pagination for every register (50 records per page). Teachers see only their own lessons and minimal student identifiers, not fees, contact details or other teachers' students.

## Financial boundary

`ERP_FINANCE_POLICY=admin_import_delivered_only` is the confirmed default. `unconfigured` disables posting for staging experiments. Only attended non-demo lessons post student charges. Demo classes are free for students and unpaid for teachers: their attendance can be recorded/imported/approved without either financial event. No cancellation fees, automatic collection, balance-driven class suspension, refunds, salary disbursement, tax calculation or tax-invoice claim is implemented. ERP_PAYROLL_POLICY=approved_attended_only enables confirmed teacher accrual. The teacher register shows approved earned amounts including reversals, not proof of payment or net dues after external settlements. No transfer/payment integration is provided.

## Migration and remaining release gates

See `ERP_MIGRATION.md` for read-only normalized preview. No importer or automatic Sheets conversion exists. Before cutover: map stable codes, reconcile historical attendance/opening balances at one cutoff, obtain verified source totals, resolve duplicate identities and rates, test on a cloned source, and approve reconciliation. Historical consumption must never be charged again on top of imported balances.

Still required before full replacement: real-data import/reconciliation, partial-day availability exceptions, staff assignment administration, payment reversal/refund workflow, production-database-version acceptance, and browser acceptance on the intended host. The old teacher logging flow and normalized lesson attendance are separate until mapped during migration; do not dual-enter the same class in both systems as a financial source.

No production deployment, source import, credential setup or external notification is performed by this code checkpoint.

## Synthetic testbed acceptance walkthrough

Use a separate database and existing test-only accounts, never production credentials or copied personal records. Keep Sheets synchronization disabled. Provision AA and BB, then create clearly synthetic codes such as SYN-AA-S001 and SYN-AA-T001. Use example.invalid identities and no real guardian contacts.

In AA, register one student and one eligible teacher, assign a subject, and enter matching availability. Confirm a past dated recurring agreement, generate one finished lesson, and submit 60 actual minutes from the linked teacher portal. Verify the balance remains zero before the admin import. Import the attendance once with SYN-BATCH-001: a ₹100 hourly rate (10000 paise) should produce -10000 paise. Reusing the same batch reference must not deduct again. Record a received ₹200 payment (20000 paise) with SYN-RECEIPT-001: balance should become +10000 paise. Change attended minutes to 30 with a reason: the old charge must reverse; a new import should charge 5000 paise. Verify cancellation generates no charge and the BB account cannot access AA records.

For duplicate testing, create two non-overlapping finished lessons for the same pair on one day. Import both before verification, approve both for teacher accrual, then record the Gallabox reply and choose the erroneous attendance ID. Only its student charge and teacher accrual should reverse. Marking a genuine repeat must retain both charges without another debit. Test a holiday exception, a schedule replacement, and a second page of records before inviting staff to trial the testbed.

These are test acceptance instructions, not instructions to run a production migration or perform payments. Receipt entry records a payment that already happened; it does not collect money.

## Native database validation

The MariaDB 10.11.18 default-database test configuration passes 197 tests / 1218 assertions after fresh-install and DataTables compatibility fixes. Some isolated service tests intentionally remain SQLite; the HTTP/default-database tests exercise native MariaDB. Five real two-process REPEATABLE READ concurrency probes also pass. See `ERP_MYSQL_TESTING.md` for the guarded opt-in harness. This does not certify other MySQL versions or replace acceptance on the intended host. Browser acceptance remains blocked because the supported cloud browser cannot reach the isolated test-server namespace.
