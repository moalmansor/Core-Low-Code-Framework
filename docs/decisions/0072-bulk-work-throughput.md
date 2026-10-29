# ADR-0072: Bulk-work throughput: parallel import batches, batch memo, one audit chain per batch

- Status: Accepted (Phase 6)
- Date: 2026-10-29

## Context

The Phase 6 performance work measured the import budget of architecture §20.2 (100 000 rows, validation and insert, in 10 minutes or less). The first measurement imported about 25 rows a second, so 100 000 rows would take over an hour. Timing each stage of a record save showed where the time went:

- **Every row was validated twice.** The import checked the row, then the save checked it again.
- **The same metadata was read for every row.** Each save re-read the form's duplicate rules, notification rules, automation conditions, assignment rules and default working calendar. Building the record observers' payload also constructed the automation and notification engines from the container on every save, even for forms with no conditions: about 5 ms per record.
- **Each event was relayed on its own.** Every record event was relayed in its own transaction after commit. Each relay queued three jobs (automations, notifications, webhooks), even when the form had no automations or rules and the organization had no webhooks.
- **A batch held every audit chain.** An import batch is one transaction of 500 rows. Audit entries pick one of 16 hash chains by object, so a batch locked all 16 chain heads until it committed. While an import ran, every other audit write in the system waited behind it.
- **Rows were saved one at a time.** Even after these fixes a save costs about 9 ms, most of it spent waiting on about 15 database round trips. One process cannot do much better.

## Decision

1. **Validate once.** A real import run leaves validation to the save, which reports the same errors. A dry run, and any row with conversion errors, still runs the full check.
2. **`BatchMemo`.** Metadata reads of a bulk unit of work are remembered for that unit only: one import batch of 500 rows.
   - Outside a unit nothing is remembered, so an ordinary request always reads current rules.
   - Security checks never use it: permissions, maintenance mode and record scope are read every time.
   - A rule changed by an administrator takes effect from the next batch.
3. **Cheap guards before costly work.**
   - The observers resolve the automation and notification engines only when the form has conditions to watch.
   - The relay queues an automation, notification or webhook job only when the form has enabled automations, the form has active notification rules, or the organization has an active webhook.
4. **One relay per transaction.** The outbox relays all of a transaction's events after it commits, in one pass. The scheduled relay remains the safety net.
5. **One audit chain per batch.** `AuditWriter::onChain()` puts every entry a batch writes on one chain (batch number mod 16). Any chain assignment is valid for the verifier, which walks each chain on its own. A batch now holds one chain head, not sixteen.
6. **Parallel insert imports.** An insert-mode import of more than one batch writes each batch's rows to a file and queues one `RunImportBatchJob` per batch on the `heavy` queue, so the heavy workers share it.
   - Each batch writes its row in `import_job_batches` first in its own transaction, together with its counts. A batch delivered twice stops at the unique key.
   - A deadlocked batch is run again (it changed nothing).
   - The import's counters are updated after each batch commits, as progress only. They are not updated inside the batch: every journal row holds a shared lock on the import's row through its foreign key, so batches updating that row would deadlock.
   - The last batch to finish recomputes the totals from `import_job_batches` and finishes the import, under a named lock, exactly once.
   - Update and upsert imports stay in order, because a later row may depend on an earlier one.
   - The heavy worker count is `HORIZON_HEAVY_PROCESSES` (default 4: the reference worker's CPUs).

7. **Fewer round trips per record.** A save made 14.5 queries per row; it now makes about 10.5. Each saving is safe by construction:
   - A new record's repeating sections are not looked up (it has none yet).
   - A journal entry opened just now skips the crash-recovery lookup (there was no earlier attempt).
   - The outbox marks a relay pass's events dispatched in one statement.
   - The audit chain head is still locked, read and updated per entry. Keeping it in memory for a batch would break the chain when a record's save rolls back after its audit entry.
8. **Exact totals.**
   - While the import runs, a batch's progress write sets the import's counters to the sums of its committed batches.
   - Writes are refused once the import is finished.
   - The finisher writes the final sums again after finishing, so a late write never inflates the totals. The first full-scale run reported 100,500 rows for 100,000 before this.
9. **Every heavy job on the heavy workers' connection.** The retention run and the personal-data search named the `heavy` queue on the default connection, which no worker reads, so on the Docker stack they never ran. Both now use `redis_heavy`, and a test (`tests/Unit/QueueRoutingTest.php`) keeps every heavy job on it.

## Consequences

- Locally (4 shared vCPU) the import went from 25 to about 180 rows a second.
- In the first full-scale CI run (before the changes in 7 and 8), 100 000 rows took about 7 minutes on MySQL and 12.6 minutes on SQL Server. Each SQL Server round trip costs more (ODBC), which is why 7 matters most there.
- The performance workflow measures the import on both engines against the full dataset (docs/performance.md).
- Rows of a parallel import are not created in file order. Record numbers, ids and creation times interleave between batches. The error report is still in row order.
- Duplicate rules compare against committed records. Two rows in different batches that duplicate each other can both be saved, as with two people saving at the same moment.
- A notification, automation or webhook created while a record event is being relayed applies to the next event.
