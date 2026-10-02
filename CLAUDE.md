# Working Rules for This Repository

## What this project is
A core low-code framework built with Laravel and Vue.js. Administrators build any
business system from the UI without writing code. The complete contract is
`docs/specification.md`. Read it in full before doing any work.

## Required reading at the start of every session
1. `docs/specification.md` — the contract. It wins over any assumption.
2. `docs/architecture.md` — the approved architecture and ERD.
3. `docs/progress.md` — what is finished and where to resume.
Then check the current branches and open pull requests before writing anything.

## Phases
Work is delivered in seven phases (Phase 0 to Phase 6), defined in section 8.3 of
the specification. Rules:
- Execute one phase at a time, in order.
- Never start a phase whose predecessor's pull request has not been merged.
- Never leave placeholders, stubs, TODOs, mock data, or fake endpoints for
  anything inside the current phase's scope.

## Branches and commits
- `main` is stable and is never committed to directly.
- One branch per phase: `phase-0-architecture`, `phase-1-foundation`,
  `phase-2-form-builder`, `phase-3-workflow`, `phase-4-actions`,
  `phase-5-platform`, `phase-6-hardening`.
- Conventional Commits: `feat:`, `fix:`, `docs:`, `test:`, `chore:`.
- Never force-push, rewrite history, or delete existing work.

## Ending a run
Before the session ends, always:
1. Commit and push everything.
2. Update `docs/progress.md` with what was completed and the exact resume point.
A run that stops mid-phase is fine. A run that stops with uncommitted work is not.

## Pull requests
- One pull request per phase, from the phase branch into `main`.
- The description carries the full verification report from section 8.2:
  requirements checklist, test results on MySQL and SQL Server, security check,
  regression check, and the manual verification guide.
- Never merge a pull request. The repository owner reviews and merges.
- Address review comments by pushing fixes to the same branch.

## Non-negotiables
- Never seed business forms, demo modules, or sample data. Examples in the
  specification are illustrative only.
- Every capability must be configurable from the admin UI without code.
- Never add a feature that bypasses permissions or security. Authorization is
  enforced server-side on every request.
- No secrets, keys, or credentials in the repository. `.env.example` only.
- Arabic (RTL) and English (LTR) must both work everywhere.
- Both MySQL and SQL Server must be supported and tested.

## Ambiguity
When a detail is not specified, choose the most secure, standard, and
user-friendly option, record the decision in `docs/decisions/`, and continue.
Do not stop to ask.
