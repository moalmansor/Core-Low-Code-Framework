# ADR-0021: Deferred foreign-key columns

- Status: Accepted (Phase 1)
- Date: 2026-10-02

## Context

Several Phase 1 tables reference tables that later phases create (for example
`roles.application_id` → `applications`, `files.form_id` → `forms`). A foreign key
cannot point at a table that does not exist yet, and a column without its
constraint would allow orphan values.

## Decision

Such columns are not created in Phase 1. The migration of the phase that creates
the referenced table adds the column, its supporting index, and its named foreign
key together. The list is kept in one place (the ERD conformance test,
`tests/Feature/Database/SchemaConformanceTest.php`), which also asserts that a
deferred column does not exist early:

`organizations.theme_id` (P5), `departments.business_calendar_id` (P2),
`roles.application_id` (P2), `roles.access_policy_id` (P5),
`permission_assignments.condition_id` (P2), `sessions.external_user_id`,
`sessions.trusted_device_id`, `sessions.impersonation_session_id` (P5),
`files.form_id`, `files.record_id`, `files.field_id` (P2), `files.external_user_id` (P5).

## Consequences

The schema of every phase matches the ERD exactly except for the listed columns,
and no column ever exists without its constraint.
