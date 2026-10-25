# Cloudflare deployment status — 25 October 2026

Live URL: **https://erp.clapslearn.in**

## Current performance release

Worker `b90f796d-9f45-4d09-85b6-398ea010c5ee`, image
`sha256:8b97acecd4ebcc52190984477abbcc04ce56709d45df6208cc52e7b528ed9e97`.
Public assets now use Cloudflare static delivery; PHP OPcache is enabled and
readiness probes run once per boot. No database migration. Live checks and all
54 measured requests passed. See [the performance comparison](ERP_PERFORMANCE_RELEASE.md)
for measured improvements and remaining limitations.

## Staff administration release — 25 October 2026

Worker version `8f41fbca-8229-4481-bdec-b59c80b3d3ce`, image
`sha256:36104a0330484abe4d583786efa5784ff8e83e8b4ccde6a4348807c6838d63c3`.
D1 migrations 0003 and 0004 applied. Includes five staff roles, team/account
lifecycle controls, optional authenticator MFA, migration preview/commit tooling
and five-minute operational checks. See [the current operations runbook](ERP_OPERATIONS_RUNBOOK.md).

Validation: 401 PHP tests / 3,539 assertions in both normal and D1 compatibility
modes; 47 JavaScript tests; two backup tests; TypeScript checking. Private local
backup restored offline with integrity and checksum checks. Real staff roster,
historical data import, external alerts, off-device backups and sustained-load
capacity certification remain deferred.

Live sign-in, both teams, all new management pages and operational diagnostics
passed after container startup. The first health request timed out during startup.

The following records describe earlier release verification.

The ERP is deployed with a new production database. All runtime hosting and
persistent application storage are in Cloudflare:

- Worker: `clapslearn-logs`
- PHP/Laravel runtime: Cloudflare Containers, `standard-1`
- D1 database: `clapslearn-erp` (`6056c8ae-89d3-4cea-a075-0d3bb8d79200`)
- R2 uploads: `clapslearn-erp-uploads`
- Custom domain and HTTPS: `erp.clapslearn.in`
- Account: `74ad8d10da1568cedacdd379a246cefc`
- Verified Worker version: `82e6d1eb-03d0-433a-b89c-f736b7c2c511`
- Container image digest: `sha256:0517f5de353932957fd5d6825a45873c02e6cd56742bf11205cb8b51848d580b`

The initial database contains the administrator, application settings, and the
AA/BB business units with administrator memberships. No sample business data was
seeded. The original default-password seeder was not used.

## Credentials

Administrator email: `clapsxtech@gmail.com`.
The generated password is stored only in the private local file
`/Users/amnas/.codex/private/clapslearn-erp/access.json` (mode 0600).
Application secrets are encrypted Worker secrets. Private recovery copies are
outside the project in the same protected directory. Do not commit them.

## Verification completed

- 375 PHP tests / 3,198 assertions pass in the normal test configuration.
- The same 375 tests / 3,198 assertions pass through the D1 compatibility adapter.
  This suite uses an isolated SQLite batch-boundary test implementation.
- Eleven separate checks passed against the actual local Cloudflare D1/R2 runtime:
  payment replay, exact balances, rollback, snapshot recovery, stale-write rejection,
  idempotent commit retry, failed-batch atomicity, authentication, and upload/read/delete.
- All 40 JavaScript tests, TypeScript checking, and production asset builds pass.
- Live HTTPS health and administrator login passed.
- The ERP landing page and all eight business screens in each of AA and BB passed:
  dashboard, people, timetable, attendance, money, followups, staff, and migration.
- After replacing the container, the existing authenticated session still worked.
  Both dashboards, legacy users/settings/workhour/marklist/timetable/subjects/topics,
  and the attendance/result/performance forms returned HTTP 200.
- Live R2 upload and exact-byte read passed. The object survived replacement of the
  container, and the temporary test object was then deleted and confirmed absent.
- Unauthenticated access to the internal database gateway returned HTTP 401.
- Application assets are served from the custom domain; external font imports were removed.
- The health probe bypasses sessions and makes no database writes. Container logs
  are enabled; startup gives Apache a regular log file and streams it to Cloudflare.

## Operational boundaries

D1 is authoritative. Container SQLite is a disposable, locked working copy;
commits are atomically acknowledged by D1 before success is returned. Never use
container disk as a backup. See [D1 architecture and limits](CLOUDFLARE_D1.md).

The adapter caps one atomic operation at 900 mutation statements and 95,000 bytes
per expanded statement. Large imports need sizing and rehearsal. Cold starts must
restore the working copy from D1, so their latency grows with the database.

