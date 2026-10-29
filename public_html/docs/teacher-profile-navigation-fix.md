# Teacher profile navigation fix — 26 October 2026

Opening a teacher candidate previously re-rendered the whole directory and placed the selected profile below all 25 directory rows. The URL changed, but the first screen appeared unchanged.

The recruitment template now renders either the directory or the selected profile. A selected profile appears immediately with a **Back to teacher profiles** link. Directory filters and pagination are preserved when opening a profile and returning. Query pagination is constructed separately from the route's `{page}` parameter so `?page=2` cannot replace the `teacher-recruitment` route segment. Existing permissions and profile mutations are unchanged.

## Verification

- Recruitment and identity-review feature tests: 5 tests / 55 assertions passed on SQLite and again with the D1 compatibility adapter.
- The production PHP 8.2 container compiled the updated Blade template successfully.
- Live browser verification with the HR test account: opened candidates 60150 and 41146, confirmed the profile heading appears immediately without directory rows, and used the return link to reopen the directory. No candidate records were edited during verification.
- Production database readiness passed after the prepared container image was restarted.

## Deployment scope and rollback

Only `resources/views/erp/teacher-recruitment.blade.php` and generated compiled views changed in the runtime image. The image inherits the exact previous production image. Worker module content, bindings and static asset configuration were compared and confirmed unchanged. No database migration, source import or account change was part of this fix.

- Previous Worker version: `40f8b743-f5b4-4c2e-8e0f-e4da8250bd03`
- Deployed Worker version: `5395e3a4-ac9e-46cd-99d9-f1f559429676`
- Patched image: `registry.cloudflare.com/74ad8d10da1568cedacdd379a246cefc/claps-profile-fix@sha256:80630ccd740cf9ede847885c23cca9a2a18d364b51d3bde5b0d1555881fc45e8`

The initial restart still served the old layout because image preparation had not completed. After explicitly preparing the image to `ready` and restarting again, live browser verification passed. Future image-only version uploads must prepare the image before restarting; a successful version upload and readiness response alone do not prove the new UI is serving.

To roll back, deploy the previous Worker version at 100%, then use the documented authenticated container restart and verify the site. Private release and rollback metadata are retained outside the repository under `~/.codex/private/clapslearn-erp/releases/teacher-profile-20261026/`.
