# ADR-0074: CI checks the request budgets on the sustained stage of the scaled load

- Status: Accepted (Phase 6). Raised in the Phase 6 pull request for the owner to confirm or reject.
- Date: 2026-10-30

## Context

Architecture §20.2 asks for the request p95 budgets at 500 concurrent users: about 50 requests a second sustained and 150 at peak, on the reference environment of §20.1. That environment has about 22 vCPUs:
- two 4-vCPU application servers;
- a 4-vCPU worker;
- a 2-vCPU Redis;
- an 8-vCPU database server.

The `Performance` workflow runs on one 4-vCPU GitHub runner. That runner holds the application, the worker, Redis, ClamAV, the database and k6 itself, so the workflow loads it at a fifth of the rates: 10 requests a second, then 30 (see `docs/performance.md`).

Run [41036865112](https://github.com/moalmansor/Core-Low-Code-Framework/actions/runs/41036865112) on `797f723` reported the p95 per stage for the first time:

| Request | SQL Server, sustained (10/s) | SQL Server, peak (30/s) | MySQL, sustained | MySQL, peak |
|---|---|---|---|---|
| Form definition | 42 ms | 1 686 ms | 48 ms | 10 288 ms |
| Record view | 88 ms | 2 167 ms | 98 ms | 10 470 ms |
| Record open | 63 ms | 1 853 ms | 56 ms | 10 395 ms |
| Record list | 120 ms | 2 199 ms | 1 158 ms | 25 530 ms |
| Global search | 636 ms | 5 979 ms | 618 ms | 14 176 ms |
| Record save | 104 ms | 2 168 ms | 91 ms | 10 470 ms |

- **At 10 requests a second, every request budget passes on SQL Server.** On MySQL, every budget except the list passes. ADR-0073 item 4 fixes the list.
- **At 30 requests a second, every request on both engines waits seconds.** The runner served 12.7 requests a second on SQL Server and 10.2 on MySQL, averaged over the run. During the peak, its CPU is fully used. The requests are not slower to compute there: they queue for a worker. The p95 at that stage measures the runner's capacity, not the code.

## Decision

1. In CI, `perf/compare.mjs --latency-stage sustained` checks the request p95 budgets on the sustained stage of the load test.
2. The error-rate budget (< 0.1 %) still covers the whole run, the peak included.
3. The peak stage still runs. The job summary reports each request's peak p95 in a separate table titled "Not asserted", next to its budget.
4. Every other budget is unchanged: the jobs, permissions, formulas, the browser timings and the 20 % regression rule against the baseline.
5. A run without `--latency-stage`, as on hardware matching §20.1 at the full 50 and 150 requests a second, checks the whole run as before.

## Consequences

- The workflow gates the latency budgets at a load the runner can serve. It also gates regressions of more than 20 % at that load, once a baseline is committed.
- **The 150 requests a second peak budget of §20.2 is not verified by CI**, and neither is the 50 requests a second sustained budget at full scale. `docs/performance.md` and the Phase 6 report say so. Verifying them needs the §20.1 environment, with the commands in `docs/performance.md`.
- If the owner prefers the whole run to count, removing `--latency-stage sustained` from `performance.yml` restores it. The job will then fail until it runs on larger runners.
