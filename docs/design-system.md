# Design System

The visual rules of the product interface. The approved proposal (owner,
2026-10-09) and ADR-0030 record why; this document says what. It describes
the code as built: when a rule changes, this file changes in the same pull
request.

Sources of truth in code:

| What | Where |
|---|---|
| Colour, type, radius and size tokens | `frontend/src/theme/tokens.css` |
| PrimeVue component styling from the tokens | `frontend/src/theme/preset.ts` |
| Tailwind utilities from the tokens, global rules | `frontend/src/style.css` |
| Contrast and brand-colour maths | `frontend/src/theme/color.ts` (PHP twin: `App\Support\Color\Contrast`) |
| Contrast test (reads `tokens.css`) | `frontend/src/theme/contrast.spec.ts` |
| Colour lint | `frontend/scripts/check-colors.mjs` (part of `npm run lint` and CI) |

## 1. Tokens

### 1.1 One definition, semantic names only

- Colour values are written in one file, `src/theme/tokens.css`, as CSS
  custom properties. Light values sit on `:root` and `.app-light`, and dark
  values on `.app-dark`.
- Components use **semantic** tokens only. Examples: `--text-muted`, not a
  grey; `--danger`, not a red.
- Components reach them in three ways:
  - directly, with `var(--…)`;
  - through Tailwind utilities generated from them (`bg-card`, `text-muted`,
    `border-line-input` …);
  - through the PrimeVue preset.
- **No raw colour outside `src/theme/`.** The colour lint fails the build on:
  - hex values;
  - `rgb()`/`hsl()`;
  - Tailwind palette classes (`text-red-600`, `bg-surface-100` …);
  - PrimeVue palette variables, in any `.vue`, `.ts` or `.css` file outside
    the theme folder.
- Values that must stay fixed are tokens too:
  - `--paper` and `--paper-ink` for QR codes, signature pads and the printed
    page;
  - `--media-backdrop` behind photos and video.

### 1.2 Palette

Light values are the owner's palette. Dark values are designed separately,
not inverted.

| Token | Use | Light | Dark |
|---|---|---|---|
| `--bg-page` | Page background | `#f4f6f9` | `#0f1419` |
| `--bg-surface` | Cards, panels, tables, inputs | `#ffffff` | `#171e26` |
| `--bg-subtle` | Table headers, hovered rows, wells | `#f8fafc` | `#1e2733` |
| `--border` | Dividers, card edges | `#e3e8ef` | `#2a3441` |
| `--border-strong` | Emphasised dividers | `#cbd5e1` | `#3c4858` |
| `--border-input` | The outline of an input | `#7993b2` | `#5e718a` |
| `--text` | Body text | `#1f2937` | `#e6edf3` |
| `--text-muted` | Secondary text, **placeholders**, column headers | `#627289` | `#93a1b1` |
| `--text-faint` | **Disabled controls only** | `#94a3b8` | `#6b7a8c` |
| `--primary` | Primary actions, focus ring | brand, default `#1a6fd4` | brand (dark), default `#4d96e8` |
| `--primary-hover` | Hovered primary | `#1559ae` | `#6ba9ee` |
| `--primary-subtle` | Selected rows, badges | `#e8f1fc` | `#162d48` |
| `--on-primary` | Label on primary | `#ffffff` | `#0f1419` |
| `--on-primary-subtle` | Primary-coloured text on `--primary-subtle` | `#1559ae` | `#6ba9ee` |
| `--link` | Links, linked records | `#107c52` | `#35a775` |
| `--danger` / `--danger-subtle` | Errors, destructive actions | `#d92d20` / `#fdecea` | `#f2635c` / `#3a1f1e` |
| `--warning` | Warnings | `#b45309` | `#d9941f` |
| `--success` | Success | `#107c52` | `#35a775` |
| `--neutral-pill` | Status pills without an admin colour | `#3f4c5a` | `#46535f` |

Derived without new values:
- `--warning-subtle` and `--success-subtle` are 12 % of their colour mixed
  into `--bg-surface`;
- `--focus-ring` is `--primary`.

### 1.3 Contrast (WCAG 2.1 AA, both modes)

Every pairing the interface uses is checked by `contrast.spec.ts` against the
values in `tokens.css`. The build fails if one drops below its minimum. The
lowest ratio of each pairing:

