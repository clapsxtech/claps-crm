# Cloudflare deployment

Production: **https://erp.clapslearn.in**

- Account: `74ad8d10da1568cedacdd379a246cefc` (`clapsxtech@gmail.com`)
- Worker: `clapslearn-logs`
- Runtime: Laravel/PHP 8.2 on Cloudflare Containers
- Database: Cloudflare D1 `clapslearn-erp`
- Uploads: Cloudflare R2 `clapslearn-erp-uploads`

All runtime hosting and persistent application storage are inside Cloudflare.
No MySQL host or R2 API key is required. Workers Paid and R2 are enabled.
The Worker uses direct D1 and R2 bindings; internal container requests require
an encrypted `INTERNAL_TOKEN`. `APP_KEY` is also an encrypted Worker secret.
Never commit either secret or the initial administrator credentials.

## Deploy changes

From `public_html`, with Node 22 and Docker running:

```sh
export PATH="/opt/homebrew/opt/node@22/bin:$PATH"
colima start --profile claps-erp --cpu 4 --memory 6 --disk 40 --vm-type vz --vz-rosetta
export DOCKER_CONTEXT=colima-claps-erp
npm ci
npm test
npx tsc --noEmit
php vendor/bin/phpunit
D1_COMPAT_TEST=1 php vendor/bin/phpunit
npm run cf:assets
npx wrangler deploy --dry-run
npm run cf:deploy
```

Public CSS, JavaScript, fonts and images are staged into `.cloudflare-assets`
and served directly by Cloudflare. `npm run cf:deploy` rebuilds this bundle before
deployment. Never publish the entire public directory, including PHP or uploads.
Production PHP OPcache requires the container restart described below after changes.

The custom domain and HTTPS application URL are in `wrangler.jsonc`. Wrangler
manages the hostname and certificate. Use the domain-owning Wrangler login;
the connected Cloudflare plugin may be signed into a different account.

The Durable Object scheduling policy starts the configured image when an instance
boots. Deploying alone does not replace an already-running container. Restart the
instance explicitly when changing images or secrets, then verify `/health`, login,
ERP pages, and uploads. Never delete the D1 database or uploads bucket to redeploy.
The restart endpoint is `POST /__cloudflare/restart`, authenticated with
`Authorization: Bearer <INTERNAL_TOKEN>`. Load the token from private secret storage;
never put its value in shell history or source control. The restart endpoint starts the new container and waits for `/ready` to verify
database recovery before returning `ready: true`. Allow up to 180 seconds for
this operation. The `/health` endpoint is a process-readiness check; `/ready` also checks the
database without creating sessions; live
sign-in and a database-backed screen are separate database checks.

## Database changes

The initial schema is in `cloudflare/migrations/0001_initial.sql`, generated from
Laravel migrations. Apply future reviewed D1 migration files with:

```sh
npx wrangler d1 migrations apply clapslearn-erp --remote
```

Do not run Laravel's normal `migrate` against the D1 adapter. New tables need
revision triggers; changes to the schema or a Time Travel restore require a
container restart to discard its working copy. Read [the D1 architecture and
limits](docs/CLOUDFLARE_D1.md) before editing the database layer.

## Optional integrations

Google service-account and Sheets delivery settings remain disabled unless
explicitly configured. They require valid Google credentials and the correct
spreadsheet. Microsoft OAuth and email delivery likewise need their provider
configuration. Hosting the app in Cloudflare does not supply those credentials.
Do not use the old sample database seeder: it contains a default password.
