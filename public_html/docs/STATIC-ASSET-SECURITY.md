# Static asset security review (2026-10-25)

Scope: script/link references in all Blade views, referenced local browser asset
headers, official upstream advisories, and a separate audit of the identified old
versions. This is not a penetration test and does not establish exploitability.

## Findings and fixes

| Previously loaded asset | Concrete finding | Resolution |
| --- | --- | --- |
| jQuery UI 1.10.3 | Datepicker XSS; affected `<1.13.0` (CVE-2021-41182) | No widget callers found; removed global include and obsolete JS file |
| jQuery Validation 1.19.3 | ReDoS and XSS advisories below | No validation-plugin callers found; removed include and obsolete JS file |
| DataTables 1.10.24, both local copies | XSS, affected `<1.11.3` | Replaced both copies with locked 1.13.11; Bootstrap 4 adapter matched |
| JSZip 3.6.0 | Prototype pollution and ZIP path traversal | Replaced with locked 3.10.2 |
| Moment 2.29.1 CDN | ReDoS and Node-specific locale traversal | Replaced with local locked 2.31.0; 2.30.1 also had a newer traversal advisory |
| GSAP 3.1.1 on error pages | Prototype pollution, affected `<3.6.0` | Replaced with compatible 3.12.7 distribution |
| pdfmake 0.1.70 + old virtual font bundle | Unmanaged old export runtime outside npm audit | Replaced with locked 0.2.23 and its matching fonts; synthetic PDF generation tested |
| Floating daterangepicker CDN | Build could change without review | Local locked 3.1.0 |
| jQuery 3.6.0 / Bootstrap 4.5.2 report CDNs | Duplicate older runtime outside lockfile | Local 3.7.1 / 4.6.2 bundle (includes Popper) |
| Duplicate Select2 / Toastr in admin footer | Reloaded bundled plugins; Toastr replaced safe options | Removed duplicates; retained bundled plugins and explicit `escapeHtml: true` |
| Magnific Popup, Slick, color picker, demo AdminLTE | No caller markup found; unused global plugins | Removed includes and obsolete JS copies; no unsupported vulnerability claim made |

The build now copies the required standalone browser distributions from exact
locked npm packages. DataTables Buttons 1.7.0, Responsive 2.2.7, their Bootstrap 4
adapters, and ion-rangeslider 2.3.1 retain their existing APIs but are now tracked
in the dependency audit rather than loose files. All application script CDNs
were removed; Google Fonts CSS remains an external font service.

## Upstream evidence

- jQuery UI: https://github.com/jquery/jquery-ui/security/advisories/GHSA-9gj3-hwp5-pmwc
- Validation: https://github.com/advisories/GHSA-ffmh-x56j-9rc3
- Validation: https://github.com/advisories/GHSA-j9m2-h2pv-wvph
- Validation: https://github.com/advisories/GHSA-rrj2-ph5q-jxw2
- DataTables: https://github.com/advisories/GHSA-h73q-5wmj-q8pj
- JSZip: https://github.com/advisories/GHSA-jg8v-48h5-wgxg
- JSZip: https://github.com/advisories/GHSA-36fh-84j7-cv5h
- Moment: https://github.com/moment/moment/security/advisories/GHSA-wc69-rhjr-hc9g
- Moment: https://github.com/advisories/GHSA-8hfj-j24r-96c4
- GSAP: https://github.com/advisories/GHSA-6g8v-hpgw-h2v7

A library version matching an advisory does not establish that this application
exposes the vulnerable call. For example, Moment's filesystem traversal applies
to Node locale loading, not the browser. Removing or updating known affected
versions avoids depending on that distinction for release hygiene.

TinyMCE 5.10.2 and the eval-source-map Popper development bundle also had no
callers anywhere in views or other public scripts and were physically removed.

## Remaining boundaries

- Bootstrap 4 is EOL. AdminLTE 3.2 CSS/JS remains intentionally vendored and is
  outside npm audit. See ASSET-BUILD.md for provenance and migration limitations.
- pdfmake's transitive crypto-js and jpeg-exif packages emit maintenance/deprecation
  warnings. They have no reported advisory in the release audit, but maintenance
  status is an additional reason to plan the next export-library migration.
- No claim is made that zero reported npm advisories proves these old UI majors
  supported or vulnerability-free. A coordinated Bootstrap 5/AdminLTE migration
  remains a separate compatibility project.
- Other unreferenced legacy assets in `public/js` (old theme animations, carousel,
  map/contact helpers) were not loaded by any current Blade view. Their existence
  is not proof of execution; they have not received a comprehensive security
  review and are not represented by npm audit. They should not be reintroduced
  without review. The known affected unused global plugins listed above were
  physically removed, not merely unloaded.
- DOM tests verify table creation, PDF/ZIP output, date ranges, and widget APIs;
  they do not replace browser visual testing or production database testing.
