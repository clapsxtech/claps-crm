# ERP migration preview contract

This release supplies a dry-run validator only. There is no upload route, importer, database write, Sheets access, attendance approval, ledger posting, or external communication. Even a valid result has `can_import: false` and `writes: 0`. Do not use successful validation as authorization to import production data.

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

Historical lessons must never be replayed through the live attendance approval/charging pipeline. Opening balances represent the agreed cutover snapshot; historical attendance is archival context only. This implementation does not persist either.

Before any future importer is implemented, explicitly agree the cutover date and balance sign policy, reconcile each source opening balance against finance records, resolve destination duplicates and assignment overlaps with existing records, check teacher eligibility, detect conflicts with existing destination IDs, confirm access permissions and backups, and approve a separate transactional import plan with stable source-to-destination mappings. The current validator does not establish those operational approvals, reconcile history to balances, or validate against existing production rows.

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
