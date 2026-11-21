/**
 * sales.clapslearn.in Worker. Serves the app (static assets) and forwards /api/* to
 * the ERP's sales API through the ERP Worker's SalesGateway entrypoint. This Worker
 * keeps no data. The ERP sign-in token lives in an HttpOnly cookie, so page scripts
 * can never read it; the Worker adds it to each forwarded request.
 */
export interface Env {
    ASSETS: Fetcher;
    ERP: Fetcher;
}

export const SESSION_COOKIE = "claps_sales";
/** Writes must carry this header: a cross-site form or image cannot set it. */
export const CSRF_HEADER = "x-claps-sales";
const ERP_ORIGIN = "https://erp.internal";
const TOKEN_MAX_AGE = 12 * 3600;

function json(body: unknown, status = 200, headers: HeadersInit = {}): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { "Content-Type": "application/json", "Cache-Control": "no-store", ...headers },
    });
}

export function readCookie(request: Request, name: string): string | null {
    const header = request.headers.get("cookie");
    if (!header) return null;
    for (const part of header.split(";")) {
        const [key, ...rest] = part.trim().split("=");
        if (key === name) return rest.join("=") || null;
    }
    return null;
}

function sessionCookie(value: string, maxAge: number): string {
    return `${SESSION_COOKIE}=${value}; Path=/api; HttpOnly; Secure; SameSite=Strict; Max-Age=${maxAge}`;
}

async function erpRequest(request: Request, path: string, token: string | null = null): Promise<Request> {
    const headers = new Headers({ Accept: "application/json" });
    const contentType = request.headers.get("content-type");
    if (contentType) headers.set("Content-Type", contentType);
    const ip = request.headers.get("cf-connecting-ip");
    if (ip) headers.set("cf-connecting-ip", ip);
    if (token) headers.set("Authorization", `Bearer ${token}`);
    const url = new URL(request.url);
    return new Request(`${ERP_ORIGIN}/api/sales${path}${url.search}`, {
        method: request.method,
        headers,
        // Bodies are small JSON documents; buffering keeps the forwarded request simple.
        body: request.method === "GET" || request.method === "HEAD" ? null : await request.arrayBuffer(),
    });
}

export async function handleApi(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);
    const path = url.pathname.slice("/api".length);
    const isRead = request.method === "GET" || request.method === "HEAD";
    if (!isRead && request.headers.get(CSRF_HEADER) !== "1") return json({ message: "Request refused." }, 403);
    if (!/^[a-z0-9/-]*$/.test(path)) return json({ message: "Not found." }, 404);

    if (path === "/login") {
        if (request.method !== "POST") return json({ message: "Method not allowed." }, 405);
        const response = await env.ERP.fetch(await erpRequest(request, "/login"));
        if (response.status !== 200) return passThrough(response);
        const body = await response.json<{ token?: string; user?: unknown }>();
        if (!body.token) return json({ message: "Sign-in failed." }, 502);
        return json({ user: body.user }, 200, { "Set-Cookie": sessionCookie(encodeURIComponent(body.token), TOKEN_MAX_AGE) });
    }
    if (path === "/logout") {
        if (request.method !== "POST") return json({ message: "Method not allowed." }, 405);
        return json({ ok: true }, 200, { "Set-Cookie": sessionCookie("", 0) });
    }

    const raw = readCookie(request, SESSION_COOKIE);
    if (!raw) return json({ message: "Sign in to continue." }, 401);
    let token: string;
    try {
        token = decodeURIComponent(raw);
    } catch {
        return json({ message: "Sign in to continue." }, 401, { "Set-Cookie": sessionCookie("", 0) });
    }
    const response = await env.ERP.fetch(await erpRequest(request, path, token));
    if (response.status === 401) {
        const passed = passThrough(response);
        passed.headers.append("Set-Cookie", sessionCookie("", 0));
        return passed;
    }
    return passThrough(response);
}

function passThrough(response: Response): Response {
    const headers = new Headers({ "Cache-Control": "no-store" });
    const contentType = response.headers.get("content-type");
    if (contentType) headers.set("Content-Type", contentType);
    const retry = response.headers.get("retry-after");
    if (retry) headers.set("Retry-After", retry);
    return new Response(response.body, { status: response.status, headers });
}

export default {
    async fetch(request: Request, env: Env): Promise<Response> {
        const url = new URL(request.url);
        if (url.pathname === "/api" || url.pathname.startsWith("/api/")) {
            try {
                return await handleApi(request, env);
            } catch {
                return json({ message: "The ERP could not be reached. Try again in a moment." }, 502);
            }
        }
        return env.ASSETS.fetch(request);
    },
} satisfies ExportedHandler<Env>;
