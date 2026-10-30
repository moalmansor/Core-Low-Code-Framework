# Final verification of §2–§7

Specification §8.3, Phase 6: *"Final verification of every requirement in sections 2 to 7 across the whole system."*

Every requirement row of the Phase 0 coverage check (`docs/design-coverage.md`) is listed again here. For each row:
- **Built in** gives the phase and its pull request. Each phase's report listed the row's own implementation and test, and the owner verified each merged phase in a browser.
- **Built and verified by**, under each heading, names the modules that implement that part of the specification and the tests that exercise it now.

How the whole system was checked again in Phase 6:
- every Pest suite (Engine, Feature, Integration, Unit, Conformance), every Vitest suite and every Playwright end-to-end suite runs on MySQL and on SQL Server in the CI run cited by the pull request;
- the axe accessibility sweep (`25-accessibility.spec.ts`) visits every screen in Arabic and English;
- `24-routes.spec.ts` renders every route;
- after the end-to-end suite, the audit chain is verified;
- the budgets of architecture §20 are measured on both engines by the Performance workflow (`docs/performance.md`).

Result: **every requirement in §2–§7 is built and verified.** The items still open with the owner are the two raised in the Phase 6 pull request, ADR-0070 and ADR-0074.

## §2 First-run behavior

Built and verified by: `Setup`, `Identity`, `Admin`. Tests: `backend/tests/Feature/Setup/SetupWizardTest.php`, `backend/tests/Feature/Identity/AuthenticationTest.php`, `frontend/e2e/01-setup.spec.ts`, `frontend/e2e/02-shell.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Empty shell: login, sidebar, top bar, Admin Console only | A§1 (principle 8), A§19.18 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| No business forms, sample data, demo modules, or example content | A§1, A§19.18 (seeders list), A§10.15 (shared reference collections not seeded) | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Seed only core roles (Super Admin, Admin, Developer, User) | A§10.4 `roles`, A§21.1 seeded grants | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Seed the full system permission catalog | A§21.1, A§10.4 `permissions` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Seed default system settings | A§19.18, A§10.2 `settings`; platform org + locales as settings (ADR-0004, ADR-0007) | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Setup wizard: name, logo, favicon | A§19.18, A§21.2 Setup | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Default language and enabled languages | A§19.18, A§10.3 `locales` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Timezone, date/number formats | A§19.18, `settings` group `formats` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Calendar system (Gregorian/Hijri/both) | A§19.18, `locales.calendar`, `settings` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| SMTP with test email | A§19.18, A§21.2 `POST /setup/smtp-test` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Super Admin account with password policy and mandatory 2FA | A§19.18, A§7.1 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Wizard locked permanently after setup | A§7.1 (SetupLock), A§19.18 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Admin lands on the Admin Console | A§19.18 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |

## §3 Tech stack

Built and verified by: `all modules; Infrastructure/Database drivers; docker-compose.yml; .github/workflows`. Tests: `backend/tests/Integration/DatabaseDriverContractTest.php`, `backend/tests/Feature/Database/SchemaConformanceTest.php`, `backend/tests/Conformance/ExpressionCorpusTest.php`, `frontend/tests/conformance/expressionCorpus.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Laravel 12+, PHP 8.3+, RESTful JSON API | A§2, A§5.2, A§21 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Sanctum (SPA cookie + tokens), Fortify (2FA, reset) | A§7.1, `api_tokens`, `sessions` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| maatwebsite/excel | A§19.5, ADR-0020 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| mpdf/mpdf with full Arabic | A§19.5, A§19.8, A§19.14 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13), Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| phpoffice/phpword | A§19.14 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| LdapRecord | A§7.1 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Socialite (OAuth2/OIDC) | A§7.1 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Horizon + Redis queues | A§8.1 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Laravel Scheduler | A§8.2, `scheduled_tasks` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Filesystem local + S3 | A§5.2 Infrastructure/Storage, `files.disk` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| ClamAV, toggled from settings | A§7.3 file uploads, `files.scan_status`, `settings` group `clamav` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Vue 3 Composition API + TS + Vite | A§5.3, A§22 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Pinia, Vue Router | A§22.1, A§5.3 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Tailwind + PrimeVue 4 with RTL | A§22.4, A§19.24, A§19.11 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| vuedraggable, TipTap, Monaco, Vue Flow, ECharts, Leaflet, signature_pad, qrcode, JsBarcode | A§22.2, A§22.4, A§19.2, A§19.20 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Hijri date adapter | A§19.24, EL§9.4 (Umm al-Qura) | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10), Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| MySQL 8+ and SQL Server 2019+ fully supported | A§6, A§9.2, A§23 CI matrix | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| All access via Eloquent / Query Builder | A§2 (layers), A§3.2, A§3.4 | all |
| Engine differences isolated in a driver layer with tests for both | A§6.1–6.3 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Arabic (RTL) and English (LTR) enabled; translation manager | A§10.3, A§19.24, A§21.2 Locales & translations | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Translations table keyed by object and locale (no fixed AR/EN columns) | A§10.3 `translations`, A§9.3 (Translatable lists), ADR-0007 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Locale declares direction, calendar, number formatting, fallback | A§10.3 `locales` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Missing translations fall back; manager lists untranslated | A§10.3, A§19.24, `GET /translations?untranslated=1` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Pest, Vitest, Playwright | A§23, A§6.3 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Docker Compose (app, worker, scheduler, Redis, MySQL, SQL Server, ClamAV, Mailpit) | A§23 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| `.devcontainer` for Codespaces | A§23, A§5.1 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| GitHub Actions: services, full suites, Larastan/ESLint/Prettier, audits | A§23 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Playwright artifacts uploaded every run | A§23 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Full installation documentation | A§25 (Phase 1 README, Phase 6 guides) | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10), Phase 6: this pull request |

## §4.1 Admin Console

