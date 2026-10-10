# ADR-0037: Record page layout, labels and sidebar entries

- Status: Accepted (Phase 3, owner design review)
- Date: 2026-10-10

## Context

The owner's design review of Phase 3 found record pages laid out as one long
column, empty values indistinguishable from filled ones, one field shown by
its key ("code") instead of its label, and two sidebar entries opening the
same screen and highlighted together. The specification set field widths per
breakpoint but said nothing about fields left unsized, nor whether a label is
required.

## Decision

- **Automatic width.** A field with no width set, in a group with no column
  settings (not a row or column), spans 12 columns on phones, 6 from 768px and
  4 from 1280px. Long text, rich text, code, files, images, signatures, maps,
  consent, option groups and display blocks span 12. A width set in the
  builder, and a group's own columns, always win. The builder canvas uses the
  same rule (`runtime/autoLayout.ts`).
- **Labels.** Publishing reports `label_missing` for a field that holds a
  value and has no label in the default language. Every per-locale lookup —
  server (`Translator::pick`) and browser (`pickText`) — reads the user's
  language, then the default language (and its fallback chain), then any
  filled language, treating blank entries as missing. Previously several
  lookups stopped at a blank entry and fell back to the field key, and some
  fell back to English instead of the default language.
- **Sidebar.** The "Workflows, statuses & views" Admin Console area is
  removed: everything in it is per form and reached from the form's row menu.
  Exactly one sidebar entry is active (longest matching path, first in menu
  order on a tie); a test fails when two areas share a route.
- **Reason code sets** are chosen from the existing sets, with an explicit
  "New set" choice; a justification rule picks its set from those that exist.

## Consequences

- Existing forms without widths now show two or three columns on wider
  screens; admins who want one column set the width to 12.
- A draft with an unlabelled field cannot be published until the label is
  filled; the builder shows the problem on the field.
- Specification §4.1, §4.6 (field properties) and §4.24 and design system
  §5.1, §5.5, §5.6 and §5.7 describe the result.
