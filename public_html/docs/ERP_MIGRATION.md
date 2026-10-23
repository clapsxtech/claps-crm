# ERP migration preview contract

The original preview service and command remain read-only inventory validators. This release also supplies a separate, disabled-by-default guarded import service and local CLI described below. There is no upload route, Sheets access or external communication. A valid dry-run is not authorization to import production data. Only synthetic fixture imports have been exercised.

## Calling the service

Call `App\Modules\Erp\Services\MigrationPreview::preview($rows, $references)` on a service instance. Both arguments are in-memory arrays. The caller supplies trusted reference catalogs as `unit_codes` and `subject_codes`, each an array of exact stable strings. Also require `cutoff_date` (YYYY-MM-DD) and `source_manifest` containing stable `source_id` and exact UTC `exported_at`. Missing cutoff/manifest or an empty batch is blocking. Missing catalogs fail closed for referenced values. The validator does not query a database for existing records.

Every row has `type` and `external_id`. IDs are case-sensitive strings of 1–128 characters, starting with an ASCII letter or digit, then letters, digits, underscore, dot, colon or hyphen. Spreadsheet row numbers and names are not IDs. Choose stable, source-issued identifiers before migration. IDs must be unique within each type. Referenced students, teachers and assignments must be present exactly once in the same batch; input ordering does not matter.

Supported normalized rows:

- `student`: `external_id`, `unit_code`, `name`, IANA `timezone`, `registered_on` (YYYY-MM-DD), nonempty string `grade`, `syllabus`.
- `teacher`: `external_id`, `unit_codes` containing exactly one unit (AA and BB teacher pools remain separate), `name`, IANA `timezone`.
- `assignment`: `external_id`, `unit_code`, `student_id`, `teacher_id`, `subject_code`, `starts_on`, optional `ends_on`, `student_rate_paise`, `teacher_rate_paise`, `currency: INR`. Person references are external IDs, not database IDs. The student's unit and teacher's allowed units must match. Rates must be nonnegative native integers no larger than the schema's unsigned 32-bit limit. Dates are strict calendar dates; end cannot precede start. Inclusive date intervals may not overlap for the same student/subject, even with different teachers.
- `opening_balance`: `external_id`, `unit_code`, `owner_type` (`student` or `teacher`), `owner_id`, signed native integer `amount_paise`, `currency: INR`, `as_of` date. Only one opening balance per unit/person is permitted, and every `as_of` must equal the batch cutoff. Positive student balance means credit available; negative means outstanding debt. Positive teacher payable means money owed to the teacher. These are proposed opening snapshots, not receipt/payment transactions.
- `historical_attendance`: `external_id`, `unit_code`, `assignment_id`, `starts_at` in exact UTC `YYYY-MM-DDTHH:MM:SSZ`, integer `actual_minutes` from 0–1440, `status` (`delivered`, `cancelled`, `absent`), `financial_effect: none`. Duplicate assignment/start-time pairs are blocked, even with different external IDs. Historical UTC dates must fall within assignment dates and strictly before the cutoff date. Cutoff is interpreted as UTC midnight. Explicit amount, charge, pay, ledger, approval or financial-posting fields are blocked.

Money values must already be integer paise: `12000` represents INR 120. Floats, booleans and numeric strings are rejected rather than rounded or coerced. Convert source currency carefully before calling this service. Aggregate overflow is a blocking error.

## Output and review

The result includes `valid`, `dry_run`, `can_import`, `writes`, per-type `counts`, `issues` and `totals`. Every issue is blocking, identifies a one-based input row, field and machine-readable code. Counts describe received recognized row types, including invalid rows. If any issue exists, totals are null so a partially valid batch cannot be mistaken for reconciled totals. A valid batch reports student opening balance and teacher opening payable sums; historical financial effect is always zero. Preview does not echo names, contacts or original records into reports.

Historical lessons must never be replayed through the live attendance approval/charging pipeline. Opening balances represent the agreed cutover snapshot; historical attendance is archival context only. The pure preview does not persist either. The guarded importer below stores historical rows only in its separate archive and requires explicit opening-balance approval.

Before any real import is attempted, explicitly agree the cutover date and balance sign policy, reconcile each source opening balance against finance records, resolve destination duplicates and assignment overlaps with existing records, check teacher eligibility, detect conflicts with existing destination IDs, confirm access permissions and backups, and approve a separate transactional import plan with stable source-to-destination mappings. The current validator does not establish those operational approvals, reconcile history to balances, or validate against existing production rows.

## Synthetic verification

Tests contain fabricated students and teachers only and require no database or Laravel application bootstrap:

`php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Feature/ErpMigrationPreviewTest.php`