| Pairing | Need | Light (lowest) | Dark (lowest) |
|---|---|---|---|
| Body text (`--text` on `--bg-page`, `--bg-surface`, `--bg-subtle`) | 4.5:1 | 13.56:1 | 12.76:1 |
| Muted text, placeholders, column headers (`--text-muted` on `--bg-page`, `--bg-surface`, `--bg-subtle`) | 4.5:1 | 4.52:1 | 5.72:1 |
| Links (`--link` on `--bg-surface`, `--bg-subtle`, `--primary-subtle`) | 4.5:1 | 4.57:1 | 4.61:1 |
| Primary as text (`--primary` on `--bg-page`, `--bg-surface`, `--bg-subtle`) | 4.5:1 | 4.54:1 | 4.91:1 |
| Text on primary-subtle (`--on-primary-subtle` on `--primary-subtle`) | 4.5:1 | 6.00:1 | 5.68:1 |
| Button labels (`--on-primary` on `--primary`, `--primary-hover`) | 4.5:1 | 4.92:1 | 6.03:1 |
| Danger text (`--danger` on `--bg-surface`, `--bg-subtle`) | 4.5:1 | 4.62:1 | 4.82:1 |
| Warning text (`--warning` on `--bg-surface`, `--bg-subtle`) | 4.5:1 | 4.80:1 | 5.89:1 |
| Success text (`--success` on `--bg-surface`, `--bg-subtle`) | 4.5:1 | 4.98:1 | 4.98:1 |
| Neutral pill label (`--on-neutral-pill` on `--neutral-pill`) | 4.5:1 | 8.78:1 | 7.89:1 |
| Yes pill label (`--on-success` on `--success`) | 4.5:1 | 5.21:1 | 6.11:1 |
| No pill label (`--on-danger` on `--danger`) | 4.5:1 | 4.83:1 | 5.92:1 |
| Input border (`--border-input` on `--bg-surface`, `--bg-subtle`) | 3.0:1 | 3.03:1 | 3.02:1 |
| Focus ring (`--primary` on `--bg-page`, `--bg-surface`, `--bg-subtle`) | 3.0:1 | 4.54:1 | 4.91:1 |

The owner's original palette failed seven pairings. Each fix changed a value
or a rule; none was ignored (decided 2026-10-09):

- **A:** `--text-muted` is `#627289`, so it reaches 4.5:1 on the page
  background, not only on cards.
- **B:** placeholders use `--text-muted`. `--text-faint` is for disabled
  controls only, which 1.4.3 exempts.
- **C:** text on `--primary-subtle` uses `--on-primary-subtle`, not `--primary`.
- **D:** dark `--primary-subtle` is `#162d48`, so links and primary text read
  on selected rows.
- **E:** banner and toast text is `--text`. Red, amber and green colour only
  the icon and the 4px inline-start edge.
- **F:** inputs use `--border-input`, a darker outline. An input's boundary is
  its only visible edge, so it needs 3:1 (WCAG 1.4.11).
- **G:** dark `--on-primary` is dark ink (`#0f1419`), because white on
  `#4d96e8` is below 4.5:1.

### 1.4 Type

- **Faces:**
  - IBM Plex Sans (Latin) and IBM Plex Sans Arabic, in weights 400, 500 and 600.
  - Both are bundled with the application (`@fontsource`). No font is loaded
    from a third party.
  - `:lang(ar)` puts the Arabic face first.
- **Scale (px):**
  - 12 small labels;
  - 13 help text;
  - 14 body and controls;
  - 16 section titles;
  - 22 page titles.
- **Table headers:**
  - 12px floor;
  - Latin headers are uppercase with 0.06em tracking;
  - Arabic headers are 13px, without uppercase or tracking. Arabic script
    needs more height than Latin at the same size, and letter-spacing breaks
    its joins.
  - Checked against real Arabic record data (names, governorates, statuses),
    not placeholder text.
- **Numbers** in tables and counts use tabular figures.

### 1.5 Shape and size

- Radii:
  - 6px for controls (`--radius-control`);
  - 12px for cards (`--radius-card`).
- Control height:
  - 36px (`--control-height`);
  - 32px in compact areas.

## 2. Modes

- **Theme:**
  - Light and dark follow the operating system's preference, live, unless
    the person chooses one in their profile (System, Light, Dark).
  - The choice is stored in the user's preferences and applied at sign-in;
    signing out returns to the system preference.
- **Direction:**
  - Arabic is right to left and English is left to right, everywhere.
  - Layouts use logical properties (`inline-start`, `ms-`/`me-`), never
    left and right, except where a physical side is the point (§5.3,
    mixed-script cells).

## 3. Brand colour

The primary colour is an **Appearance & Branding** setting
(`branding.primary_color`, and optionally `branding.primary_color_dark`), not
a value in code.

- **Readable or refused:**
  - The colour must reach 4.5:1 as text on the light surfaces, and its dark
    variant on the dark ones.
  - The editor checks this live and offers the nearest readable shade
    ("Use #…").
  - The server enforces the same rule (`ReadableBrandColour`), so the API
    cannot store an unreadable colour either.
