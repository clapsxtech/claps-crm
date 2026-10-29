export interface StorageEnv {
    DB: D1Database;
    UPLOADS: R2Bucket;
    INTERNAL_TOKEN: string;
}
const json = (value: unknown, status = 200) => Response.json(value, { status, headers: { 'Cache-Control': 'no-store' } });
const identifier = (value: string) => '"' + value.replaceAll('"', '""') + '"';
async function digest(value: string) {
    return [...new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(value)))].map(b => b.toString(16).padStart(2, '0')).join('');
}

export async function isStorageAuthorized(request: Request, env: StorageEnv): Promise<boolean> {
    return Boolean(env.INTERNAL_TOKEN) && await digest(request.headers.get('Authorization') ?? '') === await digest('Bearer ' + env.INTERNAL_TOKEN);
}

// Convert bindings to SQLite literals without interpreting question marks inside quoted SQL.
// This avoids D1's bound-parameter limit for Laravel's multi-row inserts.
export function bindSql(sql: string, params: unknown[]): string {
    let result = '', quote = '', index = 0;
    for (let i = 0; i < sql.length; i++) {
        const ch = sql[i];
        if (quote) {
            result += ch;
            if (ch === quote) {
                if (sql[i + 1] === quote) result += sql[++i]; else quote = '';
            }
        } else if (ch === '"' || ch === "'" || ch === '`') { quote = ch; result += ch; }
        else if (ch === '?') {
            if (index >= params.length) throw new Error('Binding count');
            const value = params[index++];
            if (value === null) result += 'NULL';
            else if (typeof value === 'number' && Number.isFinite(value)) result += String(value);
            else if (typeof value === 'boolean') result += value ? '1' : '0';
            else if (typeof value === 'string' && !value.includes('\0')) result += "'" + value.replaceAll("'", "''") + "'";
            else throw new Error('Unsupported binding');
        } else result += ch;
    }
    if (quote || index !== params.length) throw new Error('Invalid bindings');
    if (new TextEncoder().encode(result).length > 95_000) throw new Error('D1 statement too large');
    return result;
}