Built and verified by: `Admin`, `Core`, `Organization`, `Access`. Tests: `backend/tests/Feature/Core/SettingsAndLocalizationTest.php`, `backend/tests/Feature/Organization/UsersAndDepartmentsTest.php`, `frontend/e2e/03-admin.spec.ts`, `frontend/e2e/24-routes.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Single control center listing all 28 areas | A§19.18 (module-registered navigation tree), A§21.2 `GET /admin/console` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Each area visible only to holders of its permission | A§19.18, A§21.1 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |

## §4.2 Operations Center

Built and verified by: `Operations`, `Monitoring`. Tests: `backend/tests/Engine/OperationsTest.php`, `frontend/e2e/13-operations.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Email queue statuses incl. Stuck (configurable threshold) | `email_queue.status`, A§19.7, settings `operations.stuck_email_minutes` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Recipients To/CC/BCC, subject, linked form/record/rule, attempts, failure reason | `email_queue` columns | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Resend single/bulk, edit recipients & resend, cancel, view HTML | A§19.7, A§21.2 Operations | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Submission journal written before processing; user, form, version, payload, error, trace, correlation ID | A§3.3 step 0, `submission_journal`, A§19.6 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Retry/resync single/bulk, edit payload & retry, discard with mandatory reason | A§19.6, A§19.7 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Idempotent retries (no duplicates) | A§19.6 (idempotency key, uuid derivation) | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Failed jobs (imports, exports, downloads, webhooks, scheduled tasks, hooks, documents): inspect/retry/discard | A§8.1, A§19.7 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Health widgets: queues, stuck items, scheduler, storage, DB/Redis/SMTP/ClamAV | A§19.7 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Email alerts by role over thresholds | `operations_alert_rules`, A§19.7 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| All Operations Center actions audited | A§19.7 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |

## §4.3 Form Builder canvas

Built and verified by: `Forms`, `frontend/src/builder`. Tests: `backend/tests/Engine/PublishLifecycleTest.php`, `backend/tests/Engine/DraftIssuesTest.php`, `frontend/src/builder/useBuilder.spec.ts`, `frontend/e2e/04-forms.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Three-panel layout: palette (searchable, categorized), canvas, properties | A§22.2 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Drag to canvas or into groups at any depth; click to insert at selection | A§22.2 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Reorder, move between groups, duplicate, copy/paste across forms, delete, multi-select bulk changes | A§22.2 (commands, clipboard MIME, BulkUpdate) | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Undo/redo full history, keyboard shortcuts, autosave drafts | A§22.2, A§13.1, ADR-0018 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Reusable field library | `field_templates`, A§21.2 Field library | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Live preview: desktop/tablet/mobile, AR/EN, as any role/user | A§22.2, `GET /forms/{uuid}/preview?as_user=` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |

## §4.4 Input types

Built and verified by: Forms (field type registry), `Records`, `frontend/src/runtime`. Tests: `backend/tests/Engine/RecordsPipelineTest.php`, `backend/tests/Engine/SubformTest.php`, `frontend/src/runtime/fieldTypes.spec.ts`, `frontend/e2e/04-forms.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| All native HTML input types (text…reset) | A§14.4 native list, A§11.3 storage | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Native elements: textarea, select, multiple, optgroup, datalist, output, progress, meter, fieldset/legend | A§14.4 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Rich text, markdown, code | A§14.4, A§11.3 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Currency, percentage, decimal precision, auto-number pattern, formula | A§14.4, A§11.3, `number_sequences`, EL | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Calendar (Gregorian/Hijri switchable/dual), date/time/datetime range, duration | A§14.4, A§14.7 (calendar), A§11.3 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Toggle, checkbox/radio/button groups, searchable dropdown, chips, tags, cascading, lookup with preview card, user/role/department/country/city pickers, rating, slider, color palette | A§14.4, A§14.5, `reference_previews` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Multi-file drag-drop, image crop/resize, camera | A§14.4, A§11.3, `files` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Signature, map (Leaflet), phone w/ country, national ID pattern+checksum, IBAN, barcode/QR display & scan, JSON, key-value, consent with terms | A§14.4, A§11.3, `fields` translatable `consent_terms` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Display elements: static HTML, heading, divider, spacer, image, alert, link | A§14.4 (`is_stored=0`) | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Layout: section, fieldset, card, tabs/tab, wizard/step, row/columns (1–12 per breakpoint), collapsible/accordion, repeater, inline sub-form | A§14.4 groups, `field_groups.type`, A§11.1 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |

## §4.5 Group properties

