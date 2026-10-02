# ADR-0019: Engine-neutral uniqueness for nullable and conditional keys

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

SQL Server unique indexes allow only one NULL. MySQL has no filtered indexes.

## Decision

Sparse rule tables use a `rule_hash` column (unique) over the identifying tuple. Uniqueness that should apply only to some rows (one held publish lock, one active queue claim) uses a nullable key column that is NULL when inactive: SQL Server gets a filtered unique index (`WHERE col IS NOT NULL`), and MySQL a plain unique index (which ignores NULLs).

## Consequences

This gives the same semantics on both engines and is covered by driver contract tests.
