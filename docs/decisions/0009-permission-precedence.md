# ADR-0009: Permission and access precedence

- Status: Accepted (Phase 0, pending owner review)
- Date: 2026-10-02

## Context

Specification §4.11 states two different orders. *Precedence* says "user overrides department, which overrides role". *Resolution model* lists "… department → role → specific user" from general to specific, which puts role above department. The two cannot both hold.

## Decision

Use **user > department > role > everyone** for permission grants, record scopes, and field/group access. For field access the full specificity vector is (target: form < group < field, status: any < specific, mode: any < specific, subject). A deny at the winning tier beats an allow. A less specific deny is overridden only by a more specific explicit allow. When roles tie at the same specificity, the most permissive value wins, because roles are additive. A field-access rule with `is_deny` is a hard ceiling at any level.

Worked truth table (permission p):

| Role | Department | User | Result |
|---|---|---|---|
| allow | — | — | allow |
| allow | deny | — | deny |
| deny | allow | — | allow |
| deny | — | allow | allow |
| allow | allow | deny | deny |
| allow + deny (two roles) | — | — | deny |
| — | — | — | deny (default) |

## Consequences

The explicit "overrides" sentence is the more direct statement of intent. Using one order everywhere means the explain-access screen and admins see consistent behavior. **The owner should confirm this choice in the Phase 0 PR review.** Changing it later only changes the subject weights in the resolver and the corpus of access tests.
