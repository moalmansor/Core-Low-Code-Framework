# ADR-0047: Notification recipients from the record, resolved at send time

- Status: Accepted (Phase 4)
- Date: 2026-10-14

## Context

The rule editor offered users, roles, departments, the creator and static addresses. It also had a "user field" option, but that option looked for fields of type `user`, while the picker's type is `user_picker`, so it never offered anything.

Review asked for more recipient kinds, each selectable for To, CC and BCC independently and each resolved when the message is sent:

- picker fields on this form or on a linked form;
- e-mail fields;
- the current assignee;
- the person who last changed the status.

## Decision

- One resolver, `Notifications\Recipients`, serves notification rules, action and automation steps that send messages, and documents that need their readers. It knows these recipient types:
  - `user`, `role` and `department`: chosen directly;
  - `field`: a relation path of up to four links ending in a user, role or department picker, or in an e-mail field;
  - `assignee`: the record's active assignments, with role and department assignments reaching their members;
  - `status_changer`: the `acted_by` of the latest status history row;
  - `creator`;
  - `actor`: the person whose change triggered the rule;
  - `static`: fixed addresses.
- The kind of a field is read from the field tree (`user`, `role`, `department` or `email`). The editor offers only fields of the right kind.
- Recipients are resolved from the record at send time. The rule stores the path, never the people.
- Inactive users are skipped.
- An address from an e-mail field that matches an active user's address reaches that user, with their language and preferences. Any other address is sent to as a static address and rendered as one (ADR-0039).
- Each of To, CC and BCC holds its own list of any of these types.

## Consequences

- Reassigning a record, changing its status or editing a picker field changes who is told, without touching the rule.
- A path that a form later loses is reported on the rule, as other broken references are.
