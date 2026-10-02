# ADR-0017: Conformance corpus location

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

The corpus must live in the repository and be run by both the backend and frontend test suites.

## Decision

Store it at `docs/conformance/expression-corpus.json`, next to the language specification. The backend and frontend suites read it by relative path. In Phase 0 it is data only.

## Consequences

The corpus is language-neutral, versioned together with the specification, and not owned by either runtime.