Built and verified by: `Forms`, `frontend/src/builder/panel`. Tests: `backend/tests/Engine/PublishLifecycleTest.php`, `frontend/src/builder/panel/panel.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Title/description (translatable), icon, CSS class | `field_groups.layout`, translatable `title`/`description` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Columns per breakpoint, spacing, border/background | `field_groups.layout` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Collapsible, default state, order | `field_groups.collapsible/default_state/sort_order` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Hide/read-only/disable by roles, users, departments, status, mode, field values, user attributes, linked data, date/time | `field_access_rules` (target=group), `conditions` (owner=group), EL§2 scopes, A§16.3 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Visual rule builder | A§14.3, A§22.2 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Require at least N filled; group custom rules with messages | `field_groups.validation` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Repeater: min/max/default rows, per-role add/remove/reorder, totals/aggregates, table/cards, child table with real FK | `field_groups.repeater`, A§11.1–11.2 (`parent_id` FK) | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Wizard: validate before next, jump, step visibility | `field_groups.wizard`, conditions | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |

## §4.6 Field properties

Built and verified by: `Forms`, `Records`, `frontend/src/builder/panel`. Tests: `backend/tests/Engine/RecordsPipelineTest.php`, `backend/tests/Engine/FieldLabelTest.php`, `frontend/src/builder/panel/panel.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Key (auto, editable, unique), label/placeholder translatable, help/tooltip/description, prefix/suffix, icon, size, width per breakpoint, label position, autofocus, tab index, autocomplete, spellcheck, CSS | `fields` (key unique per form, `ui` JSON, translatables) | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Bind to existing table/column (introspection) or auto-create | `forms.binding_mode`, `fields.column_name`, A§11.1, A§6.1 introspection | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Column name, type (suggested), length/precision/scale, nullable, DB default | `fields` columns, A§11.3 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Index none/index/unique (scoped) | `fields.index_type/unique_scope`, A§11.2 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Stored encrypted | `fields.is_encrypted`, A§7.5 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Relation binding: type, target, display/value columns, on-delete | `relations`, A§11.6 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Options: static (value/label/color/icon/order), collection, form records, visual query builder | `field_options`, A§14.5 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Cascading, custom values, min/max selections, defaults, searchable/lazy, grouping | A§14.5, `field_options.group_key/parent_value` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Validation: required, length, number, regex, format presets, date rules (relative, weekdays, dates, past/future), file rules, uniqueness (scoped), cross-field, async, per-rule messages | A§14.6, EL (relative dates) | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Defaults: static, current user/department/date, URL param, other field, formula, referenced record | A§14.7 `default` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Formula editor with math/date/text/conditional/lookup/aggregate functions | EL§9, A§22.2 (Monaco formula mode) | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Transforms, masks, thousand separators/decimals, currency/symbol position, date format, digits | A§14.7 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Calendar settings: system, first day, time step, 12/24h, timezone | A§14.7 `date` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| File storage disk, folder pattern, naming | A§14.7 `file` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Auto-fill on reference selection | A§14.7 `autofill`, `reference_previews.autofill_map` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Track changes, sensitive masking, personal data flag | `fields.track_changes/is_sensitive/is_personal_data`, A§7.5, A§19.16 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Justification level per role/user/department/status | `fields.justification_level`, `justification_rules`, A§19.3 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Access per role/user/department/status/mode: Hidden/RO/Editable/Required; matrix view | `field_access_rules`, A§16.3–16.4 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Conditions: show/hide, enable/disable, require, read-only, set/clear value, reload options, message | A§14.3 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Events on change/focus/blur: set field, reload options, custom action, webhook, notification | A§14.8 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Developer hook (Manage Code only) | `fields.hook_binding`, A§19.13 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Table settings: visible, sortable, filterable, searchable, label override, display format | `fields.table_settings`, translatable `column_label` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Export/import/print: exportable, importable, Excel column name, include in print/PDF | `fields.export_settings` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |

## §4.7 Conditions engine & expression language

Built and verified by: `app/Expressions`, `frontend/src/expressions`. Tests: `backend/tests/Conformance/ExpressionCorpusTest.php`, `frontend/tests/conformance/expressionCorpus.spec.ts`, `frontend/src/runtime/rules.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Visual builder with nested IF/AND/OR | A§14.3, A§22.2 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Operands: field values, repeater aggregates, user/roles/department/attributes, status, mode, dates, linked fields | EL§2 scopes, EL§5, EL§9.5 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Operators: equals…changed from/to | EL§4, EL§9.1 (`in`, `matches`, `changed_from_to`) | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Effects incl. block submit, trigger action | A§14.3 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Scope: fields, groups, options, actions, transitions, notifications | `conditions.owner_type` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Evaluated on client (UX) and server (enforcement) | A§3.3 step 7, A§22.3, `conditions.runtime` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| One language, written grammar in `docs/expression-language.md` | EL§2 | Phase 0: [#1](https://github.com/moalmansor/Core-Low-Code-Framework/pull/1) |
| Pure, no I/O/loops/assignment; typed values; explicit nulls; function library incl. Hijri, lookups, aggregates | EL§1, EL§3, EL§9 | Phase 0: [#1](https://github.com/moalmansor/Core-Low-Code-Framework/pull/1), Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| AST stored as JSON, consumed by both runtimes | EL§6, A§14.3 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Shared conformance corpus in repo, run in CI against PHP and TS | `docs/conformance/expression-corpus.json` (169 cases), EL§10, A§23 | Phase 0: [#1](https://github.com/moalmansor/Core-Low-Code-Framework/pull/1), Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Bounded: AST depth, path depth, timeout; server authoritative | EL§8 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Division by zero, overflow, type mismatch, missing refs → defined results surfaced as validation | EL§7 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |

## §4.8 Collections

Built and verified by: `Reference`, `Forms`. Tests: `backend/tests/Engine/ReferenceDataTest.php`, `backend/tests/Engine/RecordsExchangeTest.php`.

| Requirement | Covered in | Built in |
|---|---|---|
| Collections from key/value to tables, same field engine | `forms.kind=collection`, `collections` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Records managed in UI with Excel import/export | A§21.2 Records, A§19.5 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Sources for selects, lookups, cascades, filters | A§14.5, A§18 (DataSourceDriverRegistry) | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Relations 1:1, 1:N, N:N | `relations.type` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Schema explorer with ERD view | A§21.2 Schema (`/schema/erd`) | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |

## §4.9 Data storage strategy

Built and verified by: `Schema`, `Records`, `Infrastructure/Database`. Tests: `backend/tests/Engine/PublishLifecycleTest.php`, `backend/tests/Engine/ReferentialIntegrityTest.php`, `backend/tests/Unit/SchemaDifferTest.php`.

| Requirement | Covered in | Built in |
|---|---|---|
| Physical table per form/collection via versioned reversible migrations | A§11, A§12, ADR-0002 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Real FKs, proper types, indexes on filterable fields | A§11.2–11.3, A§11.6 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Repeaters/sub-forms as child tables | A§11.1 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Metadata layer describes tables/fields/relations | A§10.5 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Referential integrity; on-delete restrict/cascade/set null | A§11.6, A§9.4 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| `row_version` optimistic concurrency, conflict screen (reload / overwrite per field / cancel), never last-write-wins | A§11.2, A§19.1 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Migration plan of individually reversible steps, persisted, statuses | `migration_plans`, `migration_steps`, A§12.1 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Failure: stop, reverse, else Schema Inconsistent (locked), guided repair (retry/manual/restore) | A§12.2, `forms.state`, A§21.2 Schema | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Reconciliation check on demand and scheduled, both engines | A§12.6, `schema_reconciliation_reports` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Exclusive locks on form + related forms; queued second admin told why | A§12.4, `publish_locks` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Online DDL where supported; duration and lock impact shown | A§12.3, A§6.1 (`supportsOnlineDdl`, `estimateDdlImpact`) | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Logical backup before destructive steps; location/retention shown | A§12.5, `schema_snapshots` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |

## §4.10 Versioning & safe changes

Built and verified by: `Schema`, `Forms`. Tests: `backend/tests/Engine/PublishLifecycleTest.php`, `backend/tests/Unit/SchemaDifferTest.php`.

| Requirement | Covered in | Built in |
|---|---|---|
| Draft → preview → impact analysis → publish | A§13.1 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Impact analysis: records, views/filters/actions, downloads, notifications/permissions, reports, linked forms | A§13.2 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Add/rename migrate safely; remove archives column; type change validates first | A§11.5 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Status rename/add/remove/merge mapping screen | A§13.5, `status_mappings` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Full history, visual diff, one-click rollback | A§13.3, `form_versions.diff_from_previous` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Rollback classes stated in UI (metadata-only / additive / destructive w/ snapshot) | A§13.4, `form_versions.change_class` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Compare environments; import refuses ambiguous drift naming conflicts | A§13.6, `environment_drift_reports` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Record stores its form version | A§11.2 `form_version_id` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |

## §4.11 Roles & permissions

Built and verified by: `Access`, `Identity`. Tests: `backend/tests/Feature/Access/PermissionResolutionTest.php`, `backend/tests/Feature/Access/RolesAndPermissionsApiTest.php`, `backend/tests/Engine/RecordSecurityTest.php`, `backend/tests/Engine/RowPermissionsTest.php`.

| Requirement | Covered in | Built in |
|---|---|---|
| One interface; roles, multiple roles per user, grants to roles/users/departments | `roles`, `user_roles`, `permission_assignments`, A§21.2 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Form level permissions incl. custom actions & download profiles auto-registered | `permissions.scope_type`, A§21.1 key patterns | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Group/field level per role/user/status/mode | `field_access_rules`, A§16.3 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Status level (transitions) and action level | `transition.{uuid}.perform`, `action.{uuid}.run` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13), Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Record level: own, department, tree, all, custom | `record_access_rules`, A§16.6 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Menu level | `menu.{uuid}.view` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Full system permission list | A§21.1 (all spec names mapped) | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Precedence user > role > department; deny beats allow within a tier; more specific tier overrides a less specific one incl. its deny; hard deny overrides every tier | A§16.2, ADR-0009 (owner decision) | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| View as user; copy permissions; export/import sets | A§21.2 Roles & permissions | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Server-side enforcement via Policies/Gates on every request | A§7.2 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Computed, sparse storage | A§16.4 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Resolution order system → form → group → field → status → mode → subject | A§16.3 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Inheritance field←group←form; UI marks inherited vs explicit; reset to inherited | A§16.3, A§16.4 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Effective permissions resolved once per request, cached, invalidated on changes | A§16.5 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Matrix filtered/paged, deviating-only default, bulk edit | A§16.4, A§21.2 Form access matrix | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Explain access | A§16.7 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |

## §4.12 Workflow

Built and verified by: `Workflow`. Tests: `backend/tests/Engine/WorkflowTest.php`, `frontend/e2e/06-workflow.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Statuses: name, color, icon, initial/final | `statuses` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Transitions: from/to, roles/users, conditions, required fields, comment/attachments, actions & notifications | `transitions`, `notification_rules.trigger=status_changed`, `actions` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Vue Flow designer | A§19.2 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| SLA timers with escalation (notify, reassign, auto-transition) | `sla_rules`, `sla_timers`, A§19.2 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Status history with comments | `status_history` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |

