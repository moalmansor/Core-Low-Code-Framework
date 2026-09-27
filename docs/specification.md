# System Specification: Core Low-Code Framework (Laravel + Vue.js)

## 1. Role & Objective
You are a senior software architect and full-stack engineer specialized in Laravel, Vue.js, and secure enterprise systems. Build a **complete, production-ready core low-code framework**. Administrators use it to build any business system entirely from the UI, **without writing a single line of code**.

Everything is created by administrators through the UI and stored as metadata:
- forms and field groups;
- collections, fields, and relations;
- workflows and statuses;
- permissions, views, filters, and actions;
- download profiles;
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
- **Setup wizard (web-based), shown on first launch.** It collects:
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
- Arabic (RTL) and English (LTR), with a translation manager in the UI for adding languages.

**Testing**
- Pest (backend), Vitest (frontend), Playwright (end-to-end).

**Deployment & Environments**
- Docker Compose (app, queue worker, scheduler, Redis, MySQL, SQL Server, ClamAV, Mailpit for development).
- A `.devcontainer` configuration so the repository opens and runs in a cloud development environment (GitHub Codespaces or an equivalent sandbox) with no local installation.
- GitHub Actions workflows for CI: service containers for MySQL, SQL Server, and Redis; the full test suite (Pest, Vitest, Playwright); static analysis (Larastan/PHPStan, ESLint, Prettier); dependency audits (composer audit, npm audit).
- Playwright artifacts (screenshots, videos, HTML report) uploaded on every CI run.
- Full installation documentation.

## 4. Modules & Requirements

### 4.1 Admin Console
A single, well-organized control center with navigation to:
- Dashboard & System Health
- Operations Center
- Form Builder
- Collections
- Applications & Menus
- Roles & Permissions
- Workflows & Statuses
- Views, Filters & Actions
- Custom Downloads
- Email Templates & Notification Rules
- Document Templates
- Reports & Dashboards
- Users & Departments
- Audit Log
- Error Monitoring
- Developer Extensions
- Configuration Packages
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
- Inline sub-form of a linked form (create child records inside the parent).

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
- Edit tracking: track changes in the audit log (on/off); mark as sensitive, which masks the value in logs and errors.

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

### 4.8 Collections (Tables & Option Sources)
- Admins create collections, from simple key/value lists to full tables, using the same field engine as forms.
- Records are managed in the UI, with Excel import/export.
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
- Referential integrity is enforced: no orphaned records, and configurable on-delete rules (restrict, cascade, set null).

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
  - Manage Download Profiles, Create Personal Download Profiles.
- **Precedence:** user overrides department, which overrides role; deny overrides allow.
- **Tools:**
  - "View as user" to see effective permissions;
  - copy permissions between roles;
  - export/import permission sets.
- **Enforcement:** server-side via Laravel Policies/Gates on every request, with no reliance on UI hiding.

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
- **Detail per record and per field:** who, when, old value, new value, IP, user agent.
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
- **Data protection:** encryption of sensitive fields; secrets only in environment config.
- **Operations:** scheduled backups with a documented restore procedure; dependency vulnerability scanning (composer audit, npm audit).
- No sensitive data in logs or in user-facing errors.

## 6. Non-Functional Requirements
- **Performance:**
  - server-side pagination, filtering, and sorting;
  - metadata caching with automatic invalidation;
  - background jobs for heavy work.
- **Data integrity:** transactions, constraints, and idempotent retries.
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
- **Notifications:** NotificationRules, EmailTemplates, EmailQueue, InAppNotifications.
- **Documents & reports:** DocumentTemplates, Reports, Dashboards, DashboardWidgets.
- **Operations & monitoring:** SubmissionJournal, AuditLogs, ErrorLogs, ErrorGroups.
- **Extensibility & integration:** Extensions, ExtensionVersions, ApiTokens, Webhooks, WebhookDeliveries.
- **System:** ConfigPackages, Translations, Settings.

Physical per-form and per-collection tables are generated from this metadata.

## 8. Delivery Plan: Phase-by-Phase with Verification

### 8.1 Execution Rules
- The system is delivered in **seven phases (Phase 0 to Phase 6)**, in the exact order below.
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
- **Metadata JSON schema:** how forms, groups, fields, conditions, rules, and download profiles are represented and stored.
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
- **Conditions engine** (4.7), enforced on client and server.
- **Data layer:**
  - physical table generation, relations, repeaters as child tables (4.9);
  - database binding and schema introspection.
- **Versioning:** draft, preview, impact analysis, diff, rollback (4.10).
- **Publishing:** sidebar placement and the menu editor (4.13).
- **Runtime form renderer:** create, edit, and view modes with live preview as any role/user.
- **Submission journal:** capture of every submission. Its management UI comes in Phase 4.
- **Permission matrix extended:**
  - form-level permissions;
  - group and field access rules per role, user, department, and mode.

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
- **View Mode:** related-data panels, derived fields, summary widgets, timeline, comments, attachments.
- **Edit Mode:** reference preview card, auto-fill, side drawer.
- **Print view** and PDF export (mPDF).

**Phase 4: Actions, Downloads, Notifications, Documents & Operations**
- **Built-in actions:**
  - export;
  - import with mapping, dry run, and upsert;
  - bulk operations, print, duplicate.
- **Custom actions:** chained steps and conditions, with per-action permissions in the matrix.
- **Custom Downloads** (4.23):
  - profile builder with a relation tree explorer at unlimited depth;
  - one-to-many handling and runtime parameters;
  - all output formats;
  - permissions and scheduled downloads;
  - background jobs.
- **Email notifications:** triggers, To/CC/BCC control, visual template editor, preview, test send.
- **In-app notifications center.**
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
- **Configuration packages:** export/import with conflict resolution.
- **Reports & dashboards:** report builder, pivot tables, charts, drag-and-drop dashboards.

**Phase 6: Hardening & Final Delivery**
- **Full security review** of the entire system against section 5, with fixes applied.
- **Performance:**
  - performance tuning (indexes, caching, query optimization);
  - load testing of large tables, imports, and multi-level custom downloads.
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
