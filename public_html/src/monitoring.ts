/** Bounded, read-only scheduled checks. Logs contain counts and timing, never records or credentials. */
export async function checkOperations(database: D1Database, health: () => Promise<Response>): Promise<{
    status: 'ok' | 'warning' | 'error'; duration_ms: number; failed_jobs: number; uncertain_events: number;
}> {
    const started = Date.now();
    const [response, results] = await Promise.all([
        health(),
        database.batch<{ count: number }>([
            database.prepare('SELECT COUNT(*) AS count FROM failed_jobs'),
            database.prepare("SELECT COUNT(*) AS count FROM integration_outbox WHERE status IN ('uncertain','sending')"),
        ]),
    ]);
    const failedJobs = Number(results[0].results[0]?.count ?? 0);
    const uncertainEvents = Number(results[1].results[0]?.count ?? 0);
    // Consume the small internal readiness response to release its stream.
    const readiness = await response.json<{ status?: string }>();
    return {
        status: !response.ok || readiness.status !== 'ok' ? 'error' : failedJobs || uncertainEvents ? 'warning' : 'ok',
        duration_ms: Date.now() - started, failed_jobs: failedJobs, uncertain_events: uncertainEvents,
    };
}
