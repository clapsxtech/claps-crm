#!/usr/bin/env node
// Restart the production Laravel container after `wrangler deploy` and verify
// it is serving. Deploying alone does not replace an already-running container
// (see CLOUDFLARE.md), so Git-connected deploys must run this afterwards.
//
//   node scripts/restart-container.mjs --check   # fail fast if INTERNAL_TOKEN is missing
//   node scripts/restart-container.mjs           # restart, then verify /health and /ready
//
// INTERNAL_TOKEN must equal the Worker secret of the same name. In Workers
// Builds, add it under Settings > Build > Build variables and secrets.
// The token is only ever sent in the Authorization header; never log it.

const appUrl = (process.env.DEPLOY_APP_URL || 'https://erp.clapslearn.in').replace(/\/+$/, '');
const token = process.env.INTERNAL_TOKEN || '';
const attempts = 3;
const restartTimeoutMs = 240_000;
const retryDelayMs = 30_000;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function fail(message) {
    console.error(`restart-container: ${message}`);
    process.exit(1);
}

if (!token) fail('INTERNAL_TOKEN is not set. Add it as a build secret before deploying.');
if (process.argv.includes('--check')) {
    console.log('restart-container: INTERNAL_TOKEN is present.');
    process.exit(0);
}

async function restart() {
    const response = await fetch(`${appUrl}/__cloudflare/restart`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}` },
        signal: AbortSignal.timeout(restartTimeoutMs),
    });
    const body = (await response.text()).slice(0, 200);
    if (response.status === 401) fail('restart was refused (401): INTERNAL_TOKEN does not match the Worker secret.');
    let ready = false;
    try { ready = JSON.parse(body).ready === true; } catch { /* non-JSON error page */ }
    return { ok: response.ok && ready, status: response.status, body };
}

async function check(path) {
    const response = await fetch(`${appUrl}${path}`, { signal: AbortSignal.timeout(60_000), headers: { 'Cache-Control': 'no-cache' } });
    await response.body?.cancel();
    return response.status;
}

for (let attempt = 1; attempt <= attempts; attempt++) {
    console.log(`restart-container: restarting (attempt ${attempt}/${attempts})...`);
    let result;
    try {
        result = await restart();
    } catch (error) {
        result = { ok: false, status: 0, body: String(error?.message || error) };
    }
    if (result.ok) {
        const health = await check('/health');
        const ready = await check('/ready');
        console.log(`restart-container: ready; /health ${health}, /ready ${ready}`);
        if (health === 200 && ready === 200) process.exit(0);
        fail('container restarted but the site is not healthy.');
    }
    console.error(`restart-container: attempt ${attempt} failed (HTTP ${result.status}): ${result.body}`);
    if (attempt < attempts) await sleep(retryDelayMs);
}
fail(`container did not become ready after ${attempts} attempts. Check the Worker logs and roll back if needed.`);
