# Mobile workflow verification — 25 October 2026

Tests used an isolated local SQLite database and fictional teacher/student records.
No test attendance or marks were inserted into production. Browser viewport checks
used 390 × 844 and a narrow 320 × 740 viewport; these are browser simulations, not
physical-device tests.

## End-to-end browser checks

- `/attendance`: reject wrong teacher credential, retry successfully, load assigned
  student, choose subject/date/duration/homework and save. Verified one persisted
  1.25-hour record with server-calculated total 125.00. Success dialog and fresh form.
- `/resultform`: reject 21 out of 20 without losing input, correct to zero out of 20,
  save successfully and show confirmation.
- `/attendanceReport`: normal member login required, wrong verification rejected,
  correct verification reveals the saved lesson. Mobile row expansion exposes
  subject/topic. Date picker applies an excluding range, shows empty state, clears
  dates and restores the lesson; student filtering works. Page hour total verified.
- `/student-attendance-report`: verified teacher sees the same saved lesson and hours.
- `/markreport`: saved zero-mark result appears immediately, including subject name,
  date and chapter; student lookup includes the submitted student. Editing the
  credential immediately hides loaded records.
- `/timetableReport`: verified teacher sees Monday's test lesson and meeting link;
  another teacher's Tuesday lesson is omitted. Mobile menu opens and navigates.

## Issues fixed

- Attendance verification now has an explicit action, progress/result feedback and
  retryable errors. Credential edits clear assignments. Repeated saves are guarded;
  failed requests retain the same idempotency key. Removed unused client rate math.
- Attendance reports use explicit verification, clear stale rows on identity edits,
  abort pending requests and ignore stale responses. Added labelled page-hour totals.
- Mobile date calendars no longer inherit large report-cell padding. They fit the
  viewport with equal day columns, scrolling and accessible Apply/Clear controls.
- Result inputs request a numeric keyboard; optional remarks are correctly labelled.
- Assessment history previously read only the imported spreadsheet snapshot. It now
  combines local submissions with historical records and suppresses exact imported
  copies. Teacher scope applies before counts, filtering or pagination. No Sheets
  integration is required for newly saved results to appear.

## Automated verification

- 377 PHP tests / 3,218 assertions in normal mode.
- Same 377 tests / 3,218 assertions using D1-compatible database adapter.
- 43 JavaScript tests, including verification retries, duplicate-submit protection,
  unchanged retry keys, stale report responses and the bundled date picker.
- Production asset build and TypeScript checks pass.
- Broader existing suites cover access control, ERP scheduling, approvals, finance,
  receipts/reversals, imports, integrations and recovery; these were automated tests,
  not a manual walkthrough of every administration mutation.

Screenshots: [attendance](../../artifacts/attendance-mobile-e2e.png),
[results](../../artifacts/results-mobile-e2e.png).

Single-login teacher accounts were discussed as a follow-up. This release keeps
existing sign-in and teacher verification rules; it does not claim to implement
account-to-teacher linking or new account provisioning.

## Production verification

Worker version `1377b881-c596-4761-af82-ffa51633c604` deployed successfully.
Health, administrator sign-in, 31 page routes and four report POST queries passed.
Live mobile calendar checked with no browser errors: [screenshot](../../artifacts/mobile-calendar-live.png).
