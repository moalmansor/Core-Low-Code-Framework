# ADR-0023: Privilege-escalation safeguards for people management

- Status: Accepted (Phase 1)
- Date: 2026-10-02

## Context

`system.manage_users` is not a "dangerous" permission, yet editing a user's
e-mail and sending a password link, resetting 2FA, or assigning roles can be
used to take over a more privileged account or to grant oneself power.

## Decision

`App\Modules\Access\EscalationGuard` enforces, server-side:

1. a user can be managed only by someone holding every permission that user holds;
2. a role or department can be given only if every permission it allows is held
   by the administrator giving it;
3. nobody changes their own roles or department;
4. giving an administrative role or one that allows a dangerous permission needs
   step-up confirmation (fresh TOTP or recovery code).

The lockout guard (an active Super Admin must always be able to manage
permissions) continues to wrap every change. Holders of `system.manage_permissions`
can still change grants directly, with step-up for dangerous grants and hard denies.

## Consequences

People administration can be delegated safely. Recorded in specification §4.11.
