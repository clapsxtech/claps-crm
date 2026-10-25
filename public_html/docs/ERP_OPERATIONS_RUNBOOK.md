# Staff administration and recovery

Release prepared 25 October 2026. Superadmins manage teams and accounts at
`/erp/administration`, inspect operational health at `/erp/administration/operations`, and prepare
imports at `/erp/migration-center`. Every user can set up authenticator MFA and
single-use recovery codes at `/account/security`.

## Staff and team administration

Create individual staff accounts with a temporary password and assign their teams.
Deliver credentials privately. Email delivery is not configured; onboarding does
not send invitations automatically. Superadmin has full access. Membership roles:

| Role | Access within assigned teams |
| --- | --- |
| Team manager | Daily operational pages and actions |
| Academics/attendance | People, teaching, timetable and attendance |
| Finance | Receipts, balances, payment requests and salaries |
| Sales/follow-ups | Follow-ups and tasks |
| Read-only | Operational pages and exports; no changes |

Server authorization checks team membership and action permissions. Combined
student/planning detail views are restricted to team managers and read-only staff
because they contain information from multiple functions. Team and account
administration remain superadmin-only. Existing legacy staff/admin memberships
retain team-manager behavior. Review those assignments before real onboarding.

Accounts support suspension, activation, password reset, session revocation,
promotion and demotion. Sensitive account actions require the acting superadmin's
password. Team archiving retains business records and blocks changes. Staff cannot
access archived teams. Superadmins can inspect or restore them. Protect recovery
codes separately from the account password.

## Backups and recovery

From `public_html`, using Node 22 and the domain-owning Wrangler login:

```sh
python3 scripts/erp-ops.py backup
python3 scripts/erp-ops.py verify /absolute/path/to/backup-directory
```

Backups default to `~/.codex/private/clapslearn-erp/backups/`, with private file
permissions and 30-day retention applied when backups run. These are on-demand
local backups; no automatic daily schedule has been installed. Keep this computer
and the private directory protected. A separate off-device copy remains future work.

Each backup contains a D1 SQL export, checksums, an offline SQLite integrity and
foreign-key validation, important table counts and ledger totals. Referenced
canonical profile uploads are downloaded from R2 and checksummed. Unreferenced
R2 objects and application secrets are excluded. Preserve the private APP_KEY and
INTERNAL_TOKEN independently; the APP_KEY is needed for encrypted MFA secrets.

Before recovery, stop writes, take a fresh snapshot where possible, and verify the
chosen backup offline. Restore into an isolated D1 database first and reconcile
counts, ledger totals and referenced uploads. Review Cloudflare D1 Time Travel or
a reviewed SQL import for the production recovery. No script automatically
restores or overwrites production. Restart the container after any database restore
to discard its working copy; then check health, login and database-backed pages.
See `CLOUDFLARE.md` for the authenticated restart procedure. This release validates
offline restoration, not a full production disaster-recovery exercise.

## Monitoring

A Worker cron checks container readiness, failed jobs and uncertain/sending
integration events every five minutes. Logs contain counts and timing, not records
or credentials. The authenticated Operations screen shows current diagnostics and
audit history. External email/webhook alerts are deferred at the user's request.
Logs alone do not notify an operator. Review unresolved integration events before
retrying to avoid duplicate downstream effects.

## Migration

Real staff rosters and historical data are deferred. The migration center supports
normalized JSON preview, reconciliation and a password-confirmed import tied to
the same file digest and a backup reference. Preview expires after 30 minutes.
Web execution is limited to 100 rows; use the reviewed operator migration procedure
for larger imports. See ERP_MIGRATION.md. Do not import an unverified source.

## Validation

401 PHP tests / 3,539 assertions pass in normal and D1 compatibility modes;
47 JavaScript tests, two backup tests and TypeScript checking pass. Targeted
administration, migration and MFA tests cover authorization, account lifecycle,
atomic provisioning, replay resistance and preview/commit validation.

`python3 scripts/erp-performance.py --credentials /private/access.json --output /private/results.json`
measures a bounded authenticated live sample: three concurrent sessions and
27 requests. It does not create business records. This is a baseline, not a
large-team capacity certification. Realistic-volume and sustained-load testing
remain necessary once expected traffic and historical data are available.

Live release verification: superadmin and staff login pages, superadmin sign-in,
both existing team dashboards and assignment pages, administration, operations,
migration center and account security returned successfully. Operational JSON
reported healthy status including migrations. Public registration remains disabled.
The initial health request exceeded 45 seconds while the new container started;
a subsequent check succeeded. Allow for startup delays during planned restarts.

Latest verified private backup: `d1-20261025T082938Z`, SHA-256
`74ba54eba4ec483b3c09b3d6fc9d62d5a74dbbd4b5adbb7c39368df6b22c169f`.
It contains one account, two teams, two memberships and no student, teacher or
ledger records. The empty business data limits the representativeness of load tests.

Post-deployment warm sample: 27 requests, three concurrent sessions, zero failures.
Health p50/p95: 1.69/2.70 seconds; home: 2.32/3.47 seconds; team dashboard:
2.27/3.64 seconds. Measurements are from this workstation and include network
latency. See ERP_PRODUCTION_PERFORMANCE_2026_10_25.json.
