# Mobile sales CRM

The admissions workspace is a dedicated CRM for phone use. Desktop retains a wider card grid. Sales staff keep the existing ownership and unit permissions.

- Pipeline cards show stage, source, owner, hot priority and the next conversation date. Six colour-coded stages come from lead status, demo history and contact activity; counts and filters use the same definition.
- Bottom navigation opens Pipeline, Today, Demos and Reports. Reminders and Transfers are visible above the content. Menu retains the wider ERP workspace.
- Call and WhatsApp actions are available directly on a lead card. WhatsApp links require an international number, or an Indian number explicitly marked IN; ambiguous legacy numbers link to contact editing.
- Selecting a lead opens its own detail page, with follow-up, demo booking, reminders, contact editing, hot priority, handoff notes, registration, lost/reopen actions, transfers and activity history.
- Follow-ups offer channel buttons and date shortcuts based on the business timezone. Existing form recovery restores values and opens the failed form after validation errors.
- Demos include all accessible enquiries, independently of pipeline pagination. Matched demos continue through Teacher matching; legacy demo updates retain date, time and status controls.
- Today includes local-date follow-up queues and overdue/current reminders. The Reminders screen includes future reminders. Pending transfers are visible to sender and recipient; contact details remain protected until acceptance.
- Reports preserve sales activity, attribution and campaign reporting; tables become labelled records on phones.

No offline synchronisation, automatic message sending or push reminders are introduced. Call/WhatsApp links open the phone's corresponding app. All writes use the existing server validation, permissions and audit records.

## Verification

- 488 PHP tests / 4,131 assertions pass against SQLite and the D1-compatible adapter.
- 59 JavaScript/deployment tests pass; TypeScript and the production asset build pass.
- Synthetic browser fixtures cover eight CRM views plus HR, HR profile, administration and operations at 320, 390, 768 and 1440 pixels. Collapsed and expanded forms have no page-level horizontal overflow; date shortcuts, disclosure navigation and drawer focus work without browser errors.
- Tests: `tests/Feature/ErpSalesCrmTest.php`, `tests/Feature/ErpAdmissionsTest.php`, `tests/Feature/ErpMobileUiTest.php`, `tests/Assets/erp-mobile.test.cjs`.
- Generate synthetic HTML with `ERP_MOBILE_PREVIEW_DIR=/tmp/claps-mobile-preview php vendor/bin/phpunit --filter 'ErpSalesCrmTest|ErpMobileUiTest'`.

## Release

Deployed to **https://erp.clapslearn.in** as version `829bd4a9-e6d2-4d31-8600-acb5c36a6cf3` at 100% on 26 October 2026. Built as an overlay on mobile release `21e4be85-fd83-42a3-8299-bad6ac1e0d68`. Only the admissions controller, pipeline helper, CRM/shared UI files and two compiled assets are included. Unrelated Sheets changes are excluded. No schema migration or import is required.

Private release artifacts: `~/.codex/private/clapslearn-erp/releases/sales-mobile-crm-20261026/`.

Candidate container: `registry.cloudflare.com/74ad8d10da1568cedacdd379a246cefc/claps-sales-crm@sha256:e3f7fa1c448cd6ddaaaf52829e78d4629cf1019a58e67dab673d9f9056913b2e`.

Live verification passed for sales, HR, admin and superadmin. The sales checks opened Pipeline, Today, Demos, Reminders, Transfers, Reports, lead details and the new-enquiry screen. Served CSS and JavaScript matched the release hashes; mobile navigation, form shortcuts and responsive widths passed. Verification changed no business records.

The restart endpoint initially returned HTTP 500 during container startup. Subsequent `/health` and `/ready` calls returned HTTP 200 with `status: ok`, and authenticated checks confirmed the new CRM templates. Do not treat the restart response alone as deployment verification.

Rollback: activate version `21e4be85-fd83-42a3-8299-bad6ac1e0d68`, restart the application instance, and verify readiness and the restored UI. No database rollback is needed.
