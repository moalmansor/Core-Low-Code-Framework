# ADR-0045: Operations Center — failures, retries, alerts and queue workers

- Status: Accepted (Phase 4)
- Date: 2026-10-13

## Context

§4.2 asks for one place to handle failures:

- an e-mail queue monitor with resend, edited recipients, cancel and the rendered HTML;
- failed submissions with retry, edited payload and discard with a reason;
- failed jobs across imports, exports, downloads, webhooks, scheduled tasks, hooks and document generation;
- health widgets;
- e-mail alerts to roles when failures exceed thresholds.

Every action must be audited.

The data was mostly already kept: the submission journal, the e-mail queue, each job table, and Laravel's `failed_jobs`. Several details were open, and building this exposed two problems with the queue workers.

## Decision

### Access

Everything in the Operations Center needs "Manage operations". It lives at Admin › Operations Center (`/admin/operations`).

### E-mails

- An e-mail is **stuck** when it is still pending more than `operations.stuck_email_minutes` after it was due, or has been sending for longer than that. A sending e-mail is never handed out again automatically, because that could send it twice.
- Resend always creates a new queued copy and keeps the original. Changing the recipients is a resend of one e-mail to the new list.
- Only pending e-mails can be cancelled.
- The rendered HTML is shown through the existing signed, short-lived HTML preview. It is served with a policy that allows inline styles only, inside a sandboxed frame.

### Failed submissions

- The list shows saves the system failed to complete: journal rows with a stack trace.
- Saves refused for a reason the person was shown (a validation error, a version conflict) are a separate "rejected input" view for reference. They are not counted as failures.
- **Retry:**
  - A retry runs the save again through the record pipeline, **as the person who submitted it**, with the same idempotency key. Their permissions, record scope, field access and the record's version all apply. A record is never created twice, and a stale update is refused rather than overwriting.
  - Duplicate warnings count as already answered by the submitter. Block rules still apply.
  - A submitter who is no longer active cannot be acted for.
- **Editing values:**
  - Values can be corrected before a retry. They are kept in `edited_payload` and the original is never changed.
  - Values of sensitive or encrypted fields are shown masked. A masked value left as it is keeps what was submitted.
- **Discard** needs a reason of at least 3 characters.

### Failed jobs

- The failures of the last 30 days are gathered into one list from each kind of work:
  - imports, exports and custom downloads;
  - action runs and data-quality scans (`bulk_operations`);
  - automation runs;
  - scheduled tasks;
  - outbound webhook calls (from the audit log);
  - the queue's `failed_jobs`.
- **Retry:**
  - Imports, exports and downloads are queued again.
  - An action run retries only the records that failed.
  - An automation run uses its own retry.
  - A scheduled task is moved to run now.
  - A queue job uses `queue:retry`.
  - A webhook call is retried through the run it belongs to.
  - System tasks run on their own schedule.
- **Dismiss** hides a failure from the list with a mandatory reason, stored in the new `operations_dismissals` table. The failure row itself is kept.
- Document generation and hooks run inside actions and automations, so their failures appear there.

### Health

The health widgets show:

- connectivity to the database, the cache, Redis (when used) and the virus scanner;
- an SMTP connect and hello, without sending anything;
- waiting jobs per queue;
- stuck and failed counts;
- when the scheduler last ran one of its every-minute commands;
- how full the storage disk is.

### Alerts

- Rules are kept in `operations_alert_rules` and edited as one document with `base_hash`. Each rule sets a measure, a threshold, the period it counts over (event measures), a quiet period and the roles to alert.
- `ops:check-alerts` runs every five minutes. For each rule whose measure has reached its threshold, it queues one e-mail per locale to the active members of the roles, in their own language, with the source `alert`. It then records `last_triggered_at` and audits the alert.
- `failed_integrations` waits for the integrations of Phase 5 and is refused until then.

### Audit

Every action is audited in the `operations` category:

- `operations.email_resent`, `operations.email_cancelled`;
- `operations.submission_edited`, `operations.submission_retried`, `operations.submission_discarded`;
- `operations.job_retried`, `operations.job_dismissed`;
- `operations.alert_rules_saved`, `operations.alert_triggered`.

### Queue workers

- Horizon had no workers for the `heavy` and `mail` queues, so imports, exports, downloads, scans and e-mails queued in production would never have run. Supervisors `heavy-1` and `mail-1` were added.
- The `redis` connection handed a running job out again after 90 seconds, while action runs may take up to an hour, so a long run could be executed twice.
- The fix:
  - The `redis` connection now waits 3,700 seconds before handing a job out again.
  - Heavy jobs use a new `redis_heavy` connection that waits 7,300 seconds, longer than their two-hour timeout.

## Consequences

- Operators can recover from failures without database access, and nothing they do bypasses the permissions of the person a save belongs to.
- A worker that crashes holds its job for up to an hour (two for heavy jobs) before it is retried. That is the price of never running a long job twice.
