# ADR-0065: Guided tours and first-use hints

- Status: Accepted (Phase 5)
- Date: 2026-10-23

## Context

Specification §4.37 asks for guided tours and first-use hints "per form or role, with a reset option". The architecture fixes:
- `tours`: form or route key, audience roles, trigger `first_use`/`manual`, steps `[{target_selector, placement, step_key}]`, version, active;
- `tour_progress`: per user and tour, the version seen and whether it was completed or dismissed;
- translatable `name`, `steps.<step_key>.title` and `steps.<step_key>.body` (§10.24, §19.23).

It leaves open:
- who builds tours;
- what a step may point at;
- where a tour runs;
- what "reset" means.

## Decision

**Who builds them.** Holders of "Manage help content". Tours are the second tab of Admin Console › Building › Help content (`/admin/help/tours`). Tours are help, so no separate permission is added.

**Where a tour runs.**
- On a form's record screens (list, new, view, edit), or in a place:
  - the home page;
  - My Work;
  - Insights;
  - the profile;
  - an admin area (`area.<key>`).
- People get only the tours of places they may open:
  - a form tour needs the form's view permission and its feature flag;
  - an area tour needs one of the area's permissions.
- When roles are chosen, only holders of one of those roles get the tour.

**What a step points at.** Steps never take a free CSS selector. A step points at one of:
- a field of the tour's form (`[data-field="key"]`);
- a group of the tour's form (`[data-group="key"]`);
- a named part of the screen (`[data-tour="…"]`): the menu, search box, notifications, Help button, account menu, New record button, Save button, or the record actions;
- nothing, which gives a note in the middle of the screen.

The server checks that the field or group exists in the form and that the named part is in the list.

When the part is not on screen (a hidden field, a narrow window), the step shows as a centred note instead of failing.

**How a tour runs.**
- The tour dims the screen around the highlighted part and shows a card with Back, Next and Close.
- Escape closes it, and the arrow keys move between steps (mirrored in Arabic).
- A first-use tour starts by itself the first time someone opens its place.
- Finishing or closing a tour records the version seen, so it does not start again.
- Every tour of the place, first-use or not, is offered in the Help panel under "Tours".

**Versions and reset.**
- Saving a tour with changed steps (targets, order or text) raises its version, and a first-use tour shows again to everyone.
- "Show again" in the editor deletes the progress of everyone, or of holders of the tour's roles, and is audited (`tour.reset`).
- People can choose "Show tips again" in their profile preferences, which forgets their own progress.

**Text.** Names, step titles and step texts are stored per locale in `translations` and kept as plain text: tags are stripped. Removing a step removes its translations. Saves carry the editor's hash (409 when it changed). Creating, changing and deleting a tour are audited.

## Consequences

- A tour cannot run script or point at arbitrary page internals. Adding a new named part means adding it to `Tours::ANCHORS`, to `TOUR_ANCHORS` in the client, and a `data-tour` attribute on the element.
- Progress is not audit data and may be deleted freely, as the architecture states.