- **Derived shades:** hover, subtle tint and on-colour are derived from the
  chosen colour (`brandShades`). The dark variant is derived when not set.
- **Applied at runtime:**
  - the bootstrap response carries the colours;
  - `applyBrand` sets the `--brand-*` custom properties on the document
    root;
  - every token built on them (primary, focus ring, selected rows, PrimeVue's
    primary scale) follows.
- **Reset:** clearing the field returns to the theme default.

## 4. Focus and motion

- **Focus ring:**
  - Every focusable element shows a 2px `--focus-ring` outline, 2px offset,
    on `:focus-visible`.
  - It never relies on colour change alone.
  - It is never removed without a replacement.
- **Reduced motion:**
  - When the system asks for reduced motion (`prefers-reduced-motion:
    reduce`), CSS animations and transitions are cut to an instant and
    smooth scrolling is off (`style.css`).
  - Scrolling started from code uses `scrollBehavior()` from
    `src/theme/motion.ts`, which also honours the preference.
  - New motion must go through one of the two.

## 5. Patterns

### 5.1 Page frame

- **Fills the window:** the shell fills the window exactly (`h-dvh`) at every
  size and zoom.
  - The sidebar runs the full height.
  - Its menu scrolls inside it.
  - The content area scrolls on its own.
  - The page itself never scrolls.
- **Active item:** the active menu item is scrolled into view when the route
  changes.
- **Tested:** e2e `05-interface`, five window sizes (1280×720 to 2560×1440,
  which covers 1920×1080 at 150 % and 80 % zoom), three screens, in both
  directions.

### 5.2 Status and boolean pills

- **Colour:** a pill takes its colour from the admin's definition (the
  option's colour). The theme only supplies a readable label.
- **Label:** white or `--on-color-dark`, whichever contrasts more with the
  background (`labelColor`).
  - For most colours one of the two reaches 4.5:1. A few mid-tones cannot
    (the worst, `#7a7a7a`, gives 4.31:1 either way).
  - The options editor warns when a chosen colour gives a label below 4.5:1,
    so the admin can adjust it.
- **No admin colour:** an option without a colour uses `--neutral-pill`.
- **Booleans:** they use `--success` (yes) and `--danger` (no) with their
  own on-colours.

### 5.3 Records table

- **Header:**
  - an icon badge, the form name;
  - a count: *1–25 of 312* or *No records*;
  - *New record*;
  - an *Actions* menu: import; export to Excel or CSV; export the selection;
    delete the selection; show deleted records.
- **Filter card** (collapsible, with a count of active filters):
  - search;
  - typed filters per field the admin marked *Filterable*:
    - text: contains, starts with, equals;
    - choices: equals, any of;
    - linked records: equals;
    - yes/no: is;
    - numbers and dates: between;
  - rows per page;
  - *Reset*;
  - *Apply & refresh*.
- **The URL holds the list:** search, filters, sort, page, page size and
  trash. A filtered list can be bookmarked and shared. Unknown fields in a
  link are ignored.
- **Table:**
  - a selection column;
  - choice values as pills (§5.2);
  - linked records as links;
  - single-word values never wrap, and longer text wraps to two lines;
  - a row menu.
- **Mixed-script text:** each cell takes its direction from its own content
  (`unicode-bidi: plaintext`), aligned to the interface's side, so an Arabic
  name in the English interface and a Latin code in the Arabic one both read
  correctly.
- **Bulk delete:** it deletes one record at a time. Each delete is
  authorised and version-checked by the server, and the result reports how
  many were deleted and how many were not.

### 5.4 Properties panel (builder)

- **Tabs:** at most four tabs, which never scroll.
  - Fields: General, Data, Validation, Rules. Events and Table & export sit
    under *More*.
  - Groups: General, Layout, Validation, Rules.
  - The form: General, Behavior, Collection, Rules.
  - The last tab used is kept while moving between elements of the same
    kind.
- **Sections:** each tab is made of collapsible sections, for example:
  - Basics;
  - Help text;
  - Display settings;
  - Layout;
  - Advanced;
  - Storage;
  - Options;
  - Behavior.
  - Rarely used sections start folded. Which sections a person opened or
    closed is remembered in their browser (a convenience only).
- **Find a setting:** it searches every tab's section titles and setting
  names, in Arabic (ignoring diacritics and letter variants) and in English.
  Matching sections show open, wherever they are.
- **Required:** it sits in *Basics*. Its message stays under Validation.
- **Translatable text:**
  - the interface language's input comes first;
  - the other enabled languages fold under one line that says how many are
    filled.
- **Screen-size widths:** one row per size, with common choices and
  *Custom* (1–12).
  - Phone, tablet and desktop always show.
  - Large phone and wide screens show on request, or when set.
