## Summary

<!-- What this pull request changes, and the phase issue it closes ("Closes #N"). -->

## Verification report (specification §8.2)

- [ ] Requirements checklist
- [ ] Tests on MySQL and SQL Server — cite the CI run on this head commit, as ``CI run [ID](https://github.com/OWNER/REPO/actions/runs/ID) on the head commit `SHA` `` (checked by the "Verification report" check)
- [ ] Security check
- [ ] Regression check
- [ ] Manual verification guide — exact URLs, credentials and expected results

## New or changed screens (docs/design-system.md §5.7)

- [ ] Every new screen is covered by `frontend/src/design/screens.spec.ts` with no new waiver
- [ ] Every new screen is listed in `frontend/e2e/15-screens.spec.ts` (English and Arabic, desktop and phone)
- [ ] Widths come from the tokens; pickers are used for anything that must match an existing item; one active menu entry
- [ ] Screenshots in English and Arabic, at a desktop and a phone width

## Specification

- [ ] Every addition or design change is written into `docs/specification.md`, with a decision record where one was made
