# Integration outbox

Result requests write the academic row and a unique submission outbox event in one database transaction. An unchanged, authenticated retry of the same UUID returns the original record; changed data returns HTTP 409. The UUID is required (old clients receive an actionable 422). Credentials are never part of the payload, payload hash, or error metadata.

Register `IntegrationServiceProvider` and configure `integrations.sheets.enabled`, `spreadsheet_id`, and `credentials_path`. Delivery is disabled by default. Enable and schedule `php artisan integration:deliver-outbox` only after local tests and deployment review. All feature tests inject a fake transport; no test needs a Google account. The Google SDK retry count is explicitly zero.

## Delivery states and manual reconciliation

- `pending`: never dispatched, or known not dispatched. Safe to try once later.
- `sending`: atomically claimed by one worker, with a claim token and timestamp.
- `sent`: provider acknowledged success; `provider_receipt` contains the returned updated range when available.
- `uncertain`: a dispatched request failed or the worker died/stalled. It might already exist in Sheets. Never automatically resend these events.

Use an authorized operator to compare an uncertain event's canonical payload, record timestamp, and available provider evidence against the configured sheet. If exactly one existing row can be identified, mark the event `sent` and record that range. Only if the operator can establish that no append happened may it be returned to `pending` for one delivery attempt. Otherwise leave it `uncertain` and investigate. Do not blindly replay all uncertain events. Do not modify the original payload or unique submission key. No reconciliation endpoint or automatic retry of ambiguous append is provided.

MARKLIST schema v1 has 12 columns: teacher name, teacher code, student name, student code, subject name, chapter, mark scored, total mark, exam mode, exam date (dd/mm/yyyy), remark, created timestamp. Admin/public requests now share this serializer; admin ID-based form inputs remain compatible through a boundary adapter, but new rows store canonical names plus stable codes rather than transient import IDs. Public subject storage uses the subject ID, not the display name. REVIEW schema v1 retains its 7-column order. No DOB column is exported. Historical Sheets rows with the old admin 11-column order require a reviewed offline cleanup, not an automatic rewrite.

Admin edits and deletes remain local-only, matching the legacy application. They do not append another Sheets row or silently overwrite a prior export. A future keyed remote update/reconciliation flow is required for edit synchronization.
