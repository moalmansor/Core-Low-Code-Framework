# ADR-0029: User-facing validation messages and verbatim expressions

- Status: Accepted (Phase 2 review)
- Date: 2026-10-08

## Context

The owner's Phase 2 walkthrough found that the condition builder showed raw
JSON-schema output ("The required properties (items) are missing …
`conditions.when.b`") and that a plain condition, *code equals an empty
text*, could not be saved. Two causes:

1. Laravel's global `ConvertEmptyStringsToNull` and `TrimStrings` middleware
   rewrote every string in the request, including text literals inside
   expressions. `""` became `null` (which the expression schema rejects for a
   text literal) and `" A "` became `"A"` (a silent change of meaning).
2. Schema violations were returned to the browser as the validator's own
   messages and JSON pointers, and shown as such.

## Decision

1. **Expressions are stored as sent.** For requests that carry metadata
   documents and expressions (`PUT /forms/{form}/draft`,
   `POST /forms/{form}/draft/validate`, `/expressions/*`, `/field-templates`)
   the two global middlewares are skipped and `NormalizeDocumentInput` applies
   the same rules (trim, empty → null) everywhere *except* inside expression
   nodes, which are recognised by their `k` member.
2. **No schema detail in the interface.** When a document fails its JSON
   schema, the server reports the pointers and messages to Error Monitoring
   (severity *warning*, exception `InvalidDocument`) and returns one issue per
   element and area (`invalid_value`) with a sentence in the user's language
   and the reference. The builder names the area by its property-panel tab
   (Rules, Validation, …), never by a path.
3. **The visual condition builder saves complete conditions only.** A row
   that still needs a field, a value or a valid number is marked next to the
   control in the user's language, and the last complete condition stays in
   the document until the row is complete. The checks mirror the expression
   schema (`operandProblem` in `ruleModel.ts`).
4. **Unexpected errors carry their reference in the message** (`ui.errors.
   unexpected_with_reference`), so inline banners show it as well as the toast.

## Consequences

- An incomplete visual condition never produces a failed save.
- Schema failures that still happen (a client bug, a hand-edited import) are
  visible to administrators in Error Monitoring under the reference the user
  reports, with full detail.
- Diagnostics of the formula language itself (syntax and type errors in a
  typed formula) are still shown with the language's own wording after the
  translated sentence; they are English only and are listed as a follow-up.
