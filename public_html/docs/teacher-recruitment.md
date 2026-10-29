# Teacher recruitment and directory

The shared site teacher pool lives at each unit's **Teacher recruitment** page.
Applications progress through APPLIED, CONTACTED and SELECTED. Registration is
recorded separately as REGISTERED; only explicit approval links or creates an
ERP teacher. Existing operational teacher statuses are preserved. Subject
eligibility, unit membership and scheduling checks continue to apply.

Site administrators and team managers review applications and record contact
history. Academics, sales/demo staff and read-only staff can search the shared
REGISTERED/APPROVED directory, but cannot read application review history or
change recruitment decisions. This pool is deliberately shared across units,
not a unit-specific copy of teacher identities.

## Source import

Sources were read without modifying either original Google Sheet:

- CLAPS TEACHER (Responses), Form responses 1 and Form responses 3.
- TEACHER 2.0 Registration CLAPS, Form responses 1.

The private snapshot contains 88,431 application response rows and 5,016
registration response rows. It is a selected operational-field export, not a
complete workbook backup. Empty rows and dashboard/template tabs are excluded.
Banking information, identity documents, home addresses and family details are
not copied into recruitment records or directory views.

Registration rows are imported first. A normalized valid phone AND exact
normalized name identify the same candidate across responses. Existing CT codes
link to canonical teachers only when names agree. Shared phones with different
names, conflicting CT identities and invalid contacts enter REVIEW. A source
row identifier is stored independently, so repeat execution skips already
committed rows. Each chunk is atomic; a whole import is resumable, not globally
atomic. Imported rows never automatically approve or reactivate teachers.

Snapshot files are private, excluded from git and Docker, and stored under
`storage/app/teacher-imports/2026-10-26`. Do not commit personal data to GitHub.

Preview:

```sh
php artisan erp:teacher-sheets-import storage/app/teacher-imports/2026-10-26
```

For the existing deployment, the release script verifies the reviewed digest,
exports a remote D1 backup, applies the additive D1 migration, deploys, restarts,
imports through the existing authenticated D1 gateway, then verifies source
counts. It uses the already-saved private Worker secrets without printing them:

```sh
python3 scripts/release-teacher-recruitment.py
```

If deployment succeeded but import was interrupted:

```sh
python3 scripts/release-teacher-recruitment.py --import-only
```

Verify without changing data:

```sh
python3 scripts/release-teacher-recruitment.py --verify-only
```
