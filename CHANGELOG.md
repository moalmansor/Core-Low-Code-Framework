# Changelog

All notable changes to this project are documented here, one section per phase.

## [Unreleased] — Phase 1: Foundation, Security & Administration Core

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