## §4.13 Publishing & navigation

Built and verified by: `Forms`, `Experience`. Tests: `backend/tests/Engine/PagesNavigationTest.php`, `backend/tests/Engine/PublishLifecycleTest.php`, `frontend/e2e/19-experience.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| On publish choose menu location, name, application, allowed roles/users | A§21.2 publish payload, `menu_items`, permissions | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Drag-and-drop menu editor with nesting | A§21.2 Menus, A§5.3 menu-editor | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Unpublish/archive/republish without data loss | `forms.state`, A§21.2 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |

## §4.14 Table, view, edit, print

Built and verified by: `Views`, `Records`. Tests: `backend/tests/Engine/ViewsTest.php`, `backend/tests/Engine/RecordFiltersTest.php`, `frontend/e2e/05-interface.spec.ts`, `frontend/e2e/15-screens.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Unified table per form per role: columns incl. linked fields, order, width, pinning, sort, page size, formatting, totals | `views`, `view_columns`, A§17 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Filters per field type incl. linked fields; quick and advanced | `filters`, A§17.3 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Global search, sorting, column chooser, saved personal/shared views | `views.allow_*`, `saved_views`, `saved_view_shares` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Row options View/Edit/Log permission-controlled | `views.row_options`, A§21.1 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Bulk selection and actions; download menu | A§19.5, A§19.8 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13), Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Admin: record counts, full edit, soft delete + restore | `forms.record_count_cache`, A§11.2 soft delete | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| View Mode panels: related data, derived fields, widgets, timeline, comments, attachments, tabs/sections per role | `view_panels`, `record_comments`, `files` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Edit Mode: preview card, auto-fill, side drawer | `reference_previews` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Print layout and PDF (mPDF, Arabic) | `print_layouts`, A§19.5 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |

## §4.15 Actions

