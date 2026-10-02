# Phase 0 Design Coverage Check

Specification §8.2: *"For Phase 0, which produces no code, steps 2–5 are replaced by
a design coverage check: confirm that every requirement in sections 2 to 7 is
covered by the architecture and data model, and list where each is covered."*

Legend: **A§n** = `docs/architecture.md` section n; **EL§n** =
`docs/expression-language.md` section n; **ADR-n** = `docs/decisions/`; table names
refer to the ERD (A§10). The **Phase** column says where each requirement will be
built (A§25). Result of the check: **every requirement in §2–§7 is covered**. One
specification conflict (§4.11 precedence) was decided by the owner and the
specification updated (ADR-0009). The phase count follows the specification's
eight phases, confirmed by the owner (ADR-0016).

## §2 First-run behavior

| Requirement | Covered in | Phase |
|---|---|---|
| Empty shell: login, sidebar, top bar, Admin Console only | A§1 (principle 8), A§19.18 | 1 |
| No business forms, sample data, demo modules, or example content | A§1, A§19.18 (seeders list), A§10.15 (shared reference collections not seeded) | 1 |
| Seed only core roles (Super Admin, Admin, Developer, User) | A§10.4 `roles`, A§21.1 seeded grants | 1 |
| Seed the full system permission catalog | A§21.1, A§10.4 `permissions` | 1 |
| Seed default system settings | A§19.18, A§10.2 `settings`; platform org + locales as settings (ADR-0004, ADR-0007) | 1 |
| Setup wizard: name, logo, favicon | A§19.18, A§21.2 Setup | 1 |
| Default language and enabled languages | A§19.18, A§10.3 `locales` | 1 |
| Timezone, date/number formats | A§19.18, `settings` group `formats` | 1 |
| Calendar system (Gregorian/Hijri/both) | A§19.18, `locales.calendar`, `settings` | 1 |
| SMTP with test email | A§19.18, A§21.2 `POST /setup/smtp-test` | 1 |
| Super Admin account with password policy and mandatory 2FA | A§19.18, A§7.1 | 1 |
| Wizard locked permanently after setup | A§7.1 (SetupLock), A§19.18 | 1 |
| Admin lands on the Admin Console | A§19.18 | 1 |

## §3 Tech stack

| Requirement | Covered in | Phase |
|---|---|---|
| Laravel 12+, PHP 8.3+, RESTful JSON API | A§2, A§5.2, A§21 | 1 |
| Sanctum (SPA cookie + tokens), Fortify (2FA, reset) | A§7.1, `api_tokens`, `sessions` | 1 |
| maatwebsite/excel | A§19.5, ADR-0020 | 2/4 |
| mpdf/mpdf with full Arabic | A§19.5, A§19.8, A§19.14 | 3/4 |
| phpoffice/phpword | A§19.14 | 4 |
| LdapRecord | A§7.1 | 1 |
| Socialite (OAuth2/OIDC) | A§7.1 | 1 |
| Horizon + Redis queues | A§8.1 | 1 |
| Laravel Scheduler | A§8.2, `scheduled_tasks` | 1 |
| Filesystem local + S3 | A§5.2 Infrastructure/Storage, `files.disk` | 1 |
| ClamAV, toggled from settings | A§7.3 file uploads, `files.scan_status`, `settings` group `clamav` | 1 |
| Vue 3 Composition API + TS + Vite | A§5.3, A§22 | 1 |
| Pinia, Vue Router | A§22.1, A§5.3 | 1 |
| Tailwind + PrimeVue 4 with RTL | A§22.4, A§19.24, A§19.11 | 1 |
| vuedraggable, TipTap, Monaco, Vue Flow, ECharts, Leaflet, signature_pad, qrcode, JsBarcode | A§22.2, A§22.4, A§19.2, A§19.20 | 2–5 |
| Hijri date adapter | A§19.24, EL§9.4 (Umm al-Qura) | 1/2 |
| MySQL 8+ and SQL Server 2019+ fully supported | A§6, A§9.2, A§23 CI matrix | 1+ |
| All access via Eloquent / Query Builder | A§2 (layers), A§3.2, A§3.4 | all |
| Engine differences isolated in a driver layer with tests for both | A§6.1–6.3 | 1 |
| Arabic (RTL) and English (LTR) enabled; translation manager | A§10.3, A§19.24, A§21.2 Locales & translations | 1 |
| Translations table keyed by object and locale (no fixed AR/EN columns) | A§10.3 `translations`, A§9.3 (Translatable lists), ADR-0007 | 1 |
| Locale declares direction, calendar, number formatting, fallback | A§10.3 `locales` | 1 |
| Missing translations fall back; manager lists untranslated | A§10.3, A§19.24, `GET /translations?untranslated=1` | 1 |
| Pest, Vitest, Playwright | A§23, A§6.3 | 1+ |
| Docker Compose (app, worker, scheduler, Redis, MySQL, SQL Server, ClamAV, Mailpit) | A§23 | 1 |
| `.devcontainer` for Codespaces | A§23, A§5.1 | 1 |
| GitHub Actions: services, full suites, Larastan/ESLint/Prettier, audits | A§23 | 1 |
| Playwright artifacts uploaded every run | A§23 | 1 |
| Full installation documentation | A§25 (Phase 1 README, Phase 6 guides) | 1/6 |

## §4.1 Admin Console

| Requirement | Covered in | Phase |
|---|---|---|
| Single control center listing all 28 areas | A§19.18 (module-registered navigation tree), A§21.2 `GET /admin/console` | 1→5 |
| Each area visible only to holders of its permission | A§19.18, A§21.1 | 1 |

