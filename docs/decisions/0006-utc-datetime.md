# ADR-0006: All timestamps in UTC with microsecond datetime types

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

MySQL `TIMESTAMP` stops at 2038 and converts by session timezone. SQL Server has no direct equivalent.

## Decision

Use `DATETIME(6)` on MySQL and `DATETIME2(6)` on SQL Server, always stored in UTC. Conversion to user, record, or field timezones happens in the presentation and expression layers.

## Consequences

This gives the same behavior on both engines with no 2038 limit and no hidden session-timezone effects.
