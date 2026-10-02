# ADR-0007: Translatable content in one keyed table; shipped locales seeded

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

Specification §3 requires translations keyed by object and locale, with no AR/EN columns, so that a third language needs no schema change.

## Decision

All translatable attributes live in `translations (object_type, object_id, field, locale)`. Locales are rows in `locales`. `ar` and `en` are seeded as part of the default system settings, because the specification says they ship enabled; the seeding adds no business content.

## Consequences

Adding a language means inserting rows only. Fallback chains and the untranslated report become simple queries.
