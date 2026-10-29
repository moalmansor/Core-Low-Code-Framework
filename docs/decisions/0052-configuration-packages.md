# ADR-0052: Configuration packages and the environment drift report

- Status: Accepted (Phase 5)
- Date: 2026-10-16

## Context

Specification §4.10 and §4.22 ask for configuration packages: configuration is exported from one environment and imported into another with conflict resolution. They also ask for a compare-environments drift report. An import must refuse to proceed when drift would make the result ambiguous, and must say which objects conflict.

The environments do not talk to each other. Outbound HTTP to another environment would need the egress gateway and stored credentials for it. So the package file is the only channel. A comparison is made against a package, or a full export, from the other environment.

## Decision

**What a package carries**
- A JSON file in format `lcf-package/1`. It holds a name, the source (environment name, address, time, who exported it), a `subjects` map and a list of objects. A SHA-256 checksum covers all of it.
- Each object has a type, uuid, key, label, the uuids it depends on, its content and a hash of the content.
- The object types are:
  - **Applications**: names, description, icon, colour, data sharing, order.
  - **Forms and collections**: the published design as the builder's draft document, the workflow, the notification rules, field access rules and permission grants.
  - **Email templates**.
  - **Document templates**, with uploaded Word files embedded as base64.
  - **Shared download profiles**.
- Per-environment properties are left out: table name, binding mode, version, and the key inside the content. The key travels on the object.
- Only a form with no unpublished changes is exported, so a package always carries what is live.
- Exporting adds the dependencies of the chosen objects: the application of each form, the forms its relations target, and the templates its rules use.
- Roles, departments and people named by grants, rules or recipients are listed in `subjects` by role key, department code or e-mail. On import they are matched by those keys. Any that are missing are reported, and the grants that name them are left out with a note.

**Comparing (the drift report)**
- The checksum is verified first. A file changed after export is refused.
- Each object then gets a state:
  - `new`: not here yet.
  - `key_taken`: another object here uses its key.
  - `same`.
  - `changed`.
  - `changed_here`: changed here since it was last imported.
  - `changed_in_both`: changed here and in the package since the last import.
  - `unpublished_here`: a form here with unpublished changes.
  - `schema_drift`: a form whose physical tables here differ from its own metadata, as found by the schema reconciler.
- "Since the last import" is decided by the content hash recorded for each object when it was last applied here. With no earlier import, a difference is plain `changed`.
- Dependencies that are neither here nor in the package are listed.
- Every comparison is stored as an `environment_drift_reports` row, whether it was a compare only or part of an import.

**Ambiguity and choices**
- An import is refused while any object is `changed_in_both`, `unpublished_here` or `schema_drift`. These are the cases where neither side can be chosen without losing work or building on a table that does not match its design. They must be resolved here first.
- Every other object takes one of these choices:
  - `take` the package's version;
  - `keep` this environment's;
  - `rename`: import under another key. This is offered only for an object that is not here yet.
- Defaults: `new` → take; `same` and `changed_here` → keep. `changed` and `key_taken` need an explicit choice.
- An object here keeps its own key. An object imported under another key is remembered, so the next import of the same package reads as `same`.

**Applying**
- Applying runs as a job on the schema queue. It is re-analysed first, because this environment may have changed since the upload.
- Objects are applied in order: applications, then forms (sorted so link targets come first), then templates, then profiles.
- Notification rules are saved after the templates, which removes the form ↔ template cycle.
- A form is saved as a draft, its workflow is saved, and it is published through the normal migration plan with the impact hash, snapshots and locks. Warnings that need confirmation and destructive changes need the same confirmations as in the builder, given on the apply screen.
- If any object fails, nothing after it is applied. A form that failed has its draft reloaded from its published version, so the environment is left exactly as it was.
- Each applied object's hash and key are stored with the package for the next comparison.
- Export, upload, download, apply start and the final outcome are audited.

**Access**
- Everything requires the `system.manage_packages` permission ("Manage configuration packages"). The screen is Administration › Configuration packages.

## Consequences

- Moving configuration needs no network path between environments. It works as well for an air-gapped production as for a test copy.
- A package never overwrites work done in both places. The person importing either resolves it here or keeps this environment's version.
- Reports and dashboards, pages and other Phase 5 objects join the package as further `PackagePart` implementations as they are built.