## §4.2 Operations Center

| Requirement | Covered in | Phase |
|---|---|---|
| Email queue statuses incl. Stuck (configurable threshold) | `email_queue.status`, A§19.7, settings `operations.stuck_email_minutes` | 4 |
| Recipients To/CC/BCC, subject, linked form/record/rule, attempts, failure reason | `email_queue` columns | 4 |
| Resend single/bulk, edit recipients & resend, cancel, view HTML | A§19.7, A§21.2 Operations | 4 |
| Submission journal written before processing; user, form, version, payload, error, trace, correlation ID | A§3.3 step 0, `submission_journal`, A§19.6 | 2 (capture) / 4 (UI) |
| Retry/resync single/bulk, edit payload & retry, discard with mandatory reason | A§19.6, A§19.7 | 4 |
| Idempotent retries (no duplicates) | A§19.6 (idempotency key, uuid derivation) | 2 |
| Failed jobs (imports, exports, downloads, webhooks, scheduled tasks, hooks, documents): inspect/retry/discard | A§8.1, A§19.7 | 4 |
| Health widgets: queues, stuck items, scheduler, storage, DB/Redis/SMTP/ClamAV | A§19.7 | 4 |
| Email alerts by role over thresholds | `operations_alert_rules`, A§19.7 | 4 |
| All Operations Center actions audited | A§19.7 | 4 |

## §4.3 Form Builder canvas

| Requirement | Covered in | Phase |
|---|---|---|
| Three-panel layout: palette (searchable, categorized), canvas, properties | A§22.2 | 2 |
| Drag to canvas or into groups at any depth; click to insert at selection | A§22.2 | 2 |
| Reorder, move between groups, duplicate, copy/paste across forms, delete, multi-select bulk changes | A§22.2 (commands, clipboard MIME, BulkUpdate) | 2 |
| Undo/redo full history, keyboard shortcuts, autosave drafts | A§22.2, A§13.1, ADR-0018 | 2 |
| Reusable field library | `field_templates`, A§21.2 Field library | 2 |
| Live preview: desktop/tablet/mobile, AR/EN, as any role/user | A§22.2, `GET /forms/{uuid}/preview?as_user=` | 2 |

## §4.4 Input types

| Requirement | Covered in | Phase |
|---|---|---|
| All native HTML input types (text…reset) | A§14.4 native list, A§11.3 storage | 2 |
| Native elements: textarea, select, multiple, optgroup, datalist, output, progress, meter, fieldset/legend | A§14.4 | 2 |
| Rich text, markdown, code | A§14.4, A§11.3 | 2 |
| Currency, percentage, decimal precision, auto-number pattern, formula | A§14.4, A§11.3, `number_sequences`, EL | 2 |
| Calendar (Gregorian/Hijri switchable/dual), date/time/datetime range, duration | A§14.4, A§14.7 (calendar), A§11.3 | 2 |
| Toggle, checkbox/radio/button groups, searchable dropdown, chips, tags, cascading, lookup with preview card, user/role/department/country/city pickers, rating, slider, color palette | A§14.4, A§14.5, `reference_previews` | 2 |
| Multi-file drag-drop, image crop/resize, camera | A§14.4, A§11.3, `files` | 2 |
| Signature, map (Leaflet), phone w/ country, national ID pattern+checksum, IBAN, barcode/QR display & scan, JSON, key-value, consent with terms | A§14.4, A§11.3, `fields` translatable `consent_terms` | 2 |
| Display elements: static HTML, heading, divider, spacer, image, alert, link | A§14.4 (`is_stored=0`) | 2 |
| Layout: section, fieldset, card, tabs/tab, wizard/step, row/columns (1–12 per breakpoint), collapsible/accordion, repeater, inline sub-form | A§14.4 groups, `field_groups.type`, A§11.1 | 2 |

## §4.5 Group properties

| Requirement | Covered in | Phase |
|---|---|---|
| Title/description (translatable), icon, CSS class | `field_groups.layout`, translatable `title`/`description` | 2 |
| Columns per breakpoint, spacing, border/background | `field_groups.layout` | 2 |
| Collapsible, default state, order | `field_groups.collapsible/default_state/sort_order` | 2 |
| Hide/read-only/disable by roles, users, departments, status, mode, field values, user attributes, linked data, date/time | `field_access_rules` (target=group), `conditions` (owner=group), EL§2 scopes, A§16.3 | 2/3 |
| Visual rule builder | A§14.3, A§22.2 | 2 |
| Require at least N filled; group custom rules with messages | `field_groups.validation` | 2 |
| Repeater: min/max/default rows, per-role add/remove/reorder, totals/aggregates, table/cards, child table with real FK | `field_groups.repeater`, A§11.1–11.2 (`parent_id` FK) | 2 |
| Wizard: validate before next, jump, step visibility | `field_groups.wizard`, conditions | 2 |

## §4.6 Field properties

