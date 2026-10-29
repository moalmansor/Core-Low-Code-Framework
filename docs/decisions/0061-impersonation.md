# ADR-0061: Impersonation

- Status: Accepted (Phase 5)
- Date: 2026-10-23

## Context

Specification §4.36 and §5 allow an administrator with the permission to act as another user to reproduce a problem. The rules:
- a persistent banner shows it;
- the session is time-limited;
- sensitive actions can be blocked during it;
- every action is recorded as performed by the administrator impersonating the user;
- it can never escalate privilege.

Architecture §7.7 adds three more:
- no target holding a permission the impersonator lacks;
- no Super Admin unless the actor is one;
- blocked actions configured in `settings.security.impersonation_blocked_actions`, and audit entries carrying the actor, the subject and `impersonation_session_id`.

## Decision

**Starting** (`POST /users/{uuid}/impersonate`, "Impersonate users")
- Needs a reason (10–500 characters) and a duration of 5 minutes up to `security.impersonation_max_minutes` (default 60, at most 480).
- Refused when:
  - the target is the actor, inactive, or a Super Admin and the actor is not;
  - the target holds any permission the actor lacks (the existing `EscalationGuard`);
  - the actor is already impersonating.
- An `impersonation_sessions` row records both people, the reason, the address, the browser and the expiry.
- The session id is regenerated and the session is switched to the target without firing sign-in events. No sign-in happened, so the target's session limits and sign-in bookkeeping are not touched.
- The session row carries `impersonation_session_id`.

**During** (`GuardImpersonation`, in the `lcf.session` group)
- At the time limit the impersonation ends (`expired`). The administrator's own session is restored, and the request answers 409 `impersonation_ended` so the screen reloads as the administrator.
- The impersonated person's account security is never reachable: password, second factor, personal tokens, sessions, trusted devices, profile changes, and starting another impersonation.
- The categories chosen in the security settings are refused with 403 `impersonation_blocked`. They are deleting or merging records, exporting or downloading data, running custom actions, and changing users, roles and permissions; all four are blocked by default.
- The audit trail records the administrator as the actor, the user as the subject, and the impersonation session on every entry written during it.
- The administrator is the one at the keyboard, so their access policies apply, not the user's, and the user's 2FA-enrolment and password-expiry gates do not stop them.
- The banner is shown on every page from `/me` (`impersonation: {by, reason, expires_at}`). It cannot be dismissed, only stopped.

**Ending**
- `POST /impersonation/stop` (reachable whatever gates the user's account would meet) ends it (`manual`) and signs the administrator back in as themselves.
- Signing out ends it (`logout`).
- Each start and end is audited.

## Consequences

- Records created during an impersonation carry the user as their author, as they would have had the user acted. The audit trail shows who really acted.
- Being signed in as the target and not the administrator means every permission check is the target's own, which is what reproducing a problem needs, and cannot exceed the administrator's because of the no-escalation rule.
