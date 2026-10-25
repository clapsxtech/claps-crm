# Cloudflare-only deployment

The app runs as PHP/Laravel in Cloudflare Containers, behind a Worker at
`erp.clapslearn.in`. D1 (`clapslearn-erp`) owns the database, including sessions.
R2 (`clapslearn-erp-uploads`) owns uploaded profile photos. No external database
or external hosting account is used. Optional Google/Microsoft integrations still
require their own credentials when enabled; migration does not replace those services.

## Transaction compatibility

Laravel uses interactive transaction callbacks, reads its own uncommitted writes,
and relies on nested savepoint rollback. D1 exposes atomic statement batches,
not an interactive SQL transaction protocol. The adapter therefore uses a
**disposable local SQLite working copy**, protected by an exclusive process lock.
It validates the copy against D1's revision on connection. A missing or stale copy
is reconstructed from a paginated D1 snapshot; start/end revisions must match.
The copy is never treated as durable storage.

Business SQL executes against the working copy. Successful mutations are journaled.
At commit, the Worker sends an atomic D1 batch containing a revision assertion,
the mutations, and a commit receipt. D1 revision triggers cover every application
table, including sessions. An unexpected revision rejects the entire batch.
Local commit and successful HTTP responses occur only after D1 confirms the commit.
A lost response is retried with the same random transaction ID; the receipt prevents
duplicate application. Unconfirmed outcomes fail closed and require a new request.
Nested savepoint rollback also removes the corresponding pending mutations.

This preserves tested existing Laravel behavior without making transactions no-ops.
The revision guard provides database-wide optimistic conflict detection, not MySQL
row locks. The PHP process lock serializes use of the working copy in one container.
A second container or an operator write can cause a conflict; it cannot silently
replace newer data. Do not disable revision triggers or mutate adapter metadata.

## Limits and operations

- Each atomic commit accepts at most 900 mutation statements and each expanded
  statement must be below 95,000 UTF-8 bytes. Oversized operations fail without
  committing a partial batch. Large imports need sizing/rehearsal before use.
- Mutation values must be deterministic and bound explicitly; SQL random/time
  expressions are rejected. Existing application timestamp bindings are supported.
- A cold/stale replica requires a database download in pages. This is appropriate
  for the current single-container design, not a claim of unlimited throughput.
  Monitor latency and database size before scaling or large imports.
- Apply schema changes through versioned D1 migrations. Generate new migration SQL
  deliberately; do not rerun `export-d1-schema.php` against an existing production
  schema. New application tables must have INSERT/UPDATE/DELETE revision triggers.
- The initial SQL in `cloudflare/migrations/0001_initial.sql` came from all Laravel
  migrations. The usual sample seeder (which contains a default password) was not used.
- R2 upload requests are authenticated internally; public profile image URLs retain
  their existing `/uploads/profile_photo/...` format. Unknown commit outcomes retain
  a newly uploaded photo rather than deleting an image that D1 may reference.
- Container replacement discards only the working copy/cache. D1 and R2 remain.
- Under the Durable Object scheduling policy, an already-running instance must be
  restarted to use a newly deployed image or updated environment secrets.

## Verification

```sh
npm test
npx tsc --noEmit
php vendor/bin/phpunit
D1_COMPAT_TEST=1 php vendor/bin/phpunit
```

The compatibility suite executes existing feature tests through the adapter against
an isolated SQLite implementation of D1's batch boundary. It is not a remote-D1 test.
`tests/Cloudflare/integration.php` separately exercises the actual local D1/R2 runtime:
payment replay, balances, rollback, restart recovery, stale writes, idempotent retry,
failed-batch atomicity, authentication, and R2 upload/read/delete.

Run its storage-only Worker with `tests/Cloudflare/wrangler.jsonc` on port 8790,
using `.wrangler/d1-tests` as the persistence directory. Test credentials and data
are local only. Never point that test Worker or synthetic test script at production.