| Requirement | Covered in | Phase |
|---|---|---|
| Key (auto, editable, unique), label/placeholder translatable, help/tooltip/description, prefix/suffix, icon, size, width per breakpoint, label position, autofocus, tab index, autocomplete, spellcheck, CSS | `fields` (key unique per form, `ui` JSON, translatables) | 2 |
| Bind to existing table/column (introspection) or auto-create | `forms.binding_mode`, `fields.column_name`, A§11.1, A§6.1 introspection | 2 |
| Column name, type (suggested), length/precision/scale, nullable, DB default | `fields` columns, A§11.3 | 2 |
| Index none/index/unique (scoped) | `fields.index_type/unique_scope`, A§11.2 | 2 |
| Stored encrypted | `fields.is_encrypted`, A§7.5 | 2 |
| Relation binding: type, target, display/value columns, on-delete | `relations`, A§11.6 | 2 |
| Options: static (value/label/color/icon/order), collection, form records, visual query builder | `field_options`, A§14.5 | 2 |
| Cascading, custom values, min/max selections, defaults, searchable/lazy, grouping | A§14.5, `field_options.group_key/parent_value` | 2 |
| Validation: required, length, number, regex, format presets, date rules (relative, weekdays, dates, past/future), file rules, uniqueness (scoped), cross-field, async, per-rule messages | A§14.6, EL (relative dates) | 2 |
| Defaults: static, current user/department/date, URL param, other field, formula, referenced record | A§14.7 `default` | 2 |
| Formula editor with math/date/text/conditional/lookup/aggregate functions | EL§9, A§22.2 (Monaco formula mode) | 2 |
| Transforms, masks, thousand separators/decimals, currency/symbol position, date format, digits | A§14.7 | 2 |
| Calendar settings: system, first day, time step, 12/24h, timezone | A§14.7 `date` | 2 |
| File storage disk, folder pattern, naming | A§14.7 `file` | 2 |
| Auto-fill on reference selection | A§14.7 `autofill`, `reference_previews.autofill_map` | 2/3 |
| Track changes, sensitive masking, personal data flag | `fields.track_changes/is_sensitive/is_personal_data`, A§7.5, A§19.16 | 2 |
| Justification level per role/user/department/status | `fields.justification_level`, `justification_rules`, A§19.3 | 3 |
| Access per role/user/department/status/mode: Hidden/RO/Editable/Required; matrix view | `field_access_rules`, A§16.3–16.4 | 2/3 |
| Conditions: show/hide, enable/disable, require, read-only, set/clear value, reload options, message | A§14.3 | 2 |
| Events on change/focus/blur: set field, reload options, custom action, webhook, notification | A§14.8 | 2/4 |
| Developer hook (Manage Code only) | `fields.hook_binding`, A§19.13 | 5 |
| Table settings: visible, sortable, filterable, searchable, label override, display format | `fields.table_settings`, translatable `column_label` | 2/3 |
| Export/import/print: exportable, importable, Excel column name, include in print/PDF | `fields.export_settings` | 2/4 |

## §4.7 Conditions engine & expression language

| Requirement | Covered in | Phase |
|---|---|---|
| Visual builder with nested IF/AND/OR | A§14.3, A§22.2 | 2 |
| Operands: field values, repeater aggregates, user/roles/department/attributes, status, mode, dates, linked fields | EL§2 scopes, EL§5, EL§9.5 | 2 |
| Operators: equals…changed from/to | EL§4, EL§9.1 (`in`, `matches`, `changed_from_to`) | 2 |
| Effects incl. block submit, trigger action | A§14.3 | 2 |
| Scope: fields, groups, options, actions, transitions, notifications | `conditions.owner_type` | 2+ |
| Evaluated on client (UX) and server (enforcement) | A§3.3 step 7, A§22.3, `conditions.runtime` | 2 |
| One language, written grammar in `docs/expression-language.md` | EL§2 | 0 ✔ |
| Pure, no I/O/loops/assignment; typed values; explicit nulls; function library incl. Hijri, lookups, aggregates | EL§1, EL§3, EL§9 | 0 ✔ / 2 |
| AST stored as JSON, consumed by both runtimes | EL§6, A§14.3 | 2 |
| Shared conformance corpus in repo, run in CI against PHP and TS | `docs/conformance/expression-corpus.json` (169 cases), EL§10, A§23 | 0 ✔ / 2 |
| Bounded: AST depth, path depth, timeout; server authoritative | EL§8 | 2 |
| Division by zero, overflow, type mismatch, missing refs → defined results surfaced as validation | EL§7 | 2 |

## §4.8 Collections

| Requirement | Covered in | Phase |
|---|---|---|
| Collections from key/value to tables, same field engine | `forms.kind=collection`, `collections` | 2 |
| Records managed in UI with Excel import/export | A§21.2 Records, A§19.5 | 2 |
| Sources for selects, lookups, cascades, filters | A§14.5, A§18 (DataSourceDriverRegistry) | 2 |
| Relations 1:1, 1:N, N:N | `relations.type` | 2 |
| Schema explorer with ERD view | A§21.2 Schema (`/schema/erd`) | 2 |

## §4.9 Data storage strategy

| Requirement | Covered in | Phase |
|---|---|---|
| Physical table per form/collection via versioned reversible migrations | A§11, A§12, ADR-0002 | 2 |
| Real FKs, proper types, indexes on filterable fields | A§11.2–11.3, A§11.6 | 2 |
| Repeaters/sub-forms as child tables | A§11.1 | 2 |
| Metadata layer describes tables/fields/relations | A§10.5 | 2 |
| Referential integrity; on-delete restrict/cascade/set null | A§11.6, A§9.4 | 2 |
| `row_version` optimistic concurrency, conflict screen (reload / overwrite per field / cancel), never last-write-wins | A§11.2, A§19.1 | 2 |
| Migration plan of individually reversible steps, persisted, statuses | `migration_plans`, `migration_steps`, A§12.1 | 2 |
| Failure: stop, reverse, else Schema Inconsistent (locked), guided repair (retry/manual/restore) | A§12.2, `forms.state`, A§21.2 Schema | 2 |
| Reconciliation check on demand and scheduled, both engines | A§12.6, `schema_reconciliation_reports` | 2 |
| Exclusive locks on form + related forms; queued second admin told why | A§12.4, `publish_locks` | 2 |
| Online DDL where supported; duration and lock impact shown | A§12.3, A§6.1 (`supportsOnlineDdl`, `estimateDdlImpact`) | 2 |
| Logical backup before destructive steps; location/retention shown | A§12.5, `schema_snapshots` | 2 |

