# ADR-0073: Lists and imports at full scale: bounded counts, id tie-breaks, fewer round trips per imported row

- Status: Accepted (Phase 6)
- Date: 2026-10-30

## Context

The first full-scale performance run (1 000 000 orders, 20 000 000 history rows, architecture §20.1) failed on both engines:

- **The load test collapsed.** About 98 % of requests failed, at about 8 requests a second instead of 50 and then 150. Requests queued until k6's 60-second timeout. On MySQL not one list request succeeded, and the server was still unresponsive 17 minutes after the load ended.
- **Cold permission resolution** took 4.7 s on SQL Server (budget 50 ms).
- **The 100 000-row import** took 1 288 s on SQL Server and 603 s on MySQL (budget 600 s).

Profiling each request type on local datasets of 50 000 and 100 000 orders showed three causes.

1. **Every list page counted every match.** The list endpoint ran an exact `count(*)` over the whole filtered result for its "1–25 of N" line. With the reference filter (three conditions within the user's record scope), that count reads every matching row: about 4 000 rows at 100 000 orders and about 40 000 at full scale. Lists are 30 % of the load. These counts occupied all 32 PHP workers, and everything behind them timed out.
2. **The sort could not use its index.** Sorting by a column appended `updated_at desc, id desc` as tie-breakers. An index on the sorted column ends in the id, but not in `updated_at`. So instead of walking the index and stopping after one page, the database read and sorted every match: 210 ms at 100 000 orders.
3. **Cold permission resolution** sent every permission key, more than 1 800 of them at 200 forms, as one `IN` list. SQL Server compiles such a list slowly (103 ms locally at 1 068 keys).
4. **An imported row cost ten round trips.** Each saved row paid for these queries, at about 1.5 ms each on SQL Server:
   - the application's state and the form's lock state;
   - a journal lookup, insert and update;
   - an audit-chain head read under lock, an insert and a head update;
   - the record insert;
   - the outbox insert.

In the CI run the Docker stack's MySQL also ran on its default 128 MB buffer pool. At full scale almost every read went to disk.

## Decision

1. **Bounded list counts.** A record list counts matches exactly up to `records.list_count_limit`, a setting in the admin console under Settings, Records (default 1 000, range 100 to 1 000 000).
   - Past the limit the API returns `meta.total` set to the limit and `meta.total_exact: false`. The list shows "1–25 of more than 1,000".
   - The pager keeps offering the next page while the current one is full.
   - A narrower filter brings back an exact count.
   - The count reads at most limit + 1 matches, whatever the size of the form.
2. **The id breaks sort ties.** When a list is sorted by a column, ties are broken by the record id in the sort's own direction. Without a sort, the newest records come first, as before (`updated_at desc, id desc`). A sorted page now walks the column's index and stops after one page.
3. **A list that is counted exactly is sorted from its matches.** Walking the sort column's index pays off only when matches are common. When few or no records match, the walk reads the whole form before it can stop. The second full-scale run showed this: the generated data leaves some top-level departments with no orders that match the reference filter. Their list requests each read all 1 000 000 orders, at 11 to 16 s per request, and held every worker.
   - The bounded count already tells which case applies. When it is exact (at most `records.list_count_limit` matches), the page query joins the counted matches, at most limit + 1 ids, and sorts only those. MySQL 8.0 and 8.4 materialize the matches first. At 1 000 000 orders, an empty result went from 3.8 to 6.9 s for the page query to none at all, and a few hundred matches take about as long as the count.
   - When the exact count shows that the requested page lies past the last match, no page query runs.
   - Past the limit, matches are common enough (more than one in `N / limit` records) for the index walk to stop early, and the list keeps it.
4. **Past the limit, MySQL is told to walk the sort column's index.**
   - MySQL chooses between walking the sorted column's index and starting from the record scope's departments and sorting every match. It decides from sampled statistics, and the choice sits on a knife edge.
   - In run 41036865112, the full-scale dataset as loaded chose the scope-first plan, at 902 ms per list. `ANALYZE TABLE` barely changed the statistics, but the plan flipped to the index walk, at 4.6 ms. Even after that, the list's p95 at 10 requests a second was 1.16 s, so the plan still flipped under load.
   - When the count is not exact and the first sort column has its own single-column index, the page query carries `JOIN_PREFIX` and `ORDER_INDEX` optimizer hints for that index (`DatabaseDriver::readInIndexOrder`).
   - The walk then reads at most about `25 × N / limit` index entries, since there are more than `limit` matches: about 25 000 at 1 000 000 records with the default limit. Most of the time it reads far fewer, whatever the statistics.
   - SQL Server already walks the index under the query's row goal, so its driver leaves the query unchanged.
   - Sorting by a linked field, or with no sort, keeps the optimizer's own choice.
5. **Permission resolution without a key list.** Computing all of a user's permissions reads every applicable grant without listing the keys. A single key (the explanation screen) still filters by key.
6. **Fewer round trips per imported row.**
   - **The form's publish lock is read once per batch** (`BatchMemo`). A batch's transaction holds the record table, so a publish that would lock the form waits for the batch to commit anyway. The application's state and maintenance mode are still read for every row: ADR-0072 keeps security checks out of the memo.
   - **Journal entries of a parallel batch are written once, with their outcome.** The batch's unique row in `import_job_batches` is written first, in the same transaction, so none of its idempotency keys can exist yet. The journal skips the lookup and writes each entry when the row is processed, rejected or failed: one insert instead of a lookup, an insert and an update. The entry still shares the batch's transaction with the record it describes.
   - **The batch's audit chain head is read once.** Inside a pinned batch (ADR-0072 item 5), the first entry locks the chain head and the lock holds until the batch commits. Later entries reuse the head the writer last wrote. Any rollback, a single row's savepoint included, makes the writer forget it, so the next entry reads the head the database kept. Inside the batch's transaction the entry needs no savepoint of its own, because the row's savepoint already covers the entry and the head update.
   - Together these take a row from ten queries to six (five without maintenance-mode reads). Throughput on SQL Server with two workers went from 64 to 117 rows a second before the journal and audit changes, which save one more query and one savepoint per row.
7. **Database memory in the stack.** `docker-compose.yml` passes `MYSQL_BUFFER_POOL_SIZE` (root `.env`, default `1G`) as MySQL's `--innodb-buffer-pool-size`. The deployment guide sizes it for the reference server.

## Consequences

- People see "more than N" on broad filters of large forms instead of an exact total that cost seconds to compute. Administrators can raise the limit, accepting slower lists.
- Records with equal values in the sorted column appear newest-created first rather than most-recently-updated first. The order is still stable from page to page.
- The parallel import test checks that every saved row has exactly one processed journal entry and that the import's audit entries form unbroken chains: consecutive sequence numbers, each linked to the one before, and each hash recomputing. A mutation that corrupts the remembered head fails it.
- A full `audit:verify --full` cannot run inside that test file: earlier tests' entries are removed while the chain heads remain. The test checks the entries the import wrote instead.
