# Operational verification and synthetic recovery

These tools prepare code-level operational checks. They do not configure hosting, monitoring services, alerts, backup destinations, retention, credentials or production restores. Nothing here certifies an actual backup or real-data cutover. No source Sheets are contacted or modified.

## Read-only health indicators

Run from the application directory with an authorized operator account:

```sh
php artisan erp:health
php artisan erp:health --stale-minutes=30
```

The command emits JSON only, with exit status 0 (all observed checks OK), 1 (warning requiring review), or 2 (unavailable/error/invalid option). It does not schedule itself, dispatch outbox events, repair records, write audit records or send alerts. There is deliberately no public HTTP endpoint and no new staff permission. Protect operator shell access and captured reports.

Indicators:

- Database: a read-only connectivity query. Errors are reduced to a stable code, never raw exception text or connection credentials.
- Schema: count of unapplied repository migrations and selected required operational tables missing. This is not an exhaustive schema-drift detector.
- Failed jobs: total retained failed-job count. Old resolved failures remain visible until the existing operator process handles them; the command never deletes them.
- Outbox: pending/sending/uncertain counts, unknown-state count, stale sending claims (including missing claim time), and pending events older than the selected threshold. A future retry time does not erase the event's age. Warnings remain visible even when delivery is disabled. Do not retry uncertain events: reconcile remote outcomes first through an approved process.
- Audit: events in the last 24 hours and most recent timestamp. An empty or quiet audit stream is informational, not evidence of an outage. Presence of audit events does not prove audit completeness or tamper resistance.

Payloads, recipient/student identities, job exception text and audit content are excluded. Times use the application's configured clock/storage semantics. Operators must agree thresholds, expected workload, incident ownership and escalation separately before connecting a scheduler or alert channel. HTTP availability, application log error rates, disk capacity, queue-worker/scheduler heartbeat, backup freshness and infrastructure monitoring are not inferred from these database indicators.

## Synthetic restore drill

```sh
php tests/Integration/erp-recovery-drill.php
```

Requires the installed application dependencies and PDO SQLite. The script accepts no parameters, source paths or credentials. It creates a randomly named, private temporary directory, ignores the installation's `.env` and configuration cache, and explicitly selects a new SQLite database with Sheets disabled. It runs the real migrations and creates only fabricated AA/BB units, students and receipt/audit fixtures. It cleans its own temporary files on normal success or failure; an abrupt process termination may leave a synthetic temporary directory for operator inspection/removal.

The rehearsal:

1. Migrates the repository schema and writes two synthetic, integer-paise receipts through the application service.
2. Records all user-table schema/row digests and closes the isolated database connection.
3. Copies that quiescent synthetic database, records SHA-256, rejects a deliberately corrupted copy before creating a restore destination, and restores only to a new file and verifies an existing destination cannot be overwritten.
4. Checks the restored file hash, SQLite integrity and declared foreign keys, all table/schema/row digests, synthetic student/unit ledger links and exact unit balances.
5. Replays the same receipt requests and confirms no additional ledger or audit events; confirms the original synthetic database file stayed unchanged.

A SHA-256 digest detects altered bytes when the expected digest is trusted; it is not a signed authenticity record. SQLite's foreign-key check covers only declared constraints, so it is complemented by explicit fixture linkage checks. This drill is not exhaustive ERP data reconciliation. The file copy is safe only because this generated fixture is isolated and closed, with no concurrent writer. Never apply this copy procedure to a running production database.

For MySQL/MariaDB, an operator-approved native consistent backup/restore procedure, exact server versions, encryption, retained artifacts, restore-target isolation, recovery objectives (RPO/RTO), application upload/storage handling and business reconciliation remain necessary. This SQLite rehearsal does not certify any of those or any actual backup. Production restore must have its own explicit authorization and validated rollback/cutover plan.

## Focused automated verification

```sh
vendor/bin/phpunit tests/Feature/ErpOperationalHealthTest.php
vendor/bin/phpunit tests/Feature/ErpRecoveryDrillTest.php
```

Health tests use synthetic data and cover healthy/empty audit, pending migration, failed jobs, stale/uncertain/unknown outbox states, redaction, non-mutation and exit statuses. Recovery tests run the isolated script in a subprocess and verify rejection of externally supplied paths. Run the full project regression suite as well before publication. Report the actual engine and freshly observed results; historical test counts are not evidence for a new revision.
