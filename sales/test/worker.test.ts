import { describe, expect, it } from "vitest";
import worker, { CSRF_HEADER, SESSION_COOKIE, type Env } from "../worker/index";

function makeEnv(reply: (request: Request) => Response | Promise<Response>) {
    const calls: Request[] = [];
    const env = {
        ASSETS: { fetch: async () => new Response("app shell") } as unknown as Fetcher,
        ERP: { fetch: async (request: Request) => { calls.push(request); return reply(request); } } as unknown as Fetcher,
    } satisfies Env;
    return { env, calls };
}

const write = (path: string, body: unknown, headers: Record<string, string> = {}) =>
    new Request(`https://sales.example.test${path}`, { method: "POST", body: JSON.stringify(body), headers: { "Content-Type": "application/json", [CSRF_HEADER]: "1", ...headers } });

describe("sales Worker", () => {
    it("serves the app for non-API paths", async () => {
        const { env, calls } = makeEnv(() => new Response("{}"));
        const response = await worker.fetch(new Request("https://sales.example.test/leads/5"), env);
        expect(await response.text()).toBe("app shell");
        expect(calls).toHaveLength(0);
    });

    it("signs in through the ERP and keeps the token in an HttpOnly cookie", async () => {
        const { env, calls } = makeEnv(() => Response.json({ token: "tok/en+1", expires_at: 1, user: { id: 41 } }));
        const response = await worker.fetch(write("/api/login", { email: "a@b", password: "p" }), env);
        expect(response.status).toBe(200);
        expect(await response.json()).toEqual({ user: { id: 41 } });
        const cookie = response.headers.get("set-cookie") ?? "";
        expect(cookie).toContain(`${SESSION_COOKIE}=tok%2Fen%2B1`);
        expect(cookie).toMatch(/HttpOnly/);
        expect(cookie).toMatch(/Secure/);
        expect(cookie).toMatch(/SameSite=Strict/);
        expect(calls[0].url).toBe("https://erp.internal/api/sales/login");
        expect(await calls[0].text()).toBe('{"email":"a@b","password":"p"}');
    });

    it("passes two-factor prompts and validation errors through without a cookie", async () => {
        for (const [status, body] of [[202, { two_factor_required: true }], [422, { errors: { email: ["No"] } }]] as const) {
            const { env } = makeEnv(() => Response.json(body, { status }));
            const response = await worker.fetch(write("/api/login", {}), env);
            expect(response.status).toBe(status);
            expect(response.headers.get("set-cookie")).toBeNull();
            expect(await response.json()).toEqual(body);
        }
    });

    it("forwards API calls with the bearer token from the cookie", async () => {
        const { env, calls } = makeEnv(() => Response.json({ data: [] }));
        const response = await worker.fetch(new Request("https://sales.example.test/api/units/3/leads?q=asha&page=2", {
            headers: { Cookie: `other=1; ${SESSION_COOKIE}=tok%2Fen`, Authorization: "Bearer spoofed" },
        }), env);
        expect(response.status).toBe(200);
        expect(calls[0].url).toBe("https://erp.internal/api/sales/units/3/leads?q=asha&page=2");
        expect(calls[0].headers.get("authorization")).toBe("Bearer tok/en");
        expect(calls[0].headers.get("cookie")).toBeNull();
    });

    it("refuses calls without a session and clears the cookie when the ERP rejects the token", async () => {
        const { env, calls } = makeEnv(() => Response.json({ message: "ended" }, { status: 401 }));
        expect((await worker.fetch(new Request("https://sales.example.test/api/me"), env)).status).toBe(401);
        expect(calls).toHaveLength(0);
        const response = await worker.fetch(new Request("https://sales.example.test/api/me", { headers: { Cookie: `${SESSION_COOKIE}=old` } }), env);
        expect(response.status).toBe(401);
        expect(response.headers.get("set-cookie")).toContain("Max-Age=0");
    });

    it("requires the CSRF header on writes and rejects odd paths", async () => {
        const { env, calls } = makeEnv(() => Response.json({}));
        const noHeader = new Request("https://sales.example.test/api/units/3/actions/create-lead", { method: "POST", body: "{}", headers: { Cookie: `${SESSION_COOKIE}=t` } });
        expect((await worker.fetch(noHeader, env)).status).toBe(403);
        const odd = new Request("https://sales.example.test/api/units/3/../../__cloudflare/restart", { headers: { Cookie: `${SESSION_COOKIE}=t` } });
        const oddResponse = await worker.fetch(odd, env);
        expect(new URL(odd.url).pathname.startsWith("/api/") ? oddResponse.status : 404).toBe(404);
        expect((await worker.fetch(new Request("https://sales.example.test/api/units/3/leads%2F..", { headers: { Cookie: `${SESSION_COOKIE}=t` } }), env)).status).toBe(404);
        expect(calls).toHaveLength(0);
    });

    it("signs out by clearing the cookie", async () => {
        const { env } = makeEnv(() => Response.json({}));
        const response = await worker.fetch(write("/api/logout", {}), env);
        expect(response.headers.get("set-cookie")).toContain(`${SESSION_COOKIE}=;`);
        expect(response.headers.get("set-cookie")).toContain("Max-Age=0");
    });

    it("reports an unreachable ERP as 502", async () => {
        const { env } = makeEnv(() => { throw new Error("down"); });
        const response = await worker.fetch(new Request("https://sales.example.test/api/me", { headers: { Cookie: `${SESSION_COOKIE}=t` } }), env);
        expect(response.status).toBe(502);
    });
});
