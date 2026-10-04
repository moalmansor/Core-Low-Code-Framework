# System Specification: Core Low-Code Framework (Laravel + Vue.js)

## 1. Role & Objective
You are a senior software architect and full-stack engineer specialized in Laravel, Vue.js, and secure enterprise systems. Build a **complete, production-ready core low-code framework**. Administrators use it to build any business system entirely from the UI, **without writing a single line of code**.

Everything is created by administrators through the UI and stored as metadata:
- forms and field groups;
- collections, fields, and relations;
- workflows and statuses;
- permissions, views, filters, and actions;
- download profiles;
- automations, integrations, and external access;
- pages, menus, home screens, branding, and reference data;
- conditions and notifications.

Nothing business-specific is hard-coded. A **runtime engine** reads this metadata and executes it dynamically, so every form works correctly the moment it is published.

Custom code is a rare exception, reserved for developers (see 4.19).

## 2. First-Run Behavior
- On first launch, the system is an **empty application shell** containing only the login page, sidebar, top bar, and the Admin Console.
- **Do not create any business forms, sample data, demo modules, or example content.**
- **Seeded items (only these):**
  - core system roles (Super Admin, Admin, Developer, User);
  - the full system permission catalog;
  - default system settings.
- **Setup wizard (web-based), shown on first launch.** Before the first step, the wizard asks for a one-time **setup token** that only someone with console access to the server can obtain (`php artisan setup:token`, also printed in the application container's log). Only the token's hash is stored, issuing a new token invalidates the previous one, and the token is discarded when setup completes. This stops whoever reaches a fresh installation first from claiming the Super Admin account. It collects:
  - system name, logo, and favicon;
  - default language and enabled languages;
  - timezone and date/number formats;
  - calendar system (Gregorian, Hijri, or both);
  - SMTP settings, with a test-email button;
  - the Super Admin account, with password policy enforcement and mandatory 2FA setup.
- **After setup:**
  - the wizard is locked permanently;
  - the admin lands on the Admin Console, which shows every administrative capability in one organized place.

## 3. Tech Stack (Fixed Decisions)

**Backend**
- Laravel 12+ and PHP 8.3+, as a RESTful JSON API.
- Laravel Sanctum (SPA cookie auth + API tokens) and Laravel Fortify (2FA, password reset).
- Supporting packages:

| Purpose | Package |
|---|---|
| Excel import/export | maatwebsite/excel |
| PDF with full Arabic support | mpdf/mpdf |
| DOCX templates | phpoffice/phpword |
| LDAP | directorytree/ldaprecord-laravel |
| SSO (OAuth2/OIDC) | laravel/socialite |
| Queues | Laravel Horizon + Redis |
| Scheduling | Laravel Scheduler |
| Storage | Laravel Filesystem (local and S3-compatible) |
| Virus scanning | ClamAV integration, enabled/disabled from settings |

**Frontend**
- Vue 3 (Composition API) + TypeScript, built with Vite.
- State and routing: Pinia and Vue Router.
- Styling and components: Tailwind CSS and PrimeVue 4 (with RTL support).
- Supporting libraries:

| Purpose | Library |
|---|---|
| Drag and drop | vuedraggable (Sortable.js) |
| Rich text | TipTap |
| Code editor | Monaco Editor |
| Workflow diagrams | Vue Flow |
| Charts | Apache ECharts |
| Maps | Leaflet + OpenStreetMap |
| Signatures | signature_pad |
| QR codes | qrcode |
| Barcodes | JsBarcode |

- Hijri calendar support via a Hijri date adapter.

**Database**
- MySQL 8+ and SQL Server 2019+, both fully supported.
- All access goes through Eloquent and the Query Builder.
- Engine-specific differences (e.g. JSON handling, schema introspection) are isolated in a dedicated driver layer, with implementations and tests for both engines.

**Languages**
- Arabic (RTL) and English (LTR) ship enabled, with a translation manager in the UI for adding more.
- Every translatable label, option, message, and template is stored in a **translations table keyed by object and locale**, never as fixed Arabic and English columns, so a third language needs no schema change. Where this specification writes "(AR/EN)" it means "translatable in every enabled locale", and the two shipped locales are simply the first two rows.
- Each locale declares its text direction, calendar preference, number formatting, and fallback locale. Missing translations fall back rather than rendering empty, and the translation manager lists what is untranslated.

**Testing**
- Pest (backend), Vitest (frontend), Playwright (end-to-end).

**Deployment & Environments**
- Docker Compose (app, queue worker, scheduler, Redis, MySQL, SQL Server, ClamAV, Mailpit for development).
- The Compose stack runs unchanged on Linux, macOS, and Windows hosts:
  - every text file is stored and checked out with LF line endings on every operating system (enforced by `.gitattributes`), because the containers run on Linux;
  - application images are built from the checkout and never pulled from a registry;
  - no secret (`.env` files, keys, passwords) is ever copied into an image; configuration reaches containers at run time;
  - every long-running service that others depend on has a health check, and the documented start command returns only when the stack is healthy;
  - a missing required setting stops start-up with a message that says how to fix it.
- A `.devcontainer` configuration so the repository opens and runs in a cloud development environment (GitHub Codespaces or an equivalent sandbox) with no local installation. It starts with no manual configuration: credentials are generated, and the application is installed, migrated, and served on a forwarded port.
- GitHub Actions workflows for CI: service containers for MySQL, SQL Server, and Redis; the full test suite (Pest, Vitest, Playwright); static analysis (Larastan/PHPStan, ESLint, Prettier); dependency audits (composer audit, npm audit). CI also:
  - fails when any file is committed with CRLF or would be checked out with CRLF on Windows;
  - starts the Compose stack on MySQL and on SQL Server from a Windows-style checkout, following the installation guide command by command, and runs the Playwright suite against it;
  - starts the dev container the way Codespaces does and verifies it.
- Playwright artifacts (screenshots, videos, HTML report) uploaded on every CI run.
- Full installation documentation, with commands for each supported host operating system.

## 4. Modules & Requirements

### 4.1 Admin Console
A single, well-organized control center with navigation to:
- Dashboard & System Health
- Operations Center
- Form Builder
- Collections & Reference Data
- Applications, Pages & Menus
- Appearance & Branding
- Blueprints & Template Library
- Automations & Scheduler
- Integrations & External Data Sources
- External Access & Portals
- Roles & Permissions
- Workflows & Statuses
- Views, Filters & Actions
- Assignment, Queues & Delegation
- Custom Downloads
- Email Templates & Notification Rules
- Document Templates
- Reports & Dashboards
- Users, Departments & Access Policies
- Data Quality & Bulk Tools
- Help Content & Announcements
- Audit Log
- Error Monitoring
- Developer Extensions
- Configuration Packages
- Retention & Storage
- Translations
- System Settings

Each area is visible only to holders of its permission.

### 4.2 Operations Center (Failures & Recovery)

**Email Queue Monitor**
- Lists every email with status: Pending, Sending, Sent, Failed, Stuck. An email counts as Stuck when it has been pending longer than a configurable threshold.
- For each email it shows:
  - recipients (To/CC/BCC) and subject;
  - linked form, record, and rule;
  - attempts count and the exact failure reason.
- Actions:
  - resend (single or bulk);
  - edit recipients and resend;
  - cancel;
  - view rendered HTML.

**Failed Submissions**
- Every submission is written to a durable **submission journal** before processing, so no data is ever lost. If the save fails, the entry remains in the journal with:
  - user, form, and form version;
  - the full payload;
  - the error, stack trace, and correlation ID.
- Actions:
  - retry/resync (single or bulk);
  - edit the payload and retry;
  - discard with a mandatory reason.
- Retries are idempotent, so a record is never duplicated.

**Failed Jobs**
- Covers imports, exports, custom downloads, webhooks, scheduled tasks, hooks, and document generation.
- Actions: inspect, retry, discard.

**Health Widgets**
- Queue sizes, stuck items, last scheduler run, storage usage.
- DB, Redis, SMTP, and ClamAV connectivity.

**Alerts**
- Email alerts to chosen roles when failures exceed configurable thresholds.

All Operations Center actions are audited.

### 4.3 Form Builder: Canvas & Drag-and-Drop
- Three-panel layout:
  - **left:** palette of all inputs and layout elements, searchable and categorized;
  - **center:** live canvas;
  - **right:** properties panel of the selected element.
- **Adding elements:**
  - drag any input or group from the palette onto the canvas, or into any group at any nesting depth;
  - or click an element in the palette to insert it at the current selection.
- **Arranging elements:**
  - reorder by dragging;
  - move fields between groups;
  - duplicate, copy/paste (including between forms), delete;
  - multi-select for bulk property changes.
- **Editing tools:**
  - undo/redo with full history;
  - keyboard shortcuts;
  - autosave of drafts.
- **Reusable field library:** save any configured field or group as a reusable template.
- **Live preview:**
  - desktop, tablet, and mobile sizes;
  - Arabic and English;
  - simulated as any specific role or user, to see exactly what they will see.

### 4.4 Form Builder: All Input Types
The palette must include **every HTML input type and form element**, each fully configurable.

**Native HTML input types (all of them)**
- Text types: text, password, email, tel, url, search, number, range.
- Date and time types: date, time, datetime-local, month, week.
- Choice types: checkbox, radio.
- Other: color, file, hidden, image, button, submit, reset.

**Native HTML form elements**
- Text and choice elements: textarea; select (single); select multiple; optgroup-grouped selects; datalist (autocomplete suggestions).
- Display elements: output, progress, meter.
- Structure: fieldset/legend (as a group).

**Extended inputs (built on top of HTML)**

*Text and rich content*
- Rich text editor (TipTap).
- Markdown editor.
- Code editor field (Monaco).

*Numbers and money*
- Currency, percentage, decimal with precision.
- Auto-number with a configurable pattern (prefix, date parts, sequence, reset period).
- Formula/calculated field.

*Dates and calendars*
- Calendar/date picker (Gregorian and Hijri, switchable or dual display).
- Date range, time range, datetime range.
- Duration.

*Choices and pickers*
- Toggle switch.
- Checkbox group, radio group, button group.
- Dropdown with search, multi-select with chips, tags input.
- Cascading dropdowns.
- Lookup to a collection or form record, with search and a preview card.
- User picker, role picker, department picker.
- Country picker, city picker (from collections).
- Rating (stars), slider with min/max/step labels.
- Color palette picker.

*Files and media*
- Multiple file upload with drag-drop zone.
- Image upload with crop/resize.
- Camera capture.

*Special inputs*
- Signature pad.
- Map/location picker (Leaflet).
- Phone with country code.
- National ID with a configurable pattern and checksum.
- IBAN.
- Barcode/QR display and scanning input.
- JSON editor.
- Key-value pairs.
- Consent checkbox with linked terms.

*Display elements*
- Static text/HTML block, heading, divider, spacer, image.
- Alert/info box.
- Link.

**Layout and group elements**
- Section, fieldset, card, tabs (and individual tab).
- Wizard steps (and individual step).
- Row with columns (1–12 grid, per breakpoint).
- Collapsible panel/accordion.
- Repeater (repeatable group / inline sub-table).
- Inline sub-form of a linked form (create child records inside the parent). Once the parent is saved, the group lists the linked records and adds new ones, which are validated and stored by the linked form under its own permissions; deleting the parent applies the relation's on-delete rule to them.

### 4.5 Form Builder: Group Properties
Clicking any group (section, fieldset, card, tab, step, row, panel, repeater) shows its full properties.

**General**
- Title and description (AR/EN), icon, CSS class.
- Layout: number of columns per breakpoint, spacing, border and background style.
- Collapsible, default state (open/closed), order.

**Visibility & Access Conditions**
- Hide/show, read-only, or disable the entire group based on any combination of:
  - specific roles;
  - specific users;
  - departments;
  - record status;
  - form mode (create / edit / view / print);
  - field values;
  - user attributes;
  - linked-record data;
  - date/time conditions.
- Conditions are built with the visual rule builder (4.7).

**Validation**
- Require at least N fields in the group to be filled.
- Group-level custom validation rules with messages.

**Repeater-specific**
- Minimum/maximum rows, and default rows.
- Add/remove/reorder permissions per role.
- Row totals and aggregates.
- Column layout as a table or as cards.
- Storage as a child table with a real foreign key.

**Wizard-step-specific**
- Step validation before moving forward.
- Allow jumping between steps.
- Step visibility conditions.

### 4.6 Form Builder: Field Properties
Clicking any field shows a complete properties panel. It is organized in tabs and exposes **every option a developer would normally code**.

**General**
- Identity: field key (auto-generated, editable, validated as unique), label (AR/EN), placeholder (AR/EN).
- Help: help text, tooltip, description.
- Decoration: prefix/suffix text, icon, size (small/medium/large).
- Layout: width in grid columns per breakpoint, label position (top/side/hidden).
- Browser behavior: autofocus, tab index, autocomplete attribute, spellcheck.
- Styling: CSS class.

**Data & Database Binding**
- Binding target: an existing table/column from schema introspection, or auto-create a new column.
- Column definition:
  - column name and data type (auto-suggested from the input type);
  - length, precision, and scale;
  - nullable and default value at the DB level.
- Indexing: none, index, or unique (with scope).
- Stored encrypted (on/off).
- Relation binding:
  - relation type (1:1, 1:N, N:N) and target collection/form;
  - display and value columns;
  - on-delete behavior.

**Options (for select, radio, checkbox group, tags, lookup, etc.)**
- Source:
  - a static list, editable inline with value/label AR/EN, color, icon, and order;
  - a collection;
  - another form's records;
  - a visual query builder (source table, filters, sort, limit) with no SQL typing.
- Cascading: the option list depends on another field's value.
- Allow custom values not in the list.
- Selection limits: minimum/maximum selections.
- Default selected options.
- Searchable, with lazy loading for large sources.
- Grouping of options.

**Validation**
- Presence: required.
- Text rules: minimum/maximum length.
- Number rules: minimum/maximum value, step.
- Pattern: regex.
- Format presets: email, URL, phone, national ID, IBAN, numeric only, Arabic only, English only, alphanumeric.
- Date rules:
  - minimum/maximum date, absolute or relative (e.g. today + 7 days);
  - disabled weekdays and disabled specific dates;
  - no past dates / no future dates.
- File rules:
  - allowed types and MIME types, maximum size, maximum count;
  - image dimensions.
- Uniqueness: unique across all records or within a scope (e.g. per department).
- Cross-field checks: compare with another field (equal, greater than, before, after).
- Server-side async validation (e.g. check existence in a collection).
- A custom error message (AR/EN) for every rule.

**Behavior**
- Default value:
  - static;
  - dynamic: current user, current user's department, current date/time;
  - from a URL parameter;
  - from another field;
  - from a formula;
  - from a referenced record.
- Calculation: formula editor with functions (math, date, text, conditional, lookup, aggregate over repeater rows).
- Transformations: trim, uppercase/lowercase.
- Masks and formatting:
  - input masks;
  - thousand separator and decimal places;
  - currency code and symbol position;
  - date display format;
  - Arabic-Indic or Western digits.
- Calendar settings: calendar system (Gregorian, Hijri, dual), first day of week, time step, 12h/24h, timezone handling.
- File storage: storage disk, folder pattern, file naming.
- Reference behavior: auto-fill other fields when a referenced record is selected.
- Edit tracking: track changes in the audit log (on/off); mark as sensitive, which masks the value in logs and errors; mark as personal data, which includes the field in personal-data search, export, and anonymization (4.26).
- Justification: whether changing this field requires a reason, and at which level (not required, optional, mandatory), per role, user, department, and status (4.24).

**Access (per field)**
- For every role, specific user, department, status, and form mode (create/edit/view/print), set the field to **Hidden / Read-only / Editable / Required**.
- A matrix view shows all combinations at once.

**Conditions**
- Visual rule builder (4.7) to show/hide, enable/disable, require, make read-only, set value, clear value, reload options, or show a message.

**Events**
- On change, focus, or blur, trigger configured actions:
  - set another field;
  - reload options;
  - run a custom action;
  - call a webhook;
  - show a notification.
- Attach a developer hook (visible to the Manage Code permission only).

**Table & Export Settings**
- Visibility and behavior in lists:
  - visible in records table by default;
  - sortable, filterable, and searchable;
  - column label override;
  - display format in tables.
- Import/export and print:
  - exportable and importable;
  - Excel column name for import mapping;
  - include in print and PDF.

### 4.7 Conditions Engine (No Code)
- A visual rule builder with nested IF / AND / OR groups.
- **Operands available:**
  - field values and repeater aggregates;
  - current user, user roles, user department, user attributes;
  - record status and form mode;
  - dates/times (today, now, relative);
  - linked-record fields.
- **Operators:**
  - equals, not equals;
  - contains, starts with, ends with;
  - greater/less than, between;
  - is empty, is not empty;
  - in list, not in list;
  - matches regex;
  - changed from/to.
- **Effects:**
  - show/hide, enable/disable, read-only, require;
  - set value, clear value, reload options;
  - show message, block submit;
  - trigger action.
- Scope: rules apply to fields, groups, options, actions, transitions, and notifications.
- Enforcement: every rule is evaluated both on the client (for UX) and on the server (for enforcement).

**Expression language (single definition, two runtimes)**
- Conditions, formulas, default values, and placeholder expressions all use **one** language with a written grammar, defined in `docs/expression-language.md` during Phase 0. It is not free-form PHP or JavaScript.
- The language is pure and side-effect free: no I/O, no loops, no assignment. It provides typed values (text, number, date, boolean, list, record reference, null), explicit null handling, and a fixed function library covering math, text, date and calendar (Gregorian and Hijri), conditional logic, lookups across relation paths, and aggregates over repeater rows.
- It is parsed into an **abstract syntax tree stored as JSON**, never as a string that each side re-parses differently. Both runtimes consume the same tree.
- **Parity is tested, not assumed:** a shared conformance corpus of expressions and expected results lives in the repository and runs in CI against both the PHP and the TypeScript evaluator. A disagreement fails the build.
- Evaluation is bounded: maximum AST depth, maximum relation-path depth, and an execution timeout. The server is always authoritative; the client result is a convenience.
- Division by zero, overflow, type mismatch, and missing references produce defined results, not exceptions, and surface as validation messages.

### 4.8 Collections (Tables & Option Sources)
- Admins create collections, from simple key/value lists to full tables, using the same field engine as forms.
- Records are managed in the UI, with Excel import/export:
  - export (Excel or CSV) contains the records the user can list and only the fields they may see, with option labels and referenced-record titles instead of codes, and cells that spreadsheet software cannot execute as formulas; the row limit is a system setting;
  - import maps the header row to fields by key, column name or label in any language, checks every row through the same validation and access rules as the form before anything is written, and reports errors per row and column; rows carrying a record ID update that record only if it is still at the exported version; importing the same file again never creates duplicates; the row limit is a system setting.
- Collections serve as sources for selects, lookups, cascading dropdowns, and filters.
- Relations between collections and forms: 1:1, 1:N, N:N.
- A schema explorer shows all tables, columns, indexes, and relations, including an ERD diagram view.

### 4.9 Data Storage Strategy
- Each form and collection gets its own **physical table**, generated via versioned, reversible migrations executed by the framework.
- Physical schema:
  - real foreign keys and proper column types;
  - indexes on filterable fields.
- Repeaters and inline sub-forms become child tables.
- A metadata layer describes all tables, fields, and relations for the runtime engine.
- Referential integrity is enforced: no orphaned records, and configurable on-delete rules (restrict, cascade, set null). Because deleted records are kept for restore, the rules are applied by the framework when a record is deleted: the whole cascade is checked first, a restrict anywhere in it refuses the delete with the number of referencing records, and every cascaded change is audited.
- **Optimistic concurrency:** every record table carries a `row_version` column, incremented on each write. Saves pass the version the user loaded; a mismatch is rejected with a conflict screen showing which fields changed and who changed them, and the user chooses to reload, overwrite field by field, or cancel. Silent last-write-wins is never acceptable.

**Schema change execution (DDL safety)**
- MySQL cannot roll back DDL inside a transaction, and SQL Server behaves differently again, so schema changes are never assumed atomic. Each publish runs as an ordered **migration plan** of individually reversible steps, persisted before execution, with each step marked pending, applied, or failed.
- On failure the system stops, attempts the recorded reverse steps, and if it cannot fully reverse, places the form in a **Schema Inconsistent** state: the form is locked for users and for further publishing, the admin sees exactly which steps applied, and a guided repair screen offers retry, manual reconciliation, or restore from the pre-publish snapshot.
- A **schema reconciliation check**, runnable on demand and on a schedule, compares the metadata against the physical schema of each engine and reports every difference.
- **Locking and concurrency:** publishing takes an exclusive lock on the form and on every form related to it; a second admin publishing a related form is queued and told why. Long-running changes on large tables are executed online where the engine supports it, with the estimated duration and lock impact shown in the impact analysis before the admin confirms.
- **Pre-publish safety:** a logical backup of the affected tables is taken before any destructive step, with its location and retention shown to the admin.

### 4.10 Versioning & Safe Changes
- **Workflow:** draft → preview → impact analysis → publish.
- **Impact analysis lists everything affected:**
  - existing records;
  - views, filters, and actions;
  - download profiles;
  - notifications and permissions;
  - reports and linked forms.
- **Schema changes:**
  - adding and renaming fields migrate the table safely;
  - removing a field archives the column (no data loss);
  - type changes validate existing data first and report conflicts.
- **Status changes:** renaming, adding, removing, or merging statuses opens a guided mapping screen for existing records.
- **History:** full version history, visual diff between versions, one-click rollback.
- **Rollback classes.** The UI states plainly which class a rollback falls into before it runs:
  - *metadata-only* (labels, layout, conditions, permissions): always reversible;
  - *additive schema* (a field was added): reversible by archiving the new column;
  - *destructive or lossy* (a type narrowed, a field removed and its data archived, a status merged): not fully reversible. The system offers restore-from-snapshot instead, names the affected records, and requires explicit confirmation.
- **Environment drift.** Because physical tables are generated from metadata, two environments can diverge. A **compare environments** tool diffs metadata and physical schema between any two environments and reports differences before a configuration package is imported. Importing a package refuses to proceed when the target's drift would make the result ambiguous, and says exactly which objects conflict.
- Each record stores the form version it was submitted with.

### 4.11 Roles & Permissions (Unified Interface)
All access control lives in **one** interface.
- Create roles, assign users (multiple roles per user), and grant permissions to roles, users, or departments.
- **Permission matrix per form:**
  - **Form level:** View, Create, Edit, Delete, Restore, Export, Import, Print, View Log, plus every custom action and every download profile (auto-registered).
  - **Group and field level:** Hidden / Read-only / Editable / Required, per role, user, status, and mode.
  - **Status level:** who performs each transition.
  - **Action level:** who runs each action.
  - **Record level:** own records, own department, department tree, all records, or custom conditions.
  - **Menu level:** who sees each sidebar item.
- **System permissions:**
  - View Errors, Manage Operations, Manage Code, Manage Settings;
  - Manage Forms, Manage Permissions, Manage Users, View Audit Log;
  - Manage Download Profiles, Create Personal Download Profiles;
  - Manage Justification Rules, View Justifications;
  - Assign Records, Reassign Records, Manage Delegation, Delegate Own Work;
  - Manage Retention, Manage Personal Data Requests, Apply Legal Hold;
  - Manage Applications, Manage Pages & Menus, Manage Branding, Manage Blueprints;
  - Manage Automations, Run Automations Manually, Manage Integrations, Manage External Access;
  - Manage Reference Data, Manage Numbering, Manage Calendars, Manage Currencies;
  - Merge Records, Bulk Update, Access Recycle Bin, Repair Data;
  - Impersonate Users, Manage Access Policies, Manage Feature Flags, Manage Help Content, Publish Announcements, Enable Maintenance Mode.
- **Precedence:** a specific user overrides role, which overrides department.
  - Within the same tier, deny overrides allow.
  - A more specific tier overrides a less specific tier, including that tier's deny.
  - A **hard deny** cannot be overridden by any tier. It is set explicitly by an administrator, is marked distinctly in the matrix, and the system refuses a hard deny that would leave no active Super Admin able to manage permissions.
- **Tools:**
  - "View as user" to see effective permissions;
  - copy permissions between roles;
  - export/import permission sets.
- **Enforcement:** server-side via Laravel Policies/Gates on every request, with no reliance on UI hiding.
- **Escalation safeguards for people management:** holding Manage Users never lets an administrator gain or hand out more power than they hold:
  - a user account can be edited, suspended, unlocked, reset, signed out, or deleted only by someone who holds every permission that account holds;
  - a role or department can be given to a user only if every permission it allows is held by the administrator giving it;
  - nobody can change their own roles or department;
  - giving an administrative role, or one that allows a sensitive permission, requires step-up confirmation (below).
- **Step-up confirmation:** granting a sensitive (dangerous) permission, setting a hard deny, importing a permission set that contains either, and giving an administrative role require a fresh authenticator code or a one-time recovery code, verified server-side.

**Resolution model (inheritance and sparse storage)**
- Access is **computed, not enumerated**. Only deviations are stored, so the number of rows stays proportional to the exceptions an admin actually creates, not to forms × fields × roles × statuses × modes.
- Resolution order, most general to most specific: system default → form default → field group → field → status override → mode override → department → role → specific user. The most specific matching rule wins; within the same tier a deny beats an allow; a hard deny beats every tier (see Precedence).
- Every field inherits from its group unless overridden; every group inherits from the form. The UI marks inherited values distinctly from explicit ones, and offers "reset to inherited".
- **Effective permissions** for a given user are resolved once per request and cached, with invalidation on any change to permissions, roles, departments, form versions, or statuses.
- The matrix UI is filtered and paged by role, status, or group rather than rendering every combination at once, shows only fields that deviate by default, and supports bulk edit across a selection.
- **Explain access:** for any user, form, field, and status the admin can see which rule produced the outcome and where it was defined.

### 4.12 Workflow Engine
- **Statuses:** name (AR/EN), color, icon, initial/final flags.
- **Transitions:**
  - from → to, and allowed roles/users;
  - conditions and required fields;
  - optional mandatory comment and attachments;
  - linked actions and notifications.
- **Visual designer:** Vue Flow, for drag-and-drop editing of statuses and transitions.
- **SLA timers:** per status, with escalation rules (notify, reassign, auto-transition).
- **History:** full status history per record, with comments.

### 4.13 Publishing & Navigation
- On publish, the admin chooses:
  - sidebar location (parent menu, order, icon) and display name (AR/EN);
  - the application/module it belongs to;
  - allowed roles/users.
- A menu editor provides drag-and-drop ordering and nesting.
- Unpublish, archive, and republish without data loss.

### 4.14 Records Table View, View Mode & Edit Mode

**Table view**
- One unified component, configured per form, for each role:
  - columns (including linked-form fields), order, width, and pinning;
  - default sort, pagination size, and formatting;
  - totals row.
- **Filters:**
  - defined by the admin from the form's fields and linked forms' fields;
  - each filter's type matches its field type;
  - quick filters and advanced filter builder.
- **User features:**
  - global search, sorting, column show/hide (if permitted);
  - saved personal views and shared views.
- **Row options:** View, Edit, Log, each permission-controlled.
- **Bulk selection** with bulk actions.
- **Download menu** with the download profiles available to the user (4.23).
- **Admin control:** record counts per form, full edit, soft delete + restore.

**View Mode (record details page)**
- The admin can add:
  - related-data panels (tables of linked-form records with chosen columns, filters, and actions);
  - read-only derived fields from related forms;
  - summary widgets;
  - status history timeline;
  - comments thread;
  - attachments panel;
  - tabs and sections with per-role visibility.

**Edit Mode**
- When a user selects a reference to another record:
  - show a configurable preview card;
  - optionally auto-fill selected fields;
  - open the referenced record in a side drawer.

**Print view**
- A configurable print layout, with export to PDF (mPDF, full Arabic support).

### 4.15 Actions
**Built-in actions**
- Export to Excel/CSV/PDF, respecting visible columns, filters, and permissions.
- Custom downloads via download profiles (see 4.23).
- Import from Excel:
  - column mapping (saved mappings) and validation preview;
  - error report download;
  - insert, update, or upsert by a key field;
  - dry-run mode.
- Bulk delete, bulk status change, bulk field update.
- Print and duplicate record.

**Custom actions (no code)**
- Steps an action can perform:
  - update fields;
  - change status;
  - send an email;
  - call a webhook (configurable method, headers, and body with placeholders);
  - generate a document;
  - create a linked record prefilled with data.
- Steps can be chained, with conditions between them.

**Outbound webhook safety**
- Target URLs are checked against an **egress allowlist** of hosts configured in system settings. Anything not listed is refused.
- Private, loopback, link-local, and cloud metadata address ranges are blocked, in both IPv4 and IPv6. The resolved address is validated at request time, not only the hostname, and the connection is pinned to the validated address so a second DNS answer cannot redirect it.
- Redirects are not followed across hosts. Requests carry a timeout, a response-size cap, and a retry limit with backoff.
- Requests are signed with a per-webhook secret. Secrets and headers are stored encrypted and masked in logs.
- Every call and its outcome are logged and visible in the Operations Center.

**Import and export safety**
- Imports enforce a maximum file size, a maximum row count, and a time limit, and run as background jobs.
- Uploaded spreadsheets are parsed with external entities and remote references disabled; formulas in imported cells are read as text and never evaluated.
- Exported CSV and Excel cells beginning with `=`, `+`, `-`, `@`, tab, or carriage return are escaped so spreadsheet software cannot execute them.
- Imports run inside a transaction per batch, are idempotent on retry, and produce a downloadable error report that names the row and the reason.

**Execution**
- Placement: row, bulk, toolbar, or view page.
- Each action has its own permission and optional confirmation dialog.
- Heavy actions run as background jobs with a progress indicator and a completion notification.
- The runtime engine always reads configured actions and rules, so no redeployment is ever needed.

### 4.16 Email Notifications
- **Triggers:**
  - record created or record updated;
  - status changed (specific from/to);
  - specific field changed or condition met;
  - scheduled reminders and SLA escalations.
- **Recipients:**
  - users, roles, departments;
  - users stored in a record field;
  - the record creator;
  - users from linked records;
  - static addresses.
- Each rule has full control over **To / CC / BCC**.
- **Template editor:**
  - rich visual editor with styling (colors, logo, layout);
  - placeholders from form fields, linked-form fields, system values, and record links;
  - conditional blocks and repeater tables;
  - AR/EN versions and attachments (including generated documents);
  - preview with a real record and test send.
- Every email is tracked in the Operations Center.

### 4.17 Document Templates
- Upload DOCX templates with placeholders, or design HTML templates.
- Generate DOCX or PDF from any record, including repeater tables and linked data.
- Usable in actions and email attachments.

### 4.18 Reports & Dashboards
- **Report builder:**
  - data source: any form or collection, including joins through relations;
  - columns, grouping, and aggregates (count, sum, average, minimum, maximum);
  - filters and sorting.
- **Output:** tables, pivot tables, and charts (bar, line, pie, area, KPI cards) via ECharts.
- **Dashboards:**
  - drag-and-drop widget layout;
  - per-role visibility;
  - export to Excel/PDF.

### 4.19 Developer Extensibility (Rare Cases Only)
- Available only with the **Manage Code** permission.
- **Server-side hooks (PHP classes):**
  - onLoad, beforeValidate, afterValidate;
  - beforeSave, afterSave, beforeDelete, afterDelete;
  - onStatusChange, onAction;
  - custom API endpoints.
- **Client-side extensions:** custom validators, custom field components registered in the palette, and form-level scripts (Vue/TypeScript).
- **Code management:**
  - stored as versioned extension modules on disk (Git-friendly), never executed via `eval()`;
  - a Monaco editor in the UI.
- **Lifecycle:**
  - write, then test in a sandbox environment;
  - approval by a user with the approval permission;
  - deploy;
  - full version history and rollback.
- **Safety:**
  - hooks run inside DB transactions with timeouts and error isolation;
  - failures go to Error Monitoring and the Operations Center.

### 4.20 Audit Log
- **What is logged:**
  - every data change: create, update, delete, restore;
  - status changes, imports, exports, downloads, prints;
  - logins and failed logins;
  - retries and resends;
  - permission and configuration changes.
- **Detail per record and per field:** who, when, old value, new value, IP, user agent, correlation ID, the acting user and, where delegation applies, the user acted for.
- **Justifications** are stored with the entry that required them (4.24) and are immutable.
- **Integrity:** audit entries are append-only. No interface, including Super Admin, can edit or delete one; corrections are new entries. Entries are hash-chained so tampering at the database level is detectable, and a verification job reports breaks in the chain.
- **Viewer:** filterable and exportable, per record and system-wide.

### 4.21 Error Monitoring
- **Captured for every exception:**
  - stack trace, file/line;
  - request details, masked payload;
  - user and role;
  - form/record, hook/job;
  - environment, timestamp.
- A correlation ID follows the request across jobs, emails, and hooks.
- Users see a friendly message with a reference ID.
- **Durability:** errors are written to a secondary sink (file or external log service) as well as the database, so failures that take the database down are still recorded. The in-app console reads the database; the secondary sink is the fallback the admin is pointed to when it is unavailable.
- **Error management:**
  - grouping of duplicates, with frequency and first/last seen;
  - status (New / In Progress / Resolved / Ignored), assignee, notes;
  - email alerts;
  - filters by date, severity, module, form, user, and status.

### 4.22 Framework Capabilities
- Multiple applications/modules on the same core.
- Configuration packages that export/import forms, collections, workflows, permissions, templates, reports, and download profiles between environments. Imports run with conflict resolution.
- Auto-generated REST API for every form and collection:
  - scoped tokens;
  - OpenAPI documentation generated automatically;
  - rate limits.
- Incoming and outgoing webhooks, with signing secrets and delivery logs.
- In-app notifications center (bell icon) in addition to email.
- System settings: branding, languages, formats, calendar system, SMTP, file limits, security policies, SSO/LDAP configuration.

### 4.23 Custom Downloads (Download Profiles)
Admins create reusable **download profiles** for any form. A profile defines exactly which data is exported, including data from linked forms at any depth.

**Profile Builder**
- **Base form:** choose the form the profile belongs to.
- **Relation tree explorer:**
  - Shows the base form and every form or collection linked to it, directly or indirectly, at unlimited depth (e.g. Form A → Form B → Form C).
  - Covers both directions: parent references and child records.
  - The admin expands the tree and checks the fields to include from any level.
  - Each selected column shows its full path (e.g. `Request › Employee › Department › Manager Name`).
- **Handling one-to-many paths:** for each path that returns multiple related records, the admin chooses one of these:
  - one row per related record (flattened, parent values repeated);
  - an aggregate in a single cell: count, sum, average, min, max, first, last, or joined text with a chosen separator;
  - a separate Excel sheet per related form, linked by a reference key.
- **Columns:**
  - custom header (AR/EN), drag-and-drop ordering, width, and display format (date, number, currency, digits);
  - calculated columns (formula editor);
  - static columns;
  - system columns: record ID, status, created by/at, updated by/at, last transition date.
- **Filters and parameters:**
  - fixed filters saved in the profile;
  - option to apply the user's current table filters and selected records;
  - runtime parameters the user fills at download time (e.g. date range, department).
- **Output:**
  - Excel (.xlsx) with multiple sheets, styled headers, frozen header row, optional logo and title row, and RTL sheets for Arabic;
  - CSV (UTF-8 with BOM for Arabic);
  - PDF via mPDF (portrait/landscape, header/footer, page numbers).
- **File naming:** a pattern with placeholders, e.g. form name, date, user.
- **Live preview:** the first 20 rows before saving.

**Access & Security**
- Each profile is auto-registered as a permission in the Roles & Permissions matrix, and is available only to the roles, users, or departments granted.
- Field-level access is always enforced, for the downloading user, at every level of the relation chain:
  - columns the user is not allowed to see are omitted automatically;
  - the admin can preview the effective columns per role.
- Record-level security is applied at every level of the chain. Users never receive records they cannot access.
- Sensitive fields stay masked unless the user has explicit permission to view them.

**Usage**
- Profiles appear in a **Download** menu in:
  - the records table toolbar (all filtered records or selected records);
  - the record view page (a single record with its related data).
- The **Create Personal Download Profiles** permission allows users to build their own profiles, limited to the fields they can access.
- **Scheduled downloads:** a profile can run on a schedule (daily, weekly, monthly) and be emailed to chosen users or roles.

**Execution**
- Queries are optimized for multi-level relations: joins or eager loading, chunked processing, and no N+1 queries.
- Large downloads run as background jobs:
  - a progress indicator and an in-app/email notification when ready;
  - a signed, expiring download link.
- A configurable maximum row limit applies per profile.
- Every download is audited: who, profile, filters, row count, time.
- Failures appear in the Operations Center with a retry option.
- Profiles can be duplicated and are included in configuration packages.

### 4.24 Edit Justification & Change Control
Admins can require users to state **why** a record was changed. The requirement is configurable, never hard-coded, and off by default.

**Where a justification can be required**
- **Per form:** any edit to the record.
- **Per field or field group:** only edits touching those fields. A salary or an amount can require a reason while a phone number does not.
- **Per status:** only once the record has reached a given status, so corrections before submission stay frictionless and changes after approval are documented.
- **Per role, user, or department:** mandatory for one audience, optional or absent for another.
- **Per action and per transition:** including bulk actions, imports, and status changes.
- **On delete and restore**, where a reason can be required independently of edits.

**Requirement levels**
Each rule is set to one of:
- **Not required** — no prompt.
- **Optional** — a reason box is shown and may be left empty.
- **Mandatory** — the save is blocked until a reason is given.
Conditional rules from 4.7 can switch between these levels, so a justification can become mandatory only when, for example, an amount changes by more than a configured threshold or the record is already approved.

**What the prompt collects**
- A free-text reason, with configurable minimum and maximum length, in the user's language.
- Optionally a **reason code** chosen from an admin-defined list (a collection), with its own validation, and optionally a free-text note required only for certain codes such as "Other".
- Optionally one or more attachments as supporting evidence, with the file rules of 4.6.
- The admin writes the prompt's title and help text in Arabic and English, and can show the user a summary of exactly which fields changed before they write the reason.

**Behavior**
- The prompt appears at save time, after validation passes, so the user never writes a justification for a save that then fails.
- Enforcement is server-side. A save that should carry a justification and does not is rejected regardless of what the client sent.
- Bulk edits ask once and apply the same justification to every affected record, with the count shown. Imports take a justification for the batch.
- A justification is immutable once saved. It cannot be edited or deleted by anyone, including Super Admin; a correction is added as a new entry.

**Where justifications appear**
- Attached to the audit log entry for that change, beside the old and new values.
- In the record's history timeline, as its own entry showing who, when, which fields, the reason code, the text, and any attachment.
- As columns available in table views, download profiles, and reports, subject to the same field-level permissions as any other data.
- Viewing justifications is governed by a dedicated permission, since a reason can itself contain sensitive information.

### 4.25 Assignment, Queues & Delegation
A status alone does not say who is expected to act, so records carry assignment as a first-class concept.

**Assignment**
- A record can be assigned to a user, to a role, or to a department, automatically on a transition or manually by anyone with the permission.
- Assignment rules per transition: assign to a specific user or role, to the user in a chosen field, to the record creator's manager, round-robin within a role, or least-loaded within a role.
- Reassignment is permission-controlled and recorded in the audit log, with an optional justification under the rules of 4.24.

**Work queues**
- Every user has a **My Work** view listing records assigned to them or to their roles and departments, across all forms, with due dates, SLA state, and priority.
- Role and department queues support **claim** and **release**, so a record taken from a shared queue is locked to one person rather than worked twice.
- Admins configure which forms appear in queues and which columns are shown.

**Delegation and cover**
- A user can delegate their work to another user for a period, with a reason; delegation is permission-controlled and can be restricted to specific forms.
- Out-of-office cover can be set by an admin on a user's behalf.
- Actions taken under delegation are recorded as performed by the delegate **on behalf of** the original user, in both the audit log and notifications.

**Parallel and multi-party approvals**
- A transition can require approval from several roles or users, as **all of**, **any N of**, or a weighted quorum.
- Each approver's decision, timestamp, and comment is recorded separately; the transition completes only when the rule is satisfied.
- Rejection behavior is configurable: return to a chosen status immediately, or wait for all decisions.
- Approval requests appear in the approver's queue and notifications, with reminders and escalation under the SLA rules of 4.12.

### 4.26 Data Retention, Archiving & Personal Data
- **Retention policies** per data class, configurable in the UI: records, audit logs, error logs, email logs, notification logs, submission journal entries, download files, and uploaded attachments.
- Each policy sets a retention period and an end action: archive to cold storage, export then delete, or delete. Policies run as scheduled jobs and every run is audited.
- **Log growth is planned for, not discovered:** audit and error tables are partitioned by time, with an archive path and a documented restore procedure. The system warns when a table or the storage volume crosses configurable thresholds.
- **Storage quotas** per form and per application, with a warning threshold and a hard limit, and a report of storage used by form and by user.
- **Personal data handling:** an admin can locate every record and log entry relating to a given person, export them, and action a deletion or anonymization request. Anonymization preserves the audit trail's integrity by replacing identifying values while keeping the sequence of events intact. Fields are markable as personal data in the field properties, which is what drives this search.
- Legal hold: a record or a set of records can be exempted from deletion until the hold is lifted, with the reason recorded.

### 4.27 Applications, Workspaces & Tenancy
- One installation hosts many **applications** (HR, Procurement, Support), each with its own menus, forms, collections, permissions, branding, and settings, built by admins without code.
- Applications can be enabled per department or per role, cloned wholesale, exported as a configuration package, archived, or retired.
- Data can be shared across applications through relations, or isolated, as the admin chooses per form.
- **Business units / tenancy mode**, chosen at setup: single organization, or multiple organizations sharing the installation with isolated data, separate branding, separate admins, and a global administrator above them. Cross-organization reporting is permission-controlled.

### 4.28 Appearance, Branding & Theming
- A visual theme editor with live preview: primary and accent colors, semantic colors, surface and border colors, light and dark palettes, border radius, shadow depth, density (compact, normal, comfortable), and font family per script, with Arabic and Latin fonts set independently.
- Logos, favicon, login background, browser title, and email header and footer artwork.
- **Per-application and per-organization themes**, so each area can look like its own product, with a global default.
- Login page content, welcome text, legal links, and support contact, all translatable.
- Admin-managed custom CSS, scoped and sanitized, applied after the theme so it cannot break layout primitives or hide security controls.
- Theme import and export, preset themes, and reset to default. Accessibility contrast is checked and warned on before a theme is saved.

### 4.29 Pages, Home Screens & Navigation
- **Custom pages** built by admins: rich content pages, link pages, dashboard pages, embedded report pages, and pages that host a form directly. Pages are placed in the menu like any form and carry their own permissions.
- **Home screen builder:** a different landing page per role, department, or user, assembled from widgets — my work, my records, charts, KPIs, shortcuts, announcements, recent activity, pinned links, and embedded tables.
- **Navigation builder:** unlimited menu depth, drag-and-drop ordering, icons, badges showing live counts (such as pending approvals), separators, section headers, and visibility per role, department, or condition.
- **Global search configuration:** which forms and fields are searchable, their weighting, result display, and per-role scope.
- **Announcements and banners:** system-wide or per application, scheduled, dismissible, targeted by role, with severity styling.
- **Maintenance mode:** admins take the system or a single application offline with a custom message, while retaining their own access.

### 4.30 Blueprints, Cloning & the Template Library
- Any form, collection, workflow, view, action, notification, dashboard, or whole application can be **saved as a blueprint** and reused.
- Duplicate anything with a choice of what comes along: structure only, structure plus permissions, or everything including notifications and actions.
- An internal **template library** with categories, search, descriptions, and previews, managed by admins, so common patterns (request-and-approve, register-and-review, inspection checklist) are started from rather than rebuilt.
- Blueprints carry a version, and updating a blueprint offers to propagate changes to objects created from it, listing what would change before anything is applied. Elements changed locally in an object are kept and listed as conflicts; propagated changes land in the object's draft and take effect when it is published; an object can be detached to stop receiving updates.
- Import and export of blueprints between environments and installations; each exported version carries a content hash that import verifies, and objects that the blueprint relates to must exist in the target before it can be used.

### 4.31 Scheduler & Automation Rules
- An admin-managed **automation builder** with no code: a trigger, optional conditions, and a sequence of steps reusing the action library of 4.15.
- **Triggers:** record created, updated, or deleted; field changed; status changed; a condition becoming true; a schedule (hourly, daily, weekly, monthly, cron-style); a date field reached, with an offset such as three days before expiry; an inbound webhook; a file arriving in a watched folder; manual run.
- **Steps:** update fields, change status, assign or reassign, create a linked record, send an email or in-app notification, generate a document, run a custom download and deliver it, call an outbound webhook through the egress gateway, or wait for a delay or a condition.
- **Controls:** enable and disable, run now, test against a sample record, concurrency limits, retry policy, loop protection (an automation cannot re-trigger itself indefinitely), and a maximum affected-record count per run with a confirmation above it.
- A **run history** per automation showing trigger, records affected, steps executed, duration, and errors, with failures surfacing in the Operations Center.
- A scheduled-task manager listing everything scheduled across the system, with next and last run times.

### 4.32 External Access & Public Portals
- Any form can be published as an **external form** reachable without an internal account, with a dedicated URL, its own theme, and a defined status on submission.
- Protection options: CAPTCHA, rate limits per address, allowed time window, submission caps, email or SMS verification, and an optional access password.
- **External user accounts** (customers, suppliers, applicants) with their own registration and approval flow, their own role, and record-level access limited to their own records and the forms the admin exposes.
- **Tokenized single-record links**: send a reviewer or signer a secure expiring link to one record, with a scoped action such as approve, reject, complete a section, or sign, without an account.
- **Signature requests**: send a record for signature, track status, and store the signed output with the audit trail.
- Everything external is still governed by the permission, condition, justification, and audit rules of the rest of the system.

### 4.33 Integrations & External Data Sources
- **External data sources** defined in the UI: a REST endpoint or a database view registered as a source, with authentication, headers, a response mapping, and a cache policy. Once registered it can feed select options, lookups, validation, and read-only panels exactly like a collection.
- **Sync jobs:** scheduled import from, or export to, an external source, with field mapping, matching keys, conflict rules, dry run, and a run log.
- **Inbound API endpoints per form**, with scoped tokens, schema documentation, validation, and the same permission and workflow enforcement as the UI.
- **Incoming webhooks** that create or update records, with signature verification and a payload mapping screen.
- **Notification channels beyond email:** in-app, SMS, and messaging providers configured as channels, selectable per notification rule, with per-user channel preferences and the same template, placeholder, and logging treatment as email.
- Every integration appears in the Operations Center with its failures, retries, and delivery history.

### 4.34 Reference Data, Calendars & Numbering
- **Business calendars:** working days, working hours, public holidays per country or organization, and multiple calendars assignable per department or form. SLA timers, due dates, and reminders count working time, not wall-clock time, when the admin chooses.
- **Numbering sequences manager:** every auto-number pattern in one place, with prefix, date parts, padding, step, reset period (never, daily, monthly, yearly), per-form or shared scope, current value, and a controlled, audited manual adjustment.
- **Currencies and exchange rates:** enabled currencies, display and rounding rules, manual or scheduled rate updates, and historical rates so past records keep their original values.
- **Units of measure** and conversion rules for numeric fields.
- **Shared reference collections** (countries, cities, departments, job titles, document types) usable across applications, with one owner and read-only use elsewhere.

### 4.35 Data Quality & Bulk Data Tools
- **Duplicate detection:** admin-defined matching rules per form (exact, normalized, or fuzzy on chosen fields), applied at entry time as a warning or a block, and runnable as a sweep over existing records.
- **Record merge:** choose the surviving record, pick the winning value field by field, repoint related records, and keep a full audit of the merge with a justification.
- **Bulk update, bulk reassign, and bulk status change** with a preview of affected records, a dry run, a maximum-count guard, and a mandatory justification where configured.
- **Data repair tools** for admins: find orphans, find records failing current validation after a rule change, and fix them in a guided screen rather than in the database.
- **Recycle bin** with retention: soft-deleted records are restorable until the retention period expires, and restoration is audited.

### 4.36 User Self-Service, Impersonation & Access Policies
- **User profile:** language, timezone, calendar preference, date and number format, theme (light, dark, system), density, notification channel preferences and digest frequency, and default landing page, all within limits the admin sets.
- **Saved views, pinned records, and personal shortcuts**, and personal API tokens if the admin permits them.
- **Impersonation:** an admin with the permission can act as another user to reproduce a problem. A persistent banner shows it, the session is time-limited, sensitive actions can be blocked during it, and every action is recorded as performed by the admin impersonating the user.
- **Access policies per role:** IP allowlists, permitted time windows, maximum concurrent sessions, session lifetime, device trust, and a stricter policy for administrative roles.
- **Feature flags** per application or role, so admins can release a new form or capability to a pilot group before everyone.

### 4.37 Help, Guidance & Adoption
- Admins author **help content** per form, per field, and per page, translatable, shown as tooltips, side panels, or a help page.
- **Guided tours** and first-use hints per form or role, with a reset option.
- An internal **knowledge page** per application, plus a changelog that admins publish to users when forms change.
- Usage insight for admins: which forms are used, by whom, how often, where users abandon a form, and which fields are left empty, so the builder can be improved on evidence.

## 5. Security Requirements
- **Standards:** full OWASP Top 10 compliance.
- **Access control:**
  - server-side authorization and record-level checks on every request (IDOR prevention);
  - mass-assignment protection for dynamic models.
- **Input and output safety:**
  - parameterized queries only;
  - strict validation of all input, including metadata definitions;
  - XSS protection, sanitized rich text, strict Content Security Policy;
  - CSRF protection;
  - security headers (HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy).
- **File uploads and downloads:**
  - type and MIME validation, storage outside the public root;
  - signed temporary download URLs;
  - ClamAV scanning.
- **Authentication:**
  - configurable password policy and 2FA (mandatory for admins);
  - lockout after failed attempts;
  - session timeout and active session management (view/revoke);
  - SSO (OAuth2/OIDC) and LDAP;
  - rate limiting.
- **Server-side request forgery:** all outbound calls made on a user's or admin's behalf (webhooks, action steps, remote template or image fetches) go through a single egress gateway enforcing the allowlist, blocked address ranges, address pinning, redirect and size limits described in 4.15. No module issues outbound HTTP directly.
- **Untrusted input parsing:** spreadsheet, document, and image parsing runs with external entities and remote references disabled, with size and time limits, and produces escaped output (4.15).
- **Expression safety:** the expression language is pure, bounded in depth and time, and cannot reach the filesystem, the network, or arbitrary code (4.7). Developer hooks remain the only code path, and they are reviewed and approved (4.19).
- **External surfaces:** public forms, external users, and tokenized links are separated from internal authentication, hold no implicit permissions, are rate limited, and expose only the fields and records the admin published. Tokens are single-purpose, expiring, revocable, and bound to one record and one action.
- **Admin-authored content** (custom CSS, help content, page content, email templates) is sanitized before rendering and cannot introduce script, exfiltrate data, or conceal security controls.
- **Impersonation** cannot be used to escalate privilege, is always visible in the interface, is time-limited, and is audited as the acting admin.
- **Data protection:** encryption of sensitive fields; secrets only in environment config; per-tenant and per-field key management with a documented key rotation procedure.
- **Operations:** scheduled backups with a documented restore procedure; dependency vulnerability scanning (composer audit, npm audit).
- No sensitive data in logs or in user-facing errors.
- **Additional safeguards (added in Phase 1):**
  - two-factor authentication cannot be switched off by holders of a role that requires it; an administrator can reset it, which forces re-enrollment at the next sign-in;
  - "forgot password" answers the same way whether or not the address belongs to an account; passwords are checked against a list of the 10,000 most common passwords, must not contain the user's name or e-mail, and may not repeat recent passwords;
  - session identifiers are never shown; session lists expose only an opaque keyed hash of each session;
  - secret settings (SMTP password, LDAP bind password, SSO client secrets) are write-only: encrypted at rest and in the cache, never returned by the API, and masked in the audit log, as is any changed value whose field name denotes a secret;
  - the egress gateway also blocks the whole 6to4 range (`2002::/16`) and IPv4-mapped, IPv4-compatible and NAT64 forms of blocked IPv4 addresses;
  - users who sign in through OpenID Connect or LDAP never take over an existing local account; matching an existing account by e-mail is allowed only for accounts of the same source and only when the provider is configured to allow it;
  - the Content Security Policy uses a per-request nonce and no `'unsafe-inline'` or `'unsafe-eval'`; styles injected by UI components carry the same nonce.
  - creating a local user e-mails a password set-up link; when outgoing e-mail is not configured yet (the setup wizard allows configuring it later) or sending fails, the user is still created, the administrator is told that the link was not sent and why, a sending failure is recorded in error monitoring, and the link can be sent again from the user's actions;
  - the application never starts with a missing application key or database: start-up stops with instructions when the key is empty, and the configured database is created with the prescribed collation when it does not exist (an existing database is never altered or dropped).

## 6. Non-Functional Requirements
- **Performance:**
  - server-side pagination, filtering, and sorting;
  - metadata caching with automatic invalidation;
  - background jobs for heavy work.
- **Data integrity:** transactions, constraints, idempotent retries, and optimistic concurrency on every record write (4.9).
- **Performance budgets**, verified by load tests in Phase 6: a form renders and a record list returns within a defined target at a stated record count; permission resolution is cached; a defined number of concurrent users is supported. The targets are written into `docs/architecture.md` in Phase 0 and tested against, not left implicit.
- **UI:** fully responsive, accessible (WCAG 2.1 AA), and consistent in design across RTL and LTR.
- **Code quality:**
  - modular, strictly typed, documented;
  - automated tests covering every module on both database engines.

## 7. Data Model (Metadata Layer)
Design complete schemas (all columns, types, indexes, foreign keys) for the following entities:

- **Structure:**
  - Applications, MenuItems;
  - Collections, Forms, FormVersions;
  - FieldGroups, Fields, FieldOptions, FieldAccessRules;
  - Conditions, Relations, FieldTemplates.
- **Access:**
  - Users, Departments, Roles, UserRoles;
  - Permissions, PermissionAssignments, RecordAccessRules;
  - Sessions.
- **Workflow:** Statuses, Transitions, StatusHistory, StatusMappings, SlaRules.
- **Views & actions:**
  - Views, ViewColumns, Filters, SavedViews;
  - ViewPanels, ReferencePreviews;
  - Actions, ActionSteps.
- **Custom downloads:** DownloadProfiles, DownloadProfileColumns, DownloadProfileFilters, DownloadSchedules, DownloadJobs.
- **Justification & change control:** JustificationRules, Justifications, JustificationReasonCodes, JustificationAttachments.
- **Assignment & delegation:** Assignments, AssignmentRules, Queues, QueueClaims, Delegations, ApprovalRequests, ApprovalDecisions.
- **Retention & personal data:** RetentionPolicies, RetentionRuns, ArchivedRecords, LegalHolds, PersonalDataRequests, StorageQuotas.
- **Schema management:** MigrationPlans, MigrationSteps, SchemaSnapshots, SchemaReconciliationReports, PublishLocks.
- **Localization:** Locales, Translations (keyed by object type, object id, field, and locale).
- **Appearance & pages:** Themes, ThemeAssets, Pages, PageWidgets, HomeScreens, Announcements, HelpContent, Tours.
- **Blueprints:** Blueprints, BlueprintVersions, BlueprintInstances.
- **Automation:** Automations, AutomationTriggers, AutomationSteps, AutomationRuns, ScheduledTasks.
- **External access:** ExternalForms, ExternalUsers, AccessTokens, SignatureRequests, SubmissionThrottles.
- **Integrations:** ExternalDataSources, SyncJobs, SyncRuns, NotificationChannels, InboundEndpoints.
- **Reference data:** BusinessCalendars, Holidays, NumberSequences, Currencies, ExchangeRates, UnitsOfMeasure.
- **Data quality:** DuplicateRules, MergeHistory, BulkOperations, RecycleBin.
- **Access & adoption:** AccessPolicies, FeatureFlags, ImpersonationSessions, UserPreferences, UsageMetrics.
- **Notifications:** NotificationRules, EmailTemplates, EmailQueue, InAppNotifications.
- **Documents & reports:** DocumentTemplates, Reports, Dashboards, DashboardWidgets.
- **Operations & monitoring:** SubmissionJournal, AuditLogs, ErrorLogs, ErrorGroups.
- **Extensibility & integration:** Extensions, ExtensionVersions, ApiTokens, Webhooks, WebhookDeliveries.
- **System:** ConfigPackages, Settings, EgressAllowlist.

Physical per-form and per-collection tables are generated from this metadata.

## 8. Delivery Plan: Phase-by-Phase with Verification

### 8.1 Execution Rules
- The system is delivered in **eight phases (Phase 0, 1, 2, 2.5, 3, 4, 5, 6)**, in the exact order below.
- **Each phase must be fully complete before it ends.**
  - Every feature in the phase's scope works end to end.
  - No placeholders, stubs, TODOs, mock data, fake endpoints, or "to be implemented" sections for in-scope features.
- **If output length or session limits require multiple runs within a phase:**
  - commit the finished work before stopping;
  - record the exact resume point in `docs/progress.md`;
  - continue sequentially until the phase is complete;
  - never summarize, abbreviate, or skip code.
- **At the end of every phase from Phase 1 onward:**
  - the application must install, run, and be usable for everything delivered so far;
  - the Admin Console shows only areas that are already built, never empty or fake screens for future phases.
- Build each phase so later phases integrate without rewriting earlier work: clean interfaces, events, and extension points.
- **Handling ambiguity:** when a detail is not specified, choose the most secure, standard, and user-friendly option, document the decision, and continue.
- **All work happens in the GitHub repository.** Nothing is delivered as chat-only output; every file is committed.
- **Project memory files**, committed and updated at the end of every run:
  - `docs/architecture.md` — the approved architecture, ERD, and all technical decisions;
  - `docs/progress.md` — phase status, completed features, verification results, and the exact resume point;
  - `docs/decisions/` — one short record per significant decision.

### 8.2 Phase Verification (Mandatory at the End of Each Phase)
Before declaring a phase complete:

1. **Requirements checklist.** List every requirement of the phase from sections 2 to 7. For each one, state:
   - status: done;
   - where it is implemented (files, endpoints, screens);
   - how it was verified.
2. **Automated tests:**
   - run all tests (Pest, Vitest, Playwright) on **both MySQL and SQL Server**;
   - report the results.
   - Any failure must be fixed before reporting.
3. **Security check.** Verify for this phase's features:
   - authorization;
   - input validation;
   - OWASP Top 10 concerns.
4. **Regression check.** Confirm all previous phases still work.
5. **Manual verification guide.** Step-by-step instructions the user can follow in a browser, with exact URLs, credentials, and expected results.
6. **Known issues:** none are allowed. Any issue found must be fixed before the phase is closed.

For Phase 0, which produces no code, steps 2–5 are replaced by a **design coverage check**: confirm that every requirement in sections 2 to 7 is covered by the architecture and data model, and list where each is covered.

The verification report goes in the phase's pull request description. After opening the PR, **stop and wait**. Do not merge, and do not start the next phase until the user explicitly approves it.

When resuming:
- read `docs/specification.md`, `docs/architecture.md`, and `docs/progress.md`;
- check the current branch and open pull requests;
- verify the previous state is intact;
- then continue.

### 8.3 Phases

**Phase 0: Architecture & Data Model (no code)**
- **Architecture document:**
  - system layers and runtime engine design;
  - module boundaries;
  - folder structure for backend and frontend;
  - database driver layer design for MySQL and SQL Server;
  - security architecture;
  - queue, job, and event design.
- **Complete ERD** for all metadata entities in section 7, with every column, type, index, and foreign key.
- **Physical table generation strategy**, including migration handling, versioning, and archiving of removed fields.
- **Metadata JSON schema:** how forms, groups, fields, conditions, rules, justification rules, and download profiles are represented and stored.
- **Expression language specification** in `docs/expression-language.md`: grammar, type system, function library, null and error semantics, AST JSON format, and the conformance corpus both runtimes are tested against (4.7).
- **Permission resolution algorithm:** the inheritance order, sparse storage model, caching, and invalidation strategy (4.11).
- **Schema change strategy:** migration plans, failure and recovery handling, locking, snapshots, and reconciliation (4.9).
- **Performance budgets and capacity assumptions** (section 6).
- **Extension model for admin-built surfaces:** how pages, widgets, themes, automations, and external data sources are represented in metadata and rendered by the runtime, so later phases add capability without reworking the core.
- **Relation traversal design:** how multi-level relation paths are resolved and queried efficiently (used by filters, view panels, reports, and custom downloads).
- **API outline:** every endpoint group, with methods, payloads, and required permissions.
- **Frontend architecture:**
  - state management;
  - the form builder's metadata model;
  - the runtime form renderer;
  - the component library structure.
- **List of technical decisions** with reasoning.
- Save everything to `docs/architecture.md`, and create `docs/progress.md`.

**Phase 1: Foundation, Security & Administration Core**
- **Project setup:**
  - Docker Compose, dev container, Laravel + Vue project structure, CI pipelines;
  - database driver layer for MySQL and SQL Server.
- **First run:** web setup wizard, then permanent lock.
- **Application shell:**
  - layout (sidebar, top bar);
  - RTL/LTR, Arabic/English;
  - translations manager.
- **Authentication:**
  - Sanctum and Fortify;
  - 2FA (mandatory for admins), password policy;
  - lockout, session management;
  - SSO (OAuth2/OIDC) and LDAP.
- **Users & Departments management**, including the department tree.
- **Roles & Permissions:**
  - the unified interface;
  - system permissions and role/user/department assignment;
  - precedence rules;
  - "View as user".
  - The form, field, and status matrices are extended in later phases.
- **Admin Console:** built areas only.
- **System Settings:** branding, formats, calendar system, SMTP with test, file limits, security policies.
- **Audit Log** (full).
- **Error Monitoring** (full), including correlation IDs.
- **Security baseline:** headers, CSP, rate limiting, encryption.

**Phase 2: Form Builder, Collections & Data Engine**
- **Collections:**
  - create collections and manage their records;
  - Excel import/export;
  - schema explorer with ERD view.
- **Form Builder canvas:**
  - three panels, full drag-and-drop, nesting;
  - undo/redo, copy/paste, multi-select, autosave;
  - reusable field library.
- **Inputs and groups:**
  - **all input types** and layout/group elements (4.4);
  - full group properties (4.5);
  - full field properties (4.6).
- **Conditions engine** (4.7), including the expression language with both runtimes and the shared conformance corpus passing in CI.
- **Data layer:**
  - physical table generation, relations, repeaters as child tables (4.9);
  - migration plans with failure recovery, publish locking, snapshots, and schema reconciliation;
  - optimistic concurrency with the conflict screen;
  - database binding and schema introspection.
- **Versioning:** draft, preview, impact analysis, diff, rollback (4.10).
- **Publishing:** sidebar placement and the menu editor (4.13).
- **Blueprints and cloning** (4.30) for forms, collections, and views, so the pilot in Phase 2.5 can be built quickly.
- **Reference data, calendars, and numbering sequences** (4.34), which fields depend on.
- **Runtime form renderer:** create, edit, and view modes with live preview as any role/user.
- **Submission journal:** capture of every submission. Its management UI comes in Phase 4.
- **Permission matrix extended:**
  - form-level permissions;
  - group and field access rules per role, user, department, and mode;
  - the inheritance and sparse-override resolution model, effective-permission caching, and "explain access" (4.11).

**Phase 2.5: Pilot & Validation (short, mandatory)**
- Before building further engines, prove the ones that exist against reality.
- The repository owner builds two or three **real** forms from their own organization in the running system, with real users, including at least one form linked to another.
- Claude's role is to fix what the pilot exposes, not to create the forms, and never to seed them into the product.
- Deliverables: a findings report in `docs/pilot-findings.md`, fixes applied, and a list of specification changes the pilot proved necessary, applied to `docs/specification.md` before Phase 3 begins.
- This phase ends when the owner confirms the pilot forms work for their users.

**Phase 3: Workflow, Records & Views**
- **Workflow engine:**
  - statuses and transitions;
  - Vue Flow visual designer;
  - SLA timers and escalations;
  - status history.
- **Status-based access:**
  - field and group access per status;
  - status-level transition permissions in the matrix;
  - status mapping screen for changes.
- **Records table view:**
  - per-role columns and filters, including fields of linked forms;
  - saved/shared views, bulk selection;
  - soft delete and restore.
- **Record-level security rules.**
- **Edit justification** (4.24): rule configuration per form, field, group, status, role, and transition; the save-time prompt with reason codes and attachments; server-side enforcement; immutability; display in history, tables, and the audit log.
- **Assignment, queues, delegation, and multi-party approvals** (4.25), including My Work, claim and release, and on-behalf-of recording.
- **View Mode:** related-data panels, derived fields, summary widgets, timeline, comments, attachments.
- **Edit Mode:** reference preview card, auto-fill, side drawer.
- **Print view** and PDF export (mPDF).

**Phase 4: Actions, Downloads, Notifications, Documents & Operations**
- **Built-in actions:**
  - export;
  - import with mapping, dry run, and upsert;
  - bulk operations, print, duplicate.
- **Custom actions:** chained steps and conditions, with per-action permissions in the matrix, and all outbound calls routed through the egress gateway (4.15).
- **Custom Downloads** (4.23):
  - profile builder with a relation tree explorer at unlimited depth;
  - one-to-many handling and runtime parameters;
  - all output formats;
  - permissions and scheduled downloads;
  - background jobs.
- **Email notifications:** triggers, To/CC/BCC control, visual template editor, preview, test send.
- **In-app notifications center**, with per-user channel preferences.
- **Automations and scheduler** (4.31): trigger and step builder, run history, loop protection, test run.
- **Data quality tools** (4.35): duplicate rules, merge, bulk operations with guards, recycle bin.
- **Document templates:** DOCX and HTML to DOCX/PDF.
- **Operations Center:**
  - email queue monitor with resend;
  - failed submissions with retry/resync;
  - failed jobs;
  - health widgets;
  - threshold alerts.

**Phase 5: Platform & Extensibility**
- **Developer extensions:**
  - server hooks and client extensions;
  - Monaco editor, sandbox, approval workflow;
  - versioning and rollback.
- **REST API:** auto-generated, with scoped tokens, rate limits, and OpenAPI docs.
- **Webhooks:** incoming and outgoing, with signing and delivery logs.
- **Configuration packages:** export/import with conflict resolution, plus the compare-environments drift report (4.10).
- **Reports & dashboards:** report builder, pivot tables, charts, drag-and-drop dashboards.
- **Applications, pages, home screens, and navigation** (4.27, 4.29), including global search configuration, announcements, and maintenance mode.
- **Appearance and branding** (4.28), per application and per organization, with contrast checking.
- **Integrations and external data sources** (4.33), sync jobs, inbound endpoints, and additional notification channels.
- **External access and portals** (4.32): external forms, external users, tokenized links, signature requests.
- **Blueprint library** completed (4.30), including whole-application cloning and propagation.
- **Self-service, impersonation, access policies, and feature flags** (4.36).
- **Help content, tours, announcements, and usage insight** (4.37).

**Phase 6: Hardening & Final Delivery**
- **Retention, archiving, storage quotas, and personal data handling** (4.26), including partitioning of audit and error tables and the documented restore path.
- **Full security review** of the entire system against section 5, with fixes applied, including an SSRF and file-parsing review and an audit-chain verification run.
- **Performance:**
  - performance tuning (indexes, caching, query optimization);
  - load testing of large tables, imports, and multi-level custom downloads, measured against the Phase 0 performance budgets.
- **Accessibility** review (WCAG 2.1 AA) and RTL/LTR visual consistency pass.
- **Complete end-to-end test suite** on both database engines.
- **Documentation:**
  - admin user guide;
  - developer extension guide;
  - deployment, backup, and restore guide.
- **Final verification** of every requirement in sections 2 to 7 across the whole system.

## 9. Rules
- Never seed business forms or demo data.
- Every capability must be configurable from the admin UI without code.
- Never add a feature that bypasses permissions or security.
- The UI must be modern, elegant, and professional in both Arabic and English.
- Audit entries and saved justifications are immutable; no interface may edit or delete them.
- No module issues outbound HTTP except through the egress gateway.
- Conditions, formulas, and defaults use the expression language only; never generated code.
- Every record write uses optimistic concurrency; silent overwrite is never acceptable.
- Anything an admin should be able to change belongs in metadata and the admin UI, never in a configuration file, a seeder, or code.

## 10. Repository & Delivery Workflow (GitHub)

**Repository structure**
```
/backend            Laravel application
/frontend           Vue application
/docker             Dockerfiles and compose files
/.devcontainer      cloud development environment
/.github/workflows  CI and automation pipelines
/docs               specification, architecture, progress, guides
```

**Branching and commits**
- `main` is the stable branch and is never committed to directly.
- One branch per phase: `phase-0-architecture`, `phase-1-foundation`, `phase-2-form-builder`, and so on.
- Small, focused commits using Conventional Commits (`feat:`, `fix:`, `docs:`, `test:`, `chore:`).
- Never force-push, rewrite history, or delete existing work.
- Commit before the end of every run; never leave work uncommitted.

**Pull request per phase**
- Each phase ends with **one pull request** from its branch into `main`.
- The PR description contains the complete verification report from section 8.2 and links to the relevant specification sections.
- **Do not merge the PR.** The user reviews it, requests changes in PR comments, and merges when satisfied.
- Respond to PR review comments by pushing fixes to the same branch.
- After the merge, tag a release: `v0.1-phase-1`, `v0.2-phase-2`, and so on.

**Continuous integration**
- CI runs on every push and pull request, and must pass before a phase is reported as complete.
- It runs migrations and the full test suite against **both MySQL and SQL Server**, plus linting, static analysis, and dependency audits.
- If any check cannot run in CI, say so explicitly in the PR instead of reporting it as passed.

**Verification without a local machine**
- The manual verification guide must be executable entirely in a browser, using the dev container or Codespaces with a forwarded port.
- Every step states the exact URL, credentials, and expected result.
- Playwright end-to-end tests cover each phase's main flows, with screenshots attached to the PR.

**Repository hygiene**
- `README.md` explains the system, how to open it in a cloud environment, and how to run it.
- `CLAUDE.md` at the repository root holds the working rules: branch naming, commit style, where progress is tracked, and how to resume.
- `.env.example` is complete; no secrets, keys, or credentials are ever committed. CI credentials come from GitHub Secrets.
- Proper `.gitignore`, a `CHANGELOG.md` updated per phase, and issue/PR templates.
- Open a GitHub issue per phase with its scope checklist, and close it when the PR is merged.