## §4.10 Versioning & safe changes

| Requirement | Covered in | Phase |
|---|---|---|
| Draft → preview → impact analysis → publish | A§13.1 | 2 |
| Impact analysis: records, views/filters/actions, downloads, notifications/permissions, reports, linked forms | A§13.2 | 2 |
| Add/rename migrate safely; remove archives column; type change validates first | A§11.5 | 2 |
| Status rename/add/remove/merge mapping screen | A§13.5, `status_mappings` | 3 |
| Full history, visual diff, one-click rollback | A§13.3, `form_versions.diff_from_previous` | 2 |
| Rollback classes stated in UI (metadata-only / additive / destructive w/ snapshot) | A§13.4, `form_versions.change_class` | 2 |
| Compare environments; import refuses ambiguous drift naming conflicts | A§13.6, `environment_drift_reports` | 5 |
| Record stores its form version | A§11.2 `form_version_id` | 2 |

## §4.11 Roles & permissions

| Requirement | Covered in | Phase |
|---|---|---|
| One interface; roles, multiple roles per user, grants to roles/users/departments | `roles`, `user_roles`, `permission_assignments`, A§21.2 | 1 |
| Form level permissions incl. custom actions & download profiles auto-registered | `permissions.scope_type`, A§21.1 key patterns | 2/4 |
| Group/field level per role/user/status/mode | `field_access_rules`, A§16.3 | 2/3 |
| Status level (transitions) and action level | `transition.{uuid}.perform`, `action.{uuid}.run` | 3/4 |
| Record level: own, department, tree, all, custom | `record_access_rules`, A§16.6 | 3 |
| Menu level | `menu.{uuid}.view` | 2 |
| Full system permission list | A§21.1 (all spec names mapped) | 1 |
| Precedence user > role > department; deny beats allow within a tier; more specific tier overrides a less specific one incl. its deny; hard deny overrides every tier | A§16.2, ADR-0009 (owner decision) | 1 |
| View as user; copy permissions; export/import sets | A§21.2 Roles & permissions | 1 |
| Server-side enforcement via Policies/Gates on every request | A§7.2 | 1 |
| Computed, sparse storage | A§16.4 | 2 |
| Resolution order system → form → group → field → status → mode → subject | A§16.3 | 2/3 |
| Inheritance field←group←form; UI marks inherited vs explicit; reset to inherited | A§16.3, A§16.4 | 2 |
| Effective permissions resolved once per request, cached, invalidated on changes | A§16.5 | 2 |
| Matrix filtered/paged, deviating-only default, bulk edit | A§16.4, A§21.2 Form access matrix | 2 |
| Explain access | A§16.7 | 2 |

## §4.12 Workflow

| Requirement | Covered in | Phase |
|---|---|---|
| Statuses: name, color, icon, initial/final | `statuses` | 3 |
| Transitions: from/to, roles/users, conditions, required fields, comment/attachments, actions & notifications | `transitions`, `notification_rules.trigger=status_changed`, `actions` | 3 |
| Vue Flow designer | A§19.2 | 3 |
| SLA timers with escalation (notify, reassign, auto-transition) | `sla_rules`, `sla_timers`, A§19.2 | 3 |
| Status history with comments | `status_history` | 3 |

## §4.13 Publishing & navigation

| Requirement | Covered in | Phase |
|---|---|---|
| On publish choose menu location, name, application, allowed roles/users | A§21.2 publish payload, `menu_items`, permissions | 2 |
| Drag-and-drop menu editor with nesting | A§21.2 Menus, A§5.3 menu-editor | 2 |
| Unpublish/archive/republish without data loss | `forms.state`, A§21.2 | 2 |

## §4.14 Table, view, edit, print

| Requirement | Covered in | Phase |
|---|---|---|
| Unified table per form per role: columns incl. linked fields, order, width, pinning, sort, page size, formatting, totals | `views`, `view_columns`, A§17 | 3 |
| Filters per field type incl. linked fields; quick and advanced | `filters`, A§17.3 | 3 |
| Global search, sorting, column chooser, saved personal/shared views | `views.allow_*`, `saved_views`, `saved_view_shares` | 3 |
| Row options View/Edit/Log permission-controlled | `views.row_options`, A§21.1 | 3 |
| Bulk selection and actions; download menu | A§19.5, A§19.8 | 3/4 |
| Admin: record counts, full edit, soft delete + restore | `forms.record_count_cache`, A§11.2 soft delete | 3 |
| View Mode panels: related data, derived fields, widgets, timeline, comments, attachments, tabs/sections per role | `view_panels`, `record_comments`, `files` | 3 |
| Edit Mode: preview card, auto-fill, side drawer | `reference_previews` | 3 |
| Print layout and PDF (mPDF, Arabic) | `print_layouts`, A§19.5 | 3 |

## §4.15 Actions

