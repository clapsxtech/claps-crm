# Full-day and timed availability exceptions

Exceptions are nonfinancial scheduling blocks for exactly one student or teacher in the selected unit. AA and BB retain separate teacher pools. No exception cancels a lesson, submits attendance, charges a student or accrues teacher pay.

## Operator workflow

1. Open the detailed scheduling workspace and choose **Record full-day or timed unavailability**.
2. Select one student or one teacher and the local date.
3. For a full day, leave both time fields blank. A blank timezone uses the participant's profile timezone; an explicit IANA timezone applies to that date instead. The unit timezone is not silently substituted.
4. For a timed exception, supply both `HH:mm` times and an explicit IANA timezone, such as `Asia/Kolkata`. The end must be later on the same date, except that end `00:00` means the next midnight when the start is later than `00:00`. To block 23:00–01:00, record 23:00–00:00 on the first date and 00:00–01:00 on the next. Equal times are invalid, not an all-day shortcut.
5. Record a reason. First explicitly reschedule or cancel any scheduled lessons overlapping that interval. Lessons elsewhere on the date do not need to change.

Nonexistent or ambiguous DST wall-clock endpoints are rejected, including a next-midnight endpoint affected by a timezone transition. Valid intervals spanning a clock change use actual elapsed UTC time, not an assumed fixed day length.

Registers show the local interval, its stored timezone and both UTC boundaries. A midnight-ending interval is labeled “next day.” Legacy full days are explicitly labeled as participant-timezone legacy records.

## Scheduling and retries

Intervals are half-open: start is included and end is excluded. A lesson ending exactly when an exception starts, or starting exactly when it ends, is allowed if all other scheduling rules pass. Multiple disjoint or adjacent windows may be recorded on the same date. Overlapping exceptions are rejected rather than merged or expanded.

An exact retry for the same participant, local date, stored timezone, UTC interval and trimmed reason returns the existing record and creates no second audit event. Reusing an overlapping interval with a different reason, timezone or boundaries is rejected. Requests do not edit prior exceptions.

Bilateral recurring confirmation, later lesson generation, one-off demos/extras and rescheduling all enforce these intervals. Generation remains atomic: if a requested batch contains a blocked occurrence, none of that batch's newly generated lessons persists. Already generated, cancelled or rescheduled occurrences are never recreated. A confirmed agreement is not silently rewritten when an exception is added.

## Additive schema and compatibility

Migration `2026_10_23_170000_add_intervals_to_erp_exceptions.php` adds nullable `starts_at`, `ends_at` and `timezone`, plus participant-scoped interval indexes. Timestamps are UTC; the timezone is an IANA snapshot.

- Existing rows are not backfilled or changed. All three new columns remain null, retaining the previous full-date meaning in the participant's profile timezone.
- Every new row sets all three columns, including full-day exceptions. A full day uses local midnight through the next local midnight, which can span 23 or 25 hours.
- Stored intervals do not move if a profile timezone later changes. Existing restrictions on profile timezone edits remain in place.
- A mixed null/non-null snapshot or reversed interval is invalid. Recording or scheduling for that participant fails closed until the saved data is explicitly corrected; it is not treated as an all-day block or ignored.
- Teacher/student locks and current reads continue to serialize exception creation with lesson scheduling. Exact replay does not depend on a nullable unique constraint.
- Rolling this migration back is refused while any snapshot-bearing row exists. Dropping interval columns would silently turn a partial exception into a legacy full day. Preserve and explicitly resolve these records before planning a rollback; never bypass this guard on a live database.

Do not roll back application code alone after snapshot rows exist: the old date-only scheduling code would interpret every partial interval as an entire date, even if the new columns remain. Deploy migration and compatible code as one coordinated release, and prepare a separately reviewed data-preserving rollback before enabling timed exceptions.

Apply migrations through the separately authorized deployment process. This code-only milestone neither migrates a real database nor authorizes production deployment.

## Synthetic checks

`ErpSchedulingTest` covers legacy preservation/replay, full-day snapshots, atomic recurring generation, one-offs, reschedules, scheduled-lesson protection, adjacency, multiple windows, changed-reason rejection, timezone/date crossover, invalid hybrids, unit/pool isolation, DST endpoints and rollback protection. `ErpExceptionHttpTest` covers form/JSON requests, HTTP authorization and both rendered registers. Manual scheduling, teacher-access and native-concurrency fixture builders include the additive migration. The native harness adds same-window concurrent replay and controlled exception/one-off and exception/materialization races in both orders, including stale REPEATABLE READ snapshots.

The extended native harness passed all 18 scenarios on isolated MariaDB `10.11.18-MariaDB-0+deb12u1` with InnoDB and `REPEATABLE READ`, including all five exception races. This establishes only the tested synthetic interleavings on that engine/version.

Use the normal test configuration for a temporary SQLite database. Re-run opt-in native database tests on the intended engine/version before operational release. Browser/device acceptance and real-data cutover remain separate release gates.
