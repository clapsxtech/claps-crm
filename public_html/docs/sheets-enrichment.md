# Sheets-to-ERP enrichment

The operational Google Sheets are treated as read-only source snapshots. CSV
exports are staged under `storage/app/erp-imports/<snapshot-date>/` (ignored by
Git) and never written back to Google Sheets.

## Preview

```bash
php artisan erp:sheets-import storage/app/erp-imports/2026-10-26
```

The preview reports file hashes, normalized student/teacher/task counts and a
snapshot digest. It uses `CCA` student codes and `CT` teacher codes as stable
identities. Re-running the preview is safe.

Execution requires an explicit source band. Use `--band=AA` for the AA unit or
`--band=BB` for the BB unit; the importer never silently places both bands in
one unit. The current first-tab task export is AA-only, so BB task history is
not inferred from it.

## Explicit import

Execution requires an approved preview digest, target unit, actor, database
name, and the existing migration-import feature flag:

```bash
php artisan erp:sheets-import storage/app/erp-imports/2026-10-26 \
  --unit=<UNIT_ID> \
  --actor=<ADMIN_USER_ID> \
  --band=AA \
  --confirm-digest=<PREVIEW_SNAPSHOT_DIGEST> \
  --target-database=<DATABASE_NAME> \
  --execute
```

`--overwrite` is an additional explicit choice. Without it, existing canonical
students, teachers and tasks are preserved and only new records are inserted.

The importer enriches canonical students and teachers, creates student/teacher
profiles, imports task history and checklist items, and creates task templates.
It does not blindly import the monthly attendance/hours summary, rendered
timetable summaries, duplicate-review rows, or invoice templates. Those require
a second mapping step to canonical lessons, attendance, and ledger/invoice
records, so the files remain in the reviewed snapshot manifest without creating
financial or attendance postings.

Production D1 imports are committed in bounded, restartable batches below D1's
900-mutation atomic-batch limit. Re-running the same snapshot is safe because
students, profiles, teachers, templates, and tasks use stable identities or
source hashes.

The canonical tables are created by
`2026_10_26_110000_create_erp_canonical_layers.php` and D1 migration
`0007_canonical_layers.sql`; D1 revision triggers are repaired by
`0008_revision_triggers_for_erp_modules.sql`.