Built and verified by: `Actions`. Tests: `backend/tests/Engine/ActionsTest.php`, `frontend/e2e/09-actions.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Export Excel/CSV/PDF respecting columns, filters, permissions | `export_jobs`, A§19.5 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Custom downloads | A§19.8 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Import: mapping (saved), validation preview, error report, insert/update/upsert, dry run | `import_mappings`, `import_jobs`, A§19.5 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Bulk delete/status/field update; print; duplicate | `actions.builtin`, `bulk_operations` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Custom action steps: update fields, change status, email, webhook, document, linked record; chained with conditions | `action_steps`, A§14.9 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Egress allowlist; blocked ranges IPv4/IPv6; resolve-time validation; address pinning | A§7.4, `egress_allowlist` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10), Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| No cross-host redirects; timeout, size cap, retry/backoff | A§7.4 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Per-webhook signing secret; encrypted secrets/headers; masked logs | A§7.4, `webhooks` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14), Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Calls logged and visible in Operations Center | `webhook_deliveries`, A§19.7 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Import limits (size, rows, time) as background jobs | A§19.5 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| XXE/remote refs disabled; formulas read as text | A§7.3 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| CSV/Excel injection escaping | A§7.3, A§19.5 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Transaction per batch, idempotent retry, error report with row and reason | A§19.5 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Placement row/bulk/toolbar/view; own permission; confirmation | `actions.placements`, `action.{uuid}.run` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Heavy actions as jobs with progress and notification | A§8.1, A§19.5 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Runtime reads configured actions — no redeploy | A§3.5, A§1 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |

## §4.16 Email notifications

Built and verified by: `Notifications`. Tests: `backend/tests/Engine/NotificationsTest.php`, `frontend/e2e/07-notifications.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Triggers: create/update, status from/to, field changed/condition, scheduled reminders, SLA | `notification_rules.trigger` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Recipients: users, roles, departments, field users, creator, linked users, static | `notification_rules.recipients`, A§19.14 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| To/CC/BCC per rule | `notification_rules.recipients` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Visual editor, placeholders (fields, linked, system, links), conditional blocks, repeater tables, translatable, attachments, preview with real record, test send | `email_templates`, A§19.14 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Every email tracked in Operations Center | `email_queue`, A§19.7 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |

## §4.17 Document templates

Built and verified by: `Documents`. Tests: `backend/tests/Engine/DocumentsTest.php`, `frontend/e2e/08-documents.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| DOCX with placeholders or HTML templates | `document_templates`, A§19.14 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Generate DOCX/PDF incl. repeaters and linked data | A§19.14 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Usable in actions and email attachments | A§14.9, `notification_rules.attachments` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |

## §4.18 Reports & dashboards

Built and verified by: `Reports`. Tests: `backend/tests/Engine/ReportsTest.php`, `frontend/e2e/18-reports.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Report builder: any form/collection, joins via relations, columns, grouping, aggregates, filters, sorting | `reports.definition`, A§19.20, A§17 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Tables, pivots, charts (bar, line, pie, area, KPI) | `reports.visualization` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Dashboards: drag-drop layout, per-role visibility, export Excel/PDF | `dashboards`, `dashboard_widgets`, A§19.20 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |

## §4.19 Developer extensibility

Built and verified by: `Extensions`, `Platform`. Tests: `backend/tests/Engine/ExtensionsTest.php`, `backend/tests/Engine/PlatformApiTest.php`, `backend/tests/Engine/PackagesTest.php`, `frontend/e2e/16-extensions.spec.ts`, `frontend/e2e/17-packages.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Manage Code only | `system.manage_code`, A§19.13 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Server hooks (onLoad…onAction) and custom API endpoints | `extensions.hook_point/type`, A§3.3, A§19.13 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Client validators, custom field components, form scripts | `extensions.type`, A§19.13 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Versioned modules on disk, never `eval()`; Monaco editor | A§19.13, `/extensions` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Lifecycle: write, sandbox test, approval, deploy, history, rollback | `extension_versions`, A§19.13 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Hooks in transactions with timeouts and isolation; failures to Error Monitoring & Ops | A§19.13 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |

## §4.20 Audit log

Built and verified by: `Audit`. Tests: `backend/tests/Feature/Audit/AuditLogTest.php`, `backend/tests/Engine/RetentionTest.php`.

| Requirement | Covered in | Built in |
|---|---|---|
| Data changes, status changes, imports/exports/downloads/prints, logins/failed logins, retries/resends, permission/config changes | `audit_logs.event` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Per record/field: who, when, old/new, IP, UA, correlation, acting & on-behalf-of user | `audit_logs` columns | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Justifications stored with the entry, immutable | `audit_logs.justification_id`, `justifications` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Append-only; no edit/delete even for Super Admin; hash chain; verification job | A§19.15, ADR-0011 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Viewer filterable/exportable per record and system-wide | A§21.2 Audit | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |

## §4.21 Error monitoring

Built and verified by: `Monitoring`. Tests: `backend/tests/Feature/Monitoring/ErrorMonitoringTest.php`, `backend/tests/Engine/OperationsTest.php`.

| Requirement | Covered in | Built in |
|---|---|---|
| Capture trace, file/line, request, masked payload, user/role, form/record, hook/job, environment, time | `error_logs` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Correlation ID across jobs, emails, hooks | A§8.4 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Friendly message with reference ID | A§19.15, `error_logs.reference_code` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Secondary sink (file/external) as well as DB | A§19.15 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Grouping, frequency, first/last seen; status, assignee, notes; email alerts; filters | `error_groups`, A§21.2 Errors | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |

## §4.22 Framework capabilities

Built and verified by: `Records`, `Core`, `Platform`. Tests: `backend/tests/Engine/RecordsPipelineTest.php`, `backend/tests/Engine/RecordCommentsTest.php`, `backend/tests/Engine/RecordsExchangeTest.php`, `frontend/e2e/14-import.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Multiple applications on one core | `applications`, A§19.17 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Configuration packages export/import with conflict resolution | `config_packages`, A§13.6, A§14.12 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Auto REST API per form/collection: scoped tokens, OpenAPI, rate limits | A§21.2 Auto REST API, `api_tokens` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Incoming/outgoing webhooks with signing and delivery logs | `webhooks`, `inbound_endpoints`, `webhook_deliveries` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| In-app notification center | `in_app_notifications` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Settings: branding, languages, formats, calendar, SMTP, file limits, security policies, SSO/LDAP | `settings` groups, A§19.18 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |

## §4.23 Custom downloads

