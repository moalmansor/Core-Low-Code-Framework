# ADR-0031: The workflow is versioned with the form; removed statuses are mapped at publish

- Status: Accepted (Phase 3)
- Date: 2026-10-10

## Context

Specification §4.10 requires safe, versioned changes and §4.12 a workflow per
form. A status change is a schema-like change: records sit in statuses, and
transitions, SLA timers, history and access rules point at them. Editing the
live workflow in place would let one administrator strand records in a status
that no longer exists, or change a running process halfway through a save.

## Decision

- Statuses, transitions and SLA rules are part of the form's draft. They are
  saved with the draft's concurrency check, compiled into the definition
  (`definition.workflow`), validated with the rest of the draft, and applied
  only when the form is published. Every version keeps the workflow it was
  published with; rollback and duplication carry it.
- Blocking problems: not exactly one initial status, a transition out of a
  final status. An unreachable status is a warning.
- A published status, transition or SLA rule is never deleted: it gets
  `archived_at` (added to `transitions` and `sla_rules`; `statuses` already had
  it), so history and audit keep their names. Saving an entry with the key of
  an archived one restores that row instead of creating a new one.
- **Status mapping.** When the draft removes a status that holds records, the
  publish screen lists it with its record count and asks for a target status;
  publishing is blocked until every such status has one. The choices are
  pending `status_mappings` rows. Publishing turns them into `map_status`
  migration steps that run after the schema steps, inside the plan, and are
  reversed with it. Each moved record gets a `status_history` row with source
  `status_mapping`, one correlation id per batch. Records without a status move
  to the initial status the same way. After publish, adds, renames, merges and
  removals are recorded in `status_mappings` against the new version.

## Consequences

- Workflow changes follow the same impact, confirmation, snapshot and rollback
  path as field changes; nothing changes under a running user.
- `archived_at` on `transitions` and `sla_rules` is an addition to the ERD
  (architecture §10), made in this phase.