Coverage includes order-independent references, exact totals, signed balances, malformed inputs, duplicate/ambiguous IDs, cross-unit references, duplicate balances and sessions, integer-paise enforcement, overflow, strict dates and forbidden historical financial replay. No real data or import is needed to run them.

## Local dry-run command

Save a normalized JSON object with two keys: `rows` (a JSON array following the row contract above) and `references` (an object with catalogs, cutoff and source manifest). Run:

`php artisan erp:migration-preview /absolute/path/to/synthetic-preview.json`

The command prints only the JSON validation summary, never the original personal rows or file contents. Exit status is 0 for a valid preview and 1 for blocking issues, missing files or malformed input. A successful command still reports `can_import: false`; it is not an import-ready authorization. Output may be captured by the operator for review, but the command itself writes no files or data.

Only readable local regular files are accepted. URL/stream wrappers are rejected. Input is limited to 5 MiB and 5,000 rows; oversize input fails rather than being truncated. Invalid JSON, missing envelope keys, malformed catalog shapes and structural errors produce machine-readable blocking codes without echoing sensitive values. Run the command in an isolated environment with integrations disabled. The wrapper does not fetch source data, send messages, approve attendance, or post opening balances.

CLI tests use temporary synthetic files and an isolated console command harness, without application/database bootstrap:

`php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Feature/ErpMigrationCommandTest.php`

## Guarded importer: code ready, real import not authorized

`MigrationImporter::preview(rows, references)` adds destination validation and a canonical SHA256 `digest` to the summary. It writes nothing. `execute(rows, references, expectedDigest, approvedCutoff, actorId, allowProduction=false)` is the separate transactional operation. Building this operation is not permission to execute it against business records.

Importer envelopes add mandatory fields beyond the inventory-only preview:

- `references.schema_version`: integer 1.
- `references.target_database`: exact configured destination database name, not a connection string or password. The service verifies the current database. SQLite fixture tests use their configured in-memory name.
- `references.subject_ids`: object mapping each subject code to an existing integer ERP subject ID. `references.subject_names` maps those same codes to the exact current subject names. Unknown or mismatched mappings block; subjects are not guessed or created.
- `references.teacher_eligibilities`: explicit approved rows with `external_id`, `teacher_id` (teacher external ID), `subject_code`, `grade`, `syllabus`. Every assignment must match an explicitly supplied eligibility for its student's grade/syllabus. Eligibility is not inferred from the assignment.
- Student `status`: explicitly `active`, `paused` or `stopped`. Teacher `status`: explicitly `active` or `inactive`. Assignment `status`: explicitly `active` or `ended`; ended assignments require `ends_on`. Values are preserved, not silently reactivated.
- Every student requires exactly one `opening_balance` row, including an explicit integer zero when the approved opening is zero. Missing balances never become guessed zeros. Only student openings are supported. Teacher salary dues, historical payroll opening balances, and proof of prior salary payments are not migrated by this importer.

All rows, reference catalogs, statuses, source-manifest metadata, eligibility declarations, target database and cutoff contribute to the digest. Object keys are canonically sorted; list order is retained. Changes require a new preview and approval of its new digest. Importer row fields are allowlisted, preventing unsupported fields from being silently dropped. Explicit rates remain mandatory. Imported student and teacher hourly rates must be native integer paise from 0 through 100000000, inclusive, matching the operational Scheduling/Finance maximum rather than merely fitting a database integer.

Default read-only usage:

`php artisan erp:migration-import /absolute/path/to/approved-normalized-envelope.json`

The following describes a manual administrative commit interface, not a command already run against real data:

`php artisan erp:migration-import /absolute/path/to/approved-normalized-envelope.json --execute --confirm-digest=EXACT_PREVIEW_SHA256 --cutoff=YYYY-MM-DD --target-database=EXACT_DATABASE --actor=EXISTING_ADMIN_ID`

Execution also requires configuration `erp.migration_import_enabled` to be boolean true (absent/false blocks). Production additionally requires `--allow-production`. That opt-in never bypasses digest, cutoff, destination, actor or unit checks. The actor must be an active member of the organization and an explicit administrator of every target unit. Merely running the CLI or supplying an administrator ID does not create an authorization grant. Operators must obtain the appropriate real-data approval before enabling or running it. Preview and commit accept only local regular files up to 5 MiB / 5,000 rows; remote URLs and stream wrappers are refused.

### Atomicity and provenance

The importer locks affected units in stable order, revalidates current destination conflicts inside a database transaction, and inserts the batch, source mappings, people, eligibility declarations, assignments, approved student openings and archived history atomically. Any failure rolls back all changes. It never overwrites unmanaged rows sharing a stable code. Changed mapped source rows, missing/moved mapped targets, overlapping destination assignments, and attempts to add another opening onto an existing student ledger block rather than merge silently.