Built and verified by: `Downloads`. Tests: `backend/tests/Engine/DownloadsTest.php`, `frontend/e2e/10-downloads.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Profiles per base form | `download_profiles` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Relation tree explorer, unlimited depth, both directions, full path labels | A§17.1–17.2, A§21.2 `relation-tree` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| One-to-many: flatten / aggregate (count…join) / separate sheet | `download_profile_columns.to_many_mode/aggregate_fn`, A§17.3 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Columns: headers (translatable), order, width, format, calculated, static, system | `download_profile_columns` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Fixed filters, user's current filters/selection, runtime parameters | `download_profile_filters`, `download_profiles.parameters/apply_user_filters` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Excel multi-sheet/styled/frozen/logo/title/RTL; CSV BOM; PDF options | `download_profiles.*_options`, A§19.8 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| File name pattern; live preview 20 rows | `file_name_pattern`, A§19.8 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Auto-registered permission per profile | `download.{uuid}.use` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Field-level access at every level; preview effective columns per role | A§19.8 step 2, A§17.3 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Record-level security at every level | A§17.3, A§16.6 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Sensitive fields masked without permission | `field.{uuid}.view_sensitive`, A§7.2 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Download menu in table toolbar and record view | `download_profiles.available_in` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Personal profiles limited to accessible fields | `is_personal`, `create_personal_download_profiles` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Scheduled downloads emailed to users/roles | `download_schedules` (per-recipient execution) | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Optimized multi-level queries, chunking, no N+1 | A§17.3–17.4 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Background jobs, progress, notification, signed expiring link | `download_jobs`, A§19.8 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Max rows per profile | `download_profiles.max_rows` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Audit: who, profile, filters, rows, time | A§19.8 step 6 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Failures in Operations Center with retry; duplicable; in packages | A§19.7, A§14.12 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14), Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |

## §4.24 Edit justification

Built and verified by: `Justification`. Tests: `backend/tests/Engine/ViewsTest.php`, `backend/tests/Engine/RecordsPipelineTest.php`, `frontend/src/runtime/useJustification.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Per form, field/group, status, role/user/department, action/transition (incl. bulk, import), delete/restore; off by default | `justification_rules.scope/subject`, A§19.3 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Levels not required / optional / mandatory; conditional switching | `level`, `condition_id`, `level_when_condition` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Free text with min/max; reason codes from admin list/collection; note for certain codes; attachments; translatable title/help; change summary | `justification_rules`, `justification_reason_codes`, `justification_attachments` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Prompt after validation passes | A§19.3 step 5 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Server-side enforcement | A§3.3 step 9, A§19.3 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Bulk asks once (count shown); imports per batch | A§19.3 step 6, `justifications.affected_count` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13), Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Immutable, even for Super Admin | `justifications` (no update path), A§19.15, ADR-0011 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Shown in audit, history timeline, table/download/report columns; dedicated view permission | A§19.3 step 7, `system.view_justifications` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13), Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |

## §4.25 Assignment, queues & delegation

Built and verified by: `Assignment`. Tests: `backend/tests/Engine/WorkflowTest.php`, `frontend/e2e/06-workflow.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Assign to user/role/department, automatic on transition or manual | `assignments`, `assignment_rules`, A§19.4 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Rules: specific, field user, creator's manager, round-robin, least-loaded | `assignment_rules.strategy` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Reassignment permission-controlled, audited, optional justification | `system.reassign_records`, `justification_rules.scope=reassign` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| My Work across forms with due dates, SLA, priority | A§19.4, A§21.2 `/my-work` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Claim/release in role/department queues | `queue_claims` (unique active key) | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Admin configures queue forms/columns | `queues`, `queue_forms` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Delegation for a period with reason, restricted to forms; admin out-of-office | `delegations` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| On-behalf-of recorded in audit and notifications | `on_behalf_of_user_id` columns, A§19.4 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Parallel approvals: all / any N / weighted quorum; per-approver decisions; rejection behavior; queue, notifications, reminders, escalation | `approval_requests`, `approval_decisions`, A§19.4 | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |

## §4.26 Retention, archiving & personal data

Built and verified by: `Retention`. Tests: `backend/tests/Engine/RetentionTest.php`, `backend/tests/Engine/KeyRotationTest.php`, `frontend/e2e/23-retention.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Policies per data class with period and end action; scheduled, audited | `retention_policies`, `retention_runs`, A§19.16 | Phase 6: this pull request |
| Audit/error tables partitioned by time; archive path; restore procedure; size warnings | A§10.21, A§19.16, `operations_alert_rules.metric=storage_usage` | Phase 6: this pull request |
| Storage quotas per form/application with warning and hard limit; usage report | `storage_quotas`, A§21.2 | Phase 6: this pull request |
| Locate, export, delete/anonymize personal data preserving audit integrity | `personal_data_requests`, `subject_keys`, A§19.16, ADR-0014 | Phase 6: this pull request |
| Personal-data field flag drives search | `fields.is_personal_data` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 6: this pull request |
| Legal hold with reason | `legal_holds`, record `legal_hold` flag | Phase 6: this pull request |

## §4.27 Applications & tenancy

Built and verified by: `Experience`, `Organization`. Tests: `backend/tests/Engine/ApplicationBlueprintsTest.php`, `backend/tests/Engine/FeatureFlagsTest.php`.

| Requirement | Covered in | Built in |
|---|---|---|
| Many applications with own menus, forms, collections, permissions, branding, settings | `applications` + scoped objects | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Enable per department/role, clone, export, archive, retire | `app.{uuid}.access`, A§19.17 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Share across applications or isolate per form | `forms.data_sharing`, A§19.17 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Single vs multi-organization mode at setup; isolated data, branding, admins; global administrator; permissioned cross-org reporting | `organizations`, A§9.5, A§19.17, ADR-0004 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10), Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |

## §4.28 Appearance & theming