| Requirement | Covered in | Phase |
|---|---|---|
| Export Excel/CSV/PDF respecting columns, filters, permissions | `export_jobs`, A§19.5 | 4 |
| Custom downloads | A§19.8 | 4 |
| Import: mapping (saved), validation preview, error report, insert/update/upsert, dry run | `import_mappings`, `import_jobs`, A§19.5 | 4 |
| Bulk delete/status/field update; print; duplicate | `actions.builtin`, `bulk_operations` | 4 |
| Custom action steps: update fields, change status, email, webhook, document, linked record; chained with conditions | `action_steps`, A§14.9 | 4 |
| Egress allowlist; blocked ranges IPv4/IPv6; resolve-time validation; address pinning | A§7.4, `egress_allowlist` | 1 (gateway) / 4 |
| No cross-host redirects; timeout, size cap, retry/backoff | A§7.4 | 1 |
| Per-webhook signing secret; encrypted secrets/headers; masked logs | A§7.4, `webhooks` | 4/5 |
| Calls logged and visible in Operations Center | `webhook_deliveries`, A§19.7 | 4 |
| Import limits (size, rows, time) as background jobs | A§19.5 | 4 |
| XXE/remote refs disabled; formulas read as text | A§7.3 | 4 |
| CSV/Excel injection escaping | A§7.3, A§19.5 | 4 |
| Transaction per batch, idempotent retry, error report with row and reason | A§19.5 | 4 |
| Placement row/bulk/toolbar/view; own permission; confirmation | `actions.placements`, `action.{uuid}.run` | 4 |
| Heavy actions as jobs with progress and notification | A§8.1, A§19.5 | 4 |
| Runtime reads configured actions — no redeploy | A§3.5, A§1 | 4 |

## §4.16 Email notifications

| Requirement | Covered in | Phase |
|---|---|---|
| Triggers: create/update, status from/to, field changed/condition, scheduled reminders, SLA | `notification_rules.trigger` | 4 |
| Recipients: users, roles, departments, field users, creator, linked users, static | `notification_rules.recipients`, A§19.14 | 4 |
| To/CC/BCC per rule | `notification_rules.recipients` | 4 |
| Visual editor, placeholders (fields, linked, system, links), conditional blocks, repeater tables, translatable, attachments, preview with real record, test send | `email_templates`, A§19.14 | 4 |
| Every email tracked in Operations Center | `email_queue`, A§19.7 | 4 |

## §4.17 Document templates

| Requirement | Covered in | Phase |
|---|---|---|
| DOCX with placeholders or HTML templates | `document_templates`, A§19.14 | 4 |
| Generate DOCX/PDF incl. repeaters and linked data | A§19.14 | 4 |
| Usable in actions and email attachments | A§14.9, `notification_rules.attachments` | 4 |

## §4.18 Reports & dashboards

| Requirement | Covered in | Phase |
|---|---|---|
| Report builder: any form/collection, joins via relations, columns, grouping, aggregates, filters, sorting | `reports.definition`, A§19.20, A§17 | 5 |
| Tables, pivots, charts (bar, line, pie, area, KPI) | `reports.visualization` | 5 |
| Dashboards: drag-drop layout, per-role visibility, export Excel/PDF | `dashboards`, `dashboard_widgets`, A§19.20 | 5 |

## §4.19 Developer extensibility

| Requirement | Covered in | Phase |
|---|---|---|
| Manage Code only | `system.manage_code`, A§19.13 | 5 |
| Server hooks (onLoad…onAction) and custom API endpoints | `extensions.hook_point/type`, A§3.3, A§19.13 | 5 |
| Client validators, custom field components, form scripts | `extensions.type`, A§19.13 | 5 |
| Versioned modules on disk, never `eval()`; Monaco editor | A§19.13, `/extensions` | 5 |
| Lifecycle: write, sandbox test, approval, deploy, history, rollback | `extension_versions`, A§19.13 | 5 |
| Hooks in transactions with timeouts and isolation; failures to Error Monitoring & Ops | A§19.13 | 5 |

## §4.20 Audit log

| Requirement | Covered in | Phase |
|---|---|---|
| Data changes, status changes, imports/exports/downloads/prints, logins/failed logins, retries/resends, permission/config changes | `audit_logs.event` | 1+ |
| Per record/field: who, when, old/new, IP, UA, correlation, acting & on-behalf-of user | `audit_logs` columns | 1 |
| Justifications stored with the entry, immutable | `audit_logs.justification_id`, `justifications` | 3 |
| Append-only; no edit/delete even for Super Admin; hash chain; verification job | A§19.15, ADR-0011 | 1 |
| Viewer filterable/exportable per record and system-wide | A§21.2 Audit | 1 |

## §4.21 Error monitoring

| Requirement | Covered in | Phase |
|---|---|---|
| Capture trace, file/line, request, masked payload, user/role, form/record, hook/job, environment, time | `error_logs` | 1 |
| Correlation ID across jobs, emails, hooks | A§8.4 | 1 |
| Friendly message with reference ID | A§19.15, `error_logs.reference_code` | 1 |
| Secondary sink (file/external) as well as DB | A§19.15 | 1 |
| Grouping, frequency, first/last seen; status, assignee, notes; email alerts; filters | `error_groups`, A§21.2 Errors | 1 |

## §4.22 Framework capabilities

| Requirement | Covered in | Phase |
|---|---|---|
| Multiple applications on one core | `applications`, A§19.17 | 2/5 |
| Configuration packages export/import with conflict resolution | `config_packages`, A§13.6, A§14.12 | 5 |
| Auto REST API per form/collection: scoped tokens, OpenAPI, rate limits | A§21.2 Auto REST API, `api_tokens` | 5 |
| Incoming/outgoing webhooks with signing and delivery logs | `webhooks`, `inbound_endpoints`, `webhook_deliveries` | 5 |
| In-app notification center | `in_app_notifications` | 4 |
| Settings: branding, languages, formats, calendar, SMTP, file limits, security policies, SSO/LDAP | `settings` groups, A§19.18 | 1 |

