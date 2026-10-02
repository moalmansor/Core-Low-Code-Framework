# ADR-0009: Permission and access precedence

- Status: Accepted (owner decision on the Phase 0 PR, 2026-10-02)
- Date: 2026-10-02

## Context

The original specification §4.11 stated two incompatible orders. *Precedence* said
"user overrides department, which overrides role". *Resolution model* listed
"… department → role → specific user", which puts role above department. The
first Phase 0 draft adopted user > department > role and asked the owner to decide.

## Decision

The owner decided, and specification §4.11 now states:

1. Precedence is **specific user > role > department** (> everyone).
2. **Within the same tier, deny beats allow.**
3. **A more specific tier overrides a less specific tier, including that tier's deny.**
4. A **hard deny** (`effect = hard_deny`) cannot be overridden by any tier.

One algorithm (architecture §16.2) applies to permission grants
(`permission_assignments`), field and group access (`field_access_rules`), and
record scopes (`record_access_rules`). Tiers are applied from least to most
specific. A tier's allows replace the value from lower tiers, its denies then
restrict it within the tier, and every hard deny is applied last as a ceiling.

For field access, a tier is the full specificity vector (target: form < group <
field, status: any < specific, mode: any < specific, subject: everyone <
department < role < user), matching the specification's resolution order. When
several roles tie in one tier, the most permissive allowed level wins, because
roles are additive. A `deny` rule caps the level ("at most read-only").

Each of the three rule tables has `effect enum<allow, deny, hard_deny>` (the
earlier `is_deny` flag on `field_access_rules` is replaced).

Truth table for a permission:

| Department | Role | User | Hard deny anywhere | Result |
|---|---|---|---|---|
| allow | — | — | no | granted |
| allow | deny | — | no | denied (role overrides department) |
| deny | allow | — | no | granted (role overrides department's deny) |
| — | allow + deny (two roles) | — | no | denied (deny beats allow within a tier) |
| — | deny | allow | no | granted (user overrides role's deny) |
| allow | allow | deny | no | denied (user tier wins) |
| — | allow | allow | yes | denied (hard deny overrides every tier) |
| — | — | — | no | denied (default) |

Safeguards: granting a hard deny is a dangerous operation (2FA re-confirmation,
audited). The system refuses a hard deny that would leave no active Super Admin
holding `system.manage_permissions`. The matrix marks hard denies distinctly, and
*explain access* lists them first.

## Consequences

Admins see one precedence everywhere. Ordinary denies stay overridable by more
specific grants, so exceptions for individual users remain possible. Hard denies
give administrators an absolute block when they need one. The Phase 1 access test
suite encodes the truth table above for both database engines.
