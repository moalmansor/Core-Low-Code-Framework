# ADR-0067: Unreachable-screen guard and four safeguards found in review

- Status: Accepted (Phase 5)
- Date: 2026-10-24

## Context

The owner's review of pull request #15 found that eight System Settings tabs answered 404 and the Self-service tab was not served at all, while CI was green. Nothing compared what the screens ask for with what the API serves:

- `SettingsScreenTest` checked the settings registry against the settings form, not the tabs against the groups the API serves;
- the screen sweep (`e2e/15-screens.spec.ts`) opens a fixed list of Phase 4 and 5 pages and never clicks a tab;
- `SettingsAndLocalizationTest` would have caught the Self-service group, but each push cancelled the previous CI run, so no run finished in between.

The owner also asked for five behaviours to be verified by automated tests. Writing them exposed four gaps, decided here.

## Decision

1. **Unreachable screens fail the build.**
   - `frontend/e2e/24-routes.spec.ts` reads every route registered under `/admin` from the running router. It fills required parameters with the pilot form and its application, `new` for editors, or the earliest existing object where `new` has no meaning (a package, a drift report, a blueprint).
   - It opens each route as the Super Admin and clicks every tab. It fails on an API answer of 403, 404, 405 or 5xx, on a 422 to a GET, on a page error, or when the page has no heading.
   - A 422 to a POST is the server checking a draft (an editor's live preview of an empty template), not a fault.
   - A new route is covered without being listed. A route whose parameter the guard cannot fill fails until it is given a value.
   - The fast suite (`SettingsScreenTest`) checks the same wiring from the source:
     - every System Settings tab is a group the API serves, apart from the egress allowlist and languages, which have their own endpoints;
     - every served group has a tab;
     - every Admin Console area has a client route.
2. **The last administrator cannot be locked out.**
   - Saving an access policy was already refused when it would lock out the person saving it.
   - A policy officer who is not a Super Admin could still save an administrative policy that no Super Admin satisfies.
   - A policy is now refused unless at least one active Super Admin can still sign in: one no policy governs, one whose policies have no address list or time window, or the person saving it, whose own access was already checked.
3. **Blocked impersonation attempts are audited.**
   - An action blocked during impersonation was refused but left no trace.
   - It is now recorded as `impersonation.blocked` (security), with the administrator as actor, the person as subject, the impersonation session, and the action, method and path.
4. **An export records a baseline.**
   - Comparison tells which side changed from the hashes recorded when a package was last applied here.
   - A package exported from this environment had none. Importing it back after a local change therefore read "changed" (both sides, ambiguous) instead of "changed here".
   - An export now records the hashes and keys of what it sent, and the latest applied import or export is the baseline.
5. **The form runtime cache follows the published version.** Within one request or job, a form republished after its runtime was cached was still served from the old definition. The cache now reuses an entry only while the form's current version matches.

## Consequences

- A screen that cannot load what it needs fails CI on both engines, whether or not anyone added it to a list.
- The guard opens every admin route, so it runs for about three minutes. Like the screen sweep, it lifts the API rate limit while it runs and waits out the window afterwards. That logic is shared in `e2e/support.ts`.
- Lock-out protection now holds whoever saves the policy.
- The audit log shows what impersonating administrators tried as well as what they did.
