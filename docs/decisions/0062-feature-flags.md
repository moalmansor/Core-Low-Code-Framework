# ADR-0062: Feature flags

- Status: Accepted (Phase 5)
- Date: 2026-10-23

## Context

Specification §4.36 asks for feature flags per application or role, so that administrators can release a new form or capability to a pilot group before everyone. The architecture fixes `feature_flags`:
- `key` and `application_id`;
- `target_type` (`form`, `action`, `page`, `capability`) and `target_id`;
- `is_enabled`;
- `audience` (`{roles, departments, users}`, where empty means everyone when the flag is enabled);
- a translatable description.

What a "capability" is, and how a flag is enforced, are left open.

## Decision

**Meaning**
- A flag puts one target behind an audience.
- While the flag is on, only the audience sees the target, or everyone when the audience is empty. While it is off, no one does, the audience included.
- A target without a flag is untouched, so flags only ever narrow what access already allows; they never grant.
- An audience is roles, departments (each including the departments under it) and people, stored by uuid so packages carry flags between environments.
- One flag per target. A flag's application must be the one its target belongs to.

**Capabilities** are fixed in code with the requests they cover:
- global search;
- insights (reports and dashboards);
- personal API tokens;
- importing records;
- exporting and downloading records;
- sharing records outside (record links and signature requests).

A capability's flag is keyed by the capability.

**Enforcement on the server** (`EnforceFeatureFlags`, at the end of `lcf.secure`):
- A flagged form, action or page that the person may not have answers **404** for every request under it, as if it did not exist.
- A capability the person has not been given answers **403** `feature_off`.

Outside the request path:
- Navigation leaves out flagged forms, collections and pages.
- The action list of a form leaves out flagged actions.
- Global search skips flagged forms.

**Screens**
- `/me` lists the capabilities that are off for the person (`capabilities_off`), so the shell hides the search box, Insights, the tokens tab, import, export and the Share button.
- The server refuses them regardless.
- Flags are managed under Feature flags with "Manage feature flags". Changes are audited and use optimistic concurrency (`base_hash`).

## Consequences

- A form under a pilot flag is invisible to everyone else, including its own records in search, until it is released.
- Turning a flag off is an instant kill switch for what it releases.
