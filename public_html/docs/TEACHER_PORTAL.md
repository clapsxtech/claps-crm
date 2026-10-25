# Single-login teacher portal

Entry point: https://erp.clapslearn.in/teacher

Linked teacher accounts are sent to their personal dashboard after sign-in. The
root workspace and the six older teacher page links also redirect linked teachers
to the appropriate dashboard section. Teachers never choose a teacher identity or
enter a DOB credential in the new dashboard.

## Dashboard

- Overview: active assignments, attendance needing submission, recorded hours,
  assessment count, upcoming lessons and quick actions.
- Timetable: own dated lessons, local times, lesson status and attendance status.
- Students: assigned active students, grade, syllabus and subject, with a shortcut
  that preselects the assignment for assessment entry.
- Attendance: actual minutes for completed eligible lessons; existing approval and
  financial posting rules remain in place. Replays do not create duplicate rows.
- Results: own active assignments only, score validation, retained input after
  validation errors, zero marks accepted, duplicate-safe retries using a D1 key.
- Reports: attendance and assessment history, teacher-timezone date boundaries,
  student filtering and paginated results. A visible 500-lesson limit applies;
  narrow the date range to inspect more lessons. No financial rates or guardian
  contact fields are exposed.

The dashboard uses ERP teacher records, enrollments, dated lessons, attendance and
marks. Historical snapshot tools remain available to administrators. This release
neither silently imports legacy snapshots nor guesses account identity from names
or email addresses.

## Enable a teacher account

1. Create an active login through User accounts if needed.
2. Create the teacher record in the correct unit's Students & teachers screen.
3. In Team access, grant the existing login the Teacher role in that unit only.
4. Use Explicitly link teacher to login account to connect the teacher record.
5. Assign students and subjects and publish dated lessons; share `/teacher`.

Administrators opening `/teacher` see these instructions and links to their own
units' Team access screens. Account unlinking, inactive users, wrong memberships
and wrong teacher pools revoke portal access. Every write checks current identity
and ownership again inside its transaction. No production teacher accounts were
invented or automatically linked by this deployment.

## Verification — 25 October 2026

- 386 PHP tests / 3,351 assertions pass normally and with the D1 adapter.
- 44 JavaScript tests pass; production build and TypeScript checks pass.
- Browser tests at 390 × 844 and 320 × 740: sign-in routing, all six sections,
  mobile navigation, assigned student shortcut, attendance save, invalid result
  correction to 0/20, immediate report visibility, date/student filters, empty
  states, reset, sign-out and session enforcement. No horizontal page overflow or
  browser errors were observed. These are simulated viewports, not physical phones.
- Fixtures were isolated locally. No test attendance, students, accounts or results
  were inserted into production.
- Security tests cover cross-teacher and cross-unit requests, forged identities,
  inactive assignments, unlink revocation, result replay/conflict and pagination.

Screenshots: [dashboard](../../artifacts/teacher-portal-mobile-dashboard.png),
[reports](../../artifacts/teacher-portal-mobile-reports.png).

D1 migration `0002_teacher_result_keys.sql` adds nullable submission key/hash fields
and a unique key to `erp_marks`. Existing rows are preserved.

## Production release

Worker `82e6d1eb-03d0-433a-b89c-f736b7c2c511`, container image
`sha256:0517f5de353932957fd5d6825a45873c02e6cd56742bf11205cb8b51848d580b`.
Health and administrator sign-in passed, as did 32 page routes and four existing
report POST queries. Anonymous `/teacher` and dashboard reports redirect to login;
the new sign-in page is served over HTTPS. Browser checks confirmed the live setup
page and Team access controls with no browser errors. No D1 migrations remain.
[Live setup screenshot](../../artifacts/teacher-portal-live-setup.png).
