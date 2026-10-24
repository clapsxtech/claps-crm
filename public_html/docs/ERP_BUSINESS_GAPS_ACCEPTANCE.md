# Operations gap closure acceptance

Verified 24 October 2026, with synthetic local data only. This is code/test evidence, not production deployment or source-data reconciliation.

## Implemented

- Partial-day and full-day exceptions with frozen UTC/timezone intervals, legacy all-day compatibility, explicit midnight endings, DST validation, exact replay, overlap checks and safe rollback refusal.
- Named, independently paginated student-import and teacher-approval/accrual queues. Duplicate candidates remain importable under confirmed policy, but teacher approval remains blocked pending review. Corrected postings reappear; stale submissions are revalidated by the existing services.
- Named record references in daily registers and task/checklist completion controls that preserve mandatory checklist safeguards.
- Exact decimal INR entry and integer-only display; old paise API inputs and exports remain compatible, mixed units and invalid precision are rejected. Student credit/dues and teacher earned/paid/net remain separate.
- Schema-v2 explicit zero/positive teacher opening payables, rollback-only non-production rehearsal, exact-snapshot reconciliation and fail-closed replay/drift checks. No history is billed or accrued again.

## Automated evidence

- Full SQLite suite: 342 tests / 2871 assertions, passed.
- Full MariaDB 10.11-default suite: 342 tests / 2871 assertions, passed. Some isolated service tests explicitly use SQLite; this is not a claim that every test ran on MariaDB.
- Real two-process MariaDB InnoDB REPEATABLE READ harness: 18/18 scenarios passed, including new exception replay and exception/scheduling races in both orders.
- 18 Node interaction/Worker tests, PHP syntax checks, Composer platform/metadata validation, Worker typecheck and production asset build passed.
- Dependency security is a separate gate. These regression results do not certify the existing dependency graph; the supported-framework/security stage is tracked separately.

## Actual cloud-browser evidence

- INR 12.345 is blocked by native precision validation; 0.00 is rejected by the server without a ledger row. Keyboard-entered 1234.29 produces exact displayed INR 1,234.29 in synthetic bookkeeping.
- A named synthetic pending class appears in both queues. Explicit student import posts exactly INR -120.29 while teacher payable remains unchanged. Separate teacher approval posts INR 60.25 and clears its queue. No real payment or transfer occurs.
- A timed exception 11:00–12:00 Asia/Kolkata displays 05:30–06:30 UTC. Exact replay retains one row; an overlapping 11:30 interval is rejected and the original remains intact.
- Only the new additive migration was applied to the isolated synthetic SQLite browser fixture. No real database or source import was used.

Known usability follow-up: server validation errors collapse details and reset lookup selection, although amount/reference inputs are retained. This is tracked as guided-workflow context retention, not claimed fixed. The timetable's earlier 490px narrow-window evidence remains valid for that board; the new forms still need real-device acceptance on the intended host.

## Remaining gates

Actual source mapping, signed opening totals, cutoff approval, real migration preview digest, intended database/host acceptance, backup/restore readiness, monitoring configuration and production cutover remain separate. Schema v1 does not imply zero teacher dues. Negative teacher advances require review. Disposable rehearsal may leave MySQL auto-increment gaps despite rollback and must not be used as a production test.
