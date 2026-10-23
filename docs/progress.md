# Progress

Project memory file (specification §8.1). Updated at the end of every run.

## Phase status

| Phase | Branch | Status | Pull request |
|---|---|---|---|
| 0 — Architecture & Data Model | `phase-0-architecture` | **Complete. Merged.** | [moalmansor/Core-Low-Code-Framework#1](https://github.com/moalmansor/Core-Low-Code-Framework/pull/1) |
| 1 — Foundation, Security & Administration Core | `phase-1-foundation` | **Complete. Merged.** | [moalmansor/Core-Low-Code-Framework#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| 2 — Form Builder, Collections & Data Engine | `phase-2-form-builder` | **Complete. Merged.** (plus the interface pull request) | [moalmansor/Core-Low-Code-Framework#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| 2.5 — Pilot & Validation | `phase-2-5-pilot` (ADR-0016) | **Complete.** Owner confirmed 2026-10-09; no findings, no code changes (`docs/pilot-findings.md`) | — (issue #5 closed) |
| 3 — Workflow, Records & Views | `phase-3-workflow` | **Complete. Merged.** Owner re-tested after merge. | [moalmansor/Core-Low-Code-Framework#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| 4 — Actions, Downloads, Notifications, Documents & Operations | `phase-4-actions` | **Complete. Merged.** Owner verified 2026-10-16. | [moalmansor/Core-Low-Code-Framework#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| 5 — Platform & Extensibility | `phase-5-platform` | **In progress** (issue #8) | — |
| 6 — Hardening & Final Delivery | `phase-6-hardening` | Not started | — |

## Phase 0: completed deliverables

- `docs/architecture.md`: layers and runtime engine (§2–§3), module boundaries (§4),
  folder structure (§5), database driver layer (§6), security architecture (§7),
  queues/jobs/events (§8), data model conventions (§9), complete ERD of
  147 tables covering every specification §7 entity, each fully expanded with
  exact MySQL 8 and SQL Server 2019 column types, nullability, defaults, and named
  keys, indexes, foreign keys, and checks (§10), physical table generation (§11), schema change strategy
  (§12), versioning/rollback/drift (§13), metadata JSON schema (§14), permission
  resolution (§16), relation traversal (§17), extension model (§18), domain engine
  designs (§19), performance budgets (§20), API outline (§21), frontend
  architecture (§22), deployment and CI (§23), technical decisions (§24), and the
  phase map (§25).
- `docs/expression-language.md`: grammar, type system, operators, null and error
  semantics, AST JSON format, bounds, function library, and corpus format.
- `docs/conformance/expression-corpus.json`: 169 conformance cases, each with a
  normative AST. Covers operators, every function, null and error semantics,
  Unicode and Arabic text, Gregorian and Umm al-Qura Hijri dates (cross-checked
  against ICU), business calendars, aggregates, and static checks.
- `docs/decisions/`: ADR-0001 to ADR-0020.
- `docs/design-coverage.md`: the design coverage check. Every requirement in
  specification §2–§7 is mapped to its design location and delivery phase.
- `CHANGELOG.md`: Phase 0 entry.

## Verification results (Phase 0)

The design coverage check passed. Every requirement in §2–§7 is covered (see
`docs/design-coverage.md`). Mechanical checks were run on the documents:

- Every foreign-key target in the ERD is a defined table (147 defined, 0 missing).
- Every specification §7 entity maps to a defined table (0 missing).
- Every ERD table is owned by exactly one module in architecture §4 (0 unassigned, 0 duplicates).
- Every table has a primary key; every FK column's type matches its target on both
  engines; no SET NULL on a NOT NULL column; no table is reachable through more
  than one cascading path and there are no cascade cycles (SQL Server error 1785);
  every constraint name is unique and at most 60 characters; every index key fits
  MySQL's 3,072-byte and SQL Server's 1,700-byte (900 clustered) limits.
- Every internal section reference in `architecture.md` and `design-coverage.md`
  resolves.
- Every corpus case parses under the grammar in `expression-language.md` with the
  reference parser used to generate the ASTs. The syntax-error cases fail as
  expected, and case ids are unique.

Phase 0 produces no code, so steps 2–5 of §8.2 (tests on MySQL and SQL Server,
security check, regression check, manual guide) are replaced by the coverage check,
as the specification states.

## Owner decisions on the Phase 0 PR (2026-10-02)

1. **Precedence (ADR-0009):** specific user > role > department. Within a tier, deny
   beats allow. A more specific tier overrides a less specific one, including its
   deny. A hard deny cannot be overridden by any tier. Applied to specification
   §4.11, architecture §16 (one algorithm for permissions, field access, and record
   scopes), and the ERD (`effect enum<allow, deny, hard_deny>` on
   `permission_assignments`, `field_access_rules`, `record_access_rules`).
2. **Phase count (ADR-0016):** eight phases. The branch for Phase 2.5 is
   `phase-2-5-pilot`. The owner updates CLAUDE.md.
3. **ERD made fully explicit:** every table in architecture §10 now lists each
   column with its exact MySQL 8 and SQL Server 2019 type, nullability, and
   default, plus named primary key, unique constraints (filtered on SQL Server
   when nullable), indexes (including FK-supporting indexes), foreign keys with
   ON DELETE, and CHECK constraints. Totals: 147 tables, 2,312 columns, 646
   foreign keys, 115 unique constraints, 643 indexes. (Phase 1 added the
   `uq_{table}_uuid` constraint to the 68 tables with a `uuid` column, which the
   ERD had implied but not listed: 183 unique constraints.)
4. **Phase issues:** one GitHub issue per phase with its scope checklist
   (specification §10): Phase 0 [moalmansor/Core-Low-Code-Framework#2](https://github.com/moalmansor/Core-Low-Code-Framework/issues/2),
   Phase 1 [moalmansor/Core-Low-Code-Framework#3](https://github.com/moalmansor/Core-Low-Code-Framework/issues/3),
   Phase 2 [moalmansor/Core-Low-Code-Framework#4](https://github.com/moalmansor/Core-Low-Code-Framework/issues/4),
   Phase 2.5 [moalmansor/Core-Low-Code-Framework#5](https://github.com/moalmansor/Core-Low-Code-Framework/issues/5),
   Phase 3 [moalmansor/Core-Low-Code-Framework#6](https://github.com/moalmansor/Core-Low-Code-Framework/issues/6),
   Phase 4 [moalmansor/Core-Low-Code-Framework#7](https://github.com/moalmansor/Core-Low-Code-Framework/issues/7),
   Phase 5 [moalmansor/Core-Low-Code-Framework#8](https://github.com/moalmansor/Core-Low-Code-Framework/issues/8),
   Phase 6 [moalmansor/Core-Low-Code-Framework#9](https://github.com/moalmansor/Core-Low-Code-Framework/issues/9). The Phase 0 issue closes
   when the Phase 0 PR is merged.

## Phase 1: completed deliverables

Scope: specification §8.3 Phase 1; issue [moalmansor/Core-Low-Code-Framework#3](https://github.com/moalmansor/Core-Low-Code-Framework/issues/3).

- **Project setup:** `backend/` (Laravel 12, modular monolith under `app/Modules`),
  `frontend/` (Vue 3 + TypeScript SPA), `docker/` + `docker-compose.yml` (app,
  worker, scheduler, nginx, MySQL, optional SQL Server, Redis, ClamAV, Mailpit),
  `.devcontainer/`, GitHub Actions CI (`.github/workflows/ci.yml`): lint, static
  analysis, backend tests on MySQL 8.4 and SQL Server 2019, frontend checks,
  Playwright end-to-end tests on both engines, dependency audits, image builds.
- **Database driver layer** (`app/Infrastructure/Database`): custom grammars with
  logical types, named constraints, CHECKs, filtered uniques, introspection, JSON,
  locks, SKIP LOCKED, named locks; 22 Phase 1 tables matching the ERD exactly
  (asserted by `SchemaConformanceTest` on each engine; deferred columns in ADR-0021).
- **First run:** setup wizard with console-issued setup token (ADR-0022), branding,
  languages, regional formats, calendar, tenancy mode, SMTP with test, Super Admin
  with password policy and mandatory TOTP; permanent lock afterwards.
- **Application shell:** sidebar, top bar, Arabic (RTL) and English (LTR) with a
  per-user preference, translations manager (interface strings and object labels,
  untranslated filter, import/export), locales manager.
- **Authentication:** Sanctum SPA sessions + Fortify; mandatory 2FA for roles that
  require it (cannot be self-disabled), recovery codes, password policy (length,
  classes, common-password list, identity check, history, expiry), lockout,
  idle/absolute session timeouts, session list/revoke, OIDC SSO (PKCE, nonce, ID
  token verification via the egress gateway), LDAP (bind, group→role mapping, JIT).
- **Users & Departments:** user administration with escalation safeguards
  (ADR-0023); department tree with closure table, move without cycles, archive.
- **Roles & Permissions:** unified screen for role/user/department grants with
  allow / deny / hard deny, precedence user > role > department, lockout guard,
  step-up for dangerous changes, view-as-user, explain, copy, export/import.
- **Admin Console** (built areas only, per permission) and **System health**.
- **System Settings:** branding, formats, calendar, mail (+ test), files, ClamAV,
  security policy, SSO providers, LDAP, alerts, egress allowlist, locales.
- **Audit Log:** 16 SHA-256 hash chains, immutable entries, secret masking,
  filters, CSV export (formula-injection safe), on-demand and scheduled
  verification with alerting.
- **Error Monitoring:** secondary JSON sink first, then database; fingerprint
  grouping, reference codes, masking, correlation IDs, triage, e-mail alerts.
- **Security baseline:** security headers, nonce-based CSP, CSRF, rate limits,
  encrypted sessions and secrets, egress gateway with SSRF protections.
- **Docs:** specification §2, §4.11, §5 updated with the safeguards added;
  ADR-0021 to ADR-0025; architecture §21.2 aligned with the built API.

## Phase 1 review: gaps found and fixed (2026-10-03)

The owner ran the stack from a clean clone on Windows (PowerShell, Docker
Desktop). It could not start, although all seven CI jobs were green.

**What failed, and why CI missed it**
- `app`, `worker`, and `scheduler` crash-looped with
  `exec /usr/local/bin/lcf-entrypoint: no such file or directory`. Cause
  (reproduced): Git for Windows checks files out with CRLF, so the entrypoint's
  shebang became `#!/bin/sh\r`; 105 of 343 files got CRLF. CI used Linux
  checkouts only and never started the Compose stack.
- `docker compose up -d` tried to pull `lcf/app:local` ("pull access denied").
- Compose required `MSSQL_SA_PASSWORD` even for MySQL.
- The guide did not state the SQL Server password policy or how to generate
  `APP_KEY`, used `grep`, which PowerShell lacks, and its SQL Server step
  ("create database `lcf` with collation …") gave no command.

**Found while fixing (not reported, same area)**
- No `.dockerignore`: a `backend/.env` created before the build (APP_KEY,
  passwords) was copied into the image.
- nginx served `public/` from a volume filled once, so a rebuild would serve
  stale assets.
- The dev container (the specification's no-local-machine path) could not start
  in a fresh Codespace: it included the root compose file, which needs a root
  `.env`; its `backend/.env` had no database password and nothing migrated.
- Creating a user before SMTP was configured returned HTTP 500 whenever the queue
  ran synchronously (the dev container), because the password-link e-mail failed
  inside the request. The CI end-to-end job had used `MAIL_MAILER=log`, which hid it.
- The test suite depended on the developer's `.env`: with a Sanctum stateful
  domain list that did not include `localhost` (as in the dev container), the
  setup-wizard tests failed. `phpunit.xml` now pins `APP_URL` and the domain.

**Fixes** (ADR-0026; specification §3 Deployment & Environments and §5 updated):
`.gitattributes` (LF everywhere) plus a CRLF strip in the Dockerfile; built-not-
pulled images; health checks and health-ordered start-up (`up -d --wait`);
`.dockerignore`; a `web` image with the assets baked in; `APP_KEY` check and
`php artisan db:ensure` in the entrypoint; a self-contained dev container with a
generated database password; users are created even when the link cannot be
e-mailed, and the administrator is told why. README "Run it with Docker" is now
a step-by-step guide for bash and PowerShell.

**CI added:** `line-endings` (fails on CRLF in the repository or in a
`core.autocrlf=true` checkout), `stack` (MySQL and SQL Server: Windows-style
checkout, the README guide command by command, health and restart checks, no
`.env` in the image, Playwright through nginx), `devcontainer` (started as
Codespaces does; HTTP checks and the Pest suite inside). The `e2e` job now uses
the production mailer.

**Could not be run by Claude:** Docker Desktop on Windows (GitHub's Windows
runners cannot run Linux containers; CI reproduces the Windows checkout on Linux
instead), and a real GitHub Codespace (the same dev container definition is
started in CI with the devcontainer CLI).

## Phase 2: work so far (branch `phase-2-form-builder`, issue #4)

Done and committed:
- Expression language: PHP reference parser, type checker and evaluator
  (`backend/app/Expressions`), TypeScript twin (`frontend/src/expressions`),
  shared corpus extended to 198 cases, green on both (Pest `Conformance`,
  Vitest `tests/conformance`). ADR-0027 records the open semantics decided.
- Metadata tables of every Phase 2 module plus the deferred ADR-0021 columns;
  schema conformance test updated (`DEFERRED_COLUMNS`, record tables excluded,
  `FrameworkTables` registry kept equal to the ERD).
- Forms module: field-type registry, JSON schemas (`form-draft`, expression
  AST), draft repository over the working tables with optimistic draft locking,
  draft validator (errors vs problems), target schema builder, definition
  compiler, client definition, form/application/menu/field-library/expression
  controllers, publish service with placement, impact analyzer, version diff.
- Schema module: differ, planner with SQL previews for both engines, executor
  (reverse on failure, `inconsistent` state, idempotent retries), publish locks,
  snapshots (encrypted), reconciler, schema explorer/ERD/plans/repair API.
- Access: auto-registered `form.*`, `app.*`, `menu.*` permissions with Super
  Admin grants, field/group access resolver (§16.3) with explain, access matrix
  API.
- Engine tests (`tests/Engine`, real DDL, truncation): publish, rename,
  archive, widen, validated type change, reversal on failure.
- Records runtime (`app/Modules/Records/Runtime`): query-builder record store,
  value codec, rule runtime (defaults, formulas, conditions), validator,
  record pipeline with submission journal, idempotency keys, optimistic
  concurrency with the 409 conflict payload, child tables, pivots, files,
  numbering, comments, history; records API under `/api/v1/r/{form}`.
- Reference data (`app/Modules/Reference`): business calendars and holidays
  (working-time arithmetic), number sequences (tokens, padding, period reset,
  Hijri), currencies and exchange rates, units of measure, with APIs.
- Excel/CSV import and export of records (`app/Modules/Records/Exchange`):
  labels instead of codes, formula-safe cells, validate-then-commit import,
  updates by record ID with the exported version, idempotent re-import.
- Blueprints (`app/Modules/Blueprints`): save a form or collection as a
  versioned blueprint (structure, or structure plus permissions), instantiate
  with derived uuids, three-way propagation preview/apply, detach, export and
  import with content hashes.
- Scheduled maintenance: `files:purge-temporary` (hourly),
  `schema:reconcile --scheduled` (daily, setting `schema.reconcile_daily`),
  `schema:purge-snapshots` (daily, retention setting).
- Relation on-delete rules (restrict, cascade, set null) enforced by the
  record pipeline (`ReferentialIntegrity`), planned as a unit and audited.
- Frontend: form builder (palette, canvas with drag and drop and nesting,
  undo/redo, clipboard, multi-select, autosave, field library, property
  panels, rule builder, formula editor, preview as role or user, publish
  dialog with impact analysis and placement, versions/diff/rollback); runtime
  renderer with every field type; records list, create/edit/view, conflict
  screen, import/export, inline sub-forms; admin screens for applications and
  menus, forms and collections, form access matrix and explain, schema
  explorer with ERD, migration plans and repair, blueprints, reference data;
  sidebar navigation from published menus. Strings per area in
  `backend/resources/ui-strings/{en,ar}/*.json`.
- Playwright `e2e/04-forms.spec.ts`: build a form, publish it into the
  sidebar, create a record, resolve a concurrent edit on the conflict screen;
  building screens in Arabic right to left. Passes locally on MySQL and SQL
  Server.
- SQL Server parity: the SQL Server connection converts BIGINT and
  UNIQUEIDENTIFIER results by declared type; all 605 backend tests pass on
  MySQL 8 and SQL Server 2019 locally.

### Accepted limits (owner decisions on the Phase 2 pull request)

- **Excel/CSV import is synchronous.** One request validates and imports up to
  `records.import_max_rows` rows (default 5,000; the system setting allows
  10–50,000) and files up to `files.max_upload_mb`. Larger files must be split.
  Converting import to background jobs with progress, saved mappings, dry run
  and upsert (`import_jobs`, `import_mappings`) is scheduled in the Phase 4
  issue.
- **Blueprints of views** moved to Phase 3, with the views themselves.

### Browser walkthrough review (2026-10-08)

Fixed on the pull request, each with regression tests:

1. **Comments failed to post** (500). The HTML sanitizer's allowed-scheme list
   had a `false` entry that HTMLPurifier rejects, so every sanitize call failed
   (comments, static HTML blocks). Rich-text record values are now sanitised
   on the server too. The unexpected-error message now includes its
   reference, so inline banners show it as well as the toast.
6. **Condition builder showed schema errors.** Root cause: global
   ConvertEmptyStringsToNull/TrimStrings rewrote text literals inside
   expressions (`""` became `null`). Document and expression requests now keep
   expression nodes verbatim (ADR-0029); schema failures become translated
   issues with a reference (detail in Error Monitoring); the Visual builder
   saves only complete conditions and marks incomplete rows; issue lists name
   the panel tab, not a path; the linked-record path box has a readable label
   and appears only for record links.
- Also found and fixed: sidebar names stayed in the previous language after a
  language switch (the preference is now saved before the interface switches).
- Also found and fixed (2026-10-09, while testing the interface work):
  republishing a form with no schema change dropped and recreated every index
  and foreign key of its tables on MySQL — 42 steps and about 40 seconds for a
  two-field form with records. MySQL's JSON type returns the stored definition
  with its object keys reordered, and the differ compared specs as encoded
  strings. Specs and record values are now compared canonically
  (`App\Support\Json\Canonical`); tests: `SchemaDifferTest`, and a
  republish-without-change test in `PublishLifecycleTest`.
- Also found and fixed (2026-10-09, by CI on the interface pull request):
  opening *Publish* during an autosave could stop at "save first" with
  *Continue* disabled, because `flush()` returned before the follow-up save
  of a newer edit. Test: `useBuilder.spec.ts`.

Decided by the owner:

- Item 3 ("field rules are repeated") was withdrawn by the owner: it could not
  be reproduced and was most likely the flat panel layout of item 2.
- Items 2, 4, 5 and the overall visual system: approved for a separate pull
  request before Phase 3 (branch `phase-2-interface`), with the owner's
  palette, contrast fixes A–G as decided on 2026-10-09, a 12px table-header
  floor (13px for Arabic), and prefers-reduced-motion respected throughout.
- Formula-language diagnostics (syntax/type errors in typed formulas) are
  English only; translating them by code is a follow-up.

## Phase 2.5: pilot (issue #5)

- The owner built pilot forms from their organization in the running system,
  including linked forms, with their users. Claude created none of them.
- **Findings:** none. The owner confirmed on 2026-10-09 that the forms work for
  their users, which ends the phase.
- **Specification changes:** none were needed (`docs/pilot-findings.md`).
- No code changed, so the phase has no pull request of its own. The findings
  report and this entry are the first commit on `phase-3-workflow`, and
  issue #5 is closed with a link to it.

## Phase 3: completed deliverables (branch `phase-3-workflow`, issue #6)

- Schema: workflow, SLA, record access, justification, assignment and views
  tables per the ERD; `archived_at` added to `transitions` and `sla_rules`
  (ADR-0031); deferred columns filled; conformance test updated.
- Workflow versioned with the form, Vue Flow designer, publish checks, status
  mapping on the publish screen, status history (ADR-0031).
- SLA timers with working time, warnings and escalations (`sla:tick`).
- Status-based field access, transition permissions, status filter in the
  matrix; record-level rules with compiled custom scopes and explain (ADR-0032).
- Edit justification with reason codes and the save-time prompt (ADR-0035).
- Assignment rules, queues, claims, delegation, approvals with reminders,
  My Work (ADR-0036); `work:maintain` every minute.
- Table views, saved/shared views, totals, bulk delete/restore, view
  blueprints; View Mode panels; lookup preview cards with auto-fill; print
  layouts as HTML and PDF (ADR-0034).
- Configuration documents saved whole with a concurrency hash (ADR-0033).
- Admin screens: form configuration hub, reason codes, queues and delegations.
- Tests: Pest engine tests for workflow, record security, justification,
  assignment, delegation, views, panels, print; Vitest for the workflow graph,
  view state, justification flow and access matrix; Playwright
  `06-workflow.spec.ts`.
- Docs: specification §4.1, §4.11, §4.12, §4.14, §4.24, §4.25 details;
  architecture §21.2; ADR-0031 to ADR-0036; CHANGELOG.
- Configuration screens redesigned (owner review, 2026-10-10), presentation
  only, across all nine tabs of *Configure form*: one page frame, a quiet tab
  intro instead of an alert, collapsible sections (common open, advanced
  folded, remembered), one switch per line with a description, inputs sized
  to their content, empty states holding the add action, item cards, and a
  sticky save bar with Discard and an unsaved-changes guard. Shared
  components in `frontend/src/components/config/`; recorded as the standard
  in `docs/design-system.md` §5.5; e2e `06-workflow` checks the frame.
- Second design review (owner, 2026-10-10), applied across all screens:
  - sidebar: "Workflows, statuses & views" removed (per-form configuration is
    reached from the form's row menu; specification §4.1 updated); exactly one
    active entry (`layouts/navActive.ts`);
  - reason code dialog: grouped rows, Set chosen from existing sets or an
    explicit new set, optional languages marked; the queue dialog fixed the
    same way; number inputs never overflow; justification rules pick a set
    from those that exist;
  - record view and edit pages: automatic two/three-column layout for unsized
    fields (builder canvas matches), muted labels with emphasised values,
    *Not filled in* for empty values, section headings with rules, an aligned
    header and workflow bar, and the shared save bar on edit;
  - labels: publishing requires a label in the default language; every
    per-locale lookup now skips blank entries and falls back to the default
    language (`Translator::pick`, `pickText`), fixing fields shown by key;
  - recorded in design system §5.1, §5.5, §5.6 (record pages) and §5.7 (a
    checklist for new screens), specification §4.1, §4.6, §4.24, ADR-0037.
- Owner report 10 (2026-10-10): transition required fields were sent as field
  identifiers and looked up by key in the browser, so they were listed as
  UUIDs and filled fields counted as empty. The server now sends key, label
  and state; the dialog names the fields and opens the edit page with them
  marked. Server enforcement verified (`WorkflowTest`: 422 while empty). An
  audit of every message naming a field, status, transition, role or form
  fixed the remaining leaks (publish checks, record-rule errors, error lists,
  publish dialog, untitled linked records, formatter fallbacks) and added
  `identifiers.spec.ts` as a guard; design system §5.6 updated.
- Owner report 11 (workflow designer): stale From/To/key/label traced to names
  and keys generated once from the first target, plus a panel that could
  change routes independently of the canvas. Routes now change only on the
  canvas (drag or *Re-route*), names and keys follow until edited, saved keys
  are locked behind *Change key*; tests in `workflowGraph.spec.ts` replay the
  report. Canvas: non-overlapping labels, *Tidy up*, selection highlight with
  endpoints, checks strip (client and server: unreachable, dead end, no
  entry), outline, no keys on nodes. Spec §4.12, design system §5.6a.

## Phase 4: completed deliverables (branch `phase-4-actions`, issue #7)

- **Before Phase 4 — identifier guard (owner request).** The designer's
  warnings named statuses by key although the browser guard existed, because
  the key was put into the message on the server. The browser guard now also
  catches identifiers passed to messages, keys printed in templates and
  `{key}`/`{uuid}`/`{id}` catalog placeholders, and tests its own rules; a new
  server guard (`backend/tests/Unit/IdentifierLeakTest.php`) covers server
  messages and API names/labels/titles. Fixed what they found (lookup preview
  labels, publish impact, blueprint confirmations, about thirty API name
  fallbacks). Design system §5.6.
- **Schema.** All Phase 4 tables generated from the ERD and checked by the
  conformance test; the Phase 4 deferred columns added. Deferred to Phase 5:
  `automation_triggers.inbound_endpoint_id`, `notification_deliveries`.
- **Import and export as background jobs** (issue #7 item): upload and inspect,
  column mapping (suggested, saved mappings, text clean-up), preview, insert /
  update / upsert by key field, dry run, batches of 500 in one transaction each,
  idempotent retry, cancel, error report, import justification scope, journal
  `import_job_id`, in-app notification. The synchronous `POST /r/{form}/import`
  and its 50 000-row cap are gone: guards are `records.import_max_rows`
  (default 100 000, up to 1 000 000) and `records.import_time_limit_minutes`.
  Exports: xlsx / csv / pdf of the table as shown, export jobs above
  `records.export_sync_rows`, expiring personal files.
- **Notifications** (ADR-0039): email templates designed from blocks with a
  live preview through a sandboxed signed link, test send; notification rules
  per form (record created/updated, status change, field changed, condition
  met, date reminders, SLA escalations, assignment, approvals) with To/CC/BCC
  from users, roles, departments, user fields, the creator, linked records'
  users and static addresses; one message per language showing only what all
  its readers may see; email queue with backoff, resend and cancel; in-app
  notifications center (bell) and per-user channel preferences. e2e
  `07-notifications`.
- **Document templates** (ADR-0040): Word templates per language (untrusted
  input checks, run-split placeholders, repeating table rows) and designed
  templates (blocks, paper, live preview) producing Word and PDF; LibreOffice
  conversion for Word to PDF (in the image and CI); documents attached to
  notification emails, generated per message for its readers. The email and
  document designers share `Records/Templating` (values, block validation,
  placeholders, HTML previews) and the `BlockListEditor` component.
  e2e `08-documents`.
- **System Settings** gained Notifications, Documents and Operations tabs; a
  test now fails when a registry setting has no field on the settings screen.
- **Actions** (ADR-0041): one actions document per form on its "Actions"
  tab. Built-ins (duplicate, print, bulk field update, bulk status change,
  bulk delete) and custom actions with chained, conditional steps (set
  fields, change status, email, in-app notification, signed webhook through
  the egress gateway, generate a document, create a linked record, assign).
  Each action has its own `action.{uuid}.run` permission in the access
  matrix plus the form ability its work needs; every change goes through the
  record pipeline. One justification per run at the strictest level needed;
  runs tracked in `bulk_operations` with progress and per-record results,
  in the background above the threshold. Runtime: row menu, selection and
  table actions menu in the records list, buttons on the record page, a run
  dialog with inputs, progress and results. e2e `09-actions`.
- **Custom downloads** (ADR-0042): shared and personal download profiles
  built from a lazy relation tree (forward links, child records, repeating
  sections, system values) with a live preview and a "columns a role
  receives" check; one row per related record, aggregates or separate
  sheets; fixed filters, parameters, the table's filters and selection;
  Excel (sheets, styled frozen header, title row with logo, RTL, number
  formats), CSV with BOM, PDF with header, footer and page numbers. Runs
  are download_jobs (inline or background, audited, expiring files);
  field access, record scope and sensitive masking at every level. A
  generic scheduler (`scheduled_tasks`, `scheduler:run`) runs scheduled
  downloads per recipient and emails them. Action step `run_download`.
  Download menus on the records table and record page; "Downloads" tab in
  the form configuration; "My download profiles". e2e `10-downloads`.
- **Automations** (ADR-0043):
  - Automations belong to a form. Triggers: record created, changed or
    deleted; field changed; status changed; condition became true; a
    schedule (hourly to cron, in a time zone); a date reached with an
    offset; a file in a watched folder; manual.
  - Steps are shared with actions through one step runner, plus "wait" and
    "wait until".
  - Runs act as the triggering user or a chosen user. Making one run as
    someone else needs "Manage permissions".
  - Schedule and manual runs cover the matching records, with a hard
    maximum and a confirmation threshold.
  - Loop protection through an outbox context; concurrency limit; retry
    policy with back-off; manual retry of failed records.
  - Test runs roll back data steps and only describe outside effects.
  - The `automations:tick` clock resumes waits, fires date triggers and
    picks up watched-folder files.
  - Screens: run history; a scheduled-task manager listing automation
    schedules, scheduled downloads and the system's own jobs; admin areas
    "Automations" and "Scheduled tasks"; an "Automations" tab in the form
    configuration with the builder.
  - The action step editor is now a shared component used by both
    builders.
  - Tests: `tests/Engine/AutomationsTest.php`, e2e `11-automations`.
  - Found and fixed on the way: the download-schedule recipient check and
    the action-step UUID lookups sent non-UUID input to SQL Server
    `uniqueidentifier` columns, which caused 500 errors; the branding e2e
    test raced its own save.
- **Data quality and bulk tools** (ADR-0044):
  - Duplicate rules per form (exact, normalized with Arabic folding,
    similar), warn or block while saving, and background sweeps. The
    record form shows likely duplicates and offers "Save anyway".
  - Merge screen: the survivor, the value for each differing field, and a
    mandatory justification. Links are repointed, the others go to the
    recycle bin, and the history is immutable.
  - Bulk reassign built-in; a preview of the records in every bulk run;
    "Try first" dry runs in rolled-back transactions.
  - Repair scans for broken links and records that break the current
    rules, with the guided clearing of links.
  - Recycle-bin retention (`data_quality.recycle_bin_days`, daily purge
    that skips legal hold) and the purge date in the bin.
  - Screens: a "Data quality" tab in the form configuration (rules,
    sweeps, repair), the merge page, the duplicate dialog, "Merge selected"
    in the records list.
  - Tests: `tests/Engine/DataQualityTest.php`, the bulk test in
    `ActionsTest.php`, e2e `12-data-quality` and the trial run in
    `09-actions`.
- **Operations Center** (ADR-0045):
  - Admin › Operations Center with five tabs: overview (health widgets),
    e-mail queue, failed submissions, failed jobs and alerts.
  - E-mails: stuck detection, resend (bulk or to edited recipients),
    cancel, and the rendered HTML in a sandboxed frame.
  - Submissions: retry as the submitter with the same idempotency key,
    corrected values kept beside the original, sensitive values masked,
    discard with a reason.
  - Jobs: imports, exports, downloads, action runs and scans, automation
    runs, scheduled tasks, webhook calls and queue failures, each with a
    safe retry; dismissal with a reason (`operations_dismissals`).
  - Alerts: rules per measure with threshold, period, quiet period and
    roles; `ops:check-alerts` every five minutes e-mails through the queue.
  - Fixed on the way: Horizon had no workers for the `heavy` and `mail`
    queues, and the `redis` connection would hand a running job out again
    after 90 seconds. Long jobs now have connections that wait longer than
    their timeout.
  - Tests: `tests/Engine/OperationsTest.php`, e2e `13-operations`.
- **Verification:** CI passes on both engines (backend, end-to-end with
  `php artisan serve`, and the Docker stack from a Windows checkout).
  CI on SQL Server caught a trial-run lookup that used a non-UUID
  placeholder; it was fixed. One Docker/SQL Server run (152) timed out
  waiting for a repair scan, and the next runs passed. The e2e now waits up
  to 60 seconds and reports the scan's status, and CI prints the
  application's errors and unfinished operations when the stack fails.
- **Pull request** to `main` closing #7, with the verification report.
  Phase 4 is complete pending the owner's review. Do not start Phase 5
  until it is merged.
- **Owner review of Phase 4 (2026-10-14), four items:**
  1. *Notification recipients from the record* (ADR-0047). Recipients can now be:
     - a user, role or department picker field, on this form or on a linked form through a relation path of up to four links;
     - an e-mail field;
     - the current assignee;
     - the person who last changed the status.
     Each kind can be chosen for To, CC and BCC independently and is resolved at send time. The old "user field" option never worked: it looked for type `user`, not `user_picker`. Test: `NotificationsTest` "reaches the people a record holds".
  2. *Document template builder* (ADR-0046). Added:
     - a placeholder picker over linked forms and system values;
     - per-value formats (dates, numbers, currencies, Arabic-Indic or Western digits);
     - linked child-record tables with chosen columns;
     - signature blocks, QR codes and barcodes;
     - a title, and a header and footer with logo and page numbers, in both PDF and Word;
     - templates saved as blueprints (`document_template`).
     Conditional blocks and live preview were already there. Configuration packages are Phase 5 and will carry these blueprints. Tests: `DocumentsTest` (8), e2e `08-documents`.
  3. *Actions against §4.15* (ADR-0049). Every built-in and step in §4.15 was already implemented. Added:
     - bulk status change with a fixed transition, offered by an "Approve or move selected records" preset;
     - one comment for the run when the transition takes one;
     - a per-record "not in status …" refusal, reported by record title;
     - typed inputs for bulk field update.
     Test: `ActionsTest` "approves every selected record".
  4. *Design checklist* (ADR-0048, design system §5.7). The checklist was applied to every Phase 4 screen. It is now enforced by `src/design/screens.spec.ts`, with pre-checklist screens waived per rule and the waiver list only allowed to shrink. It is also enforced by e2e `15-screens.spec.ts`: every Phase 4 screen in English and Arabic at desktop and phone width. The sweep found and fixed:
     - the scheduled-tasks header on phones;
     - the autocomplete width in Arabic;
     - the raw `subject` key in a validation message;
     - the sidebar emptying itself after a refused request.
     A pull-request template carries the checklist.

## Phase 5: progress (branch `phase-5-platform`, issue #8)

Done:
1. **Schema.** Every Phase 5 table of the ERD exists, together with the columns that earlier phases deferred to it. Schema conformance passes; `DEFERRED_COLUMNS` is now empty. Phase 6 tables are not created.
2. **Settings fix.** Eight System Settings tabs (Records, Schema, Data quality, Actions, Downloads, Notifications, Documents and Operations) answered 404 and now load and save. The new "API and webhooks" settings group is added. A regression test covers every group that has a screen.
3. **REST API and webhooks** (ADR-0050):
   - personal API tokens, with a token-only guard, per-token rate limits and IP allow-lists;
   - the `/api/v1/data/{form}` API through the record pipeline, with OpenAPI 3.1;
   - signed outgoing webhooks with retries, a masked delivery log, resend, purge and Operations Center entries;
   - inbound endpoints (`/api/inbound/{slug}`, signed webhook or API token) that create, update or upsert records through the pipeline;
   - the inbound webhook automation trigger;
   - the screens: Admin › Integrations (outgoing webhooks, incoming endpoints, API tokens), the webhook and endpoint editors with their logs, Profile › API tokens, and the trigger in the automation editor.
   Fixed on the way: a bearer header on a session endpoint answered 500 (Sanctum looked up a table that does not exist). It now answers 401.

4. **Developer extensions** (ADR-0051):
   - server hooks at all nine points and custom API endpoints, checked by a token guard and run with a per-statement time limit, failing closed;
   - client validators, field components and form scripts compiled by esbuild, running in sandboxed opaque-origin frames;
   - the Monaco editor, sandbox test runs, approval by a second person, deployment, version history, rollback and turning off;
   - failures in Error Monitoring and the Operations Center;
   - the screens Admin › Developer extensions and the field-component picker in the builder;
   - an end-to-end test of a client validator from the editor to the record form.

5. **Configuration packages and the drift report** (ADR-0052):
   - export of applications, forms (with workflow, notification rules, access), email and document templates and download profiles, with dependencies, a checksum, and roles, departments and people by key;
   - a drift report against this environment: new, key in use, same, changed, changed here, changed in both, unpublished here, tables differ;
   - refusal while ambiguous, and per-object take, keep or import under another key;
   - applying in the background in dependency order through the normal publish, restoring a form that fails;
   - remembered hashes and keys for the next import;
   - the screens Admin › Configuration packages (packages, export picker, import with choices and outcome, drift reports);
   - tests: `PackagesTest` (6) and e2e `17-packages`.

6. **Reports and dashboards** (ADR-0053):
   - a report engine over relation paths, with date buckets, count/sum/average/min/max, detail lists, per-viewer scope and field access, a record cap and caching;
   - the report builder with live preview;
   - ECharts charts with a validated palette and a table view, pivot tables and key figures;
   - dashboards on a twelve-column grid with drag-and-drop and role-limited widgets;
   - Excel/CSV/PDF export with chart images;
   - the Reports page for viewers;
   - reports and dashboards in configuration packages;
   - tests: `ReportsTest` (4), a package round trip, and e2e `18-reports`.

7. **Pages, navigation, home screens, announcements, maintenance mode and global search** (ADR-0054):
   - custom pages (content, link, dashboard, report, a form's records) with their own permission and the permission of what they show;
   - the navigation builder with page, report, dashboard and My Work items, live badges, rules on the person and 20 levels;
   - home screens per person, role, department or everyone, for the main home and each application, built from widgets;
   - announcements as banners, on home screens or in the bell; system-wide maintenance mode; global search configured per form;
   - fixed on the way: My Work titles now respect field access; the e2e screen sweep waits out the rate-limit minute it used (the cause of the SQL Server sign-in failures in specs 16 and 17);
   - tests: `PagesNavigationTest` (2), `HomeScreensTest` (2), `AnnouncementsSearchMaintenanceTest` (3), e2e `19-experience`.

7b. **Themes per organization and per application** (ADR-0055):
   - light and dark colours, corners, density, shadows and a font per script, applied as `--theme-*` properties over the product tokens;
   - contrast checked on the server in both modes (text below 4.5:1 refused with the nearest readable shade; weak non-text contrasts acknowledged), live in the editor with a two-mode preview;
   - presets, reset, JSON import and export; artwork for the sign-in page, favicon and emails; translatable sign-in welcome, support contact, browser title and legal links;
   - custom CSS limited to safe properties, scoped to `.app-content` and served as its own stylesheet;
   - the organization default reaches the sign-in page; an application's theme applies while the user works in it;
   - tests: `ThemesTest` (4), `theme.spec.ts`, e2e `19-experience` (theme flow) and the screen sweep.

8. **Notification channels, external data sources and sync jobs** (ADR-0056, ADR-0057):
   - SMS (Twilio, HTTPS) and messaging (Slack, Teams, HTTPS) channels chosen per notification rule, with encrypted write-only provider settings, a test message, a per-minute limit, a delivery log with retries, an SMS preference per person, and failures in the Operations Center;
   - REST data sources through the egress gateway (API key, bearer, basic, OAuth 2.0 client credentials) and views of environment-defined database connections, mapped to value, label, parent and columns, cached per policy, with a live test;
   - an "external" option source for choice fields (checked again on save, labels on records) and an "External data" panel in View Mode;
   - sync jobs: import through the record pipeline as a run-as user with matching fields and four conflict rules, export of changed records, dry runs rolled back, CSV run logs, schedules, and failed runs in the Operations Center;
   - tests: `NotificationChannelsTest` (3), `DataSourcesTest` (3), `SyncJobsTest` (3), e2e `20-integrations` and the screen sweep.

Next, in order:
9. External access.
10. The blueprint library and application cloning.
11. Self-service, impersonation, access policies, feature flags, help, tours and usage.
12. Specification and architecture updates, the full test run on both engines, and the pull request.

## Resume point

Phase 4 is complete and merged (pull request
[moalmansor/Core-Low-Code-Framework#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14),
merge commit `06008cc`). The owner verified it on 2026-10-16: custom
downloads with a restricted user across linked forms, the bulk approve
action, notifications to a user from a linked form's field, document
generation as PDF and Word, and the Phase 4 screens in Arabic at narrow
widths all behaved correctly.

Phase 5 is in progress on branch `phase-5-platform`
([moalmansor/Core-Low-Code-Framework#8](https://github.com/moalmansor/Core-Low-Code-Framework/issues/8)).
See "Phase 5: progress" for what is done and the next item. Phase 6 starts
only after the Phase 5 pull request is merged.

### Local development notes

- Local e2e: after `migrate:fresh` of `lcf_e2e`, also run `CACHE_STORE=file php artisan cache:clear`; the file cache outlives the database and can serve a published definition from the previous run. Run Playwright with `DB_DATABASE=lcf_e2e` so `setup:token` reaches the e2e database.
- Dev container / Codespaces: everything is prepared by `.devcontainer/post-create.sh`;
  the application runs on port 8000; `cd backend && php artisan setup:token`.
- Without a container: `cd backend && cp .env.example .env && php artisan key:generate && php artisan db:ensure && php artisan migrate --seed && php artisan setup:token`
- `cd frontend && npm ci && npm run dev` (proxies the API to `:8000`), or `npm run build`
  to serve the SPA from Laravel.
- Tests: `php artisan test` (set `DB_CONNECTION=sqlsrv` for SQL Server);
  `npm run test`; `npm run e2e` against a fresh database (run `php artisan cache:clear`
  after `migrate:fresh`, because settings are cached).
