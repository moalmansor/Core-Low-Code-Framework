# ADR-0043: Automations — runs, run-as user, loop protection, waits and test runs

- Status: Accepted (Phase 4)
- Date: 2026-10-12

## Context

§4.31 asks for an automation builder with no code: triggers (record events,
field and status changes, a condition becoming true, schedules, a date
reached with an offset, an inbound webhook, a file in a watched folder,
manual), steps from the action library plus waits, and controls (enable,
run now, test against a sample record, concurrency, retry policy, loop
protection, a maximum affected-record count with confirmation above it), a
run history with failures in the Operations Center and a scheduled-task
manager. The ERD (§10.18) fixes the tables. Several details were open.

## Decision

- **One form per automation.** Every automation belongs to a form; its
  condition and steps work on that form's records. Steps reuse the action
  library (update fields, change status, assign, create a linked record,
  e-mail, in-app notification, document, download, webhook through the
  egress gateway) through the same step runner as actions, with the source
  `automation` on saves, the status history, e-mails and notifications.
- **What a run covers.** Record events, a date reached and a watched-folder
  file start one run for one record. A schedule or a manual run covers every
  record that meets the condition at that moment. If more records match than
  the automation's maximum, the run does not start (it is recorded as failed
  and says why). If more match than "confirm above", the run waits for
  someone with "Run automations manually" to confirm or cancel it.
- **Run as.** Every run acts as a user, so permissions, record rules, field
  access and status access apply to each step exactly as for that person.
  The choices are "the person who caused the event" (record-event and
  manual triggers only) or a specific active user. Schedules, dates and
  watched folders have nobody who caused them, so they need a specific user.
  A dedicated service account is an ordinary user an admin creates and
  grants roles to. The ERD's `system` value stays unused. Setting up an
  automation that runs as someone else, or changing one that does, needs
  "Manage permissions", because otherwise an automation manager could act
  with another person's rights.
- **Loop protection.** Events caused by a run's steps carry that run (an
  outbox context). A run started by such an event is one level deeper. Past
  the automation's maximum chain depth (default 3) the run is recorded as
  `skipped_loop`, linked to the run that caused it, and audited.
- **Waits and retries.** "Wait" (a delay) and "wait until" (a condition with
  a timeout, then stop or continue) park the run, which the automation clock
  resumes when due. Waits are only allowed when every trigger runs for one
  record, so a set of records never waits as a block. A failing step can
  stop the run, continue, or retry on the automation's retry policy (up to
  `maxAttempts`, with the listed back-off seconds). A failed run can be
  retried by hand: the records whose steps failed run again from their first
  step, along with any records the run had not reached.
- **Concurrency.** At most `concurrency` runs of an automation execute at
  once; others stay queued briefly. A long run continues in a fresh job
  every ~50 seconds, so no worker holds it past its time limit. A run still
  "running" an hour after its last progress has lost its worker and is
  marked failed; it can be retried.
- **Test run.** A test runs against one sample record. Data steps run inside
  a transaction that is always rolled back. E-mails, notifications,
  webhooks, documents and downloads, which a rollback cannot undo, are only
  described. Waits are noted and skipped. Tests work on a disabled
  automation, so it can be tried before it is turned on.
- **After a delete.** A record that was deleted can still be named in a
  message or sent to a webhook, but not changed. Automations with the
  "record deleted" trigger allow only those steps.
- **Downloads.** The automation download step runs a shared profile of the
  form for each recipient (users and roles), under that recipient's own
  permissions, and e-mails the file. It covers either the record or the
  profile's own records. `download_jobs` gains `automation_run_id` and
  `deliver_by_email`, which scheduled downloads now use too.
- **Watched folders.** The operator mounts a folder at `WATCHED_FOLDERS_ROOT`
  (the `watched` disk). Admins name a folder inside it (letters, digits,
  dashes and underscores, separated by `/`; nothing can climb out of the
  root). Each file left alone for 30 seconds is checked like an upload (the
  file field's types and size, malware scan) and becomes a record with the
  file in the chosen file field. Its name can optionally go into a text
  field, since many forms require a title. The record is saved as the
  run-as user, and a run starts for it. The file then moves to `processed/`,
  or to `failed/` with a note naming the fields that were wrong.
- **Inbound webhooks** arrive with inbound endpoints (Phase 5, §4.33). The
  trigger type exists in the schema but is not offered until then.
- **Scheduled-task manager.** It lists `scheduled_tasks`: automation
  schedules, scheduled downloads, and the system's own scheduled jobs. The
  system jobs are recorded as they run (kind `system`), with their last
  result, duration and next time. `scheduler:run` never runs them itself.

## Consequences

Automations never do more than the user they run as could do by hand, and
a chain of automations cannot run away. Admins can try an automation safely
before turning it on. Inbound-webhook triggers remain for Phase 5.
