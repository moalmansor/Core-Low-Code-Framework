# ADR-0022: One-time setup token for the first-run wizard

- Status: Accepted (Phase 1)
- Date: 2026-10-02

## Context

Specification §2 requires a web setup wizard on first launch that creates the
Super Admin. Between deployment and setup, anyone who can reach the URL could
complete the wizard and own the installation.

## Decision

The wizard requires a one-time token obtained from the server console
(`php artisan setup:token`; the Docker entrypoint prints one while setup is
incomplete). Only its SHA-256 hash is stored (`settings: setup.token_hash`);
issuing a token replaces the previous one; it is checked with a constant-time
comparison on every setup call (header `X-Setup-Token`), rate limited, and
discarded on completion. After completion every `/api/v1/setup/*` endpoint
answers 404 and the command refuses to issue tokens.

## Consequences

Claiming a fresh installation needs console or log access to the server. The
step is recorded in the specification (§2).
