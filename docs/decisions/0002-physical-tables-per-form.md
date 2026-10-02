# ADR-0002: One physical table per form and collection

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

Specification §4.9 requires real tables, foreign keys, column types, and indexes per form, with versioned, reversible migrations.

## Decision

Each form gets `f_{key}`, each collection `c_{key}`, each repeater/inline sub-form a child table, each many-to-many relation a pivot `p_{key}`. Tables are created and changed by runtime migration plans (architecture §11–§12), not by Laravel migration files.

## Consequences

This meets the specification directly and gives the query planner indexed, typed columns, which the performance budgets need. EAV or JSON-blob storage was rejected because it cannot provide real foreign keys, typed indexes, or acceptable list performance at 1 M rows.