Built and verified by: Experience (themes), `frontend/src/theme`. Tests: `backend/tests/Engine/ThemesTest.php`, `frontend/src/theme/contrast.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Theme editor: colors, light/dark, radius, shadows, density, fonts per script | `themes.tokens`, A§19.11 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Logos, favicon, login background, browser title, email artwork | `theme_assets`, translatable `browser_title` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10), Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Per-application and per-organization themes with global default | `themes.application_id`, `organizations.theme_id` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Login content, welcome, legal links, support contact translatable | `themes` translatables | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Scoped, sanitized custom CSS that cannot hide security controls | A§19.11, A§7.3 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Import/export, presets, reset; contrast check before save | `themes.is_preset/contrast_report`, A§21.2 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |

## §4.29 Pages, home screens & navigation

Built and verified by: `Experience`. Tests: `backend/tests/Engine/PagesNavigationTest.php`, `backend/tests/Engine/HomeScreensTest.php`, `backend/tests/Engine/AnnouncementsSearchMaintenanceTest.php`, `frontend/e2e/19-experience.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Custom pages (content, link, dashboard, report, form host) with permissions | `pages`, `page.{uuid}.view` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Home screen per role/department/user from widgets | `home_screens`, `page_widgets`, A§18 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Navigation builder: unlimited depth, DnD, icons, live badges, separators, headers, visibility per role/department/condition | `menu_items` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Global search configuration: forms, fields, weights, display, per-role scope | `search_configs` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Announcements/banners: scope, schedule, dismissible, targeting, severity | `announcements`, `announcement_dismissals` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Maintenance mode system/application with admin access retained | A§19.17, `applications.maintenance_mode` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |

## §4.30 Blueprints & template library

Built and verified by: `Blueprints`. Tests: `backend/tests/Engine/BlueprintsTest.php`, `backend/tests/Engine/PartBlueprintsTest.php`, `backend/tests/Engine/ApplicationBlueprintsTest.php`, `frontend/e2e/22-blueprints.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Save form/collection/workflow/view/action/notification/dashboard/application as blueprint | `blueprints.kind` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Duplicate with structure / +permissions / everything | `blueprint_versions.include_mode`, `blueprint_instances` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Template library: categories, search, descriptions, previews | `blueprints.is_library/category/tags/preview_file_id` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Versioned; propagation with preview | `blueprint_versions`, `blueprint_instances.last_propagated_version_id`, A§21.2 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Import/export between environments | A§14.12 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |

## §4.31 Scheduler & automation

Built and verified by: `Automation`, `Scheduling`. Tests: `backend/tests/Engine/AutomationsTest.php`, `frontend/e2e/11-automations.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| No-code builder: trigger, conditions, steps from action library | `automations`, `automation_triggers`, `automation_steps` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| All triggers listed (record events, field/status change, condition, schedules, date reached with offset, inbound webhook, watched folder, manual) | `automation_triggers.type`, A§19.9 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| All steps listed incl. wait for delay/condition | `automation_steps.type` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Enable/disable, run now, test, concurrency, retry, loop protection, max records with confirmation | A§19.9, `automations` columns | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Run history with failures in Ops Center | `automation_runs` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Scheduled-task manager with next/last run | `scheduled_tasks`, A§8.2 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |

## §4.32 External access & portals

Built and verified by: `External`. Tests: `backend/tests/Engine/ExternalAccessTest.php`, `frontend/e2e/21-external.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| External forms with URL, theme, initial status | `external_forms` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| CAPTCHA, per-IP limits, time window, caps, email/SMS verification, password | `external_forms`, `submission_throttles`, ADR-0013 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| External user accounts with registration/approval, own role, own records only | `external_users`, A§19.12 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Tokenized single-record links with scoped actions | `access_tokens` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Signature requests with tracking and signed output in audit | `signature_requests` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Governed by permission, condition, justification, and audit rules | A§7.6, A§19.12 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |

## §4.33 Integrations

Built and verified by: `Integrations`. Tests: `backend/tests/Engine/DataSourcesTest.php`, `backend/tests/Engine/SyncJobsTest.php`, `backend/tests/Engine/NotificationChannelsTest.php`, `backend/tests/Feature/Security/EgressGatewayTest.php`, `frontend/e2e/20-integrations.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| External data sources (REST/DB view) with auth, headers, mapping, cache; usable like collections | `external_data_sources`, A§18, A§19.21 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Sync jobs: schedule, mapping, keys, conflicts, dry run, run log | `sync_jobs`, `sync_runs` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Inbound API endpoints per form with scoped tokens, docs, enforcement | `inbound_endpoints`, A§21.2 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Incoming webhooks with signature verification and payload mapping | `inbound_endpoints.signature_*`, `webhook_deliveries` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Channels beyond email (in-app, SMS, messaging), per rule and user preference | `notification_channels`, `notification_deliveries`, `user_preferences.notification_channels` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Integrations visible in Operations Center | A§19.7, A§19.21 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |

## §4.34 Reference data, calendars & numbering

Built and verified by: `Reference`. Tests: `backend/tests/Engine/ReferenceDataTest.php`.

| Requirement | Covered in | Built in |
|---|---|---|
| Business calendars (days, hours, holidays per country/org), assignable per department/form; working-time SLAs | `business_calendars`, `holidays`, `departments/forms.business_calendar_id`, A§19.19 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Numbering manager: patterns, padding, step, reset period, scope, current value, audited adjustment | `number_sequences`, A§19.19 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Currencies, exchange rates (manual/scheduled), history | `currencies`, `exchange_rates` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Units of measure and conversions | `units_of_measure` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Shared reference collections with one owner | `collections.is_shared_reference/owner_application_id` | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |

## §4.35 Data quality

Built and verified by: `DataQuality`. Tests: `backend/tests/Engine/DataQualityTest.php`, `frontend/e2e/12-data-quality.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Duplicate rules (exact/normalized/fuzzy), warn/block at entry, sweep | `duplicate_rules`, A§19.10 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Merge with survivor, per-field choice, repoint relations, audit + justification | `merge_history`, A§19.10 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Bulk update/reassign/status with preview, dry run, max-count guard, justification | `bulk_operations` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Repair tools: orphans, failing validation, guided fix | A§19.10, `bulk_operations.type` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Recycle bin with retention, audited restore | `recycle_bin` | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |

## §4.36 Self-service, impersonation, access policies

Built and verified by: `Identity`, `Adoption`. Tests: `backend/tests/Engine/SelfServiceTest.php`, `backend/tests/Feature/Identity/ImpersonationTest.php`, `backend/tests/Feature/Identity/AccessPoliciesTest.php`, `backend/tests/Engine/FeatureFlagsTest.php`.

| Requirement | Covered in | Built in |
|---|---|---|
| Profile preferences within admin limits | `user_preferences`, A§19.22 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Saved views, pinned records, shortcuts, personal API tokens (if permitted) | `saved_views`, `user_preferences`, `api_tokens` | Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13), Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Impersonation: banner, time limit, blocked sensitive actions, audited as admin | `impersonation_sessions`, A§7.7 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Access policies: IP allowlist, time windows, concurrent sessions, lifetime, device trust, stricter admin policy | `access_policies`, `trusted_devices`, A§19.22 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Feature flags per application/role | `feature_flags` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |

## §4.37 Help & adoption

Built and verified by: `Adoption`. Tests: `backend/tests/Engine/HelpContentTest.php`, `backend/tests/Engine/ToursTest.php`, `backend/tests/Engine/UsageInsightTest.php`, `frontend/e2e/23-adoption.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Help per form/field/page as tooltip/panel/page, translatable | `help_content` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Guided tours and hints per form/role with reset | `tours`, `tour_progress` | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Knowledge page per application; changelog published to users | `pages.type=knowledge/changelog`, A§19.23 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Usage insight: usage, abandonment, empty fields | `usage_metrics`, A§19.23 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |

