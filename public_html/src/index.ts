import { DurableObject } from "cloudflare:workers";

interface Env {
    LARAVEL_CONTAINER: DurableObjectNamespace<LaravelContainer>;
    APP_KEY: string;
    APP_URL: string;
    DB_HOST: string;
    DB_PORT: string;
    DB_DATABASE: string;
    DB_USERNAME: string;
    DB_PASSWORD: string;
    GOOGLE_CREDENTIALS_BASE64: string;
    MYSQL_SSL_CA_BASE64?: string;
    AWS_ACCESS_KEY_ID: string;
    AWS_SECRET_ACCESS_KEY: string;
    R2_ACCOUNT_ID: string;
    R2_BUCKET_NAME: string;
}

const PORT = 8080;
const INACTIVITY_TIMEOUT_MS = 30 * 60 * 1000;

export class LaravelContainer extends DurableObject<Env> {
    private starting: Promise<void> | undefined;

    constructor(ctx: DurableObjectState, env: Env) {
        super(ctx, env);

        if (ctx.container?.running) {
            void ctx.blockConcurrencyWhile(() =>
                ctx.container!.setInactivityTimeout(INACTIVITY_TIMEOUT_MS),
            );
        }
    }

    async fetch(request: Request): Promise<Response> {
        this.starting ??= this.startAndWaitForPort().finally(() => {
            this.starting = undefined;
        });
        await this.starting;

        const original = new URL(request.url);
        const target = new URL(request.url);
        target.protocol = "http:";
        target.host = "container";

        const headers = new Headers(request.headers);
        headers.delete("host");
        headers.set("x-forwarded-host", original.host);
        headers.set("x-forwarded-proto", original.protocol.slice(0, -1));

        return this.ctx.container!.getTcpPort(PORT).fetch(
            new Request(target, { method: request.method, headers, body: request.body, redirect: "manual" }),
        );
    }

    private async startAndWaitForPort(): Promise<void> {
        const container = this.ctx.container!;

        if (!container.running) {
            container.start({
                image: container.images.base,
                instance: "basic",
                enableInternet: true,
                env: {
                    APP_NAME: "Claps Learn Logs",
                    APP_ENV: "production",
                    APP_DEBUG: "false",
                    APP_KEY: this.env.APP_KEY,
                    APP_URL: this.env.APP_URL,
                    LOG_CHANNEL: "stderr",
                    DB_CONNECTION: "mysql",
                    DB_HOST: this.env.DB_HOST,
                    DB_PORT: this.env.DB_PORT,
                    DB_DATABASE: this.env.DB_DATABASE,
                    DB_USERNAME: this.env.DB_USERNAME,
                    DB_PASSWORD: this.env.DB_PASSWORD,
                    SESSION_DRIVER: "database",
                    SESSION_SECURE_COOKIE: "true",
                    CACHE_DRIVER: "array",
                    QUEUE_CONNECTION: "sync",
                    GOOGLE_CREDENTIALS_BASE64: this.env.GOOGLE_CREDENTIALS_BASE64,
                    MYSQL_SSL_CA_BASE64: this.env.MYSQL_SSL_CA_BASE64 ?? "",
                    AWS_ACCESS_KEY_ID: this.env.AWS_ACCESS_KEY_ID,
                    AWS_SECRET_ACCESS_KEY: this.env.AWS_SECRET_ACCESS_KEY,
                    R2_ACCOUNT_ID: this.env.R2_ACCOUNT_ID,
                    R2_BUCKET_NAME: this.env.R2_BUCKET_NAME,
                },
            });
        }

        await container.setInactivityTimeout(INACTIVITY_TIMEOUT_MS);
        const port = container.getTcpPort(PORT);
        let lastError: unknown;

        for (let attempt = 0; attempt < 100; attempt++) {
            try {
                const response = await port.fetch("http://container/health", {
                    signal: AbortSignal.timeout(1000),
                });
                await response.body?.cancel();
                if (response.ok) return;
                lastError = new Error(`Health check returned ${response.status}`);
            } catch (error) {
                lastError = error;
            }
            await scheduler.wait(200);
        }

        throw new Error("Laravel container did not become ready", { cause: lastError });
    }
}

export default {
    fetch(request: Request, env: Env): Promise<Response> {
        return env.LARAVEL_CONTAINER.getByName("production").fetch(request);
    },
} satisfies ExportedHandler<Env>;
