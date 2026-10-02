# ADR-0012: No outbound calls during authentication

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

Breached-password APIs would be an outbound dependency in the login path, and all egress must go through the gateway.

## Decision

Use an offline common-password list bundled with the application for the password policy. Authentication makes no outbound HTTP calls (SSO/OIDC flows are browser redirects plus token exchange through the egress gateway).

## Consequences

Login stays available when third parties are down, and no credential-derived data leaves the system.
