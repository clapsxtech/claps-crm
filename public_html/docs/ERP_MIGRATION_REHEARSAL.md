# Explicit opening dues and isolated migration rehearsal

## What is implemented, and what is not approval

Schema v2 extends the existing normalized migration envelope with mandatory teacher opening snapshots. It does not read Sheets, infer pay from planned/attended hours, recreate classes, prove historical salary payments, transfer money, or authorize any real-data import. All verification fixtures are fabricated. There are no new migration upload or editing routes, and no new database tables.

An opening row is the amount agreed at UTC midnight on the approved cutoff. It is not an event that happened at that time. Keep the original accounting evidence and source-export manifests privately; do not commit real or sanitized business extracts, credentials, or reconciliation outputs to the source repository.

## Version and amount contract

- Existing schema v1 remains student-only. It neither requires nor creates teacher openings and must not be interpreted as zero salary due for all teachers.
- Schema v2 requires one explicit student opening for every student and one explicit teacher opening for every teacher, even when zero. Person identity is `(owner_type, owner_id)`; matching student/teacher codes are not interchangeable.
- Teacher opening `amount_paise` is a native JSON integer from 0 through 1,000,000,000 inclusive (INR 10,000,000 maximum). This is an operational import safety bound, not a newly invented business entitlement.
- Positive teacher amount means payable to the teacher. Negative means an unsupported advance/overpayment situation and is held for accounting review. Strings, fractions, booleans, unsupported currencies and out-of-range values are rejected rather than converted.
- Student signed opening semantics remain unchanged: positive credit, negative debt. No historical session is charged or accrued again.
- All openings have `currency: "INR"`, a stable `external_id`, their owner's explicit unit, and `as_of` equal to the cutoff. A teacher belongs to exactly one unit. No automatic cross-unit sharing is introduced.
- Every mapped row and its complete import envelope is digest-bound. Object-key ordering is normalized; list order is retained. Source/cutoff identity and stable mapping IDs prevent duplicate imports. Any changed amount, source metadata, status, database target, eligibility or cutoff needs a new reviewed digest, not a silent replay.

The teacher-specific row below is fabricated, and must be included alongside the matching teacher row and all other required references. It is not an executable real-data import:

```json
{
  "type": "opening_balance",
  "external_id": "SYN-TEACHER-OPENING-1",
  "unit_code": "SYN-AA",
  "owner_type": "teacher",
  "owner_id": "SYN-TEACHER-1",
  "amount_paise": 150000,
  "currency": "INR",
  "as_of": "2026-10-01"
}
```

The resulting ledger row has `kind=opening_balance`, a teacher owner, no student owner, no attendance, an immutable migration event key, and the original batch actor. Reports show it as other adjustments and net payable, not newly earned salary or a completed payment. Later already-paid salary records can reduce the current payable through the existing recording workflow; importing the opening does not record those payments for you.

## Isolated operator sequence

1. Obtain approval for the exact source export, opening amounts/signs, cutoff, target environment, administrator and intended action. A valid preview is not that approval. Resolve negative teacher advances separately rather than changing their sign or dropping the teacher.
2. Use a disposable non-production database and separate filesystem/configuration. Ensure all relevant tables use transactional storage; rehearsal verifies SQLite or MySQL-compatible InnoDB and refuses unsupported engines. Keep Sheets and Google service-account integrations disabled, outbound transports on local test sinks, schedulers stopped, and production credentials absent. Rehearsal only uses direct local database operations, but integration isolation remains required.
3. Create/verify the synthetic destination units, subject catalog and active unit-admin memberships. Build an explicit schema-v2 envelope with approved statuses, rates, teacher eligibility, stable IDs, opening amounts (including zeros), source manifest and exact target database. Source inventory arithmetic alone does not supply these approvals.
4. Run the read-only default preview and review every blocking diagnostic, per-type count and opening total:

   ```sh
   php artisan erp:migration-import /absolute/local/synthetic-v2.json
   ```

5. For an authorized synthetic rehearsal only, explicitly enable the existing import feature in the isolated configuration (`ERP_MIGRATION_IMPORT_ENABLED=true`). Do not change a production configuration. Copy the exact preview digest and approved target/cutoff/admin into the rehearsal command:

   ```sh
   php artisan erp:migration-import /absolute/local/synthetic-v2.json \
     --rehearse --confirm-digest=EXACT_PREVIEW_SHA256 \
     --cutoff=YYYY-MM-DD --target-database=EXACT_DISPOSABLE_DATABASE \
     --actor=EXISTING_UNIT_ADMIN_ID
   ```

