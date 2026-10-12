# ADR-0041: Actions — built-ins, custom steps, runs and permissions

- Status: Accepted (Phase 4)
- Date: 2026-10-12

## Context

§4.15 asks for built-in actions (bulk delete, bulk status change, bulk field
update, print, duplicate) and no-code custom actions whose chained steps
update fields, change status, send email, call a webhook, generate a
document and create a linked record; each action placed on a row, a
selection, the toolbar or the record page, with its own permission, an
optional confirmation, and background execution for heavy runs. It does not
say how built-ins are configured, how a run combines justification rules,
what an action may do beyond the user's own rights, or how a run is
followed.

## Decision

- **One document per form.** A form's actions are a configuration document
  saved whole with a concurrency hash (ADR-0033), edited on the form's
  "Actions" tab. Built-ins are actions too: the admin adds one, places it
  (duplicate and print on rows and the record page; bulk update, status
  change and delete on a selection or the toolbar), labels it per language
  and gives it the same settings as a custom action. Export and import stay
  governed by the form's own abilities (§4.15 import/export, ADR-0038) and
  are not configured here.
- **Permissions.** Each action registers `action.{uuid}.run` in the access
  matrix, under the form. Running needs that permission **and** the form
  ability its work needs: create to duplicate, print to print, edit to bulk
  update, delete to bulk delete, view otherwise. An action never widens
  rights: every change goes through the record pipeline, so field access,
  record scope, status access, claims, legal hold, validation, transition
  conditions and optimistic concurrency apply exactly as in the form. A
  record the user may not change is reported, not skipped silently.
- **Availability.** An optional condition (expression language) decides
  whether a record offers the action; it is checked when the menu opens and
  again on the server for every record of a run. Each step may have its own
  condition, evaluated on the record as the previous steps left it.
- **Steps.** In order, per record; each sees the changes of the steps before
  it. A failing step stops that record's remaining steps unless set to
  continue, in which case it leaves a warning on the run. Steps are not
  undone when a later one fails: each change is its own audited save, as if
  the user had made it. Besides §4.15's steps, "send an in-app notification"
  and "assign" are offered; assigning needs the user's own assign/reassign
  permission. Recipients include "the person running the action".
- **Justification.** One justification per run, at the strictest level any
  of its records needs: the action's own level, action-scope justification
  rules, the edit rules of the fields it sets, and the transition and delete
  rules it triggers. It is saved once with the record count and every
  change links to it.
- **Runs.** Every run is a `bulk_operations` row (type `action`, or the
  bulk type of a built-in, with `action_id`) holding the targets with the
  versions the user saw, the progress and a per-record result. A record
  changed since it was chosen is a conflict, never overwritten. Runs above
  the action's queue threshold (default `actions.queue_threshold`, 50) go
  to a background job; the starter follows progress, may stop it between
  records, and is notified when it ends. A run holds at most the action's
  maximum (default `actions.max_records`, 500). A retried job replays the
  journal by a per-record idempotency key instead of saving twice. Only
  the starter can see or cancel a run.
- **Webhooks.** Through the egress gateway only (allowlist, address policy,
  pinning, timeout, size cap). Placeholders in the URL and body take the
  record's values the runner may read. The secret and header values are
  stored encrypted, shown only as "set", and kept when the action is saved
  again. Requests are signed: `X-LCF-Timestamp` and `X-LCF-Signature:
  sha256=HMAC(secret, timestamp + "." + body)`. Network errors, 5xx and 429
  are retried up to three attempts with backoff; every attempt is audited
  (`action.webhook_called`, host, method, status, duration — never the
  body or headers).
- **Outputs.** A generated document goes into a file field of the record or
  is offered to the runner as a personal download that expires like other
  generated files; a linked record can be opened after the run.

## Consequences

- Built-ins and custom actions share placement, permissions, conditions,
  justification and run tracking, so there is one model to learn.
- Because actions run with the user's own rights, an admin cannot use an
  action to let users change what they otherwise could not.
