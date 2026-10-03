# ADR-0024: SPA stack, interface strings, and a strict CSP

- Status: Accepted (Phase 1)
- Date: 2026-10-02

## Decision

- Vue 3 + TypeScript + Pinia + Vue Router, PrimeVue 4 (Aura) with Tailwind 4 and
  logical (start/end) utilities so every screen works in RTL and LTR.
- The SPA is served by Laravel (`resources/views/app.blade.php`) from the Vite
  manifest, so the per-request CSP nonce applies to scripts; PrimeVue receives
  the nonce from a `csp-nonce` meta tag for the styles it injects. No
  `'unsafe-inline'`/`'unsafe-eval'`; `v-html` is forbidden by lint.
- Interface strings live in `backend/resources/ui-strings/{locale}.json` (flat
  keys). They are bundled for first paint and served merged with administrator
  overrides by `GET /api/v1/i18n/{locale}`; the Translations manager edits them per
  locale. Server messages (validation, errors, e-mail) are in `lang/{locale}`.
- The user's language preference is stored in `user_preferences` and sets
  `lang`/`dir` on the document.

## Consequences

A unit test keeps the Arabic and English catalogs in step and checks that every
key used in the source exists; e2e tests fail on any CSP violation.
