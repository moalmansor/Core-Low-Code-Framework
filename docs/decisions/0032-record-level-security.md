# ADR-0032: Record-level rules — default "all", tier walk, compiled custom scopes

- Status: Accepted (Phase 3)
- Date: 2026-10-10

## Context

Specification §4.11 lists record-level permissions (own, own department,
department tree, all, custom conditions) and the precedence rules (user > role
> department; deny beats allow within a tier; a more specific tier overrides a
less specific one; hard deny always applies). The default when no rule exists
was not stated, and custom conditions are expressions (§4.7) that must filter
lists in the database, not in memory.

## Decision

- Default: with no rule for any of the user's subjects, the form permission
  alone grants every record (system default scope `all`). This keeps Phase 2
  forms working unchanged and makes record rules opt-in.
- Resolution per operation (view, edit, delete; a rule for `all` counts for
  each): walk the tiers everyone → department → role → user; the most specific
  tier that has rules replaces the result of the less specific ones; within it
  allows are unioned and denies are subtracted. Hard denies from any tier are
  subtracted last. Scopes: own, own department, department tree, assigned,
  all, none, custom.
- Custom conditions are compiled by `ScopePredicate` into parameterised SQL
  over the form's table (columns, operators, literals, `user.*` context, and
  the functions it can translate). A condition it cannot translate is refused
  when the rule is saved. If a stored condition ever fails to compile, the
  scope fails closed (no records). A denied custom scope is applied as a
  `NOT EXISTS` exclusion.
- `RecordScope` applies the result to every query that reads records: lists,
  single records (404 when outside the scope), exports, bulk operations,
  panels, previews, print, the status history and My Work.
- Delegation (ADR-0036) unions the delegator's scope for the forms the
  delegation covers.

## Consequences

- One algorithm serves permissions, field access and record scope, as
  architecture §16 requires.
- Some expressions valid in field rules are not valid as record scopes; the
  editor reports this at save time.
