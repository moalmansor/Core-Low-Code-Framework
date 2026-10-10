# ADR-0035: Justification scopes delivered in Phase 3, and how rules combine

- Status: Accepted (Phase 3)
- Date: 2026-10-10

## Context

Specification §4.24 lists justification scopes including actions, bulk
actions, imports and merges, which are delivered by later phases (§4.15,
§4.35). The ERD keeps columns for them.

## Decision

- Phase 3 enforces the scopes form, group, field, status, transition, delete,
  restore and reassign, and treats the builder's per-field and per-group
  "Change justification" setting as a rule for everyone. Bulk delete and
  restore use the delete and restore scopes once for the batch. The action,
  import and merge scopes, and `justification_rules.action_id` /
  `justifications.import_job_id`, arrive with those features.
- Per (scope, target) only the most specific subject tier present counts;
  across the rules that apply, the strictest level wins and constraints
  combine (longest minimum length, shortest maximum, intersected attachment
  types, smallest size limit).
- The prompt is returned with 422 `justification_required` (or
  `justification_invalid` with errors) after all other validation has passed,
  and the client resends the same change with the justification. The
  justification is saved in the same transaction as the change; the model
  refuses updates and deletes.
- Reason codes come from code sets managed under Justification reason codes,
  or from a collection's entries, listed in the prompt.

## Consequences

- No scope in the specification is dropped; the ones that depend on later
  features are scheduled with them.