## §5 Security requirements

Built and verified by: `Identity`, `Access`, Core (headers, CSP, egress), `Audit`, `Retention`. Tests: `backend/tests/Feature/Security/HttpEdgeTest.php`, `backend/tests/Feature/Security/PublicRoutesTest.php`, `backend/tests/Feature/Security/UntrustedInputTest.php`, `backend/tests/Feature/Security/EgressGatewayTest.php`, `backend/tests/Feature/Identity/SessionsAndPasswordsTest.php`, `docs/security-review.md`.

| Requirement | Covered in | Built in |
|---|---|---|
| OWASP Top 10 | A§7.8 | all |
| Server-side authz and record checks (IDOR); mass-assignment protection for dynamic models | A§7.2, A§3.2 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10), Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Parameterized queries; strict input validation incl. metadata | A§7.3, A§14 | all |
| XSS, sanitized rich text, strict CSP, CSRF, security headers | A§7.3 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Upload validation, private storage, signed URLs, ClamAV | A§7.3, `files` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Password policy, 2FA mandatory for admins, lockout, session timeout & management, SSO/LDAP, rate limiting | A§7.1 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| SSRF: single egress gateway for all outbound calls | A§7.4 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Untrusted parsing safety | A§7.3 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14) |
| Expression safety | EL§1, EL§8 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| External surfaces separated, no implicit permissions, rate limited, scoped tokens | A§7.6, A§19.12 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Admin-authored content sanitized | A§19.11 | Phase 4: [#14](https://github.com/moalmansor/Core-Low-Code-Framework/pull/14), Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Impersonation cannot escalate, visible, time-limited, audited | A§7.7 | Phase 5: [#15](https://github.com/moalmansor/Core-Low-Code-Framework/pull/15) |
| Encryption of sensitive fields; secrets in env; per-tenant/per-field keys; rotation procedure | A§7.5, `encryption_keys` | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10), Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Scheduled backups with restore procedure; dependency scanning | A§23 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10), Phase 6: this pull request |
| No sensitive data in logs or user-facing errors | A§7.5 Masker, A§19.15 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |

## §6 Non-functional requirements

Built and verified by: `all modules; perf/; docs/performance.md`. Tests: `backend/tests/Performance (Performance workflow)`, `frontend/e2e/25-accessibility.spec.ts`, `frontend/src/i18n/catalog.spec.ts`.

| Requirement | Covered in | Built in |
|---|---|---|
| Server-side pagination/filtering/sorting | A§3.4, A§17 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12), Phase 3: [#13](https://github.com/moalmansor/Core-Low-Code-Framework/pull/13) |
| Metadata caching with invalidation | A§3.1, A§16.5 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Background jobs for heavy work | A§8.1 | Phase 1: [#10](https://github.com/moalmansor/Core-Low-Code-Framework/pull/10) |
| Transactions, constraints, idempotent retries, optimistic concurrency on every write | A§3.3, A§19.1, A§19.6 | Phase 2: [#11](https://github.com/moalmansor/Core-Low-Code-Framework/pull/11), [#12](https://github.com/moalmansor/Core-Low-Code-Framework/pull/12) |
| Performance budgets written into architecture and load-tested in Phase 6 | A§20 | Phase 0: [#1](https://github.com/moalmansor/Core-Low-Code-Framework/pull/1), Phase 6: this pull request |
| Responsive, WCAG 2.1 AA, consistent RTL/LTR | A§22.4, A§19.24, A§19.11 | all |
| Modular, strictly typed, documented; tests for every module on both engines | A§4, A§23, A§6.3 | all |

## §7 Data model

Built and verified by: `database/migrations; Schema`. Tests: `backend/tests/Feature/Database/SchemaConformanceTest.php`.

Every entity listed in specification §7 is defined with all columns, types,
indexes, and foreign keys in A§10. The one-to-one mapping is in A§10.26. Supporting
tables required by §2–§6 are listed there too. Physical per-form tables: A§11.

| §7 group | Entities | Covered in |
|---|---|---|
| Structure | 12 | A§10.5 |
| Access | 8 | A§10.4 |
| Workflow | 5 | A§10.8 |
| Views & actions | 8 | A§10.9 |
| Custom downloads | 5 | A§10.10 |
| Justification & change control | 4 | A§10.11 |
| Assignment & delegation | 7 | A§10.12 |
| Retention & personal data | 6 | A§10.25 |
| Schema management | 5 | A§10.7 |
| Localization | 2 | A§10.3 |
| Appearance & pages | 8 | A§10.23 |
| Blueprints | 3 | A§10.17 |
| Automation | 5 | A§10.18 |
| External access | 5 | A§10.19 |
| Integrations | 5 | A§10.20 |
| Reference data | 6 | A§10.15 |
| Data quality | 4 | A§10.16 |
| Access & adoption | 5 | A§10.24 |
| Notifications | 4 | A§10.13 |
| Documents & reports | 4 | A§10.14 |
| Operations & monitoring | 4 | A§10.21 (journal in A§10.6) |
| Extensibility & integration | 5 | A§10.22 |
| System | 3 | A§10.2 |
