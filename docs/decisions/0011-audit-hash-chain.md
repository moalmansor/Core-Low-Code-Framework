# ADR-0011: Sharded hash-chained audit log with time partitions

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

Specification §4.20 requires append-only audit entries with tamper-evident hash chaining. §4.26 requires time partitioning.

## Decision

Use 16 independent chains (`chain_id`), each serialized by a locked head row in `audit_chain_heads`. Each entry's hash is SHA-256(prev_hash ‖ canonical row JSON). Partition monthly with a PK of (id, occurred_at). The runtime DB user has INSERT/SELECT only on the table.

## Consequences

A single chain would serialize every write in the system. Sixteen chains keep contention low and still detect any modification. Partitioning keeps growth manageable, and archiving moves whole partitions together with their chain proofs.
