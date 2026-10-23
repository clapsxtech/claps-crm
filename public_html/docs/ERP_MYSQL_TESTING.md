# Opt-in ERP MySQL-compatible concurrency tests

Run `tests/Integration/erp-mysql-concurrency.php` explicitly. It is not part of the normal PHPUnit test discovery and never runs against the application's configured database or `.env`.

## Safety requirements

Use a dedicated local disposable database containing only synthetic test fixtures. This harness drops and recreates ERP tables in that database for each scenario. It refuses any host other than `127.0.0.1`, requires an explicit TCP port of 1024–65535, a database name matching `erp_probe_[a-z0-9_]+`, and a reset confirmation equal to that database name.

Prerequisites:

- PHP 8.2 with `pdo_mysql` and `proc_open`, including in spawned PHP child processes.
- Installed application Composer dependencies.
- A verified local MySQL-compatible InnoDB server and pre-created disposable database.
- A database user authorized to create/drop tables only in that disposable database. Do not use production credentials.

Set these explicit environment variables in your shell:

- `ERP_PROBE_HOST=127.0.0.1`
- `ERP_PROBE_PORT=33306` (replace with the verified isolated server port)
- `ERP_PROBE_DATABASE=erp_probe_concurrency`
- `ERP_PROBE_USER` with the verified disposable database user
- `ERP_PROBE_PASSWORD` explicitly, even if deliberately empty
- `ERP_PROBE_ALLOW_RESET=erp_probe_concurrency`
- Optionally `ERP_PROBE_ARTIFACT_DIR` to a writable directory outside the repository; default is the system temporary directory plus `/erp_mysql_probe`.

Run from the application root:

```sh
php tests/Integration/erp-mysql-concurrency.php
```

The script resolves the application root relative to itself; there are no workstation-specific paths or stored credentials. Ensure `PHPRC` and any required runtime library environment are inherited when your PHP setup needs them: child workers use `PHP_BINARY` directly. The server and all workers must share the same network namespace.

## Scenarios and assertions

Every scenario uses real independent connections in separate PHP processes. Each session explicitly uses `REPEATABLE READ` with a 10-second InnoDB lock timeout. File barriers coordinate simultaneous starts and controlled interleavings.

1. Two simultaneous submissions of the same lesson return the same attendance ID and create one row.
2. Two import batches with distinct references for the same attendance create two snapshots but exactly one student charge.
3. A cancellation holding the teacher lock commits while attendance waits; the waiting submission must reject the now-cancelled lesson and create no charge.
4. A correction commits while a new import waits; the ledger must retain the original charge, compensating reversal, and corrected charge. No teacher payroll accrual may appear.

The harness prints JSON and returns zero only on success. It writes `last-result.json` and per-worker results/stdout/stderr under the artifact directory, never into the repository. Barrier waits are bounded; failures identify the unsuccessful scenario. Test artifacts contain synthetic fixture data only. No runtime outputs, credentials, or database files should be committed.

## Observed evidence and limits

The isolated four-scenario harness passed on MariaDB `10.11.18-MariaDB-0+deb12u1` using InnoDB and `REPEATABLE READ`. This is evidence for that specific server/version and exercised interleavings, not universal MySQL certification or permission to deploy. Re-run against the intended deployment engine/version and configuration before release. The probe covers the ERP migration and operations only; it does not establish that all legacy application migrations are compatible with that server.