6. Require exit 0, `rehearsed=true`, `rolled_back=true`, `writes=0`, matching counts/totals, and `reconciliation.reconciled=true`. For a fresh fixture batch, `attempted_writes` must be positive. `replayed=true` means the batch already existed and no inserts were exercised. Do not mistake the reconciliation inside the rolled-back transaction for a committed import.
7. Verify the destination contains no newly persisted people, eligibility, assignments, openings, history, mappings, batches, or audit rows. A clean rehearsal discards its audit insert too. Keep the operator's private report as evidence; the command itself writes no report files. Repeat from the original envelope to confirm it is still a fresh preview.
8. A separately approved real commit uses `--execute` instead of `--rehearse`; rehearsal is not permission to do so. All original digest, cutoff, explicit target, active per-unit administrator and disabled-by-default execution gates remain. Production execution retains its additional deliberate opt-in; production rehearsal is never allowed.
9. After any separately approved commit, use the same exact envelope to reconcile, without enabling imports:

   ```sh
   php artisan erp:migration-import /absolute/local/approved-v2.json --reconcile
   ```

   Require the exact matching committed batch, `reconciled=true`, and zero writes. Independently compare source financial evidence and per-person balances before cutover. The tool certifies correspondence to the approved input, not the correctness or completeness of that input.

## Reconciliation and immutable provenance

Reconciliation verifies source/cutoff/digest, stored manifest and approved summary, mapped row digests, target table/identity, owning batch provenance, and actual destination fields. Opening amounts, owner IDs, unit, kind, currency, event/reference, cutoff timestamp, actor and lack of reversal are checked. Historical records must match their exact archive identity, assignment, time, actual minutes and status, with no financial effect. Assignment rates and linked owners/subjects, explicit eligibilities, person fields and teacher unit membership are compared as well. Unexpected batch mappings and missing targets fail closed.

The check recomputes mapped-record counts and opening totals, not the sum of all later operational ledger entries. For example, an approved INR 1,500 opening remains an INR 1,500 import snapshot after recording an already-paid INR 500 payment; current net payable is INR 1,000. Reconciliation does not overwrite either.

A legitimate later change to an imported name/status/assignment is still reported as snapshot drift. Review it; do not force a replay or edit the source digest to hide it. This code exposes no mutation or deletion route for migration provenance and never repairs mismatches automatically. Direct database-administrator tampering is outside an application's immutability guarantee.

## Rollback limits and stop conditions

- Rehearsal rolls back successful inserts as well as failures, preserving an enclosing transaction/savepoint. Unsupported/nontransactional tables are refused before writes.
- InnoDB auto-increment counters can advance despite rollback. Use a disposable database: zero persisted business rows is not a guarantee of byte-for-byte database identity or gap-free IDs.
- A real import is atomic and is reconciled before commit; an insertion or reconciliation failure rolls back the entire import. There is no destructive "undo committed migration" feature. Restoring a backup or designing compensating entries requires separate review, especially after later operational activity.
- An exact replay performs no new writes only when source and destination still reconcile. Missing or drifted records are an error, not permission to recreate or overwrite them.
- Stop on any blocked source identity, duplicate opening, existing owner ledger, unsupported teacher advance, changed digest, actor/unit mismatch, target mismatch, invalid provenance, or incomplete history. Do not weaken the checks to make the import pass.
- Historical starts strictly before UTC cutoff stay in the archival path. They cannot re-enter live attendance submission, student charges or teacher accrual, including lessons that cross midnight. Opening provenance itself preserves this boundary.

## Repeatable synthetic checks

Run the repository's normal isolated test configuration, without a production `.env` or cached production configuration:

```sh
php vendor/bin/phpunit --do-not-cache-result --filter 'ErpMigration'
```

`ErpMigrationOpeningDuesTest` exercises teacher amounts/zeros, statements, digest and source identity, owner separation, cutoff duplication, successful/failing rollback, post-write reconciliation failure, provenance/destination drift, historical posting guards, later payment separation and CLI mode/privacy checks. Existing preview/import/cutoff tests remain. Native MySQL-compatible verification is coordinated with the repository's isolated harness; SQLite passing alone is not proof of deployment-engine, concurrency or browser acceptance.
