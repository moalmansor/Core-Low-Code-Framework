# Working Rules for This Repository

## What this project is
A core low-code framework built with Laravel and Vue.js. Administrators build any
business system from the UI without writing code. The complete contract is
`docs/specification.md`. Read it in full before doing any work.

## Required reading at the start of every session
1. `docs/specification.md` — the contract. It wins over any assumption.
2. `docs/architecture.md` — the approved architecture and ERD.
3. `docs/expression-language.md` — the grammar both evaluators implement.
4. `docs/progress.md` — what is finished and where to resume.
5. `docs/decisions/` — decisions already made. Do not silently reverse one.
Then check the current branches, open pull requests, and open issues before
writing anything.

## Phases
Work is delivered in eight phases (Phase 0, 1, 2, 2.5, 3, 4, 5, 6), defined in
section 8.3 of the specification. Rules:
- Execute one phase at a time, in order.
- Never start a phase whose predecessor's pull request has not been merged.
- Never leave placeholders, stubs, TODOs, mock data, or fake endpoints for
  anything inside the current phase's scope.
- Phase 2.5 is a pilot run by the repository owner. Claude fixes what the pilot
  exposes and never creates the pilot forms.

## Branches and commits
- `main` is stable and is never committed to directly.
- One branch per phase, and work on that branch rather than a session default
  branch: `phase-0-architecture`, `phase-1-foundation`, `phase-2-form-builder`,
  `phase-2-5-pilot`, `phase-3-workflow`, `phase-4-actions`, `phase-5-platform`,
  `phase-6-hardening`.
- Conventional Commits: `feat:`, `fix:`, `docs:`, `test:`, `chore:`.
- Never force-push, rewrite history, or delete existing work.

## Ending a run
Before the session ends, always:
1. Commit and push everything.
2. Update `docs/progress.md` with what was completed and the exact resume point.
A run that stops mid-phase is fine. A run that stops with uncommitted work is not.

## Pull requests
- One pull request per phase, from the phase branch into `main`, linked to that
  phase's GitHub issue with a closing reference.
- The description carries the full verification report from section 8.2:
  requirements checklist, test results on MySQL and SQL Server, security check,
  regression check, and the manual verification guide.
- From Phase 1 onward, CI must actually run and pass on both database engines
  before a phase is reported complete. If a check could not run, say so plainly
  rather than reporting it as passed.
- The manual verification guide must be executable in a browser, with exact URLs,
  credentials, and expected results for every step.
- Never merge a pull request. The repository owner reviews and merges.
- Address review comments by pushing fixes to the same branch.

## Changing the specification
- `docs/specification.md` is the single source of truth and must never drift from
  what is built.
- Any addition, safeguard, or design change that is not already in the
  specification is written into it in the same pull request that introduces it.
- Where the specification contradicts itself, or contradicts `CLAUDE.md`, raise it
  in the pull request and do not pick a side silently.

## Non-negotiables
- Never seed business forms, demo modules, or sample data. Examples in the
  specification are illustrative only.
- Every capability must be configurable from the admin UI without code. Anything
  an admin should be able to change belongs in metadata, never in a config file,
  a seeder, or code.
- Never add a feature that bypasses permissions or security. Authorization is
  enforced server-side on every request.
- Access precedence is specific user > role > department. Within a tier, deny
  beats allow; a more specific tier overrides a less specific one, including its
  deny; a hard deny cannot be overridden by any tier.
- Every record write uses optimistic concurrency. Silent overwrite is never
  acceptable.
- Audit entries and saved justifications are immutable. No interface, including
  Super Admin, may edit or delete one.
- All outbound HTTP goes through the egress gateway. No module calls out directly.
- Conditions, formulas, and default values use the expression language only, never
  generated or evaluated code. The PHP and TypeScript evaluators must both pass
  the shared conformance corpus.
- No secrets, keys, or credentials in the repository. `.env.example` only.
- Arabic (RTL) and English (LTR) must both work everywhere, and translatable text
  is stored per locale, never as fixed Arabic and English columns.
- Both MySQL and SQL Server must be supported and tested.

## Ambiguity
When a detail is not specified, choose the most secure, standard, and
user-friendly option, record the decision in `docs/decisions/`, and continue.
Do not stop to ask.
