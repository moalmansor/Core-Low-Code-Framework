# ADR-0055: Themes per organization and per application

- Status: Accepted (Phase 5)
- Date: 2026-10-23

## Context

Specification §4.28 asks for a visual theme editor with live preview, light and dark palettes, shape, density, shadow and fonts per script; artwork (logos, favicon, sign-in background, email header and footer); per-application and per-organization themes with a global default; translatable sign-in page content; scoped and sanitized custom CSS; import, export, presets and reset; and contrast checked and warned on before saving.

The interface already draws every colour from the semantic tokens of `frontend/src/theme/tokens.css` (ADR-0030), and Appearance & Branding already sets a primary colour with a contrast check. The Content-Security-Policy forbids inline style markup.

## Decision

**What a theme holds**
- Colours for light and dark mode: primary, accent (links), success, warning, danger, page, surface, subtle background and border. A colour left empty keeps the product's value.
- Corners (square, slightly rounded, rounded), density (compact, normal, comfortable), shadows (none, soft, strong), and a Latin and an Arabic font, each either IBM Plex or the device's font. Fonts stay self-hosted (no third-party font host).
- Translatable name, sign-in welcome, support contact and browser-tab title; up to 8 legal links (`https://` or a path, never `javascript:` or `//`) with a label per language.
- Artwork: logo, dark-mode logo, favicon, sign-in background, email header and email footer. Images are re-encoded on upload by the file store; artwork is served publicly, because the sign-in page and emails show it, with `nosniff` and a `default-src 'none'` policy.
- Custom CSS (below).

**Where it applies**
- One theme may be the organization default; it reaches the sign-in page through the bootstrap. Without one, the product theme applies with the Appearance & Branding colour, which also fills in when a theme sets no primary colour.
- An application may have one theme of its own; the shell applies it while the user works in that application (its home screen, menu items and records) and goes back to the default elsewhere. An application's theme cannot be the default.
- The theme is applied as `--theme-*` custom properties set through the CSSOM on the document root; `tokens.css` reads each with the product value as its fallback, so a theme only overrides and can never remove a token. Everything derived (hover shades, labels on the primary colour) is computed as before.

**Contrast**
- Checked on the server in both modes on every save, and live in the editor through the same endpoint.
- Text must reach 4.5:1 (WCAG 2.1 AA): body and secondary text on the page, surface and subtle background; primary, accent, success, warning and danger on the surface and subtle background. A failure is refused, and the nearest readable shade is offered.
- A border that barely shows against cards (below 1.12:1) or cards the same as the page (below 1.02:1) is a warning. The admin must acknowledge warnings to save.
- Every preset passes every check in both modes (tested).

**Custom CSS**
- Plain rules only: no at-rules, escapes, `url()`, `expression`, `javascript:`, `behavior`, `-moz-binding`, `import` or `!important`.
- Only colour, typography, border, spacing and shadow properties (an allow-list), with plain values. No `display`, `position`, `visibility` or sizes, so it cannot hide or move controls.
- Every selector is prefixed with `.app-content`, the page content area. The menu, header, banners, dialogs and toasts are rendered outside it. Selectors that name banners, dialogs, toasts, the shell or the document root are refused outright.
- The compiled CSS is served as a stylesheet of its own (`/api/v1/theme-css/{uuid}.css`, versioned) and linked after the theme, never inlined, so the Content-Security-Policy stays strict.

**Lifecycle**
- Saves use the document hash (409 on a stale save) and bump the theme's version, which also busts cached artwork and CSS.
- Reset returns colours, shape, density, shadow, fonts and custom CSS to the product values; texts, links and artwork stay.
- Export is a JSON file (`format: lcf-theme`, version 1) with tokens, custom CSS and texts. Artwork is not exported; it is uploaded again where the file is imported. Import takes a free key (`key_2`…) and runs the same checks, except that warnings are taken as acknowledged by the file's author; errors are still refused.
- Deleting a theme detaches it from the organization, applications and external forms, which fall back to the default or the product theme.
- Every change is audited. Managing themes needs "Manage branding".

## Consequences

- New admin area "Themes" in the Configuration section, next to Appearance & Branding.
- The shell's content area carries the `app-content` class; the maintenance banner carries `maintenance-banner`.
- The email layout uses the default theme's header and footer artwork when present.
- Fonts other than the two shipped families are not offered: uploading font files would need a font sanitizer and is left out deliberately.
