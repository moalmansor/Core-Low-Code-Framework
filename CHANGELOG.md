# Changelog

All notable changes to this project are documented here, one section per phase.

## [Unreleased] — Phase 0: Architecture & Data Model

### Added
- `docs/architecture.md`: the complete architecture, ERD (147 tables), physical
  table and schema-change strategies, metadata JSON schema, permission resolution,
  relation traversal, extension model, performance budgets, API outline, frontend
  architecture, and technical decisions.
- `docs/expression-language.md`: the expression language specification.
- `docs/conformance/expression-corpus.json`: the shared conformance corpus (169 cases).
- `docs/decisions/`: ADR-0001 to ADR-0020.
- `docs/design-coverage.md`: the Phase 0 design coverage check.
- `docs/progress.md`: phase status and resume point.

### Changed (owner review)
- Specification §4.11: precedence is user > role > department. Deny beats allow
  within a tier, a more specific tier overrides a less specific one, and a hard
  deny overrides every tier (ADR-0009).
- ERD fully expanded: exact MySQL 8 and SQL Server 2019 types, nullability,
  defaults, and named keys, indexes, foreign keys, and checks for all 147 tables.
