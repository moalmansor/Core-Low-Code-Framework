# ADR-0033: Per-form configuration documents saved whole with a concurrency hash

- Status: Accepted (Phase 3)
- Date: 2026-10-10

## Context

A form now has several configuration sets besides its fields: workflow, SLA,
record rules, justification rules, assignment rules, views, View Mode panels,
reference previews and print layouts. Each is a list edited on one screen.
CLAUDE.md requires that nothing is ever silently overwritten.

## Decision

- Each set is loaded and saved as one document through
  `GET/PUT /forms/{form}/<set>`. The GET returns the document and a SHA-256
  hash of its canonical JSON; the PUT carries `base_hash` and is refused with
  409 and the newer document when the stored one changed meanwhile. The
  editor then reloads instead of overwriting.
- Validation runs over the whole document and reports errors by path
  (`rules.3.condition`), which the editors show beside the entry.
- Versioned with the form (ADR-0031): the workflow and SLA. Live when saved:
  record rules, justification rules, assignment rules, views, panels,
  previews and print layouts — they change who sees and does what, not the
  data's shape, and every save is audited. The definition compiler still
  freezes access and justification rules into each version for history.
- Queues and reason codes are single objects: their updates carry
  `base_updated_at` and are refused with 409 when stale.

## Consequences

- All Phase 3 editors share one save bar and one conflict behaviour.
