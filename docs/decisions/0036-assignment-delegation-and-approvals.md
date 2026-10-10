# ADR-0036: Assignment, queues, delegation and approvals

- Status: Accepted (Phase 3)
- Date: 2026-10-10

## Context

Specification §4.25 describes assignment, queues with claim and release,
delegation with on-behalf-of recording, and multi-party approvals, but not how
delegation interacts with permissions, nor what notifications mean before the
notification engine (Phase 4).

## Decision

- **Delegation** is a union: while active, the delegate holds, for the forms
  the delegation names (all when none), the delegator's form and transition
  permissions and record scope in addition to their own. Every action taken
  through it records `on_behalf_of_user_id` in the audit log, status history,
  approval decisions and justifications. An administrator sets delegations or
  out-of-office cover only for users whose permissions they hold
  (ADR-0023). Delegations are revoked, never deleted.
- **Assignment rules** are ordered; on creation and on each transition the
  first rule whose condition holds assigns the record (user, role, department,
  user in a field, creator's manager, round-robin or least-loaded within a
  role) with a due time and priority.
- **Queues and claims.** Role and department assignments can be claimed; a
  claimed record is locked against edits and transitions by others (423)
  until released, moved on, or the queue's claim timeout passes.
- **Approvals.** A transition with an approval rule creates a request and
  waits: all of, any N of, or weighted quorum; rejection immediately or after
  all decisions, to a chosen status. While pending, no other transition is
  offered. A rejection needs a comment; a second decision is refused (409).
- **SLA notify** escalations and approval reminders are written to the outbox
  and the audit log; the notification engine of Phase 4 delivers them.
- **Picking people.** `GET /subject-options?type=&search=` names users, roles
  and departments for pickers. Without Manage Users or Manage Permissions a
  user can search users only with at least three characters and sees at most
  ten results.

## Consequences

- Nothing a delegate does is attributed to the delegator alone, and no
  administrator can use delegation to exceed their own access.
