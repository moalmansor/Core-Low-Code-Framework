# ADR-0015: Separate database principals for framework migrations and runtime

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

Least privilege argues for a DML-only runtime user, but publishing forms requires runtime DDL.

## Decision

Framework migrations run as a migration principal. The runtime principal has DML on framework tables (INSERT/SELECT only on immutable tables) plus DDL rights limited to generated tables (`f_`, `c_`, `p_` prefixes) where the engine allows: on SQL Server a dedicated schema; on MySQL, privileges granted per table at creation by the migration principal through a narrow stored procedure. Details go in the Phase 6 deployment guide.

## Consequences

This limits the damage from a compromised application user while keeping no-code publishing possible.
