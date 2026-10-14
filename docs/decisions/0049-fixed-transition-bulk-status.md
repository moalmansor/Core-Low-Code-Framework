# ADR-0049: Bulk status change with a fixed transition

- Status: Accepted (Phase 4)
- Date: 2026-10-14

## Context

Review asked for an "approve all selected" action that is configurable from the UI. It must respect each record's allowed transitions and each user's permissions, and report which records could not be moved and why.

The built-in bulk status change already moved records through the record pipeline, but the user had to pick the transition every time. It also could not take a transition's comment.

## Decision

- Each action has a `settings` column (JSON). For `bulk_status`, `settings.transition` may name one transition of the form's workflow. It is checked when the action is saved.
- With a fixed transition, the run dialog shows "Approve: Submitted → Approved" and asks nothing else. Without one, the user picks among the transitions of the selected records' statuses.
- A transition whose comment is optional or mandatory asks one comment for the whole run. A mandatory comment must be given before the run starts.
- Each record is moved by the workflow engine as the running user. Status access, transition permission, record scope, required fields and conditions all apply.
- A record that is not in the transition's from-status fails with "is not in status …". The same check now applies to the "change status" step of custom actions.
- The result lists every record that did not move, by title, with its reason. The rest are moved. The trial run reports the same list without keeping anything.
- The Actions tab adds actions from a gallery. Its "Approve or move selected records" preset creates a bulk status action, ready for its transition to be chosen.

## Consequences

- An approval action is one action per transition, with its own permission in the matrix. Who may approve in bulk is decided there, as for any action.
