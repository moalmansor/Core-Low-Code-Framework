# ADR-0016: Phase numbering follows the specification

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

CLAUDE.md says work is delivered in seven phases (0–6). Specification §8.1 defines eight, including Phase 2.5 (Pilot & Validation).

## Decision

Follow the specification: phases 0, 1, 2, 2.5, 3, 4, 5, 6. Phase 2.5 has no branch of its own in CLAUDE.md. It will use `phase-2-5-pilot` unless the owner directs otherwise.

## Consequences

The specification is the contract and wins over other documents (CLAUDE.md, "It wins over any assumption"). The owner may want to update CLAUDE.md to match.
