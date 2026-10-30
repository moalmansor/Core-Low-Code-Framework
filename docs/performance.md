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

Run [41056582257](https://github.com/moalmansor/Core-Low-Code-Framework/actions/runs/41056582257), on commit `ae3b3bb`, **passed every budget on both engines**. It used the full §20.1 dataset, with k6 at 10 requests a second sustained, then 30 at peak.

| Budget | MySQL | SQL Server | Budget |
|---|---|---|---|
| Form definition load (p95) | 113 ms | 53 ms | 150 ms |
| Record view, record and 3 panels (p95) | 163 ms | 92 ms | 400 ms |
| Record open, 40 fields and 2 repeaters (p95) | 147 ms | 70 ms | 400 ms |
| Record list, 1 000 000 rows (p95) | 157 ms | 131 ms | 500 ms |
| Global search, 20 forms (p95) | 303 ms | 477 ms | 800 ms |
| Record save (p95) | 204 ms | 123 ms | 400 ms |
| Permission resolution, cached / cold | 0.8 ms / 15 ms | 0.5 ms / 23 ms | 5 ms / 50 ms |
| Formula set, server / client | 1.5 ms / 1.1 ms | 1.9 ms / 1.2 ms | 20 ms / 10 ms |
| Form render, 100 fields | 97 ms | 94 ms | 1.5 s |
| Import 100 000 rows | 276 s | 256 s | 600 s |
| Download, 3 levels, 100 000 rows | 175 s | 175 s | 300 s |
| Additive publish, 1 000 000 rows | 3.2 s | 2.0 s | 30 s |
| Error rate under load | 0 % | 0 % | < 0.1 % |

The request p95s above are from the sustained stage (ADR-0074). The peak stage, at 30 requests a second, is reported but not asserted:

| Request p95 at the peak | MySQL | SQL Server | Budget |
|---|---|---|---|
| Form definition | 160 ms | 3 257 ms | 150 ms |
| Record view | 283 ms | 3 817 ms | 400 ms |
| Record open | 236 ms | 3 462 ms | 400 ms |
| Record list | 295 ms | 3 785 ms | 500 ms |
| Global search | 760 ms | 5 446 ms | 800 ms |
| Record save | 393 ms | 3 760 ms | 400 ms |

On MySQL the peak stays within or close to budget. On SQL Server the runner, which also runs SQL Server itself, saturates at the peak, and the requests queue.

### Baselines

`perf/baseline/mysql.json` and `perf/baseline/sqlsrv.json` hold the values a later run is compared with for the 20 % rule.

The same code varies a lot from run to run on shared runners. The MySQL definition load measured 27 ms on `6d67213` and 113 ms on `ae3b3bb`, with no change to that code path. Each baseline value is therefore the slower of the two full-scale runs on the current code: run 41046313256 on `6d67213` and run 41056582257 on `ae3b3bb`. For SQL Server's global search, only the second run is used, because the first was before the fix to it.

Even so, a result can move by more than 20 % on noise alone. A regression verdict should be confirmed by re-running before anything is changed. Dedicated runners would make the 20 % rule a reliable signal.
