# Performance

How the budgets of architecture §20.2 are measured, at what load, and with what results.

## What is measured

The `Performance` workflow (`.github/workflows/performance.yml`) runs on every push to a phase branch, on pull requests, and on demand. It runs once per engine, MySQL 8.4 and SQL Server 2019. Each run takes about 55 minutes and does the following:

1. **Builds the Docker stack** from `docker-compose.yml` with the production image: PHP-FPM with 32 workers, nginx, the Horizon worker, the scheduler, Redis and ClamAV.
2. **Builds the reference dataset of §20.1** through the API and in bulk (`backend/tests/Performance`):
   - 1 000 000 orders in the measured form (40 stored fields, 2 repeating sections of 5 rows, 3 lookups);
   - 200 forms;
   - 5 000 users, 50 roles, and 300 departments, 6 levels deep;
   - 20 000 000 history rows.
3. **Times the job budgets** with the harness: permission resolution (cached and cold), the formula set of a 100-field form, a 100 000-row import, a three-level 100 000-row download, and an additive publish on the 1 000 000-row table.
4. **Runs the load test** (`perf/k6/load.js`). 200 users sign in, then a sustained stage and a peak stage run this mix:

   | Share | Request |
   |---|---|
   | 30 % | Record list: 25 rows, 10 columns, 3 filters, a sort, within the user's record scope |
   | 20 % | Record view: the record and its 3 panels |
   | 15 % | Record open |
   | 10 % | Form definition |
   | 10 % | Global search across 20 forms |
   | 15 % | Record save, with an edit of the new record a third of the time |

5. **Times the browser budgets** with Playwright (`frontend/e2e-perf`): rendering the 100-field form, and client-side formulas.
6. **Compares** with `perf/compare.mjs`. In CI the request p95s are checked on the sustained stage. The peak stage is reported next to them without being asserted, and the error rate covers the whole run (ADR-0074). A result over its §20.2 budget fails the run. So does a result more than 20 % slower than the engine's baseline in `perf/baseline/{mysql,sqlsrv}.json`, once a baseline is committed. The table appears in the job summary.

## The load is scaled to the runner

§20.2 sets 50 requests a second sustained and 150 at peak for the reference environment of §20.1:
- two 4-vCPU application servers;
- a 4-vCPU worker;
- Redis;
- an 8-vCPU database server.

A GitHub-hosted runner has 4 vCPUs. It holds the whole stack, the database and k6, which is roughly a fifth of that hardware. The workflow therefore loads it at a fifth of the rates by default: 10 requests a second sustained and 30 at peak. The `rate` and `peak_rate` inputs change this for a manual run.

**The full 50 and 150 requests a second have not been run on the reference environment.** CI checks the request budgets at the scaled sustained rate. The scaled peak saturates the runner, so its p95s are reported without being asserted (ADR-0074). Someone with hardware matching §20.1 runs the same script at full rate, against the stack built from the same image:

```sh
docker run --rm --network host -v "$PWD:/w" -w /w grafana/k6:0.54.0 run \
  -e LCF_URL=https://lcf.example.test -e LCF_TARGETS=/w/perf-targets.json \
  -e LCF_K6_OUT=/w/k6-summary.json -e LCF_RATE=50 -e LCF_PEAK_RATE=150 perf/k6/load.js
node perf/compare.mjs --engine mysql --k6 k6-summary.json --rate 50 --peak-rate 150
```

`perf-targets.json` comes from the dataset step (`LCF_PERFORMANCE=1 php tests/Performance/bench.php dataset --admin=<an administrator's email> --scale=1 --out=perf-targets.json`). The dataset is written into the database it runs against, so build it in a database kept for load testing, never in production.

## Reading a run

- **The comparison table** gives one row per budget: the measured value (the p95 for requests, the duration for jobs), the budget, the baseline, and a verdict. A trend with no successful sample shows as not measured. It is never shown as a pass.
- **The k6 summary** in the load-test step gives each request type's p95 overall and per stage (sustained and peak), with sample counts.
- **"Where the time went under load"** prints the following when a run fails:
  - CPU and memory per container, every 20 seconds during the load;
  - the database's busy sessions: MySQL's process list, or SQL Server's longest requests with their reads;
  - the PHP stack of every request that ran longer than 3 seconds (the PHP-FPM slow log).
- **"List query plan (MySQL)"** prints the list query's plan and statistics on the loaded dataset, before and after the statistics are rebuilt.
- The artifact `performance-<engine>` keeps the raw files: `k6-summary.json`, `perf-measure.json`, `perf-render.json`, `perf-diag.txt` and the comparison results.

## Results

The latest full-scale run with every budget reported is run [41028678338](https://github.com/moalmansor/Core-Low-Code-Framework/actions/runs/41028678338), on commit `840ee16`, at 10 requests a second sustained and 30 at peak.

| Budget | MySQL | SQL Server | Budget |
|---|---|---|---|
| Permission resolution, cached | 1.0 ms | 0.9 ms | 5 ms |
| Permission resolution, cold | 22 ms | 43 ms | 50 ms |
| Formula set, server / client | 2.5 ms / 1.6 ms | 2.9 ms / 1.6 ms | 20 ms / 10 ms |
| Form render, 100 fields | 177 ms | 118 ms | 1.5 s |
| Import 100 000 rows | 429 s | 371 s | 600 s |
| Download, 3 levels, 100 000 rows | 182 s | 281 s | 300 s |
| Additive publish, 1 000 000 rows | 3.9 s | 1.7 s | 30 s |
| Error rate under load | 0 % | 0 % | < 0.1 % |
| Request p95s (definition, view, open, list, search, save) | 27 to 42 s | 11 to 19 s | 150 to 800 ms |

The job, permission, formula and browser budgets pass on both engines. The request budgets do not pass yet at the scaled load:
- On MySQL, the record list query holds all four cores from the start of the sustained stage. The investigation is in ADR-0073 and `docs/progress.md`.
- On SQL Server, the database is mostly idle between peaks.

No baseline is committed until a run passes every budget. A baseline taken from a failing run would hide later regressions.

See `docs/progress.md` for the latest state.
