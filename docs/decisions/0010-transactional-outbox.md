# ADR-0010: Transactional outbox for after-commit side effects

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

Notifications, automations, and webhooks must fire if and only if the data change committed. Queues are in Redis, outside the database transaction.

## Decision

Domain events that leave the transaction are written to `outbox_events` in the same transaction and relayed after commit by a poller using `SKIP LOCKED`/`READPAST`.

## Consequences

This guarantees at-least-once delivery without phantom notifications. Consumers are idempotent, keyed by event id.