## §4.23 Custom downloads

| Requirement | Covered in | Phase |
|---|---|---|
| Profiles per base form | `download_profiles` | 4 |
| Relation tree explorer, unlimited depth, both directions, full path labels | A§17.1–17.2, A§21.2 `relation-tree` | 4 |
| One-to-many: flatten / aggregate (count…join) / separate sheet | `download_profile_columns.to_many_mode/aggregate_fn`, A§17.3 | 4 |
| Columns: headers (translatable), order, width, format, calculated, static, system | `download_profile_columns` | 4 |
| Fixed filters, user's current filters/selection, runtime parameters | `download_profile_filters`, `download_profiles.parameters/apply_user_filters` | 4 |
| Excel multi-sheet/styled/frozen/logo/title/RTL; CSV BOM; PDF options | `download_profiles.*_options`, A§19.8 | 4 |
| File name pattern; live preview 20 rows | `file_name_pattern`, A§19.8 | 4 |
| Auto-registered permission per profile | `download.{uuid}.use` | 4 |
| Field-level access at every level; preview effective columns per role | A§19.8 step 2, A§17.3 | 4 |
| Record-level security at every level | A§17.3, A§16.6 | 4 |
| Sensitive fields masked without permission | `field.{uuid}.view_sensitive`, A§7.2 | 4 |
| Download menu in table toolbar and record view | `download_profiles.available_in` | 4 |
| Personal profiles limited to accessible fields | `is_personal`, `create_personal_download_profiles` | 4 |
| Scheduled downloads emailed to users/roles | `download_schedules` (per-recipient execution) | 4 |
| Optimized multi-level queries, chunking, no N+1 | A§17.3–17.4 | 4 |
| Background jobs, progress, notification, signed expiring link | `download_jobs`, A§19.8 | 4 |
| Max rows per profile | `download_profiles.max_rows` | 4 |
| Audit: who, profile, filters, rows, time | A§19.8 step 6 | 4 |
| Failures in Operations Center with retry; duplicable; in packages | A§19.7, A§14.12 | 4/5 |

## §4.24 Edit justification

| Requirement | Covered in | Phase |
|---|---|---|
| Per form, field/group, status, role/user/department, action/transition (incl. bulk, import), delete/restore; off by default | `justification_rules.scope/subject`, A§19.3 | 3 |
| Levels not required / optional / mandatory; conditional switching | `level`, `condition_id`, `level_when_condition` | 3 |
| Free text with min/max; reason codes from admin list/collection; note for certain codes; attachments; translatable title/help; change summary | `justification_rules`, `justification_reason_codes`, `justification_attachments` | 3 |
| Prompt after validation passes | A§19.3 step 5 | 3 |
| Server-side enforcement | A§3.3 step 9, A§19.3 | 3 |
| Bulk asks once (count shown); imports per batch | A§19.3 step 6, `justifications.affected_count` | 3/4 |
| Immutable, even for Super Admin | `justifications` (no update path), A§19.15, ADR-0011 | 3 |
| Shown in audit, history timeline, table/download/report columns; dedicated view permission | A§19.3 step 7, `system.view_justifications` | 3/4 |

## §4.25 Assignment, queues & delegation

| Requirement | Covered in | Phase |
|---|---|---|
| Assign to user/role/department, automatic on transition or manual | `assignments`, `assignment_rules`, A§19.4 | 3 |
| Rules: specific, field user, creator's manager, round-robin, least-loaded | `assignment_rules.strategy` | 3 |
| Reassignment permission-controlled, audited, optional justification | `system.reassign_records`, `justification_rules.scope=reassign` | 3 |
| My Work across forms with due dates, SLA, priority | A§19.4, A§21.2 `/my-work` | 3 |
| Claim/release in role/department queues | `queue_claims` (unique active key) | 3 |
| Admin configures queue forms/columns | `queues`, `queue_forms` | 3 |
| Delegation for a period with reason, restricted to forms; admin out-of-office | `delegations` | 3 |
| On-behalf-of recorded in audit and notifications | `on_behalf_of_user_id` columns, A§19.4 | 3 |
| Parallel approvals: all / any N / weighted quorum; per-approver decisions; rejection behavior; queue, notifications, reminders, escalation | `approval_requests`, `approval_decisions`, A§19.4 | 3 |

## §4.26 Retention, archiving & personal data

| Requirement | Covered in | Phase |
|---|---|---|
| Policies per data class with period and end action; scheduled, audited | `retention_policies`, `retention_runs`, A§19.16 | 6 |
| Audit/error tables partitioned by time; archive path; restore procedure; size warnings | A§10.21, A§19.16, `operations_alert_rules.metric=storage_usage` | 6 |
| Storage quotas per form/application with warning and hard limit; usage report | `storage_quotas`, A§21.2 | 6 |
| Locate, export, delete/anonymize personal data preserving audit integrity | `personal_data_requests`, `subject_keys`, A§19.16, ADR-0014 | 6 |
| Personal-data field flag drives search | `fields.is_personal_data` | 2/6 |
| Legal hold with reason | `legal_holds`, record `legal_hold` flag | 6 |

## §4.27 Applications & tenancy

