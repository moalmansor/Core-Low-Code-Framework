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
- Field widths (maximum widths; a field never overflows a narrow screen):
  - `xs` 7rem (`--field-xs`): numbers, minutes, counts;
  - `sm` 13rem (`--field-sm`): keys, codes, short choices;
  - `md` 20rem (`--field-md`): names, pickers, most choices;
  - `lg` 32rem (`--field-lg`): longer text, paths;
  - `full`: expressions, long help text, lists.
- Reading measure: 42rem (`--measure`) for help text.

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
- **Active item:** exactly one menu entry is highlighted, or none. An entry
  is active when the page is its address or lies under it; the longest match
  wins, and on a tie the first entry in menu order (`layouts/navActive.ts`,
  tested in `navActive.spec.ts`). It is scrolled into view when the route
  changes.
- **No duplicate entries:** two entries never open the same screen. A
  screen reached per item (a form's configuration, for example) is opened
  from that item's row menu, not from its own sidebar entry; a test fails
  when two Admin Console areas share a route.
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

### 5.5 Configuration screens

The standard for every screen where an admin configures something and saves
it as a whole: today the nine tabs of *Configure form* (workflow, SLA,
record access, justification, assignment, table views, View Mode, lookup
previews, print layouts). New configuration screens follow it. The shared
parts live in `frontend/src/components/config/`.

- **Frame:**
  - a back link to the list, the page title, one line of subtitle, and the
    page's secondary action at the end of the header;
  - the tab strip 24px below the header;
  - each tab's content 24px below the tab strip, its blocks 24px apart, at
    most 64rem wide (the workflow canvas takes the full width).
- **Tab intro (`TabIntro`):** the tab's name as a heading and one or two
  sentences of muted help text under it.
  - Help that applies on every visit is never an alert. Alerts (`Message`)
    are only for real problems: a save error, a conflict, a validation list.
- **Sections (`ConfigSection`):** each tab, and each item card, is made of
  collapsible sections with plain headings (16px, semibold) and an optional
  count.
  - Common sections (basics, the main lists) start open; advanced ones
    (conditions, wording, row actions, approvals, management) start folded.
  - Which sections a person opened or closed is remembered in their browser
    (a convenience only).
  - Settings of different kinds never share a band. For a table view, for
    example: *Basics* holds name, key and rows per page; *Where it is used* holds
    Default and Use in work queues; *What users can do* holds the view
    chooser, search and totals.
- **Switches (`SettingSwitch`):** one per line. The label and a short
  description at the start, the switch at the end, at most 32rem wide so the
  two stay connected. Related switches sit together under their section's
  heading.
- **Fields (`ConfigField`):** the label above the input, the hint and the
  error below. A short field (`xs`, `sm`) keeps a 20rem column so its label
  and hint read on one or two lines, while the input itself stays narrow. The input is sized to its content with the field widths of
  §1.5: a key is `sm`, a name `md`, rows per page `xs`. Short fields share a
  row (`cfg-row`, wrapping on narrow screens).
- **Empty states (`EmptyState`):** a list with nothing in it says what the
  thing is and what happens without it, and holds the add action. For
  example: *No columns chosen. The view shows the form's main fields.* with
  *Add column*. A bare heading with a lone add button is not used.
- **Items (`ConfigItem`):** each entry of a list (a rule, a column, a panel,
  a layout) is a card with a header (its title, a one-line summary, badges,
  move up and down where order matters, remove) and its sections below. The
  card folds away so a long list stays readable. An invalid entry has a
  danger border.
- **Save bar (`ConfigSaveBar`):** at the bottom of the tab, sticky to the
  bottom of the window while there is more to scroll.
  - The status at the start: *Unsaved changes* or *All changes saved*.
  - *Discard changes* (restores the last saved version) and *Save* at the
    end. Save is disabled when there is nothing to save.
  - Switching tabs or leaving the page with unsaved changes asks first.
  - Saving keeps optimistic concurrency: a document changed elsewhere is
    reported as a conflict, never overwritten.
- **Both directions:** padding, chevrons and alignment use logical sides, so
  every part reads correctly in Arabic and English.
- **Dialogs that edit settings** follow the same rules (`dlg-form`,
  `dlg-group`, `dlg-heading` in `style.css`): settings grouped under plain
  headings, one control per row, labels above, inputs sized with the field
  widths, switches as switch rows. A number and a switch never share a row.
- **Number inputs** never overflow their space: they shrink to their
  container (global rule on `.p-inputnumber`).
- **Translatable text:** the default language is marked required (*); every
  other language says *(optional)*, and shows the default language when left
  empty.
- **Choosing from what exists:** a value that groups other things (a reason
  code's set, a rule's set) is picked from a list of existing ones, with an
  explicit *New …* choice where creating one is allowed. Free text that would
  silently create a second group is not used.
- **Composite pickers** (a type and a value side by side, such as *Members*)
  take the `lg` width so both parts stay usable.

### 5.6 Record pages (view and edit)

The screens users see most. The standard applies to the record view, the
edit and create pages, and View Mode panels that show the form.

- **Header:**
  - a back link to the list, then the record's title (its title, else its
    number, else the form name) with its number and *In trash* tags;
  - one line of facts: created, last updated (date and person), form
    version;
  - the actions (Edit, Print, Restore, Delete) at the end, aligned with the
    title; on narrow screens they wrap below it.
