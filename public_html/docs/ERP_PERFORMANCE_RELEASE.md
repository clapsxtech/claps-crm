# Performance release — 25 October 2026

## Changes

- Cloudflare serves a staged allowlist of public CSS, JavaScript, fonts and images directly. These requests no longer wait for the PHP container. PHP files, uploaded user files, source maps, hidden files and symlinks are excluded. Browser caches revalidate using the static service's ETags so deployments do not leave stale unversioned assets.
- The container checks readiness once per boot instead of before every request. A stopped container or forwarding error invalidates readiness. Failed requests, especially mutations, are never automatically replayed.
- PHP OPcache stores compiled code in memory. Timestamp validation is disabled only in the immutable production container; deployments must restart it. Realpath caching reduces repeated filesystem resolution.
- Database revision checks, commit acknowledgements, authentication, session revocation and role checks are unchanged. Authenticated HTML is not shared-cached.

## Deployment

Use `npm run cf:deploy`; it builds production frontend assets and stages the static allowlist before Wrangler builds and uploads the container. The generated `.cloudflare-assets` directory is excluded from the container image. Never configure Wrangler to publish the entire `public` directory, which includes the PHP entry point and may contain user uploads.

After deploying, invoke the authenticated container restart documented in CLOUDFLARE.md. The first start may take longer while the disposable database working copy is reconstructed. Static assets are available independently of container startup.

## Validation

50 JavaScript tests pass, including readiness reuse, re-probing after container stop, no mutation replay after forwarding failure, and static packaging exclusions. TypeScript checking passes. Eleven account, MFA and migration tests (129 assertions) pass.

The live comparison uses three independent authenticated sessions and 54 measured GET requests per run: health, home, tools, a team dashboard and the main CSS/JavaScript. Each endpoint has nine observations. Curl totals include connection setup and network time; these are bounded workstation measurements, not browser Core Web Vitals or large-data capacity certification.

## Production release

Worker: `b90f796d-9f45-4d09-85b6-398ea010c5ee`.
Container image: `sha256:8b97acecd4ebcc52190984477abbcc04ce56709d45df6208cc52e7b528ed9e97`.
OPcache extension, enabled flag, 192 MB allocation and immutable-code configuration
were verified directly in the built image. No schema changes were required.
Cloudflare served the public stylesheet successfully while the application restarted.

## Live results

All 54 post-release measured requests succeeded. Medians and p95 values below
are seconds, measured with the same script and default networking on both runs.

| Endpoint | Median before | Median after | p95 before | p95 after |
| --- | ---: | ---: | ---: | ---: |
| /health | 1.59 | 1.59 | 3.73 | 2.74 |
| / | 1.88 | 1.84 | 3.16 | 2.92 |
| /tools | 2.35 | 2.14 | 3.20 | 2.50 |
| /erp/units/1/business/dashboard | 2.27 | 2.03 | 3.46 | 2.82 |
| /css/erp.css | 1.44 | 0.28 | 2.94 | 1.28 |
| /js/erp-shell.js | 1.08 | 0.28 | 2.27 | 1.31 |

CSS median improved 81%; JavaScript median improved 74%. Dynamic-page medians
improved modestly (home 2%, tools 9%, dashboard 11%); p95 improved 8–22%.
These nine-observation endpoint samples are not statistically strong capacity claims.
Network diagnostics showed occasional connection establishment exceeding one second.
Separate IPv4 diagnostic requests measured health at 0.54 seconds and login at
0.94 seconds; those use different networking and must not be presented as the
before/after improvement. Full browser Core Web Vitals were not measured.

The browser rendered tools correctly and navigated to the team dashboard and back.
Live login, both team dashboards/assignment screens, administration, operations,
migration and account security passed. Operational JSON reported healthy status.
Conditional asset requests returned 304 with zero body bytes. The first health
request during planned container replacement still exceeded the 45-second test
limit; the next check and subsequent warm tests succeeded. Cold recovery time and
realistic large-data capacity remain separate limitations.
