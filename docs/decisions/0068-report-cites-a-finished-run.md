# ADR-0068: The verification report can only cite a finished run on the head commit

- Status: Accepted (Phase 5)
- Date: 2026-10-24

## Context

CLAUDE.md and specification §8.2 require the verification report to cite a CI run on the pull request's head commit. Until now that rule was kept by hand. On pull request #15 it failed:

- CI cancels a branch's running workflow when a newer commit is pushed (`cancel-in-progress`).
- Several pushes came in quick succession, so no run finished between them.
- The report went on citing an earlier, passing run.
- `SettingsAndLocalizationTest` would have failed on the commits in between, but no run reached it.

Cancellation itself does not skip a test on what is merged, because the head commit always gets its own run. What allowed the gap was a citation that nobody and nothing checked.

## Decision

1. **A check enforces the citation.** `.github/workflows/report.yml` runs on every push to a pull request and on every edit of its description. `.github/scripts/check-report.mjs` reads the one line that states the run:

   ```
   CI run [ID](https://github.com/OWNER/REPO/actions/runs/ID) on the head commit `SHA`
   ```

   The check fails unless all of these hold:
   - the line appears exactly once;
   - the link and the ID agree, and the run is in this repository;
   - the run is a run of `ci.yml`;
   - the run's commit is the pull request's head commit, and the commit named in the line is that commit;
   - the run has finished and passed;
   - every job of its latest attempt passed (a cancelled or skipped job proves nothing).

   The rules are unit-tested (`check-report.test.mjs`), and those tests run in the same job.

   A push makes the citation stale, so the check stays red until CI finishes on the new head and the report is updated to cite that run. Editing the description re-runs the check. The description and commit reach the script as environment values and are never spliced into the script.

2. **Cancellation stays on for pull requests and phase branches, and is off for `main`.**
   - On a pull request, only the head is merged and it always gets a complete run. With the check in place, a cancelled run can never be cited.
   - Turning cancellation off there would queue every intermediate push for the full matrix on both engines, about 50 minutes each, and delay the result for the head.
   - On `main`, every push is a merge that stays in history, so each one must be tested to the end. A cancelled run there would hide which merge broke the build. Runs on `main` are never cancelled.

## Consequences

- The report can no longer describe something other than what would be merged without the check showing it.
- The check protects merging only if the repository owner adds "Report cites a finished CI run on the head commit" to the required status checks of `main`'s branch protection. The repository's settings cannot be changed from a pull request.
- After every push, the author waits for CI and then updates the report. That was already the rule, and it is now visible as a red check until it is done.
