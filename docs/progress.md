# Progress

Project memory file (specification §8.1). Updated at the end of every run.

## Phase status

| Phase | Branch | Status | Pull request |
|---|---|---|---|
| 0 — Architecture & Data Model | `phase-0-architecture` | **Complete. Merged.** | [moalmansor/Core-Low-Code-Framework#1](https://github.com/moalmansor/Core-Low-Code-Framework/pull/1) |
| 1 — Foundation, Security & Administration Core | `phase-1-foundation` | **In review.** The owner's review found that the stack did not run from a Windows clone; fixed on the branch (see "Phase 1 review" below). Awaiting re-review. | [moalmansor/Core-Low-Code-Framework#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| 2 — Form Builder, Collections & Data Engine | `phase-2-form-builder` | Not started | — |
| 2.5 — Pilot & Validation | `phase-2-5-pilot` (ADR-0016) | Not started | — |
| 3 — Workflow, Records & Views | `phase-3-workflow` | Not started | — |
| 4 — Actions, Downloads, Notifications, Documents & Operations | `phase-4-actions` | Not started | — |
| 5 — Platform & Extensibility | `phase-5-platform` | Not started | — |
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

## Resume point

Phase 1 is on `phase-1-foundation`, in review in
[moalmansor/Core-Low-Code-Framework#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) (→ `main`, closes issue #3). The PR's
verification report cites the CI run on the PR's head commit (CLAUDE.md); after
any new commit, wait for CI on it and update the report. Next: the owner re-runs
the README guide on Windows; address what they report on the same branch.
**Do not start Phase 2** until the owner
merges the Phase 1 PR and approves Phase 2. If review comments arrive, push fixes to
`phase-1-foundation`.

When Phase 2 begins: create `phase-2-form-builder` from the updated `main`, add the
deferred columns of ADR-0021 that point at Phase 2 tables (`departments.business_calendar_id`,
`roles.application_id`, `permission_assignments.condition_id`, `files.form_id|record_id|field_id`)
in the migrations that create those tables, and follow the Phase 2 row of
architecture §25.

### Local development notes

- Dev container / Codespaces: everything is prepared by `.devcontainer/post-create.sh`;
  the application runs on port 8000; `cd backend && php artisan setup:token`.
- Without a container: `cd backend && cp .env.example .env && php artisan key:generate && php artisan db:ensure && php artisan migrate --seed && php artisan setup:token`
- `cd frontend && npm ci && npm run dev` (proxies the API to `:8000`), or `npm run build`
  to serve the SPA from Laravel.
- Tests: `php artisan test` (set `DB_CONNECTION=sqlsrv` for SQL Server);
  `npm run test`; `npm run e2e` against a fresh database (run `php artisan cache:clear`
  after `migrate:fresh`, because settings are cached).
