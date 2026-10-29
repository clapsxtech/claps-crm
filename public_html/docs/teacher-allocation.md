# Teacher matching and class allocation

Open **Teacher matching** in a business unit, or follow the link from an enquiry
or student profile. The workflow reuses canonical teachers, eligibility,
availability, enrollments, recurring agreements, lessons and admissions records.
It does not create a separate teacher or student directory.

## Prepare and review

Managers resolve identity conflicts in Teacher recruitment. Corrections require
verified name, valid phone and evidence. Existing teacher-code links must still
match the canonical teacher. Merging two operational teachers is prohibited.
Duplicate recruitment profiles are retained with a surviving-profile pointer;
source rows and histories remain readable without moving or deleting them.
Merged profiles cannot be edited or booked. A profile already holding merged
children cannot itself be merged, keeping the history one level deep.

Academics and managers confirm exact subject/class/syllabus eligibility and
supported teaching media. Canonical availability windows carry weekday, times,
timezone and effective dates. New windows require confirmation evidence.
Free-text imported availability and qualifications are never auto-approved.

## Match and book

Select an enquiry or student, subject, teaching medium, date, time, timezone and
duration. Matching uses exact confirmed eligibility, not fuzzy text guesses.
Teachers are paginated 25 per page. Each result shows teacher-local time,
scheduled lesson hours in the next seven days, and the next unmet requirement.
Workload excludes demos and is informational, not a capacity policy.

The same scheduling service checks full interval coverage, exceptions,
recurring agreements and dated lessons. Timed demos also reserve their interval
against later demos and regular lessons. Legacy assigned demos without duration
conservatively reserve their local calendar date until reviewed/cancelled and
rebooked; no end time is guessed. Booking checks run again transactionally.

Demo booking requires bilateral WhatsApp/call confirmation and evidence. Lead
owners can book demos and record feedback; they cannot set teacher pay or
allocate regular classes. Read-only roles cannot mutate. Academics and sales
staff see only their own/assigned enquiries; managers see all in their unit.

After the timed demo ends, record attended feedback: Continue, Try another, or
Awaiting feedback. Only Continue permits a regular handoff for the same teacher
and subject. An existing student can be allocated directly without inventing a
duplicate admissions record.

Regular allocation requires explicit hourly rates in INR, first/last dates and
bilateral weekly confirmation. It creates or reuses the joined lead's canonical
student, records the confirmed student availability, creates the enrollment,
confirms a weekly agreement and generates dated lessons through the chosen end
date (at most 366 days). Every occurrence must fit teacher availability and be
free of conflicts. The whole handoff rolls back if any check fails. It never
posts attendance, charges, salary accrual or payments.

Stable request references make exact booking retries idempotent; changed details
under the same reference are rejected. Existing matched demos cannot have their
reserved times changed or be marked joined via the old admissions status form.
Cancel and rebook a slot through matching instead. A converted demo retains its
enrollment and agreement links.

## Release

The additive production migration is `0010_teacher_allocation.sql`; the matching
Laravel migration supports conventional database installations and local tests.
Production D1 uses Wrangler migrations, not Laravel migrate.

```sh
cd /Users/amnas/Desktop/logs.clapslearn.com-main/public_html
python3 scripts/release-teacher-recruitment.py --deploy-only
```

The helper backs up D1, applies migrations, deploys, restarts, checks readiness
and verifies the new schema. It does not rerun the Sheets data import. Source
Sheets remain read-only. Live teacher identity, eligibility, availability and
confirmation decisions must be made by authorized staff, not bulk approved by
the release script.
