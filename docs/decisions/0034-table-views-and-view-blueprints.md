# ADR-0034: Table views as relation paths; view blueprints apply directly

- Status: Accepted (Phase 3)
- Date: 2026-10-10

## Context

Specification §4.14 asks for per-role columns and filters, including fields of
linked forms, saved and shared views, totals and row options; §4.30 asks for
blueprints of views (moved to Phase 3).

## Decision

- **Paths.** A column or filter is a relation path: keys followed through
  lookups (`customer.city`), at most `RelationPaths::MAX_DEPTH` hops, plus
  system keys prefixed `@` (`@status`, `@record_number`, `@created_at`,
  `@updated_at`) and, for View Justifications holders, `@justification.*`.
  Each hop checks the user's form permission, field access and record scope
  on the linked form; values the user may not see are never sent.
- **Who uses a view.** Each view registers `view.{uuid}.use`; the matrix grants
  it. The first granted view by priority applies, else the default view.
- **Saved views** store state (columns, widths, filters, sort, page size,
  search) on top of a view; sharing targets everyone, roles, departments or
  users; a shared view is hidden from people who cannot use its view.
- **Bulk delete/restore** are `POST /r/{form}/bulk-delete|bulk-restore`, at
  most 200 items, one justification for the batch (§4.24).
- **View blueprints** (§4.30): saving a view as a blueprint snapshots its
  columns and filters as paths; instantiating creates a view on the chosen form
  after checking every path resolves there, and propagating applies new
  versions to linked views. Views are live configuration, so a view blueprint
  is applied directly, without the draft and publish of form blueprints.
- **Print** renders HTML on the server from the layout and the print-mode field
  rules; PDF is produced with mPDF using the bundled DejaVu Sans for Arabic
  shaping and right-to-left text. Every print is audited.

## Consequences

- One path resolver serves views, panels, previews, queues and blueprints.
