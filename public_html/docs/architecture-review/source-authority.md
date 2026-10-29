# Attendance and payment source authority

**Status: confirmed by the user after the architecture review.** The application becomes the authoritative record for attendance and payments after cutover. Sheets are retained as read-only history and exports. This records the business decision; no live system settings or Sheet permissions were changed.

## Consequences for implementation

- Canonical attendance belongs to a dated application lesson and retains its submission identity, review history and corrections. A later Sheet snapshot cannot replace an accepted attendance record.
- Payment receipts, student charges, teacher accruals, recorded salary payments and reversals remain distinct ledger events. Derived Sheet balances cannot overwrite the application ledger. Recording a payment does not initiate a bank transfer.
- Existing approval rules remain in force: attendance submission does not by itself authorize student charging or teacher accrual. Keep administrative import/approval and applicable parent-review gates until a separate policy change is explicitly decided.
- After cutover, integrations may export application data to designated reporting destinations. Imported history and discrepancies go through explicit staging and reconciliation; no automatic bidirectional reconciliation or last-write-wins behavior is permitted for attendance or money.
- Preserve source workbook/gid/record identity, snapshot digest, cutoff, canonical target and import batch. Repeated migration or delivery must not duplicate attendance or financial effects.

## Cutover procedure

1. Select a precise UTC cutoff instant and record its local-time equivalent. The current migration model uses a cutoff date at UTC midnight; choose a compatible instant or extend that model before selecting a different boundary.
2. Freeze legacy operational writes at that boundary and capture immutable source snapshots, including the upstream Accounts workbook. Prevent simultaneous independent writes in Sheets and the application during the transition.
3. Reconcile source identities, attendance minutes, receipts, student credit/debt and teacher payables. Keep unresolved rows out of canonical posting and account for them in an exception register.
4. Import approved opening balances and nonfinancial history using the existing cutoff protections. Do not repost pre-cutoff lessons as new charges or accruals. Rehearse restart/replay and final-delta handling.
5. Verify balances and role-based workflows, obtain departmental acceptance and complete the remaining release gates before opening application operations.
6. Keep original Sheets as read-only historical evidence. Where exports are needed, use designated reporting copies whose contents cannot flow back into canonical attendance/payment records automatically.

## Recovery rule

If the application becomes unavailable after cutover, do not resume untracked operational writes in legacy Sheets. Preserve incoming work in a controlled recovery queue with stable event IDs, then reconcile it once service is restored. Any rollback must preserve and account for every application event accepted since cutover; an older Sheet snapshot is not a valid replacement for those events.

## Still unresolved

The cutover date/instant, full source reconciliation, invoice lifecycle, database constraints, task identities, recruitment integration and actual production-environment acceptance remain open as described in [the review](README.md). This decision resolves source ownership for attendance/payments; it does not certify production readiness or authorize immediate deployment.
