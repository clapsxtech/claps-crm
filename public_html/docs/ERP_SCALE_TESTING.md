# Isolated synthetic scale observations

A disposable MariaDB 10.11.18 database contained 2,000 students, 200 unit-separated teachers, 48,000 lessons, 44,000 attendance records and 82,000 ledger entries. Authenticated Laravel HTTP-kernel requests used the same generated dataset before and after the read-projection change. Three sequential samples per route produced the medians below. These are local process measurements, not browser/network latency, concurrent-user capacity or a production SLA. No real records were used.

| Page | Before median | After median | SQL queries | Locking reads |
| --- | ---: | ---: | --- | --- |
| People | 995.81 ms | 62.09 ms | 85 → 11 | 75 → 0 |
| Money | 1032.34 ms | 91.31 ms | 189 → 16 | 150 → 0 |
| People page40 | 1335.33 ms | 55.80 ms | 85 → 11 | 75 → 0 |

Both runs passed all 51 request assertions; process peak was 42MiB. The change replaces per-person complete-ledger locking with bounded grouped read projections. These display snapshots never authorize financial mutations; mutation locks and idempotency checks remain intact. The detailed workspace also uses grouped balances and teacher summaries.

Attendance search remained about 174 ms in the after run with two SQL statements, a future EXPLAIN-driven profiling candidate. Three samples do not support a reliable p95 or production capacity claim. Validate the intended engine, hosting resources and concurrent workload before deployment.

Imported-party cutoff checks use indexed target lookups and shared current reads of immutable migration provenance. Mutable teacher/student/attendance locks remain exclusive. The native concurrency harness additionally exercises mapped cutoff visibility and unrelated parties sharing one batch; these scenarios should be rerun when changing import or posting lock order.

Raw benchmark data and the generated seed stay outside the repository; no personal source data is needed to reproduce the volume. The separate browser acceptance run uses a small fabricated cohort, not the scale fixture. Do not confuse HTTP-kernel performance tests with browser workflow coverage.

## Focused current-source sample

A subsequent run used one warmup followed by 20 measured requests per page on the identical synthetic volume. People p50=85.66 ms and p95=106.86 ms (11 queries); Money p50=109.37 ms and p95=181.18 ms (16 queries). Both had zero locking SELECTs; all 42 requests passed and peak process memory remained 42 MiB. Percentiles use nearest rank (p95 is the 19th ordered value of 20). This larger current-source sample is separate from the earlier three-sample before/after median comparison; there is no measured baseline p95. Requests were sequential in-process HTTP-kernel calls, excluding network/browser rendering and concurrent-user load.