`erp_migration_batches` records the source, cutoff, canonical digest, manifest, counts, totals and actor. `(source_id, cutoff)` is unique. An exact batch replay returns the original batch summary with zero new writes; changed payloads at the same source/cutoff are rejected. `erp_migration_mappings` records stable source/type/external-ID provenance and each canonical row digest. `erp_migration_history` is the separate append-only archival path exposed by this code: it has no mutation route and is never inserted into live lessons or attendance. Historical rows always have `financial_effect=none`; no live approval/import/payroll service is called.

Approved student openings become ledger entries of kind `opening_balance`, dated at UTC midnight on the explicitly approved cutoff. Positive means available credit, negative means debt. They are not receipts and do not claim payment occurred. No teacher opening, teacher accrual, historical lesson charge, payment, outbound message or transfer is generated.

### Verification scope

`ErpMigrationImporterTest` uses synthetic fixtures only: dry-run/no writes, exact canonical digest/replay, schema/target/cutoff mismatch, explicit subject/eligibility mapping, every-student zero-or-signed opening, explicit inactive/paused/ended statuses, no historical financial replay, no unmanaged stable-code overwrites, per-unit/deleted-actor denial, commit-time conflict revalidation and injected mid-import atomic rollback. The CLI test commits synthetic rows only within the isolated test database.

## Live attendance boundary after migration

Committed source mappings and their batch cutoff now prevent imported students, teachers or assignments from re-entering pre-cutoff attendance through live submission, student charging or teacher accrual. The boundary is UTC midnight on the approved cutoff; a lesson starting before it, including one crossing midnight, remains historical. Exact-cutoff starts can use the live workflow. Existing mistaken postings can still be corrected or reversed, but re-import/reapproval cannot recreate pre-cutoff financial effects. Invalid or ambiguous mapped provenance fails closed. This does not infer a cutoff from a report heading or export timestamp.

## Read-only source-shape CSV inventory

`php artisan erp:source-inventory /local/path/adapter_manifest_v1.json` reads only local CSV files beside a versioned manifest and prints diagnostics. A nonzero exit means blocking diagnostics, not a failed or partial import. The command has no execute mode and always returns `can_import=false`, `writes=0`. It does not manufacture normalized import rows from incomplete source data.

Manifest version 1 requires `source_id`, timezone-qualified ISO `exported_at`, explicit `cutoff_date` (null remains blocked), `student_openings_approved` and `sources`. Each source declares a simple local filename, SHA256, workbook/sheet identity, physical spreadsheet range (for example `D12:U12`), physical row count, timezone, header row count, kind and physical-letter column map. Kinds are `master`, `registration`, `attendance`, `due`, `timetable`, or `inventory`. Source URLs may be retained privately as provenance but are never fetched. Original timezone labels are preserved; the historical `Asia/Calcutta` label is recognized as the `Asia/Kolkata` timezone alias even when a stripped runtime omits backward aliases.

Required maps: attendance `attendance_id,teacher_id,student_id,date,hours,unit`; master `student_id,teacher_id,student_rate,teacher_rate`; due `prior,received,charges,current`; registration `student_id`. Optional `registered_on`, `starts_at` and `status` remain absent when source semantics are unknown. Timetable `expected_hours` is planning duration only, never substituted for actual attendance.

All selected sources must share one attendance identity namespace. Identical IDs across mirrored tabs count once, with equality checked on linked identities, date, exact minutes, unit, start and status. Never combine unrelated systems with coincident IDs under one source manifest. Conflicting IDs invalidate attendance totals. Any selected file failing structure, hash, row count or width validation makes the inventory incomplete and nulls attendance minutes rather than presenting partial totals as complete.

Money accepts canonical decimal strings with at most two fractional digits and converts to integer paise without floating-point rounding. Hours allow up to four fractional digits but must convert to exact whole minutes. Currency symbols, grouping commas, exponents, whitespace, excessive precision and fractional minutes are rejected. Source arithmetic is only an observation, not evidence of receipts, sign convention approval or a cutover opening. Teacher openings remain unsupported. Registration-date semantics, explicit status mappings, assignment effective dates, approved eligibility, exact attendance starts, cutoff and approved student openings must be resolved separately. No source-derived financial replay is authorized by this diagnostic.

Tests contain fabricated records only. Private/sanitized source extracts, mapping manifests and reconciliation results must remain outside source control.

This intentionally conservative inventory CLI currently always exits nonzero because teacher opening support is absent, even if CSV structure is complete. Inspect `inventory_complete` separately from import readiness. Limits: 100 sources, 10,000 physical rows per source, 50,000 declared rows per bundle, 5 MiB per CSV, 1 MiB mapping manifest. These are bounded diagnostic extracts, not a complete-population loader.
