# ADR-0008: Expression language: JSON AST, decimal arithmetic, step bounds, shared corpus

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

Specification §4.7 requires one language, two runtimes, stored as an AST, with parity proven by a shared corpus.

## Decision

Use the language defined in `docs/expression-language.md`: decimal numbers (34 significant digits, division to 16 fractional digits, half away from zero), Unicode code-point strings with NFC normalization, Umm al-Qura Hijri, step-count bounds instead of wall-clock timeouts, and null-plus-diagnostic error semantics. The server parser is the reference.

## Consequences

Binary floating point, UTF-16 string lengths, and wall-clock timeouts would make the PHP and TS results drift apart. Each choice here removes one source of disagreement.
