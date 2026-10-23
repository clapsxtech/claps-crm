# Cloudflare deployment

This application runs on Cloudflare Containers behind a Worker. It intentionally remains a Laravel/PHP application; Cloudflare Workers alone do not provide a PHP runtime.

## Architecture

- The Worker in `src/index.ts` terminates public HTTP traffic and forwards requests to one named Laravel container.
- Apache and PHP 8.2 run the Laravel application on port 8080.
- A managed, publicly reachable MySQL service stores application data and sessions.
- An R2 bucket is mounted at `public/uploads`, preserving uploaded profile photos across container restarts.
- Google service-account JSON is injected as an encrypted Worker secret and decoded only when the container starts.

Cloudflare Containers require a Workers Paid plan. The database is not hosted by this repository; provision MySQL with TLS from a provider that accepts outbound connections from the container.

## 1. Prerequisites

Install Node.js, Docker Desktop (or another Docker-compatible engine), and authenticate Wrangler:

```bash
npm install
npx wrangler login
```

Create an R2 bucket named `clapslearn-uploads` and an R2 API token scoped to Object Read & Write for that bucket.

## 2. Prepare the database

Back up the existing database, configure local database environment variables for the target MySQL database, and run:

```bash
php artisan migrate --force
```

The included session migration is required because container-local session files are ephemeral.

## 3. Configure encrypted secrets

Generate an application key if the existing deployment does not already have one:

```bash
printf 'base64:%s\n' "$(openssl rand -base64 32)"
```

Set each required value. Wrangler prompts for the value without writing it to source control:

```bash
npx wrangler secret put APP_KEY
npx wrangler secret put APP_URL
npx wrangler secret put DB_HOST
npx wrangler secret put DB_DATABASE
npx wrangler secret put DB_USERNAME
npx wrangler secret put DB_PASSWORD
npx wrangler secret put GOOGLE_CREDENTIALS_BASE64
npx wrangler secret put AWS_ACCESS_KEY_ID
npx wrangler secret put AWS_SECRET_ACCESS_KEY
npx wrangler secret put R2_ACCOUNT_ID
npx wrangler secret put R2_BUCKET_NAME
```

`APP_URL` must be the final HTTPS origin, such as `https://logs.example.com`. Encode the Google credential JSON on one line before entering it:

```bash
base64 < storage/credentials.json | tr -d '\n'
```

If MySQL requires a custom CA certificate, encode it the same way and set the optional secret:

```bash
npx wrangler secret put MYSQL_SSL_CA_BASE64
```

For local development only, copy `.dev.vars.example` to `.dev.vars` and replace every placeholder. `.dev.vars` is ignored by Git.

## 4. Verify and deploy

Start Docker, then run:

```bash
npm run cf:dev
```

Check `http://localhost:8787/health`, sign in, upload a profile image, and verify that the object appears in R2. Then deploy:

```bash
npm run cf:deploy
```

Attach the final custom domain in Workers & Pages and make sure `APP_URL` matches it. When using Workers Builds, set the production deploy command to `npm run cf:deploy`; non-production version uploads do not roll out container images.

## Operational notes

- Updating a Worker secret does not change an already-running container. Restart the container or deploy again after rotating secrets.
- R2 is mounted through FUSE and is appropriate for uploaded assets, not database or session files.
- The Worker uses one named container because this Laravel release is stateful. Database sessions and R2 uploads make restarts safe, but horizontal scaling should be introduced only after testing all external integrations.
- Never commit `.env`, `.dev.vars`, the Google credential JSON, database certificates, or R2 credentials.
