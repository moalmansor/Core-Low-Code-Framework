# Changelog

All notable changes to this project are documented here, one section per phase.

## [Unreleased] — Interface (before Phase 3)

### Added
- Design system (`docs/design-system.md`, ADR-0030): semantic colour tokens
  with light and dark values, a PrimeVue preset built on them, IBM Plex Sans
  and IBM Plex Sans Arabic bundled, a visible focus ring, and reduced motion.
- Contrast of every pairing tested against WCAG 2.1 AA in both modes; a colour
  lint fails the build on colours written outside the theme.
- Primary colour in Appearance & Branding (light and optional dark), refused
  when unreadable, with the nearest readable shade offered.
- Records table: header with count and Actions menu, filter card with typed
  filters, list state in the URL, choice pills, bulk delete, mixed-script
  cells.
- Properties panel: four tabs plus More, collapsible sections, "Find a
  setting", folded translations, one row per screen size.
- Options editor warns when an option colour makes its pill label hard to
  read.

### Changed
- The application frame fills the window at every size and zoom; the menu
  and content scroll separately.
- Placeholders use the muted text colour; inputs have a darker outline.
- The container image sets `opcache.jit = disable` explicitly, and the
  end-to-end CI jobs run with the JIT off as production does: PHP 8.3.35's
  JIT segfaulted on the form publish analysis.

## [Unreleased] — Phase 2: Form Builder, Collections & Data Engine

### Added
- Expression language: PHP reference evaluator and TypeScript twin, parser,
  type checker; shared conformance corpus (198 cases) green on both in CI.
- Form builder: three panels, drag and drop with nesting, undo/redo,
  copy/paste, multi-select, autosave with draft locking, field library, every
  input type and group, all group and field properties, rule builder and
  formula editor, preview as any role or user.
- Data engine: physical tables per form and collection, child tables, pivots,
  migration plans persisted before execution with reversal, Schema
  Inconsistent state and guided repair, publish locks, encrypted snapshots,
  scheduled reconciliation, database binding and introspection.
- Versioning: draft, impact analysis, diff, rollback; publishing with sidebar
  placement and the menu editor.
- Records runtime: record pipeline with submission journal and idempotency,
  optimistic concurrency with the conflict screen, relation on-delete rules,
  repeater row permissions, inline sub-form records, files, numbering,
  comments, history; Excel/CSV import and export.
- Permission matrix: form-level permissions, group and field access per
  role, user, department and mode, cached resolution, explain access.
- Blueprints with versions, propagation preview, export and import; reference
  data (calendars, holidays, numbering, currencies, exchange rates, units).
- Admin screens for applications, forms and collections, access, schema
  explorer with ERD, migration plans, blueprints and reference data.
- ADR-0027 and ADR-0028.

### Changed
- Blueprints of views moved to Phase 3; the module overview no longer names
  `record_attachments` or `access_cache_versions` (neither is needed).

### Fixed
- SQL Server returns BIGINT and UNIQUEIDENTIFIER values with the same PHP
  types as MySQL.
- Opening the root URL directly shows the application shell.
- The access epoch is mirrored in `settings` as architecture §16.5 describes, so
  a counter lost from the cache never lets an old access snapshot match again.
- Republishing a form whose schema did not change no longer drops and
  recreates every index and foreign key of its tables on MySQL (MySQL's JSON
  type reorders object keys, and specs were compared as encoded strings).
- Opening *Publish* during an autosave no longer stops at "save first" with
  *Continue* disabled; the builder saves until nothing is pending.

## Phase 1: Foundation, Security & Administration Core

### Added
- Laravel 12 backend (modular monolith) and Vue 3 SPA; Docker Compose stack, dev
  container, and CI running every test on MySQL 8 and SQL Server 2019.
- Database driver layer for MySQL and SQL Server and the 22 Phase 1 tables.
- First-run setup wizard protected by a console-issued setup token, then locked.
- Application shell in Arabic (RTL) and English (LTR); translations and locales managers.
- Sign-in with Sanctum/Fortify, mandatory 2FA for administrative roles, password
  policy, lockout, session timeouts and management, OIDC SSO, and LDAP.
- Users, departments (tree), and the unified Roles & Permissions screen with
  precedence, hard deny, step-up, view-as-user, explain, copy, export/import.
- Admin Console, System health, System Settings, Audit Log (hash-chained), Error
  Monitoring, egress gateway, security headers and nonce-based CSP.
- ADR-0021 to ADR-0026.
- `php artisan db:ensure`: creates the configured database with the prescribed
  collation when it is missing (run by the container entrypoint).
- CI jobs `line-endings`, `stack` (the Compose stack from a Windows-style checkout
  on MySQL and SQL Server, with the Playwright suite through nginx), and
  `devcontainer`.

### Fixed (owner review of the Phase 1 pull request)
- The stack now starts from a Windows clone: LF line endings enforced by
  `.gitattributes`, and the entrypoint is stripped of CR in the image.
- `docker compose up -d` builds instead of pulling; services start in health
  order; `MSSQL_SA_PASSWORD` is needed only for SQL Server; a missing `APP_KEY`
  stops start-up with instructions.
- No `.env` file or other local state is copied into images (`.dockerignore`).
- nginx serves assets from a `web` image built with the app, never stale.
- The dev container starts in a fresh Codespace with no manual configuration.
- Creating a user before e-mail is configured no longer fails; the administrator
  is told the password link was not sent.
- README: step-by-step installation for bash and PowerShell, including SQL Server.

### Changed
- Specification §2, §4.11 and §5 record the safeguards added in this phase;
  §3 records the portability, image, health, and CI rules (ADR-0026).
- Architecture §10: `uq_{table}_uuid` listed for every table with a `uuid` column;
  §21.2 aligned with the built API.

## Phase 0: Architecture & Data Model

### Added
- `docs/architecture.md`: the complete architecture, ERD (147 tables), physical
  table and schema-change strategies, metadata JSON schema, permission resolution,
  relation traversal, extension model, performance budgets, API outline, frontend
  architecture, and technical decisions.
- `docs/expression-language.md`: the expression language specification.
- `docs/conformance/expression-corpus.json`: the shared conformance corpus (169 cases).
- `docs/decisions/`: ADR-0001 to ADR-0020.
- `docs/design-coverage.md`: the Phase 0 design coverage check.
- `docs/progress.md`: phase status and resume point.

### Changed (owner review)
- Specification §4.11: precedence is user > role > department. Deny beats allow
  within a tier, a more specific tier overrides a less specific one, and a hard
  deny overrides every tier (ADR-0009).
- ERD fully expanded: exact MySQL 8 and SQL Server 2019 types, nullability,
  defaults, and named keys, indexes, foreign keys, and checks for all 147 tables.