Optional Google Sheets, Microsoft OAuth, and email integrations retain their code
but are not configured or live-verified; no provider credentials were supplied.
Hosting and storage require no external provider.

Future image or secret changes require a container restart after deployment.
See [deployment instructions](../CLOUDFLARE.md). Keep the private credentials file
for administrator access and encrypted-data recovery.

## UI redesign verified

The workspace home, shared ERP shell, business dashboards, forms, tables and empty
states now use a consistent responsive design. Navigation includes authorized unit
switching, keyboard-accessible mobile navigation and sign-out. Existing workflows
and permission checks are preserved. Desktop and 390px mobile checks passed on the
live deployment, alongside administrator login and all 16 business screens.
Cold-start readiness now uses an elapsed-time deadline, so fast connection refusals
do not exhaust the retry budget before the container boots.

Screenshots: [desktop](../../artifacts/erp-redesign-desktop.png),
[mobile](../../artifacts/erp-redesign-mobile.png).

## Unified entry point and Academics

The primary workspace now lives at `/`; `/erp` and `/home` redirect to it.
Legacy tools are grouped at `/legacy`, with links to the corresponding new
workflows. Academics adds a dedicated assessment form, searchable date-filtered
results, safe named CSV exports and subject eligibility management.
See [replacement status and remaining legacy features](ERP_REPLACEMENT_STATUS.md).

Live verification for this release passed: administrator login, root workspace,
Legacy tools and all nine business screens in each of AA and BB (18 screens).
The browser confirmed `/erp` arrives at `/` and the assessment action opens its form.
Screenshot: [Academics](../../artifacts/erp-academics-live.png).

## Complete records and teacher interface

The records catalogue, historical reports and site administration now share the
ERP shell. `/tools` replaces the Legacy tools directory, with `/legacy` redirected
for bookmarks. All six teacher routes now use the Teacher workspace, with mobile
navigation, consistent forms and report controls. Existing route permissions,
verification requirements and record storage are preserved. See
[replacement status](ERP_REPLACEMENT_STATUS.md) for scope and tests.

Final release verification: health, administrator login, workspace, tools directory,
all six teacher routes, topics, users, roles, settings and historical assessment/
monthly reports passed over HTTPS. The bundled date picker opened in the live
student-attendance report with no browser errors. Screenshots:
[teacher reports](../../artifacts/erp-teacher-reports-live.png),
[topics](../../artifacts/erp-topics-new-ui.png).

## Mobile workflow fixes

Attendance verification, retry behavior, stale-report clearing, mobile calendar
layout and direct local assessment history were verified with isolated fixtures.
377 PHP tests / 3,218 assertions pass in normal and D1-compatible modes, alongside
43 JavaScript tests, production asset build and TypeScript checks. See the
[workflow verification record](MOBILE_WORKFLOW_VERIFICATION.md) for exact scope
and screenshots.

Production verification passed health, administrator sign-in, 31 page routes and
four live report POST queries. The new attendance verification action is present,
and the live mobile calendar renders without browser errors. Container restart
and readiness confirmed before verification.

## Single-login teacher portal

`/teacher` is the entry point. Linked teachers receive a personal dashboard with
all six sections; administrators receive account-linking instructions and links
to their permitted units. See [teacher portal setup and verification](TEACHER_PORTAL.md).
386 PHP tests / 3,351 assertions pass normally and in D1-compatible mode; 44
JavaScript tests, the production build and TypeScript checks pass.
D1 migration `0002_teacher_result_keys.sql` applied successfully.

Live verification passed: health, admin login, 32 page routes, four report queries,
anonymous portal access enforcement, new login UI and administrator setup controls.
No migrations remain pending.

## Superadmin and staff release — 25 October 2026

- Deployed Worker version: `f63d8d5f-703c-48d0-88ef-43c78aa11c10`.
- Container image: `sha256:fd9284bd1c197acb1ab3e9a5b63090e78c65d54644ec3b62c65b2dadcade3089`.
- Production container restart confirmed. Initial cold-start health probes timed out while the D1 working copy restored; subsequent health and application checks passed.
- Verified both `/superadmin/login` and `/staff/login`, authenticated superadmin login, both team dashboards and assignment screens, account management, logout, and disabled public registration.
- 390 PHP tests / 3,410 assertions passed in both standard and D1 compatibility configurations; 44 frontend tests passed, along with TypeScript checking and the production container build.
- No schema changes, default passwords, or production staff assignments were introduced by deployment. Staff isolation and assignment/revocation behavior were covered by automated tests; no synthetic staff account was created in production.
