# ADR-0004: Organization column everywhere, with a platform root

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

Specification §4.27 offers single-organization and multi-organization modes, chosen at setup, with a global administrator above organizations.

## Decision

Every tenant-owned table has `organization_id NOT NULL`. Seeders create one platform organization (id 1). In single mode the wizard renames it and all data lives there. In multi mode tenants are child organizations, and platform users with Super Admin are the global administrators. A global Eloquent scope and the Query Planner always filter by organization.

## Consequences

One schema serves both modes. Avoiding NULL tenants removes a class of filtering bugs. Seeding the platform organization is part of the default system settings, not business data, so it is allowed under §2.
