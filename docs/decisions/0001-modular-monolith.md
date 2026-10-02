# ADR-0001: Modular monolith

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

The specification fixes Laravel + Vue and requires transactional guarantees across forms, workflow, audit, and journal on every record write. Phases must add modules without reworking earlier ones.

## Decision

One Laravel application organised as modules under `app/Modules/<Module>`, each with its own models, services, policies, events, routes, migrations, and tests. Modules talk through `Contracts\` interfaces and domain events only; architecture tests enforce the boundaries.

## Consequences

A single deployable keeps the Record Pipeline in one database transaction and keeps the Docker/Codespaces setup simple. Microservices would add distributed-transaction problems the specification forbids (no lost data, no duplicates). The cost is discipline: the boundaries are enforced by tests, not by process isolation.
