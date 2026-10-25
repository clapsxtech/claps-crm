import { checkOperations } from "./monitoring";
import { DurableObject } from "cloudflare:workers";
import { storageRequest, isStorageAuthorized, type StorageEnv } from "./storage";

interface Env extends StorageEnv {
    LARAVEL_CONTAINER: DurableObjectNamespace<LaravelContainer>;
    APP_KEY: string;
    APP_URL: string;
    TRUSTED_PROXIES?: string;
    ERP_BUSINESS_ID?: string;
    SHEETS_SYNC_ENABLED?: string;
    GOOGLE_SPREADSHEET_ID?: string;
    GOOGLE_SERVICE_ENABLED?: string;
    GOOGLE_CREDENTIALS_BASE64?: string;

}

const PORT = 8080;
const INACTIVITY_TIMEOUT_MS = 30 * 60 * 1000;

export class LaravelContainer extends DurableObject<Env> {
    private starting: Promise<void> | undefined;
    private ready = false;

    async restart(): Promise<void> {
        if (this.starting) await this.starting;
        this.ready = false;
        await this.ctx.container!.destroy();
        this.starting = undefined;
    }

    constructor(ctx: DurableObjectState, env: Env) {
        super(ctx, env);

        if (ctx.container?.running) {
            void ctx.blockConcurrencyWhile(() =>
                ctx.container!.setInactivityTimeout(INACTIVITY_TIMEOUT_MS),
            );
        }
    }

    async fetch(request: Request): Promise<Response> {
        // Probe once per boot, not once per HTTP request. A stopped container
        // must pass readiness again. Never replay a failed mutation request.
        if (!this.ready || !this.ctx.container!.running) {
            this.ready = false;
            this.starting ??= this.startAndWaitForPort().then(() => {
                this.ready = true;
            }).finally(() => { this.starting = undefined; });
            await this.starting;
        }

        const original = new URL(request.url);
        const target = new URL(request.url);
        target.protocol = "http:";
        // getTcpPort selects the network destination. Keep the public hostname
        // so Apache/Laravel do not generate links to an internal container host.

        const headers = new Headers(request.headers);
        // Never propagate a visitor-supplied proxy chain or origin override.
        const forwardingHeaders: string[] = [];
        headers.forEach((_value, name) => {
            if (name === "forwarded" || name.startsWith("x-forwarded-") ||
                name === "x-real-ip" || name === "x-original-url" || name === "x-rewrite-url") {
                forwardingHeaders.push(name);
            }
        });
        forwardingHeaders.forEach((name) => headers.delete(name));
        headers.set("host", original.host);
        headers.set("x-forwarded-host", original.host);
        headers.set("x-forwarded-proto", original.protocol.slice(0, -1));
        headers.set("x-forwarded-port", original.port || (original.protocol === "https:" ? "443" : "80"));

        try {
            return await this.ctx.container!.getTcpPort(PORT).fetch(
                new Request(target, { method: request.method, headers, body: request.body, redirect: "manual" }),
            );
        } catch (error) {
            this.ready = false;
            throw error;
        }
    }

    private async startAndWaitForPort(): Promise<void> {
        const container = this.ctx.container!;

        if (!container.running) {
            const publicUrl = new URL(this.env.APP_URL);
            const secureOrigin = publicUrl.protocol === "https:";
            container.start({
                image: container.images.base,
                // Apache reopens its log descriptors. Give it a regular file and
                // forward that file to the platform log stream from a separate process.
                entrypoint: ['/bin/sh', '-c', 'touch /tmp/erp-runtime.log; tail --pid=$$ -n +1 -F /tmp/erp-runtime.log & exec /usr/local/bin/start-container >> /tmp/erp-runtime.log 2>&1'],
                instance: "standard-1",
                enableInternet: true,
                env: {
                    APP_NAME: "Claps Learn Logs",
                    APP_ENV: "production",
                    APP_DEBUG: "false",
                    APP_KEY: this.env.APP_KEY,
                    APP_URL: this.env.APP_URL,
                    // This is deployment configuration, not a request header.
                    // Laravel applies it only to requests with this exact Host.
                    PUBLIC_ORIGIN: secureOrigin ? this.env.APP_URL : "",
                    TRUSTED_PROXIES: this.env.TRUSTED_PROXIES ?? "",
                    ERP_BUSINESS_ID: this.env.ERP_BUSINESS_ID ?? "1",
                    SHEETS_SYNC_ENABLED: this.env.SHEETS_SYNC_ENABLED ?? "false",
                    GOOGLE_SPREADSHEET_ID: this.env.GOOGLE_SPREADSHEET_ID ?? "",
                    GOOGLE_SERVICE_ENABLED: this.env.GOOGLE_SERVICE_ENABLED ?? "false",
                    LOG_CHANNEL: "stderr",
                    DB_CONNECTION: "d1",
                    CLOUDFLARE_GATEWAY_URL: this.env.APP_URL,
                    CLOUDFLARE_INTERNAL_TOKEN: this.env.INTERNAL_TOKEN,
                    SESSION_DRIVER: "database",
                    SESSION_SECURE_COOKIE: secureOrigin ? "true" : "false",
                    CACHE_DRIVER: "file",
                    QUEUE_CONNECTION: "sync",
                    GOOGLE_CREDENTIALS_BASE64: this.env.GOOGLE_CREDENTIALS_BASE64 ?? "",

                },
            });
        }

        await container.setInactivityTimeout(INACTIVITY_TIMEOUT_MS);
        const port = container.getTcpPort(PORT);
        let lastError: unknown;

        // Connection refusals can return immediately while a cold image boots.
        // Bound readiness by elapsed time, not a small number of fast failures.
        const readyDeadline = Date.now() + 120_000;
        while (Date.now() < readyDeadline) {
            try {
                const response = await port.fetch("http://container/health", {
                    signal: AbortSignal.timeout(5000),
                });
                await response.body?.cancel();
                if (response.ok) return;
                lastError = new Error(`Health check returned ${response.status}`);
            } catch (error) {
                lastError = error;
            }
            await scheduler.wait(500);
        }

        throw new Error("Laravel container did not become ready", { cause: lastError });
    }
}

export default {
    async scheduled(_controller: ScheduledController, env: Env): Promise<void> {
        try {
            const report = await checkOperations(env.DB, () => env.LARAVEL_CONTAINER.getByName('production').fetch(new Request(new URL('/health', env.APP_URL))));
            const message = JSON.stringify({ event: 'erp.operational_health', ...report });
            if (report.status === 'ok') console.log(message);
            else console.error(message);
            if (report.status === 'error') throw new Error('ERP readiness failed');
        } catch {
            console.error(JSON.stringify({ event: 'erp.operational_health', status: 'error', code: 'check_failed' }));
            throw new Error('ERP operational health check failed');
        }
    },
    async fetch(request: Request, env: Env): Promise<Response> {
        if (new URL(request.url).pathname === '/__cloudflare/restart') {
            if (request.method !== 'POST' || !await isStorageAuthorized(request, env)) {
                return new Response('Unauthorized', { status: 401 });
            }
            await env.LARAVEL_CONTAINER.getByName('production').restart();
            return Response.json({ restarted: true }, { headers: { 'Cache-Control': 'no-store' } });
        }
        const storage = await storageRequest(request, env);
        if (storage) return storage;
        return env.LARAVEL_CONTAINER.getByName("production").fetch(request);
    },
} satisfies ExportedHandler<Env>;
