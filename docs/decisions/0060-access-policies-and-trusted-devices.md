# ADR-0060: Access policies per role and trusted devices

- Status: Accepted (Phase 5)
- Date: 2026-10-23

## Context

Specification §4.36 asks for access policies per role: IP allowlists, permitted time windows, maximum concurrent sessions, session lifetime, device trust, and a stricter policy for administrative roles. The architecture (§10.24) fixes `access_policies`, `trusted_devices`, `roles.access_policy_id` and `sessions.trusted_device_id`. It says a user's effective policy is "the strictest combination across roles". The specification does not say how a device becomes trusted, what happens when a user is over the session limit, or how an administrator avoids locking themselves out.

## Decision

**Who is governed**
- A role has at most one policy (`roles.access_policy_id`). A user is governed by the policies of their active roles.
- A policy marked **administrative** also governs everyone who holds an administrative role (`roles.is_admin_role`), whatever other policies they have. This is the "stricter policy for administrative roles".

**The strictest combination**
- Every non-empty address list must match the request address (IPv4 or IPv6, CIDR ranges).
- Every non-empty set of time windows must contain the current moment. A window has days (ISO, Monday = 1), a from and to time, and a time zone. A window whose end is before its start runs past midnight and belongs to the day it starts.
- The smallest concurrent-session limit, idle limit and lifetime win. The system-wide session settings remain an upper bound.
- A second factor is required when any policy requires one or requires trusted devices.

**Where it is enforced**
- At sign-in, after the password check: outside the addresses or hours, sign-in is refused with the reason, and the attempt is logged with the reason.
- On every signed-in request (`EnforceAccessPolicy` in the `lcf.session` group): outside the addresses or hours, or on an untrusted device when one is required, the session ends. The answer is 401 `access_policy` with the reason (`ip`, `time`, `device`), and the denial is audited. The sign-in page shows a translated message for the known reasons only, never text taken from the address.
- Session limits tighten the existing idle and absolute timeouts.
- The concurrent-session limit is applied when a sign-in succeeds: the user's oldest other sessions are ended, so the new sign-in plus the newest others fit, and this is audited.
  - We chose this over refusing the new sign-in, because a person cannot end a forgotten session without signing in.

**Trusted devices**
- A browser carries a random `lcf_device` cookie (encrypted, HTTP-only, same-site Lax). Only an HMAC-SHA256 of its value, keyed with the application key, is stored.
- A device becomes trusted for 90 days when a second factor is verified on it while a policy asks for trusted devices:
  - the two-factor challenge;
  - confirming a new two-factor setup;
  - or an identity provider's multi-factor sign-in (`amr`).
- Until the user has a second factor, enrolling one is required first; the 2FA enrolment gate already enforces this.
- People see and forget their trusted devices in their profile. Forgetting one makes it ask again.

**No self-lockout**
- Saving a policy is refused when, applied, it would shut out the administrator saving it from where and when they are working: their address or the current time.
  - An untrusted device is not counted, because it is fixed by verifying a second factor.

## Consequences

- Policies are configured in the Admin Console (Access policies) with "Manage access policies". Changes take effect on the next request.
- Sessions that existed before a trusted-device policy was applied end on their next request, once, and the user signs in again with their second factor.
