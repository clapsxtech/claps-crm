# ERP workflow usability refresh

## Changes

- Failed POST form input is restored only to the matching action and immutable hidden row identity. This prevents a shared field such as `name`, `student_id`, or `amount_inr` from leaking into unrelated forms. The failed action disclosure opens automatically. Existing hidden record IDs and CSRF tokens are never overwritten.
- Recovery uses Laravel's flashed input for the current validation response, not browser storage. Passwords and tokens are excluded from the JSON payload. JavaScript is required for this enhancement; server validation remains authoritative.
- Entity lookup retains an existing selection when a filtered page does not contain it, when the network fails, or when an invalid response arrives. A restored ID is explicitly labeled as a previous selection to verify, rather than inventing a person's name. Lookup failures offer a retry, and stale responses are invalidated as soon as a new query is typed.
- Validation has a focusable summary, field links, inline messages and programmatic invalid state. Lookup search, select and retry controls have separate labels. The layout adds a skip link, visible focus treatment and wrapping pagination.
- Attendance action queues use labeled stacked rows at narrow widths while preserving the native table at desktop widths. Status text remains visible instead of relying on color. Student names in the business registers and detailed student register link to their unit-scoped profiles.
- No new financial operation, external communication, import or permission is introduced. Existing server-side access, validation and idempotency rules still apply.

## Design research

Research on 2026-10-25 included the Figma dashboard template collection and Dribbble school-management dashboard example as product-layout references. No external asset or private application data was uploaded or copied. Implementation decisions prioritize the application's existing workflows and the following official accessibility guidance:

- Figma dashboard templates: https://www.figma.com/templates/dashboard-designs/
- Dribbble school-management example: https://dribbble.com/shots/22145909-School-management-dashboard
- GOV.UK validation/error summary: https://design-system.service.gov.uk/components/error-summary/
- W3C form labels: https://www.w3.org/WAI/tutorials/forms/labels/
- W3C reflow: https://www.w3.org/WAI/WCAG22/Understanding/reflow.html

The external inspiration pages were reviewed as references, not as evidence of this application's accessibility or browser compatibility.

## Fresh verification

`node --test tests/Deployment/erp-workflow-ui.test.cjs` passes nine focused tests, including real JSDOM field recovery, row isolation, CSRF preservation, checkbox restoration, summary-to-field focus and retryable/stale lookup behavior. This ran on Node 22.23.4 as well as the task runtime's Node 24.

All five changed/new Blade templates compile with Laravel’s BladeCompiler and the compiled PHP passes syntax lint. Full application view caching was not yet available at this check because the restored runtime was still missing the Toastr service provider.

A fresh headless Chromium fixture run was attempted but did not start: the initial sandbox denied the process socket, and the approved escalation path failed during environment setup with a read-only mount error. Consequently, responsive CSS and the queue markup have contract coverage, but this change does not claim fresh visual, viewport-overflow or end-to-end browser acceptance. Prior browser acceptance documents describe earlier work and must not be treated as verification of this refresh.

Before hosting acceptance, verify real rendered pages at 1440px and 320px: queue action buttons remain visible, no page-level horizontal overflow occurs, and a failed form reload opens the correct form, restores the selection, and moves focus from the error summary to its field. Also verify JavaScript-disabled forms still submit and display server errors.
