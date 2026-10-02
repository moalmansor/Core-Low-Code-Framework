# Progress

Project memory file (specification §8.1). Updated at the end of every run.

## Phase status

| Phase | Branch | Status | Pull request |
|---|---|---|---|
| 0 — Architecture & Data Model | `phase-0-architecture` | **Complete. Awaiting owner review.** | [moalmansor/Core-Low-Code-Framework#1](https://github.com/moalmansor/Core-Low-Code-Framework/pull/1) |
| 1 — Foundation, Security & Administration Core | `phase-1-foundation` | Not started. Blocked until the Phase 0 PR is merged and the owner approves. | — |
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
   foreign keys, 115 unique constraints, 643 indexes.
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

## Resume point

Phase 0 is complete. Its pull request ([moalmansor/Core-Low-Code-Framework#1](https://github.com/moalmansor/Core-Low-Code-Framework/pull/1), `phase-0-architecture` → `main`) is open and
awaits review. **Do not start Phase 1** until the owner merges the Phase 0 PR and
explicitly approves Phase 1. If review comments arrive, push fixes to
`phase-0-architecture`.

When Phase 1 begins: create `phase-1-foundation` from the updated `main`, then
start with architecture §5 scaffolding (`/backend`, `/frontend`, `/docker`,
`/.devcontainer`, CI) and §6 driver layer contract tests, following the Phase 1
row of architecture §25.
