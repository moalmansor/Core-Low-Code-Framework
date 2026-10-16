# ADR-0038: Imports and large exports run as background jobs

- Status: Accepted (Phase 4)
- Date: 2026-10-12

## Context

Phase 2 imported spreadsheets synchronously inside the request, which issue #7
noted as a limitation: a large file held a PHP worker for minutes, could hit
the web server's timeout half-way, and had no progress, cancel or retry. The
specification (§4.15) requires a maximum file size, row count and time limit,
background jobs, a transaction per batch, idempotent retry, and an error
report naming the row and the reason.

## Decision

- **Import is three steps** in the dialog: upload and inspect (sheet, headers,
  row count, suggested mapping), map and preview (a dry run of the first rows
  through the full record pipeline), then start. Starting creates an
  `import_jobs` row and queues `RunImportJob` on the `heavy` queue.
- **Batches of 500 rows**, each in its own transaction. Every row goes through
  the same record pipeline as the form (validation, rules, access, optimistic
  concurrency), journaled with the import job, so a retried batch never
  inserts a row twice. Errors are written per batch and assembled into an
  `.xlsx` error report (row number, column, reason) on request.
- **Modes:** insert, update and upsert by a key field the admin chooses; the
  key must be unique within the records the importer may see.
- **Limits** are System Settings, not code: `records.import_max_rows`
  (default 100,000, at most 1,000,000) and `records.import_time_limit_minutes`.
  A job past its time limit stops between batches and reports how far it got.
- **Cancel** stops the job between batches; finished batches stay.
- **Justification:** an import into a form with an import justification rule
  asks once, before the job starts, and the reason is attached to every
  record the job writes (§4.24 "Imports take a justification for the batch").
- **Exports** honour the chosen view and columns; up to
  `records.export_sync_rows` rows (default 5,000) download at once, larger
  ones run as `RunExportJob` and are announced in the notifications center.
  Generated files are owned by their requester only and expire after
  `records.job_file_hours`.
- **Saved mappings** (`import_mappings`) are named and per form, shared by
  everyone who may import into it; their creator or a form manager may
  delete one.

## Consequences

- The old synchronous `POST /r/{form}/import` endpoint is removed; the API
  moves to `/r/{form}/imports/*` and `/r/{form}/exports`.
- Queue workers must run the `heavy` queue (the Docker stack's worker does).
