# ADR-0048: The screen checklist is enforced by tests

- Status: Accepted (Phase 4)
- Date: 2026-10-14

## Context

`docs/design-system.md` §5.7 lists what every new screen must meet. It was written after flat screens came up in review three times.

The Phase 4 screens still shipped flat. They were built feature by feature and checked through behaviour tests and English desktop screenshots. Nothing ran the checklist against them, and nothing in CI or review asked for it. A document alone did not hold.

## Decision

- `frontend/src/design/screens.spec.ts` runs with the unit tests. It covers every view, the template editors, and the dialogs that open over records, and fails on each rule a source file can show:
  - `frame`: a routed page without a title;
  - `one-save-bar`;
  - `grouped`: more than six settings with no section, card or heading;
  - `switch-described`;
  - `empty-state`: a table with no empty state;
  - `quiet-help`: an `info` alert used as help;
  - `per-locale`: fixed English and Arabic inputs;
  - `empty-value`: a bare dash;
  - `list-join`: a list joined with a fixed comma instead of `joinList`;
  - `logical-sides`;
  - `drawer-side`: a drawer pinned to `left` or `right`.
- Screens from Phases 1–3 that do not meet a rule yet are listed in `BEFORE_CHECKLIST` with the rule ids. The list may only shrink: a test fails when a waived screen starts meeting a rule, so the waiver is removed. Phase 4 screens have no waivers.
- `frontend/e2e/15-screens.spec.ts` opens every Phase 4 screen in English and Arabic at 1280×800 and 390×844. It fails on:
  - the wrong direction;
  - sideways scrolling of the page or its content area;
  - an element sticking out of the content area, other than one that scrolls in its own box (a table, the tab strip).
- New screens are added to that e2e list.
- `.github/pull_request_template.md` carries the checklist. Widths, pickers and menu entries need judgement and stay with the reviewer, together with the screenshots.
- Two shared pieces make the rules easy to meet:
  - `EmptyValue` shows an empty value;
  - `joinList` joins a list the way the user's language writes it.
- The `.page-head` style lets a page's controls wrap under its title on a narrow window.

## Consequences

- A new screen that repeats the Phase 4 problems fails CI instead of reaching review.
- The rules are heuristics over source text. A rule that misfires is fixed in the test, never waived for a new screen.
