# Sales, HR and administration on mobile

The shared operations shell now provides safe-area-aware bottom navigation, larger phone controls, readable form labels and single-column forms. Shortcuts reuse the sidebar's permission-filtered pages. Desktop navigation and native tables remain available.

- Sales: Today, Overdue and No next action shortcuts; search above the pipeline; optional status filters; tap-to-call contacts; enquiry history links jump to the selected record. New enquiries and reports open on demand below the work lists.
- HR: name/phone/code search and stage selection stay visible; additional filters expand when needed and remain open when active. Teacher profiles become labelled cards on phones. Application entry and registration guidance follow the directory.
- Administration: Add staff, Find account, Manage teams and Create team shortcuts. Creation forms start collapsed. Account actions retain their password and permission requirements.
- Simple operational tables use one set of records/forms with labelled mobile cells, preserving table semantics. Complex reports and historical DataTables retain horizontal scrolling.
- Record lookups in closed forms wait until every containing disclosure is open, reducing initial requests. Validation opens disclosures containing invalid fields; existing server-error input recovery remains in place.

## Verification (26 October 2026)

- Production asset build passed.
- Targeted admissions, recruitment, administration, access, allocation, tuition and mobile feature tests passed.
- Asset and deployment JavaScript tests passed, including lookup deferral, mobile table enhancement, menu focus return and validation disclosure recovery.
- Chrome checks against synthetic Laravel-rendered pages covered sales, HR directory, HR profile, accounts and operations at 320, 390, 768 and 1440 pixels. No page-level overflow with disclosures closed or expanded; no JavaScript page errors. New enquiry and navigation interactions were checked.
- Screenshots are under `artifacts/mobile-operations/` in the repository root. These contain synthetic records only.

Run PHP and JavaScript checks from `public_html`:

```sh
php vendor/bin/phpunit --filter 'ErpMobileUiTest|ErpAdmissionsTest|ErpTeacherRecruitmentTest|AdministrationWorkflowTest|TeamAccessTest|ErpTuitionHttpTest|ErpTeacherAllocationTest'
npm test
npm run build
```

For optional synthetic HTML to use in a browser check, set `ERP_MOBILE_PREVIEW_DIR` to a temporary directory when running `ErpMobileUiTest`. Serve its pages together with the built `public/css` and `public/js` assets. Browser checks used Chrome emulation; physical iOS/Android devices were not tested. The mobile update was deployed on 26 October 2026; see the production release below.


## Production release

Live at https://erp.clapslearn.in on 26 October 2026.

- Worker version: `21e4be85-fd83-42a3-8299-bad6ac1e0d68` (100% traffic).
- Previous version: `5395e3a4-ac9e-46cd-99d9-f1f559429676`.
- Image: `registry.cloudflare.com/74ad8d10da1568cedacdd379a246cefc/claps-mobile-operations@sha256:2be27e95c46732ec8ab9bf817b464f4b3460e2f929d12cca54fd32ad8a90e112`.

This release overlays only the mobile templates, styles, shell script and asset manifest on the previous production image. Pending Sheets work in the checkout was excluded. Production Worker code, bindings, runtime settings and unrelated static scripts were preserved. No database migration, import or business-record edit was performed.

Full local validation passed: 483 PHP tests / 4,065 assertions on SQLite and again with the D1 compatibility adapter, plus TypeScript checking. The image compiled its Blade templates with production PHP 8.2.

Authenticated live checks passed for sales, HR, team admin and superadmin at 320, 390, 768 and 1440 pixels. These checked the mobile navigation, enquiry/application disclosures, teacher profile navigation and page overflow. Live CSS and JavaScript hashes matched the release. Service and database readiness both returned 200 / ok. Verification changed only its own sign-in sessions.

The first restart still served the previous templates. Reapplying the prepared version with immediate Durable Object activation and restarting switched the instance. That restart request returned a temporary 500, after which both health endpoints recovered and authenticated checks confirmed the new UI. Verify the rendered UI and asset fingerprint, not only a deployment or readiness response.

Private release configuration, file hashes and image overlay are retained under `~/.codex/private/clapslearn-erp/releases/mobile-operations-20261026/`. For rollback, activate the previous version at 100%, restart the instance, and verify the previous UI and database readiness.
