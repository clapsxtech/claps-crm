# Additional workflow sheets — read-only review

Reviewed 26 October 2026. Scope: workbook/tab metadata, bounded headers and
sample records, plus selected formulas. No writes, imports or message sending.
This is workflow discovery, not a complete audit of records or calculations.

## Demo 3.0

Source: https://docs.google.com/spreadsheets/d/1up15SO6NNW2v9_grbn-Ykt8AocL1_UHhRbwkb1XmC54/edit

- DAILY UPDATE records staff/date, enquiries, no replies, arranged demos,
  feedback and follow-ups split by age (yesterday, 2, 3, 10 and other days),
  time-based work, demo checks, overtime, points and performance labels.
  Registrations, newly paid students and collection are explicitly labelled
  yesterday, while activity messages distinguish today. Sample collection
  formulas sum the internal STUDENT REGISTRATION tab by previous date and staff.
- INCENTIVE has repeated monthly blocks for hours worked, enquiries, joined,
  payment, demos arranged, collection and efficiency/conversion ratios. Sampled
  cells include division and value errors. The tab name does not establish an
  approved incentive payout formula; none was verified in the sampled ranges.
- Staff Ads tracks staff, country, campaign, budget, daily enquiry trends,
  weekly/monthly enquiries, demos, joins, collection, cost per lead and ROI.
  Treat its recommendations as sheet outputs, not instructions to change ads.
- STF also breaks campaign enquiries and conversion ratios down by staff.
  There is no tab literally called “Staff are”; Staff Ads and STF were inspected
  as the closest relevant matches.

Adaptation: derive daily reports from dated events and ledger receipts; separate
activity credit, owner-at-event, registration credit and payment credit. Preserve
attribution when leads transfer. Define unique lead/student counts versus event
counts and reporting dates explicitly. Handle zero denominators as unavailable,
not infinite performance. Add versioned incentive rules only after the actual
eligible events, rates, reversals and approval process are established.

## Student registration

Source: https://docs.google.com/spreadsheets/d/1BlPibi73uveaxU7f03u-9gq0Xlfjd3lAce4ZFQyZj-c/edit

Form responses 1 combines registration details with operational enrichment:
student code/name/status, rates, section, hours, first class/attendance, advance,
contact details, source, meeting link, school, class, syllabus, medium, preferred
time, admin/demo fields, monthly history, concerns, feedback and follow-up date.
Headers also cover terms acceptance, rejoining and duplicate detection.

Adaptation: keep one canonical student ID linked to the originating lead;
retain original submitted data separately from later corrections. Model multiple
contacts, preferences and consent evidence explicitly. Store monthly history as
dated records rather than ever-growing columns. Derive balances/hours from
validated transactions and attendance, never copy spreadsheet summary cells as
financial truth. Preserve concerns, feedback, stopped/rejoined history and next
follow-up as operational records.

## View Teacher

Source: https://docs.google.com/spreadsheets/d/1iuIQP9dBGyPUbLfml2FbAY5xJNETniwWhZJ2ebO4m90/edit

Form responses 1 contains teacher code/name, category, availability/status,
teacher type, subjects, medium and grade-band flags, curricula, demo attendance
and conversion counts, ratings and timetable hours. Additional fields cover
qualifications, experience, preferred times, equipment/connectivity, language
confidence, interview assessment, parent complaint history and reviews. Some
headers are duplicated or span two rows; one sampled helper cell has #REF!.

Adaptation: separate recruitment/interview evidence from active teacher
availability and capabilities. Match by subject, curriculum, grade, medium and
confirmed time availability. Keep ratings as dated assessments with assessor and
criteria; calculate demo statistics from linked demo events. Parse multi-row
headers explicitly before any future import and keep complaints access-scoped.

## Next design priorities

1. Daily staff activity and conversion reports with documented counting rules.
2. Campaign attribution linked through demo, registration and verified receipts.
3. Registration handoff and teacher matching using existing canonical records.
4. Incentive calculation only after payout rules are supplied or verified.

## Local implementation following this review

Admissions now has an optional date-range Sales activity & campaign report.
It counts creation, repeat enquiry, contact attempt, demo booking, demo feedback
and registration events by their recorded actor. Current lead visibility applies;
transferred leads disappear from a former owner's restricted report, while team
reports preserve the original actor credit. Date boundaries use the team's
timezone and convert to the installation timezone used for event storage.
Ranges are limited to 93 days and aggregation occurs in the database.

Campaign outcomes count unique leads whose original enquiry date falls in the
range, with their current joined/lost state and whether any demo was booked.
Repeated demos do not inflate the lead count. Corrected source values affect
these reports; they are not immutable historical attribution snapshots.

Finance-authorized viewers also see team receipt totals less receipt reversals,
using each ledger entry's date. These totals are separate from campaign outcomes
and are never joined through potentially repeated lead records. ROI, ad spend,
worked hours, performance scores and incentive payouts remain unconfigured.

Learning needs, parent concerns, student context and parent communication notes
can now be edited on an open enquiry, with the existing audit history. They are
shown during teacher matching and on the registered student's profile. Existing
students opened directly for matching use their latest linked lead's notes.
Teacher match rows show all-time team demo bookings, attendance and regular
allocations from ERP demo records; these figures are not imported ratings.
New demo feedback updates also appear in the lead activity history and report.

No new database migration or sheet import is required. No live deployment or
Google Sheet changes were performed. The changes use the existing local ERP
schema, including the admissions and teacher-allocation migrations already in
this working tree.

Validation: 15 focused tests passed initially (158 assertions). The final D1
compatibility run covered sales reporting, admissions, teacher allocation,
student profiles and reporting projections: 24 tests, 250 assertions.

