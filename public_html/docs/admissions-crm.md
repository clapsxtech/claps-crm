# Integrated admissions and CRM

The former `claps-crm` workflow is now an ERP business-unit module at
`/erp/units/{unit}/business/admissions`.

## Ownership model

- `erp_leads` is the admissions pipeline and keeps enquiry history, assignment,
  source, demo/follow-up state and the eventual link to a student.
- `erp_lead_activities`, `erp_demos`, `erp_lead_reminders` and
  `erp_lead_transfers` keep the CRM timeline and workflow state in the same
  unit-scoped database.
- A joined lead points to one canonical `erp_students` row. The conversion
  transaction never creates a second CRM student record.
- Initial payments go through the existing finance service and are stored only
  in `erp_ledger`; receipt reversal and reconciliation therefore remain shared
  with the rest of the ERP.
- Teachers, subjects, users, memberships and permissions continue to use the
  existing ERP tables. The standalone CRM's duplicate user/catalog store is
  not recreated.

## Workflow coverage

The module supports enquiry capture and explicit re-enquiry matching, lead
ownership, demos, follow-ups, reminders, transfer requests, lost/joined status,
conversion to a canonical student, and an optional first payment with a shared
receipt reference. All writes are unit-scoped, role-checked and audited.

## Database rollout

- Laravel: `database/migrations/2026_10_26_090000_create_erp_admissions.php`
- Cloudflare D1: `cloudflare/migrations/0006_admissions.sql`

Existing CRM data should be imported through a reviewed export/import job that
maps its snapshot records to these tables. It should match existing students by
an approved identifier before conversion; do not bulk-copy the CRM snapshot
into a second student or payment database.

## Sales workflow improvements (26 October 2026)

- Phone matches offer possible visible enquiries. Staff explicitly enter the
  existing enquiry ID or choose a separate student; shared parent phones do not
  automatically merge siblings. External IDs remain idempotent. Re-enquiries
  preserve original date, source and owner, with subsequent source/date in history.
- Follow-up filters show Today, Overdue and No next action for open leads using
  the business timezone, independently of manually created reminders.
- Clicking a lead opens paginated activity and demo history on the admissions
  page. History and dashboard counts follow the pipeline's unit/owner scope.
- Progress is derived in this order: Registered/Lost, active scheduled demo,
  attended demo, contact recorded, New. A repeat scheduled demo takes precedence
  over an earlier attended demo. Cancelled/no-show demos alone do not imply
  attendance. Contact outcomes and interest/obstacles remain separate.
- No migration is required. These changes are local until deployed.

Validation: admissions tests (6 tests, 66 assertions); D1 compatibility run of
admissions and teacher allocation (12 tests, 134 assertions).
