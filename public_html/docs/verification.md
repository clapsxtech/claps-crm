# Verification record: 23 October 2026

Baseline: `amnasahamed/logs.clapslearn.com`, main commit `1e60e6b82257dd4c3cdf600ed1a6bd694ed6fe2e`.

## Automated results

- PHP 8.2.33 / Laravel 8.83.27 / PHPUnit 9.6.20 / SQLite: **128 tests, 658 assertions; 127 passed, 1 explicitly skipped; no failures or errors**.
- Node deployment and interaction regressions: **18 passed**, including Worker request forwarding, delete feedback/retries, staff form validation/duplicate submits and modal lifecycle.
- Independent review probes: **6 passed, 12 assertions**, covering conflict/credential ambiguity, unchanged replay after topic rename, credential non-flashing, successful-login redirect and actual attendance edit rendering. These are separate from the committed 128-test suite.
- Actual rendered Blade JavaScript syntax and PHP syntax checks pass. Real jQuery/JSDOM interaction checks pass. These are not visual-browser tests.
- Composer metadata validates, including the BCMath platform requirement. Node 22.23.4 production asset compilation and TypeScript typecheck pass; configuration and locked Wrangler requirements agree.

The one PHP skip is the real native file-cache persistence test. The WASM environment cannot acquire a shared lock on a named host file. The test contains a capability probe and must run without skipping on the intended native PHP deployment.

## Regression coverage compared with audited behavior

| Audited behavior | New verified expectation |
|---|---|
| Missing/unknown teacher credential accepts attendance/results | 403/422 and no business row or event |
| Forged assignment and client amounts accepted | Exact assignment required; authoritative rate snapshot and server decimal total |
| Unverified logged-in report users see all rows and DOB | Administrator or verified teacher scope applied before rows/counts; credentials omitted |
| Public timetable HTML embeds private schedules | No embedded directory/rows; POST verification and teacher/day projection |
| Save-before-Sheets failure causes duplicate retry | Atomic business/outbox commit; authenticated replay; 409 on changed payload; uncertain delivery quarantined |
| Staff creation writes absent columns | Additive guarded schema, validated provisioning, no privilege escalation |
| Broken search/delete feedback | Actual searchable columns; consistent supported-delete contract; unsupported destructive buttons removed |
| Container URLs, Node mismatch, unreadable optional CA | Public-origin HTTP-kernel/Worker tests; Node22 alignment; PHP-worker CA ownership |
| Destructive or malformed snapshot imports | Complete validation before transaction; stable reference IDs; local attendance overwrite guard; POST/CSRF |

Public registration is intentionally still pending its account-policy decision and is not counted as fixed. No end-to-end live Google, Cloudflare deployment, MySQL or visual mobile certification is claimed.

## How to reproduce on a normal development machine

```sh
composer install
nvm use
npm ci
php vendor/bin/phpunit --do-not-cache-result
npm run test:deployment
npx tsc --noEmit
npm run production
sh -n cloudflare/start-container.sh
```

Use an isolated test configuration. The committed PHPUnit configuration uses an in-memory SQLite database, synthetic accounts, array mail/session stores and disabled Sheets. No production secrets or customer data are needed. The repository previously lacked this regression coverage; these are newly added tests, not claims about a preexisting suite.