| Requirement | Covered in | Phase |
|---|---|---|
| Many applications with own menus, forms, collections, permissions, branding, settings | `applications` + scoped objects | 2/5 |
| Enable per department/role, clone, export, archive, retire | `app.{uuid}.access`, A§19.17 | 5 |
| Share across applications or isolate per form | `forms.data_sharing`, A§19.17 | 2 |
| Single vs multi-organization mode at setup; isolated data, branding, admins; global administrator; permissioned cross-org reporting | `organizations`, A§9.5, A§19.17, ADR-0004 | 1/5 |

## §4.28 Appearance & theming

| Requirement | Covered in | Phase |
|---|---|---|
| Theme editor: colors, light/dark, radius, shadows, density, fonts per script | `themes.tokens`, A§19.11 | 5 (branding basics 1) |
| Logos, favicon, login background, browser title, email artwork | `theme_assets`, translatable `browser_title` | 1/5 |
| Per-application and per-organization themes with global default | `themes.application_id`, `organizations.theme_id` | 5 |
| Login content, welcome, legal links, support contact translatable | `themes` translatables | 5 |
| Scoped, sanitized custom CSS that cannot hide security controls | A§19.11, A§7.3 | 5 |
| Import/export, presets, reset; contrast check before save | `themes.is_preset/contrast_report`, A§21.2 | 5 |

## §4.29 Pages, home screens & navigation

| Requirement | Covered in | Phase |
|---|---|---|
| Custom pages (content, link, dashboard, report, form host) with permissions | `pages`, `page.{uuid}.view` | 5 |
| Home screen per role/department/user from widgets | `home_screens`, `page_widgets`, A§18 | 5 |
| Navigation builder: unlimited depth, DnD, icons, live badges, separators, headers, visibility per role/department/condition | `menu_items` | 2/5 |
| Global search configuration: forms, fields, weights, display, per-role scope | `search_configs` | 5 |
| Announcements/banners: scope, schedule, dismissible, targeting, severity | `announcements`, `announcement_dismissals` | 5 |
| Maintenance mode system/application with admin access retained | A§19.17, `applications.maintenance_mode` | 5 |

## §4.30 Blueprints & template library

| Requirement | Covered in | Phase |
|---|---|---|
| Save form/collection/workflow/view/action/notification/dashboard/application as blueprint | `blueprints.kind` | 2/5 |
| Duplicate with structure / +permissions / everything | `blueprint_versions.include_mode`, `blueprint_instances` | 2 |
| Template library: categories, search, descriptions, previews | `blueprints.is_library/category/tags/preview_file_id` | 5 |
| Versioned; propagation with preview | `blueprint_versions`, `blueprint_instances.last_propagated_version_id`, A§21.2 | 5 |
| Import/export between environments | A§14.12 | 5 |

## §4.31 Scheduler & automation

| Requirement | Covered in | Phase |
|---|---|---|
| No-code builder: trigger, conditions, steps from action library | `automations`, `automation_triggers`, `automation_steps` | 4 |
| All triggers listed (record events, field/status change, condition, schedules, date reached with offset, inbound webhook, watched folder, manual) | `automation_triggers.type`, A§19.9 | 4 |
| All steps listed incl. wait for delay/condition | `automation_steps.type` | 4 |
| Enable/disable, run now, test, concurrency, retry, loop protection, max records with confirmation | A§19.9, `automations` columns | 4 |
| Run history with failures in Ops Center | `automation_runs` | 4 |
| Scheduled-task manager with next/last run | `scheduled_tasks`, A§8.2 | 4 |

## §4.32 External access & portals

| Requirement | Covered in | Phase |
|---|---|---|
| External forms with URL, theme, initial status | `external_forms` | 5 |
| CAPTCHA, per-IP limits, time window, caps, email/SMS verification, password | `external_forms`, `submission_throttles`, ADR-0013 | 5 |
| External user accounts with registration/approval, own role, own records only | `external_users`, A§19.12 | 5 |
| Tokenized single-record links with scoped actions | `access_tokens` | 5 |
| Signature requests with tracking and signed output in audit | `signature_requests` | 5 |
| Governed by permission, condition, justification, and audit rules | A§7.6, A§19.12 | 5 |

## §4.33 Integrations

| Requirement | Covered in | Phase |
|---|---|---|
| External data sources (REST/DB view) with auth, headers, mapping, cache; usable like collections | `external_data_sources`, A§18, A§19.21 | 5 |
| Sync jobs: schedule, mapping, keys, conflicts, dry run, run log | `sync_jobs`, `sync_runs` | 5 |
| Inbound API endpoints per form with scoped tokens, docs, enforcement | `inbound_endpoints`, A§21.2 | 5 |
| Incoming webhooks with signature verification and payload mapping | `inbound_endpoints.signature_*`, `webhook_deliveries` | 5 |
| Channels beyond email (in-app, SMS, messaging), per rule and user preference | `notification_channels`, `notification_deliveries`, `user_preferences.notification_channels` | 5 |
| Integrations visible in Operations Center | A§19.7, A§19.21 | 5 |

## §4.34 Reference data, calendars & numbering

| Requirement | Covered in | Phase |
|---|---|---|
| Business calendars (days, hours, holidays per country/org), assignable per department/form; working-time SLAs | `business_calendars`, `holidays`, `departments/forms.business_calendar_id`, A§19.19 | 2 |
| Numbering manager: patterns, padding, step, reset period, scope, current value, audited adjustment | `number_sequences`, A§19.19 | 2 |
| Currencies, exchange rates (manual/scheduled), history | `currencies`, `exchange_rates` | 2 |
| Units of measure and conversions | `units_of_measure` | 2 |
| Shared reference collections with one owner | `collections.is_shared_reference/owner_application_id` | 2 |