- **Workflow bar** (forms with a workflow): one bordered bar under the
  header. Facts sit at the start as labelled columns (*Status* with the SLA
  tag; *Assigned to* with due time and claim), every action at the end in
  one row: the transitions first, then claim, release, assign or reassign as
  secondary buttons, separated by a divider. An approval in progress shows as
  its own card below. The status shows in this bar, not again beside the
  title.
- **Layout:** fields use the width of the page.
  - A field the admin has not sized takes the automatic width
    (`runtime/autoLayout.ts`): one column on phones, two from tablets (768px),
    three on wide screens (1280px). Long text, rich text, code, files,
    images, signatures, maps, consent, option groups and display blocks take
    the whole row.
  - A width set in the builder always wins, and so do a group's own column
    settings and rows and columns. The builder canvas shows the same
    automatic widths, and its width setting says so.
- **The form's structure carries through:** sections show their heading with
  a rule under it and space above; tabs stay tabs in the view and the edit
  page; cards, panels and fieldsets keep their frame.
- **Labels and values (view):** the label is small and muted
  (`--text-muted`, 14px, regular); the value is the emphasis (`--text`,
  medium weight). Rows are 20px apart and columns 32px apart.
- **Empty values:** shown as *Not filled in*, small, italic and muted, never
  as a bare dash; in compact places (table cells) a muted dash with the same
  words for screen readers.
- **Internal identifiers never reach the interface.** No UUID, key, id or
  document path appears in a label, message, dialog or table cell.
  - Text per language is read in the user's language, then the default
    language, then any filled language; blank entries count as missing.
  - When no language has a label, the key is shown made readable
    ("visit_date" → "Visit date"), never as-is: `Translator::labelOf` on the
    server, `labelOf` / `humanize` in the browser. A linked record without a
    title reads *Untitled record*.
  - Messages that name a field, status, transition or form take its label
    (for example the workflow's publish checks and record-rule errors).
  - Validation lists say where in words ("Transition 2 › Column 1", or the
    status's name), not as a path.
  - Publishing requires a label in the default language for every field
    that holds a value.
  - `src/i18n/identifiers.spec.ts` fails when the source falls back to a key,
    id or raw path; the few technical admin views that show definition paths
    or storage locations are listed there with their reason.
- **Transition requirements:** a transition that needs fields filled names
  them by label and offers *Fill them in*, which opens the edit page with
  those fields marked and the first in view. The server enforces the same
  fields and refuses the transition (422, one error per field) while any is
  empty.
- **Edit and create:** the same header (back link, *Edit {title}* or
  *New record: {form}*), the form in one card, and the save bar of §5.5 at the bottom
  (*Unsaved changes* / *No changes*, Cancel, Save); leaving with unsaved
  changes asks first.

### 5.7 Checklist for every new screen

Flat stacks of controls with no hierarchy came up three times in review.
Before a screen ships (Phase 4 onward), check it against this list:

1. **Frame:** title, optional tab strip, content with 24px between blocks,
   one save bar at the bottom (§5.1, §5.5).
2. **Grouping:** related settings sit under a plain heading; common groups
   open, advanced ones folded. No band mixes unrelated kinds of settings.
3. **One control per row** for switches and numbers, each switch with a
   one-line description; or a labelled grid. Two controls never share the
   same space.
4. **Widths from the tokens:** no input wider than what it holds.
5. **Empty states** say what the thing is, what happens without it, and hold
   the add action.
6. **Help is quiet text;** alerts only for real problems.
7. **Values read differently from labels,** and empty values read as empty
   (§5.6).
8. **Pickers over free text** for anything that must match an existing item.
9. **One active menu entry,** no duplicate entries.
10. **Arabic and English** both checked, with screenshots, at a desktop and
    a phone width.

