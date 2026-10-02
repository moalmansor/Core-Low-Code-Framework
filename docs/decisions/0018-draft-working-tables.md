# ADR-0018: Drafts in working tables; published versions as immutable snapshots

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

The builder needs fast, granular autosave and undo, while history, diff, and rollback need exact immutable versions.

## Decision

Drafts are edited in `field_groups`, `fields`, and the related working tables. Publishing serializes the full definition into `form_versions.definition` (immutable). The runtime only ever executes published snapshots.

## Consequences

Editing stays relational and simple. History, diff, and rollback work on exact JSON snapshots, and records reference the version they were saved with.
