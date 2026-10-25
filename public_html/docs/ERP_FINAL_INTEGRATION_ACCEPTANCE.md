# Reconstructed integration checkpoint — 25 October 2026

This is a freshly reconstructed candidate based on published main commit `8e44585bbc27ff01fb49363a7894ed8e5dfb01f4`, not the unavailable unpublished checkpoint from 24 October. Its source and evidence stand on their own. Earlier 404/406-test counts and prior native concurrency/browser outcomes must not be attributed to this candidate.

## Implemented

- Laravel 12, supported framework middleware, compatible permission migration, stable locked PHP dependencies, and PHPUnit 11. Composer resolves against PHP 8.2.30 to match the existing PHP 8.2 container target, rather than silently selecting PHP 8.3/8.4-only dependencies on the verification machine.
- Container asset stage uses the new esbuild/Sass pipeline. Its Composer stage installs required bcmath before dependency installation. Generated provider caches and local test artifacts are excluded from source/build inputs. Docker execution itself remains unverified.
- Unit-admin-only student overview with independently paginated named assignments, agreements, lessons, attendance, student ledger, payment requests, follow-ups and tasks. This is read-only; it neither changes financial state nor exposes teacher earnings as student balances.
- Operator-only, redacted read-only health checks and a disposable synthetic SQLite recovery drill. These do not configure production monitoring, backup destinations or recovery access.
- Action-and-record-scoped form recovery, safe lookup selection retention and retry/race handling, accessible error/focus behavior, responsive queue presentation and profile links.
- Reproducible frontend build, patched locked dependencies, refreshed actually used legacy assets, local script references and removal of unused vulnerable plugin copies. See `ASSET-BUILD.md` and `STATIC-ASSET-SECURITY.md` for provenance and residual support risks.
- Manual-only synthetic verification workflow. Publishing does not trigger this workflow, and no workflow run is claimed.

## Fresh verification

Runtime used: PHP 8.4.24, PHPUnit 11.5.57, Laravel 12.70.1, SQLite and Node 22.23.4.

- Full PHP regression: 359 tests, 3002 assertions, passed. Randomized order with seed 20261025 also passed.
- Node regression: 37 tests passed, including real compiled assets, DataTables initialization, PDF/ZIP generation, date picker behavior, UI state handling and Docker/PHP-target contract guards.
- Locked dependency installation, production asset build, TypeScript checking and Blade compilation passed.
- Composer and npm audits reported no known vulnerabilities in their respective locked dependency graphs at verification time. This is not a claim that every vendored asset is audited, supported or free of vulnerabilities.
- Synthetic recovery drill passed all 14 checks, including corrupted-copy rejection, overwrite prevention, schema/row integrity, exact paise balances and idempotent replay.
- Independent static review of the new access, financial projection, UI recovery and operational boundaries found no remaining blocking issue after the Docker-stage bcmath fix.

## Explicit limits and release gates

- The actual PHP 8.2 executable and Docker image were not run here. Locked PHP requirements and container/build contracts were checked; execute the image before deployment.
- Native MySQL/MariaDB concurrency was not rerun: this executor denied server sockets. Existing native harness coverage is retained, and the manual workflow supplies a disposable MariaDB service for a future authorized run.
- No visual browser acceptance or authenticated end-to-end session is claimed. Synthetic HTTP renders passed, but the cloud browser disallowed local file URLs and this executor could not expose a socket-backed preview. DOM/JSDOM checks are not a substitute for real-device visual acceptance.
- Bootstrap 4 is end-of-life. The explicitly vendored AdminLTE 3 distribution is outside npm audit. Maintenance warnings and remaining legacy support risks are documented, not concealed by a zero-audit count.
- Production hosting, credential grants/rotation, real source mapping, opening totals, cutoff approval, migration digest/target approval, live reconciliation, monitoring ownership and staff acceptance remain separate operational gates.
- No deployment, real student-data import/export, source Sheet write, external message, payment or production restore was performed. Existing unit-admin/teacher/task boundaries remain; finer role permissions require an approved role/action/data-access matrix.

Publication should use a non-force expected-head update, preserve the published parent, and compare every final intended path/hash against the remote tree. Save the complete source and verification manifest persistently before uploading the candidate.
