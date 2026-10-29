# Migration, rollback and isolated-testbed checklist

## Decisions still pending

1. **Account creation policy:** administrator-created staff or public self-signup. The existing public registration route/controller has deliberately not been disabled or given new provisioning privileges without this answer. Its original schema mismatch remains a release blocker. Staff creation through the authorized administrator interface is repaired and tested.
2. **Attendance and payment source of truth — decided:** the user confirmed application authority after cutover, with Sheets retained as read-only history/exports. Follow [the source-authority decision](architecture-review/source-authority.md). Cutoff and reconciliation remain release gates. Local-submission snapshot replacement must continue to fail rather than erase accepted application records; do not bypass the guard or bulk-delete submission keys to make an import run.

The prepared changes must not be described as production-ready or fully resolved while these decisions remain open.

## Safe experimental deployment

- Use a separate Cloudflare Worker/container, database, storage bucket, hostname, secrets and access policy. Do not reuse the production database connection, Sheets target, OAuth configuration, email transport or object storage.
- Leave `SHEETS_SYNC_ENABLED=false` and `GOOGLE_SERVICE_ENABLED=false`, with no original spreadsheet credentials. The Worker explicitly forwards disabled defaults; snapshot reads and delivery are guarded. New local submissions may queue events but do not dispatch them.
- Keep email and other outbound services disabled or on a local test sink. Test cloning must not trigger actual messages, payments or writes to original Sheets. Review copied scheduled jobs and outbox rows before enabling any worker.
- The owner intends to clone/import representative data later. No source-data import or credential transfer has been performed or authorized by this implementation. Prefer sanitized data; protect any copied student information with least-privilege access and retention controls.
- Inspect imported business IDs, account status, stable codes, duplicate names, date formats, decimal rates and historical numeric result IDs before opening access. Unresolvable old IDs must be flagged for human reconciliation, not guessed.

## Additive database changes

Back up and restore-test the target database first. Run migrations only in the isolated testbed after approval. No production migration has been run.

- `2026_10_23_100000_create_integration_outbox_table`: adds the outbox and unique submission key. Existing result rows are **not** automatically enqueued or sent. Do not backfill old deliveries by guessing whether Sheets already contains them.
- `2026_10_23_110000_add_staff_profile_columns_to_users`: adds only missing nullable staff-profile columns. Existing deployments may already contain some of them. Its rollback intentionally retains these columns and their data because ownership cannot safely be inferred.
- `2026_10_23_120000_add_submission_identity_to_work_hours`: adds nullable UUID/hash fields with a unique UUID constraint. Legacy rows remain null. New records do not store DOB. Historical DOB data is not destructively purged by this patch; a separately reviewed retention cleanup may be appropriate.

The legacy string financial fields are retained to avoid unsafe destructive conversion. New writes are validated decimal strings. A later decimal-column migration requires profiling and explicit invalid-value reconciliation. The two existing rate fields are not renamed or conflated; the current UI's `hours × hour_payment` rule is preserved.

## Rollback

Stop integration runners and take a fresh database backup before rollback. Restoring the previous application can lose the new authorization and retry protections; do not expose it publicly without reviewing that risk.

Prefer an application rollback that retains additive schema and all outbox evidence. Dropping the outbox loses delivery/replay history; dropping attendance UUID/hash fields removes duplicate protection. Export and reconcile these records before any schema rollback. The staff migration's no-op down method is intentional. No automatic legacy-data backfill or destructive cleanup is included.

## Runtime verification before rollout

- Use Node 22 and PHP with BCMath; Composer now declares `ext-bcmath` and Docker already installs it.
- Install locked dependencies, compile assets, run all PHPUnit and Node regression tests, typecheck TypeScript and syntax-check the startup script.
- Build and run the actual Docker image. Validate MySQL strict-mode migrations, transaction/concurrency behavior, TLS CA readability, file/session/cache persistence and real browser/mobile flows. These were not established by SQLite/PHP-WASM tests.
- The file-cache persistence regression must pass on native PHP. It explicitly skips in PHP-WASM because named-file shared locking is unavailable there; the production implementation is not weakened to accommodate the test runtime.
- Confirm HTTPS form actions/assets and CSRF behavior on the exact test hostname. Unknown proxy headers remain untrusted.
- Only after a separately authorized integration setup, verify the correct test spreadsheet and runner. Laravel schedules the guarded outbox command every minute and snapshot imports every fifteen minutes when integration is enabled, but an external `schedule:run` runner is still required. This patch does not create one or execute live delivery.
- Review the previously tracked OAuth secret with its provider and rotate/revoke it if genuine. Removing the fallback does not remove Git history or revoke a credential. No rotation or credential generation was performed.
