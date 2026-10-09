# ADR-0028: Data engine decisions made while building Phase 2

- Status: Accepted (Phase 2)
- Date: 2026-10-03

## Context

Building the form builder, schema engine, records runtime, import/export and
blueprints (specification §8.3 Phase 2) required choices that the
specification and architecture leave open. Each is recorded here; code that
depends on one cites this ADR.

## Decision

1. **Draft errors and problems.** The draft validator returns *errors* (the
   document cannot be stored: dangling references, duplicate uuids or keys,
   unknown types, group cycles — 422) and *problems* (design faults a draft may
   carry while the admin works: expression type errors, missing options,
   invalid nesting, column clashes). Problems are stored and shown; preview and
   publish refuse until none remain.
2. **Physical table names per organization.** Forms use `f_{key}` and
   collections `c_{key}` in the platform organization; other organizations add
   their id (`f{org}_{key}`), because every organization's tables share one
   database. Child tables are `{table}__{group}`, many-to-many pivots
   `p_{formkey}__{relationkey}`, archived columns `zz_{column}_v{version}`
   (deterministic, so the impact hash of a plan does not depend on time). All
   names are fitted to 60 characters.
3. **Archived keys.** A published field or group removed from the draft keeps
   its row with a tombstone key `zz_{id}_{key}`, freeing the key for new
   elements; keys that change are parked under `zz_tmp_{id}` during a save so
   swaps never collide.
4. **"Allowed" on publish.** The roles, users and departments chosen as
   allowed in the publish dialog receive view, create and edit on the form,
   access to its application and visibility of its menu entry. Finer
   permissions are set in the permission matrix.
5. **Referential integrity in the pipeline.** Reference columns carry real
   foreign keys with `NO ACTION`; records are soft-deleted, so the database
   never applies on-delete rules. The record pipeline applies each relation's
   rule (restrict, cascade, set null) before a delete, planning the whole
   cascade first so that a restrict anywhere refuses the delete as a unit.
   Child-row `parent_id` and pivot `source_id` use `CASCADE` for hard row
   removal only.
6. **Records through the query builder.** The runtime uses a query-builder
   record store driven by the published definition instead of a dynamic
   Eloquent model: one code path for main, child and pivot tables, explicit
   control of every column, and no model events that could bypass the
   pipeline (journal, guards, audit, outbox).
7. **One level of repeaters.** A repeater may not contain another repeater;
   nested structure uses sub-forms (related records).
8. **Field access rules are live.** Group and field access rules are not part
   of a form version: they apply immediately and bump the access-cache epoch.
9. **Deferred columns.** Columns that reference tables of later phases
   (`status_id`, `external_user_id`, `import_job_id`, theme and policy ids) are
   created now without foreign keys; the phase that creates the target table
   adds the constraint (as in ADR-0021).
10. **Engine parity of results.** pdo_sqlsrv returns BIGINT as strings and
    UNIQUEIDENTIFIER in upper case. The SQL Server connection converts result
    columns by their declared type, never by name, so raw queries return the
    same PHP values as on MySQL and user text is never altered. Empty-list
    sentinels in `IN` clauses use the nil UUID, valid on both engines.
11. **Spreadsheet import and export in Phase 2.** Export streams the records
    the reader can list with the fields they may see, with labels instead of
    codes and formula-safe cells. Import is synchronous up to
    `records.import_max_rows` (default 5,000): every row is checked by the
    record pipeline first; rows with a record ID update that record using the
    exported Version (optimistic concurrency); each committed row has an
    idempotency key derived from the file hash and the row, so re-importing a
    file never duplicates records. Background import jobs and saved mappings
    (`import_jobs`, `import_mappings`) arrive with Phase 4. Repeater rows are
    not part of the spreadsheet.
12. **Blueprints.** A version stores the form's draft document and, by include
    mode, its access rules and grants by subject uuid. Instances derive element
    uuids from the instance's form uuid and the blueprint element uuid
    (UUIDv8 from SHA-256), so later versions map onto an instance without a
    stored mapping. Propagation is a three-way merge per element and per form
    property; locally changed elements are kept and reported as conflicts;
    results land in the instance's draft for the admin to publish; access is
    not propagated. Export files carry a SHA-256 content hash per version that
    import verifies. Blueprints of views, workflows, actions, notifications,
    dashboards and whole applications arrive with the modules that build them.
13. **Interface strings per area.** Bundled strings live in
    `resources/ui-strings/{locale}.json` plus one file per area in
    `resources/ui-strings/{locale}/`; the server and the SPA merge them the
    same way.

14. **User context in definitions.** Definition and preview responses carry
    the resolved user (role keys and uuids, department code and uuid,
    ancestor departments, attributes) so `@user` references and
    `current_department` defaults evaluate in the browser exactly as on the
    server; previews "as role" carry only that role.
15. **Repeater row permissions are enforced on the server.** When a repeater
    names roles for adding, removing or reordering rows, the record pipeline
    refuses those changes from anyone without one of the roles; the renderer
    hides the controls the same way.
16. **Type-specific UI settings.** Settings a field type needs only for display
    (rows, slider bounds, rating stars, image crop, map defaults, …) live in
    `field.ui.props` under the names listed in `frontend/src/runtime/uiProps.ts`;
    the builder writes exactly those names.
17. **Inline sub-forms.** Records of a linked form shown inside a parent are
    listed and added through `/r/{form}/{record}/subforms/{group}`, which runs
    the linked form's own record pipeline and sets the parent key at insert;
    the relation's on-delete rule applies when the parent is deleted.
18. **Picker lists without management rights.** Menu and numbering editors list
    forms through `GET /form-options` (uuid, key, kind, name, state), allowed to
    holders of Manage Pages & Menus, Numbering or Applications as well as
    Manage Forms, so those editors work without the right to change forms.

19. **No `record_attachments` or `access_cache_versions` tables.** The
    architecture's module overview named both, but neither the ERD nor the
    designs that use them need a table: record attachments are `files` rows
    carrying the record's `form_id` and `record_id` with no `field_id` (the ERD
    already defines those columns and their index), and the access epoch is a
    cache counter mirrored in `settings` (architecture §16.5). Both names were
    removed from the overview (owner decision on the Phase 2 pull request).
    The epoch mirror, described in §16.5 but missing until then, was
    implemented in the same change.

## Consequences

- The specification and architecture are updated in the same pull request
  where these decisions add or refine behaviour (§4.8, §4.9, §4.30, §11).
- Blueprints of views depend on the Views module, so the owner moved them to
  Phase 3 (specification §8.3).
