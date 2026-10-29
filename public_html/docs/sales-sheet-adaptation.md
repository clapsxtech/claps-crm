# Sales sheet adaptation assessment

Reviewed 26 October 2026 against the local working tree. This is a code review,
not confirmation of deployed behavior. The Google Sheet was read only; no data
was imported. The assessment below was followed by the local implementation
documented in `admissions-crm.md`: explicit duplicate choice, follow-up filters,
lead history, progress labels, and preservation of attribution on re-enquiry.
The remaining proposals below are future work.

Source: https://docs.google.com/spreadsheets/d/1HyBNVCh6I2bJ2q4pMnZb9_KdiX-Mah6hyfeQ6Grl34U/edit

Evidence is limited to tab metadata, headers, and sampled rows/formulas in
WHATSAPP, DEMO STATUS, SEND, REFERENCE, and DATA. Cross-tab record linkage and
complete data quality have not been verified.

## Existing foundation

The admissions migration and `app/Modules/Erp/Services/Admissions.php` already
provide stable internal lead IDs, external IDs, source, owner, activity records,
follow-ups, reminders, transfer requests, multiple demos and student conversion.
`TeacherAllocation.php` provides timed demo matching, feedback and regular-class
allocation. Extend these records rather than building a second sales database.

## Prioritized changes

| Priority | Gap found in local code | Proposed adaptation and acceptance condition |
| --- | --- | --- |
| 1 | `Admissions::createLead` automatically reuses an active lead based on phone or its last ten digits when no external ID is supplied. | Show possible duplicates and let authorized staff explicitly choose an existing enquiry or a different child. Siblings sharing a parent's phone retain separate lead IDs; repeated external IDs remain idempotent. Candidate results must respect lead visibility. |
| 1 | Follow-up dates appear in pipeline rows, while the reminder queue reads a separate reminders table. | Add Today, Overdue and No next action views based on open leads' next follow-up dates. A follow-up entered without a separate reminder must appear when due. Apply the same owner/unit visibility to rows and counts. |
| 1 | Activities are recorded, but the admissions controller/view do not expose the lead timeline. | Add an accessible lead detail view with dated calls, notes, outcomes, assignment changes, demos and conversion. Preserve the lead history after student conversion; enforce access on direct URLs. |
| 2 | Pipeline filtering exposes only LEAD, JOINED and LOST; interest is a separate free-text field. | Display operational progress separately from contact outcomes: New, Contacted, Demo scheduled, Demo attended, Registered. Keep No reply, Fees issue and Next year as outcomes/reasons. Define precedence for multiple demos, cancellations, reopening and conversion before implementation. |
| 2 | Source is editable; repeat enquiries update the enquiry date and can replace ownership. | Preserve original source/date and explicitly log each later enquiry's source/date and any reassignment. Add structured referral references without requiring a referred student to exist yet. |
| 2 | The sheet's SEND tab prepares messages; the inspected admissions UI offers no equivalent composer. | Provide an editable demo message using student, teacher, subject, local time/timezone and meeting link. Opening or copying a message must not count as sent; record staff confirmation separately. |
| 3 | Existing demo feedback and student conversion provide a handoff foundation. | Present an admission checklist containing agreed fees, successful demo, feedback and owner; use existing conversion/allocation services so student and payment records remain canonical. |

## Implementation sequence

Start with sibling-safe duplicate review, due follow-up views and lead history.
Then introduce operational stages, attribution history and message preparation.
Keep schema changes compatible with both Laravel and D1 migration paths.

Focused verification should cover shared-parent siblings, re-enquiries, access
boundaries, due dates in the business timezone, multiple demo outcomes and
conversion retaining sales history. Do not import the sheet or send messages
as part of these changes without a separate user request.
