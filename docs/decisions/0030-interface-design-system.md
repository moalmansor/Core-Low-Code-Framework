# ADR-0030: One token-based design system for the interface

- Status: Accepted (interface pull request, before Phase 3)
- Date: 2026-10-09

## Context

The owner's Phase 2 walkthrough found three problems:

- the field properties panel was too long to work with (item 2);
- the records list was missing standard table behaviour (item 4);
- the page frame broke at some window sizes and zoom levels (item 5).

The walkthrough also asked for one visual system across the product. Colours
were written directly in components (Tailwind palette classes, PrimeVue's
palette, hex values), so dark mode, a brand colour and an accessibility check
could not be applied in one place.

The owner approved the following on 2026-10-09:

- the frame, table and panel proposals;
- IBM Plex Sans with IBM Plex Sans Arabic;
- the primary colour as an Appearance & Branding setting;
- the owner's light and dark palette, with contrast fixes A–G for the seven
  pairings that failed WCAG 2.1 AA;
- a 12px floor for table headers (13px for Arabic);
- respect for `prefers-reduced-motion`.

## Decision

1. **Semantic tokens, one file.**
   - Colours are written only in `frontend/src/theme/tokens.css`.
   - Components use semantic tokens: directly, through Tailwind utilities
     generated from them, or through a PrimeVue preset built on them.
   - `scripts/check-colors.mjs` runs as part of `npm run lint` and in CI. It
     fails the build on any raw colour, palette class or palette variable
     outside `src/theme/`.
2. **Contrast is tested, not reviewed.**
   - `contrast.spec.ts` reads `tokens.css` and checks every pairing the
     interface uses in both modes: 4.5:1 for text, 3:1 for input outlines and
     the focus ring.
   - Where the palette failed, the value or the rule changed (fixes A–G,
     listed in `docs/design-system.md` §1.3). No pairing is exempted.
3. **The brand colour is metadata.**
   - It is stored in `branding.primary_color` and the optional
     `branding.primary_color_dark`.
   - The server and the editor both refuse a colour that is unreadable as
     text. The server rule is `ReadableBrandColour`, and the editor uses the
     same maths in TypeScript.
   - The other shades are derived from it and applied as custom properties
     at runtime. Nothing is rebuilt.
4. **Fonts are bundled.** IBM Plex Sans and IBM Plex Sans Arabic ship with
   the application. A CDN would break the strict CSP (ADR-0024) and offline
   installations.
5. **The list state lives in the URL.** Search, filters, sort, page and page
   size are query parameters, so a list can be shared and bookmarked. The
   server validates each one against the form's filterable fields and
   ignores unknown ones.
6. **Pills take the admin's colour.**
   - The label is white or dark, whichever contrasts more.
   - A colour that leaves the label below 4.5:1 is warned about in the
     options editor, not refused. The admin owns the colour, and the worst
     case is 4.31:1.
7. **Properties panel structure.**
   - At most four tabs, plus *More*; collapsible sections inside each tab.
   - A search that crosses tabs and ignores Arabic diacritics.
   - Which sections are open is a per-browser convenience in local storage,
     not a user preference: losing it costs nothing.

## Consequences

- A new component cannot introduce an unchecked colour: lint fails.
- Changing a token re-runs the contrast check, so the palette cannot drift
  below AA without a failing build.
- Per-application and per-organization themes (specification §4.28) remain
  a later phase. They will set the same custom properties, so components do
  not change.
- PrimeVue's default palette is no longer used. Upgrading PrimeVue requires
  checking that the preset still covers every component in use.
