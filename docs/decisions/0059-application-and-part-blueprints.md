# ADR-0059: Blueprints of whole applications, workflows, notification rules, actions and dashboards; cloning applications

- Status: Accepted (Phase 5)
- Date: 2026-10-23

## Context

Specification §4.30 asks that any form, collection, workflow, view, action, notification, dashboard or whole application can be saved as a blueprint, duplicated with a choice of what comes along (structure; structure plus permissions; everything, including notifications and actions), kept in a template library, versioned, and propagated to what was made from it, keeping local changes as conflicts. Phase 2 built blueprints of forms and collections (ADR-0024), Phase 3 of views, and Phase 4 of document templates. Phase 5 completes the list: applications, workflows, notification rules, actions and dashboards, and whole-application cloning.

## Decision

**Identity without a stored map**
- Everything a blueprint makes gets a uuid derived from the object it lands in: `derive(parent, old) = uuid from SHA-256(parent ‖ old)`.
  - For an application, each form's uuid is derived from the new application, and its fields, groups, relations, statuses, transitions, rules and actions from the new form.
  - For a workflow, rules or actions applied to a form, the blueprint's own elements are derived from the target form.
  - For a dashboard, the widgets are derived from the new dashboard.
- Because derivation is deterministic, a later version maps onto what was made without storing a map. Links between an application's own forms are remapped to the new forms. Links to forms outside the application keep pointing at those forms, and those forms must exist before the blueprint can be used.

**Whole applications**
- A version captures every managed form and collection of the application, with:
  - its draft design and workflow (all levels);
  - its access rules and grants ("structure plus permissions" and above);
  - its notification rules and custom actions ("everything").
- Making an application from a blueprint, or cloning one directly (`POST /applications/{uuid}/clone`), creates the application and its forms. The form keys are `{application key}_{suggested key}`, made unique if taken. The forms are published in the order their links need.
  - A form that cannot be published stays a draft, and this is reported.
  - Notification rules and actions are added after publishing, because they need a published form.
  - Webhook secrets and header values never travel; they are entered again on the copy.
  - Records are never copied.
- Propagating a newer version merges each form three ways: the draft design, the workflow lists and, at "everything", the rules and actions.
  - What was changed locally is kept and listed as a conflict.
  - Design changes land in the draft, and publishing stays a separate step.
  - A form the blueprint gained is created as a draft.
  - Rules and actions have no draft, so their changes apply when propagation is confirmed. This is why propagation always shows a preview first.

**Workflows, notification rules and actions**
- These are captured from one form and applied to another. References to the source form's fields, statuses and transitions are matched **by key** on the target. Fields are read from the target's draft, so a field just added there matches too.
- A workflow is applied only to a form that has no workflow yet. Merging two state machines silently could strand records, so a form that already has one is refused.
- Rules and actions are added to the form's own. One whose key the form already uses is refused.
- A form follows one blueprint of each kind (the link is unique per kind and object). A second one is refused until the first is detached; a detached link is replaced.
- A rule or action that uses a field the target does not have is **left out**, and the response lists the missing fields. Saving it half-working would either be refused by validation or fire on nothing.
- Each of these needs Manage Forms as well as Manage Blueprints.

**Dashboards**
- A dashboard blueprint makes a new dashboard with its widgets, under a new key and name.
- It needs Manage Reports as well as Manage Blueprints.
- Widget roles and reports refer to objects in the same installation and are validated as on any dashboard save.

**Merging**
- `BlueprintService::mergeLists` is the one three-way merge used for every list of elements (statuses, transitions, SLA, rules, actions, widgets). Each element is compared by uuid between base, target and local:
  - unchanged locally → apply;
  - already the same → skip;
  - changed or removed locally → conflict, kept as it is.
- `mergeDocuments` does the same for a form design.

**Screens**
- The blueprint library's "New blueprint" first asks what to save (a form or collection, a form's workflow, rules or actions, an application, a dashboard), then shows the matching source picker.
- A blueprint's page shows what was made from it, whatever its kind, and offers the matching "create from it" or "apply to a form" dialog. Anything the server reported is listed there.
- The Applications screen's row menu has "Make a copy".

## Consequences

- One merge and one derivation rule serve every kind, so propagation behaves the same everywhere.
- A workflow blueprint cannot be applied on top of an existing workflow. To replace one, the administrator removes the old workflow first, which is a deliberate step with its own status mapping.
- Cloning publishes the copies at once. A copy that fails to publish is reported, never half-published.
