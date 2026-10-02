# ADR-0014: Crypto-shredding for personal data in immutable logs

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

Anonymization (§4.26) must remove identifying values, but audit entries are immutable and hash-chained (§4.20).

## Decision

Values of fields flagged as personal data are stored in audit `changes`, journal payloads, and merge snapshots encrypted with a per-record subject key (`subject_keys`). Anonymization overwrites the live record and destroys the subject key.

## Consequences

The ciphertext, and therefore every chain hash, stays unchanged, while the personal values become unrecoverable. The sequence of events stays intact as the specification requires.
