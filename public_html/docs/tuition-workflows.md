# Tuition operations rules and rollout

The ERP keeps the timetable, reported teaching and financial postings separate. An enrollment remains the student–teacher–subject price agreement, with independent student and teacher hourly rates frozen on dated lessons. Existing integer-paise, minute-based calculations and explicit student charge imports remain intact. Sales and incentives are outside this change.

## Implemented workflows

- Teacher attendance captures actual minutes, optional changed start time and a reason. Teacher-local times are converted to UTC. Future teaching cannot be submitted, and a changed request cannot silently replay existing attendance. Late means submitted after the actual teaching day in the student's timezone; it is a flag, not a rejection or penalty.
- Cancelled slots remain unpaid and uncharged. Missing attendance is an unknown outcome, not proof of a missed class. The private operations page lists recent unresolved slots and shortened classes for makeup follow-up. Check replacement links before arranging makeup time; this is a follow-up queue, not an automated entitlement ledger.
- Admins and team managers can add private, append-only class notes and demo outcomes. Preference outcomes are Continue, Try another teacher, and Awaiting feedback. These records and HR concerns are absent from parent statements and teacher views/exports.
- Monthly parent statements show actual local teaching time, minutes and calculated student charges. They can be printed/saved as PDF and shared manually. They omit teacher pay and private notes. After actual sharing, admins record evidence and choose the UTC objection deadline. Bulk sharing rejects stale attendance snapshots.
- Submitted attendance cannot be approved for teacher accrual before parent acceptance or expiry of a shared deadline without dispute. Disputes require explicit recorded resolution or an attendance correction. Corrections reverse existing financial entries and reset parent review. Historical approved rows remain approved; no past earnings are automatically reversed. No scheduled auto-payment or automatic message sending is introduced.
- HR can record candidates, qualifications, subject/level preferences, languages and availability; append interviews with a 0–100 score, HTTPS recording link, strengths, concerns and Ready/Hold/Rejected decision. Ready does not automatically grant teacher access. Use existing explicit teacher onboarding, membership and subject eligibility.

## Deployment

1. Back up the production database using the existing deployment procedure.
2. Production uses Cloudflare D1. Apply `cloudflare/migrations/0005_tuition_workflows.sql` with `npx wrangler d1 migrations apply clapslearn-erp --remote`, deploy the compatible image, then restart the container as described in `CLOUDFLARE.md`. Do not run Laravel migrate against the production D1 adapter. The equivalent Laravel migration is `2026_10_25_180000_add_erp_tuition_workflows.php`, for conventional database installations. Do not serve new code against the old schema. Monthly review batches accept at most 200 rows to remain within the D1 adapter mutation limit.
3. Parent review is required by default (`ERP_PARENT_REVIEW_REQUIRED=true`). Existing test suites explicitly use the old approval policy; the new workflow tests turn review enforcement on and test its gates. Keep production enforcement enabled.
4. Admins/team managers open **Class follow-up, parent review and HR** from the business dashboard or operations workspace. The route is `/erp/units/{unit}/tuition`.
5. Agree a standard parent objection period operationally. The software requires an explicit deadline rather than inventing a default. All new private-screen UTC input labels are explicit.

## Remaining integration boundaries

Website applications and external sales CRM handoffs are not connected automatically: their source contract and access were not supplied. Candidate entry is currently internal/manual. Parent communication uses the existing manual channel, with recorded sharing evidence. HR scoring is a simple configurable-in-code 0–100 input, not a new hiring policy or ranking algorithm. Private-screen recent lists are bounded and disclose those limits. Incentives, repeated-demo pricing, automatic reminders, and automated makeup reconciliation remain separate work.

## Validation

PHP feature coverage includes actual timing, idempotency, parent deadlines, dispute blocking, corrections, scoped notes/interviews, HTTP role restrictions, clean parent statement rendering, and stale-statement rejection. Existing PHP, browser asset and deployment tests also pass. Production migrations and deployment were not run during implementation.