export async function storageRequest(request: Request, env: StorageEnv): Promise<Response | null> {
    const url = new URL(request.url);
    if (url.pathname.startsWith('/uploads/')) {
        if (!['GET', 'HEAD'].includes(request.method)) return new Response(null, { status: 405 });
        const key = url.pathname.slice('/uploads/'.length);
        if (!/^profile_photo\/[a-f0-9-]{36}\.(jpe?g|png|gif|webp)$/.test(key)) return new Response(null, { status: 404 });
        const object = await env.UPLOADS.get(key);
        if (!object) return new Response(null, { status: 404 });
        const headers = new Headers({ 'X-Content-Type-Options': 'nosniff', 'Cache-Control': 'private, max-age=300' });
        object.writeHttpMetadata(headers); headers.set('ETag', object.httpEtag);
        return new Response(request.method === 'HEAD' ? null : object.body, { headers });
    }
    if (!url.pathname.startsWith('/__cloudflare/')) return null;
    if (!await isStorageAuthorized(request, env)) return json({ error: 'Unauthorized' }, 401);
    try {
        if (url.pathname.startsWith('/__cloudflare/uploads/')) {
            const key = url.pathname.slice('/__cloudflare/uploads/'.length);
            if (!/^profile_photo\/[a-f0-9-]{36}\.(jpe?g|png|gif|webp)$/.test(key)) return json({ error: 'Invalid key' }, 400);
            if (request.method === 'PUT') {
                const data = await request.arrayBuffer();
                if (data.byteLength > 2 * 1024 * 1024) return json({ error: 'Too large' }, 413);
                await env.UPLOADS.put(key, data, { httpMetadata: { contentType: request.headers.get('Content-Type') ?? 'application/octet-stream' } });
                return json({ ok: true });
            }
            if (request.method === 'DELETE') { await env.UPLOADS.delete(key); return json({ ok: true }); }
            return json({ error: 'Method' }, 405);
        }
        if (request.method !== 'POST') return json({ error: 'Method' }, 405);
        const input = await request.json() as any;
        const action = url.pathname.slice('/__cloudflare/database/'.length);
        // All reads use the primary, including revision checks and replica recovery.
        const db = env.DB.withSession('first-primary');
        if (action === 'state') return json(await db.prepare('SELECT revision FROM erp_d1_revision WHERE id=1').first());
        if (action === 'schema') {
            const results = await db.batch([
                db.prepare('SELECT revision FROM erp_d1_revision WHERE id=1'),
                db.prepare("SELECT type,name,tbl_name,sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' AND substr(name,1,7) != 'erp_d1_' AND substr(name,1,4) != '_cf_' AND name != 'd1_migrations' AND type IN ('table','index','view','trigger') ORDER BY CASE type WHEN 'table' THEN 0 WHEN 'index' THEN 1 ELSE 2 END,name"),
                db.prepare('SELECT name,seq FROM sqlite_sequence'),
            ]);
            return json({ revision: (results[0].results[0] as { revision: number }).revision, schema: results[1].results, sequences: results[2].results });
        }
        if (action === 'rows_batch') {
            if (!Array.isArray(input.tables) || input.tables.length < 1 || input.tables.length > 8 || new Set(input.tables).size !== input.tables.length) return json({ error: 'Invalid tables' }, 400);
            const limit = input.limit ?? 200;
            if (!Number.isSafeInteger(limit) || limit < 1 || limit > 2000) return json({ error: 'Invalid page size' }, 400);
            if (input.cursors !== undefined && (input.cursors === null || typeof input.cursors !== 'object' || Array.isArray(input.cursors))) return json({ error: 'Invalid cursors' }, 400);
            if (input.tables.some((table: string) => input.cursors?.[table] !== undefined && (!Number.isSafeInteger(input.cursors[table]) || input.cursors[table] < -1))) return json({ error: 'Invalid cursor' }, 400);
            const catalog = await db.prepare("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND substr(name,1,7) != 'erp_d1_' AND substr(name,1,4) != '_cf_' AND name != 'd1_migrations'").all<{ name: string }>();
            const allowed = new Set(catalog.results.map(row => row.name));
            if (input.tables.some((table: unknown) => typeof table !== 'string' || !allowed.has(table))) return json({ error: 'Unknown table' }, 400);
            const tables: string[] = input.tables;
            const results = await db.batch(tables.map(table => db.prepare(`SELECT rowid AS _erp_d1_rowid,* FROM ${identifier(table)} WHERE rowid>? ORDER BY rowid LIMIT ${limit}`).bind(input.cursors?.[table] ?? -1)));
            return json({ pages: Object.fromEntries(tables.map((table, index) => [table, { rows: results[index].results, more: results[index].results.length === limit }])) });
        }
        if (action === 'rows') {
            if (typeof input.table !== 'string' || /^(erp_d1_|_cf_|sqlite_)/.test(input.table) || input.table === 'd1_migrations') return json({ error: 'Invalid table' }, 400);
            const table = await db.prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=? AND name NOT LIKE 'sqlite_%'").bind(input.table).first();
            if (!table) return json({ error: 'Unknown table' }, 400);
            const cursor = input.cursor === null ? -1 : input.cursor;
            if (!Number.isSafeInteger(cursor)) return json({ error: 'Invalid cursor' }, 400);
            const rows = await db.prepare(`SELECT rowid AS _erp_d1_rowid,* FROM ${identifier(input.table)} WHERE rowid>? ORDER BY rowid LIMIT 200`).bind(cursor).all();
            return json({ rows: rows.results, more: rows.results.length === 200 });
        }
        if (action === 'commit') {
            if (!/^[a-f0-9]{48}$/.test(input.id) || !Number.isSafeInteger(input.revision) || !Array.isArray(input.statements) || input.statements.length < 1 || input.statements.length > 900) return json({ error: 'Invalid transaction' }, 400);
            const hash = await digest(JSON.stringify({ revision: input.revision, statements: input.statements }));
            const receipt = await db.prepare('SELECT revision,hash FROM erp_d1_commits WHERE id=?').bind(input.id).first();
            if (receipt) return receipt.hash === hash ? json({ revision: receipt.revision }) : json({ error: 'Transaction ID conflict' }, 409);
            const statements = input.statements.map((s: any) => {
                if (typeof s.sql !== 'string' || !/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i.test(s.sql) || /\berp_d1_/i.test(s.sql) || !Array.isArray(s.params)) throw new Error('Invalid mutation');
                return db.prepare(bindSql(s.sql, s.params));
            });
            try {
                const result = await db.batch([
                    // CHECK failure aborts the entire batch before any business write occurs.
                    db.prepare('INSERT INTO erp_d1_guard(ok) SELECT CASE WHEN revision=? THEN 1 ELSE 0 END FROM erp_d1_revision WHERE id=1').bind(input.revision),
                    db.prepare('DELETE FROM erp_d1_guard'),
                    ...statements,
                    db.prepare('INSERT INTO erp_d1_commits(id,hash,revision) SELECT ?,?,revision FROM erp_d1_revision WHERE id=1').bind(input.id, hash),
                    db.prepare('SELECT revision FROM erp_d1_revision WHERE id=1'),
                ]);
                return json({ revision: (result[result.length - 1].results[0] as { revision: number }).revision });
            } catch {
                // Another invocation may have committed this same request while its retry arrived.
                const committed = await db.prepare('SELECT revision,hash FROM erp_d1_commits WHERE id=?').bind(input.id).first();
                if (committed?.hash === hash) return json({ revision: committed.revision });
                return json({ error: 'Transaction rejected; no changes committed' }, 409);
            }
        }
        return json({ error: 'Unknown operation' }, 404);
    } catch { return json({ error: 'Storage request failed' }, 500); }
}
