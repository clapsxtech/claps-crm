# Page loading and cold recovery — 25 October 2026

## Changes

The PHP container now reaches the authenticated D1/R2 gateway through a host-specific
Cloudflare outbound intercept (`erp-storage.internal`) and a service entrypoint in
the same Worker. Database traffic stays on Cloudflare's internal path instead of
returning through the public application hostname. Existing token checks, primary
revision reads, commit guards and idempotency receipts remain in effect.

Cold database recovery fetches initial table pages in groups of eight, instead of
one network request per table. Each table is still paginated at 200 rows. The
start/end revision comparison, foreign-key validation and atomic working-copy
replacement still reject an inconsistent snapshot. No business data is cached
across users or served without authorization.

Blade templates are compiled into the container image rather than compiled on every
boot. `/ready` checks the database without creating sessions; `/health` remains a
process check. Container startup, scheduled monitoring and the authenticated restart
endpoint now wait for database readiness. The restart response confirms both
`restarted` and `ready`. Operators should allow up to 180 seconds for the restart
HTTP call, including platform provisioning; do not interpret a caller timeout as
proof that the restart failed. This does not promise instantaneous platform VM starts.

A `Server-Timing: app` header reports application middleware execution time including
session handling, so network time can be distinguished from application processing.
It contains no queries, identifiers or credentials.

## Validation

- 404 PHP tests / 3,548 assertions pass in normal and D1 compatibility modes.
- 50 JavaScript tests and TypeScript checking pass.
- Fourteen checks pass against local Cloudflare D1/R2, including batched recovery
  beyond 200 rows, unauthorized/metadata table rejection, exact balances, rollback,
  stale writes, commit replay and uploads.
- A regression test verifies recovery retries when a write changes the revision
  during a batched snapshot. Readiness failure responses contain no exception details.

## Verified live results

Worker version: `17b96157-56ec-418e-837f-4cc710cbc813`.
Container image: `sha256:36b68fa2ca9e17a6bbb068694517b55503cb699f5ca3fa3db93a34f6b8dfee9e`.

The authenticated restart completed with `restarted: true, ready: true` in
26.16 seconds, including container replacement and database reconstruction. Earlier
release checks exceeded their 45-second first-health timeout; that is a lower bound,
not an exact prior restart measurement. Scheduled readiness checks keep the normal
instance active. Platform replacement can still require a cold start.

The same bounded three-session, 54-request benchmark completed with zero failures.
Median tools responses improved from 2.14 to 1.78 seconds (17%), and the dashboard
from 2.03 to 1.66 seconds (18%). Home improved from 1.84 to 1.70 seconds (8%).
Tools p95 increased from 2.50 to 2.92 seconds, while dashboard p95 improved from
2.82 to 2.59 seconds; network variability remains visible in these small samples.
These are total workstation HTTP timings, not browser Core Web Vitals or proof of
large-data capacity. Full results are in ERP_COLD_START_RESULTS_2026_10_25.json.

Live login, both team dashboards/assignment screens, tools, administration,
operations, migration and security checks passed. Operational diagnostics report
healthy status. Browser verification confirmed the tools page renders correctly.

