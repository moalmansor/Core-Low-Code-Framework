# ADR-0003: Stable UUID identity for metadata and records

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

Configuration packages, blueprints, environment drift comparison, and public links all need identifiers that stay the same across environments. Auto-increment ids do not.

## Decision

Every metadata table and every record table has a `uuid` column (UUIDv7, unique) next to its bigint `id`. Metadata JSON refers to other objects only by uuid. APIs and URLs expose uuids; internal joins use ids.

## Consequences

Using uuids makes packages portable and drift comparison exact, and it avoids exposing sequential ids. Authorization, not obscurity, still prevents IDOR (architecture §7.2). Keeping bigint ids for joins keeps indexes compact.