## §4.35 Data quality

| Requirement | Covered in | Phase |
|---|---|---|
| Duplicate rules (exact/normalized/fuzzy), warn/block at entry, sweep | `duplicate_rules`, A§19.10 | 4 |
| Merge with survivor, per-field choice, repoint relations, audit + justification | `merge_history`, A§19.10 | 4 |
| Bulk update/reassign/status with preview, dry run, max-count guard, justification | `bulk_operations` | 4 |
| Repair tools: orphans, failing validation, guided fix | A§19.10, `bulk_operations.type` | 4 |
| Recycle bin with retention, audited restore | `recycle_bin` | 4 |

## §4.36 Self-service, impersonation, access policies

| Requirement | Covered in | Phase |
|---|---|---|
| Profile preferences within admin limits | `user_preferences`, A§19.22 | 5 |
| Saved views, pinned records, shortcuts, personal API tokens (if permitted) | `saved_views`, `user_preferences`, `api_tokens` | 3/5 |
| Impersonation: banner, time limit, blocked sensitive actions, audited as admin | `impersonation_sessions`, A§7.7 | 5 |
| Access policies: IP allowlist, time windows, concurrent sessions, lifetime, device trust, stricter admin policy | `access_policies`, `trusted_devices`, A§19.22 | 5 (baseline 1) |
| Feature flags per application/role | `feature_flags` | 5 |

## §4.37 Help & adoption

| Requirement | Covered in | Phase |
|---|---|---|
| Help per form/field/page as tooltip/panel/page, translatable | `help_content` | 5 |
| Guided tours and hints per form/role with reset | `tours`, `tour_progress` | 5 |
| Knowledge page per application; changelog published to users | `pages.type=knowledge/changelog`, A§19.23 | 5 |
| Usage insight: usage, abandonment, empty fields | `usage_metrics`, A§19.23 | 5 |

## §5 Security requirements

| Requirement | Covered in | Phase |
|---|---|---|
| OWASP Top 10 | A§7.8 | all |
| Server-side authz and record checks (IDOR); mass-assignment protection for dynamic models | A§7.2, A§3.2 | 1/2 |
| Parameterized queries; strict input validation incl. metadata | A§7.3, A§14 | all |
| XSS, sanitized rich text, strict CSP, CSRF, security headers | A§7.3 | 1 |
| Upload validation, private storage, signed URLs, ClamAV | A§7.3, `files` | 1 |
| Password policy, 2FA mandatory for admins, lockout, session timeout & management, SSO/LDAP, rate limiting | A§7.1 | 1 |
| SSRF: single egress gateway for all outbound calls | A§7.4 | 1 |
| Untrusted parsing safety | A§7.3 | 4 |
| Expression safety | EL§1, EL§8 | 2 |
| External surfaces separated, no implicit permissions, rate limited, scoped tokens | A§7.6, A§19.12 | 5 |
| Admin-authored content sanitized | A§19.11 | 4/5 |
| Impersonation cannot escalate, visible, time-limited, audited | A§7.7 | 5 |
| Encryption of sensitive fields; secrets in env; per-tenant/per-field keys; rotation procedure | A§7.5, `encryption_keys` | 1/2 |
| Scheduled backups with restore procedure; dependency scanning | A§23 | 1/6 |
| No sensitive data in logs or user-facing errors | A§7.5 Masker, A§19.15 | 1 |

## §6 Non-functional requirements

| Requirement | Covered in | Phase |
|---|---|---|
| Server-side pagination/filtering/sorting | A§3.4, A§17 | 2/3 |
| Metadata caching with invalidation | A§3.1, A§16.5 | 2 |
| Background jobs for heavy work | A§8.1 | 1+ |
| Transactions, constraints, idempotent retries, optimistic concurrency on every write | A§3.3, A§19.1, A§19.6 | 2 |
| Performance budgets written into architecture and load-tested in Phase 6 | A§20 | 0 ✔ / 6 |
| Responsive, WCAG 2.1 AA, consistent RTL/LTR | A§22.4, A§19.24, A§19.11 | all |
| Modular, strictly typed, documented; tests for every module on both engines | A§4, A§23, A§6.3 | all |

## §7 Data model

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

## §8.3 Phase 0 deliverables

| Deliverable | Location |
|---|---|
| System layers and runtime engine design | A§2, A§3 |
| Module boundaries | A§4 |
| Folder structure (backend, frontend) | A§5 |
| Database driver layer (MySQL, SQL Server) | A§6 |
| Security architecture | A§7 |
| Queue, job, and event design | A§8 |
| Complete ERD (every column, type, index, FK) | A§9–A§10 |
| Physical table generation strategy (migrations, versioning, archiving removed fields) | A§11, A§13 |
| Metadata JSON schema (forms, groups, fields, conditions, rules, justification rules, download profiles) | A§14 |
| Expression language specification + conformance corpus | `docs/expression-language.md`, `docs/conformance/expression-corpus.json` |
| Permission resolution algorithm | A§16 |
| Schema change strategy | A§12 |
| Performance budgets and capacity assumptions | A§20 |
| Extension model for admin-built surfaces | A§18 |
| Relation traversal design | A§17 |
| API outline (groups, methods, payloads, permissions) | A§21 |
| Frontend architecture (state, builder model, renderer, component library) | A§22 |
| Technical decisions with reasoning | A§24, `docs/decisions/` |
| `docs/architecture.md` and `docs/progress.md` | ✔ |
