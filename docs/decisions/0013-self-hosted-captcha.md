# ADR-0013: Self-hosted proof-of-work CAPTCHA

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

External forms need CAPTCHA (§4.32). Third-party CAPTCHAs add egress, tracking, and CSP exceptions.

## Decision

Use a self-hosted proof-of-work challenge (ALTCHA-style, HMAC-signed challenge, verified server-side), combined with rate limits and submission throttles.

## Consequences

It needs no third-party calls, works in restricted networks, and is privacy-preserving. Admins can add stricter verification (email/SMS) per form.
