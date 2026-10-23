# ClapsLearn: modular monolith foundation

## Scope and compatibility

This patch keeps Laravel 8, the existing database tables, route names, and Blade interface. It is a repair and an incremental module boundary, not a full ERP or a framework rewrite. Existing controllers are HTTP adapters; reusable actions and services own the new authorization, validation, calculation and integration rules.

The installation is explicitly **one organization**. `ERP_BUSINESS_ID` identifies its existing users. Academic tables have no trustworthy tenant key, so adding another business ID does not create a safe multi-tenant deployment. Use separate databases and deployments for the experimental copy.

## Module ownership

| Module | Current responsibility | Boundary |
|---|---|---|
| IdentityAccess | Site membership, administrator gate, report scope | Reject missing/inactive/deleted/foreign-site users; preserve existing admin compatibility, do not grant new roles |
| People | Teacher assignment verification and staff provisioning | Verify exact teacher/student code-and-name pairs; reject ambiguous or conflicting master data; do not serialize credentials |
| Academics | Result/performance submissions and canonical exports | New rows retain names and stable codes; legacy numeric IDs are resolved only with corroborating stable codes |
| Attendance | Record, correct, and import work-hour records | Authenticate before replay; immutable original assignment; local submission IDs prevent destructive snapshot replacement |
| Billing | Decimal work-hour calculations | Existing UI rule is preserved: hours × authoritative `hour_payment`; client amounts are ignored; historical corrections retain original rates |
| Integrations | Snapshot reader/reconciler and transactional outbox | No remote append inside an HTTP save; immutable credential-free payload, atomic claim, explicit uncertain outcome |

Eloquent models remain in `app/Models` as compatibility adapters. Moving every model/controller is not necessary to establish useful boundaries. New features should depend on module services rather than importing controller methods. Existing legacy report adapters can be migrated gradually.

## Identity and report rules

- Public teacher flows retain the existing DOB-like credential for compatibility, but require a nonempty exact match. This is a weak legacy credential, not strong account authentication.
- Timetable data is returned only after POST verification, and only the verified teacher's day-specific fields are projected. No public HTML embeds schedules, links, phone numbers or credentials.
- Ordinary logged-in staff must additionally verify the teacher identity before viewing individual report rows/counts. Administrators of the configured site may access the complete reports. Student codes alone are not authentication.
- All compatibility credential routes share a hashed, normalized teacher-name attempt bucket: 30 requests per minute. Credentials are never cache keys. File cache persists within the single running container; shared durable caching is required before horizontal scaling. This mitigation has a targeted temporary denial-of-service tradeoff and does not replace teacher accounts.
- No DOB alias or confirmation password is flashed back as old form input. Financial fields and private report response fields are allowlisted.

## Submission and integration semantics

A result/performance save commits its business row and immutable outbox event in one transaction. A client-generated UUID is retained until confirmed success. Replaying an authenticated identical request returns the original result; reusing that key for different data is HTTP 409. Lookup labels can later change without breaking retries. Attendance uses its own unique submission key and server-calculated rate snapshot.

Google `values.append` cannot provide exactly-once delivery. A worker atomically claims an event; ambiguous exceptions, timeouts and stale claims become `uncertain` and are not automatically appended again. Reconciliation must inspect the destination before any authorized resend. See `app/Modules/Integrations/README.md`. Administrative edits remain local-only; they do not silently append another remote row. Unsupported hard-delete buttons are removed rather than introducing unreviewed destructive capabilities.

Reference imports validate the complete nonempty snapshot before mutation. Teacher/student records reconcile by stable code to retain retained IDs. Other snapshots replace atomically without MySQL `TRUNCATE`. Conflicting identities, credentials and rates fail closed. Attendance replacement is rejected when local UUID-bearing submissions exist; this is a data-loss guard, not a decision that either Sheets or the database is the permanent source of truth.

## Timetable-centered roadmap, not implemented features

The next design should follow the business's one-to-one tuition model, after reviewing the actual Sheet schema:

1. Establish stable teachers, students, subjects and effective-dated teacher/student/subject assignments. Model a teacher's multiple subject capabilities and a student's different teachers by subject explicitly.
2. Separate each person's availability from the agreed recurring timetable. A matched availability slot becomes a proposed agreement, not automatic proof that a class occurred. Record time zones and effective dates.
3. Generate dated lesson occurrences from accepted timetable agreements. Track rescheduling, cancellation, completion and corrections without rewriting historical agreements.
4. Attach attendance, learning outcomes, approved rate snapshots and payable/billing entries to those occurrences. Introduce approvals and audit history before automating payments.
5. Replace DOB verification with explicit teacher accounts and reliable account-to-person mappings. Design real tenant keys and authorization only if multi-organization hosting is actually needed.
6. Add reconciliation, operational dashboards and carefully scoped portals after the core timetable-to-lesson lifecycle is proven with representative sanitized data.

No scheduling algorithm, availability import, payment workflow, new portal, tenant migration or production data import is part of this patch.
