# Deployment hardening and verification

This document supplements `CLOUDFLARE.md`. No production deployment, database change, credential rotation or external integration write is performed by these source changes.

## Supported build/runtime

- Use Node **22** (`nvm use`, then `npm ci`). The root npm engine and lockfile now agree with the locked Wrangler version's Node 22 minimum. Node 20 is not supported by this Wrangler version.
- The Docker build compiles frontend assets in a Node 22 stage, then runs Laravel with PHP 8.2/Apache. Copying `artisan` into the asset stage lets Laravel Mix detect its public directory correctly; the generated manifest and font files are included with JS/CSS.
- PHP development verification requires Composer dev dependencies and the extensions in the application's Composer/PHPUnit requirements. The production image installs dependencies without dev packages.

## Public origin and proxy trust

Cloudflare's TCP transport accepts HTTP URLs, not HTTPS URLs. The Worker changes only the request scheme for this transport, preserving the public hostname, path, query and body. It strips visitor-supplied forwarding chains and origin/path overrides before constructing its own forwarding metadata.

For an HTTPS `APP_URL`, the Worker injects `PUBLIC_ORIGIN` into the container. Laravel uses this deployment setting to restore HTTPS only when the actual HTTP `Host` matches that exact configured HTTPS origin. It does not use visitor-supplied `X-Forwarded-Host` to make that decision. This ensures generated login forms, redirects and asset URLs use the public HTTPS origin without guessing the container transport's source IP or trusting every proxy.

- Set `APP_URL` to the final HTTPS origin, for example `https://logs.example.com`, with no credentials, application path, query or fragment.
- The canonical origin is a **single-origin deployment setting**, not a client identity or authorization mechanism. An alias hostname will not inherit the configured origin. Configure the final domain before checking generated URLs.
- The container must remain reachable through its Worker transport, not exposed directly as an internet-facing HTTP server.
- HTTP `APP_URL` values, such as local `http://localhost:8787`, leave canonical HTTPS mode disabled and use non-secure cookies only for that explicitly configured HTTP origin. Do not use an HTTP origin for production.
- `TRUSTED_PROXIES` is optional and empty by default. For another known reverse-proxy deployment, configure only its verified immediate-source IPs or narrow CIDRs, separated by commas. Wildcards, hostnames, catch-all CIDRs and invalid masks are rejected. Do not guess ranges or set `*` merely to make HTTPS detection work.
- Explicitly trusted proxies can supply protocol, port and client IP. Forwarded host/path overrides are never trusted. An unlisted peer's forwarding headers are stripped.
- The default Cloudflare path deliberately does **not** infer client identity from an unverified forwarded-IP header. Application rate-limit keys must not assume the container peer IP distinguishes visitors.

Official transport reference: https://developers.cloudflare.com/sandbox/previews/serve-previews-on-their-own-hostnames/

## Cache and rate limiting

The single-container deployment uses the file cache rather than an in-memory array cache. The startup script creates `storage/framework/cache/data` and grants the PHP worker ownership before Apache starts. This preserves counters across separate PHP requests within the running container. Container replacement resets local cache state.

For horizontal scaling or durable counters across container replacement, use a shared supported cache such as Redis or a properly migrated database cache, then test rate limiting across instances. Do not turn on array caching in production: each PHP request would receive a fresh counter.

Do not describe limits keyed by the container's peer IP as per-visitor limits. Legacy teacher-code/date-of-birth verification remains a weak identity mechanism; identity-keyed attempt limits can reduce guessing but carry a temporary targeted denial-of-service tradeoff. They are not a replacement for proper authenticated teacher accounts.

## Secrets and database TLS

- Google OAuth client ID and client secret no longer have checked-in fallback values. OAuth use requires explicitly configured `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`; service-account integrations use their separately configured credential file.
- Removing a fallback does not revoke a previously exposed credential or remove it from repository history. An authorized operator should review exposure and rotate/revoke it with the provider if appropriate. Existing credentials have not been changed by this patch.
- A provided MySQL CA is decoded to `/tmp/mysql-ca.pem`, mode `0600`, owned by `www-data:www-data`. PHP can read the CA while other container users cannot. `MYSQL_ATTR_SSL_CA` points at that file before Laravel configuration is cached.
- Never commit `.env`, `.dev.vars`, service-account JSON, database certificates or provider secrets. Avoid printing secrets in test reports or deployment logs.

## Optional module settings and integration delivery

The Worker forwards these optional deployment settings to Laravel:

- `ERP_BUSINESS_ID` defaults to `1`; set it to the installation's existing organization identifier. This does not make legacy academic tables multi-tenant safe.
- `SHEETS_SYNC_ENABLED` defaults to `false` and `GOOGLE_SPREADSHEET_ID` defaults to empty. Enable sync only after credentials, the exact target spreadsheet and the authorized delivery workflow have been verified.
- `GOOGLE_SERVICE_ENABLED` defaults to `false` for the older Google package's service-account configuration. Set it explicitly if an authorized legacy integration needs that package behavior.

These settings can be supplied as Worker variables alongside the encrypted secrets. Updating settings does not update a running container's environment; restart/redeploy through the authorized deployment workflow.

Queued Sheets events require an operational runner for `php artisan integration:deliver-outbox --limit=100`. Enqueuing an event is not confirmation that Sheets was updated. This source patch does not create an external scheduler or execute integration delivery. Establish and verify the runner before claiming synchronization is operational. Review `uncertain` outcomes against the destination before attempting any resend, because the remote append may already have succeeded.

## Verification commands

```sh
nvm use
npm ci
npm run test:deployment
npx tsc --noEmit
npm run production
composer install
php vendor/bin/phpunit
sh -n cloudflare/start-container.sh
```

The Node 22.23.4 production asset build and TypeScript check were run successfully in the isolated development workspace. Docker image execution is still required.

The focused PHP tests are `ProxyOriginTest` and `DeploymentConfigTest`. The real file-cache persistence test explicitly skips on runtimes without native filesystem locking (including PHP-WASM); it must pass on native PHP before rollout. They exercise Laravel's HTTP kernel, a real login page, generated assets/forms/redirects, spoofed headers, hostname mismatches, explicit proxy trust and rejected configuration. The Node suite executes the Worker forwarding path with a controlled container transport and the delete handlers with a jQuery-shaped event/AJAX harness, including network errors and repeated submission.

Before an authorized rollout, build and run the Docker image, verify the configured public HTTPS origin, confirm session and cache persistence across requests, confirm PHP can read the database CA, test real MySQL TLS and R2 uploads, and test the separately authorized Google integration. These live checks require the target deployment and its valid credentials; passing local regressions does not establish them.
