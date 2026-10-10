# Architecture & Data Model — Core Low-Code Framework

Status: **Phase 0 deliverable, proposed for approval.**
Contract: [`docs/specification.md`](specification.md). Where this document and
the specification disagree, the specification wins and this document is wrong.
Companion documents:

- [`docs/expression-language.md`](expression-language.md) — grammar, types, functions, AST, conformance corpus.
- [`docs/conformance/expression-corpus.json`](conformance/expression-corpus.json) — the shared conformance corpus.
- [`docs/decisions/`](decisions/) — one record per significant decision (ADR-0001 … ).
- [`docs/design-coverage.md`](design-coverage.md) — Phase 0 design coverage check (spec §2–§7 → this document).
- [`docs/progress.md`](progress.md) — phase status and resume point.

## Table of contents

1. [Guiding principles](#1-guiding-principles)
2. [System layers](#2-system-layers)
3. [Runtime engine design](#3-runtime-engine-design)
4. [Module boundaries](#4-module-boundaries)
5. [Folder structure](#5-folder-structure)
6. [Database driver layer (MySQL & SQL Server)](#6-database-driver-layer-mysql--sql-server)
7. [Security architecture](#7-security-architecture)
8. [Queue, job, and event design](#8-queue-job-and-event-design)
9. [Data model conventions](#9-data-model-conventions)
10. [ERD — complete metadata schema](#10-erd--complete-metadata-schema)
11. [Physical table generation strategy](#11-physical-table-generation-strategy)
12. [Schema change strategy](#12-schema-change-strategy)
13. [Versioning, impact analysis, rollback, environment drift](#13-versioning-impact-analysis-rollback-environment-drift)
14. [Metadata JSON schema](#14-metadata-json-schema)
15. [Expression language (summary)](#15-expression-language-summary)
16. [Permission resolution algorithm](#16-permission-resolution-algorithm)
17. [Relation traversal design](#17-relation-traversal-design)
18. [Extension model for admin-built surfaces](#18-extension-model-for-admin-built-surfaces)
19. [Domain engine designs](#19-domain-engine-designs)
20. [Performance budgets & capacity assumptions](#20-performance-budgets--capacity-assumptions)
21. [API outline](#21-api-outline)
22. [Frontend architecture](#22-frontend-architecture)
23. [Deployment, environments & CI](#23-deployment-environments--ci)
24. [Technical decisions](#24-technical-decisions)
25. [Phase map](#25-phase-map)

---

## 1. Guiding principles

1. **Metadata is the program.** Every business object (form, collection, field,
   workflow, view, action, rule, page, theme, automation, integration) is a row in
   the metadata schema (§10). The runtime engine (§3) interprets it. No business
   object is ever expressed as PHP, TypeScript, a seeder, or a configuration file.
2. **Server is authoritative.** The client evaluates conditions and access for UX
   only; every rule is re-evaluated on the server on every request (§7, §16).
3. **One definition, two runtimes.** Conditions, formulas, defaults, and
   placeholders are a single expression language stored as an AST (§15) with a
   PHP and a TypeScript evaluator kept in lock-step by a shared conformance corpus.
4. **Engine neutrality.** MySQL 8+ and SQL Server 2019+ are equal citizens. Any
   engine-specific SQL lives in the driver layer (§6) and nowhere else.
5. **Nothing is lost.** Submissions are journaled before processing, deletes are
   soft, removed fields are archived, schema steps are reversible, audit entries
   and justifications are append-only and hash-chained.
6. **Sparse, computed configuration.** Access, translation, and override data store
   only deviations; effective values are computed and cached (§16).
7. **Additive phases.** Every engine exposes interfaces, events, and registries
   (§18) so later phases plug in without rewriting earlier code.
8. **Empty shell on first run.** Seeders create only: the four core roles, the
   system permission catalog, default system settings, and the two shipped
   locales (`ar`, `en`) — locales are system settings, see ADR-0007.

## 2. System layers

```mermaid
flowchart TB
  subgraph Client["Browser (Vue 3 + TS SPA)"]
    Shell["App shell / router / Pinia stores"]
    Builder["Admin builders: form, workflow, view, download, page, theme, automation"]
    Renderer["Runtime renderers: form, table, view page, dashboard, page"]
    ExprTS["Expression evaluator (TS)"]
  end
  subgraph Edge["HTTP edge"]
    MW["Middleware: security headers, CSP, CORS, rate limit, correlation ID, locale, tenant, maintenance, access policy, Sanctum"]
  end
  subgraph App["Laravel application (modular monolith)"]
    API["API controllers (thin) + FormRequests"]
    Policies["Policies / Gates → Access Resolver"]
    Services["Application services (use cases)"]
    Runtime["Runtime engine: Definition Compiler, Record Pipeline, Query Planner, Action Runner"]
    Domain["Domain modules (Forms, Workflow, Access, Downloads, ...)"]
    ExprPHP["Expression evaluator (PHP)"]
    Infra["Infrastructure: DB driver layer, Egress gateway, Storage, Mail, Cache, Queue, Secondary log sink"]
  end
  subgraph Data["Data & services"]
    DB[("MySQL 8+ / SQL Server 2019+")]
    Redis[("Redis: cache, queues, locks, rate limits")]
    FS[("Filesystem local / S3")]
    ClamAV["ClamAV"]
    SMTP["SMTP / SMS / messaging providers"]
  end
  Client -->|JSON over HTTPS, Sanctum cookie or token| Edge --> API --> Policies --> Services --> Runtime --> Domain --> Infra
  Runtime --> ExprPHP
  Infra --> DB & Redis & FS & ClamAV & SMTP
  Workers["Horizon workers + Scheduler"] --> Services
```

| Layer | Responsibility | May depend on |
|---|---|---|
| **Presentation (SPA)** | Admin builders and runtime renderers. Holds no authority. | API contracts only |
| **HTTP edge** | Cross-cutting middleware: security headers, CSP nonce, correlation ID, locale, organization/application context, maintenance mode, access policy (IP/time window/sessions), rate limiting, authentication. | Infrastructure |
| **API** | Thin controllers; `FormRequest` validates *shape*; responses via API Resources that apply field-level masking. | Application services |
| **Authorization** | Laravel Policies/Gates that delegate to the Access Resolver (§16). Invoked on every controller action and inside every service that touches records. | Access module |
| **Application services** | Use cases (`PublishForm`, `SaveRecord`, `RunAction`, `GenerateDownload`…). Own transactions, emit domain events, write audit entries. | Runtime, domain modules |
| **Runtime engine** | Interprets metadata: compiles definitions, runs the record pipeline, plans queries over relation paths, executes actions/automations. | Domain modules, expression evaluator, driver layer |
| **Domain modules** | Entities (Eloquent models for metadata tables), domain rules, repositories, module events. | Infrastructure contracts |
| **Infrastructure** | DB driver layer, dynamic record model, egress gateway, storage + ClamAV, mail/SMS channels, cache, locks, secondary error sink. | Framework / vendor |

Rules enforced by architecture tests (Pest `arch()` in Phase 1):
controllers never touch models of another module directly; only the driver layer
may call `DB::statement` with engine-specific SQL; only `Infrastructure\Egress`
may instantiate an HTTP client; no class outside `Extensions` may load
extension code; `eval`, `create_function`, `unserialize` on untrusted input,
and `exec`-family functions are banned.

## 3. Runtime engine design

The runtime engine turns stored metadata into behavior. It has six parts.

### 3.1 Definition Compiler

Input: a published `form_versions` row (or collection version), its groups,
fields, options, conditions, relations, access rules, justification rules,
statuses, and transitions. Output: an immutable **CompiledDefinition** value
object, serialized to the cache.

```
CompiledDefinition {
  formId, versionId, versionNumber, tableName, rowVersionColumn,
  fields: map<fieldKey, CompiledField{ type, column, dbType, nullable, encrypted,
          sensitive, personal, relation?, options?, validation[], defaultExpr?,
          formulaExpr?, transforms[], mask, conditions[], events[] }>,
  groups: tree<CompiledGroup{ type, children, conditions[], validation[], repeater? }>,
  childTables: map<groupKey, CompiledDefinition>,       // repeaters / inline sub-forms
  relations: map<relationKey, CompiledRelation>,
  conditions: list<CompiledCondition{ id, scope, targetRef, ast, effects[] }>,
  dependencyGraph: { field → dependents }                // for formula/condition recompute
  workflow?: CompiledWorkflow
  justification: list<CompiledJustificationRule>
  accessBaseline: AccessDefaults                        // form/group/field defaults only
}
```

- Cache key: `def:{org}:{formId}:{versionId}`; versions are immutable so entries
  never go stale — only the *pointer* `def:current:{formId}` is invalidated on
  publish/rollback (§8 `FormPublished`).
- The compiler validates the metadata against the JSON schemas of §14 and the
  expression type checker of §15; a definition that does not compile cannot be
  published.
- The same CompiledDefinition (minus server-only data: column names, encryption
  flags, hook bindings) is sent to the client renderer as the **ClientDefinition**,
  with access already resolved for the requesting user (§16), so hidden fields are
  never sent.

### 3.2 Dynamic record model

`DynamicRecord` is a single Eloquent model class whose `$table`, casts, and
fillable set are bound at runtime from a CompiledDefinition
(`DynamicRecord::for($definition)`). It:

- sets `$fillable` to exactly the editable field columns for the *current user*
  (from the Access Resolver) — mass-assignment protection for dynamic models;
- applies casts from field types (encrypted cast for encrypted fields, decimal,
  date, JSON);
- uses the `row_version` column for optimistic concurrency (§19.1);
- uses soft deletes (`deleted_at`, `deleted_by`);
- exposes relations built from `relations` metadata (belongsTo, hasMany,
  belongsToMany through generated pivot tables).

### 3.3 Record Pipeline

Every create/update/delete/restore/transition — whether from the UI, the REST
API, an import, an action step, an automation, an inbound webhook, or an external
form — goes through one pipeline:

```
 0. Journal       → submission_journal row (status=received, payload, correlation_id,
                    idempotency_key)                         [committed before anything else]
 1. Authorize     → Access Resolver: form-level + record-level + status + transition
 2. Load          → current record (if any) + row_version check (optimistic concurrency)
 3. Hook onLoad / beforeValidate                             [Phase 5 extension point]
 4. Normalize     → transforms (trim, case), masks stripped, digits normalized
 5. Compute       → defaults (create), formulas (dependency order), set-value effects
 6. Field access  → drop non-editable keys; reject writes to read-only/hidden fields;
                    required-by-access added to rules
 7. Validate      → field rules, group rules, cross-field, uniqueness, async rules,
                    condition-driven require/block-submit (server evaluation)
 8. Hook afterValidate
 9. Justification → JustificationRule evaluation (§19.3); reject if mandatory and absent
10. Duplicate check → DuplicateRules (warn / block)                  [Phase 4]
11. Transaction BEGIN
      hook beforeSave → write parent row (row_version+1) → write child rows
      → write pivot rows → status history / approvals / assignment
      → audit entries (+ justification) → hook afterSave
      → outbox events (record.saved, status.changed …)
    COMMIT
12. Journal       → status=processed, record_id
13. Dispatch      → after-commit events: notifications, automations, webhooks, SLA timers
```

Failure at any step marks the journal row `failed` with the error, stack trace
(masked), and correlation ID; the record is not partially written because step 11
is one transaction. Retries reuse the `idempotency_key` (§19.6).

### 3.4 Query Planner

Builds list, filter, search, report, and download queries from metadata:
column selections and filters expressed as **relation paths** (§17), the user's
record-level scope (§16.6), sort, and pagination. It produces Query Builder
expressions only (parameterized), chooses joins vs. batched eager loads per path
cardinality, and hands engine-specific fragments (JSON extraction, pagination,
full-text, collation) to the driver layer.

### 3.5 Action & Automation Runner

Executes `actions` (§19.5) and `automations` (§19.9) as ordered step lists:
each step is a registered `StepHandler` (update fields, change status, email,
webhook, document, create linked record, assign, download, wait). Steps run
through the Record Pipeline when they write records, so every rule still applies.
Heavy runs are queued (§8).

### 3.6 Expression Service

Façade over the PHP evaluator: `parse` (text → AST, used only by the builder
API), `check` (type-check an AST against a definition), `evaluate` (AST +
context → value). Contexts are built by the runtime: `record`, `old`, `user`,
`mode`, `status`, `now`, `rows` (repeater), relation-path resolver with
depth/row limits. See [`expression-language.md`](expression-language.md).

### 3.7 Registries (extension points)

| Registry | Registers | Used by |
|---|---|---|
| `FieldTypeRegistry` | input/layout element types: storage mapping, validation, renderer key | builder palette, compiler, renderer |
| `ConditionEffectRegistry` | effects (show/hide, require, set value…) | conditions engine |
| `ExpressionFunctionRegistry` | expression functions (fixed library; extensions cannot add server-side functions without Manage Code + approval) | evaluators |
| `StepHandlerRegistry` | action/automation steps | action runner |
| `TriggerRegistry` | automation triggers | automations |
| `WidgetRegistry` | page/home/dashboard widgets | page renderer |
| `NotificationChannelRegistry` | email, in-app, SMS, messaging | notifications |
| `DataSourceDriverRegistry` | collection, form, REST, DB view | options, lookups, panels |
| `ExportWriterRegistry` | xlsx, csv, pdf, docx | exports, downloads, documents |
| `HookPointRegistry` | onLoad … onAction, custom endpoints | extensions |
| `PermissionCatalog` | system and auto-registered permissions | access |

## 4. Module boundaries

The backend is a **modular monolith**: one Laravel app, modules under
`app/Modules/<Module>` with their own models, services, policies, events,
controllers, routes, migrations, and tests. Cross-module calls go through the
module's public `Contracts\` interfaces or events — never through another
module's models.

| Module | Owns (tables, §10) | Public contracts | Delivered |
|---|---|---|---|
| **Core** | settings, locales, translations, organizations, egress_allowlist, feature_flags, encryption_keys, outbox_events | `Settings`, `Translator`, `TenantContext`, `Clock`, `CorrelationId` | P1 |
| **Identity** | users, sessions, user_preferences, access_policies, impersonation_sessions, password_histories, login_attempts, trusted_devices | `CurrentUser`, `PasswordPolicy`, `SessionManager` | P1 (impersonation, access policies UI P5) |
| **Organization** | departments, department_closure | `DepartmentTree` | P1 |
| **Access** | roles, user_roles, permissions, permission_assignments, field_access_rules, record_access_rules | `AccessResolver`, `PermissionCatalog`, `ExplainAccess` | P1 (system), P2 (form/field), P3 (status/record) |
| **Audit** | audit_logs, audit_chain_heads | `AuditWriter`, `ChainVerifier` | P1 |
| **Monitoring** | error_logs, error_groups | `ErrorReporter` | P1 |
| **Schema** | migration_plans, migration_steps, schema_snapshots, schema_reconciliation_reports, publish_locks | `SchemaManager`, `Introspector`, driver layer | P2 |
| **Forms** | applications, forms, form_versions, collections, field_groups, fields, field_options, conditions, relations, field_templates, menu_items | `DefinitionRepository`, `DefinitionCompiler` | P2 |
| **Expressions** | — (pure) | `ExpressionService` | P2 |
| **Records** | per-form tables, submission_journal, record_comments, files | `RecordPipeline`, `QueryPlanner`, `DynamicRecord` | P2 (P3 views) |
| **Reference** | business_calendars, holidays, number_sequences, currencies, exchange_rates, units_of_measure | `WorkingTimeCalculator`, `NumberGenerator`, `FxConverter` | P2 |
| **Blueprints** | blueprints, blueprint_versions, blueprint_instances | `BlueprintService` | P2 forms and collections; P3 views; library completed P5 |
| **Workflow** | statuses, transitions, status_history, status_mappings, sla_rules, sla_timers | `WorkflowEngine` | P3 |
| **Views** | views, view_columns, filters, saved_views, saved_view_shares, view_panels, reference_previews, print_layouts | `ViewResolver` | P3 |
| **Justification** | justification_rules, justifications, justification_reason_codes, justification_attachments | `JustificationGate` | P3 |
| **Assignment** | assignments, assignment_rules, queues, queue_forms, queue_claims, delegations, approval_requests, approval_decisions | `AssignmentService`, `DelegationResolver` | P3 |
| **Actions** | actions, action_steps, bulk_operations, import_mappings, import_jobs, export_jobs | `ActionRunner` | P4 |
| **Downloads** | download_profiles, download_profile_columns, download_profile_filters, download_schedules, download_jobs | `DownloadEngine` | P4 |
| **Notifications** | notification_rules, email_templates, email_queue, in_app_notifications, notification_channels, notification_deliveries | `Notifier` | P4 (extra channels P5) |
| **Documents** | document_templates | `DocumentRenderer` | P4 |
| **Automation** | automations, automation_triggers, automation_steps, automation_runs, scheduled_tasks | `AutomationEngine` | P4 |
| **DataQuality** | duplicate_rules, merge_history, recycle_bin | `DuplicateDetector`, `MergeService` | P4 |
| **Operations** | operations_alert_rules (reads the submission journal, the email queue, and Laravel failed jobs through their owners' contracts) | `HealthProbe` | P4 |
| **Reports** | reports, dashboards, dashboard_widgets | `ReportEngine` | P5 |
| **Platform** | api_tokens, webhooks, webhook_deliveries, inbound_endpoints, config_packages, environment_drift_reports | `OpenApiGenerator`, `PackageService` | P5 |
| **Extensions** | extensions, extension_versions | `HookRunner` | P5 |
| **Experience** | themes, theme_assets, pages, page_widgets, home_screens, announcements, announcement_dismissals, help_content, tours, tour_progress, search_configs (uses Forms' application records for navigation and theming) | `ThemeCompiler`, `PageRenderer` | P5 |
| **Integrations** | external_data_sources, sync_jobs, sync_runs | `DataSourceDriver`s | P5 |
| **External** | external_forms, external_users, access_tokens, signature_requests, submission_throttles | `ExternalGate` | P5 |
| **Adoption** | usage_metrics | `UsageRecorder` | P5 |
| **Retention** | retention_policies, retention_runs, archived_records, legal_holds, personal_data_requests, storage_quotas, subject_keys | `RetentionEngine`, `PersonalDataLocator` | P6 |

Phase 1 creates *all* tables for modules delivered in P1; each later phase adds
its own module migrations. Tables are never created ahead of the phase that uses
them (no empty schema placeholders), but every table's design is fixed here.

## 5. Folder structure

### 5.1 Repository

```
/backend              Laravel 12 application
/frontend             Vue 3 + TypeScript + Vite SPA
/docker               Dockerfiles (php-fpm, nginx, worker, scheduler), compose files
/.devcontainer        devcontainer.json + compose override (Codespaces)
/.github/workflows    ci.yml, e2e.yml, audit.yml, autobuild.yml
/.github/ISSUE_TEMPLATE, pull_request_template.md
/docs                 specification, architecture, progress, decisions, guides,
                      expression-language, conformance/ (shared corpus)
/extensions           developer extension modules (Git-friendly, §19.13); empty
README.md  CHANGELOG.md  CLAUDE.md  .env.example  .gitignore  .editorconfig
```

### 5.2 Backend (`/backend`)

```
app/
  Kernel/                       bootstrapping, module loader, arch rules
  Http/Middleware/              SecurityHeaders, ContentSecurityPolicy, CorrelationId,
                                SetLocale, ResolveTenant, EnforceAccessPolicy,
                                MaintenanceMode, ImpersonationGuard, SetupLock
  Support/                      Money, LocalizedDate, HijriCalendar, Digits, Result types
  Infrastructure/
    Database/                   Driver layer (§6): Contracts/, MySql/, SqlServer/,
                                SchemaBuilder/, Introspection/, Locks/
    Egress/                     EgressGateway, AddressValidator, PinnedResolver
    Storage/                    FileStore, VirusScanner (ClamAV), SignedUrls
    Logging/                    SecondarySink, Masker
    Cache/                      TaggedCache, VersionedKeys
  Modules/
    <Module>/
      Contracts/                public interfaces consumed by other modules
      Models/                   Eloquent models for this module's metadata tables
      Services/                 use cases
      Runtime/                  (Forms, Records, Workflow…) engine parts
      Policies/                 Laravel policies delegating to AccessResolver
      Http/Controllers/  Http/Requests/  Http/Resources/
      Events/  Listeners/  Jobs/
      Database/Migrations/      module migrations (metadata tables)
      Schemas/                  JSON schemas for this module's metadata (§14)
      routes.php
      ModuleServiceProvider.php
  Expressions/                  Lexer, Parser, TypeChecker, Evaluator, Functions/
config/  database/seeders/ (RolesSeeder, PermissionCatalogSeeder, SettingsSeeder, LocalesSeeder only)
routes/api.php (module route loader)  routes/web.php (SPA entry, signed downloads)
tests/ Unit/ Feature/ Arch/ Conformance/ (reads /docs/conformance/expression-corpus.json)
storage/  (private disk root; never web-served)
```

### 5.3 Frontend (`/frontend`)

```
src/
  app/                router/, stores/ (Pinia), plugins/ (i18n, primevue, echarts), guards/
  api/                typed API client (generated types from OpenAPI), interceptors
                      (CSRF, correlation ID, 409 conflict, 422 validation, 423 locked)
  design-system/      tokens, theme runtime, RTL utilities, base components wrapping PrimeVue
  layout/             AppShell, Sidebar, TopBar, AdminConsoleLayout, ImpersonationBanner
  expressions/        lexer, parser, type checker, evaluator (TS twin of backend)
  runtime/
    form-renderer/    FormRenderer, FieldHost, GroupHost, ConditionRuntime, FormulaRuntime
    fields/           one component per field type (registered in FieldTypeRegistry)
    table/            RecordTable, FilterBuilder, SavedViews, BulkBar, DownloadMenu
    record-view/      ViewPage, panels, timeline, comments, attachments
    pages/            PageRenderer, WidgetHost, widgets/
  builders/
    form-builder/     Palette, Canvas, PropertiesPanel, history (undo/redo), clipboard
    workflow-designer/ (Vue Flow)  view-builder/  download-builder/  rule-builder/
    page-builder/  theme-editor/  automation-builder/  report-builder/  menu-editor/
  modules/            feature areas mirroring backend modules (admin screens)
  locales/            UI chrome strings (ar.json, en.json) — business labels come from API
tests/ unit (Vitest), conformance (reads ../docs/conformance), e2e/ (Playwright)
```

## 6. Database driver layer (MySQL & SQL Server)

All application data access uses Eloquent and the Query Builder. Everything that
differs between engines is behind `Infrastructure\Database\Contracts\DatabaseDriver`,
resolved from the default connection's driver name (`mysql` → `MySqlDriver`,
`sqlsrv` → `SqlServerDriver`). Both implementations are tested in CI against real
engines (service containers).

### 6.1 Contract

```php
interface DatabaseDriver {
  // Type mapping
  public function columnType(LogicalType $t): ColumnSpec;          // §9.2 table
  // DDL
  public function createTable(TableSpec $t): array;                // SQL statements
  public function addColumn(string $table, ColumnSpec $c): array;
  public function renameColumn(string $table, string $from, string $to): array;
  public function alterColumnType(string $table, ColumnSpec $c): array;
  public function addIndex(string $table, IndexSpec $i): array;    // handles filtered unique
  public function dropIndex(string $table, string $name): array;
  public function addForeignKey(string $table, ForeignKeySpec $f): array;
  public function supportsOnlineDdl(DdlOperation $op): OnlineDdlSupport; // ALGORITHM/LOCK, ONLINE=ON
  public function estimateDdlImpact(string $table, DdlOperation $op): DdlImpact;
  public function ddlIsTransactional(): bool;                      // MySQL false, SQL Server true*
  // Introspection
  public function tables(): array; public function columns(string $t): array;
  public function indexes(string $t): array; public function foreignKeys(string $t): array;
  public function tableStats(string $t): TableStats;               // rows, data size
  // JSON
  public function jsonExtract(string $col, string $path): Expression;
  public function jsonContains(string $col, string $path, mixed $v): Expression;
  public function jsonColumnCheck(string $col): ?string;           // ISJSON on SQL Server
  // Query fragments
  public function caseInsensitiveLike(string $col): Expression;    // collation-aware
  public function fullTextMatch(array $cols, string $term): ?Expression; // fallback LIKE
  public function dateTrunc(string $col, string $unit): Expression;
  public function lockForUpdate(Builder $q): Builder;              // FOR UPDATE / UPDLOCK,ROWLOCK
  public function skipLocked(Builder $q): Builder;                 // SKIP LOCKED / READPAST
  // Locks, partitions, backups
  public function namedLock(string $name, int $timeout): bool;     // GET_LOCK / sp_getapplock
  public function releaseNamedLock(string $name): void;
  public function createTimePartitions(string $table, string $col, array $bounds): array;
  public function logicalBackup(array $tables, string $target): BackupResult;
  public function maxIdentifierLength(): int;                      // 64 / 128 → use 60
  public function maxIndexKeyBytes(): int;                         // 3072 / 1700
}
```

### 6.2 Known differences handled

| Concern | MySQL 8 | SQL Server 2019 | Driver strategy |
|---|---|---|---|
| Unicode text | `utf8mb4`, `utf8mb4_0900_ai_ci` | `NVARCHAR`, database collation `Arabic_100_CI_AI_SC` | `string`→`varchar`/`nvarchar`. DB created with case- and accent-insensitive collation on both; binary collation for keys/tokens columns. |
| JSON | native `JSON` | `nvarchar(max)` + `CHECK (ISJSON(col)=1)` | `json` logical type; extraction via `JSON_EXTRACT`/`JSON_VALUE`; indexed JSON paths become generated/computed persisted columns. |
| Boolean | `tinyint(1)` | `bit` | `bool` logical type, cast in model. |
| Datetime | `datetime(6)` | `datetime2(6)` | All stored UTC, `datetime` logical type (no `timestamp` 2038 limit). |
| Unique + NULL | multiple NULLs allowed | one NULL only | Unique indexes on nullable columns emitted as filtered index `WHERE col IS NOT NULL` on SQL Server. |
| DDL transactions | implicit commit | transactional | Never relied upon; every DDL step individually reversible (§12). |
| Multiple cascade paths | allowed | error 1785 | FK graph designed with at most one cascading path per table; other FKs `NO ACTION` with application cascade (§9.4). |
| Identifier length | 64 | 128 | Generated names capped at 60 chars with hashed suffix. |
| Index key size | 3072 bytes | 1700 bytes (nonclustered) | Indexed strings ≤ 255 chars (`nvarchar(255)` = 510 bytes); composite keys checked by compiler. |
| Online DDL | `ALGORITHM=INSTANT/INPLACE, LOCK=NONE` | `ONLINE = ON` (Enterprise) / fallback offline | `supportsOnlineDdl` reports per op; impact analysis shows the result. |
| Named locks | `GET_LOCK` | `sp_getapplock` | Used as a secondary guard to Redis publish locks. |
| Row locking for queues | `FOR UPDATE SKIP LOCKED` | `WITH (UPDLOCK, READPAST, ROWLOCK)` | `skipLocked()` for claim/outbox pollers. |
| Pagination | `LIMIT/OFFSET` | `OFFSET/FETCH` (needs ORDER BY) | Query Builder; planner always adds a deterministic ORDER BY (`id` tiebreaker). Keyset pagination for large exports. |
| Partitioning | `PARTITION BY RANGE COLUMNS` | partition function + scheme | `createTimePartitions`; PK includes partition column (§10.21). |
| Full-text | `FULLTEXT` index | Full-Text catalog (optional) | Global search uses an indexed `search_text` column with LIKE prefix fallback when full-text is unavailable. |
| Backups before destructive steps | `SELECT … INTO` dump via chunked export to file | same | Logical backup = chunked JSONL+schema export to private storage (engine-neutral, restorable to either engine). |

### 6.3 Testing

Every driver method has a contract test run against both engines in CI
(`tests/Database/DriverContractTest.php`). The full suite runs twice per CI job
matrix entry: `DB_CONNECTION=mysql` and `DB_CONNECTION=sqlsrv`.

## 7. Security architecture

### 7.1 Authentication & sessions

- **Sanctum SPA cookie auth** for the SPA (stateful domains, `XSRF-TOKEN`
  CSRF cookie) and **Sanctum personal access tokens** for API clients, with
  abilities (scopes) mapped to permissions; tokens stored hashed (`api_tokens`).
- **Fortify** for login, password reset, password confirmation, and TOTP 2FA
  with recovery codes (encrypted). 2FA is mandatory for any user holding a role
  flagged `requires_2fa` (Super Admin, Admin, Developer by default); login
  completes only after 2FA enrollment.
- **Password policy** (settings): minimum length, character classes, history
  (`password_histories`), expiry, and an offline common-password list shipped with
  the application (no outbound call is made at login — ADR-0012).
- **Lockout**: per-account and per-IP counters in Redis + `login_attempts` table
  for audit; progressive delay then lock for N minutes (settings).
- **Sessions**: database session driver (`sessions` table) so active sessions can
  be listed and revoked; idle timeout and absolute lifetime from access policy;
  concurrent-session cap enforced at login; session ID regenerated on login,
  privilege change, and impersonation start/stop.
- **SSO**: Socialite OIDC/OAuth2 providers configured in settings (client secret
  encrypted); JIT provisioning optional with role mapping from claims.
  **LDAP**: LdapRecord with bind credentials encrypted in settings, group → role
  mapping. Both still subject to 2FA policy unless the IdP asserts MFA (`amr`).
- **Setup lock**: `settings.setup.completed_at`; `SetupLock` middleware returns
  404 for all `/setup/*` routes once set; the flag can only be changed by direct DB
  access.

### 7.2 Authorization

- Every route is behind `auth:sanctum` (except login, setup, public external
  surfaces, signed downloads, inbound webhooks) and a policy check.
- Policies delegate to `AccessResolver` (§16): system permission, form-level,
  status/transition, action, download profile, record-level scope, group/field
  access. Record IDs in URLs are always resolved *through* the record scope query
  (`whereScope($user)`) — an out-of-scope ID returns 404, never 403 (IDOR).
- API Resources serialize only fields the user may see; sensitive fields are
  masked unless `view_sensitive` is granted for that field.
- Mass assignment: dynamic models fill only the access-filtered fillable set;
  metadata models use explicit `$fillable`.
- Deny overrides allow within a tier; more specific tiers override less specific ones; hard deny overrides everything (§16).

### 7.3 Input & output safety

| Threat | Control |
|---|---|
| SQL injection (A03) | Query Builder bindings only; identifiers (table/column names) come from metadata and are validated against `^[a-z][a-z0-9_]{0,59}$` and a reserved-word list, then quoted by the grammar. Visual query builder emits AST, never SQL. |
| Metadata injection | Every metadata write validated against its JSON schema (§14) + semantic checks (references exist, AST type-checks, limits). |
| XSS | Vue escapes by default; `v-html` allowed only in a `SafeHtml` component fed by server-sanitized HTML (HTML Purifier profile per content type: rich text, email template, page content, help). |
| CSP | Strict CSP with per-request nonce: `default-src 'self'; script-src 'self' 'nonce-…'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; img-src 'self' data: blob: <tile server>; connect-src 'self'`; Monaco loaded from self (workers via blob allowed only on code-editor routes). |
| CSRF | Sanctum CSRF cookie + `VerifyCsrfToken`; SameSite=Lax; token API requests are header-authenticated and immune. |
| Headers | HSTS (1y, includeSubDomains), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (camera/geolocation self only). |
| Custom CSS | Parsed with a CSS parser; allowed properties whitelist; no `url()` to external hosts, no `@import`, no `expression`, no `position:fixed` over chrome; scoped under `.app-content` so it cannot hide security banners (§19.11). |
| Spreadsheet / document parsing | PhpSpreadsheet with XXE disabled (`libxml` entity loader off), read-data-only, formulas read as text; DOCX via PhpWord with entity loading disabled; size/row/time limits; background jobs. |
| CSV/Excel injection | Export writers prefix cells starting with `= + - @ \t \r` with `'`. |
| File uploads | Extension + MIME sniff (finfo) + allowlist per field, size limits, image re-encoding to strip payloads, ClamAV scan before the file is linked, private disk outside web root, served only via signed expiring routes after a policy check. |
| SSRF | Single **Egress Gateway** (§7.4). |
| Deserialization | Queue payloads signed (Laravel encrypts/serializes only own classes); no `unserialize` of user input. |
| Expression abuse | Pure language, depth/time/row bounds (§15). |
| Error leakage | Users see message + reference ID; details only in Error Monitoring with masking. |

### 7.4 Egress gateway

`Infrastructure\Egress\EgressGateway` is the **only** class allowed to perform
outbound HTTP (enforced by arch test banning `Http::`, Guzzle, `curl_*`,
`file_get_contents('http…')` elsewhere). Per request:

1. Parse URL; scheme must be `https` (or `http` only if the allowlist entry
   explicitly allows it); no userinfo.
2. Host must match an `egress_allowlist` entry (exact host or `*.suffix`, port list).
3. Resolve DNS (A and AAAA). Every resolved address is checked against blocked
   ranges: `0.0.0.0/8, 10/8, 100.64/10, 127/8, 169.254/16, 172.16/12, 192.0.0/24,
   192.168/16, 198.18/15, 224/4, 240/4, ::/128, ::1/128, ::ffff:0:0/96 (re-checked
   as IPv4), 64:ff9b::/96, fc00::/7, fe80::/10, ff00::/8`, plus metadata endpoints
   (`169.254.169.254`, `fd00:ec2::254`, `metadata.google.internal`).
4. Connect to the validated IP with `CURLOPT_RESOLVE` pinning (SNI/Host header kept)
   so a second DNS answer cannot redirect.
5. Redirects: followed only to the same scheme+host+port, max 3, each re-validated.
6. Timeout (connect 5 s, total configurable ≤ 60 s), response size cap
   (configurable, default 10 MB, streamed), retry with exponential backoff (max 5).
7. Signing: HMAC-SHA256 over `timestamp.body` with the per-webhook secret
   (`X-Signature`, `X-Timestamp`).
8. Logging: `webhook_deliveries` / integration logs with headers masked
   (Authorization, cookies, configured secret headers) and bodies truncated.

### 7.5 Data protection & key management

- Encrypted fields use an envelope scheme: per-organization **data keys**
  (`encryption_keys` table, each wrapped by the app master key `APP_KEY` /
  external KMS key ID), and per-field key reference stored in field metadata.
  Ciphertext format `v1:{keyId}:{base64}` so rotation can re-encrypt lazily and via
  a batch job; documented rotation procedure in Phase 6 guide.
- Secrets (SMTP password, SSO secrets, webhook secrets, data source credentials)
  stored encrypted in their tables; masked in API responses (write-only fields).
- Encrypted columns are not filterable or sortable except for exact-match through a
  keyed blind index column (`<col>__bidx`, HMAC-SHA256) when the admin enables
  "searchable encrypted" on the field.
- Logs: `Masker` removes values of fields flagged sensitive/encrypted, passwords,
  tokens, and configured header names before writing to any sink.

### 7.6 External surfaces

Public forms, external users, and tokenized links use a **separate guard**
(`external`) and separate route group `/x/*` with its own rate limiter, CSP, and
session cookie name. They have no implicit permissions: an external principal
resolves access only through the `external_forms` publication and the
`access_tokens` scope (one record, one action, expiry, single use or N uses,
revocable). See §19.12.

### 7.7 Impersonation

Requires `impersonate_users`; cannot target a user holding any permission the
impersonator lacks (no escalation); cannot target Super Admin unless the actor is
Super Admin; time-limited (`impersonation_sessions.expires_at`); persistent
banner; actions flagged in `settings.security.impersonation_blocked_actions`
denied; every audit entry carries `actor_user_id` (admin) and
`subject_user_id` (impersonated) with `impersonation_session_id`.

### 7.8 OWASP Top 10 (2021) mapping

| Risk | Primary controls (sections) |
|---|---|
| A01 Broken access control | §7.2, §16, record scope 404s, arch tests that every route has a policy |
| A02 Cryptographic failures | TLS + HSTS, §7.5 envelope encryption, hashed tokens, bcrypt/argon2id passwords |
| A03 Injection | §7.3, identifier validation, AST-only expressions |
| A04 Insecure design | journaling, idempotency, optimistic concurrency, egress gateway, approvals for code |
| A05 Security misconfiguration | secure defaults in settings seeder, setup wizard, headers/CSP, debug off in prod check on health page |
| A06 Vulnerable components | composer audit + npm audit in CI, Dependabot |
| A07 Identification & auth failures | §7.1 lockout, 2FA, session management, rate limits |
| A08 Software & data integrity | hash-chained audit, signed webhooks, extension approval workflow, signed queue payloads |
| A09 Logging & monitoring failures | audit log, error monitoring with secondary sink, alerts |
| A10 SSRF | §7.4 |

## 8. Queue, job, and event design

### 8.1 Queues (Horizon + Redis)

| Queue | Workloads | Notes |
|---|---|---|
| `critical` | submission retries, approvals fan-out, publish (schema) jobs | low latency, `tries=3` |
| `default` | notifications dispatch, automations (event-triggered), SLA evaluations | |
| `mail` | `SendEmailJob` per `email_queue` row | rate-limited per SMTP settings |
| `webhooks` | outbound webhook deliveries, integration calls | backoff, egress only |
| `heavy` | imports, exports, custom downloads, document generation, bulk ops, merges, retention runs, reconciliation, logical backups | long timeout, `maxProcesses` capped |
| `scheduled` | jobs dispatched by the scheduler tick | |

Every job implements `CorrelatedJob` (carries correlation ID, organization ID,
acting user, on-behalf-of user) and `TracksProgress` where user-visible
(writes `progress` to its tracking row: download_jobs, import_jobs, bulk_operations…).
Failed jobs go to Laravel `failed_jobs` (+ our tracking row `status=failed`) and
surface in the Operations Center (§19.7). All jobs are idempotent: keyed by the
tracking row id; a retry checks state before acting.

### 8.2 Scheduler

A single `schedule:run` cron entry. Scheduled items are **data** in
`scheduled_tasks` (§10.18): the scheduler tick (every minute) loads due tasks and
dispatches their jobs with an atomic claim (`skipLocked`) so multiple scheduler
containers cannot double-run. System tasks (registered by modules, not editable
business logic): SLA tick, stuck-email detection, schema reconciliation, audit
chain verification, retention runs, exchange-rate refresh, scheduled downloads,
sync jobs, threshold alerts, partition maintenance, recycle-bin purge. Each run
writes `last_run_at`, `last_status`, and an audit entry.

### 8.3 Events

Domain events are PHP classes dispatched by services. Events that cross the
transaction boundary use a **transactional outbox** (`outbox_events` table,
§10.21): written in the same transaction as the change, relayed by a poller to
Laravel events/queues after commit. This guarantees that notifications,
automations, and webhooks fire if and only if the data committed.

| Event | Producer | Consumers |
|---|---|---|
| `RecordCreated/Updated/Deleted/Restored` | Records | Notifications, Automations, Webhooks, Search index, Usage, Duplicate sweep |
| `RecordFieldChanged` (per field diff) | Records | Notifications (field changed), Automations |
| `StatusChanged` | Workflow | Notifications, SLA, Assignment, Automations, Webhooks |
| `TransitionApprovalRequested/Decided` | Assignment | Notifications, queues |
| `RecordAssigned/Claimed/Released` | Assignment | Notifications, My Work cache |
| `FormPublished/RolledBack/Unpublished` | Forms | Definition cache, Access cache, Menus, OpenAPI regen, Blueprints |
| `AccessChanged` (roles, perms, user roles, departments, rules) | Access/Identity | Access cache version bump |
| `SettingsChanged` | Core | Config cache, theme compile |
| `SubmissionFailed`, `JobFailed`, `EmailFailed`, `WebhookFailed` | various | Operations alert evaluator |
| `ConditionBecameTrue` (evaluated on save) | Records | Automations, Notifications |
| `ErrorCaptured` | Monitoring | Error alerts |
| `UserLoggedIn/LoginFailed/LockedOut` | Identity | Audit, alerts |

### 8.4 Correlation

`CorrelationId` middleware accepts a valid inbound `X-Correlation-ID` (UUID) or
generates a ULID; it is placed in the log context, every audit/error/journal row,
every job payload, every outbound email header (`X-Correlation-ID`) and webhook,
and returned in the response header so users can quote it.

## 9. Data model conventions

### 9.1 Naming

- Tables: `snake_case`, plural (`form_versions`). Columns: `snake_case`.
- Physical record tables generated per form: `f_{form_key}` (forms),
  `c_{collection_key}` (collections), child tables `f_{form_key}__{group_key}`,
  pivots `p_{form_key}__{relation_key}`; archived columns renamed
  `zz_{column}_v{version}`; organizations other than the platform one add their
  id (`f{org}_{key}`) (ADR-0028).
  All generated identifiers ≤ 60 chars (hash suffix when truncated). See §11.
- Constraints and indexes: `pk_{table}`, `uq_{table}_{cols}`, `ix_{table}_{cols}`,
  `fk_{table}_{col}`, `ck_{table}_{col}` (JSON checks `ck_{table}_{col}_json`);
  names longer than 60 characters are cut to 51 characters plus `_` and an 8-hex hash.

### 9.2 Logical types (both engines)

| Logical type | MySQL 8 | SQL Server 2019 | Notes |
|---|---|---|---|
| `bigint` / `id` | `BIGINT UNSIGNED` for ids and FKs (`AUTO_INCREMENT` for PK); `BIGINT` for counters/sizes | `BIGINT` (`IDENTITY(1,1)` for PK) | |
| `int` | `INT` | `INT` | |
| `smallint` | `SMALLINT` | `SMALLINT` | small enums by number, orders |
| `bool` | `TINYINT(1)` | `BIT` | |
| `decimal(p,s)` | `DECIMAL(p,s)` | `DECIMAL(p,s)` | money uses `decimal(19,4)`, rates `decimal(20,10)` |
| `string(n)` | `VARCHAR(n)` utf8mb4 | `NVARCHAR(n)` | n ≤ 255 when indexed |
| `code(n)` | `VARCHAR(n) CHARACTER SET ascii COLLATE ascii_bin` | `VARCHAR(n) COLLATE Latin1_General_100_BIN2` | machine keys, tokens (case-sensitive, ASCII) |
| `text` | `TEXT` | `NVARCHAR(MAX)` | |
| `longtext` | `LONGTEXT` | `NVARCHAR(MAX)` | |
| `json` | `JSON` | `NVARCHAR(MAX)` + `CHECK (ISJSON(col)=1)` | never filtered directly; indexed paths → generated/computed columns |
| `uuid` | `CHAR(36) CHARACTER SET ascii COLLATE ascii_bin` | `UNIQUEIDENTIFIER` | Laravel `uuid()`; generated as UUIDv7 (time-ordered) |
| `datetime` | `DATETIME(6)` | `DATETIME2(6)` | always UTC |
| `date` | `DATE` | `DATE` | |
| `time` | `TIME(0)` | `TIME(0)` | |
| `hash` | `CHAR(64) CHARACTER SET ascii COLLATE ascii_bin` | `CHAR(64) COLLATE Latin1_General_100_BIN2` | SHA-256 hex |
| `enum<…>` | `VARCHAR(n) CHARACTER SET ascii COLLATE ascii_bin` + `CHECK (col IN (…))` | `VARCHAR(n) COLLATE Latin1_General_100_BIN2` + `CHECK (col IN (…))` | n = max(32, longest value); PHP backed enum; listed values are exhaustive |

### 9.3 Column mixins

Recurring column sets have names. §10 lists every table **fully expanded**, with
the mixin columns written out as real columns; the names below just explain where
those columns come from:

| Mixin | Columns |
|---|---|
| `@pk` | `id` id PK |
| `@uuid` | `uuid` uuid NOT NULL, **unique** — stable cross-environment identity used by configuration packages, blueprints, drift compare, and public URLs |
| `@org` | `organization_id` bigint NOT NULL FK → `organizations.id` (NO ACTION) |
| `@ts` | `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL |
| `@by` | `created_by` bigint NULL FK → `users.id` (SET NULL is avoided for SQL Server cascade paths: NO ACTION; user rows are never hard-deleted), `updated_by` bigint NULL FK → `users.id` (NO ACTION) |
| `@soft` | `deleted_at` datetime NULL, `deleted_by` bigint NULL FK → `users.id` (NO ACTION); index (`organization_id`, `deleted_at`) |
| `@meta` | `@pk @uuid @org @ts @by` |

**Translatable attributes** are listed per table as *Translatable:* and stored in
`translations` (§10.3) — never as columns.

### 9.4 Foreign keys and deletion

- Metadata rows are archived or soft-deleted, not hard-deleted, once referenced by
  data. FKs default to **NO ACTION** (restrict).
- **CASCADE** is used only for strict composition (a child cannot exist without
  its parent: `field_options` → `fields`, `action_steps` → `actions`, …) and only
  where the table has a single cascading path (SQL Server rule 1785).
- Polymorphic references (`owner_type` + `owner_id`, `subject_type` +
  `subject_id`) have no DB FK; integrity is enforced by the owning service and
  verified by the data-repair tool (§19.10).
- References into generated record tables (`record_id`) carry no DB FK (the
  target table varies by form); the pair (`form_id`, `record_id`) is always
  stored and indexed.

### 9.5 Organizations and the platform root

Every row belongs to an organization. The seeders create one **platform
organization** (`organizations.id = 1`, `is_platform = 1`) that owns the seeded
roles, permission catalog, global settings, and locales. In *single organization*
mode the setup wizard renames it and all business data lives there. In
*multi-organization* mode each tenant is a child organization; platform users
holding Super Admin are the global administrators (ADR-0004).

## 10. ERD — complete metadata schema

Every entity of specification §7 appears below with every column, its exact
MySQL 8 and SQL Server 2019 type, nullability, and default, plus the named primary
key, unique constraints, indexes, foreign keys (with ON DELETE), and CHECK
constraints. Additional supporting tables required by §2–§6 are marked
**(supporting)**. Totals: 147 tables, 2,312 columns, 646 foreign keys, 183 unique
constraints, 643 indexes.

Conventions that apply to every table:

- **MySQL 8:** `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  ROW_FORMAT=DYNAMIC`; CHECK constraints are enforced (8.0.16+). `NO ACTION` behaves
  as `RESTRICT`.
- **SQL Server 2019:** schema `dbo`; database collation `Arabic_100_CI_AI_SC`
  (case- and accent-insensitive, supplementary characters); primary keys are
  clustered; `NVARCHAR` for all user text.
- A unique constraint over a nullable column is a plain unique index on MySQL
  (NULLs never collide) and a **filtered** unique index `WHERE col IS NOT NULL` on
  SQL Server (ADR-0019).
- Every FK column has an index whose leading column is that FK — listed explicitly
  ("supports FK") because SQL Server does not create them automatically.
- Defaults shown as "—" have no database default; the application always supplies
  the value. Timestamps are written by the application in UTC.
- The cascade graph was checked: no table is reachable through more than one
  `CASCADE`/`SET NULL` path and there are no cascade cycles, so every FK is valid on
  SQL Server (error 1785 cannot occur).

### 10.1 Domain overview

```mermaid
erDiagram
  organizations ||--o{ applications : owns
  applications ||--o{ menu_items : has
  applications ||--o{ forms : groups
  forms ||--o{ form_versions : versions
  forms ||--o| collections : "is (kind=collection)"
  forms ||--o{ field_groups : has
  field_groups ||--o{ field_groups : nests
  field_groups ||--o{ fields : contains
  fields ||--o{ field_options : offers
  forms ||--o{ relations : source
  forms ||--o{ statuses : has
  statuses ||--o{ transitions : from
  forms ||--o{ views : has
  forms ||--o{ actions : has
  forms ||--o{ download_profiles : has
  forms ||--o{ notification_rules : has
  forms ||--o{ justification_rules : has
  forms ||--o{ migration_plans : publishes
  users }o--o{ roles : user_roles
  departments ||--o{ users : employs
  permissions ||--o{ permission_assignments : granted
  forms ||--o{ f_record_tables : "generates (A§11)"
```

### 10.2 System & tenancy

```mermaid
erDiagram
  organizations ||--o{ organizations : parent
  organizations ||--o{ settings : has
  organizations ||--o{ egress_allowlist : has
  organizations ||--o{ encryption_keys : has
  organizations ||--o{ config_packages : has
  config_packages }o--o| environment_drift_reports : checked_by
```

**`organizations`** (supporting — tenancy root, §4.27)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `parent_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `organizations.id` NO ACTION; NULL only for the platform org |
| `key` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | unique |
| `is_platform` | TINYINT(1) | BIT | NOT NULL | 0 | exactly one row = 1 |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `active`, `suspended`, `archived` |
| `default_locale` | VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(10) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `timezone` | VARCHAR(64) | NVARCHAR(64) | NOT NULL | — | IANA name |
| `theme_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `themes.id` NO ACTION (default theme) |
| `settings_overrides` | JSON | NVARCHAR(MAX) | NULL | — | org-specific overrides of global settings keys allowed to vary |

- **Primary key:** `pk_organizations` (`id`); SQL Server clustered.
- **Unique:** `uq_organizations_key` (`key`)
- **Unique:** `uq_organizations_uuid` (`uuid`)
- **Index:** `ix_organizations_parent_id` (`parent_id`)
- **Index:** `ix_organizations_created_by` (`created_by`) — supports FK
- **Index:** `ix_organizations_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_organizations_theme_id` (`theme_id`) — supports FK
- **Foreign key:** `fk_organizations_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_organizations_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_organizations_parent_id`: `parent_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_organizations_theme_id`: `theme_id` → `themes`(`id`) ON DELETE NO ACTION
- **Check:** `ck_organizations_status`: `status IN ('active', 'suspended', 'archived')` (both engines)
- **Check (SQL Server):** `ck_organizations_settings_overrides_json`: `ISJSON(settings_overrides) = 1`
- Translatable: `name`.

**`settings`** (§7 System)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `group` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | e.g. `branding`, `mail`, `security`, `formats`, `files`, `sso`, `ldap`, `clamav`, `operations`, `retention`, `setup` |
| `key` | VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(128) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `value` | JSON | NVARCHAR(MAX) | NULL | — | non-secret values, stored as the envelope `{"v": value}` because SQL Server 2019 `ISJSON` rejects JSON scalars |
| `encrypted_value` | TEXT | NVARCHAR(MAX) | NULL | — | secrets (SMTP password, SSO client secret, LDAP bind password) — never returned by the API |
| `is_encrypted` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` |

- **Primary key:** `pk_settings` (`id`); SQL Server clustered.
- **Unique:** `uq_settings_organization_id_group_key` (`organization_id`, `group`, `key`)
- **Index:** `ix_settings_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_settings_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_settings_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_settings_value_json`: `ISJSON(value) = 1`

**`egress_allowlist`** (§7 System, §4.15)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `host_pattern` | VARCHAR(253) | NVARCHAR(253) | NOT NULL | — | exact host or `*.example.com`; IP literals rejected |
| `ports` | JSON | NVARCHAR(MAX) | NOT NULL | — | e.g. `[443]` |
| `allow_http` | TINYINT(1) | BIT | NOT NULL | 0 | default 0 |
| `description` | VARCHAR(255) | NVARCHAR(255) | NULL | — |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_egress_allowlist` (`id`); SQL Server clustered.
- **Unique:** `uq_egress_allowlist_uuid` (`uuid`)
- **Unique:** `uq_egress_allowlist_organization_id_host_pattern` (`organization_id`, `host_pattern`)
- **Index:** `ix_egress_allowlist_created_by` (`created_by`) — supports FK
- **Index:** `ix_egress_allowlist_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_egress_allowlist_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_egress_allowlist_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_egress_allowlist_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_egress_allowlist_ports_json`: `ISJSON(ports) = 1`

**`encryption_keys`** (supporting — §5 key management)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `key_id` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | unique; embedded in ciphertext prefix |
| `wrapped_key` | TEXT | NVARCHAR(MAX) | NOT NULL | — | data key encrypted by the master key / KMS |
| `kms_key_ref` | VARCHAR(255) | NVARCHAR(255) | NULL | — | external KMS key identifier when used |
| `algorithm` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | `aes-256-gcm` |
| `purpose` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `fields`, `files`, `secrets`, `blind_index` |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | one active per (org, purpose); values: `active`, `retiring`, `retired` |
| `rotated_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_encryption_keys` (`id`); SQL Server clustered.
- **Unique:** `uq_encryption_keys_key_id` (`key_id`)
- **Index:** `ix_encryption_keys_organization_id_purpose_status` (`organization_id`, `purpose`, `status`)
- **Foreign key:** `fk_encryption_keys_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Check:** `ck_encryption_keys_purpose`: `purpose IN ('fields', 'files', 'secrets', 'blind_index')` (both engines)
- **Check:** `ck_encryption_keys_status`: `status IN ('active', 'retiring', 'retired')` (both engines)

**`config_packages`** (§7 System, §4.22)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `direction` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `export`, `import` |
| `name` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — |  |
| `manifest` | JSON | NVARCHAR(MAX) | NOT NULL | — | object list: type, uuid, version, hash, dependencies |
| `file_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `files.id` (package archive, signed) |
| `checksum` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `source_environment` | VARCHAR(128) | NVARCHAR(128) | NULL | — |  |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `draft`, `validated`, `conflicts`, `applying`, `applied`, `failed`, `rolled_back` |
| `conflict_report` | JSON | NVARCHAR(MAX) | NULL | — | per object: none / changed-in-target / missing-dependency / drift |
| `resolution` | JSON | NVARCHAR(MAX) | NULL | — | admin choice per conflict: keep target / take package / rename |
| `drift_report_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `environment_drift_reports.id` |
| `applied_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `applied_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` |

- **Primary key:** `pk_config_packages` (`id`); SQL Server clustered.
- **Unique:** `uq_config_packages_uuid` (`uuid`)
- **Index:** `ix_config_packages_organization_id_status` (`organization_id`, `status`)
- **Index:** `ix_config_packages_organization_id_created_at` (`organization_id`, `created_at`)
- **Index:** `ix_config_packages_created_by` (`created_by`) — supports FK
- **Index:** `ix_config_packages_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_config_packages_file_id` (`file_id`) — supports FK
- **Index:** `ix_config_packages_drift_report_id` (`drift_report_id`) — supports FK
- **Index:** `ix_config_packages_applied_by` (`applied_by`) — supports FK
- **Foreign key:** `fk_config_packages_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_config_packages_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_config_packages_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_config_packages_file_id`: `file_id` → `files`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_config_packages_drift_report_id`: `drift_report_id` → `environment_drift_reports`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_config_packages_applied_by`: `applied_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_config_packages_direction`: `direction IN ('export', 'import')` (both engines)
- **Check:** `ck_config_packages_status`: `status IN ('draft', 'validated', 'conflicts', 'applying', 'applied', 'failed', 'rolled_back')` (both engines)
- **Check (SQL Server):** `ck_config_packages_manifest_json`: `ISJSON(manifest) = 1`
- **Check (SQL Server):** `ck_config_packages_conflict_report_json`: `ISJSON(conflict_report) = 1`
- **Check (SQL Server):** `ck_config_packages_resolution_json`: `ISJSON(resolution) = 1`

**`environment_drift_reports`** (supporting — §4.10 compare environments)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `source_label` | VARCHAR(128) | NVARCHAR(128) | NOT NULL | — | environment name of the package / remote snapshot |
| `target_label` | VARCHAR(128) | NVARCHAR(128) | NOT NULL | — |  |
| `source_manifest_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `metadata_differences` | JSON | NVARCHAR(MAX) | NOT NULL | — | per object uuid: added/removed/changed with field-level diff |
| `schema_differences` | JSON | NVARCHAR(MAX) | NOT NULL | — | per physical table: column/index/FK differences |
| `is_ambiguous` | TINYINT(1) | BIT | NOT NULL | 0 | import refused when 1 |
| `conflicting_objects` | JSON | NVARCHAR(MAX) | NULL | — |  |

- **Primary key:** `pk_environment_drift_reports` (`id`); SQL Server clustered.
- **Unique:** `uq_environment_drift_reports_uuid` (`uuid`)
- **Index:** `ix_environment_drift_reports_organization_id_created_at` (`organization_id`, `created_at`)
- **Index:** `ix_environment_drift_reports_created_by` (`created_by`) — supports FK
- **Index:** `ix_environment_drift_reports_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_environment_drift_reports_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_environment_drift_reports_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_environment_drift_reports_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_environment_drift_reports_metadata_differences_json`: `ISJSON(metadata_differences) = 1`
- **Check (SQL Server):** `ck_environment_drift_reports_schema_differences_json`: `ISJSON(schema_differences) = 1`
- **Check (SQL Server):** `ck_environment_drift_reports_conflicting_objects_json`: `ISJSON(conflicting_objects) = 1`

### 10.3 Localization

**`locales`** (§7 Localization)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `code` | VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(10) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | unique, BCP 47 (`ar`, `en`) |
| `native_name` | VARCHAR(64) | NVARCHAR(64) | NOT NULL | — |  |
| `direction` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `ltr`, `rtl` |
| `calendar` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | preference; values: `gregorian`, `hijri`, `both` |
| `digits` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `western`, `arabic_indic` |
| `date_format` | VARCHAR(32) | NVARCHAR(32) | NOT NULL | — |  |
| `time_format` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `12h`, `24h` |
| `number_format` | JSON | NVARCHAR(MAX) | NOT NULL | — | decimal & group separators, grouping |
| `first_day_of_week` | SMALLINT | SMALLINT | NOT NULL | — | 0=Sunday … 6=Saturday |
| `fallback_locale_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `locales.id` NO ACTION |
| `is_enabled` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `is_default` | TINYINT(1) | BIT | NOT NULL | 0 | exactly one |
| `sort_order` | INT | INT | NOT NULL | 0 |  |

- **Primary key:** `pk_locales` (`id`); SQL Server clustered.
- **Unique:** `uq_locales_code` (`code`)
- **Index:** `ix_locales_fallback_locale_id` (`fallback_locale_id`) — supports FK
- **Foreign key:** `fk_locales_fallback_locale_id`: `fallback_locale_id` → `locales`(`id`) ON DELETE NO ACTION
- **Check:** `ck_locales_direction`: `direction IN ('ltr', 'rtl')` (both engines)
- **Check:** `ck_locales_calendar`: `calendar IN ('gregorian', 'hijri', 'both')` (both engines)
- **Check:** `ck_locales_digits`: `digits IN ('western', 'arabic_indic')` (both engines)
- **Check:** `ck_locales_time_format`: `time_format IN ('12h', '24h')` (both engines)
- **Check (SQL Server):** `ck_locales_number_format_json`: `ISJSON(number_format) = 1`
- **Note:** global (platform)

**`translations`** (§7 Localization; §3 Languages)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `object_type` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | morph alias: `form`, `field`, `field_option`, `status`, `ui`, … |
| `object_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | 0 for `ui` strings |
| `field` | VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(191) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | attribute (`label`, `placeholder`, `validation.required`, UI message key) |
| `locale` | VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(10) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | → `locales.code` (FK on code, NO ACTION) |
| `value` | TEXT | NVARCHAR(MAX) | NOT NULL | — |  |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |

- **Primary key:** `pk_translations` (`id`); SQL Server clustered.
- **Unique:** `uq_translations_object_type_object_id_field_locale` (`object_type`, `object_id`, `field`, `locale`)
- **Index:** `ix_translations_organization_id_locale_object_type` (`organization_id`, `locale`, `object_type`)
- **Index:** `ix_translations_locale` (`locale`) — supports FK
- **Index:** `ix_translations_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_translations_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_translations_locale`: `locale` → `locales`(`code`) ON DELETE NO ACTION
- **Foreign key:** `fk_translations_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- Missing rows fall back along `fallback_locale_id` then the default locale; the translation manager lists (object × translatable field × enabled locale) combinations with no row.

### 10.4 Identity & access

```mermaid
erDiagram
  organizations ||--o{ users : has
  departments ||--o{ departments : parent
  departments ||--o{ department_closure : ancestor
  departments ||--o{ users : member
  users ||--o{ user_roles : has
  roles ||--o{ user_roles : granted
  roles }o--o| access_policies : governed_by
  permissions ||--o{ permission_assignments : assigned
  forms ||--o{ record_access_rules : has
  forms ||--o{ field_access_rules : has
  users ||--o{ sessions : has
  users ||--o| user_preferences : has
  users ||--o{ impersonation_sessions : impersonates
```

**`users`** (§7 Access)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `deleted_at` | DATETIME(6) | DATETIME2(6) | NULL | — | soft delete |
| `deleted_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `name` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — |  |
| `email` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — | unique across installation |
| `username` | VARCHAR(128) | NVARCHAR(128) | NULL | — | unique when present (LDAP) |
| `password` | VARCHAR(255) | NVARCHAR(255) | NULL | — | argon2id; NULL for SSO-only |
| `email_verified_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `two_factor_secret` | TEXT | NVARCHAR(MAX) | NULL | — | encrypted |
| `two_factor_recovery_codes` | TEXT | NVARCHAR(MAX) | NULL | — | encrypted |
| `two_factor_confirmed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `department_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `departments.id` NO ACTION |
| `manager_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `job_title` | VARCHAR(255) | NVARCHAR(255) | NULL | — |  |
| `phone` | VARCHAR(32) | NVARCHAR(32) | NULL | — |  |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `pending`, `active`, `suspended`, `disabled` |
| `auth_source` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `local`, `ldap`, `oidc` |
| `external_subject` | VARCHAR(255) | NVARCHAR(255) | NULL | — | IdP subject / LDAP objectGUID |
| `attributes` | JSON | NVARCHAR(MAX) | NULL | — | admin-defined user attributes used by conditions |
| `password_changed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `last_login_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `last_login_ip` | VARCHAR(45) | NVARCHAR(45) | NULL | — |  |
| `failed_login_count` | SMALLINT | SMALLINT | NOT NULL | 0 | default 0 |
| `locked_until` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `remember_token` | VARCHAR(100) | NVARCHAR(100) | NULL | — |  |
| `anonymized_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_users` (`id`); SQL Server clustered.
- **Unique:** `uq_users_uuid` (`uuid`)
- **Unique:** `uq_users_email` (`email`)
- **Unique:** `uq_users_username` (`username`) — SQL Server: filtered `WHERE username IS NOT NULL`; MySQL: unique (NULLs never collide)
- **Unique:** `uq_users_auth_source_external_subject` (`auth_source`, `external_subject`) — SQL Server: filtered `WHERE external_subject IS NOT NULL`; MySQL: unique (NULLs never collide)
- **Index:** `ix_users_organization_id_department_id` (`organization_id`, `department_id`)
- **Index:** `ix_users_manager_id` (`manager_id`)
- **Index:** `ix_users_organization_id_deleted_at` (`organization_id`, `deleted_at`)
- **Index:** `ix_users_created_by` (`created_by`) — supports FK
- **Index:** `ix_users_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_users_deleted_by` (`deleted_by`) — supports FK
- **Index:** `ix_users_department_id` (`department_id`) — supports FK
- **Foreign key:** `fk_users_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_users_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_users_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_users_deleted_by`: `deleted_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_users_department_id`: `department_id` → `departments`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_users_manager_id`: `manager_id` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_users_status`: `status IN ('pending', 'active', 'suspended', 'disabled')` (both engines)
- **Check:** `ck_users_auth_source`: `auth_source IN ('local', 'ldap', 'oidc')` (both engines)
- **Check (SQL Server):** `ck_users_attributes_json`: `ISJSON(attributes) = 1`
- **Note:** users are never hard-deleted; anonymization (§4.26) overwrites PII

**`departments`** (§7 Access)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `deleted_at` | DATETIME(6) | DATETIME2(6) | NULL | — | soft delete |
| `deleted_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `parent_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `departments.id` NO ACTION |
| `code` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | unique per org |
| `manager_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `business_calendar_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `business_calendars.id` NO ACTION |
| `depth` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_departments` (`id`); SQL Server clustered.
- **Unique:** `uq_departments_uuid` (`uuid`)
- **Unique:** `uq_departments_organization_id_code` (`organization_id`, `code`)
- **Index:** `ix_departments_parent_id` (`parent_id`)
- **Index:** `ix_departments_organization_id_deleted_at` (`organization_id`, `deleted_at`)
- **Index:** `ix_departments_created_by` (`created_by`) — supports FK
- **Index:** `ix_departments_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_departments_deleted_by` (`deleted_by`) — supports FK
- **Index:** `ix_departments_manager_user_id` (`manager_user_id`) — supports FK
- **Index:** `ix_departments_business_calendar_id` (`business_calendar_id`) — supports FK
- **Foreign key:** `fk_departments_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_departments_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_departments_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_departments_deleted_by`: `deleted_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_departments_parent_id`: `parent_id` → `departments`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_departments_manager_user_id`: `manager_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_departments_business_calendar_id`: `business_calendar_id` → `business_calendars`(`id`) ON DELETE NO ACTION
- Translatable: `name`.

**`department_closure`** (supporting — department tree queries)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `ancestor_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `departments.id` CASCADE |
| `descendant_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `departments.id` NO ACTION (single cascade path) |
| `depth` | SMALLINT | SMALLINT | NOT NULL | 0 | 0 = self |

- **Primary key:** `pk_department_closure` (`ancestor_id`, `descendant_id`); SQL Server clustered.
- **Index:** `ix_department_closure_descendant_id_depth` (`descendant_id`, `depth`)
- **Foreign key:** `fk_department_closure_ancestor_id`: `ancestor_id` → `departments`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_department_closure_descendant_id`: `descendant_id` → `departments`(`id`) ON DELETE NO ACTION

**`roles`** (§7 Access)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `key` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `is_system` | TINYINT(1) | BIT | NOT NULL | 0 | system roles cannot be deleted or renamed by key |
| `audience` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | external roles serve external users (§4.32); values: `internal`, `external` |
| `application_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `applications.id` NO ACTION — application-scoped role |
| `requires_2fa` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `is_admin_role` | TINYINT(1) | BIT | NOT NULL | 0 | stricter access policy applies |
| `access_policy_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `access_policies.id` NO ACTION |
| `sort_order` | INT | INT | NOT NULL | 0 |  |

- **Primary key:** `pk_roles` (`id`); SQL Server clustered.
- **Unique:** `uq_roles_uuid` (`uuid`)
- **Unique:** `uq_roles_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_roles_created_by` (`created_by`) — supports FK
- **Index:** `ix_roles_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_roles_application_id` (`application_id`) — supports FK
- **Index:** `ix_roles_access_policy_id` (`access_policy_id`) — supports FK
- **Foreign key:** `fk_roles_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_roles_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_roles_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_roles_application_id`: `application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_roles_access_policy_id`: `access_policy_id` → `access_policies`(`id`) ON DELETE NO ACTION
- **Check:** `ck_roles_audience`: `audience IN ('internal', 'external')` (both engines)
- **Note:** seeded: `super_admin`, `admin`, `developer`, `user` on the platform org
- Translatable: `name`, `description`.

**`user_roles`** (§7 Access)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` CASCADE |
| `role_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `roles.id` NO ACTION |
| `valid_from` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `valid_until` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `assigned_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |

- **Primary key:** `pk_user_roles` (`id`); SQL Server clustered.
- **Unique:** `uq_user_roles_user_id_role_id` (`user_id`, `role_id`)
- **Index:** `ix_user_roles_role_id` (`role_id`)
- **Index:** `ix_user_roles_assigned_by` (`assigned_by`) — supports FK
- **Foreign key:** `fk_user_roles_user_id`: `user_id` → `users`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_user_roles_role_id`: `role_id` → `roles`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_user_roles_assigned_by`: `assigned_by` → `users`(`id`) ON DELETE NO ACTION

**`permissions`** (§7 Access) — the catalog; seeded system rows + auto-registered rows

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `key` | VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(191) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | `system.manage_forms`, `form.{uuid}.export`, `action.{uuid}.run`, `download.{uuid}.use`, `menu.{uuid}.view`, `transition.{uuid}.perform`, `app.{uuid}.access`, `page.{uuid}.view`, `report.{uuid}.view`, `dashboard.{uuid}.view`, `view.{uuid}.use`, `field.{uuid}.view_sensitive` |
| `scope_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `system`, `application`, `form`, `action`, `download_profile`, `menu_item`, `transition`, `page`, `report`, `dashboard`, `view`, `field` |
| `scope_id` | BIGINT UNSIGNED | BIGINT | NULL | — | id of the scoped object (polymorphic) |
| `ability` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | `view`, `create`, `edit`, `delete`, `restore`, `export`, `import`, `print`, `view_log`, `run`, `use`, `perform`, `access`, `view_sensitive`, or system ability |
| `category` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | grouping for the UI |
| `is_system` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `is_dangerous` | TINYINT(1) | BIT | NOT NULL | 0 | requires 2FA re-confirmation to grant |

- **Primary key:** `pk_permissions` (`id`); SQL Server clustered.
- **Unique:** `uq_permissions_key` (`key`)
- **Index:** `ix_permissions_scope_type_scope_id` (`scope_type`, `scope_id`)
- **Index:** `ix_permissions_organization_id` (`organization_id`) — supports FK
- **Foreign key:** `fk_permissions_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Check:** `ck_permissions_scope_type`: `scope_type IN ('system', 'application', 'form', 'action', 'download_profile', 'menu_item', 'transition', 'page', 'report', 'dashboard', 'view', 'field')` (both engines)
- Translatable: `label`, `description`.

**`permission_assignments`** (§7 Access)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `permission_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `permissions.id` CASCADE |
| `subject_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `role`, `user`, `department` |
| `subject_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `effect` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | §16.2; `hard_deny` is a dangerous grant (2FA re-confirmation, lockout guard); values: `allow`, `deny`, `hard_deny` |
| `include_descendants` | TINYINT(1) | BIT | NOT NULL | 0 | department subject applies to sub-departments |
| `condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION (custom condition) |
| `valid_until` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `granted_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |

- **Primary key:** `pk_permission_assignments` (`id`); SQL Server clustered.
- **Unique:** `uq_permission_assignments_permission_id_subject_typ_fc5e337d` (`permission_id`, `subject_type`, `subject_id`)
- **Index:** `ix_permission_assignments_subject_type_subject_id` (`subject_type`, `subject_id`)
- **Index:** `ix_permission_assignments_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_permission_assignments_condition_id` (`condition_id`) — supports FK
- **Index:** `ix_permission_assignments_granted_by` (`granted_by`) — supports FK
- **Foreign key:** `fk_permission_assignments_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_permission_assignments_permission_id`: `permission_id` → `permissions`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_permission_assignments_condition_id`: `condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_permission_assignments_granted_by`: `granted_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_permission_assignments_subject_type`: `subject_type IN ('role', 'user', 'department')` (both engines)
- **Check:** `ck_permission_assignments_effect`: `effect IN ('allow', 'deny', 'hard_deny')` (both engines)

**`field_access_rules`** (§7 Structure — sparse overrides, §16)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `target_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `form`, `group`, `field` |
| `group_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `field_groups.id` NO ACTION |
| `field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION |
| `subject_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `everyone`, `role`, `department`, `user` |
| `subject_id` | BIGINT UNSIGNED | BIGINT | NULL | — | NULL for `everyone` |
| `status_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `statuses.id` NO ACTION; NULL = any status |
| `mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | NULL = any mode; values: `create`, `edit`, `view`, `print` |
| `access` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `hidden`, `read_only`, `editable`, `required` |
| `effect` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | `allow` sets the level; `deny` caps it within its tier; `hard_deny` caps it at every tier (§16.3); values: `allow`, `deny`, `hard_deny` |
| `rule_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | SHA-256 of (`form_id`, `target_type`, `group_id`, `field_id`, `subject_type`, `subject_id`, `status_id`, `mode`) — nullable-safe uniqueness on both engines; one rule per coordinate |

- **Primary key:** `pk_field_access_rules` (`id`); SQL Server clustered.
- **Unique:** `uq_field_access_rules_uuid` (`uuid`)
- **Unique:** `uq_field_access_rules_rule_hash` (`rule_hash`)
- **Index:** `ix_field_access_rules_form_id_target_type_group_id_field_id` (`form_id`, `target_type`, `group_id`, `field_id`)
- **Index:** `ix_field_access_rules_form_id_subject_type_subject_id` (`form_id`, `subject_type`, `subject_id`)
- **Index:** `ix_field_access_rules_form_id_status_id_mode` (`form_id`, `status_id`, `mode`)
- **Index:** `ix_field_access_rules_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_field_access_rules_created_by` (`created_by`) — supports FK
- **Index:** `ix_field_access_rules_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_field_access_rules_group_id` (`group_id`) — supports FK
- **Index:** `ix_field_access_rules_field_id` (`field_id`) — supports FK
- **Index:** `ix_field_access_rules_status_id` (`status_id`) — supports FK
- **Foreign key:** `fk_field_access_rules_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_access_rules_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_access_rules_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_access_rules_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_field_access_rules_group_id`: `group_id` → `field_groups`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_access_rules_field_id`: `field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_access_rules_status_id`: `status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Check:** `ck_field_access_rules_target_type`: `target_type IN ('form', 'group', 'field')` (both engines)
- **Check:** `ck_field_access_rules_subject_type`: `subject_type IN ('everyone', 'role', 'department', 'user')` (both engines)
- **Check:** `ck_field_access_rules_mode`: `mode IN ('create', 'edit', 'view', 'print')` (both engines)
- **Check:** `ck_field_access_rules_access`: `access IN ('hidden', 'read_only', 'editable', 'required')` (both engines)
- **Check:** `ck_field_access_rules_effect`: `effect IN ('allow', 'deny', 'hard_deny')` (both engines)

**`record_access_rules`** (§7 Access)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `subject_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `everyone`, `role`, `department`, `user` |
| `subject_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `operation` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `view`, `edit`, `delete`, `all` |
| `scope` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `none`, `own`, `own_department`, `department_tree`, `assigned`, `all`, `custom` |
| `condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION; for `custom` |
| `effect` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | §16.6; values: `allow`, `deny`, `hard_deny` |
| `priority` | INT | INT | NOT NULL | 0 | display ordering only; resolution per §16.6 |

- **Primary key:** `pk_record_access_rules` (`id`); SQL Server clustered.
- **Unique:** `uq_record_access_rules_uuid` (`uuid`)
- **Index:** `ix_record_access_rules_form_id_subject_type_subject_5f002f5e` (`form_id`, `subject_type`, `subject_id`, `operation`)
- **Index:** `ix_record_access_rules_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_record_access_rules_created_by` (`created_by`) — supports FK
- **Index:** `ix_record_access_rules_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_record_access_rules_condition_id` (`condition_id`) — supports FK
- **Foreign key:** `fk_record_access_rules_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_record_access_rules_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_record_access_rules_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_record_access_rules_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_record_access_rules_condition_id`: `condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Check:** `ck_record_access_rules_subject_type`: `subject_type IN ('everyone', 'role', 'department', 'user')` (both engines)
- **Check:** `ck_record_access_rules_operation`: `operation IN ('view', 'edit', 'delete', 'all')` (both engines)
- **Check:** `ck_record_access_rules_scope`: `scope IN ('none', 'own', 'own_department', 'department_tree', 'assigned', 'all', 'custom')` (both engines)
- **Check:** `ck_record_access_rules_effect`: `effect IN ('allow', 'deny', 'hard_deny')` (both engines)

**`sessions`** (§7 Access)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(128) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | PK (session ID) |
| `user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` CASCADE |
| `external_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `external_users.id` NO ACTION |
| `guard` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `web`, `external` |
| `ip_address` | VARCHAR(45) | NVARCHAR(45) | NULL | — |  |
| `user_agent` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `payload` | LONGTEXT | NVARCHAR(MAX) | NOT NULL | — | encrypted session data |
| `last_activity` | INT | INT | NOT NULL | — | unix time |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `absolute_expires_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `two_factor_passed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `trusted_device_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `trusted_devices.id` NO ACTION |
| `impersonation_session_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `impersonation_sessions.id` NO ACTION |

- **Primary key:** `pk_sessions` (`id`); SQL Server clustered.
- **Index:** `ix_sessions_user_id` (`user_id`)
- **Index:** `ix_sessions_last_activity` (`last_activity`)
- **Index:** `ix_sessions_external_user_id` (`external_user_id`)
- **Index:** `ix_sessions_trusted_device_id` (`trusted_device_id`) — supports FK
- **Index:** `ix_sessions_impersonation_session_id` (`impersonation_session_id`) — supports FK
- **Foreign key:** `fk_sessions_user_id`: `user_id` → `users`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_sessions_external_user_id`: `external_user_id` → `external_users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sessions_trusted_device_id`: `trusted_device_id` → `trusted_devices`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sessions_impersonation_session_id`: `impersonation_session_id` → `impersonation_sessions`(`id`) ON DELETE NO ACTION
- **Check:** `ck_sessions_guard`: `guard IN ('web', 'external')` (both engines)

**`password_histories`** (supporting)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` CASCADE |
| `password_hash` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — |  |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |

- **Primary key:** `pk_password_histories` (`id`); SQL Server clustered.
- **Index:** `ix_password_histories_user_id_created_at` (`user_id`, `created_at`)
- **Foreign key:** `fk_password_histories_user_id`: `user_id` → `users`(`id`) ON DELETE CASCADE

**`login_attempts`** (supporting — lockout evidence and alerts)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `identifier_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | HMAC of the submitted login (no plaintext) |
| `user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `guard` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `web`, `external`, `api` |
| `ip_address` | VARCHAR(45) | NVARCHAR(45) | NOT NULL | — |  |
| `user_agent` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `successful` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `failure_reason` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | `bad_password`, `locked`, `2fa_failed`, `policy_ip`, `policy_time`, … |
| `attempted_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |

- **Primary key:** `pk_login_attempts` (`id`); SQL Server clustered.
- **Index:** `ix_login_attempts_identifier_hash_attempted_at` (`identifier_hash`, `attempted_at`)
- **Index:** `ix_login_attempts_ip_address_attempted_at` (`ip_address`, `attempted_at`)
- **Index:** `ix_login_attempts_organization_id_attempted_at` (`organization_id`, `attempted_at`)
- **Index:** `ix_login_attempts_user_id` (`user_id`) — supports FK
- **Foreign key:** `fk_login_attempts_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_login_attempts_user_id`: `user_id` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_login_attempts_guard`: `guard IN ('web', 'external', 'api')` (both engines)

**`trusted_devices`** (supporting — §4.36 device trust)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` CASCADE |
| `device_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | HMAC of device cookie value |
| `label` | VARCHAR(255) | NVARCHAR(255) | NULL | — | derived from user agent |
| `last_used_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `expires_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `revoked_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_trusted_devices` (`id`); SQL Server clustered.
- **Unique:** `uq_trusted_devices_user_id_device_hash` (`user_id`, `device_hash`)
- **Foreign key:** `fk_trusted_devices_user_id`: `user_id` → `users`(`id`) ON DELETE CASCADE

### 10.5 Structure (applications, forms, fields)

```mermaid
erDiagram
  applications ||--o{ menu_items : has
  menu_items ||--o{ menu_items : parent
  applications ||--o{ forms : contains
  forms ||--o| collections : extends
  forms ||--o{ form_versions : has
  forms ||--o{ field_groups : has
  field_groups ||--o{ field_groups : parent
  field_groups ||--o{ fields : contains
  fields ||--o{ field_options : has
  fields }o--o| relations : binds
  relations }o--|| forms : target
  conditions }o--|| forms : scoped_to
  field_templates }o--o| applications : library
```

**`applications`** (§7 Structure, §4.27)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `deleted_at` | DATETIME(6) | DATETIME2(6) | NULL | — | soft delete |
| `deleted_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `key` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `icon` | VARCHAR(64) | NVARCHAR(64) | NULL | — |  |
| `color` | VARCHAR(16) | NVARCHAR(16) | NULL | — |  |
| `theme_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `themes.id` NO ACTION |
| `home_screen_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `home_screens.id` NO ACTION (default) |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `active`, `archived`, `retired` |
| `data_sharing_default` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | default for new forms; values: `shared`, `isolated` |
| `maintenance_mode` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `maintenance_until` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `settings` | JSON | NVARCHAR(MAX) | NULL | — | per-application setting overrides |
| `sort_order` | INT | INT | NOT NULL | 0 |  |

- **Primary key:** `pk_applications` (`id`); SQL Server clustered.
- **Unique:** `uq_applications_uuid` (`uuid`)
- **Unique:** `uq_applications_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_applications_organization_id_deleted_at` (`organization_id`, `deleted_at`)
- **Index:** `ix_applications_created_by` (`created_by`) — supports FK
- **Index:** `ix_applications_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_applications_deleted_by` (`deleted_by`) — supports FK
- **Index:** `ix_applications_theme_id` (`theme_id`) — supports FK
- **Index:** `ix_applications_home_screen_id` (`home_screen_id`) — supports FK
- **Foreign key:** `fk_applications_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_applications_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_applications_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_applications_deleted_by`: `deleted_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_applications_theme_id`: `theme_id` → `themes`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_applications_home_screen_id`: `home_screen_id` → `home_screens`(`id`) ON DELETE NO ACTION
- **Check:** `ck_applications_status`: `status IN ('active', 'archived', 'retired')` (both engines)
- **Check:** `ck_applications_data_sharing_default`: `data_sharing_default IN ('shared', 'isolated')` (both engines)
- **Check (SQL Server):** `ck_applications_settings_json`: `ISJSON(settings) = 1`
- Translatable: `name`, `description`, `maintenance_message`. Access: permission `app.{uuid}.access` (auto-registered) granted to roles/departments enables it.

**`menu_items`** (§7 Structure, §4.13, §4.29)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `application_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `applications.id` CASCADE |
| `parent_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `menu_items.id` NO ACTION |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `form`, `collection`, `page`, `report`, `dashboard`, `my_work`, `link`, `separator`, `header` |
| `target_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | morph alias |
| `target_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `url` | VARCHAR(2048) | NVARCHAR(2048) | NULL | — | for `link` (validated `https://` or app-relative) |
| `open_in_new_tab` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `icon` | VARCHAR(64) | NVARCHAR(64) | NULL | — |  |
| `badge` | JSON | NVARCHAR(MAX) | NULL | — | `{source:"my_work"\|"query", form_uuid, filter AST}` live count |
| `visibility_condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_menu_items` (`id`); SQL Server clustered.
- **Unique:** `uq_menu_items_uuid` (`uuid`)
- **Index:** `ix_menu_items_application_id_parent_id_sort_order` (`application_id`, `parent_id`, `sort_order`)
- **Index:** `ix_menu_items_target_type_target_id` (`target_type`, `target_id`)
- **Index:** `ix_menu_items_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_menu_items_created_by` (`created_by`) — supports FK
- **Index:** `ix_menu_items_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_menu_items_parent_id` (`parent_id`) — supports FK
- **Index:** `ix_menu_items_visibility_condition_id` (`visibility_condition_id`) — supports FK
- **Foreign key:** `fk_menu_items_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_menu_items_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_menu_items_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_menu_items_application_id`: `application_id` → `applications`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_menu_items_parent_id`: `parent_id` → `menu_items`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_menu_items_visibility_condition_id`: `visibility_condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Check:** `ck_menu_items_type`: `type IN ('form', 'collection', 'page', 'report', 'dashboard', 'my_work', 'link', 'separator', 'header')` (both engines)
- **Check (SQL Server):** `ck_menu_items_badge_json`: `ISJSON(badge) = 1`
- Translatable: `label`. Visibility by role/department/user via permission `menu.{uuid}.view`.

**`forms`** (§7 Structure)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `deleted_at` | DATETIME(6) | DATETIME2(6) | NULL | — | soft delete |
| `deleted_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `application_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `applications.id` NO ACTION |
| `kind` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | collections use the same engine (§4.8); values: `form`, `collection` |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | immutable after first publish; drives table name |
| `table_name` | VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(60) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | `f_{key}` / `c_{key}` or a bound existing table |
| `binding_mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | `bound` = existing table via introspection; values: `managed`, `bound` |
| `state` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `draft`, `published`, `unpublished`, `archived`, `schema_inconsistent` |
| `current_version_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `form_versions.id` NO ACTION (published) |
| `draft_version_number` | INT | INT | NOT NULL | — | next version number |
| `draft_updated_at` | DATETIME(6) | DATETIME2(6) | NULL | — | autosave timestamp |
| `draft_updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `icon` | VARCHAR(64) | NVARCHAR(64) | NULL | — |  |
| `data_sharing` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | §4.27; values: `shared`, `isolated` |
| `workflow_enabled` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `numbering_sequence_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `number_sequences.id` NO ACTION (record number) |
| `business_calendar_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `business_calendars.id` NO ACTION |
| `title_template` | JSON | NVARCHAR(MAX) | NULL | — | expression AST producing the record title |
| `settings` | JSON | NVARCHAR(MAX) | NOT NULL | — | §14.2 form settings (autosave, conflict UI, print, comments, attachments, etc.) |
| `blueprint_instance_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `blueprint_instances.id` NO ACTION |
| `record_count_cache` | BIGINT | BIGINT | NOT NULL | 0 | admin record counts (refreshed by job) |

- **Primary key:** `pk_forms` (`id`); SQL Server clustered.
- **Unique:** `uq_forms_uuid` (`uuid`)
- **Unique:** `uq_forms_organization_id_key` (`organization_id`, `key`)
- **Unique:** `uq_forms_organization_id_table_name` (`organization_id`, `table_name`)
- **Index:** `ix_forms_application_id_state` (`application_id`, `state`)
- **Index:** `ix_forms_organization_id_deleted_at` (`organization_id`, `deleted_at`)
- **Index:** `ix_forms_created_by` (`created_by`) — supports FK
- **Index:** `ix_forms_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_forms_deleted_by` (`deleted_by`) — supports FK
- **Index:** `ix_forms_current_version_id` (`current_version_id`) — supports FK
- **Index:** `ix_forms_draft_updated_by` (`draft_updated_by`) — supports FK
- **Index:** `ix_forms_numbering_sequence_id` (`numbering_sequence_id`) — supports FK
- **Index:** `ix_forms_business_calendar_id` (`business_calendar_id`) — supports FK
- **Index:** `ix_forms_blueprint_instance_id` (`blueprint_instance_id`) — supports FK
- **Foreign key:** `fk_forms_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_forms_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_forms_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_forms_deleted_by`: `deleted_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_forms_application_id`: `application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_forms_current_version_id`: `current_version_id` → `form_versions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_forms_draft_updated_by`: `draft_updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_forms_numbering_sequence_id`: `numbering_sequence_id` → `number_sequences`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_forms_business_calendar_id`: `business_calendar_id` → `business_calendars`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_forms_blueprint_instance_id`: `blueprint_instance_id` → `blueprint_instances`(`id`) ON DELETE NO ACTION
- **Check:** `ck_forms_kind`: `kind IN ('form', 'collection')` (both engines)
- **Check:** `ck_forms_binding_mode`: `binding_mode IN ('managed', 'bound')` (both engines)
- **Check:** `ck_forms_state`: `state IN ('draft', 'published', 'unpublished', 'archived', 'schema_inconsistent')` (both engines)
- **Check:** `ck_forms_data_sharing`: `data_sharing IN ('shared', 'isolated')` (both engines)
- **Check (SQL Server):** `ck_forms_title_template_json`: `ISJSON(title_template) = 1`
- **Check (SQL Server):** `ck_forms_settings_json`: `ISJSON(settings) = 1`
- Translatable: `name`, `description`.

**`collections`** (§7 Structure, §4.8)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE; unique (1:1 with a `kind=collection` form) |
| `collection_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `key_value`, `table` |
| `is_shared_reference` | TINYINT(1) | BIT | NOT NULL | 0 | §4.34 shared reference collection |
| `owner_application_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `applications.id` NO ACTION; others read-only |
| `value_field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION (default option value) |
| `label_field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION (default option label) |
| `parent_field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION (cascading/hierarchical lists) |

- **Primary key:** `pk_collections` (`id`); SQL Server clustered.
- **Unique:** `uq_collections_form_id` (`form_id`)
- **Index:** `ix_collections_owner_application_id` (`owner_application_id`) — supports FK
- **Index:** `ix_collections_value_field_id` (`value_field_id`) — supports FK
- **Index:** `ix_collections_label_field_id` (`label_field_id`) — supports FK
- **Index:** `ix_collections_parent_field_id` (`parent_field_id`) — supports FK
- **Foreign key:** `fk_collections_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_collections_owner_application_id`: `owner_application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_collections_value_field_id`: `value_field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_collections_label_field_id`: `label_field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_collections_parent_field_id`: `parent_field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Check:** `ck_collections_collection_type`: `collection_type IN ('key_value', 'table')` (both engines)

**`form_versions`** (§7 Structure, §4.10)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `version_number` | INT | INT | NOT NULL | — |  |
| `state` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | drafts live in the working tables; values: `published`, `superseded`, `rolled_back` |
| `definition` | JSON | NVARCHAR(MAX) | NOT NULL | — | full snapshot: form, groups, fields, options, conditions, relations, access rules, justification rules, statuses, transitions (§14) |
| `definition_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `schema_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | hash of the physical-schema-relevant subset |
| `change_class` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | rollback class (§13.4); values: `metadata_only`, `additive_schema`, `destructive` |
| `diff_from_previous` | JSON | NVARCHAR(MAX) | NULL | — | structured diff for the visual diff screen |
| `impact_report` | JSON | NVARCHAR(MAX) | NULL | — | impact analysis shown before publish |
| `migration_plan_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `migration_plans.id` NO ACTION |
| `snapshot_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `schema_snapshots.id` NO ACTION (pre-publish) |
| `rollback_of_version_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `form_versions.id` NO ACTION |
| `published_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `published_by` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `change_note` | TEXT | NVARCHAR(MAX) | NULL | — |  |

- **Primary key:** `pk_form_versions` (`id`); SQL Server clustered.
- **Unique:** `uq_form_versions_form_id_version_number` (`form_id`, `version_number`)
- **Unique:** `uq_form_versions_uuid` (`uuid`)
- **Index:** `ix_form_versions_form_id_state` (`form_id`, `state`)
- **Index:** `ix_form_versions_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_form_versions_created_by` (`created_by`) — supports FK
- **Index:** `ix_form_versions_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_form_versions_migration_plan_id` (`migration_plan_id`) — supports FK
- **Index:** `ix_form_versions_snapshot_id` (`snapshot_id`) — supports FK
- **Index:** `ix_form_versions_rollback_of_version_id` (`rollback_of_version_id`) — supports FK
- **Index:** `ix_form_versions_published_by` (`published_by`) — supports FK
- **Foreign key:** `fk_form_versions_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_form_versions_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_form_versions_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_form_versions_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_form_versions_migration_plan_id`: `migration_plan_id` → `migration_plans`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_form_versions_snapshot_id`: `snapshot_id` → `schema_snapshots`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_form_versions_rollback_of_version_id`: `rollback_of_version_id` → `form_versions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_form_versions_published_by`: `published_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_form_versions_state`: `state IN ('published', 'superseded', 'rolled_back')` (both engines)
- **Check:** `ck_form_versions_change_class`: `change_class IN ('metadata_only', 'additive_schema', 'destructive')` (both engines)
- **Check (SQL Server):** `ck_form_versions_definition_json`: `ISJSON(definition) = 1`
- **Check (SQL Server):** `ck_form_versions_diff_from_previous_json`: `ISJSON(diff_from_previous) = 1`
- **Check (SQL Server):** `ck_form_versions_impact_report_json`: `ISJSON(impact_report) = 1`

**`field_groups`** (§7 Structure, §4.5)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `parent_group_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `field_groups.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | unique per form |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `section`, `fieldset`, `card`, `tabs`, `tab`, `wizard`, `step`, `row`, `column`, `panel`, `accordion`, `repeater`, `subform` |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `layout` | JSON | NVARCHAR(MAX) | NOT NULL | — | columns per breakpoint, column span (for `column`), spacing, border, background, css_class, icon |
| `collapsible` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `default_state` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `open`, `closed` |
| `validation` | JSON | NVARCHAR(MAX) | NULL | — | `{min_filled, rules:[{ast, message_key}]}` |
| `repeater` | JSON | NVARCHAR(MAX) | NULL | — | `{min_rows, max_rows, default_rows, display:"table"\|"cards", aggregates:[…], row_permissions:{add,remove,reorder:[role uuids]}}` |
| `wizard` | JSON | NVARCHAR(MAX) | NULL | — | `{validate_before_next, allow_jump}` |
| `child_table_name` | VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(60) COLLATE Latin1_General_100_BIN2 | NULL | — | repeater / subform child table |
| `subform_form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION (inline sub-form of a linked form) |
| `relation_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `relations.id` NO ACTION (repeater/subform FK) |
| `justification_level` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | default for fields inside (§4.24); values: `inherit`, `not_required`, `optional`, `mandatory` |
| `archived_at` | DATETIME(6) | DATETIME2(6) | NULL | — | removed from draft but retained for history |

- **Primary key:** `pk_field_groups` (`id`); SQL Server clustered.
- **Unique:** `uq_field_groups_uuid` (`uuid`)
- **Unique:** `uq_field_groups_form_id_key` (`form_id`, `key`)
- **Index:** `ix_field_groups_form_id_parent_group_id_sort_order` (`form_id`, `parent_group_id`, `sort_order`)
- **Index:** `ix_field_groups_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_field_groups_created_by` (`created_by`) — supports FK
- **Index:** `ix_field_groups_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_field_groups_parent_group_id` (`parent_group_id`) — supports FK
- **Index:** `ix_field_groups_subform_form_id` (`subform_form_id`) — supports FK
- **Index:** `ix_field_groups_relation_id` (`relation_id`) — supports FK
- **Foreign key:** `fk_field_groups_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_groups_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_groups_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_groups_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_field_groups_parent_group_id`: `parent_group_id` → `field_groups`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_groups_subform_form_id`: `subform_form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_groups_relation_id`: `relation_id` → `relations`(`id`) ON DELETE NO ACTION
- **Check:** `ck_field_groups_type`: `type IN ('section', 'fieldset', 'card', 'tabs', 'tab', 'wizard', 'step', 'row', 'column', 'panel', 'accordion', 'repeater', 'subform')` (both engines)
- **Check:** `ck_field_groups_default_state`: `default_state IN ('open', 'closed')` (both engines)
- **Check:** `ck_field_groups_justification_level`: `justification_level IN ('inherit', 'not_required', 'optional', 'mandatory')` (both engines)
- **Check (SQL Server):** `ck_field_groups_layout_json`: `ISJSON(layout) = 1`
- **Check (SQL Server):** `ck_field_groups_validation_json`: `ISJSON(validation) = 1`
- **Check (SQL Server):** `ck_field_groups_repeater_json`: `ISJSON(repeater) = 1`
- **Check (SQL Server):** `ck_field_groups_wizard_json`: `ISJSON(wizard) = 1`
- Translatable: `title`, `description`.

**`fields`** (§7 Structure, §4.6)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `group_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `field_groups.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | unique per form (auto-generated, editable before first publish) |
| `type` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | registered field type (§14.4) |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `is_stored` | TINYINT(1) | BIT | NOT NULL | 1 | false for display elements (heading, divider, static HTML…) |
| `column_name` | VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(60) COLLATE Latin1_General_100_BIN2 | NULL | — | physical column |
| `db_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | logical type (§9.2) |
| `length` | INT | INT | NULL | — |  |
| `precision` | SMALLINT | SMALLINT | NULL | — |  |
| `scale` | SMALLINT | SMALLINT | NULL | — |  |
| `is_nullable` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `db_default` | JSON | NVARCHAR(MAX) | NULL | — | DB-level default |
| `index_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `none`, `index`, `unique` |
| `unique_scope` | JSON | NVARCHAR(MAX) | NULL | — | field keys the uniqueness is scoped by (e.g. department) |
| `is_encrypted` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `blind_index` | TINYINT(1) | BIT | NOT NULL | 0 | exact-match search on encrypted value |
| `is_sensitive` | TINYINT(1) | BIT | NOT NULL | 0 | masked in logs/errors/exports without permission |
| `is_personal_data` | TINYINT(1) | BIT | NOT NULL | 0 | §4.26 |
| `track_changes` | TINYINT(1) | BIT | NOT NULL | 1 | audit field-level diff |
| `relation_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `relations.id` NO ACTION |
| `options_source` | JSON | NVARCHAR(MAX) | NULL | — | §14.5 |
| `validation` | JSON | NVARCHAR(MAX) | NOT NULL | — | §14.6 |
| `behavior` | JSON | NVARCHAR(MAX) | NOT NULL | — | defaults, formula AST, transforms, masks, number/date formatting, calendar, file storage, autofill (§14.7) |
| `ui` | JSON | NVARCHAR(MAX) | NOT NULL | — | size, icon, width per breakpoint, label position, autofocus, tab index, autocomplete, spellcheck, css class |
| `table_settings` | JSON | NVARCHAR(MAX) | NOT NULL | — | visible by default, sortable, filterable, searchable, display format |
| `export_settings` | JSON | NVARCHAR(MAX) | NOT NULL | — | exportable, importable, excel column name, print/PDF inclusion |
| `events` | JSON | NVARCHAR(MAX) | NULL | — | on change/focus/blur → actions (§14.8) |
| `hook_binding` | JSON | NVARCHAR(MAX) | NULL | — | developer hook reference (visible with Manage Code only) |
| `justification_level` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `inherit`, `not_required`, `optional`, `mandatory` |
| `template_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `field_templates.id` NO ACTION (created from library) |
| `archived_at` | DATETIME(6) | DATETIME2(6) | NULL | — | field removed: column archived (§11.5) |
| `archived_column_name` | VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(60) COLLATE Latin1_General_100_BIN2 | NULL | — |  |

- **Primary key:** `pk_fields` (`id`); SQL Server clustered.
- **Unique:** `uq_fields_uuid` (`uuid`)
- **Unique:** `uq_fields_form_id_key` (`form_id`, `key`)
- **Index:** `ix_fields_form_id_group_id_sort_order` (`form_id`, `group_id`, `sort_order`)
- **Index:** `ix_fields_form_id_column_name` (`form_id`, `column_name`)
- **Index:** `ix_fields_relation_id` (`relation_id`)
- **Index:** `ix_fields_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_fields_created_by` (`created_by`) — supports FK
- **Index:** `ix_fields_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_fields_group_id` (`group_id`) — supports FK
- **Index:** `ix_fields_template_id` (`template_id`) — supports FK
- **Foreign key:** `fk_fields_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_fields_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_fields_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_fields_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_fields_group_id`: `group_id` → `field_groups`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_fields_relation_id`: `relation_id` → `relations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_fields_template_id`: `template_id` → `field_templates`(`id`) ON DELETE NO ACTION
- **Check:** `ck_fields_index_type`: `index_type IN ('none', 'index', 'unique')` (both engines)
- **Check:** `ck_fields_justification_level`: `justification_level IN ('inherit', 'not_required', 'optional', 'mandatory')` (both engines)
- **Check (SQL Server):** `ck_fields_db_default_json`: `ISJSON(db_default) = 1`
- **Check (SQL Server):** `ck_fields_unique_scope_json`: `ISJSON(unique_scope) = 1`
- **Check (SQL Server):** `ck_fields_options_source_json`: `ISJSON(options_source) = 1`
- **Check (SQL Server):** `ck_fields_validation_json`: `ISJSON(validation) = 1`
- **Check (SQL Server):** `ck_fields_behavior_json`: `ISJSON(behavior) = 1`
- **Check (SQL Server):** `ck_fields_ui_json`: `ISJSON(ui) = 1`
- **Check (SQL Server):** `ck_fields_table_settings_json`: `ISJSON(table_settings) = 1`
- **Check (SQL Server):** `ck_fields_export_settings_json`: `ISJSON(export_settings) = 1`
- **Check (SQL Server):** `ck_fields_events_json`: `ISJSON(events) = 1`
- **Check (SQL Server):** `ck_fields_hook_binding_json`: `ISJSON(hook_binding) = 1`
- Translatable: `label`, `placeholder`, `help_text`, `tooltip`, `description`, `prefix`, `suffix`, `column_label`, `validation.<rule>` messages, `consent_terms`.

**`field_options`** (§7 Structure)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `field_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `fields.id` CASCADE |
| `value` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — | stored value |
| `group_key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NULL | — | optgroup / option grouping |
| `parent_value` | VARCHAR(255) | NVARCHAR(255) | NULL | — | static cascading |
| `color` | VARCHAR(16) | NVARCHAR(16) | NULL | — |  |
| `icon` | VARCHAR(64) | NVARCHAR(64) | NULL | — |  |
| `is_default` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION (option visibility) |

- **Primary key:** `pk_field_options` (`id`); SQL Server clustered.
- **Unique:** `uq_field_options_field_id_value` (`field_id`, `value`)
- **Unique:** `uq_field_options_uuid` (`uuid`)
- **Index:** `ix_field_options_field_id_sort_order` (`field_id`, `sort_order`)
- **Index:** `ix_field_options_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_field_options_condition_id` (`condition_id`) — supports FK
- **Foreign key:** `fk_field_options_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_options_field_id`: `field_id` → `fields`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_field_options_condition_id`: `condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- Translatable: `label`.

**`conditions`** (§7 Structure, §4.7)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` CASCADE; NULL for non-form owners (menus, permissions…) |
| `owner_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `field`, `group`, `option`, `action`, `action_step`, `transition`, `notification_rule`, `justification_rule`, `automation`, `automation_step`, `view`, `view_panel`, `menu_item`, `page_widget`, `permission_assignment`, `record_access_rule`, `sla_rule`, `assignment_rule`, `legal_hold`, `form` |
| `owner_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `name` | VARCHAR(255) | NVARCHAR(255) | NULL | — |  |
| `ast` | JSON | NVARCHAR(MAX) | NOT NULL | — | expression AST (boolean) — §15 |
| `effects` | JSON | NVARCHAR(MAX) | NOT NULL | — | `[{effect, target_ref?, params?}]` (§14.3); empty for pure predicates |
| `else_effects` | JSON | NVARCHAR(MAX) | NULL | — | effects applied when the predicate is false |
| `evaluate_on` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | `change` supports *changed from/to*; values: `always`, `change` |
| `runtime` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | server_only for secrets/lookups beyond client scope; values: `client_and_server`, `server_only` |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_conditions` (`id`); SQL Server clustered.
- **Unique:** `uq_conditions_uuid` (`uuid`)
- **Index:** `ix_conditions_owner_type_owner_id` (`owner_type`, `owner_id`)
- **Index:** `ix_conditions_form_id_is_active` (`form_id`, `is_active`)
- **Index:** `ix_conditions_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_conditions_created_by` (`created_by`) — supports FK
- **Index:** `ix_conditions_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_conditions_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_conditions_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_conditions_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_conditions_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Check:** `ck_conditions_owner_type`: `owner_type IN ('field', 'group', 'option', 'action', 'action_step', 'transition', 'notification_rule', 'justification_rule', 'automation', 'automation_step', 'view', 'view_panel', 'menu_item', 'page_widget', 'permission_assignment', 'record_access_rule', 'sla_rule', 'assignment_rule', 'legal_hold', 'form')` (both engines)
- **Check:** `ck_conditions_evaluate_on`: `evaluate_on IN ('always', 'change')` (both engines)
- **Check:** `ck_conditions_runtime`: `runtime IN ('client_and_server', 'server_only')` (both engines)
- **Check (SQL Server):** `ck_conditions_ast_json`: `ISJSON(ast) = 1`
- **Check (SQL Server):** `ck_conditions_effects_json`: `ISJSON(effects) = 1`
- **Check (SQL Server):** `ck_conditions_else_effects_json`: `ISJSON(else_effects) = 1`

**`relations`** (§7 Structure)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | unique per source form |
| `source_form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `target_form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `one_to_one`, `one_to_many`, `many_to_one`, `many_to_many` |
| `kind` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | repeaters & inline sub-forms are `child_table` / `subform`; values: `reference`, `child_table`, `subform` |
| `fk_table` | VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(60) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | table holding the FK column |
| `fk_column` | VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(60) COLLATE Latin1_General_100_BIN2 | NULL | — | NULL for many_to_many |
| `pivot_table` | VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(60) COLLATE Latin1_General_100_BIN2 | NULL | — | `p_{key}` for many_to_many |
| `display_field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION |
| `value_field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION (default target PK) |
| `on_delete` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | enforced in DB where the cascade graph allows, otherwise in the pipeline (§9.4); values: `restrict`, `cascade`, `set_null` |
| `inverse_key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NULL | — | name of the reverse relation for traversal |
| `is_cross_application` | TINYINT(1) | BIT | NOT NULL | 0 |  |

- **Primary key:** `pk_relations` (`id`); SQL Server clustered.
- **Unique:** `uq_relations_uuid` (`uuid`)
- **Unique:** `uq_relations_source_form_id_key` (`source_form_id`, `key`)
- **Index:** `ix_relations_target_form_id` (`target_form_id`)
- **Index:** `ix_relations_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_relations_created_by` (`created_by`) — supports FK
- **Index:** `ix_relations_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_relations_display_field_id` (`display_field_id`) — supports FK
- **Index:** `ix_relations_value_field_id` (`value_field_id`) — supports FK
- **Foreign key:** `fk_relations_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_relations_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_relations_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_relations_source_form_id`: `source_form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_relations_target_form_id`: `target_form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_relations_display_field_id`: `display_field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_relations_value_field_id`: `value_field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Check:** `ck_relations_type`: `type IN ('one_to_one', 'one_to_many', 'many_to_one', 'many_to_many')` (both engines)
- **Check:** `ck_relations_kind`: `kind IN ('reference', 'child_table', 'subform')` (both engines)
- **Check:** `ck_relations_on_delete`: `on_delete IN ('restrict', 'cascade', 'set_null')` (both engines)

**`field_templates`** (§7 Structure — reusable field library, §4.3)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `application_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `applications.id` NO ACTION; NULL = global |
| `kind` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `field`, `group` |
| `category` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `definition` | JSON | NVARCHAR(MAX) | NOT NULL | — | field or group subtree in §14 format (keys regenerated on insert) |
| `usage_count` | INT | INT | NOT NULL | 0 |  |

- **Primary key:** `pk_field_templates` (`id`); SQL Server clustered.
- **Unique:** `uq_field_templates_uuid` (`uuid`)
- **Index:** `ix_field_templates_organization_id_kind_category` (`organization_id`, `kind`, `category`)
- **Index:** `ix_field_templates_created_by` (`created_by`) — supports FK
- **Index:** `ix_field_templates_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_field_templates_application_id` (`application_id`) — supports FK
- **Foreign key:** `fk_field_templates_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_templates_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_templates_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_field_templates_application_id`: `application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Check:** `ck_field_templates_kind`: `kind IN ('field', 'group')` (both engines)
- **Check (SQL Server):** `ck_field_templates_definition_json`: `ISJSON(definition) = 1`
- Translatable: `name`, `description`.

### 10.6 Records support

**`files`** (supporting — every upload, attachment, generated file)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `disk` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | storage disk name (local private / s3) |
| `path` | VARCHAR(1024) | NVARCHAR(1024) | NOT NULL | — | never under the public root |
| `original_name` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — | sanitized |
| `mime_type` | VARCHAR(127) | NVARCHAR(127) | NOT NULL | — | sniffed |
| `extension` | VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(16) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `size_bytes` | BIGINT | BIGINT | NOT NULL | — |  |
| `sha256` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `scan_status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `pending`, `clean`, `infected`, `skipped`, `error` |
| `scanned_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `width` | INT | INT | NULL | — |  |
| `height` | INT | INT | NULL | — |  |
| `is_encrypted` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `is_temporary` | TINYINT(1) | BIT | NOT NULL | 0 | uploaded but not yet linked; purged after 24 h |
| `owner_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | morph alias (record, justification, theme_asset, …) |
| `owner_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION |
| `uploaded_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `external_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `external_users.id` NO ACTION |
| `deleted_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_files` (`id`); SQL Server clustered.
- **Unique:** `uq_files_uuid` (`uuid`)
- **Index:** `ix_files_form_id_record_id` (`form_id`, `record_id`)
- **Index:** `ix_files_owner_type_owner_id` (`owner_type`, `owner_id`)
- **Index:** `ix_files_organization_id_is_temporary_created_at` (`organization_id`, `is_temporary`, `created_at`)
- **Index:** `ix_files_sha256` (`sha256`)
- **Index:** `ix_files_field_id` (`field_id`) — supports FK
- **Index:** `ix_files_uploaded_by` (`uploaded_by`) — supports FK
- **Index:** `ix_files_external_user_id` (`external_user_id`) — supports FK
- **Foreign key:** `fk_files_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_files_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_files_field_id`: `field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_files_uploaded_by`: `uploaded_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_files_external_user_id`: `external_user_id` → `external_users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_files_scan_status`: `scan_status IN ('pending', 'clean', 'infected', 'skipped', 'error')` (both engines)

**`record_comments`** (supporting — §4.14 comments thread)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `deleted_at` | DATETIME(6) | DATETIME2(6) | NULL | — | soft delete |
| `deleted_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `parent_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `record_comments.id` NO ACTION |
| `body` | TEXT | NVARCHAR(MAX) | NOT NULL | — | sanitized rich text |
| `author_user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `on_behalf_of_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |

- **Primary key:** `pk_record_comments` (`id`); SQL Server clustered.
- **Unique:** `uq_record_comments_uuid` (`uuid`)
- **Index:** `ix_record_comments_form_id_record_id_created_at` (`form_id`, `record_id`, `created_at`)
- **Index:** `ix_record_comments_organization_id_deleted_at` (`organization_id`, `deleted_at`)
- **Index:** `ix_record_comments_deleted_by` (`deleted_by`) — supports FK
- **Index:** `ix_record_comments_parent_id` (`parent_id`) — supports FK
- **Index:** `ix_record_comments_author_user_id` (`author_user_id`) — supports FK
- **Index:** `ix_record_comments_on_behalf_of_user_id` (`on_behalf_of_user_id`) — supports FK
- **Foreign key:** `fk_record_comments_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_record_comments_deleted_by`: `deleted_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_record_comments_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_record_comments_parent_id`: `parent_id` → `record_comments`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_record_comments_author_user_id`: `author_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_record_comments_on_behalf_of_user_id`: `on_behalf_of_user_id` → `users`(`id`) ON DELETE NO ACTION

**`submission_journal`** (§7 Operations — durable journal, §4.2)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `idempotency_key` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | unique; client-generated per submit attempt or derived for imports/API |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `form_version_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `form_versions.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NULL | — | set when processed |
| `operation` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `create`, `update`, `delete`, `restore`, `transition`, `action` |
| `source` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `ui`, `api`, `import`, `automation`, `action`, `external`, `inbound_webhook`, `sync` |
| `user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `on_behalf_of_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `external_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `external_users.id` NO ACTION |
| `payload` | LONGTEXT | NVARCHAR(MAX) | NOT NULL | — | JSON; values of encrypted/sensitive fields encrypted with the field key |
| `expected_row_version` | BIGINT | BIGINT | NULL | — |  |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `received`, `processing`, `processed`, `failed`, `retrying`, `discarded` |
| `attempts` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `error_message` | TEXT | NVARCHAR(MAX) | NULL | — | masked |
| `error_trace` | LONGTEXT | NVARCHAR(MAX) | NULL | — | masked |
| `error_log_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `error_logs.id` (logical; no FK — partitioned table) |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `edited_payload` | LONGTEXT | NVARCHAR(MAX) | NULL | — | admin edit before retry (original kept) |
| `edited_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `discard_reason` | TEXT | NVARCHAR(MAX) | NULL | — | mandatory on discard |
| `discarded_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `processed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `import_job_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `import_jobs.id` NO ACTION |

- **Primary key:** `pk_submission_journal` (`id`); SQL Server clustered.
- **Unique:** `uq_submission_journal_idempotency_key` (`idempotency_key`)
- **Unique:** `uq_submission_journal_uuid` (`uuid`)
- **Index:** `ix_submission_journal_organization_id_status_created_at` (`organization_id`, `status`, `created_at`)
- **Index:** `ix_submission_journal_form_id_record_id` (`form_id`, `record_id`)
- **Index:** `ix_submission_journal_correlation_id` (`correlation_id`)
- **Index:** `ix_submission_journal_form_version_id` (`form_version_id`) — supports FK
- **Index:** `ix_submission_journal_user_id` (`user_id`) — supports FK
- **Index:** `ix_submission_journal_on_behalf_of_user_id` (`on_behalf_of_user_id`) — supports FK
- **Index:** `ix_submission_journal_external_user_id` (`external_user_id`) — supports FK
- **Index:** `ix_submission_journal_edited_by` (`edited_by`) — supports FK
- **Index:** `ix_submission_journal_discarded_by` (`discarded_by`) — supports FK
- **Index:** `ix_submission_journal_import_job_id` (`import_job_id`) — supports FK
- **Foreign key:** `fk_submission_journal_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_submission_journal_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_submission_journal_form_version_id`: `form_version_id` → `form_versions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_submission_journal_user_id`: `user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_submission_journal_on_behalf_of_user_id`: `on_behalf_of_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_submission_journal_external_user_id`: `external_user_id` → `external_users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_submission_journal_edited_by`: `edited_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_submission_journal_discarded_by`: `discarded_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_submission_journal_import_job_id`: `import_job_id` → `import_jobs`(`id`) ON DELETE NO ACTION
- **Check:** `ck_submission_journal_operation`: `operation IN ('create', 'update', 'delete', 'restore', 'transition', 'action')` (both engines)
- **Check:** `ck_submission_journal_source`: `source IN ('ui', 'api', 'import', 'automation', 'action', 'external', 'inbound_webhook', 'sync')` (both engines)
- **Check:** `ck_submission_journal_status`: `status IN ('received', 'processing', 'processed', 'failed', 'retrying', 'discarded')` (both engines)

**`outbox_events`** — see §10.21.

**`import_jobs`** / **`import_mappings`** / **`export_jobs`** — see §10.9.

### 10.7 Schema management

```mermaid
erDiagram
  forms ||--o{ migration_plans : has
  migration_plans ||--o{ migration_steps : ordered
  migration_plans ||--o| schema_snapshots : pre_publish
  forms ||--o{ publish_locks : locks
  forms ||--o{ schema_reconciliation_reports : checked
```

**`migration_plans`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `from_version_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `form_versions.id` NO ACTION |
| `to_version_number` | INT | INT | NOT NULL | — |  |
| `purpose` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `publish`, `rollback`, `status_mapping`, `repair`, `restore_snapshot` |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `pending`, `locked`, `running`, `applied`, `failed`, `reversing`, `reversed`, `inconsistent` |
| `steps_total` | INT | INT | NOT NULL | 0 |  |
| `steps_applied` | INT | INT | NOT NULL | 0 |  |
| `snapshot_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `schema_snapshots.id` NO ACTION |
| `impact` | JSON | NVARCHAR(MAX) | NOT NULL | — | estimated duration, lock impact, online/offline per step, affected rows |
| `lock_token` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `confirmed_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `confirmed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `started_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `finished_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `error` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |

- **Primary key:** `pk_migration_plans` (`id`); SQL Server clustered.
- **Unique:** `uq_migration_plans_uuid` (`uuid`)
- **Index:** `ix_migration_plans_form_id_status` (`form_id`, `status`)
- **Index:** `ix_migration_plans_organization_id_status` (`organization_id`, `status`)
- **Index:** `ix_migration_plans_created_by` (`created_by`) — supports FK
- **Index:** `ix_migration_plans_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_migration_plans_from_version_id` (`from_version_id`) — supports FK
- **Index:** `ix_migration_plans_snapshot_id` (`snapshot_id`) — supports FK
- **Index:** `ix_migration_plans_confirmed_by` (`confirmed_by`) — supports FK
- **Foreign key:** `fk_migration_plans_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_migration_plans_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_migration_plans_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_migration_plans_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_migration_plans_from_version_id`: `from_version_id` → `form_versions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_migration_plans_snapshot_id`: `snapshot_id` → `schema_snapshots`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_migration_plans_confirmed_by`: `confirmed_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_migration_plans_purpose`: `purpose IN ('publish', 'rollback', 'status_mapping', 'repair', 'restore_snapshot')` (both engines)
- **Check:** `ck_migration_plans_status`: `status IN ('pending', 'locked', 'running', 'applied', 'failed', 'reversing', 'reversed', 'inconsistent')` (both engines)
- **Check (SQL Server):** `ck_migration_plans_impact_json`: `ISJSON(impact) = 1`

**`migration_steps`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `migration_plan_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `migration_plans.id` CASCADE |
| `sequence` | INT | INT | NOT NULL | — |  |
| `operation` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `create_table`, `drop_table_archive`, `add_column`, `rename_column`, `alter_column`, `archive_column`, `restore_column`, `add_index`, `drop_index`, `add_foreign_key`, `drop_foreign_key`, `create_pivot`, `copy_data`, `backfill`, `validate_data`, `map_status`, `rename_table` |
| `table_name` | VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(60) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `forward` | JSON | NVARCHAR(MAX) | NOT NULL | — | operation spec (driver-neutral) |
| `reverse` | JSON | NVARCHAR(MAX) | NOT NULL | — | inverse operation spec; `{"irreversible":true,"restore":"snapshot"}` if lossy |
| `sql_preview` | LONGTEXT | NVARCHAR(MAX) | NULL | — | statements per engine for review |
| `is_destructive` | TINYINT(1) | BIT | NOT NULL | 0 | requires backup before execution |
| `is_online` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `estimated_ms` | BIGINT | BIGINT | NULL | — |  |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `pending`, `applied`, `failed`, `reversed`, `reverse_failed`, `skipped` |
| `started_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `applied_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `reversed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `duration_ms` | BIGINT | BIGINT | NULL | — |  |
| `error` | TEXT | NVARCHAR(MAX) | NULL | — |  |

- **Primary key:** `pk_migration_steps` (`id`); SQL Server clustered.
- **Unique:** `uq_migration_steps_migration_plan_id_sequence` (`migration_plan_id`, `sequence`)
- **Foreign key:** `fk_migration_steps_migration_plan_id`: `migration_plan_id` → `migration_plans`(`id`) ON DELETE CASCADE
- **Check:** `ck_migration_steps_operation`: `operation IN ('create_table', 'drop_table_archive', 'add_column', 'rename_column', 'alter_column', 'archive_column', 'restore_column', 'add_index', 'drop_index', 'add_foreign_key', 'drop_foreign_key', 'create_pivot', 'copy_data', 'backfill', 'validate_data', 'map_status', 'rename_table')` (both engines)
- **Check:** `ck_migration_steps_status`: `status IN ('pending', 'applied', 'failed', 'reversed', 'reverse_failed', 'skipped')` (both engines)
- **Check (SQL Server):** `ck_migration_steps_forward_json`: `ISJSON(forward) = 1`
- **Check (SQL Server):** `ck_migration_steps_reverse_json`: `ISJSON(reverse) = 1`

**`schema_snapshots`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `migration_plan_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `migration_plans.id` NO ACTION |
| `kind` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `metadata`, `physical_schema`, `data_backup` |
| `tables` | JSON | NVARCHAR(MAX) | NOT NULL | — | tables included with row counts |
| `disk` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `path` | VARCHAR(1024) | NVARCHAR(1024) | NOT NULL | — |  |
| `size_bytes` | BIGINT | BIGINT | NOT NULL | — |  |
| `checksum` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `expires_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | retention shown to admin |
| `restored_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_schema_snapshots` (`id`); SQL Server clustered.
- **Unique:** `uq_schema_snapshots_uuid` (`uuid`)
- **Index:** `ix_schema_snapshots_form_id_created_at` (`form_id`, `created_at`)
- **Index:** `ix_schema_snapshots_expires_at` (`expires_at`)
- **Index:** `ix_schema_snapshots_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_schema_snapshots_created_by` (`created_by`) — supports FK
- **Index:** `ix_schema_snapshots_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_schema_snapshots_migration_plan_id` (`migration_plan_id`) — supports FK
- **Foreign key:** `fk_schema_snapshots_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_schema_snapshots_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_schema_snapshots_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_schema_snapshots_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_schema_snapshots_migration_plan_id`: `migration_plan_id` → `migration_plans`(`id`) ON DELETE NO ACTION
- **Check:** `ck_schema_snapshots_kind`: `kind IN ('metadata', 'physical_schema', 'data_backup')` (both engines)
- **Check (SQL Server):** `ck_schema_snapshots_tables_json`: `ISJSON(tables) = 1`

**`schema_reconciliation_reports`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION; NULL = all forms |
| `trigger` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `on_demand`, `scheduled`, `post_publish`, `post_failure` |
| `engine` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `mysql`, `sqlsrv` |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `running`, `clean`, `drift`, `error` |
| `difference_count` | INT | INT | NOT NULL | 0 |  |
| `differences` | JSON | NVARCHAR(MAX) | NOT NULL | — | `[{table, kind: missing_table\|missing_column\|extra_column\|type_mismatch\|nullability\|index\|fk, expected, actual}]` |
| `triggered_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `started_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `finished_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_schema_reconciliation_reports` (`id`); SQL Server clustered.
- **Index:** `ix_schema_reconciliation_reports_organization_id_created_at` (`organization_id`, `created_at`)
- **Index:** `ix_schema_reconciliation_reports_form_id_created_at` (`form_id`, `created_at`)
- **Index:** `ix_schema_reconciliation_reports_triggered_by` (`triggered_by`) — supports FK
- **Foreign key:** `fk_schema_reconciliation_reports_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_schema_reconciliation_reports_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_schema_reconciliation_reports_triggered_by`: `triggered_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_schema_reconciliation_reports_trigger`: `trigger IN ('on_demand', 'scheduled', 'post_publish', 'post_failure')` (both engines)
- **Check:** `ck_schema_reconciliation_reports_engine`: `engine IN ('mysql', 'sqlsrv')` (both engines)
- **Check:** `ck_schema_reconciliation_reports_status`: `status IN ('running', 'clean', 'drift', 'error')` (both engines)
- **Check (SQL Server):** `ck_schema_reconciliation_reports_differences_json`: `ISJSON(differences) = 1`

**`publish_locks`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `lock_group` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | hash of the sorted set of related form ids locked together |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `held`, `waiting`, `released`, `expired` |
| `migration_plan_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `migration_plans.id` NO ACTION |
| `owner_user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `blocked_by_lock_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `publish_locks.id` NO ACTION (why queued) |
| `acquired_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `heartbeat_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `expires_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `held_key` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | = form_id while `status=held`, else NULL; **unique** → at most one holder per form (MySQL allows many NULLs; SQL Server uses a filtered index) |

- **Primary key:** `pk_publish_locks` (`id`); SQL Server clustered.
- **Unique:** `uq_publish_locks_held_key` (`held_key`) — SQL Server: filtered `WHERE held_key IS NOT NULL`; MySQL: unique (NULLs never collide)
- **Index:** `ix_publish_locks_form_id_status_created_at` (`form_id`, `status`, `created_at`)
- **Index:** `ix_publish_locks_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_publish_locks_migration_plan_id` (`migration_plan_id`) — supports FK
- **Index:** `ix_publish_locks_owner_user_id` (`owner_user_id`) — supports FK
- **Index:** `ix_publish_locks_blocked_by_lock_id` (`blocked_by_lock_id`) — supports FK
- **Foreign key:** `fk_publish_locks_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_publish_locks_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_publish_locks_migration_plan_id`: `migration_plan_id` → `migration_plans`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_publish_locks_owner_user_id`: `owner_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_publish_locks_blocked_by_lock_id`: `blocked_by_lock_id` → `publish_locks`(`id`) ON DELETE NO ACTION
- **Check:** `ck_publish_locks_status`: `status IN ('held', 'waiting', 'released', 'expired')` (both engines)

### 10.8 Workflow

```mermaid
erDiagram
  forms ||--o{ statuses : has
  statuses ||--o{ transitions : from
  statuses ||--o{ transitions : to
  statuses ||--o{ sla_rules : timed_by
  sla_rules ||--o{ sla_timers : runs
  status_history }o--|| statuses : to
  status_mappings }o--|| form_versions : applied_in
```

**`statuses`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `color` | VARCHAR(16) | NVARCHAR(16) | NOT NULL | — |  |
| `icon` | VARCHAR(64) | NVARCHAR(64) | NULL | — |  |
| `is_initial` | TINYINT(1) | BIT | NOT NULL | 0 | exactly one per form |
| `is_final` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `diagram_position` | JSON | NVARCHAR(MAX) | NULL | — | Vue Flow node position |
| `archived_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_statuses` (`id`); SQL Server clustered.
- **Unique:** `uq_statuses_uuid` (`uuid`)
- **Unique:** `uq_statuses_form_id_key` (`form_id`, `key`)
- **Index:** `ix_statuses_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_statuses_created_by` (`created_by`) — supports FK
- **Index:** `ix_statuses_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_statuses_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_statuses_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_statuses_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_statuses_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Check (SQL Server):** `ck_statuses_diagram_position_json`: `ISJSON(diagram_position) = 1`
- Translatable: `name`, `description`.

**`transitions`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `from_status_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `statuses.id` NO ACTION; NULL = from any status |
| `to_status_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `statuses.id` NO ACTION |
| `condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION |
| `required_fields` | JSON | NVARCHAR(MAX) | NULL | — | field uuids that must be filled |
| `comment_level` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `none`, `optional`, `mandatory` |
| `attachments_level` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `none`, `optional`, `mandatory` |
| `approval_mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | §4.25; values: `none`, `all`, `any_n`, `quorum` |
| `approval_config` | JSON | NVARCHAR(MAX) | NULL | — | approvers `[{type:user\|role\|department, uuid, weight}]`, `n`, `quorum_weight` |
| `rejection_behavior` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `immediate`, `wait_all` |
| `rejection_status_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `statuses.id` NO ACTION |
| `confirmation` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `button_style` | JSON | NVARCHAR(MAX) | NULL | — | color/icon/placement |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `diagram_edge` | JSON | NVARCHAR(MAX) | NULL | — | Vue Flow edge data |
| `archived_at` | DATETIME(6) | DATETIME2(6) | NULL | — | removed from the workflow after a version that used it was published; history keeps pointing at it (ADR-0031) |

- **Primary key:** `pk_transitions` (`id`); SQL Server clustered.
- **Unique:** `uq_transitions_uuid` (`uuid`)
- **Unique:** `uq_transitions_form_id_key` (`form_id`, `key`)
- **Index:** `ix_transitions_form_id_from_status_id` (`form_id`, `from_status_id`)
- **Index:** `ix_transitions_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_transitions_created_by` (`created_by`) — supports FK
- **Index:** `ix_transitions_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_transitions_from_status_id` (`from_status_id`) — supports FK
- **Index:** `ix_transitions_to_status_id` (`to_status_id`) — supports FK
- **Index:** `ix_transitions_condition_id` (`condition_id`) — supports FK
- **Index:** `ix_transitions_rejection_status_id` (`rejection_status_id`) — supports FK
- **Foreign key:** `fk_transitions_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_transitions_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_transitions_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_transitions_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_transitions_from_status_id`: `from_status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_transitions_to_status_id`: `to_status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_transitions_condition_id`: `condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_transitions_rejection_status_id`: `rejection_status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Check:** `ck_transitions_comment_level`: `comment_level IN ('none', 'optional', 'mandatory')` (both engines)
- **Check:** `ck_transitions_attachments_level`: `attachments_level IN ('none', 'optional', 'mandatory')` (both engines)
- **Check:** `ck_transitions_approval_mode`: `approval_mode IN ('none', 'all', 'any_n', 'quorum')` (both engines)
- **Check:** `ck_transitions_rejection_behavior`: `rejection_behavior IN ('immediate', 'wait_all')` (both engines)
- **Check (SQL Server):** `ck_transitions_required_fields_json`: `ISJSON(required_fields) = 1`
- **Check (SQL Server):** `ck_transitions_approval_config_json`: `ISJSON(approval_config) = 1`
- **Check (SQL Server):** `ck_transitions_button_style_json`: `ISJSON(button_style) = 1`
- **Check (SQL Server):** `ck_transitions_diagram_edge_json`: `ISJSON(diagram_edge) = 1`
- Translatable: `label`, `confirmation_text`. Who performs it: permission `transition.{uuid}.perform` (auto-registered).

**`status_history`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `from_status_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `statuses.id` NO ACTION |
| `to_status_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `statuses.id` NO ACTION |
| `transition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `transitions.id` NO ACTION (NULL for mapping/automation) |
| `source` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `user`, `automation`, `sla_escalation`, `status_mapping`, `bulk`, `api`, `external` |
| `comment` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `attachment_file_ids` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `acted_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `on_behalf_of_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `external_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `external_users.id` NO ACTION |
| `justification_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `justifications.id` NO ACTION |
| `approval_request_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `approval_requests.id` NO ACTION |
| `seconds_in_previous` | BIGINT | BIGINT | NULL | — |  |
| `working_seconds_in_previous` | BIGINT | BIGINT | NULL | — |  |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `acted_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |

- **Primary key:** `pk_status_history` (`id`); SQL Server clustered.
- **Index:** `ix_status_history_form_id_record_id_acted_at` (`form_id`, `record_id`, `acted_at`)
- **Index:** `ix_status_history_to_status_id_acted_at` (`to_status_id`, `acted_at`)
- **Index:** `ix_status_history_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_status_history_from_status_id` (`from_status_id`) — supports FK
- **Index:** `ix_status_history_transition_id` (`transition_id`) — supports FK
- **Index:** `ix_status_history_acted_by` (`acted_by`) — supports FK
- **Index:** `ix_status_history_on_behalf_of_user_id` (`on_behalf_of_user_id`) — supports FK
- **Index:** `ix_status_history_external_user_id` (`external_user_id`) — supports FK
- **Index:** `ix_status_history_justification_id` (`justification_id`) — supports FK
- **Index:** `ix_status_history_approval_request_id` (`approval_request_id`) — supports FK
- **Foreign key:** `fk_status_history_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_history_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_history_from_status_id`: `from_status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_history_to_status_id`: `to_status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_history_transition_id`: `transition_id` → `transitions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_history_acted_by`: `acted_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_history_on_behalf_of_user_id`: `on_behalf_of_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_history_external_user_id`: `external_user_id` → `external_users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_history_justification_id`: `justification_id` → `justifications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_history_approval_request_id`: `approval_request_id` → `approval_requests`(`id`) ON DELETE NO ACTION
- **Check:** `ck_status_history_source`: `source IN ('user', 'automation', 'sla_escalation', 'status_mapping', 'bulk', 'api', 'external')` (both engines)
- **Check (SQL Server):** `ck_status_history_attachment_file_ids_json`: `ISJSON(attachment_file_ids) = 1`
- **Note:** append-only

**`status_mappings`** (§4.10 guided mapping)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `form_version_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `form_versions.id` NO ACTION (version applying it) |
| `change_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `rename`, `add`, `remove`, `merge` |
| `from_status_key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `to_status_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `statuses.id` NO ACTION |
| `records_affected` | INT | INT | NOT NULL | 0 |  |
| `migration_plan_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `migration_plans.id` NO ACTION |
| `applied_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_status_mappings` (`id`); SQL Server clustered.
- **Index:** `ix_status_mappings_form_id_form_version_id` (`form_id`, `form_version_id`)
- **Index:** `ix_status_mappings_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_status_mappings_created_by` (`created_by`) — supports FK
- **Index:** `ix_status_mappings_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_status_mappings_form_version_id` (`form_version_id`) — supports FK
- **Index:** `ix_status_mappings_to_status_id` (`to_status_id`) — supports FK
- **Index:** `ix_status_mappings_migration_plan_id` (`migration_plan_id`) — supports FK
- **Foreign key:** `fk_status_mappings_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_mappings_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_mappings_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_mappings_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_mappings_form_version_id`: `form_version_id` → `form_versions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_mappings_to_status_id`: `to_status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_status_mappings_migration_plan_id`: `migration_plan_id` → `migration_plans`(`id`) ON DELETE NO ACTION
- **Check:** `ck_status_mappings_change_type`: `change_type IN ('rename', 'add', 'remove', 'merge')` (both engines)

**`sla_rules`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `status_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `statuses.id` NO ACTION |
| `duration_minutes` | INT | INT | NOT NULL | — |  |
| `use_working_time` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `business_calendar_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `business_calendars.id` NO ACTION; NULL = form/department calendar |
| `warn_before_minutes` | INT | INT | NULL | — |  |
| `escalations` | JSON | NVARCHAR(MAX) | NOT NULL | — | `[{after_minutes, action: notify\|reassign\|transition, params}]` |
| `condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `archived_at` | DATETIME(6) | DATETIME2(6) | NULL | — | removed after a version that used it was published; timers keep pointing at it (ADR-0031) |

- **Primary key:** `pk_sla_rules` (`id`); SQL Server clustered.
- **Unique:** `uq_sla_rules_uuid` (`uuid`)
- **Index:** `ix_sla_rules_form_id_status_id` (`form_id`, `status_id`)
- **Index:** `ix_sla_rules_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_sla_rules_created_by` (`created_by`) — supports FK
- **Index:** `ix_sla_rules_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_sla_rules_status_id` (`status_id`) — supports FK
- **Index:** `ix_sla_rules_business_calendar_id` (`business_calendar_id`) — supports FK
- **Index:** `ix_sla_rules_condition_id` (`condition_id`) — supports FK
- **Foreign key:** `fk_sla_rules_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sla_rules_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sla_rules_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sla_rules_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_sla_rules_status_id`: `status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sla_rules_business_calendar_id`: `business_calendar_id` → `business_calendars`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sla_rules_condition_id`: `condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_sla_rules_escalations_json`: `ISJSON(escalations) = 1`

**`sla_timers`** (supporting — runtime state)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `sla_rule_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `sla_rules.id` NO ACTION |
| `status_history_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `status_history.id` NO ACTION |
| `started_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `due_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | precomputed with the business calendar |
| `warned_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `breached_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `escalation_level` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `next_check_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `state` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `running`, `warned`, `breached`, `completed`, `cancelled` |
| `completed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_sla_timers` (`id`); SQL Server clustered.
- **Index:** `ix_sla_timers_state_next_check_at` (`state`, `next_check_at`)
- **Index:** `ix_sla_timers_form_id_record_id` (`form_id`, `record_id`)
- **Index:** `ix_sla_timers_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_sla_timers_sla_rule_id` (`sla_rule_id`) — supports FK
- **Index:** `ix_sla_timers_status_history_id` (`status_history_id`) — supports FK
- **Foreign key:** `fk_sla_timers_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sla_timers_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sla_timers_sla_rule_id`: `sla_rule_id` → `sla_rules`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sla_timers_status_history_id`: `status_history_id` → `status_history`(`id`) ON DELETE NO ACTION
- **Check:** `ck_sla_timers_state`: `state IN ('running', 'warned', 'breached', 'completed', 'cancelled')` (both engines)

### 10.9 Views & actions

```mermaid
erDiagram
  forms ||--o{ views : has
  views ||--o{ view_columns : shows
  views ||--o{ filters : offers
  views ||--o{ saved_views : base_of
  saved_views ||--o{ saved_view_shares : shared
  forms ||--o{ view_panels : view_mode
  forms ||--o{ reference_previews : preview
  forms ||--o{ print_layouts : prints
  forms ||--o{ actions : has
  actions ||--o{ action_steps : chain
  forms ||--o{ import_jobs : imports
```

**`views`** (table configuration per form, per role)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `is_default` | TINYINT(1) | BIT | NOT NULL | 0 | fallback when no role-specific view matches |
| `priority` | INT | INT | NOT NULL | 0 | lowest wins when a user's roles match several views |
| `page_size` | SMALLINT | SMALLINT | NOT NULL | — |  |
| `default_sort` | JSON | NVARCHAR(MAX) | NOT NULL | — | `[{path, dir}]` |
| `show_totals` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `allow_column_chooser` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `allow_global_search` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `row_options` | JSON | NVARCHAR(MAX) | NOT NULL | — | `{view, edit, log}` toggles (still permission-checked) |
| `include_in_queues` | TINYINT(1) | BIT | NOT NULL | 0 |  |

- **Primary key:** `pk_views` (`id`); SQL Server clustered.
- **Unique:** `uq_views_uuid` (`uuid`)
- **Unique:** `uq_views_form_id_key` (`form_id`, `key`)
- **Index:** `ix_views_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_views_created_by` (`created_by`) — supports FK
- **Index:** `ix_views_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_views_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_views_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_views_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_views_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Check (SQL Server):** `ck_views_default_sort_json`: `ISJSON(default_sort) = 1`
- **Check (SQL Server):** `ck_views_row_options_json`: `ISJSON(row_options) = 1`
- Translatable: `name`. Audience: permission `view.{uuid}.use`.

**`view_columns`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `view_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `views.id` CASCADE |
| `path` | JSON | NVARCHAR(MAX) | NOT NULL | — | relation path (§17): `["employee","department","name"]` |
| `path_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `width` | SMALLINT | SMALLINT | NULL | — | px |
| `pinned` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | logical start/end for RTL; values: `none`, `start`, `end` |
| `is_visible` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `is_sortable` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `format` | JSON | NVARCHAR(MAX) | NULL | — | display format override |
| `aggregate` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | totals row; values: `none`, `count`, `sum`, `avg`, `min`, `max` |

- **Primary key:** `pk_view_columns` (`id`); SQL Server clustered.
- **Unique:** `uq_view_columns_view_id_path_hash` (`view_id`, `path_hash`)
- **Unique:** `uq_view_columns_uuid` (`uuid`)
- **Index:** `ix_view_columns_view_id_sort_order` (`view_id`, `sort_order`)
- **Foreign key:** `fk_view_columns_view_id`: `view_id` → `views`(`id`) ON DELETE CASCADE
- **Check:** `ck_view_columns_pinned`: `pinned IN ('none', 'start', 'end')` (both engines)
- **Check:** `ck_view_columns_aggregate`: `aggregate IN ('none', 'count', 'sum', 'avg', 'min', 'max')` (both engines)
- **Check (SQL Server):** `ck_view_columns_path_json`: `ISJSON(path) = 1`
- **Check (SQL Server):** `ck_view_columns_format_json`: `ISJSON(format) = 1`
- Translatable: `label`.

**`filters`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `view_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `views.id` CASCADE |
| `path` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `path_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `filter_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | derived from field type (text, number_range, date_range, options, user, boolean, status…) |
| `operators` | JSON | NVARCHAR(MAX) | NOT NULL | — | allowed operators |
| `is_quick` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `default_value` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `sort_order` | INT | INT | NOT NULL | 0 |  |

- **Primary key:** `pk_filters` (`id`); SQL Server clustered.
- **Unique:** `uq_filters_view_id_path_hash` (`view_id`, `path_hash`)
- **Unique:** `uq_filters_uuid` (`uuid`)
- **Foreign key:** `fk_filters_view_id`: `view_id` → `views`(`id`) ON DELETE CASCADE
- **Check (SQL Server):** `ck_filters_path_json`: `ISJSON(path) = 1`
- **Check (SQL Server):** `ck_filters_operators_json`: `ISJSON(operators) = 1`
- **Check (SQL Server):** `ck_filters_default_value_json`: `ISJSON(default_value) = 1`
- Translatable: `label`.

**`saved_views`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `view_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `views.id` NO ACTION |
| `owner_user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `name` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — | user text (not translated) |
| `state` | JSON | NVARCHAR(MAX) | NOT NULL | — | columns, order, widths, filters, sort, page size, search |
| `is_shared` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `is_default` | TINYINT(1) | BIT | NOT NULL | 0 | user's default for this form |

- **Primary key:** `pk_saved_views` (`id`); SQL Server clustered.
- **Unique:** `uq_saved_views_uuid` (`uuid`)
- **Index:** `ix_saved_views_owner_user_id_form_id` (`owner_user_id`, `form_id`)
- **Index:** `ix_saved_views_form_id_is_shared` (`form_id`, `is_shared`)
- **Index:** `ix_saved_views_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_saved_views_view_id` (`view_id`) — supports FK
- **Foreign key:** `fk_saved_views_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_saved_views_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_saved_views_view_id`: `view_id` → `views`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_saved_views_owner_user_id`: `owner_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_saved_views_state_json`: `ISJSON(state) = 1`

**`saved_view_shares`** (supporting)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `saved_view_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `saved_views.id` CASCADE |
| `subject_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `role`, `department`, `user`, `everyone` |
| `subject_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |

- **Primary key:** `pk_saved_view_shares` (`id`); SQL Server clustered.
- **Index:** `ix_saved_view_shares_saved_view_id` (`saved_view_id`)
- **Index:** `ix_saved_view_shares_subject_type_subject_id` (`subject_type`, `subject_id`)
- **Foreign key:** `fk_saved_view_shares_saved_view_id`: `saved_view_id` → `saved_views`(`id`) ON DELETE CASCADE
- **Check:** `ck_saved_view_shares_subject_type`: `subject_type IN ('role', 'department', 'user', 'everyone')` (both engines)

**`view_panels`** (View Mode composition)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `parent_panel_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `view_panels.id` NO ACTION (tabs/sections) |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `tabs`, `tab`, `section`, `related_table`, `derived_fields`, `summary_widget`, `status_timeline`, `comments`, `attachments`, `form_body`, `html` |
| `relation_path` | JSON | NVARCHAR(MAX) | NULL | — | for related tables / derived fields |
| `config` | JSON | NVARCHAR(MAX) | NOT NULL | — | columns, filters, actions, widget definition, derived field paths |
| `visibility_condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION (per role etc.) |
| `sort_order` | INT | INT | NOT NULL | 0 |  |

- **Primary key:** `pk_view_panels` (`id`); SQL Server clustered.
- **Unique:** `uq_view_panels_uuid` (`uuid`)
- **Index:** `ix_view_panels_form_id_parent_panel_id_sort_order` (`form_id`, `parent_panel_id`, `sort_order`)
- **Index:** `ix_view_panels_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_view_panels_created_by` (`created_by`) — supports FK
- **Index:** `ix_view_panels_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_view_panels_parent_panel_id` (`parent_panel_id`) — supports FK
- **Index:** `ix_view_panels_visibility_condition_id` (`visibility_condition_id`) — supports FK
- **Foreign key:** `fk_view_panels_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_view_panels_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_view_panels_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_view_panels_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_view_panels_parent_panel_id`: `parent_panel_id` → `view_panels`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_view_panels_visibility_condition_id`: `visibility_condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Check:** `ck_view_panels_type`: `type IN ('tabs', 'tab', 'section', 'related_table', 'derived_fields', 'summary_widget', 'status_timeline', 'comments', 'attachments', 'form_body', 'html')` (both engines)
- **Check (SQL Server):** `ck_view_panels_relation_path_json`: `ISJSON(relation_path) = 1`
- **Check (SQL Server):** `ck_view_panels_config_json`: `ISJSON(config) = 1`
- Translatable: `title`, `content` (html).

**`reference_previews`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `target_form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE (form being previewed) |
| `field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION (lookup-specific override) |
| `display_paths` | JSON | NVARCHAR(MAX) | NOT NULL | — | fields shown on the card |
| `layout` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `autofill_map` | JSON | NVARCHAR(MAX) | NULL | — | `[{from_path, to_field_uuid, overwrite}]` |
| `drawer_enabled` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_reference_previews` (`id`); SQL Server clustered.
- **Unique:** `uq_reference_previews_uuid` (`uuid`)
- **Index:** `ix_reference_previews_target_form_id_field_id` (`target_form_id`, `field_id`)
- **Index:** `ix_reference_previews_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_reference_previews_created_by` (`created_by`) — supports FK
- **Index:** `ix_reference_previews_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_reference_previews_field_id` (`field_id`) — supports FK
- **Foreign key:** `fk_reference_previews_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_reference_previews_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_reference_previews_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_reference_previews_target_form_id`: `target_form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_reference_previews_field_id`: `field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_reference_previews_display_paths_json`: `ISJSON(display_paths) = 1`
- **Check (SQL Server):** `ck_reference_previews_layout_json`: `ISJSON(layout) = 1`
- **Check (SQL Server):** `ck_reference_previews_autofill_map_json`: `ISJSON(autofill_map) = 1`

**`print_layouts`** (supporting — §4.14 print view)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `paper` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `a4`, `a3`, `letter`, `legal` |
| `orientation` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `portrait`, `landscape` |
| `layout` | JSON | NVARCHAR(MAX) | NOT NULL | — | sections/fields/panels included, page breaks |
| `show_logo` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `is_default` | TINYINT(1) | BIT | NOT NULL | 0 |  |

- **Primary key:** `pk_print_layouts` (`id`); SQL Server clustered.
- **Unique:** `uq_print_layouts_uuid` (`uuid`)
- **Unique:** `uq_print_layouts_form_id_key` (`form_id`, `key`)
- **Index:** `ix_print_layouts_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_print_layouts_created_by` (`created_by`) — supports FK
- **Index:** `ix_print_layouts_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_print_layouts_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_print_layouts_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_print_layouts_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_print_layouts_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Check:** `ck_print_layouts_paper`: `paper IN ('a4', 'a3', 'letter', 'legal')` (both engines)
- **Check:** `ck_print_layouts_orientation`: `orientation IN ('portrait', 'landscape')` (both engines)
- **Check (SQL Server):** `ck_print_layouts_layout_json`: `ISJSON(layout) = 1`
- Translatable: `name`, `header_html`, `footer_html`.

**`actions`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `kind` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `builtin`, `custom` |
| `builtin` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | values: `export`, `import`, `print`, `duplicate`, `bulk_delete`, `bulk_status`, `bulk_update`, `download` |
| `placements` | JSON | NVARCHAR(MAX) | NOT NULL | — | subset of `row`,`bulk`,`toolbar`,`view_page` |
| `condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION (availability) |
| `requires_confirmation` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `run_mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | auto = queued above threshold; values: `sync`, `queued`, `auto` |
| `queue_threshold` | INT | INT | NULL | — |  |
| `max_records` | INT | INT | NULL | — |  |
| `justification_level` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `not_required`, `optional`, `mandatory` |
| `icon` | VARCHAR(64) | NVARCHAR(64) | NULL | — |  |
| `color` | VARCHAR(16) | NVARCHAR(16) | NULL | — |  |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_actions` (`id`); SQL Server clustered.
- **Unique:** `uq_actions_uuid` (`uuid`)
- **Unique:** `uq_actions_form_id_key` (`form_id`, `key`)
- **Index:** `ix_actions_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_actions_created_by` (`created_by`) — supports FK
- **Index:** `ix_actions_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_actions_condition_id` (`condition_id`) — supports FK
- **Foreign key:** `fk_actions_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_actions_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_actions_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_actions_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_actions_condition_id`: `condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Check:** `ck_actions_kind`: `kind IN ('builtin', 'custom')` (both engines)
- **Check:** `ck_actions_builtin`: `builtin IN ('export', 'import', 'print', 'duplicate', 'bulk_delete', 'bulk_status', 'bulk_update', 'download')` (both engines)
- **Check:** `ck_actions_run_mode`: `run_mode IN ('sync', 'queued', 'auto')` (both engines)
- **Check:** `ck_actions_justification_level`: `justification_level IN ('not_required', 'optional', 'mandatory')` (both engines)
- **Check (SQL Server):** `ck_actions_placements_json`: `ISJSON(placements) = 1`
- Translatable: `label`, `confirmation_text`, `success_message`. Permission: `action.{uuid}.run`.

**`action_steps`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `action_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `actions.id` CASCADE |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `update_fields`, `change_status`, `send_email`, `send_notification`, `call_webhook`, `generate_document`, `create_linked_record`, `assign`, `run_download` |
| `config` | JSON | NVARCHAR(MAX) | NOT NULL | — | step-specific (§14.9) |
| `condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION (run only if) |
| `on_failure` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `stop`, `continue` |

- **Primary key:** `pk_action_steps` (`id`); SQL Server clustered.
- **Unique:** `uq_action_steps_uuid` (`uuid`)
- **Index:** `ix_action_steps_action_id_sort_order` (`action_id`, `sort_order`)
- **Index:** `ix_action_steps_condition_id` (`condition_id`) — supports FK
- **Foreign key:** `fk_action_steps_action_id`: `action_id` → `actions`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_action_steps_condition_id`: `condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Check:** `ck_action_steps_type`: `type IN ('update_fields', 'change_status', 'send_email', 'send_notification', 'call_webhook', 'generate_document', 'create_linked_record', 'assign', 'run_download')` (both engines)
- **Check:** `ck_action_steps_on_failure`: `on_failure IN ('stop', 'continue')` (both engines)
- **Check (SQL Server):** `ck_action_steps_config_json`: `ISJSON(config) = 1`

**`import_mappings`** (supporting — saved import mappings)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `name` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — |  |
| `mapping` | JSON | NVARCHAR(MAX) | NOT NULL | — | `[{column_header, field_uuid, transform}]` |
| `mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `insert`, `update`, `upsert` |
| `key_field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION |

- **Primary key:** `pk_import_mappings` (`id`); SQL Server clustered.
- **Unique:** `uq_import_mappings_uuid` (`uuid`)
- **Index:** `ix_import_mappings_form_id` (`form_id`)
- **Index:** `ix_import_mappings_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_import_mappings_created_by` (`created_by`) — supports FK
- **Index:** `ix_import_mappings_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_import_mappings_key_field_id` (`key_field_id`) — supports FK
- **Foreign key:** `fk_import_mappings_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_import_mappings_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_import_mappings_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_import_mappings_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_import_mappings_key_field_id`: `key_field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Check:** `ck_import_mappings_mode`: `mode IN ('insert', 'update', 'upsert')` (both engines)
- **Check (SQL Server):** `ck_import_mappings_mapping_json`: `ISJSON(mapping) = 1`

**`import_jobs`** (supporting)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `file_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `files.id` NO ACTION |
| `import_mapping_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `import_mappings.id` NO ACTION |
| `mapping` | JSON | NVARCHAR(MAX) | NOT NULL | — | effective mapping |
| `mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `insert`, `update`, `upsert` |
| `key_field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION |
| `dry_run` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `queued`, `validating`, `running`, `completed`, `completed_with_errors`, `failed`, `cancelled` |
| `total_rows` | INT | INT | NOT NULL | 0 |  |
| `processed_rows` | INT | INT | NOT NULL | 0 |  |
| `created_count` | INT | INT | NOT NULL | 0 |  |
| `updated_count` | INT | INT | NOT NULL | 0 |  |
| `error_count` | INT | INT | NOT NULL | 0 |  |
| `last_committed_batch` | INT | INT | NOT NULL | 0 | resume point for idempotent retry |
| `error_report_file_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `files.id` NO ACTION |
| `justification_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `justifications.id` NO ACTION |
| `started_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `finished_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `error` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |

- **Primary key:** `pk_import_jobs` (`id`); SQL Server clustered.
- **Unique:** `uq_import_jobs_uuid` (`uuid`)
- **Index:** `ix_import_jobs_form_id_created_at` (`form_id`, `created_at`)
- **Index:** `ix_import_jobs_organization_id_status` (`organization_id`, `status`)
- **Index:** `ix_import_jobs_user_id` (`user_id`) — supports FK
- **Index:** `ix_import_jobs_file_id` (`file_id`) — supports FK
- **Index:** `ix_import_jobs_import_mapping_id` (`import_mapping_id`) — supports FK
- **Index:** `ix_import_jobs_key_field_id` (`key_field_id`) — supports FK
- **Index:** `ix_import_jobs_error_report_file_id` (`error_report_file_id`) — supports FK
- **Index:** `ix_import_jobs_justification_id` (`justification_id`) — supports FK
- **Foreign key:** `fk_import_jobs_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_import_jobs_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_import_jobs_user_id`: `user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_import_jobs_file_id`: `file_id` → `files`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_import_jobs_import_mapping_id`: `import_mapping_id` → `import_mappings`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_import_jobs_key_field_id`: `key_field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_import_jobs_error_report_file_id`: `error_report_file_id` → `files`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_import_jobs_justification_id`: `justification_id` → `justifications`(`id`) ON DELETE NO ACTION
- **Check:** `ck_import_jobs_mode`: `mode IN ('insert', 'update', 'upsert')` (both engines)
- **Check:** `ck_import_jobs_status`: `status IN ('queued', 'validating', 'running', 'completed', 'completed_with_errors', 'failed', 'cancelled')` (both engines)
- **Check (SQL Server):** `ck_import_jobs_mapping_json`: `ISJSON(mapping) = 1`

**`export_jobs`** (supporting — built-in exports)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `view_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `views.id` NO ACTION |
| `user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `format` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `xlsx`, `csv`, `pdf` |
| `columns` | JSON | NVARCHAR(MAX) | NOT NULL | — | effective (permission-filtered) columns |
| `filters` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `selected_ids` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `queued`, `running`, `completed`, `failed`, `expired`, `cancelled` |
| `row_count` | INT | INT | NULL | — |  |
| `progress` | SMALLINT | SMALLINT | NOT NULL | 0 | 0–100 |
| `file_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `files.id` NO ACTION |
| `expires_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `error` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |

- **Primary key:** `pk_export_jobs` (`id`); SQL Server clustered.
- **Unique:** `uq_export_jobs_uuid` (`uuid`)
- **Index:** `ix_export_jobs_user_id_created_at` (`user_id`, `created_at`)
- **Index:** `ix_export_jobs_organization_id_status` (`organization_id`, `status`)
- **Index:** `ix_export_jobs_form_id` (`form_id`) — supports FK
- **Index:** `ix_export_jobs_view_id` (`view_id`) — supports FK
- **Index:** `ix_export_jobs_file_id` (`file_id`) — supports FK
- **Foreign key:** `fk_export_jobs_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_export_jobs_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_export_jobs_view_id`: `view_id` → `views`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_export_jobs_user_id`: `user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_export_jobs_file_id`: `file_id` → `files`(`id`) ON DELETE NO ACTION
- **Check:** `ck_export_jobs_format`: `format IN ('xlsx', 'csv', 'pdf')` (both engines)
- **Check:** `ck_export_jobs_status`: `status IN ('queued', 'running', 'completed', 'failed', 'expired', 'cancelled')` (both engines)
- **Check (SQL Server):** `ck_export_jobs_columns_json`: `ISJSON(columns) = 1`
- **Check (SQL Server):** `ck_export_jobs_filters_json`: `ISJSON(filters) = 1`
- **Check (SQL Server):** `ck_export_jobs_selected_ids_json`: `ISJSON(selected_ids) = 1`

### 10.10 Custom downloads

```mermaid
erDiagram
  forms ||--o{ download_profiles : base
  download_profiles ||--o{ download_profile_columns : has
  download_profiles ||--o{ download_profile_filters : has
  download_profiles ||--o{ download_schedules : scheduled
  download_profiles ||--o{ download_jobs : produces
  download_schedules ||--o{ download_jobs : triggers
```

**`download_profiles`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `deleted_at` | DATETIME(6) | DATETIME2(6) | NULL | — | soft delete |
| `deleted_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION (base form) |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `is_personal` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `owner_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION (personal profiles) |
| `formats` | JSON | NVARCHAR(MAX) | NOT NULL | — | subset of `xlsx`,`csv`,`pdf` |
| `sheet_mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `single`, `per_related_form` |
| `file_name_pattern` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — | placeholders `{form}`, `{date:yyyyMMdd}`, `{user}`, `{param.x}` |
| `xlsx_options` | JSON | NVARCHAR(MAX) | NOT NULL | — | styled headers, frozen header, logo, title row, RTL for Arabic |
| `csv_options` | JSON | NVARCHAR(MAX) | NOT NULL | — | delimiter, UTF-8 BOM (always on for Arabic locales) |
| `pdf_options` | JSON | NVARCHAR(MAX) | NOT NULL | — | orientation, header/footer, page numbers |
| `apply_user_filters` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `allow_selected_records` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `available_in` | JSON | NVARCHAR(MAX) | NOT NULL | — | `table_toolbar`, `record_view` |
| `parameters` | JSON | NVARCHAR(MAX) | NOT NULL | — | runtime parameter definitions `[{key, type, required, default}]` |
| `max_rows` | INT | INT | NOT NULL | 0 |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_download_profiles` (`id`); SQL Server clustered.
- **Unique:** `uq_download_profiles_uuid` (`uuid`)
- **Unique:** `uq_download_profiles_form_id_key` (`form_id`, `key`)
- **Index:** `ix_download_profiles_owner_user_id` (`owner_user_id`)
- **Index:** `ix_download_profiles_organization_id_deleted_at` (`organization_id`, `deleted_at`)
- **Index:** `ix_download_profiles_created_by` (`created_by`) — supports FK
- **Index:** `ix_download_profiles_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_download_profiles_deleted_by` (`deleted_by`) — supports FK
- **Foreign key:** `fk_download_profiles_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_download_profiles_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_download_profiles_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_download_profiles_deleted_by`: `deleted_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_download_profiles_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_download_profiles_owner_user_id`: `owner_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_download_profiles_sheet_mode`: `sheet_mode IN ('single', 'per_related_form')` (both engines)
- **Check (SQL Server):** `ck_download_profiles_formats_json`: `ISJSON(formats) = 1`
- **Check (SQL Server):** `ck_download_profiles_xlsx_options_json`: `ISJSON(xlsx_options) = 1`
- **Check (SQL Server):** `ck_download_profiles_csv_options_json`: `ISJSON(csv_options) = 1`
- **Check (SQL Server):** `ck_download_profiles_pdf_options_json`: `ISJSON(pdf_options) = 1`
- **Check (SQL Server):** `ck_download_profiles_available_in_json`: `ISJSON(available_in) = 1`
- **Check (SQL Server):** `ck_download_profiles_parameters_json`: `ISJSON(parameters) = 1`
- Translatable: `name`, `description`, `parameters.<key>.label`. Permission: `download.{uuid}.use`.

**`download_profile_columns`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `download_profile_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `download_profiles.id` CASCADE |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `kind` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `field`, `calculated`, `static`, `system`, `justification` |
| `path` | JSON | NVARCHAR(MAX) | NULL | — | full relation path, both directions |
| `path_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `system_column` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | values: `record_id`, `status`, `created_by`, `created_at`, `updated_by`, `updated_at`, `last_transition_at` |
| `expression` | JSON | NVARCHAR(MAX) | NULL | — | AST for calculated columns |
| `static_value` | VARCHAR(1024) | NVARCHAR(1024) | NULL | — |  |
| `to_many_mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | when the path crosses a one-to-many hop; values: `flatten`, `aggregate`, `separate_sheet` |
| `aggregate_fn` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | values: `count`, `sum`, `avg`, `min`, `max`, `first`, `last`, `join` |
| `join_separator` | VARCHAR(16) | NVARCHAR(16) | NULL | — |  |
| `sheet_key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `width` | SMALLINT | SMALLINT | NULL | — |  |
| `format` | JSON | NVARCHAR(MAX) | NULL | — | date/number/currency/digits |

- **Primary key:** `pk_download_profile_columns` (`id`); SQL Server clustered.
- **Unique:** `uq_download_profile_columns_uuid` (`uuid`)
- **Index:** `ix_download_profile_columns_download_profile_id_sort_order` (`download_profile_id`, `sort_order`)
- **Foreign key:** `fk_download_profile_columns_download_profile_id`: `download_profile_id` → `download_profiles`(`id`) ON DELETE CASCADE
- **Check:** `ck_download_profile_columns_kind`: `kind IN ('field', 'calculated', 'static', 'system', 'justification')` (both engines)
- **Check:** `ck_download_profile_columns_system_column`: `system_column IN ('record_id', 'status', 'created_by', 'created_at', 'updated_by', 'updated_at', 'last_transition_at')` (both engines)
- **Check:** `ck_download_profile_columns_to_many_mode`: `to_many_mode IN ('flatten', 'aggregate', 'separate_sheet')` (both engines)
- **Check:** `ck_download_profile_columns_aggregate_fn`: `aggregate_fn IN ('count', 'sum', 'avg', 'min', 'max', 'first', 'last', 'join')` (both engines)
- **Check (SQL Server):** `ck_download_profile_columns_path_json`: `ISJSON(path) = 1`
- **Check (SQL Server):** `ck_download_profile_columns_expression_json`: `ISJSON(expression) = 1`
- **Check (SQL Server):** `ck_download_profile_columns_format_json`: `ISJSON(format) = 1`
- Translatable: `header`.

**`download_profile_filters`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `download_profile_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `download_profiles.id` CASCADE |
| `path` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `operator` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `value` | JSON | NVARCHAR(MAX) | NULL | — | fixed value |
| `value_expression` | JSON | NVARCHAR(MAX) | NULL | — | AST (e.g. `today() - 30`) |
| `parameter_key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NULL | — | bound to a runtime parameter |
| `sort_order` | INT | INT | NOT NULL | 0 |  |

- **Primary key:** `pk_download_profile_filters` (`id`); SQL Server clustered.
- **Unique:** `uq_download_profile_filters_uuid` (`uuid`)
- **Index:** `ix_download_profile_filters_download_profile_id` (`download_profile_id`)
- **Foreign key:** `fk_download_profile_filters_download_profile_id`: `download_profile_id` → `download_profiles`(`id`) ON DELETE CASCADE
- **Check (SQL Server):** `ck_download_profile_filters_path_json`: `ISJSON(path) = 1`
- **Check (SQL Server):** `ck_download_profile_filters_value_json`: `ISJSON(value) = 1`
- **Check (SQL Server):** `ck_download_profile_filters_value_expression_json`: `ISJSON(value_expression) = 1`

**`download_schedules`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `download_profile_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `download_profiles.id` CASCADE |
| `frequency` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `daily`, `weekly`, `monthly`, `cron` |
| `cron_expression` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `timezone` | VARCHAR(64) | NVARCHAR(64) | NOT NULL | — |  |
| `format` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `xlsx`, `csv`, `pdf` |
| `parameters` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `recipients` | JSON | NVARCHAR(MAX) | NOT NULL | — | `{users:[uuid], roles:[uuid]}` — each recipient receives only data they can access (run per recipient) |
| `run_as_policy` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | fixed: never a shared identity; values: `per_recipient` |
| `scheduled_task_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `scheduled_tasks.id` NO ACTION |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_download_schedules` (`id`); SQL Server clustered.
- **Unique:** `uq_download_schedules_uuid` (`uuid`)
- **Index:** `ix_download_schedules_download_profile_id` (`download_profile_id`)
- **Index:** `ix_download_schedules_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_download_schedules_created_by` (`created_by`) — supports FK
- **Index:** `ix_download_schedules_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_download_schedules_scheduled_task_id` (`scheduled_task_id`) — supports FK
- **Foreign key:** `fk_download_schedules_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_download_schedules_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_download_schedules_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_download_schedules_download_profile_id`: `download_profile_id` → `download_profiles`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_download_schedules_scheduled_task_id`: `scheduled_task_id` → `scheduled_tasks`(`id`) ON DELETE NO ACTION
- **Check:** `ck_download_schedules_frequency`: `frequency IN ('daily', 'weekly', 'monthly', 'cron')` (both engines)
- **Check:** `ck_download_schedules_format`: `format IN ('xlsx', 'csv', 'pdf')` (both engines)
- **Check:** `ck_download_schedules_run_as_policy`: `run_as_policy IN ('per_recipient')` (both engines)
- **Check (SQL Server):** `ck_download_schedules_parameters_json`: `ISJSON(parameters) = 1`
- **Check (SQL Server):** `ck_download_schedules_recipients_json`: `ISJSON(recipients) = 1`

**`download_jobs`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `download_profile_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `download_profiles.id` NO ACTION |
| `download_schedule_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `download_schedules.id` NO ACTION |
| `user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `format` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `xlsx`, `csv`, `pdf` |
| `parameters` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `filters_snapshot` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `selected_ids` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `effective_columns` | JSON | NVARCHAR(MAX) | NOT NULL | — | after field-level permission filtering |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `queued`, `running`, `completed`, `failed`, `expired`, `cancelled` |
| `progress` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `row_count` | INT | INT | NULL | — |  |
| `file_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `files.id` NO ACTION |
| `expires_at` | DATETIME(6) | DATETIME2(6) | NULL | — | signed link expiry |
| `attempts` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `error` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `started_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `finished_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |

- **Primary key:** `pk_download_jobs` (`id`); SQL Server clustered.
- **Unique:** `uq_download_jobs_uuid` (`uuid`)
- **Index:** `ix_download_jobs_user_id_created_at` (`user_id`, `created_at`)
- **Index:** `ix_download_jobs_organization_id_status` (`organization_id`, `status`)
- **Index:** `ix_download_jobs_download_profile_id_created_at` (`download_profile_id`, `created_at`)
- **Index:** `ix_download_jobs_download_schedule_id` (`download_schedule_id`) — supports FK
- **Index:** `ix_download_jobs_file_id` (`file_id`) — supports FK
- **Foreign key:** `fk_download_jobs_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_download_jobs_download_profile_id`: `download_profile_id` → `download_profiles`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_download_jobs_download_schedule_id`: `download_schedule_id` → `download_schedules`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_download_jobs_user_id`: `user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_download_jobs_file_id`: `file_id` → `files`(`id`) ON DELETE NO ACTION
- **Check:** `ck_download_jobs_format`: `format IN ('xlsx', 'csv', 'pdf')` (both engines)
- **Check:** `ck_download_jobs_status`: `status IN ('queued', 'running', 'completed', 'failed', 'expired', 'cancelled')` (both engines)
- **Check (SQL Server):** `ck_download_jobs_parameters_json`: `ISJSON(parameters) = 1`
- **Check (SQL Server):** `ck_download_jobs_filters_snapshot_json`: `ISJSON(filters_snapshot) = 1`
- **Check (SQL Server):** `ck_download_jobs_selected_ids_json`: `ISJSON(selected_ids) = 1`
- **Check (SQL Server):** `ck_download_jobs_effective_columns_json`: `ISJSON(effective_columns) = 1`

### 10.11 Justification & change control

**`justification_rules`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `scope` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `form`, `group`, `field`, `status`, `action`, `transition`, `delete`, `restore`, `import`, `bulk`, `reassign`, `merge` |
| `group_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `field_groups.id` NO ACTION |
| `field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION |
| `status_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `statuses.id` NO ACTION ("once reached") |
| `action_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `actions.id` NO ACTION |
| `transition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `transitions.id` NO ACTION |
| `subject_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `everyone`, `role`, `department`, `user` |
| `subject_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `level` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `not_required`, `optional`, `mandatory` |
| `condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION |
| `level_when_condition` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | level applied when the condition is true; values: `not_required`, `optional`, `mandatory` |
| `min_length` | SMALLINT | SMALLINT | NULL | — |  |
| `max_length` | SMALLINT | SMALLINT | NULL | — |  |
| `reason_code_mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `none`, `optional`, `required` |
| `reason_code_source` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `codes`, `collection` |
| `reason_code_set` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NULL | — | set key in `justification_reason_codes` |
| `reason_code_collection_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION (kind=collection) |
| `attachments_mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `none`, `optional`, `required` |
| `max_attachments` | SMALLINT | SMALLINT | NULL | — |  |
| `attachment_rules` | JSON | NVARCHAR(MAX) | NULL | — | file rules (§4.6) |
| `show_change_summary` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 | default rules none → off by default |

- **Primary key:** `pk_justification_rules` (`id`); SQL Server clustered.
- **Unique:** `uq_justification_rules_uuid` (`uuid`)
- **Index:** `ix_justification_rules_form_id_scope` (`form_id`, `scope`)
- **Index:** `ix_justification_rules_form_id_field_id` (`form_id`, `field_id`)
- **Index:** `ix_justification_rules_form_id_transition_id` (`form_id`, `transition_id`)
- **Index:** `ix_justification_rules_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_justification_rules_created_by` (`created_by`) — supports FK
- **Index:** `ix_justification_rules_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_justification_rules_group_id` (`group_id`) — supports FK
- **Index:** `ix_justification_rules_field_id` (`field_id`) — supports FK
- **Index:** `ix_justification_rules_status_id` (`status_id`) — supports FK
- **Index:** `ix_justification_rules_action_id` (`action_id`) — supports FK
- **Index:** `ix_justification_rules_transition_id` (`transition_id`) — supports FK
- **Index:** `ix_justification_rules_condition_id` (`condition_id`) — supports FK
- **Index:** `ix_justification_rules_reason_code_collection_id` (`reason_code_collection_id`) — supports FK
- **Foreign key:** `fk_justification_rules_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justification_rules_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justification_rules_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justification_rules_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_justification_rules_group_id`: `group_id` → `field_groups`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justification_rules_field_id`: `field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justification_rules_status_id`: `status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justification_rules_action_id`: `action_id` → `actions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justification_rules_transition_id`: `transition_id` → `transitions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justification_rules_condition_id`: `condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justification_rules_reason_code_collection_id`: `reason_code_collection_id` → `forms`(`id`) ON DELETE NO ACTION
- **Check:** `ck_justification_rules_scope`: `scope IN ('form', 'group', 'field', 'status', 'action', 'transition', 'delete', 'restore', 'import', 'bulk', 'reassign', 'merge')` (both engines)
- **Check:** `ck_justification_rules_subject_type`: `subject_type IN ('everyone', 'role', 'department', 'user')` (both engines)
- **Check:** `ck_justification_rules_level`: `level IN ('not_required', 'optional', 'mandatory')` (both engines)
- **Check:** `ck_justification_rules_level_when_condition`: `level_when_condition IN ('not_required', 'optional', 'mandatory')` (both engines)
- **Check:** `ck_justification_rules_reason_code_mode`: `reason_code_mode IN ('none', 'optional', 'required')` (both engines)
- **Check:** `ck_justification_rules_reason_code_source`: `reason_code_source IN ('codes', 'collection')` (both engines)
- **Check:** `ck_justification_rules_attachments_mode`: `attachments_mode IN ('none', 'optional', 'required')` (both engines)
- **Check (SQL Server):** `ck_justification_rules_attachment_rules_json`: `ISJSON(attachment_rules) = 1`
- Translatable: `prompt_title`, `help_text`.

**`justification_reason_codes`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `set_key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `code` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `requires_note` | TINYINT(1) | BIT | NOT NULL | 0 | e.g. "Other" |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_justification_reason_codes` (`id`); SQL Server clustered.
- **Unique:** `uq_justification_reason_codes_uuid` (`uuid`)
- **Unique:** `uq_justification_reason_codes_organization_id_set_key_code` (`organization_id`, `set_key`, `code`)
- **Index:** `ix_justification_reason_codes_created_by` (`created_by`) — supports FK
- **Index:** `ix_justification_reason_codes_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_justification_reason_codes_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justification_reason_codes_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justification_reason_codes_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- Translatable: `label`.

**`justifications`** — immutable

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `context` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `edit`, `delete`, `restore`, `transition`, `action`, `bulk`, `import`, `reassign`, `merge`, `personal_data`, `manual_sequence_adjust` |
| `rule_ids` | JSON | NVARCHAR(MAX) | NOT NULL | — | rules that demanded it |
| `reason_text` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `reason_code_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `justification_reason_codes.id` NO ACTION |
| `reason_code_record_id` | BIGINT UNSIGNED | BIGINT | NULL | — | when sourced from a collection |
| `reason_code_label_snapshot` | VARCHAR(255) | NVARCHAR(255) | NULL | — | label at time of entry |
| `note` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `changed_fields` | JSON | NVARCHAR(MAX) | NOT NULL | — | field uuids + labels snapshot |
| `affected_count` | INT | INT | NOT NULL | 0 | 1, or N for bulk/import |
| `bulk_operation_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `bulk_operations.id` NO ACTION |
| `import_job_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `import_jobs.id` NO ACTION |
| `user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `on_behalf_of_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `external_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `external_users.id` NO ACTION |
| `locale` | VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(10) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `content_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | integrity hash; also included in the audit entry hash |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |

- **Primary key:** `pk_justifications` (`id`); SQL Server clustered.
- **Unique:** `uq_justifications_uuid` (`uuid`)
- **Index:** `ix_justifications_form_id_record_id_created_at` (`form_id`, `record_id`, `created_at`)
- **Index:** `ix_justifications_user_id_created_at` (`user_id`, `created_at`)
- **Index:** `ix_justifications_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_justifications_reason_code_id` (`reason_code_id`) — supports FK
- **Index:** `ix_justifications_bulk_operation_id` (`bulk_operation_id`) — supports FK
- **Index:** `ix_justifications_import_job_id` (`import_job_id`) — supports FK
- **Index:** `ix_justifications_on_behalf_of_user_id` (`on_behalf_of_user_id`) — supports FK
- **Index:** `ix_justifications_external_user_id` (`external_user_id`) — supports FK
- **Foreign key:** `fk_justifications_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justifications_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justifications_reason_code_id`: `reason_code_id` → `justification_reason_codes`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justifications_bulk_operation_id`: `bulk_operation_id` → `bulk_operations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justifications_import_job_id`: `import_job_id` → `import_jobs`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justifications_user_id`: `user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justifications_on_behalf_of_user_id`: `on_behalf_of_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justifications_external_user_id`: `external_user_id` → `external_users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_justifications_context`: `context IN ('edit', 'delete', 'restore', 'transition', 'action', 'bulk', 'import', 'reassign', 'merge', 'personal_data', 'manual_sequence_adjust')` (both engines)
- **Check (SQL Server):** `ck_justifications_rule_ids_json`: `ISJSON(rule_ids) = 1`
- **Check (SQL Server):** `ck_justifications_changed_fields_json`: `ISJSON(changed_fields) = 1`
- **Note:** no `updated_at`; no update/delete path exists

**`justification_attachments`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `justification_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `justifications.id` NO ACTION |
| `file_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `files.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |

- **Primary key:** `pk_justification_attachments` (`id`); SQL Server clustered.
- **Unique:** `uq_justification_attachments_justification_id_file_id` (`justification_id`, `file_id`)
- **Index:** `ix_justification_attachments_file_id` (`file_id`) — supports FK
- **Foreign key:** `fk_justification_attachments_justification_id`: `justification_id` → `justifications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_justification_attachments_file_id`: `file_id` → `files`(`id`) ON DELETE NO ACTION

### 10.12 Assignment, queues & delegation

```mermaid
erDiagram
  transitions ||--o{ assignment_rules : assigns
  assignment_rules ||--o{ assignments : creates
  queues ||--o{ queue_forms : includes
  queues ||--o{ queue_claims : claimed
  assignments ||--o{ queue_claims : claim
  users ||--o{ delegations : delegator
  transitions ||--o{ approval_requests : requires
  approval_requests ||--o{ approval_decisions : collects
```

**`assignments`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `assignee_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `user`, `role`, `department` |
| `assignee_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `assignment_rule_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `assignment_rules.id` NO ACTION |
| `transition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `transitions.id` NO ACTION |
| `approval_request_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `approval_requests.id` NO ACTION |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `active`, `completed`, `reassigned`, `cancelled` |
| `priority` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `due_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `assigned_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `on_behalf_of_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `justification_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `justifications.id` NO ACTION |
| `completed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_assignments` (`id`); SQL Server clustered.
- **Unique:** `uq_assignments_uuid` (`uuid`)
- **Index:** `ix_assignments_assignee_type_assignee_id_status_due_at` (`assignee_type`, `assignee_id`, `status`, `due_at`)
- **Index:** `ix_assignments_form_id_record_id_status` (`form_id`, `record_id`, `status`)
- **Index:** `ix_assignments_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_assignments_assignment_rule_id` (`assignment_rule_id`) — supports FK
- **Index:** `ix_assignments_transition_id` (`transition_id`) — supports FK
- **Index:** `ix_assignments_approval_request_id` (`approval_request_id`) — supports FK
- **Index:** `ix_assignments_assigned_by` (`assigned_by`) — supports FK
- **Index:** `ix_assignments_on_behalf_of_user_id` (`on_behalf_of_user_id`) — supports FK
- **Index:** `ix_assignments_justification_id` (`justification_id`) — supports FK
- **Foreign key:** `fk_assignments_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_assignments_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_assignments_assignment_rule_id`: `assignment_rule_id` → `assignment_rules`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_assignments_transition_id`: `transition_id` → `transitions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_assignments_approval_request_id`: `approval_request_id` → `approval_requests`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_assignments_assigned_by`: `assigned_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_assignments_on_behalf_of_user_id`: `on_behalf_of_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_assignments_justification_id`: `justification_id` → `justifications`(`id`) ON DELETE NO ACTION
- **Check:** `ck_assignments_assignee_type`: `assignee_type IN ('user', 'role', 'department')` (both engines)
- **Check:** `ck_assignments_status`: `status IN ('active', 'completed', 'reassigned', 'cancelled')` (both engines)

**`assignment_rules`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `transition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `transitions.id` NO ACTION; NULL = on create |
| `strategy` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `user`, `role`, `department`, `field_user`, `creator_manager`, `round_robin`, `least_loaded` |
| `target_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | values: `user`, `role`, `department` |
| `target_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION (`field_user`) |
| `condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION |
| `due_in_minutes` | INT | INT | NULL | — |  |
| `use_working_time` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `priority` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `round_robin_cursor_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION (updated under row lock) |
| `sort_order` | INT | INT | NOT NULL | 0 | first matching rule applies |

- **Primary key:** `pk_assignment_rules` (`id`); SQL Server clustered.
- **Unique:** `uq_assignment_rules_uuid` (`uuid`)
- **Index:** `ix_assignment_rules_form_id_transition_id_sort_order` (`form_id`, `transition_id`, `sort_order`)
- **Index:** `ix_assignment_rules_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_assignment_rules_created_by` (`created_by`) — supports FK
- **Index:** `ix_assignment_rules_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_assignment_rules_transition_id` (`transition_id`) — supports FK
- **Index:** `ix_assignment_rules_field_id` (`field_id`) — supports FK
- **Index:** `ix_assignment_rules_condition_id` (`condition_id`) — supports FK
- **Index:** `ix_assignment_rules_round_robin_cursor_user_id` (`round_robin_cursor_user_id`) — supports FK
- **Foreign key:** `fk_assignment_rules_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_assignment_rules_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_assignment_rules_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_assignment_rules_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_assignment_rules_transition_id`: `transition_id` → `transitions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_assignment_rules_field_id`: `field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_assignment_rules_condition_id`: `condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_assignment_rules_round_robin_cursor_user_id`: `round_robin_cursor_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_assignment_rules_strategy`: `strategy IN ('user', 'role', 'department', 'field_user', 'creator_manager', 'round_robin', 'least_loaded')` (both engines)
- **Check:** `ck_assignment_rules_target_type`: `target_type IN ('user', 'role', 'department')` (both engines)

**`queues`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `role`, `department` |
| `role_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `roles.id` NO ACTION |
| `department_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `departments.id` NO ACTION |
| `claim_timeout_minutes` | INT | INT | NULL | — | auto-release |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_queues` (`id`); SQL Server clustered.
- **Unique:** `uq_queues_uuid` (`uuid`)
- **Unique:** `uq_queues_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_queues_created_by` (`created_by`) — supports FK
- **Index:** `ix_queues_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_queues_role_id` (`role_id`) — supports FK
- **Index:** `ix_queues_department_id` (`department_id`) — supports FK
- **Foreign key:** `fk_queues_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_queues_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_queues_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_queues_role_id`: `role_id` → `roles`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_queues_department_id`: `department_id` → `departments`(`id`) ON DELETE NO ACTION
- **Check:** `ck_queues_type`: `type IN ('role', 'department')` (both engines)
- Translatable: `name`.

**`queue_forms`** (supporting — forms and columns shown in queues)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `queue_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `queues.id` CASCADE |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `columns` | JSON | NVARCHAR(MAX) | NOT NULL | — | relation paths shown |
| `sort_order` | INT | INT | NOT NULL | 0 |  |

- **Primary key:** `pk_queue_forms` (`id`); SQL Server clustered.
- **Unique:** `uq_queue_forms_queue_id_form_id` (`queue_id`, `form_id`)
- **Index:** `ix_queue_forms_form_id` (`form_id`) — supports FK
- **Foreign key:** `fk_queue_forms_queue_id`: `queue_id` → `queues`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_queue_forms_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_queue_forms_columns_json`: `ISJSON(columns) = 1`

**`queue_claims`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `queue_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `queues.id` NO ACTION |
| `assignment_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `assignments.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `claimed_by` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `claimed_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `released_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `release_reason` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | values: `released`, `completed`, `timeout`, `reassigned`, `admin` |
| `active_key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NULL | — | `{form_id}:{record_id}` while active, NULL after release; **unique** → one active claim per record |

- **Primary key:** `pk_queue_claims` (`id`); SQL Server clustered.
- **Unique:** `uq_queue_claims_active_key` (`active_key`) — SQL Server: filtered `WHERE active_key IS NOT NULL`; MySQL: unique (NULLs never collide)
- **Index:** `ix_queue_claims_claimed_by_released_at` (`claimed_by`, `released_at`)
- **Index:** `ix_queue_claims_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_queue_claims_queue_id` (`queue_id`) — supports FK
- **Index:** `ix_queue_claims_assignment_id` (`assignment_id`) — supports FK
- **Index:** `ix_queue_claims_form_id` (`form_id`) — supports FK
- **Foreign key:** `fk_queue_claims_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_queue_claims_queue_id`: `queue_id` → `queues`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_queue_claims_assignment_id`: `assignment_id` → `assignments`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_queue_claims_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_queue_claims_claimed_by`: `claimed_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_queue_claims_release_reason`: `release_reason IN ('released', 'completed', 'timeout', 'reassigned', 'admin')` (both engines)

**`delegations`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `delegator_user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `delegate_user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | out_of_office set by admin; values: `delegation`, `out_of_office` |
| `starts_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `ends_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `reason` | TEXT | NVARCHAR(MAX) | NOT NULL | — |  |
| `form_ids` | JSON | NVARCHAR(MAX) | NULL | — | NULL = all forms permitted |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `scheduled`, `active`, `expired`, `revoked` |
| `revoked_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `revoked_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |

- **Primary key:** `pk_delegations` (`id`); SQL Server clustered.
- **Unique:** `uq_delegations_uuid` (`uuid`)
- **Index:** `ix_delegations_delegator_user_id_status_starts_at` (`delegator_user_id`, `status`, `starts_at`)
- **Index:** `ix_delegations_delegate_user_id_status` (`delegate_user_id`, `status`)
- **Index:** `ix_delegations_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_delegations_created_by` (`created_by`) — supports FK
- **Index:** `ix_delegations_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_delegations_revoked_by` (`revoked_by`) — supports FK
- **Foreign key:** `fk_delegations_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_delegations_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_delegations_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_delegations_delegator_user_id`: `delegator_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_delegations_delegate_user_id`: `delegate_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_delegations_revoked_by`: `revoked_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_delegations_type`: `type IN ('delegation', 'out_of_office')` (both engines)
- **Check:** `ck_delegations_status`: `status IN ('scheduled', 'active', 'expired', 'revoked')` (both engines)
- **Check (SQL Server):** `ck_delegations_form_ids_json`: `ISJSON(form_ids) = 1`

**`approval_requests`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `transition_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `transitions.id` NO ACTION |
| `mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `all`, `any_n`, `quorum` |
| `required_count` | SMALLINT | SMALLINT | NULL | — |  |
| `quorum_weight` | DECIMAL(9,2) | DECIMAL(9,2) | NULL | — |  |
| `rejection_behavior` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `immediate`, `wait_all` |
| `rejection_status_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `statuses.id` NO ACTION |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `pending`, `approved`, `rejected`, `cancelled`, `expired` |
| `requested_by` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `record_row_version` | BIGINT | BIGINT | NOT NULL | — | version at request time |
| `due_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `completed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_approval_requests` (`id`); SQL Server clustered.
- **Unique:** `uq_approval_requests_uuid` (`uuid`)
- **Index:** `ix_approval_requests_form_id_record_id_status` (`form_id`, `record_id`, `status`)
- **Index:** `ix_approval_requests_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_approval_requests_transition_id` (`transition_id`) — supports FK
- **Index:** `ix_approval_requests_rejection_status_id` (`rejection_status_id`) — supports FK
- **Index:** `ix_approval_requests_requested_by` (`requested_by`) — supports FK
- **Foreign key:** `fk_approval_requests_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_approval_requests_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_approval_requests_transition_id`: `transition_id` → `transitions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_approval_requests_rejection_status_id`: `rejection_status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_approval_requests_requested_by`: `requested_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_approval_requests_mode`: `mode IN ('all', 'any_n', 'quorum')` (both engines)
- **Check:** `ck_approval_requests_rejection_behavior`: `rejection_behavior IN ('immediate', 'wait_all')` (both engines)
- **Check:** `ck_approval_requests_status`: `status IN ('pending', 'approved', 'rejected', 'cancelled', 'expired')` (both engines)

**`approval_decisions`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `approval_request_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `approval_requests.id` CASCADE |
| `approver_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `user`, `role`, `department` |
| `approver_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `weight` | DECIMAL(9,2) | DECIMAL(9,2) | NOT NULL | — |  |
| `decision` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `pending`, `approved`, `rejected` |
| `decided_by_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `on_behalf_of_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `comment` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `decided_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `reminded_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_approval_decisions` (`id`); SQL Server clustered.
- **Unique:** `uq_approval_decisions_approval_request_id_approver__8af1a9f1` (`approval_request_id`, `approver_type`, `approver_id`)
- **Index:** `ix_approval_decisions_approver_type_approver_id_decision` (`approver_type`, `approver_id`, `decision`)
- **Index:** `ix_approval_decisions_decided_by_user_id` (`decided_by_user_id`) — supports FK
- **Index:** `ix_approval_decisions_on_behalf_of_user_id` (`on_behalf_of_user_id`) — supports FK
- **Foreign key:** `fk_approval_decisions_approval_request_id`: `approval_request_id` → `approval_requests`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_approval_decisions_decided_by_user_id`: `decided_by_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_approval_decisions_on_behalf_of_user_id`: `on_behalf_of_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_approval_decisions_approver_type`: `approver_type IN ('user', 'role', 'department')` (both engines)
- **Check:** `ck_approval_decisions_decision`: `decision IN ('pending', 'approved', 'rejected')` (both engines)

### 10.13 Notifications

**`notification_rules`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `trigger` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `record_created`, `record_updated`, `status_changed`, `field_changed`, `condition_met`, `scheduled_reminder`, `sla_warning`, `sla_escalation`, `approval_requested`, `assigned` |
| `from_status_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `statuses.id` NO ACTION |
| `to_status_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `statuses.id` NO ACTION |
| `field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION |
| `condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION |
| `schedule` | JSON | NVARCHAR(MAX) | NULL | — | reminder: `{date_field_uuid, offset_minutes}` or cron |
| `email_template_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `email_templates.id` NO ACTION |
| `channels` | JSON | NVARCHAR(MAX) | NOT NULL | — | `["email","in_app","sms",…]` channel keys |
| `recipients` | JSON | NVARCHAR(MAX) | NOT NULL | — | `{to:[…], cc:[…], bcc:[…]}`, items `{type: user\|role\|department\|field_user\|creator\|linked_record_users\|static, ref}` |
| `attachments` | JSON | NVARCHAR(MAX) | NULL | — | document template uuids, record file fields |
| `delay_minutes` | INT | INT | NULL | — |  |
| `respect_user_preferences` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_notification_rules` (`id`); SQL Server clustered.
- **Unique:** `uq_notification_rules_uuid` (`uuid`)
- **Index:** `ix_notification_rules_form_id_trigger_is_active` (`form_id`, `trigger`, `is_active`)
- **Index:** `ix_notification_rules_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_notification_rules_created_by` (`created_by`) — supports FK
- **Index:** `ix_notification_rules_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_notification_rules_from_status_id` (`from_status_id`) — supports FK
- **Index:** `ix_notification_rules_to_status_id` (`to_status_id`) — supports FK
- **Index:** `ix_notification_rules_field_id` (`field_id`) — supports FK
- **Index:** `ix_notification_rules_condition_id` (`condition_id`) — supports FK
- **Index:** `ix_notification_rules_email_template_id` (`email_template_id`) — supports FK
- **Foreign key:** `fk_notification_rules_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_notification_rules_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_notification_rules_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_notification_rules_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_notification_rules_from_status_id`: `from_status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_notification_rules_to_status_id`: `to_status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_notification_rules_field_id`: `field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_notification_rules_condition_id`: `condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_notification_rules_email_template_id`: `email_template_id` → `email_templates`(`id`) ON DELETE NO ACTION
- **Check:** `ck_notification_rules_trigger`: `trigger IN ('record_created', 'record_updated', 'status_changed', 'field_changed', 'condition_met', 'scheduled_reminder', 'sla_warning', 'sla_escalation', 'approval_requested', 'assigned')` (both engines)
- **Check (SQL Server):** `ck_notification_rules_schedule_json`: `ISJSON(schedule) = 1`
- **Check (SQL Server):** `ck_notification_rules_channels_json`: `ISJSON(channels) = 1`
- **Check (SQL Server):** `ck_notification_rules_recipients_json`: `ISJSON(recipients) = 1`
- **Check (SQL Server):** `ck_notification_rules_attachments_json`: `ISJSON(attachments) = 1`
- Translatable: `name`.

**`email_templates`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `application_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `applications.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION (placeholder context) |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `design` | JSON | NVARCHAR(MAX) | NOT NULL | — | visual editor document (blocks, styles, conditional blocks, repeater tables) |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_email_templates` (`id`); SQL Server clustered.
- **Unique:** `uq_email_templates_uuid` (`uuid`)
- **Unique:** `uq_email_templates_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_email_templates_created_by` (`created_by`) — supports FK
- **Index:** `ix_email_templates_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_email_templates_application_id` (`application_id`) — supports FK
- **Index:** `ix_email_templates_form_id` (`form_id`) — supports FK
- **Foreign key:** `fk_email_templates_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_email_templates_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_email_templates_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_email_templates_application_id`: `application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_email_templates_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_email_templates_design_json`: `ISJSON(design) = 1`
- Translatable: `subject`, `body_html` (compiled from `design` per locale), `preheader`.

**`email_queue`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `notification_rule_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `notification_rules.id` NO ACTION |
| `email_template_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `email_templates.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `source` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `rule`, `action`, `automation`, `system`, `test`, `download`, `alert` |
| `locale` | VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(10) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `to` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `cc` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `bcc` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `subject` | VARCHAR(998) | NVARCHAR(998) | NOT NULL | — |  |
| `body_html` | LONGTEXT | NVARCHAR(MAX) | NOT NULL | — | rendered, sanitized |
| `attachment_file_ids` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | Stuck = `pending` older than `settings.operations.stuck_email_minutes` (computed); values: `pending`, `sending`, `sent`, `failed`, `cancelled` |
| `attempts` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `max_attempts` | SMALLINT | SMALLINT | NOT NULL | — |  |
| `next_attempt_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `last_error` | TEXT | NVARCHAR(MAX) | NULL | — | exact SMTP failure reason |
| `message_id` | VARCHAR(255) | NVARCHAR(255) | NULL | — |  |
| `sent_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `cancelled_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `resent_from_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `email_queue.id` NO ACTION |
| `on_behalf_of_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |

- **Primary key:** `pk_email_queue` (`id`); SQL Server clustered.
- **Unique:** `uq_email_queue_uuid` (`uuid`)
- **Index:** `ix_email_queue_organization_id_status_created_at` (`organization_id`, `status`, `created_at`)
- **Index:** `ix_email_queue_form_id_record_id` (`form_id`, `record_id`)
- **Index:** `ix_email_queue_status_next_attempt_at` (`status`, `next_attempt_at`)
- **Index:** `ix_email_queue_notification_rule_id` (`notification_rule_id`) — supports FK
- **Index:** `ix_email_queue_email_template_id` (`email_template_id`) — supports FK
- **Index:** `ix_email_queue_cancelled_by` (`cancelled_by`) — supports FK
- **Index:** `ix_email_queue_resent_from_id` (`resent_from_id`) — supports FK
- **Index:** `ix_email_queue_on_behalf_of_user_id` (`on_behalf_of_user_id`) — supports FK
- **Foreign key:** `fk_email_queue_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_email_queue_notification_rule_id`: `notification_rule_id` → `notification_rules`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_email_queue_email_template_id`: `email_template_id` → `email_templates`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_email_queue_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_email_queue_cancelled_by`: `cancelled_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_email_queue_resent_from_id`: `resent_from_id` → `email_queue`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_email_queue_on_behalf_of_user_id`: `on_behalf_of_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_email_queue_source`: `source IN ('rule', 'action', 'automation', 'system', 'test', 'download', 'alert')` (both engines)
- **Check:** `ck_email_queue_status`: `status IN ('pending', 'sending', 'sent', 'failed', 'cancelled')` (both engines)
- **Check (SQL Server):** `ck_email_queue_to_json`: `ISJSON(to) = 1`
- **Check (SQL Server):** `ck_email_queue_cc_json`: `ISJSON(cc) = 1`
- **Check (SQL Server):** `ck_email_queue_bcc_json`: `ISJSON(bcc) = 1`
- **Check (SQL Server):** `ck_email_queue_attachment_file_ids_json`: `ISJSON(attachment_file_ids) = 1`

**`in_app_notifications`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` CASCADE |
| `type` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `title` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — | rendered in recipient's locale |
| `body` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `link` | VARCHAR(2048) | NVARCHAR(2048) | NULL | — | app-relative |
| `data` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `notification_rule_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `notification_rules.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `on_behalf_of_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `read_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |

- **Primary key:** `pk_in_app_notifications` (`id`); SQL Server clustered.
- **Unique:** `uq_in_app_notifications_uuid` (`uuid`)
- **Index:** `ix_in_app_notifications_user_id_read_at_created_at` (`user_id`, `read_at`, `created_at`)
- **Index:** `ix_in_app_notifications_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_in_app_notifications_notification_rule_id` (`notification_rule_id`) — supports FK
- **Index:** `ix_in_app_notifications_form_id` (`form_id`) — supports FK
- **Index:** `ix_in_app_notifications_on_behalf_of_user_id` (`on_behalf_of_user_id`) — supports FK
- **Foreign key:** `fk_in_app_notifications_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_in_app_notifications_user_id`: `user_id` → `users`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_in_app_notifications_notification_rule_id`: `notification_rule_id` → `notification_rules`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_in_app_notifications_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_in_app_notifications_on_behalf_of_user_id`: `on_behalf_of_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_in_app_notifications_data_json`: `ISJSON(data) = 1`

**`notification_deliveries`** (supporting — non-email channel log)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `notification_channel_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `notification_channels.id` NO ACTION |
| `notification_rule_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `notification_rules.id` NO ACTION |
| `user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `recipient` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — | masked phone/handle |
| `payload` | JSON | NVARCHAR(MAX) | NOT NULL | — | rendered message |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `pending`, `sent`, `failed`, `cancelled` |
| `attempts` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `error` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `sent_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |

- **Primary key:** `pk_notification_deliveries` (`id`); SQL Server clustered.
- **Index:** `ix_notification_deliveries_organization_id_status_created_at` (`organization_id`, `status`, `created_at`)
- **Index:** `ix_notification_deliveries_notification_channel_id` (`notification_channel_id`) — supports FK
- **Index:** `ix_notification_deliveries_notification_rule_id` (`notification_rule_id`) — supports FK
- **Index:** `ix_notification_deliveries_user_id` (`user_id`) — supports FK
- **Foreign key:** `fk_notification_deliveries_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_notification_deliveries_notification_channel_id`: `notification_channel_id` → `notification_channels`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_notification_deliveries_notification_rule_id`: `notification_rule_id` → `notification_rules`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_notification_deliveries_user_id`: `user_id` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_notification_deliveries_status`: `status IN ('pending', 'sent', 'failed', 'cancelled')` (both engines)
- **Check (SQL Server):** `ck_notification_deliveries_payload_json`: `ISJSON(payload) = 1`

### 10.14 Documents & reports

**`document_templates`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION (placeholder context) |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `docx`, `html` |
| `file_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `files.id` NO ACTION (DOCX source, per locale via `locale_files`) |
| `locale_files` | JSON | NVARCHAR(MAX) | NULL | — | `{locale: file_uuid}` |
| `output_formats` | JSON | NVARCHAR(MAX) | NOT NULL | — | `docx`, `pdf` |
| `paper` | JSON | NVARCHAR(MAX) | NOT NULL | — | size, orientation, margins |
| `placeholders_detected` | JSON | NVARCHAR(MAX) | NOT NULL | — | validated against the form definition |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_document_templates` (`id`); SQL Server clustered.
- **Unique:** `uq_document_templates_uuid` (`uuid`)
- **Unique:** `uq_document_templates_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_document_templates_created_by` (`created_by`) — supports FK
- **Index:** `ix_document_templates_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_document_templates_form_id` (`form_id`) — supports FK
- **Index:** `ix_document_templates_file_id` (`file_id`) — supports FK
- **Foreign key:** `fk_document_templates_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_document_templates_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_document_templates_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_document_templates_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_document_templates_file_id`: `file_id` → `files`(`id`) ON DELETE NO ACTION
- **Check:** `ck_document_templates_type`: `type IN ('docx', 'html')` (both engines)
- **Check (SQL Server):** `ck_document_templates_locale_files_json`: `ISJSON(locale_files) = 1`
- **Check (SQL Server):** `ck_document_templates_output_formats_json`: `ISJSON(output_formats) = 1`
- **Check (SQL Server):** `ck_document_templates_paper_json`: `ISJSON(paper) = 1`
- **Check (SQL Server):** `ck_document_templates_placeholders_detected_json`: `ISJSON(placeholders_detected) = 1`
- Translatable: `name`, `html` (for `type=html`).

**`reports`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `application_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `applications.id` NO ACTION |
| `base_form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `definition` | JSON | NVARCHAR(MAX) | NOT NULL | — | columns (paths), joins via relations, grouping, aggregates, filters, sort, pivot, chart config |
| `visualization` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `table`, `pivot`, `bar`, `line`, `pie`, `area`, `kpi` |
| `is_personal` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `owner_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `cache_ttl_seconds` | INT | INT | NULL | — |  |

- **Primary key:** `pk_reports` (`id`); SQL Server clustered.
- **Unique:** `uq_reports_uuid` (`uuid`)
- **Unique:** `uq_reports_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_reports_base_form_id` (`base_form_id`)
- **Index:** `ix_reports_created_by` (`created_by`) — supports FK
- **Index:** `ix_reports_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_reports_application_id` (`application_id`) — supports FK
- **Index:** `ix_reports_owner_user_id` (`owner_user_id`) — supports FK
- **Foreign key:** `fk_reports_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_reports_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_reports_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_reports_application_id`: `application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_reports_base_form_id`: `base_form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_reports_owner_user_id`: `owner_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_reports_visualization`: `visualization IN ('table', 'pivot', 'bar', 'line', 'pie', 'area', 'kpi')` (both engines)
- **Check (SQL Server):** `ck_reports_definition_json`: `ISJSON(definition) = 1`
- Translatable: `name`, `description`. Permission `report.{uuid}.view`.

**`dashboards`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `application_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `applications.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `layout` | JSON | NVARCHAR(MAX) | NOT NULL | — | grid settings per breakpoint |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_dashboards` (`id`); SQL Server clustered.
- **Unique:** `uq_dashboards_uuid` (`uuid`)
- **Unique:** `uq_dashboards_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_dashboards_created_by` (`created_by`) — supports FK
- **Index:** `ix_dashboards_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_dashboards_application_id` (`application_id`) — supports FK
- **Foreign key:** `fk_dashboards_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_dashboards_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_dashboards_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_dashboards_application_id`: `application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_dashboards_layout_json`: `ISJSON(layout) = 1`
- Translatable: `name`. Permission `dashboard.{uuid}.view`.

**`dashboard_widgets`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `dashboard_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `dashboards.id` CASCADE |
| `type` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | registered widget type (§18) |
| `report_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `reports.id` NO ACTION |
| `config` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `layout` | JSON | NVARCHAR(MAX) | NOT NULL | — | `{x,y,w,h}` per breakpoint |
| `visibility_condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION |
| `sort_order` | INT | INT | NOT NULL | 0 |  |

- **Primary key:** `pk_dashboard_widgets` (`id`); SQL Server clustered.
- **Unique:** `uq_dashboard_widgets_uuid` (`uuid`)
- **Index:** `ix_dashboard_widgets_dashboard_id_sort_order` (`dashboard_id`, `sort_order`)
- **Index:** `ix_dashboard_widgets_report_id` (`report_id`) — supports FK
- **Index:** `ix_dashboard_widgets_visibility_condition_id` (`visibility_condition_id`) — supports FK
- **Foreign key:** `fk_dashboard_widgets_dashboard_id`: `dashboard_id` → `dashboards`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_dashboard_widgets_report_id`: `report_id` → `reports`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_dashboard_widgets_visibility_condition_id`: `visibility_condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_dashboard_widgets_config_json`: `ISJSON(config) = 1`
- **Check (SQL Server):** `ck_dashboard_widgets_layout_json`: `ISJSON(layout) = 1`
- Translatable: `title`.

### 10.15 Reference data, calendars & numbering

**`business_calendars`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `timezone` | VARCHAR(64) | NVARCHAR(64) | NOT NULL | — |  |
| `working_days` | JSON | NVARCHAR(MAX) | NOT NULL | — | `[0..6]` |
| `working_hours` | JSON | NVARCHAR(MAX) | NOT NULL | — | `[{day, start:"08:00", end:"16:00"}]` (multiple spans per day allowed) |
| `country_code` | VARCHAR(2) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(2) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `is_default` | TINYINT(1) | BIT | NOT NULL | 0 |  |

- **Primary key:** `pk_business_calendars` (`id`); SQL Server clustered.
- **Unique:** `uq_business_calendars_uuid` (`uuid`)
- **Unique:** `uq_business_calendars_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_business_calendars_created_by` (`created_by`) — supports FK
- **Index:** `ix_business_calendars_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_business_calendars_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_business_calendars_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_business_calendars_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_business_calendars_working_days_json`: `ISJSON(working_days) = 1`
- **Check (SQL Server):** `ck_business_calendars_working_hours_json`: `ISJSON(working_hours) = 1`
- Translatable: `name`.

**`holidays`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `business_calendar_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `business_calendars.id` CASCADE |
| `starts_on` | DATE | DATE | NOT NULL | — | Gregorian date of the occurrence |
| `ends_on` | DATE | DATE | NOT NULL | — |  |
| `recurrence` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `none`, `yearly_gregorian`, `yearly_hijri` |
| `hijri_month` | SMALLINT | SMALLINT | NULL | — |  |
| `hijri_day` | SMALLINT | SMALLINT | NULL | — |  |

- **Primary key:** `pk_holidays` (`id`); SQL Server clustered.
- **Unique:** `uq_holidays_uuid` (`uuid`)
- **Index:** `ix_holidays_business_calendar_id_starts_on` (`business_calendar_id`, `starts_on`)
- **Foreign key:** `fk_holidays_business_calendar_id`: `business_calendar_id` → `business_calendars`(`id`) ON DELETE CASCADE
- **Check:** `ck_holidays_recurrence`: `recurrence IN ('none', 'yearly_gregorian', 'yearly_hijri')` (both engines)
- Translatable: `name`.

**`number_sequences`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `scope` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `form`, `shared` |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION |
| `pattern` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — | e.g. `{prefix}-{yyyy}-{seq:5}` |
| `prefix` | VARCHAR(32) | NVARCHAR(32) | NULL | — |  |
| `padding` | SMALLINT | SMALLINT | NOT NULL | — |  |
| `step` | INT | INT | NOT NULL | — |  |
| `reset_period` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `never`, `daily`, `monthly`, `yearly` |
| `calendar` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | for date parts and reset boundaries; values: `gregorian`, `hijri` |
| `period_key` | VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(16) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | current period bucket (`2026`, `202610`, …) |
| `current_value` | BIGINT | BIGINT | NOT NULL | — | incremented under `lockForUpdate` |
| `last_adjusted_at` | DATETIME(6) | DATETIME2(6) | NULL | — | manual adjustment (audited + justification) |

- **Primary key:** `pk_number_sequences` (`id`); SQL Server clustered.
- **Unique:** `uq_number_sequences_uuid` (`uuid`)
- **Unique:** `uq_number_sequences_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_number_sequences_form_id_field_id` (`form_id`, `field_id`)
- **Index:** `ix_number_sequences_created_by` (`created_by`) — supports FK
- **Index:** `ix_number_sequences_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_number_sequences_field_id` (`field_id`) — supports FK
- **Foreign key:** `fk_number_sequences_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_number_sequences_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_number_sequences_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_number_sequences_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_number_sequences_field_id`: `field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Check:** `ck_number_sequences_scope`: `scope IN ('form', 'shared')` (both engines)
- **Check:** `ck_number_sequences_reset_period`: `reset_period IN ('never', 'daily', 'monthly', 'yearly')` (both engines)
- **Check:** `ck_number_sequences_calendar`: `calendar IN ('gregorian', 'hijri')` (both engines)

**`currencies`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `code` | VARCHAR(3) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(3) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | ISO 4217 |
| `symbol` | VARCHAR(8) | NVARCHAR(8) | NOT NULL | — |  |
| `decimals` | SMALLINT | SMALLINT | NOT NULL | — |  |
| `rounding` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `half_up`, `half_even`, `down`, `up` |
| `symbol_position` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `before`, `after` |
| `is_base` | TINYINT(1) | BIT | NOT NULL | 0 | one per org |
| `is_enabled` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_currencies` (`id`); SQL Server clustered.
- **Unique:** `uq_currencies_uuid` (`uuid`)
- **Unique:** `uq_currencies_organization_id_code` (`organization_id`, `code`)
- **Index:** `ix_currencies_created_by` (`created_by`) — supports FK
- **Index:** `ix_currencies_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_currencies_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_currencies_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_currencies_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_currencies_rounding`: `rounding IN ('half_up', 'half_even', 'down', 'up')` (both engines)
- **Check:** `ck_currencies_symbol_position`: `symbol_position IN ('before', 'after')` (both engines)
- Translatable: `name`.

**`exchange_rates`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `base_currency_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `currencies.id` NO ACTION |
| `quote_currency_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `currencies.id` NO ACTION |
| `rate` | DECIMAL(20,10) | DECIMAL(20,10) | NOT NULL | — |  |
| `effective_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `source` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | scheduled refresh goes through the egress gateway; values: `manual`, `scheduled` |

- **Primary key:** `pk_exchange_rates` (`id`); SQL Server clustered.
- **Unique:** `uq_exchange_rates_base_currency_id_quote_currency_i_12272837` (`base_currency_id`, `quote_currency_id`, `effective_at`)
- **Index:** `ix_exchange_rates_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_exchange_rates_created_by` (`created_by`) — supports FK
- **Index:** `ix_exchange_rates_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_exchange_rates_quote_currency_id` (`quote_currency_id`) — supports FK
- **Foreign key:** `fk_exchange_rates_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_exchange_rates_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_exchange_rates_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_exchange_rates_base_currency_id`: `base_currency_id` → `currencies`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_exchange_rates_quote_currency_id`: `quote_currency_id` → `currencies`(`id`) ON DELETE NO ACTION
- **Check:** `ck_exchange_rates_source`: `source IN ('manual', 'scheduled')` (both engines)

**`units_of_measure`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `code` | VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(16) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `dimension` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | length, mass, volume, time, area, … |
| `symbol` | VARCHAR(16) | NVARCHAR(16) | NOT NULL | — |  |
| `base_unit_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `units_of_measure.id` NO ACTION |
| `factor` | DECIMAL(30,15) | DECIMAL(30,15) | NOT NULL | — | value_in_base = value × factor + offset |
| `offset` | DECIMAL(30,15) | DECIMAL(30,15) | NOT NULL | — |  |
| `precision` | SMALLINT | SMALLINT | NOT NULL | — |  |

- **Primary key:** `pk_units_of_measure` (`id`); SQL Server clustered.
- **Unique:** `uq_units_of_measure_uuid` (`uuid`)
- **Unique:** `uq_units_of_measure_organization_id_code` (`organization_id`, `code`)
- **Index:** `ix_units_of_measure_created_by` (`created_by`) — supports FK
- **Index:** `ix_units_of_measure_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_units_of_measure_base_unit_id` (`base_unit_id`) — supports FK
- **Foreign key:** `fk_units_of_measure_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_units_of_measure_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_units_of_measure_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_units_of_measure_base_unit_id`: `base_unit_id` → `units_of_measure`(`id`) ON DELETE NO ACTION
- Translatable: `name`.

Shared reference collections (countries, cities, departments-as-data, job titles,
document types) are **collections** (`collections.is_shared_reference = 1`) —
admin-created, never seeded.

### 10.16 Data quality

**`duplicate_rules`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `match_fields` | JSON | NVARCHAR(MAX) | NOT NULL | — | `[{field_uuid, method: exact\|normalized\|fuzzy, threshold?}]` |
| `match_mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `all`, `any` |
| `action` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `warn`, `block` |
| `applies_on` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `create`, `update`, `both` |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `last_sweep_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `last_sweep_operation_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `bulk_operations.id` NO ACTION |

- **Primary key:** `pk_duplicate_rules` (`id`); SQL Server clustered.
- **Unique:** `uq_duplicate_rules_uuid` (`uuid`)
- **Index:** `ix_duplicate_rules_form_id_is_active` (`form_id`, `is_active`)
- **Index:** `ix_duplicate_rules_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_duplicate_rules_created_by` (`created_by`) — supports FK
- **Index:** `ix_duplicate_rules_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_duplicate_rules_last_sweep_operation_id` (`last_sweep_operation_id`) — supports FK
- **Foreign key:** `fk_duplicate_rules_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_duplicate_rules_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_duplicate_rules_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_duplicate_rules_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_duplicate_rules_last_sweep_operation_id`: `last_sweep_operation_id` → `bulk_operations`(`id`) ON DELETE NO ACTION
- **Check:** `ck_duplicate_rules_match_mode`: `match_mode IN ('all', 'any')` (both engines)
- **Check:** `ck_duplicate_rules_action`: `action IN ('warn', 'block')` (both engines)
- **Check:** `ck_duplicate_rules_applies_on`: `applies_on IN ('create', 'update', 'both')` (both engines)
- **Check (SQL Server):** `ck_duplicate_rules_match_fields_json`: `ISJSON(match_fields) = 1`
- Translatable: `name`, `message`.

**`merge_history`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `survivor_record_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `merged_record_ids` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `field_choices` | JSON | NVARCHAR(MAX) | NOT NULL | — | `{field_uuid: source_record_id}` |
| `merged_snapshots` | JSON | NVARCHAR(MAX) | NOT NULL | — | full pre-merge values (sensitive values encrypted) |
| `relations_repointed` | JSON | NVARCHAR(MAX) | NOT NULL | — | `{relation_key: count}` |
| `justification_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `justifications.id` NO ACTION |
| `merged_by` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `merged_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |

- **Primary key:** `pk_merge_history` (`id`); SQL Server clustered.
- **Unique:** `uq_merge_history_uuid` (`uuid`)
- **Index:** `ix_merge_history_form_id_survivor_record_id` (`form_id`, `survivor_record_id`)
- **Index:** `ix_merge_history_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_merge_history_justification_id` (`justification_id`) — supports FK
- **Index:** `ix_merge_history_merged_by` (`merged_by`) — supports FK
- **Foreign key:** `fk_merge_history_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_merge_history_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_merge_history_justification_id`: `justification_id` → `justifications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_merge_history_merged_by`: `merged_by` → `users`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_merge_history_merged_record_ids_json`: `ISJSON(merged_record_ids) = 1`
- **Check (SQL Server):** `ck_merge_history_field_choices_json`: `ISJSON(field_choices) = 1`
- **Check (SQL Server):** `ck_merge_history_merged_snapshots_json`: `ISJSON(merged_snapshots) = 1`
- **Check (SQL Server):** `ck_merge_history_relations_repointed_json`: `ISJSON(relations_repointed) = 1`
- **Note:** immutable

**`bulk_operations`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `update`, `reassign`, `status_change`, `delete`, `restore`, `duplicate_sweep`, `validation_sweep`, `orphan_scan`, `repair` |
| `criteria` | JSON | NVARCHAR(MAX) | NOT NULL | — | filter AST or explicit ids |
| `payload` | JSON | NVARCHAR(MAX) | NULL | — | values / target status / assignee |
| `dry_run` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `preview_count` | INT | INT | NULL | — |  |
| `max_count` | INT | INT | NOT NULL | 0 | guard |
| `confirmed_above_max` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `previewing`, `awaiting_confirmation`, `queued`, `running`, `completed`, `completed_with_errors`, `failed`, `cancelled` |
| `progress` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `affected_count` | INT | INT | NOT NULL | 0 |  |
| `failed_count` | INT | INT | NOT NULL | 0 |  |
| `result` | JSON | NVARCHAR(MAX) | NULL | — | per-record failures / findings |
| `justification_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `justifications.id` NO ACTION |
| `created_by` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `started_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `finished_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `error` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |

- **Primary key:** `pk_bulk_operations` (`id`); SQL Server clustered.
- **Unique:** `uq_bulk_operations_uuid` (`uuid`)
- **Index:** `ix_bulk_operations_form_id_created_at` (`form_id`, `created_at`)
- **Index:** `ix_bulk_operations_organization_id_status` (`organization_id`, `status`)
- **Index:** `ix_bulk_operations_justification_id` (`justification_id`) — supports FK
- **Index:** `ix_bulk_operations_created_by` (`created_by`) — supports FK
- **Foreign key:** `fk_bulk_operations_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_bulk_operations_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_bulk_operations_justification_id`: `justification_id` → `justifications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_bulk_operations_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_bulk_operations_type`: `type IN ('update', 'reassign', 'status_change', 'delete', 'restore', 'duplicate_sweep', 'validation_sweep', 'orphan_scan', 'repair')` (both engines)
- **Check:** `ck_bulk_operations_status`: `status IN ('previewing', 'awaiting_confirmation', 'queued', 'running', 'completed', 'completed_with_errors', 'failed', 'cancelled')` (both engines)
- **Check (SQL Server):** `ck_bulk_operations_criteria_json`: `ISJSON(criteria) = 1`
- **Check (SQL Server):** `ck_bulk_operations_payload_json`: `ISJSON(payload) = 1`
- **Check (SQL Server):** `ck_bulk_operations_result_json`: `ISJSON(result) = 1`

**`recycle_bin`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `title_snapshot` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — |  |
| `deleted_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `deleted_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `justification_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `justifications.id` NO ACTION |
| `purge_after` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | from retention policy |
| `restored_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `restored_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `purged_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_recycle_bin` (`id`); SQL Server clustered.
- **Index:** `ix_recycle_bin_form_id_record_id` (`form_id`, `record_id`)
- **Index:** `ix_recycle_bin_organization_id_purge_after_purged_at` (`organization_id`, `purge_after`, `purged_at`)
- **Index:** `ix_recycle_bin_deleted_by` (`deleted_by`) — supports FK
- **Index:** `ix_recycle_bin_justification_id` (`justification_id`) — supports FK
- **Index:** `ix_recycle_bin_restored_by` (`restored_by`) — supports FK
- **Foreign key:** `fk_recycle_bin_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_recycle_bin_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_recycle_bin_deleted_by`: `deleted_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_recycle_bin_justification_id`: `justification_id` → `justifications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_recycle_bin_restored_by`: `restored_by` → `users`(`id`) ON DELETE NO ACTION

### 10.17 Blueprints

**`blueprints`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `deleted_at` | DATETIME(6) | DATETIME2(6) | NULL | — | soft delete |
| `deleted_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `kind` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `form`, `collection`, `workflow`, `view`, `action`, `notification`, `dashboard`, `application` |
| `category` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `tags` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `is_library` | TINYINT(1) | BIT | NOT NULL | 0 | appears in template library |
| `preview_file_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `files.id` NO ACTION |
| `current_version_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `blueprint_versions.id` NO ACTION |
| `source_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | object it was saved from |
| `source_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |

- **Primary key:** `pk_blueprints` (`id`); SQL Server clustered.
- **Unique:** `uq_blueprints_uuid` (`uuid`)
- **Index:** `ix_blueprints_organization_id_kind_category` (`organization_id`, `kind`, `category`)
- **Index:** `ix_blueprints_organization_id_deleted_at` (`organization_id`, `deleted_at`)
- **Index:** `ix_blueprints_created_by` (`created_by`) — supports FK
- **Index:** `ix_blueprints_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_blueprints_deleted_by` (`deleted_by`) — supports FK
- **Index:** `ix_blueprints_preview_file_id` (`preview_file_id`) — supports FK
- **Index:** `ix_blueprints_current_version_id` (`current_version_id`) — supports FK
- **Foreign key:** `fk_blueprints_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_blueprints_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_blueprints_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_blueprints_deleted_by`: `deleted_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_blueprints_preview_file_id`: `preview_file_id` → `files`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_blueprints_current_version_id`: `current_version_id` → `blueprint_versions`(`id`) ON DELETE NO ACTION
- **Check:** `ck_blueprints_kind`: `kind IN ('form', 'collection', 'workflow', 'view', 'action', 'notification', 'dashboard', 'application')` (both engines)
- **Check (SQL Server):** `ck_blueprints_tags_json`: `ISJSON(tags) = 1`
- Translatable: `name`, `description`.

**`blueprint_versions`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `blueprint_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `blueprints.id` CASCADE |
| `version` | INT | INT | NOT NULL | — |  |
| `content` | JSON | NVARCHAR(MAX) | NOT NULL | — | package format (§14.12) with dependency list |
| `content_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `include_mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `structure`, `structure_permissions`, `everything` |
| `changelog` | TEXT | NVARCHAR(MAX) | NULL | — |  |

- **Primary key:** `pk_blueprint_versions` (`id`); SQL Server clustered.
- **Unique:** `uq_blueprint_versions_blueprint_id_version` (`blueprint_id`, `version`)
- **Unique:** `uq_blueprint_versions_uuid` (`uuid`)
- **Index:** `ix_blueprint_versions_created_by` (`created_by`) — supports FK
- **Index:** `ix_blueprint_versions_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_blueprint_versions_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_blueprint_versions_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_blueprint_versions_blueprint_id`: `blueprint_id` → `blueprints`(`id`) ON DELETE CASCADE
- **Check:** `ck_blueprint_versions_include_mode`: `include_mode IN ('structure', 'structure_permissions', 'everything')` (both engines)
- **Check (SQL Server):** `ck_blueprint_versions_content_json`: `ISJSON(content) = 1`

**`blueprint_instances`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `blueprint_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `blueprints.id` NO ACTION |
| `blueprint_version_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `blueprint_versions.id` NO ACTION |
| `object_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `object_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `include_mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `structure`, `structure_permissions`, `everything` |
| `last_propagated_version_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `blueprint_versions.id` NO ACTION |
| `is_detached` | TINYINT(1) | BIT | NOT NULL | 0 | stops propagation offers |

- **Primary key:** `pk_blueprint_instances` (`id`); SQL Server clustered.
- **Unique:** `uq_blueprint_instances_object_type_object_id` (`object_type`, `object_id`)
- **Unique:** `uq_blueprint_instances_uuid` (`uuid`)
- **Index:** `ix_blueprint_instances_blueprint_id` (`blueprint_id`)
- **Index:** `ix_blueprint_instances_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_blueprint_instances_created_by` (`created_by`) — supports FK
- **Index:** `ix_blueprint_instances_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_blueprint_instances_blueprint_version_id` (`blueprint_version_id`) — supports FK
- **Index:** `ix_blueprint_instances_last_propagated_version_id` (`last_propagated_version_id`) — supports FK
- **Foreign key:** `fk_blueprint_instances_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_blueprint_instances_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_blueprint_instances_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_blueprint_instances_blueprint_id`: `blueprint_id` → `blueprints`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_blueprint_instances_blueprint_version_id`: `blueprint_version_id` → `blueprint_versions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_blueprint_instances_last_propagated_version_id`: `last_propagated_version_id` → `blueprint_versions`(`id`) ON DELETE NO ACTION
- **Check:** `ck_blueprint_instances_include_mode`: `include_mode IN ('structure', 'structure_permissions', 'everything')` (both engines)

### 10.18 Automation & scheduler

**`automations`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `application_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `applications.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `is_enabled` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION |
| `run_as` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | system runs still obey record rules with a dedicated service principal; values: `system`, `triggering_user`, `specific_user` |
| `run_as_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `concurrency_limit` | SMALLINT | SMALLINT | NOT NULL | — |  |
| `retry_policy` | JSON | NVARCHAR(MAX) | NOT NULL | — | `{max_attempts, backoff_seconds[]}` |
| `max_records_per_run` | INT | INT | NOT NULL | — |  |
| `confirm_above` | INT | INT | NULL | — | runs above it wait for confirmation |
| `max_chain_depth` | SMALLINT | SMALLINT | NOT NULL | 3 | loop protection (default 3) |

- **Primary key:** `pk_automations` (`id`); SQL Server clustered.
- **Unique:** `uq_automations_uuid` (`uuid`)
- **Unique:** `uq_automations_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_automations_form_id_is_enabled` (`form_id`, `is_enabled`)
- **Index:** `ix_automations_created_by` (`created_by`) — supports FK
- **Index:** `ix_automations_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_automations_application_id` (`application_id`) — supports FK
- **Index:** `ix_automations_condition_id` (`condition_id`) — supports FK
- **Index:** `ix_automations_run_as_user_id` (`run_as_user_id`) — supports FK
- **Foreign key:** `fk_automations_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automations_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automations_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automations_application_id`: `application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automations_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automations_condition_id`: `condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automations_run_as_user_id`: `run_as_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_automations_run_as`: `run_as IN ('system', 'triggering_user', 'specific_user')` (both engines)
- **Check (SQL Server):** `ck_automations_retry_policy_json`: `ISJSON(retry_policy) = 1`
- Translatable: `name`, `description`.

**`automation_triggers`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `automation_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `automations.id` CASCADE |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `record_created`, `record_updated`, `record_deleted`, `field_changed`, `status_changed`, `condition_true`, `schedule`, `date_reached`, `inbound_webhook`, `watched_folder`, `manual` |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION |
| `from_status_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `statuses.id` NO ACTION |
| `to_status_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `statuses.id` NO ACTION |
| `cron_expression` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NULL | — | hourly/daily/weekly/monthly normalized to cron |
| `timezone` | VARCHAR(64) | NVARCHAR(64) | NULL | — |  |
| `date_field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION |
| `offset_minutes` | INT | INT | NULL | — | negative = before |
| `inbound_endpoint_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `inbound_endpoints.id` NO ACTION |
| `watched_disk` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `watched_path` | VARCHAR(1024) | NVARCHAR(1024) | NULL | — |  |
| `config` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `scheduled_task_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `scheduled_tasks.id` NO ACTION |
| `last_fired_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_automation_triggers` (`id`); SQL Server clustered.
- **Unique:** `uq_automation_triggers_uuid` (`uuid`)
- **Index:** `ix_automation_triggers_type_form_id` (`type`, `form_id`)
- **Index:** `ix_automation_triggers_automation_id` (`automation_id`)
- **Index:** `ix_automation_triggers_form_id` (`form_id`) — supports FK
- **Index:** `ix_automation_triggers_field_id` (`field_id`) — supports FK
- **Index:** `ix_automation_triggers_from_status_id` (`from_status_id`) — supports FK
- **Index:** `ix_automation_triggers_to_status_id` (`to_status_id`) — supports FK
- **Index:** `ix_automation_triggers_date_field_id` (`date_field_id`) — supports FK
- **Index:** `ix_automation_triggers_inbound_endpoint_id` (`inbound_endpoint_id`) — supports FK
- **Index:** `ix_automation_triggers_scheduled_task_id` (`scheduled_task_id`) — supports FK
- **Foreign key:** `fk_automation_triggers_automation_id`: `automation_id` → `automations`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_automation_triggers_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automation_triggers_field_id`: `field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automation_triggers_from_status_id`: `from_status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automation_triggers_to_status_id`: `to_status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automation_triggers_date_field_id`: `date_field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automation_triggers_inbound_endpoint_id`: `inbound_endpoint_id` → `inbound_endpoints`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automation_triggers_scheduled_task_id`: `scheduled_task_id` → `scheduled_tasks`(`id`) ON DELETE NO ACTION
- **Check:** `ck_automation_triggers_type`: `type IN ('record_created', 'record_updated', 'record_deleted', 'field_changed', 'status_changed', 'condition_true', 'schedule', 'date_reached', 'inbound_webhook', 'watched_folder', 'manual')` (both engines)
- **Check (SQL Server):** `ck_automation_triggers_config_json`: `ISJSON(config) = 1`

**`automation_steps`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `automation_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `automations.id` CASCADE |
| `sort_order` | INT | INT | NOT NULL | 0 |  |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `update_fields`, `change_status`, `assign`, `create_linked_record`, `send_email`, `send_notification`, `generate_document`, `run_download`, `call_webhook`, `wait_delay`, `wait_condition` |
| `config` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION |
| `on_failure` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `stop`, `continue`, `retry` |

- **Primary key:** `pk_automation_steps` (`id`); SQL Server clustered.
- **Unique:** `uq_automation_steps_uuid` (`uuid`)
- **Index:** `ix_automation_steps_automation_id_sort_order` (`automation_id`, `sort_order`)
- **Index:** `ix_automation_steps_condition_id` (`condition_id`) — supports FK
- **Foreign key:** `fk_automation_steps_automation_id`: `automation_id` → `automations`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_automation_steps_condition_id`: `condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Check:** `ck_automation_steps_type`: `type IN ('update_fields', 'change_status', 'assign', 'create_linked_record', 'send_email', 'send_notification', 'generate_document', 'run_download', 'call_webhook', 'wait_delay', 'wait_condition')` (both engines)
- **Check:** `ck_automation_steps_on_failure`: `on_failure IN ('stop', 'continue', 'retry')` (both engines)
- **Check (SQL Server):** `ck_automation_steps_config_json`: `ISJSON(config) = 1`

**`automation_runs`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `automation_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `automations.id` NO ACTION |
| `trigger_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `trigger_ref` | JSON | NVARCHAR(MAX) | NOT NULL | — | record ids / schedule time / webhook delivery id |
| `parent_run_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `automation_runs.id` NO ACTION (chain) |
| `chain_depth` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `is_test` | TINYINT(1) | BIT | NOT NULL | 0 | test against a sample record (no side effects committed) |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `queued`, `awaiting_confirmation`, `running`, `waiting`, `succeeded`, `failed`, `partially_failed`, `cancelled`, `skipped_loop` |
| `records_affected` | INT | INT | NOT NULL | 0 |  |
| `steps_log` | JSON | NVARCHAR(MAX) | NOT NULL | — | per step: status, duration, output summary, error |
| `current_step` | INT | INT | NULL | — | for waits |
| `resume_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `attempts` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `started_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `finished_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `duration_ms` | BIGINT | BIGINT | NULL | — |  |
| `error` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `triggered_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |

- **Primary key:** `pk_automation_runs` (`id`); SQL Server clustered.
- **Unique:** `uq_automation_runs_uuid` (`uuid`)
- **Index:** `ix_automation_runs_automation_id_created_at` (`automation_id`, `created_at`)
- **Index:** `ix_automation_runs_status_resume_at` (`status`, `resume_at`)
- **Index:** `ix_automation_runs_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_automation_runs_parent_run_id` (`parent_run_id`) — supports FK
- **Index:** `ix_automation_runs_triggered_by` (`triggered_by`) — supports FK
- **Foreign key:** `fk_automation_runs_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automation_runs_automation_id`: `automation_id` → `automations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automation_runs_parent_run_id`: `parent_run_id` → `automation_runs`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_automation_runs_triggered_by`: `triggered_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_automation_runs_status`: `status IN ('queued', 'awaiting_confirmation', 'running', 'waiting', 'succeeded', 'failed', 'partially_failed', 'cancelled', 'skipped_loop')` (both engines)
- **Check (SQL Server):** `ck_automation_runs_trigger_ref_json`: `ISJSON(trigger_ref) = 1`
- **Check (SQL Server):** `ck_automation_runs_steps_log_json`: `ISJSON(steps_log) = 1`

**`scheduled_tasks`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `kind` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `system`, `automation`, `download`, `sync`, `retention`, `report`, `reconciliation` |
| `owner_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `owner_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `name` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — |  |
| `cron_expression` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `timezone` | VARCHAR(64) | NVARCHAR(64) | NOT NULL | — |  |
| `is_enabled` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `next_run_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `last_run_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `last_status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | values: `succeeded`, `failed`, `running`, `skipped` |
| `last_duration_ms` | BIGINT | BIGINT | NULL | — |  |
| `last_error` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `claimed_until` | DATETIME(6) | DATETIME2(6) | NULL | — | atomic claim |
| `claimed_by` | VARCHAR(128) | NVARCHAR(128) | NULL | — | worker host |

- **Primary key:** `pk_scheduled_tasks` (`id`); SQL Server clustered.
- **Unique:** `uq_scheduled_tasks_uuid` (`uuid`)
- **Index:** `ix_scheduled_tasks_is_enabled_next_run_at` (`is_enabled`, `next_run_at`)
- **Index:** `ix_scheduled_tasks_owner_type_owner_id` (`owner_type`, `owner_id`)
- **Index:** `ix_scheduled_tasks_organization_id` (`organization_id`) — supports FK
- **Foreign key:** `fk_scheduled_tasks_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Check:** `ck_scheduled_tasks_kind`: `kind IN ('system', 'automation', 'download', 'sync', 'retention', 'report', 'reconciliation')` (both engines)
- **Check:** `ck_scheduled_tasks_last_status`: `last_status IN ('succeeded', 'failed', 'running', 'skipped')` (both engines)

### 10.19 External access

**`external_forms`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `slug` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | unique; URL `/x/f/{slug}` |
| `theme_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `themes.id` NO ACTION |
| `initial_status_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `statuses.id` NO ACTION |
| `exposed_fields` | JSON | NVARCHAR(MAX) | NOT NULL | — | field uuids published externally (allow-list) |
| `captcha` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `rate_limit_per_ip` | INT | INT | NOT NULL | — | per hour |
| `opens_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `closes_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `daily_window` | JSON | NVARCHAR(MAX) | NULL | — | allowed time of day |
| `submission_cap` | INT | INT | NULL | — |  |
| `submissions_count` | INT | INT | NOT NULL | 0 |  |
| `verification` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `none`, `email`, `sms` |
| `password_hash` | VARCHAR(255) | NVARCHAR(255) | NULL | — |  |
| `run_as_user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION (service principal recorded as creator) |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_external_forms` (`id`); SQL Server clustered.
- **Unique:** `uq_external_forms_uuid` (`uuid`)
- **Unique:** `uq_external_forms_slug` (`slug`)
- **Index:** `ix_external_forms_form_id` (`form_id`)
- **Index:** `ix_external_forms_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_external_forms_created_by` (`created_by`) — supports FK
- **Index:** `ix_external_forms_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_external_forms_theme_id` (`theme_id`) — supports FK
- **Index:** `ix_external_forms_initial_status_id` (`initial_status_id`) — supports FK
- **Index:** `ix_external_forms_run_as_user_id` (`run_as_user_id`) — supports FK
- **Foreign key:** `fk_external_forms_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_external_forms_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_external_forms_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_external_forms_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_external_forms_theme_id`: `theme_id` → `themes`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_external_forms_initial_status_id`: `initial_status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_external_forms_run_as_user_id`: `run_as_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_external_forms_verification`: `verification IN ('none', 'email', 'sms')` (both engines)
- **Check (SQL Server):** `ck_external_forms_exposed_fields_json`: `ISJSON(exposed_fields) = 1`
- **Check (SQL Server):** `ck_external_forms_daily_window_json`: `ISJSON(daily_window) = 1`
- Translatable: `title`, `intro`, `success_message`.

**`external_users`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `deleted_at` | DATETIME(6) | DATETIME2(6) | NULL | — | soft delete |
| `deleted_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `email` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — |  |
| `name` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — |  |
| `password` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — |  |
| `role_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `roles.id` NO ACTION (audience=external) |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `pending_verification`, `pending_approval`, `approved`, `rejected`, `suspended` |
| `email_verified_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `two_factor_secret` | TEXT | NVARCHAR(MAX) | NULL | — | encrypted |
| `two_factor_confirmed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `approved_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `approved_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `last_login_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `failed_login_count` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `locked_until` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `anonymized_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_external_users` (`id`); SQL Server clustered.
- **Unique:** `uq_external_users_uuid` (`uuid`)
- **Unique:** `uq_external_users_organization_id_email` (`organization_id`, `email`)
- **Index:** `ix_external_users_organization_id_status` (`organization_id`, `status`)
- **Index:** `ix_external_users_organization_id_deleted_at` (`organization_id`, `deleted_at`)
- **Index:** `ix_external_users_created_by` (`created_by`) — supports FK
- **Index:** `ix_external_users_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_external_users_deleted_by` (`deleted_by`) — supports FK
- **Index:** `ix_external_users_role_id` (`role_id`) — supports FK
- **Index:** `ix_external_users_approved_by` (`approved_by`) — supports FK
- **Foreign key:** `fk_external_users_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_external_users_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_external_users_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_external_users_deleted_by`: `deleted_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_external_users_role_id`: `role_id` → `roles`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_external_users_approved_by`: `approved_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_external_users_status`: `status IN ('pending_verification', 'pending_approval', 'approved', 'rejected', 'suspended')` (both engines)

**`access_tokens`** (tokenized single-record links)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `token_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | SHA-256 of a 256-bit random token; plaintext only in the sent link |
| `purpose` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `record_action`, `signature`, `external_resume`, `email_verification` |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `action` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `view`, `approve`, `reject`, `complete_section`, `sign` |
| `section_key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `recipient_email` | VARCHAR(255) | NVARCHAR(255) | NULL | — |  |
| `max_uses` | SMALLINT | SMALLINT | NOT NULL | — |  |
| `uses` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `expires_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `revoked_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `revoked_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `last_used_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `last_used_ip` | VARCHAR(45) | NVARCHAR(45) | NULL | — |  |

- **Primary key:** `pk_access_tokens` (`id`); SQL Server clustered.
- **Unique:** `uq_access_tokens_token_hash` (`token_hash`)
- **Unique:** `uq_access_tokens_uuid` (`uuid`)
- **Index:** `ix_access_tokens_form_id_record_id` (`form_id`, `record_id`)
- **Index:** `ix_access_tokens_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_access_tokens_created_by` (`created_by`) — supports FK
- **Index:** `ix_access_tokens_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_access_tokens_revoked_by` (`revoked_by`) — supports FK
- **Foreign key:** `fk_access_tokens_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_access_tokens_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_access_tokens_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_access_tokens_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_access_tokens_revoked_by`: `revoked_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_access_tokens_purpose`: `purpose IN ('record_action', 'signature', 'external_resume', 'email_verification')` (both engines)
- **Check:** `ck_access_tokens_action`: `action IN ('view', 'approve', 'reject', 'complete_section', 'sign')` (both engines)

**`signature_requests`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `signer_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `user`, `external_user`, `email` |
| `signer_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `signer_external_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `external_users.id` NO ACTION |
| `signer_email` | VARCHAR(255) | NVARCHAR(255) | NULL | — |  |
| `access_token_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `access_tokens.id` NO ACTION |
| `document_template_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `document_templates.id` NO ACTION |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `pending`, `viewed`, `signed`, `declined`, `expired`, `cancelled` |
| `signature_file_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `files.id` NO ACTION (signature image) |
| `signed_file_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `files.id` NO ACTION (signed output) |
| `signed_document_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `signed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `signer_ip` | VARCHAR(45) | NVARCHAR(45) | NULL | — |  |
| `signer_user_agent` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `decline_reason` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `expires_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |

- **Primary key:** `pk_signature_requests` (`id`); SQL Server clustered.
- **Unique:** `uq_signature_requests_uuid` (`uuid`)
- **Index:** `ix_signature_requests_form_id_record_id` (`form_id`, `record_id`)
- **Index:** `ix_signature_requests_organization_id_status` (`organization_id`, `status`)
- **Index:** `ix_signature_requests_created_by` (`created_by`) — supports FK
- **Index:** `ix_signature_requests_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_signature_requests_signer_user_id` (`signer_user_id`) — supports FK
- **Index:** `ix_signature_requests_signer_external_user_id` (`signer_external_user_id`) — supports FK
- **Index:** `ix_signature_requests_access_token_id` (`access_token_id`) — supports FK
- **Index:** `ix_signature_requests_document_template_id` (`document_template_id`) — supports FK
- **Index:** `ix_signature_requests_signature_file_id` (`signature_file_id`) — supports FK
- **Index:** `ix_signature_requests_signed_file_id` (`signed_file_id`) — supports FK
- **Foreign key:** `fk_signature_requests_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_signature_requests_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_signature_requests_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_signature_requests_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_signature_requests_signer_user_id`: `signer_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_signature_requests_signer_external_user_id`: `signer_external_user_id` → `external_users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_signature_requests_access_token_id`: `access_token_id` → `access_tokens`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_signature_requests_document_template_id`: `document_template_id` → `document_templates`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_signature_requests_signature_file_id`: `signature_file_id` → `files`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_signature_requests_signed_file_id`: `signed_file_id` → `files`(`id`) ON DELETE NO ACTION
- **Check:** `ck_signature_requests_signer_type`: `signer_type IN ('user', 'external_user', 'email')` (both engines)
- **Check:** `ck_signature_requests_status`: `status IN ('pending', 'viewed', 'signed', 'declined', 'expired', 'cancelled')` (both engines)

**`submission_throttles`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `external_form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `external_forms.id` CASCADE |
| `key_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `ip`, `email`, `global` |
| `key_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | HMAC of the IP/email |
| `window_start` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `count` | INT | INT | NOT NULL | — |  |

- **Primary key:** `pk_submission_throttles` (`id`); SQL Server clustered.
- **Unique:** `uq_submission_throttles_external_form_id_key_type_k_7ad12ded` (`external_form_id`, `key_type`, `key_hash`, `window_start`)
- **Foreign key:** `fk_submission_throttles_external_form_id`: `external_form_id` → `external_forms`(`id`) ON DELETE CASCADE
- **Check:** `ck_submission_throttles_key_type`: `key_type IN ('ip', 'email', 'global')` (both engines)

### 10.20 Integrations

**`external_data_sources`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `rest`, `db_view` |
| `rest_config` | JSON | NVARCHAR(MAX) | NULL | — | base URL, method, path, query template, pagination (calls via egress gateway) |
| `db_connection` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NULL | — | name of a connection defined in environment config (no credentials in DB) |
| `db_view` | VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(128) COLLATE Latin1_General_100_BIN2 | NULL | — | view name, validated by introspection |
| `auth_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `none`, `api_key`, `bearer`, `basic`, `oauth2_client_credentials` |
| `credentials` | TEXT | NVARCHAR(MAX) | NULL | — | encrypted |
| `headers` | TEXT | NVARCHAR(MAX) | NULL | — | encrypted JSON |
| `response_mapping` | JSON | NVARCHAR(MAX) | NOT NULL | — | JSON-path → field keys, value/label columns |
| `cache_policy` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `none`, `ttl`, `stale_while_revalidate` |
| `cache_ttl_seconds` | INT | INT | NULL | — |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `last_tested_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `last_test_status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — |  |

- **Primary key:** `pk_external_data_sources` (`id`); SQL Server clustered.
- **Unique:** `uq_external_data_sources_uuid` (`uuid`)
- **Unique:** `uq_external_data_sources_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_external_data_sources_created_by` (`created_by`) — supports FK
- **Index:** `ix_external_data_sources_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_external_data_sources_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_external_data_sources_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_external_data_sources_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_external_data_sources_type`: `type IN ('rest', 'db_view')` (both engines)
- **Check:** `ck_external_data_sources_auth_type`: `auth_type IN ('none', 'api_key', 'bearer', 'basic', 'oauth2_client_credentials')` (both engines)
- **Check:** `ck_external_data_sources_cache_policy`: `cache_policy IN ('none', 'ttl', 'stale_while_revalidate')` (both engines)
- **Check (SQL Server):** `ck_external_data_sources_rest_config_json`: `ISJSON(rest_config) = 1`
- **Check (SQL Server):** `ck_external_data_sources_response_mapping_json`: `ISJSON(response_mapping) = 1`
- Translatable: `name`.

**`sync_jobs`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `external_data_source_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `external_data_sources.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `direction` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `import`, `export` |
| `field_mapping` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `match_keys` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `conflict_rule` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `source_wins`, `target_wins`, `newest_wins`, `skip` |
| `run_as_user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `scheduled_task_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `scheduled_tasks.id` NO ACTION |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_sync_jobs` (`id`); SQL Server clustered.
- **Unique:** `uq_sync_jobs_uuid` (`uuid`)
- **Index:** `ix_sync_jobs_form_id` (`form_id`)
- **Index:** `ix_sync_jobs_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_sync_jobs_created_by` (`created_by`) — supports FK
- **Index:** `ix_sync_jobs_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_sync_jobs_external_data_source_id` (`external_data_source_id`) — supports FK
- **Index:** `ix_sync_jobs_run_as_user_id` (`run_as_user_id`) — supports FK
- **Index:** `ix_sync_jobs_scheduled_task_id` (`scheduled_task_id`) — supports FK
- **Foreign key:** `fk_sync_jobs_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sync_jobs_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sync_jobs_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sync_jobs_external_data_source_id`: `external_data_source_id` → `external_data_sources`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sync_jobs_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sync_jobs_run_as_user_id`: `run_as_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sync_jobs_scheduled_task_id`: `scheduled_task_id` → `scheduled_tasks`(`id`) ON DELETE NO ACTION
- **Check:** `ck_sync_jobs_direction`: `direction IN ('import', 'export')` (both engines)
- **Check:** `ck_sync_jobs_conflict_rule`: `conflict_rule IN ('source_wins', 'target_wins', 'newest_wins', 'skip')` (both engines)
- **Check (SQL Server):** `ck_sync_jobs_field_mapping_json`: `ISJSON(field_mapping) = 1`
- **Check (SQL Server):** `ck_sync_jobs_match_keys_json`: `ISJSON(match_keys) = 1`
- Translatable: `name`.

**`sync_runs`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `sync_job_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `sync_jobs.id` NO ACTION |
| `dry_run` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `queued`, `running`, `succeeded`, `failed`, `partially_failed` |
| `created_count` | INT | INT | NOT NULL | 0 |  |
| `updated_count` | INT | INT | NOT NULL | 0 |  |
| `skipped_count` | INT | INT | NOT NULL | 0 |  |
| `error_count` | INT | INT | NOT NULL | 0 |  |
| `log_file_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `files.id` NO ACTION |
| `started_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `finished_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `error` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `triggered_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |

- **Primary key:** `pk_sync_runs` (`id`); SQL Server clustered.
- **Unique:** `uq_sync_runs_uuid` (`uuid`)
- **Index:** `ix_sync_runs_sync_job_id_created_at` (`sync_job_id`, `created_at`)
- **Index:** `ix_sync_runs_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_sync_runs_log_file_id` (`log_file_id`) — supports FK
- **Index:** `ix_sync_runs_triggered_by` (`triggered_by`) — supports FK
- **Foreign key:** `fk_sync_runs_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sync_runs_sync_job_id`: `sync_job_id` → `sync_jobs`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sync_runs_log_file_id`: `log_file_id` → `files`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_sync_runs_triggered_by`: `triggered_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_sync_runs_status`: `status IN ('queued', 'running', 'succeeded', 'failed', 'partially_failed')` (both engines)

**`notification_channels`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `email`, `in_app`, `sms`, `messaging` |
| `provider` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | registered provider driver |
| `config` | TEXT | NVARCHAR(MAX) | NULL | — | encrypted JSON (API keys, sender ids) |
| `rate_limit_per_minute` | INT | INT | NULL | — |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `is_default` | TINYINT(1) | BIT | NOT NULL | 0 |  |

- **Primary key:** `pk_notification_channels` (`id`); SQL Server clustered.
- **Unique:** `uq_notification_channels_uuid` (`uuid`)
- **Unique:** `uq_notification_channels_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_notification_channels_created_by` (`created_by`) — supports FK
- **Index:** `ix_notification_channels_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_notification_channels_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_notification_channels_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_notification_channels_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_notification_channels_type`: `type IN ('email', 'in_app', 'sms', 'messaging')` (both engines)
- Translatable: `name`.

**`inbound_endpoints`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `kind` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `api`, `webhook` |
| `slug` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | URL `/api/inbound/{slug}` |
| `operation` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `create`, `update`, `upsert` |
| `match_field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION |
| `payload_mapping` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `signature_secret` | TEXT | NVARCHAR(MAX) | NULL | — | encrypted (webhooks) |
| `signature_header` | VARCHAR(64) | NVARCHAR(64) | NULL | — |  |
| `signature_algorithm` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | values: `hmac_sha256`, `hmac_sha512` |
| `run_as_user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `initial_status_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `statuses.id` NO ACTION |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_inbound_endpoints` (`id`); SQL Server clustered.
- **Unique:** `uq_inbound_endpoints_uuid` (`uuid`)
- **Unique:** `uq_inbound_endpoints_organization_id_slug` (`organization_id`, `slug`)
- **Index:** `ix_inbound_endpoints_created_by` (`created_by`) — supports FK
- **Index:** `ix_inbound_endpoints_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_inbound_endpoints_form_id` (`form_id`) — supports FK
- **Index:** `ix_inbound_endpoints_match_field_id` (`match_field_id`) — supports FK
- **Index:** `ix_inbound_endpoints_run_as_user_id` (`run_as_user_id`) — supports FK
- **Index:** `ix_inbound_endpoints_initial_status_id` (`initial_status_id`) — supports FK
- **Foreign key:** `fk_inbound_endpoints_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_inbound_endpoints_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_inbound_endpoints_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_inbound_endpoints_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_inbound_endpoints_match_field_id`: `match_field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_inbound_endpoints_run_as_user_id`: `run_as_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_inbound_endpoints_initial_status_id`: `initial_status_id` → `statuses`(`id`) ON DELETE NO ACTION
- **Check:** `ck_inbound_endpoints_kind`: `kind IN ('api', 'webhook')` (both engines)
- **Check:** `ck_inbound_endpoints_operation`: `operation IN ('create', 'update', 'upsert')` (both engines)
- **Check:** `ck_inbound_endpoints_signature_algorithm`: `signature_algorithm IN ('hmac_sha256', 'hmac_sha512')` (both engines)
- **Check (SQL Server):** `ck_inbound_endpoints_payload_mapping_json`: `ISJSON(payload_mapping) = 1`

### 10.21 Operations & monitoring

**`audit_logs`** — append-only, hash-chained, time-partitioned

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — | auto-increment; PK is (`id`, `occurred_at`) for partitioning |
| `occurred_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | partition key (monthly) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `chain_id` | SMALLINT | SMALLINT | NOT NULL | — | shard of the hash chain (0–15) |
| `chain_seq` | BIGINT | BIGINT | NOT NULL | — |  |
| `event` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | `record.created`, `record.updated`, `record.deleted`, `record.restored`, `status.changed`, `import`, `export`, `download`, `print`, `auth.login`, `auth.login_failed`, `auth.logout`, `ops.retry`, `ops.resend`, `access.changed`, `config.changed`, `impersonation.started`, … |
| `category` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `data`, `workflow`, `auth`, `access`, `config`, `operations`, `export`, `schema`, `security` |
| `object_type` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `object_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `record_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `changes` | JSON | NVARCHAR(MAX) | NULL | — | `[{field_uuid, field_key, old, new}]` — sensitive values masked, encrypted fields as `«encrypted»` |
| `actor_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | acting user (impersonating admin when impersonating) |
| `subject_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | impersonated user |
| `on_behalf_of_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | delegation |
| `external_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `impersonation_session_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `justification_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `ip_address` | VARCHAR(45) | NVARCHAR(45) | NULL | — |  |
| `user_agent` | VARCHAR(512) | NVARCHAR(512) | NULL | — |  |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `meta` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `prev_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | SHA-256(prev_hash ‖ canonical JSON of the row incl. justification content_hash) |

- **Primary key:** `pk_audit_logs` (`id`, `occurred_at`). MySQL: `PARTITION BY RANGE COLUMNS(occurred_at)` monthly. SQL Server: clustered on (`occurred_at`, `id`) on monthly partition scheme `ps_audit_logs_monthly`.
- **Unique:** `uq_audit_logs_chain_id_chain_seq_occurred_at` (`chain_id`, `chain_seq`, `occurred_at`)
- **Index:** `ix_audit_logs_form_id_record_id_occurred_at` (`form_id`, `record_id`, `occurred_at`)
- **Index:** `ix_audit_logs_actor_user_id_occurred_at` (`actor_user_id`, `occurred_at`)
- **Index:** `ix_audit_logs_object_type_object_id` (`object_type`, `object_id`)
- **Index:** `ix_audit_logs_organization_id_event_occurred_at` (`organization_id`, `event`, `occurred_at`)
- **Index:** `ix_audit_logs_correlation_id` (`correlation_id`)
- **Foreign keys:** none — partitioned table; `*_id` columns are logical references verified by the chain verifier and the data-repair scan (ADR-0011).
- **Check:** `ck_audit_logs_category`: `category IN ('data', 'workflow', 'auth', 'access', 'config', 'operations', 'export', 'schema', 'security')` (both engines)
- **Check (SQL Server):** `ck_audit_logs_changes_json`: `ISJSON(changes) = 1`
- **Check (SQL Server):** `ck_audit_logs_meta_json`: `ISJSON(meta) = 1`
- No FKs (partitioned tables cannot carry FKs on MySQL; integrity via the chain).

**`audit_chain_heads`** (supporting)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `chain_id` | SMALLINT | SMALLINT | NOT NULL | — | PK |
| `last_seq` | BIGINT | BIGINT | NOT NULL | 0 |  |
| `last_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `last_verified_seq` | BIGINT | BIGINT | NOT NULL | 0 |  |
| `last_verified_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |

- **Primary key:** `pk_audit_chain_heads` (`chain_id`); SQL Server clustered.
- Appenders lock the head row (`lockForUpdate`) inside the writing transaction; 16 chains keep contention low; the verification job walks each chain.

**`error_logs`** — time-partitioned

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — | PK (`id`, `occurred_at`) |
| `occurred_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `error_group_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | logical ref → `error_groups.id` |
| `reference_code` | VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(16) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | shown to the user |
| `severity` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency` |
| `module` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `exception_class` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — |  |
| `message` | TEXT | NVARCHAR(MAX) | NOT NULL | — | masked |
| `file` | VARCHAR(1024) | NVARCHAR(1024) | NULL | — |  |
| `line` | INT | INT | NULL | — |  |
| `trace` | LONGTEXT | NVARCHAR(MAX) | NULL | — | masked |
| `request` | JSON | NVARCHAR(MAX) | NULL | — | method, route, url (query masked), masked payload, masked headers |
| `user_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `role_keys` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `record_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `hook` | VARCHAR(255) | NVARCHAR(255) | NULL | — |  |
| `job` | VARCHAR(255) | NVARCHAR(255) | NULL | — |  |
| `environment` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `release` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NULL | — |  |

- **Primary key:** `pk_error_logs` (`id`, `occurred_at`). MySQL: `PARTITION BY RANGE COLUMNS(occurred_at)` monthly. SQL Server: clustered on (`occurred_at`, `id`) on monthly partition scheme `ps_error_logs_monthly`.
- **Index:** `ix_error_logs_error_group_id_occurred_at` (`error_group_id`, `occurred_at`)
- **Index:** `ix_error_logs_organization_id_occurred_at` (`organization_id`, `occurred_at`)
- **Index:** `ix_error_logs_reference_code` (`reference_code`)
- **Index:** `ix_error_logs_correlation_id` (`correlation_id`)
- **Index:** `ix_error_logs_form_id_occurred_at` (`form_id`, `occurred_at`)
- **Index:** `ix_error_logs_user_id_occurred_at` (`user_id`, `occurred_at`)
- **Foreign keys:** none — partitioned table; `*_id` columns are logical references verified by the chain verifier and the data-repair scan (ADR-0011).
- **Check:** `ck_error_logs_severity`: `severity IN ('debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency')` (both engines)
- **Check (SQL Server):** `ck_error_logs_request_json`: `ISJSON(request) = 1`
- **Check (SQL Server):** `ck_error_logs_role_keys_json`: `ISJSON(role_keys) = 1`

**`error_groups`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `fingerprint` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | class + normalized message + top frames |
| `exception_class` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — |  |
| `message_sample` | TEXT | NVARCHAR(MAX) | NOT NULL | — |  |
| `module` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `severity` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency` |
| `first_seen_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `last_seen_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `occurrences` | BIGINT | BIGINT | NOT NULL | 0 |  |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `new`, `in_progress`, `resolved`, `ignored` |
| `assignee_user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `notes` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `resolved_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `resolved_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `last_alerted_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_error_groups` (`id`); SQL Server clustered.
- **Unique:** `uq_error_groups_organization_id_fingerprint` (`organization_id`, `fingerprint`)
- **Index:** `ix_error_groups_status_last_seen_at` (`status`, `last_seen_at`)
- **Index:** `ix_error_groups_assignee_user_id` (`assignee_user_id`) — supports FK
- **Index:** `ix_error_groups_resolved_by` (`resolved_by`) — supports FK
- **Foreign key:** `fk_error_groups_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_error_groups_assignee_user_id`: `assignee_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_error_groups_resolved_by`: `resolved_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_error_groups_severity`: `severity IN ('debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency')` (both engines)
- **Check:** `ck_error_groups_status`: `status IN ('new', 'in_progress', 'resolved', 'ignored')` (both engines)

**`outbox_events`** (supporting — transactional outbox, §8.3)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `event_type` | VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(128) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `payload` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `available_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `dispatched_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `attempts` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `last_error` | TEXT | NVARCHAR(MAX) | NULL | — |  |

- **Primary key:** `pk_outbox_events` (`id`); SQL Server clustered.
- **Index:** `ix_outbox_events_dispatched_at_available_at` (`dispatched_at`, `available_at`)
- **Check (SQL Server):** `ck_outbox_events_payload_json`: `ISJSON(payload) = 1`

**`operations_alert_rules`** (supporting — §4.2 threshold alerts)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `metric` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `failed_submissions`, `failed_jobs`, `failed_emails`, `stuck_emails`, `failed_webhooks`, `failed_integrations`, `queue_size`, `error_rate`, `storage_usage`, `scheduler_lag` |
| `threshold` | INT | INT | NOT NULL | — |  |
| `window_minutes` | INT | INT | NOT NULL | — |  |
| `recipient_role_ids` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `cooldown_minutes` | INT | INT | NOT NULL | — |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `last_triggered_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_operations_alert_rules` (`id`); SQL Server clustered.
- **Unique:** `uq_operations_alert_rules_uuid` (`uuid`)
- **Index:** `ix_operations_alert_rules_organization_id_is_active` (`organization_id`, `is_active`)
- **Index:** `ix_operations_alert_rules_created_by` (`created_by`) — supports FK
- **Index:** `ix_operations_alert_rules_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_operations_alert_rules_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_operations_alert_rules_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_operations_alert_rules_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_operations_alert_rules_metric`: `metric IN ('failed_submissions', 'failed_jobs', 'failed_emails', 'stuck_emails', 'failed_webhooks', 'failed_integrations', 'queue_size', 'error_rate', 'storage_usage', 'scheduler_lag')` (both engines)
- **Check (SQL Server):** `ck_operations_alert_rules_recipient_role_ids_json`: `ISJSON(recipient_role_ids) = 1`

Laravel's `failed_jobs` and `job_batches` tables are used unchanged (plus our tracking rows).

### 10.22 Extensibility & platform

**`extensions`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `key` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | module directory name under `/extensions` |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `server_hook`, `api_endpoint`, `client_validator`, `client_field`, `form_script` |
| `hook_point` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | values: `onLoad`, `beforeValidate`, `afterValidate`, `beforeSave`, `afterSave`, `beforeDelete`, `afterDelete`, `onStatusChange`, `onAction` |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `draft`, `testing`, `pending_approval`, `approved`, `deployed`, `disabled`, `rejected` |
| `current_version_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `extension_versions.id` NO ACTION |
| `deployed_version_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `extension_versions.id` NO ACTION |
| `timeout_ms` | INT | INT | NOT NULL | — |  |

- **Primary key:** `pk_extensions` (`id`); SQL Server clustered.
- **Unique:** `uq_extensions_uuid` (`uuid`)
- **Unique:** `uq_extensions_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_extensions_created_by` (`created_by`) — supports FK
- **Index:** `ix_extensions_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_extensions_form_id` (`form_id`) — supports FK
- **Index:** `ix_extensions_current_version_id` (`current_version_id`) — supports FK
- **Index:** `ix_extensions_deployed_version_id` (`deployed_version_id`) — supports FK
- **Foreign key:** `fk_extensions_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_extensions_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_extensions_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_extensions_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_extensions_current_version_id`: `current_version_id` → `extension_versions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_extensions_deployed_version_id`: `deployed_version_id` → `extension_versions`(`id`) ON DELETE NO ACTION
- **Check:** `ck_extensions_type`: `type IN ('server_hook', 'api_endpoint', 'client_validator', 'client_field', 'form_script')` (both engines)
- **Check:** `ck_extensions_hook_point`: `hook_point IN ('onLoad', 'beforeValidate', 'afterValidate', 'beforeSave', 'afterSave', 'beforeDelete', 'afterDelete', 'onStatusChange', 'onAction')` (both engines)
- **Check:** `ck_extensions_status`: `status IN ('draft', 'testing', 'pending_approval', 'approved', 'deployed', 'disabled', 'rejected')` (both engines)
- Translatable: `name`, `description`.

**`extension_versions`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `extension_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `extensions.id` CASCADE |
| `version` | INT | INT | NOT NULL | — |  |
| `source_path` | VARCHAR(1024) | NVARCHAR(1024) | NOT NULL | — | file in `/extensions/{key}/v{n}/` |
| `checksum` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | verified before every load |
| `manifest` | JSON | NVARCHAR(MAX) | NOT NULL | — | entry class, hook point, permissions requested |
| `test_status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `not_run`, `passed`, `failed` |
| `test_report` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `submitted_by` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `approved_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION (≠ submitter) |
| `approved_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `deployed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `rejected_reason` | TEXT | NVARCHAR(MAX) | NULL | — |  |

- **Primary key:** `pk_extension_versions` (`id`); SQL Server clustered.
- **Unique:** `uq_extension_versions_extension_id_version` (`extension_id`, `version`)
- **Unique:** `uq_extension_versions_uuid` (`uuid`)
- **Index:** `ix_extension_versions_submitted_by` (`submitted_by`) — supports FK
- **Index:** `ix_extension_versions_approved_by` (`approved_by`) — supports FK
- **Foreign key:** `fk_extension_versions_extension_id`: `extension_id` → `extensions`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_extension_versions_submitted_by`: `submitted_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_extension_versions_approved_by`: `approved_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_extension_versions_test_status`: `test_status IN ('not_run', 'passed', 'failed')` (both engines)
- **Check (SQL Server):** `ck_extension_versions_manifest_json`: `ISJSON(manifest) = 1`
- **Check (SQL Server):** `ck_extension_versions_test_report_json`: `ISJSON(test_report) = 1`

**`api_tokens`** (Sanctum token model `ApiToken`)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `tokenable_type` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `tokenable_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `name` | VARCHAR(255) | NVARCHAR(255) | NOT NULL | — |  |
| `token` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | SHA-256 |
| `abilities` | JSON | NVARCHAR(MAX) | NOT NULL | — | scopes: `form:{uuid}:read`, `form:{uuid}:write`, … ⊆ owner's permissions |
| `ip_allowlist` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `rate_limit_per_minute` | INT | INT | NULL | — |  |
| `last_used_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `expires_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `revoked_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |

- **Primary key:** `pk_api_tokens` (`id`); SQL Server clustered.
- **Unique:** `uq_api_tokens_token` (`token`)
- **Unique:** `uq_api_tokens_uuid` (`uuid`)
- **Index:** `ix_api_tokens_tokenable_type_tokenable_id` (`tokenable_type`, `tokenable_id`)
- **Index:** `ix_api_tokens_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_api_tokens_created_by` (`created_by`) — supports FK
- **Foreign key:** `fk_api_tokens_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_api_tokens_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_api_tokens_abilities_json`: `ISJSON(abilities) = 1`
- **Check (SQL Server):** `ck_api_tokens_ip_allowlist_json`: `ISJSON(ip_allowlist) = 1`

**`webhooks`** (outgoing)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `url` | VARCHAR(2048) | NVARCHAR(2048) | NOT NULL | — | validated against the egress allowlist on save and at send |
| `method` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `POST`, `PUT`, `PATCH`, `DELETE`, `GET` |
| `headers` | TEXT | NVARCHAR(MAX) | NULL | — | encrypted JSON |
| `body_template` | JSON | NVARCHAR(MAX) | NULL | — | placeholders as expression ASTs |
| `events` | JSON | NVARCHAR(MAX) | NOT NULL | — | subscribed event types |
| `secret` | TEXT | NVARCHAR(MAX) | NOT NULL | — | encrypted signing secret |
| `timeout_seconds` | SMALLINT | SMALLINT | NOT NULL | — |  |
| `max_retries` | SMALLINT | SMALLINT | NOT NULL | — |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_webhooks` (`id`); SQL Server clustered.
- **Unique:** `uq_webhooks_uuid` (`uuid`)
- **Unique:** `uq_webhooks_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_webhooks_created_by` (`created_by`) — supports FK
- **Index:** `ix_webhooks_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_webhooks_form_id` (`form_id`) — supports FK
- **Foreign key:** `fk_webhooks_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_webhooks_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_webhooks_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_webhooks_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Check:** `ck_webhooks_method`: `method IN ('POST', 'PUT', 'PATCH', 'DELETE', 'GET')` (both engines)
- **Check (SQL Server):** `ck_webhooks_body_template_json`: `ISJSON(body_template) = 1`
- **Check (SQL Server):** `ck_webhooks_events_json`: `ISJSON(events) = 1`
- Translatable: `name`.

**`webhook_deliveries`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `direction` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `outgoing`, `incoming` |
| `webhook_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `webhooks.id` NO ACTION |
| `inbound_endpoint_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `inbound_endpoints.id` NO ACTION |
| `action_step_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `action_steps.id` NO ACTION |
| `automation_run_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `automation_runs.id` NO ACTION |
| `url` | VARCHAR(2048) | NVARCHAR(2048) | NOT NULL | — |  |
| `method` | VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(8) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `resolved_ip` | VARCHAR(45) | NVARCHAR(45) | NULL | — | pinned address |
| `request_headers` | JSON | NVARCHAR(MAX) | NULL | — | masked |
| `request_body` | TEXT | NVARCHAR(MAX) | NULL | — | truncated, masked |
| `response_status` | SMALLINT | SMALLINT | NULL | — |  |
| `response_headers` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `response_body` | TEXT | NVARCHAR(MAX) | NULL | — | truncated |
| `duration_ms` | INT | INT | NULL | — |  |
| `attempt` | SMALLINT | SMALLINT | NOT NULL | 0 |  |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `pending`, `succeeded`, `failed`, `retrying`, `blocked`, `signature_invalid` |
| `error` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `next_attempt_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |

- **Primary key:** `pk_webhook_deliveries` (`id`); SQL Server clustered.
- **Unique:** `uq_webhook_deliveries_uuid` (`uuid`)
- **Index:** `ix_webhook_deliveries_organization_id_status_created_at` (`organization_id`, `status`, `created_at`)
- **Index:** `ix_webhook_deliveries_webhook_id_created_at` (`webhook_id`, `created_at`)
- **Index:** `ix_webhook_deliveries_inbound_endpoint_id_created_at` (`inbound_endpoint_id`, `created_at`)
- **Index:** `ix_webhook_deliveries_action_step_id` (`action_step_id`) — supports FK
- **Index:** `ix_webhook_deliveries_automation_run_id` (`automation_run_id`) — supports FK
- **Foreign key:** `fk_webhook_deliveries_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_webhook_deliveries_webhook_id`: `webhook_id` → `webhooks`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_webhook_deliveries_inbound_endpoint_id`: `inbound_endpoint_id` → `inbound_endpoints`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_webhook_deliveries_action_step_id`: `action_step_id` → `action_steps`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_webhook_deliveries_automation_run_id`: `automation_run_id` → `automation_runs`(`id`) ON DELETE NO ACTION
- **Check:** `ck_webhook_deliveries_direction`: `direction IN ('outgoing', 'incoming')` (both engines)
- **Check:** `ck_webhook_deliveries_status`: `status IN ('pending', 'succeeded', 'failed', 'retrying', 'blocked', 'signature_invalid')` (both engines)
- **Check (SQL Server):** `ck_webhook_deliveries_request_headers_json`: `ISJSON(request_headers) = 1`
- **Check (SQL Server):** `ck_webhook_deliveries_response_headers_json`: `ISJSON(response_headers) = 1`

### 10.23 Appearance, pages & help

**`themes`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `application_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `applications.id` NO ACTION; NULL = organization/global |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `is_default` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `is_preset` | TINYINT(1) | BIT | NOT NULL | 0 | built-in presets are system rows (not business content) |
| `tokens` | JSON | NVARCHAR(MAX) | NOT NULL | — | colors (primary, accent, semantic, surface, border) light & dark, radius, shadow depth, density, font family per script (arabic, latin) |
| `custom_css` | TEXT | NVARCHAR(MAX) | NULL | — | as authored |
| `custom_css_compiled` | TEXT | NVARCHAR(MAX) | NULL | — | sanitized + scoped output |
| `contrast_report` | JSON | NVARCHAR(MAX) | NULL | — | WCAG checks; warnings shown before save |
| `version` | INT | INT | NOT NULL | — | cache-busting |

- **Primary key:** `pk_themes` (`id`); SQL Server clustered.
- **Unique:** `uq_themes_uuid` (`uuid`)
- **Unique:** `uq_themes_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_themes_created_by` (`created_by`) — supports FK
- **Index:** `ix_themes_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_themes_application_id` (`application_id`) — supports FK
- **Foreign key:** `fk_themes_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_themes_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_themes_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_themes_application_id`: `application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_themes_tokens_json`: `ISJSON(tokens) = 1`
- **Check (SQL Server):** `ck_themes_contrast_report_json`: `ISJSON(contrast_report) = 1`
- Translatable: `name`, `login_welcome`, `legal_links`, `support_contact`, `browser_title`.

**`theme_assets`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `theme_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `themes.id` CASCADE |
| `kind` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `logo`, `logo_dark`, `favicon`, `login_background`, `email_header`, `email_footer`, `font_arabic`, `font_latin` |
| `locale` | VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(10) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `file_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `files.id` NO ACTION |

- **Primary key:** `pk_theme_assets` (`id`); SQL Server clustered.
- **Index:** `ix_theme_assets_theme_id_kind_locale` (`theme_id`, `kind`, `locale`)
- **Index:** `ix_theme_assets_file_id` (`file_id`) — supports FK
- **Foreign key:** `fk_theme_assets_theme_id`: `theme_id` → `themes`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_theme_assets_file_id`: `file_id` → `files`(`id`) ON DELETE NO ACTION
- **Check:** `ck_theme_assets_kind`: `kind IN ('logo', 'logo_dark', 'favicon', 'login_background', 'email_header', 'email_footer', 'font_arabic', 'font_latin')` (both engines)

**`pages`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `deleted_at` | DATETIME(6) | DATETIME2(6) | NULL | — | soft delete |
| `deleted_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `application_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `applications.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `content`, `link`, `dashboard`, `report`, `form_host`, `knowledge`, `changelog` |
| `url` | VARCHAR(2048) | NVARCHAR(2048) | NULL | — |  |
| `dashboard_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `dashboards.id` NO ACTION |
| `report_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `reports.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `layout` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `is_published` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `published_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_pages` (`id`); SQL Server clustered.
- **Unique:** `uq_pages_uuid` (`uuid`)
- **Unique:** `uq_pages_application_id_key` (`application_id`, `key`)
- **Index:** `ix_pages_organization_id_deleted_at` (`organization_id`, `deleted_at`)
- **Index:** `ix_pages_created_by` (`created_by`) — supports FK
- **Index:** `ix_pages_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_pages_deleted_by` (`deleted_by`) — supports FK
- **Index:** `ix_pages_dashboard_id` (`dashboard_id`) — supports FK
- **Index:** `ix_pages_report_id` (`report_id`) — supports FK
- **Index:** `ix_pages_form_id` (`form_id`) — supports FK
- **Foreign key:** `fk_pages_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_pages_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_pages_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_pages_deleted_by`: `deleted_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_pages_application_id`: `application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_pages_dashboard_id`: `dashboard_id` → `dashboards`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_pages_report_id`: `report_id` → `reports`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_pages_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Check:** `ck_pages_type`: `type IN ('content', 'link', 'dashboard', 'report', 'form_host', 'knowledge', 'changelog')` (both engines)
- **Check (SQL Server):** `ck_pages_layout_json`: `ISJSON(layout) = 1`
- Translatable: `title`, `content` (sanitized HTML). Permission `page.{uuid}.view`.

**`page_widgets`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `page_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `pages.id` CASCADE |
| `home_screen_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `home_screens.id` NO ACTION |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | registry-extensible; values: `my_work`, `my_records`, `chart`, `kpi`, `shortcuts`, `announcements`, `recent_activity`, `pinned_links`, `embedded_table`, `report`, `html` |
| `config` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `layout` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `visibility_condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION |
| `sort_order` | INT | INT | NOT NULL | 0 |  |

- **Primary key:** `pk_page_widgets` (`id`); SQL Server clustered.
- **Unique:** `uq_page_widgets_uuid` (`uuid`)
- **Index:** `ix_page_widgets_page_id_sort_order` (`page_id`, `sort_order`)
- **Index:** `ix_page_widgets_home_screen_id_sort_order` (`home_screen_id`, `sort_order`)
- **Index:** `ix_page_widgets_visibility_condition_id` (`visibility_condition_id`) — supports FK
- **Foreign key:** `fk_page_widgets_page_id`: `page_id` → `pages`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_page_widgets_home_screen_id`: `home_screen_id` → `home_screens`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_page_widgets_visibility_condition_id`: `visibility_condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Check:** `ck_page_widgets_type`: `type IN ('my_work', 'my_records', 'chart', 'kpi', 'shortcuts', 'announcements', 'recent_activity', 'pinned_links', 'embedded_table', 'report', 'html')` (both engines)
- **Check (SQL Server):** `ck_page_widgets_config_json`: `ISJSON(config) = 1`
- **Check (SQL Server):** `ck_page_widgets_layout_json`: `ISJSON(layout) = 1`
- Translatable: `title`, `content`.

**`home_screens`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `application_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `applications.id` NO ACTION |
| `target_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `default`, `role`, `department`, `user` |
| `target_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `priority` | INT | INT | NOT NULL | 0 | user > role > department > default; then priority |
| `layout` | JSON | NVARCHAR(MAX) | NOT NULL | — |  |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_home_screens` (`id`); SQL Server clustered.
- **Unique:** `uq_home_screens_uuid` (`uuid`)
- **Index:** `ix_home_screens_organization_id_application_id_targ_0e5da274` (`organization_id`, `application_id`, `target_type`, `target_id`)
- **Index:** `ix_home_screens_created_by` (`created_by`) — supports FK
- **Index:** `ix_home_screens_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_home_screens_application_id` (`application_id`) — supports FK
- **Foreign key:** `fk_home_screens_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_home_screens_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_home_screens_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_home_screens_application_id`: `application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Check:** `ck_home_screens_target_type`: `target_type IN ('default', 'role', 'department', 'user')` (both engines)
- **Check (SQL Server):** `ck_home_screens_layout_json`: `ISJSON(layout) = 1`
- Translatable: `name`.

**`announcements`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `application_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `applications.id` NO ACTION |
| `severity` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `info`, `success`, `warning`, `danger` |
| `placement` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `banner`, `home`, `bell` |
| `audience_role_ids` | JSON | NVARCHAR(MAX) | NULL | — | NULL = everyone |
| `starts_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `ends_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `is_dismissible` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `is_published` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `published_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |

- **Primary key:** `pk_announcements` (`id`); SQL Server clustered.
- **Unique:** `uq_announcements_uuid` (`uuid`)
- **Index:** `ix_announcements_organization_id_is_published_starts_at` (`organization_id`, `is_published`, `starts_at`)
- **Index:** `ix_announcements_created_by` (`created_by`) — supports FK
- **Index:** `ix_announcements_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_announcements_application_id` (`application_id`) — supports FK
- **Index:** `ix_announcements_published_by` (`published_by`) — supports FK
- **Foreign key:** `fk_announcements_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_announcements_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_announcements_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_announcements_application_id`: `application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_announcements_published_by`: `published_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_announcements_severity`: `severity IN ('info', 'success', 'warning', 'danger')` (both engines)
- **Check:** `ck_announcements_placement`: `placement IN ('banner', 'home', 'bell')` (both engines)
- **Check (SQL Server):** `ck_announcements_audience_role_ids_json`: `ISJSON(audience_role_ids) = 1`
- Translatable: `title`, `body`.

**`announcement_dismissals`** (supporting)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `announcement_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `announcements.id` CASCADE |
| `user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `dismissed_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |

- **Primary key:** `pk_announcement_dismissals` (`announcement_id`, `user_id`); SQL Server clustered.
- **Index:** `ix_announcement_dismissals_user_id` (`user_id`) — supports FK
- **Foreign key:** `fk_announcement_dismissals_announcement_id`: `announcement_id` → `announcements`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_announcement_dismissals_user_id`: `user_id` → `users`(`id`) ON DELETE NO ACTION

**`help_content`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `target_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `form`, `field`, `group`, `page`, `application`, `admin_area` |
| `target_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `display` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `tooltip`, `side_panel`, `help_page` |
| `is_published` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `sort_order` | INT | INT | NOT NULL | 0 |  |

- **Primary key:** `pk_help_content` (`id`); SQL Server clustered.
- **Unique:** `uq_help_content_uuid` (`uuid`)
- **Index:** `ix_help_content_target_type_target_id` (`target_type`, `target_id`)
- **Index:** `ix_help_content_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_help_content_created_by` (`created_by`) — supports FK
- **Index:** `ix_help_content_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_help_content_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_help_content_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_help_content_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_help_content_target_type`: `target_type IN ('form', 'field', 'group', 'page', 'application', 'admin_area')` (both engines)
- **Check:** `ck_help_content_display`: `display IN ('tooltip', 'side_panel', 'help_page')` (both engines)
- Translatable: `title`, `body`.

**`tours`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `route_key` | VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(128) COLLATE Latin1_General_100_BIN2 | NULL | — | page/admin area |
| `audience_role_ids` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `trigger` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `first_use`, `manual` |
| `steps` | JSON | NVARCHAR(MAX) | NOT NULL | — | `[{target_selector, placement, step_key}]` |
| `version` | INT | INT | NOT NULL | — | bump re-shows to users |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_tours` (`id`); SQL Server clustered.
- **Unique:** `uq_tours_uuid` (`uuid`)
- **Index:** `ix_tours_form_id` (`form_id`)
- **Index:** `ix_tours_route_key` (`route_key`)
- **Index:** `ix_tours_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_tours_created_by` (`created_by`) — supports FK
- **Index:** `ix_tours_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_tours_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_tours_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_tours_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_tours_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Check:** `ck_tours_trigger`: `trigger IN ('first_use', 'manual')` (both engines)
- **Check (SQL Server):** `ck_tours_audience_role_ids_json`: `ISJSON(audience_role_ids) = 1`
- **Check (SQL Server):** `ck_tours_steps_json`: `ISJSON(steps) = 1`
- Translatable: `name`, `steps.<step_key>.title`, `steps.<step_key>.body`.

**`tour_progress`** (supporting)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `tour_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `tours.id` CASCADE |
| `user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `version` | INT | INT | NOT NULL | — |  |
| `completed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `dismissed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |

- **Primary key:** `pk_tour_progress` (`tour_id`, `user_id`); SQL Server clustered.
- **Index:** `ix_tour_progress_user_id` (`user_id`) — supports FK
- **Foreign key:** `fk_tour_progress_tour_id`: `tour_id` → `tours`(`id`) ON DELETE CASCADE
- **Foreign key:** `fk_tour_progress_user_id`: `user_id` → `users`(`id`) ON DELETE NO ACTION
- Reset = delete rows for a user/role (not audit data).

**`search_configs`** (supporting — §4.29 global search)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` CASCADE |
| `is_enabled` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `fields` | JSON | NVARCHAR(MAX) | NOT NULL | — | `[{field_uuid, weight}]` |
| `result_template` | JSON | NVARCHAR(MAX) | NOT NULL | — | title/subtitle paths |
| `role_scope_ids` | JSON | NVARCHAR(MAX) | NULL | — | roles allowed to search this form (still record-scoped) |

- **Primary key:** `pk_search_configs` (`id`); SQL Server clustered.
- **Unique:** `uq_search_configs_form_id` (`form_id`)
- **Index:** `ix_search_configs_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_search_configs_created_by` (`created_by`) — supports FK
- **Index:** `ix_search_configs_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_search_configs_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_search_configs_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_search_configs_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_search_configs_form_id`: `form_id` → `forms`(`id`) ON DELETE CASCADE
- **Check (SQL Server):** `ck_search_configs_fields_json`: `ISJSON(fields) = 1`
- **Check (SQL Server):** `ck_search_configs_result_template_json`: `ISJSON(result_template) = 1`
- **Check (SQL Server):** `ck_search_configs_role_scope_ids_json`: `ISJSON(role_scope_ids) = 1`

### 10.24 Access policies, flags, impersonation, preferences, usage

**`access_policies`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `key` | VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(48) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `ip_allowlist` | JSON | NVARCHAR(MAX) | NULL | — | CIDRs |
| `time_windows` | JSON | NVARCHAR(MAX) | NULL | — | `[{days:[…], from, to, timezone}]` |
| `max_concurrent_sessions` | SMALLINT | SMALLINT | NULL | — |  |
| `session_idle_minutes` | INT | INT | NOT NULL | — |  |
| `session_absolute_minutes` | INT | INT | NOT NULL | — |  |
| `require_trusted_device` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `require_2fa` | TINYINT(1) | BIT | NOT NULL | 0 |  |
| `is_admin_policy` | TINYINT(1) | BIT | NOT NULL | 0 |  |

- **Primary key:** `pk_access_policies` (`id`); SQL Server clustered.
- **Unique:** `uq_access_policies_uuid` (`uuid`)
- **Unique:** `uq_access_policies_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_access_policies_created_by` (`created_by`) — supports FK
- **Index:** `ix_access_policies_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_access_policies_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_access_policies_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_access_policies_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Check (SQL Server):** `ck_access_policies_ip_allowlist_json`: `ISJSON(ip_allowlist) = 1`
- **Check (SQL Server):** `ck_access_policies_time_windows_json`: `ISJSON(time_windows) = 1`
- Translatable: `name`. A user's effective policy is the strictest combination across roles.

**`feature_flags`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `key` | VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `application_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `applications.id` NO ACTION |
| `target_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `form`, `action`, `page`, `capability` |
| `target_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `is_enabled` | TINYINT(1) | BIT | NOT NULL | 1 |  |
| `audience` | JSON | NVARCHAR(MAX) | NOT NULL | — | `{roles:[], departments:[], users:[]}`; empty = everyone when enabled |

- **Primary key:** `pk_feature_flags` (`id`); SQL Server clustered.
- **Unique:** `uq_feature_flags_uuid` (`uuid`)
- **Unique:** `uq_feature_flags_organization_id_key` (`organization_id`, `key`)
- **Index:** `ix_feature_flags_created_by` (`created_by`) — supports FK
- **Index:** `ix_feature_flags_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_feature_flags_application_id` (`application_id`) — supports FK
- **Foreign key:** `fk_feature_flags_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_feature_flags_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_feature_flags_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_feature_flags_application_id`: `application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Check:** `ck_feature_flags_target_type`: `target_type IN ('form', 'action', 'page', 'capability')` (both engines)
- **Check (SQL Server):** `ck_feature_flags_audience_json`: `ISJSON(audience) = 1`
- Translatable: `description`.

**`impersonation_sessions`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `impersonator_user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `impersonated_user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` NO ACTION |
| `reason` | TEXT | NVARCHAR(MAX) | NOT NULL | — |  |
| `started_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `expires_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `ended_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `end_reason` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | values: `manual`, `expired`, `logout`, `revoked` |
| `ip_address` | VARCHAR(45) | NVARCHAR(45) | NOT NULL | — |  |
| `user_agent` | TEXT | NVARCHAR(MAX) | NULL | — |  |

- **Primary key:** `pk_impersonation_sessions` (`id`); SQL Server clustered.
- **Unique:** `uq_impersonation_sessions_uuid` (`uuid`)
- **Index:** `ix_impersonation_sessions_impersonator_user_id_started_at` (`impersonator_user_id`, `started_at`)
- **Index:** `ix_impersonation_sessions_impersonated_user_id` (`impersonated_user_id`)
- **Index:** `ix_impersonation_sessions_organization_id` (`organization_id`) — supports FK
- **Foreign key:** `fk_impersonation_sessions_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_impersonation_sessions_impersonator_user_id`: `impersonator_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_impersonation_sessions_impersonated_user_id`: `impersonated_user_id` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_impersonation_sessions_end_reason`: `end_reason IN ('manual', 'expired', 'logout', 'revoked')` (both engines)

**`user_preferences`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `user_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `users.id` CASCADE; unique |
| `locale` | VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(10) COLLATE Latin1_General_100_BIN2 | NULL | — |  |
| `timezone` | VARCHAR(64) | NVARCHAR(64) | NULL | — |  |
| `calendar` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | values: `gregorian`, `hijri`, `both` |
| `date_format` | VARCHAR(32) | NVARCHAR(32) | NULL | — |  |
| `number_format` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `digits` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | values: `western`, `arabic_indic` |
| `theme_mode` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `light`, `dark`, `system` |
| `density` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | values: `compact`, `normal`, `comfortable` |
| `notification_channels` | JSON | NVARCHAR(MAX) | NULL | — | per notification type → channel keys |
| `digest_frequency` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `none`, `daily`, `weekly` |
| `landing_page` | JSON | NVARCHAR(MAX) | NULL | — |  |
| `pinned_records` | JSON | NVARCHAR(MAX) | NULL | — | `[{form_uuid, record_id}]` (max 50) |
| `shortcuts` | JSON | NVARCHAR(MAX) | NULL | — |  |

- **Primary key:** `pk_user_preferences` (`id`); SQL Server clustered.
- **Unique:** `uq_user_preferences_user_id` (`user_id`)
- **Foreign key:** `fk_user_preferences_user_id`: `user_id` → `users`(`id`) ON DELETE CASCADE
- **Check:** `ck_user_preferences_calendar`: `calendar IN ('gregorian', 'hijri', 'both')` (both engines)
- **Check:** `ck_user_preferences_digits`: `digits IN ('western', 'arabic_indic')` (both engines)
- **Check:** `ck_user_preferences_theme_mode`: `theme_mode IN ('light', 'dark', 'system')` (both engines)
- **Check:** `ck_user_preferences_density`: `density IN ('compact', 'normal', 'comfortable')` (both engines)
- **Check:** `ck_user_preferences_digest_frequency`: `digest_frequency IN ('none', 'daily', 'weekly')` (both engines)
- **Check (SQL Server):** `ck_user_preferences_number_format_json`: `ISJSON(number_format) = 1`
- **Check (SQL Server):** `ck_user_preferences_notification_channels_json`: `ISJSON(notification_channels) = 1`
- **Check (SQL Server):** `ck_user_preferences_landing_page_json`: `ISJSON(landing_page) = 1`
- **Check (SQL Server):** `ck_user_preferences_pinned_records_json`: `ISJSON(pinned_records) = 1`
- **Check (SQL Server):** `ck_user_preferences_shortcuts_json`: `ISJSON(shortcuts) = 1`
- All values validated against admin-set limits (`settings.self_service`).

**`usage_metrics`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `day` | DATE | DATE | NOT NULL | — |  |
| `metric` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `form_view`, `form_start`, `form_submit`, `form_abandon`, `field_left_empty`, `page_view`, `search` |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `field_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `fields.id` NO ACTION |
| `user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `dims_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | of (`metric`, `form_id`, `field_id`, `user_id`) |
| `count` | INT | INT | NOT NULL | — |  |

- **Primary key:** `pk_usage_metrics` (`id`); SQL Server clustered.
- **Unique:** `uq_usage_metrics_day_dims_hash` (`day`, `dims_hash`)
- **Index:** `ix_usage_metrics_form_id_day` (`form_id`, `day`)
- **Index:** `ix_usage_metrics_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_usage_metrics_field_id` (`field_id`) — supports FK
- **Index:** `ix_usage_metrics_user_id` (`user_id`) — supports FK
- **Foreign key:** `fk_usage_metrics_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_usage_metrics_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_usage_metrics_field_id`: `field_id` → `fields`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_usage_metrics_user_id`: `user_id` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_usage_metrics_metric`: `metric IN ('form_view', 'form_start', 'form_submit', 'form_abandon', 'field_left_empty', 'page_view', 'search')` (both engines)

### 10.25 Retention & personal data

**`retention_policies`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `data_class` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `records`, `audit_logs`, `error_logs`, `email_logs`, `notification_logs`, `submission_journal`, `download_files`, `attachments`, `recycle_bin` |
| `application_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `applications.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `retention_days` | INT | INT | NOT NULL | — |  |
| `date_basis` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `created_at`, `updated_at`, `final_status_at` |
| `end_action` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | audit logs: archive only (chain preserved); values: `archive`, `export_then_delete`, `delete` |
| `archive_disk` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NULL | — | cold storage disk |
| `scheduled_task_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `scheduled_tasks.id` NO ACTION |
| `is_active` | TINYINT(1) | BIT | NOT NULL | 1 |  |

- **Primary key:** `pk_retention_policies` (`id`); SQL Server clustered.
- **Unique:** `uq_retention_policies_uuid` (`uuid`)
- **Index:** `ix_retention_policies_organization_id_data_class` (`organization_id`, `data_class`)
- **Index:** `ix_retention_policies_created_by` (`created_by`) — supports FK
- **Index:** `ix_retention_policies_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_retention_policies_application_id` (`application_id`) — supports FK
- **Index:** `ix_retention_policies_form_id` (`form_id`) — supports FK
- **Index:** `ix_retention_policies_scheduled_task_id` (`scheduled_task_id`) — supports FK
- **Foreign key:** `fk_retention_policies_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_retention_policies_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_retention_policies_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_retention_policies_application_id`: `application_id` → `applications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_retention_policies_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_retention_policies_scheduled_task_id`: `scheduled_task_id` → `scheduled_tasks`(`id`) ON DELETE NO ACTION
- **Check:** `ck_retention_policies_data_class`: `data_class IN ('records', 'audit_logs', 'error_logs', 'email_logs', 'notification_logs', 'submission_journal', 'download_files', 'attachments', 'recycle_bin')` (both engines)
- **Check:** `ck_retention_policies_date_basis`: `date_basis IN ('created_at', 'updated_at', 'final_status_at')` (both engines)
- **Check:** `ck_retention_policies_end_action`: `end_action IN ('archive', 'export_then_delete', 'delete')` (both engines)
- Translatable: `name`.

**`retention_runs`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `retention_policy_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `retention_policies.id` NO ACTION |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `running`, `succeeded`, `failed`, `partially_failed` |
| `scanned_count` | BIGINT | BIGINT | NOT NULL | 0 |  |
| `archived_count` | BIGINT | BIGINT | NOT NULL | 0 |  |
| `deleted_count` | BIGINT | BIGINT | NOT NULL | 0 |  |
| `skipped_legal_hold` | BIGINT | BIGINT | NOT NULL | — |  |
| `export_file_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `files.id` NO ACTION |
| `started_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `finished_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `error` | TEXT | NVARCHAR(MAX) | NULL | — |  |
| `triggered_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `correlation_id` | VARCHAR(36) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(36) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |

- **Primary key:** `pk_retention_runs` (`id`); SQL Server clustered.
- **Unique:** `uq_retention_runs_uuid` (`uuid`)
- **Index:** `ix_retention_runs_retention_policy_id_started_at` (`retention_policy_id`, `started_at`)
- **Index:** `ix_retention_runs_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_retention_runs_export_file_id` (`export_file_id`) — supports FK
- **Index:** `ix_retention_runs_triggered_by` (`triggered_by`) — supports FK
- **Foreign key:** `fk_retention_runs_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_retention_runs_retention_policy_id`: `retention_policy_id` → `retention_policies`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_retention_runs_export_file_id`: `export_file_id` → `files`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_retention_runs_triggered_by`: `triggered_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_retention_runs_status`: `status IN ('running', 'succeeded', 'failed', 'partially_failed')` (both engines)

**`archived_records`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `source_table` | VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(60) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `retention_run_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `retention_runs.id` NO ACTION |
| `disk` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `path` | VARCHAR(1024) | NVARCHAR(1024) | NOT NULL | — |  |
| `payload_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — |  |
| `archived_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `restorable_until` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `restored_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `restored_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |

- **Primary key:** `pk_archived_records` (`id`); SQL Server clustered.
- **Index:** `ix_archived_records_form_id_record_id` (`form_id`, `record_id`)
- **Index:** `ix_archived_records_source_table_archived_at` (`source_table`, `archived_at`)
- **Index:** `ix_archived_records_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_archived_records_retention_run_id` (`retention_run_id`) — supports FK
- **Index:** `ix_archived_records_restored_by` (`restored_by`) — supports FK
- **Foreign key:** `fk_archived_records_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_archived_records_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_archived_records_retention_run_id`: `retention_run_id` → `retention_runs`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_archived_records_restored_by`: `restored_by` → `users`(`id`) ON DELETE NO ACTION

**`legal_holds`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `form_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `forms.id` NO ACTION |
| `record_id` | BIGINT UNSIGNED | BIGINT | NULL | — | single record |
| `condition_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `conditions.id` NO ACTION — set of records |
| `reason` | TEXT | NVARCHAR(MAX) | NOT NULL | — |  |
| `placed_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — |  |
| `lifted_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `lifted_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `lift_reason` | TEXT | NVARCHAR(MAX) | NULL | — |  |

- **Primary key:** `pk_legal_holds` (`id`); SQL Server clustered.
- **Unique:** `uq_legal_holds_uuid` (`uuid`)
- **Index:** `ix_legal_holds_form_id_record_id_lifted_at` (`form_id`, `record_id`, `lifted_at`)
- **Index:** `ix_legal_holds_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_legal_holds_created_by` (`created_by`) — supports FK
- **Index:** `ix_legal_holds_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_legal_holds_condition_id` (`condition_id`) — supports FK
- **Index:** `ix_legal_holds_lifted_by` (`lifted_by`) — supports FK
- **Foreign key:** `fk_legal_holds_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_legal_holds_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_legal_holds_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_legal_holds_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_legal_holds_condition_id`: `condition_id` → `conditions`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_legal_holds_lifted_by`: `lifted_by` → `users`(`id`) ON DELETE NO ACTION
- Records under hold also carry `legal_hold` = 1 in their table for fast exclusion (§11.2).

**`personal_data_requests`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `locate`, `export`, `delete`, `anonymize` |
| `subject` | TEXT | NVARCHAR(MAX) | NOT NULL | — | encrypted JSON of identifiers (email, national id, user id, name) |
| `subject_hash` | CHAR(64) CHARACTER SET ascii COLLATE ascii_bin | CHAR(64) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | blind index for lookups |
| `status` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `received`, `locating`, `awaiting_review`, `executing`, `completed`, `rejected`, `failed` |
| `findings` | JSON | NVARCHAR(MAX) | NULL | — | per form/log: counts and record ids |
| `result_file_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `files.id` NO ACTION |
| `justification_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `justifications.id` NO ACTION |
| `completed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `completed_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |

- **Primary key:** `pk_personal_data_requests` (`id`); SQL Server clustered.
- **Unique:** `uq_personal_data_requests_uuid` (`uuid`)
- **Index:** `ix_personal_data_requests_organization_id_status` (`organization_id`, `status`)
- **Index:** `ix_personal_data_requests_subject_hash` (`subject_hash`)
- **Index:** `ix_personal_data_requests_created_by` (`created_by`) — supports FK
- **Index:** `ix_personal_data_requests_updated_by` (`updated_by`) — supports FK
- **Index:** `ix_personal_data_requests_result_file_id` (`result_file_id`) — supports FK
- **Index:** `ix_personal_data_requests_justification_id` (`justification_id`) — supports FK
- **Index:** `ix_personal_data_requests_completed_by` (`completed_by`) — supports FK
- **Foreign key:** `fk_personal_data_requests_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_personal_data_requests_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_personal_data_requests_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_personal_data_requests_result_file_id`: `result_file_id` → `files`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_personal_data_requests_justification_id`: `justification_id` → `justifications`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_personal_data_requests_completed_by`: `completed_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_personal_data_requests_type`: `type IN ('locate', 'export', 'delete', 'anonymize')` (both engines)
- **Check:** `ck_personal_data_requests_status`: `status IN ('received', 'locating', 'awaiting_review', 'executing', 'completed', 'rejected', 'failed')` (both engines)
- **Check (SQL Server):** `ck_personal_data_requests_findings_json`: `ISJSON(findings) = 1`

**`subject_keys`** (supporting — crypto-shredding of personal data in logs, ADR-0014)

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `form_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `forms.id` NO ACTION; NULL for user-account subjects |
| `record_id` | BIGINT UNSIGNED | BIGINT | NULL | — |  |
| `user_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION (subject is a user account) |
| `wrapped_key` | TEXT | NVARCHAR(MAX) | NULL | — | data key wrapped by the org `blind_index`/`fields` key; NULL once destroyed |
| `destroyed_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `destroyed_by_request_id` | BIGINT UNSIGNED | BIGINT | NULL | — | → `personal_data_requests.id` NO ACTION |

- **Primary key:** `pk_subject_keys` (`id`); SQL Server clustered.
- **Unique:** `uq_subject_keys_form_id_record_id` (`form_id`, `record_id`) — SQL Server: filtered `WHERE form_id IS NOT NULL AND record_id IS NOT NULL`; MySQL: unique (NULLs never collide)
- **Unique:** `uq_subject_keys_user_id` (`user_id`) — SQL Server: filtered `WHERE user_id IS NOT NULL`; MySQL: unique (NULLs never collide)
- **Index:** `ix_subject_keys_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_subject_keys_destroyed_by_request_id` (`destroyed_by_request_id`) — supports FK
- **Foreign key:** `fk_subject_keys_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_subject_keys_form_id`: `form_id` → `forms`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_subject_keys_user_id`: `user_id` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_subject_keys_destroyed_by_request_id`: `destroyed_by_request_id` → `personal_data_requests`(`id`) ON DELETE NO ACTION

**`storage_quotas`**

| Column | MySQL 8 | SQL Server 2019 | Null | Default | Notes |
|---|---|---|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | BIGINT IDENTITY(1,1) | NOT NULL | — |  |
| `uuid` | CHAR(36) CHARACTER SET ascii COLLATE ascii_bin | UNIQUEIDENTIFIER | NOT NULL | — | stable cross-environment identity (ADR-0003) |
| `organization_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — | → `organizations.id` NO ACTION |
| `created_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `updated_at` | DATETIME(6) | DATETIME2(6) | NOT NULL | — | set by the application (UTC) |
| `created_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `updated_by` | BIGINT UNSIGNED | BIGINT | NULL | — | → `users.id` NO ACTION |
| `scope_type` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `organization`, `application`, `form` |
| `scope_id` | BIGINT UNSIGNED | BIGINT | NOT NULL | — |  |
| `warn_bytes` | BIGINT | BIGINT | NOT NULL | — |  |
| `limit_bytes` | BIGINT | BIGINT | NOT NULL | — |  |
| `used_bytes` | BIGINT | BIGINT | NOT NULL | 0 |  |
| `measured_at` | DATETIME(6) | DATETIME2(6) | NULL | — |  |
| `state` | VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin | VARCHAR(32) COLLATE Latin1_General_100_BIN2 | NOT NULL | — | values: `ok`, `warning`, `exceeded` |

- **Primary key:** `pk_storage_quotas` (`id`); SQL Server clustered.
- **Unique:** `uq_storage_quotas_uuid` (`uuid`)
- **Unique:** `uq_storage_quotas_scope_type_scope_id` (`scope_type`, `scope_id`)
- **Index:** `ix_storage_quotas_organization_id` (`organization_id`) — supports FK
- **Index:** `ix_storage_quotas_created_by` (`created_by`) — supports FK
- **Index:** `ix_storage_quotas_updated_by` (`updated_by`) — supports FK
- **Foreign key:** `fk_storage_quotas_organization_id`: `organization_id` → `organizations`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_storage_quotas_created_by`: `created_by` → `users`(`id`) ON DELETE NO ACTION
- **Foreign key:** `fk_storage_quotas_updated_by`: `updated_by` → `users`(`id`) ON DELETE NO ACTION
- **Check:** `ck_storage_quotas_scope_type`: `scope_type IN ('organization', 'application', 'form')` (both engines)
- **Check:** `ck_storage_quotas_state`: `state IN ('ok', 'warning', 'exceeded')` (both engines)

### 10.26 Entity checklist (specification §7)

| §7 group | Entities → tables |
|---|---|
| Structure | Applications→`applications`, MenuItems→`menu_items`, Collections→`collections`, Forms→`forms`, FormVersions→`form_versions`, FieldGroups→`field_groups`, Fields→`fields`, FieldOptions→`field_options`, FieldAccessRules→`field_access_rules`, Conditions→`conditions`, Relations→`relations`, FieldTemplates→`field_templates` |
| Access | Users→`users`, Departments→`departments`, Roles→`roles`, UserRoles→`user_roles`, Permissions→`permissions`, PermissionAssignments→`permission_assignments`, RecordAccessRules→`record_access_rules`, Sessions→`sessions` |
| Workflow | Statuses, Transitions, StatusHistory→`status_history`, StatusMappings, SlaRules |
| Views & actions | Views, ViewColumns, Filters, SavedViews, ViewPanels, ReferencePreviews, Actions, ActionSteps |
| Custom downloads | DownloadProfiles, DownloadProfileColumns, DownloadProfileFilters, DownloadSchedules, DownloadJobs |
| Justification | JustificationRules, Justifications, JustificationReasonCodes, JustificationAttachments |
| Assignment | Assignments, AssignmentRules, Queues, QueueClaims, Delegations, ApprovalRequests, ApprovalDecisions |
| Retention | RetentionPolicies, RetentionRuns, ArchivedRecords, LegalHolds, PersonalDataRequests, StorageQuotas |
| Schema management | MigrationPlans, MigrationSteps, SchemaSnapshots, SchemaReconciliationReports, PublishLocks |
| Localization | Locales, Translations |
| Appearance & pages | Themes, ThemeAssets, Pages, PageWidgets, HomeScreens, Announcements, HelpContent→`help_content`, Tours |
| Blueprints | Blueprints, BlueprintVersions, BlueprintInstances |
| Automation | Automations, AutomationTriggers, AutomationSteps, AutomationRuns, ScheduledTasks |
| External access | ExternalForms, ExternalUsers, AccessTokens, SignatureRequests, SubmissionThrottles |
| Integrations | ExternalDataSources, SyncJobs, SyncRuns, NotificationChannels, InboundEndpoints |
| Reference data | BusinessCalendars, Holidays, NumberSequences, Currencies, ExchangeRates, UnitsOfMeasure→`units_of_measure` |
| Data quality | DuplicateRules, MergeHistory→`merge_history`, BulkOperations, RecycleBin→`recycle_bin` |
| Access & adoption | AccessPolicies, FeatureFlags, ImpersonationSessions, UserPreferences, UsageMetrics |
| Notifications | NotificationRules, EmailTemplates, EmailQueue→`email_queue`, InAppNotifications |
| Documents & reports | DocumentTemplates, Reports, Dashboards, DashboardWidgets |
| Operations & monitoring | SubmissionJournal→`submission_journal`, AuditLogs, ErrorLogs, ErrorGroups |
| Extensibility & integration | Extensions, ExtensionVersions, ApiTokens, Webhooks, WebhookDeliveries |
| System | ConfigPackages, Settings, EgressAllowlist→`egress_allowlist` |

Supporting tables: `organizations`, `encryption_keys`, `environment_drift_reports`,
`department_closure`, `password_histories`, `login_attempts`, `trusted_devices`,
`files`, `record_comments`, `sla_timers`, `saved_view_shares`, `print_layouts`,
`import_mappings`, `import_jobs`, `export_jobs`, `queue_forms`,
`notification_deliveries`, `audit_chain_heads`, `outbox_events`,
`operations_alert_rules`, `announcement_dismissals`, `tour_progress`,
`search_configs`, `subject_keys`, plus Laravel's `failed_jobs`, `job_batches`, `cache`, `cache_locks`.

## 11. Physical table generation strategy

### 11.1 What gets a table

| Metadata | Physical table |
|---|---|
| Form (`kind=form`, `binding_mode=managed`) | `f_{form_key}` |
| Collection (`kind=collection`) | `c_{collection_key}` |
| Repeater group / inline sub-form group | `f_{form_key}__{group_key}` (child table with real FK `parent_id`) — for an inline sub-form of a *linked* form, the linked form's own table with the relation FK |
| many-to-many relation | `p_{form_key}__{relation_key}` pivot (`source_id`, `target_id`, `sort_order`, `created_at`, `created_by`) with FKs to both tables and a unique pair |

Tables of organizations other than the platform organization carry the
organization id after the prefix (`f{org}_{key}`, `c{org}_{key}`), because all
organizations share one database; every name is fitted to 60 characters
(ADR-0028).
| Bound form (`binding_mode=bound`) | an existing table found by introspection; the framework adds only its system columns after admin confirmation in the impact analysis, and never drops anything |

### 11.2 System columns of every record table

| Column | Type | Purpose |
|---|---|---|
| `id` | id | PK |
| `uuid` | uuid | unique — public identity (API, links, packages) |
| `organization_id` | bigint | FK → `organizations.id` NO ACTION; tenant isolation |
| `form_version_id` | bigint | FK → `form_versions.id` NO ACTION — version the record was last submitted with (§4.10) |
| `record_number` | string(64)? | auto-number when configured; unique |
| `status_id` | bigint? | FK → `statuses.id` NO ACTION (workflow forms) |
| `status_changed_at` | datetime? | |
| `row_version` | bigint | optimistic concurrency, starts at 1 |
| `owner_user_id` | bigint? | FK → `users.id` — "own records" scope (creator unless reassigned ownership) |
| `owner_department_id` | bigint? | FK → `departments.id` — department scope |
| `created_by`, `updated_by` | bigint? | FK → `users.id` |
| `created_at`, `updated_at` | datetime | |
| `deleted_at`, `deleted_by` | datetime?, bigint? | soft delete / recycle bin |
| `legal_hold` | bool | denormalized hold flag |
| `search_text` | text? | normalized concatenation of searchable fields (Arabic normalization: alef/ya/ta-marbuta folding, diacritics stripped, digits unified) |
| `external_user_id` | bigint? | FK → `external_users.id` — owner for portal submissions |
| Child tables add | `parent_id` bigint FK → parent table `id` (CASCADE — single path), `sort_order` int | |

Default indexes: `uuid` unique; (`organization_id`, `deleted_at`, `id`);
(`status_id`, `updated_at`); (`owner_user_id`); (`owner_department_id`);
(`created_at`); `record_number` unique (when used); plus one index per field marked
filterable/sortable/indexed or unique (with its scope columns), and every FK
column.

### 11.3 Field → column mapping

The `FieldTypeRegistry` declares each type's storage. Defaults (overridable within
compatible types in the field's *Data & Database Binding* tab):

| Field types | Column(s) |
|---|---|
| text, password (hashed only if flagged as secret), email, tel, url, search, hidden, color, phone, national ID, IBAN, barcode value | `string(n)` (default 255) |
| textarea, rich text, markdown, code, static HTML (not stored) | `text` / `longtext` |
| number, range, slider, rating | `int` / `decimal(p,s)` from step/precision |
| currency | `decimal(19,4)` + `{col}__currency code(3)` when multi-currency |
| percentage, decimal | `decimal(p,s)` |
| auto-number | `string(64)` unique (scope) |
| formula / calculated | stored column of the result type (recomputed on write; read-only) |
| date, month, week | `date` (`month`/`week` stored as first day + display format) |
| time | `time` |
| datetime-local | `datetime` (UTC) + field's timezone handling |
| date range / time range / datetime range | `{col}__from`, `{col}__to` |
| duration | `bigint` seconds |
| checkbox, toggle, consent | `bool` (+ `{col}__at datetime` for consent) |
| radio, select, dropdown, button group, single lookup | `string(255)` for static options; `bigint` FK for collection/form lookups |
| checkbox group, multi-select, tags | `json` array for static options; pivot table for record references |
| user / role / department picker | `bigint` FK (single) or pivot (multiple) |
| country / city picker | FK to the admin's collection |
| file, image, camera, signature | single: `bigint` FK → `files.id`; multiple: `json` array of file ids (+ ownership rows in `files`) |
| map/location | `{col}__lat decimal(10,7)`, `{col}__lng decimal(10,7)`, `{col}__label string(255)?` |
| phone with country code | `{col}` E.164 `string(20)` + `{col}__country code(2)` |
| JSON editor, key-value | `json` |
| output, progress, meter, heading, divider, spacer, image, alert, link, button/submit/reset/image-button | not stored (`is_stored=0`) |
| encrypted fields | `text` ciphertext (+ `{col}__bidx hash` when searchable) |

### 11.4 Generation process

1. The Definition Compiler produces the **target schema** (tables, columns, indexes,
   FKs) for the draft.
2. The `SchemaDiffer` compares it with the **current schema** recorded in the
   published version (not with the live DB — reconciliation covers drift) and
   produces an ordered list of driver-neutral operations.
3. The `MigrationPlanner` turns operations into `migration_steps` with
   forward/reverse specs and marks destructive/online characteristics (§12).
4. Execution is done by the `SchemaExecutor` through the driver layer — these are
   **runtime migrations** recorded in `migration_plans`, not Laravel migration
   files. Laravel migration files exist only for the framework's own metadata tables.

### 11.5 Field removal, rename, and type change

- **Rename** a field label: metadata only. Rename the *key/column*: `rename_column`
  step (reverse = rename back); dependent views/filters/downloads updated by uuid
  references (they never store column names).
- **Remove**: the column is **archived** — renamed to `zz_{column}_v{version}` (the publishing version, so a plan's impact hash is deterministic),
  made nullable, and excluded from the definition; `fields.archived_at`,
  `archived_column_name` recorded; data remains and can be restored with the field.
  Purging archived columns is a separate, explicit, audited admin action that first
  takes a data backup.
- **Type change**: `validate_data` step first scans existing values with the new
  type's converter and reports conflicts (record ids + values) in the impact
  analysis; publish is blocked until the admin resolves conflicts (edit records,
  choose a default, or cancel). Then: add `{col}__new`, `copy_data` converted,
  swap names, archive the old column — reversible because the old column survives.

### 11.6 Relations & integrity

FKs are real (`add_foreign_key`) with DB `NO ACTION` for references. Records are
soft-deleted, so the database never applies on-delete rules; the Record
Pipeline applies each relation's rule inside the delete transaction
(`ReferentialIntegrity`, ADR-0028): the whole cascade is planned first and a
`restrict` anywhere refuses the delete (409 `referenced`); `cascade` soft-deletes
referencing records (recursively), removes referencing repeater rows and
many-to-many links; `set_null` clears the reference. Child-row `parent_id` and
pivot `source_id` use DB `CASCADE` for hard row removal only. The orphan scan
(§19.10) verifies integrity for paths enforced in the application.

## 12. Schema change strategy

### 12.1 Migration plans

A publish produces a `migration_plans` row with ordered `migration_steps`, **all
persisted before execution**. Every step has a `reverse` spec. Steps are designed
to be individually safe:

| Operation | Reverse | Destructive? |
|---|---|---|
| create_table | rename to `zz_` archive (never drop) | no |
| add_column (nullable or with default) | archive_column | no |
| rename_column | rename back | no |
| archive_column | restore_column | no |
| alter_column widen (length ↑, precision ↑, NOT NULL → NULL) | alter back if data fits else snapshot | no |
| alter_column narrow / type change | via new column + copy (§11.5) | lossy only if forced — requires snapshot |
| add_index / add_foreign_key | drop | no |
| drop_index / drop_foreign_key | re-add | no |
| map_status (data update) | inverse mapping from recorded before-values | data change — snapshot of affected rows |
| backfill / copy_data | restore from recorded before-values or snapshot | data change |

### 12.2 Execution and failure handling

```
acquire publish locks (§12.4) → impact analysis confirmed → pre-publish snapshot
(metadata + physical schema; data backup if any destructive/data step) →
plan.status=running → for each step: status=pending→(execute)→applied | failed
on failure:
   plan.status=reversing → execute reverse of applied steps in reverse order
   all reversed → plan.status=reversed, form stays on previous version, admin sees error
   any reverse fails → plan.status=inconsistent; forms.state=schema_inconsistent:
       - records of the form are locked (read-only) for users; publishing blocked
       - admin repair screen: per-step status (applied/failed/reversed/reverse_failed),
         actions: retry remaining steps, retry reverse, mark step manually reconciled
         (with reconciliation check proof), restore from pre-publish snapshot
on success: form_versions row created, forms.current_version_id switched (in one
metadata transaction), definition cache pointer bumped, locks released
```

Because DDL is not transactional on MySQL (and only partially relied on in SQL
Server), the metadata switch happens **after** all steps applied: the running
application keeps serving the previous version until then. New columns are always
nullable or defaulted so old code paths keep working during the window.

### 12.3 Online changes and impact

`supportsOnlineDdl` + `estimateDdlImpact` (row count × measured per-row cost
for the engine, maintained by the reconciliation job) produce per-step
"online / brief lock / blocking, estimated duration". The impact analysis shows
them; blocking steps on tables over a configurable size require an explicit
"schedule in maintenance window" or confirmation.

### 12.4 Locking

- Lock set = the form + every form related to it (relations in either direction,
  child tables, forms whose definitions reference it in lookups/conditions).
- Primary lock: Redis `Lock` per form id (atomic, TTL with heartbeat), persisted as
  `publish_locks` rows (`status=held`, unique `held_key`) — the DB row is the source
  of truth for UI and survives Redis restarts; secondary DB named lock
  (`GET_LOCK`/`sp_getapplock`) during execution.
- Lock acquisition is all-or-nothing in ascending form-id order (no deadlocks). If
  any is held, a `waiting` row is created with `blocked_by_lock_id`; the admin sees
  "queued behind publish of *Form X* by *User Y* started at …"; the publish job is
  released when the blocker finishes (event `PublishLockReleased`).
- While a form is locked, record writes continue; schema steps that need exclusive
  table access take engine locks only for their own duration.

### 12.5 Snapshots and backups

- *Metadata snapshot*: the full previous `form_versions.definition` (always).
- *Physical schema snapshot*: introspected columns/indexes/FKs of affected tables.
- *Data backup* (before any destructive or data-changing step): chunked keyset
  export of affected tables (or affected columns + ids) to JSONL + schema on the
  private disk (or S3), checksummed, encrypted with the org's file key; location and
  retention (`expires_at`, default 30 days, setting) shown to the admin;
  restorable via the repair screen to either engine.

### 12.6 Reconciliation

`SchemaReconciler` compares, for each form, the expected schema (from the
current published version) with introspection of the live engine: missing/extra
tables and columns, type/length/nullability, indexes, FKs. Runs on demand, on a
schedule (default daily), after every failed plan, and before configuration
package import. Results in `schema_reconciliation_reports`; differences alert the
roles configured in operations alert rules.

## 13. Versioning, impact analysis, rollback, environment drift

### 13.1 Lifecycle

`draft` (working tables, autosave every few seconds with optimistic draft
locking per form: `draft_updated_at` compare) → `preview` (renders the draft
CompiledDefinition without publishing; as any role/user) → `impact analysis` →
`publish` (migration plan) → new `form_versions` row.

### 13.2 Impact analysis

`ImpactAnalyzer` computes, from the diff between draft and current version:

- existing records: count, records failing new validation/required rules, type
  conflicts (§11.5), status mapping needs;
- views, view columns, filters, saved views, view panels, reference previews
  referencing removed/changed fields (by uuid path);
- actions, action steps, automations, notification rules, email/document templates
  (placeholders), download profiles (columns, filters), reports, dashboards
  referencing affected fields/statuses;
- permissions: auto-registered permissions added/removed; access rules targeting
  removed fields/groups/statuses;
- linked forms: relations whose target/source changes; lookups and conditions in
  other forms referencing this form;
- schema plan: steps, online/blocking, estimated duration, backup size, rollback class.

Broken references must be resolved (auto-fix suggestions: remove column from view,
remap to a replacement field) before publish is enabled.

### 13.3 Diff and history

Every version stores the full definition and `diff_from_previous` (structured:
added/removed/changed per object uuid with before/after property values). The
visual diff screen renders it side-by-side with the form preview.

### 13.4 Rollback classes

| Class | Determined by | Rollback behavior |
|---|---|---|
| `metadata_only` | no schema operations between versions | publish the older definition as a new version; always reversible |
| `additive_schema` | only create_table/add_column/add_index | new version without the field; added columns archived (not dropped) |
| `destructive` | type narrowed, field removed (archived), status merged, data mapped | not fully reversible; UI names affected records and offers restore-from-snapshot; explicit typed confirmation required |

Rollback is itself a publish (new version number, `rollback_of_version_id` set).

### 13.5 Status changes

Renaming a status is metadata-only. Adding is additive. Removing or merging opens
the **status mapping screen**: for each removed status, choose the target status;
records are counted per status; the mapping becomes `map_status` migration steps
(with before-values recorded) and `status_mappings` rows; `status_history` gets
entries with `source=status_mapping`.

### 13.6 Environment drift & package import

`EnvironmentComparer` takes a package manifest (or a metadata export of another
environment) and produces `environment_drift_reports`: per object uuid,
metadata differences, and per table, physical schema differences (via the
reconciler). Import is refused when `is_ambiguous` (object changed in both
environments since the package's base version, or physical schema in the target
diverges from its own metadata), listing exactly which objects conflict. Otherwise
conflicts are resolved per object (keep target / take package / rename) and the
import runs as a set of publishes under one lock set.

## 14. Metadata JSON schema

All metadata JSON is validated with **JSON Schema (draft 2020-12)** documents
stored in `backend/app/Modules/*/Schemas/*.schema.json` and shared with the
frontend at build time (generated TypeScript types). Validation happens on every
metadata write and on package import. All cross-references use **uuids**, never
numeric ids or column names, so definitions are portable between environments.

### 14.1 Form definition (published snapshot `form_versions.definition`)

```json
{
  "$schema": "https://schemas.core-lcf/form-definition/v1",
  "form": {
    "uuid": "…", "key": "leave_request", "kind": "form", "version": 7,
    "settings": { "…see 14.2…" },
    "i18n": { "name": {"ar": "…", "en": "…"}, "description": {"…": "…"} }
  },
  "groups":   [ { "uuid": "…", "key": "…", "type": "section", "parent": null, "order": 1,
                  "layout": {}, "collapsible": false, "defaultState": "open",
                  "validation": {}, "repeater": null, "wizard": null,
                  "i18n": { "title": {}, "description": {} } } ],
  "fields":   [ { "uuid": "…", "key": "start_date", "type": "date", "group": "<group uuid>",
                  "order": 3, "storage": {}, "options": null, "validation": {},
                  "behavior": {}, "ui": {}, "table": {}, "export": {}, "events": [],
                  "flags": { "encrypted": false, "sensitive": false, "personal": false,
                             "trackChanges": true },
                  "justification": "inherit",
                  "i18n": { "label": {}, "placeholder": {}, "help": {}, "messages": {} } } ],
  "relations":  [ { "uuid": "…", "key": "employee", "type": "many_to_one",
                    "target": "<form uuid>", "kind": "reference", "onDelete": "restrict",
                    "display": "<field uuid>", "inverse": "requests" } ],
  "conditions": [ { "uuid": "…", "owner": {"type": "field", "uuid": "…"},
                    "when": { "…AST (§15)…" }, "effects": [ … ], "else": [ … ],
                    "evaluateOn": "always", "runtime": "client_and_server" } ],
  "access":     [ { "target": {"type": "field", "uuid": "…"},
                    "subject": {"type": "role", "uuid": "…"},
                    "status": "<status uuid>|null", "mode": "edit|null",
                    "access": "read_only", "effect": "allow" } ],
  "justification": [ { "uuid": "…", "scope": "field", "target": "<uuid>",
                       "subject": {"type": "everyone"}, "level": "mandatory",
                       "condition": "<condition uuid>|null", "levelWhen": null,
                       "text": {"min": 10, "max": 1000}, "reasonCodes": {"mode": "required", "set": "edits"},
                       "attachments": {"mode": "optional", "max": 3}, "showSummary": true } ],
  "workflow": { "statuses": [ … ], "transitions": [ … ], "sla": [ … ] },
  "schema":   { "table": "f_leave_request", "columns": [ … ], "indexes": [ … ], "foreignKeys": [ … ] }
}
```

### 14.2 Form settings

`{ autosaveDrafts, allowComments, allowAttachments, attachmentRules,
recordTitle: AST, conflictResolution: "field_by_field", printLayout: uuid,
defaultView: uuid, numbering: uuid, calendar: uuid, workflowEnabled,
dataSharing, submitButtonLabel(i18n), afterSubmit: {redirect: "view|list|new"},
modes: {create, edit, view, print} }`

### 14.3 Conditions & effects

```json
{
  "when": { "type": "binary", "op": "and", "left": { … }, "right": { … } },
  "effects": [
    { "effect": "show" | "hide" | "enable" | "disable" | "read_only" | "require",
      "target": {"type": "field|group|option|action|transition", "uuid": "…"} },
    { "effect": "set_value",   "target": {…}, "value": { "…AST…" } },
    { "effect": "clear_value", "target": {…} },
    { "effect": "reload_options", "target": {…} },
    { "effect": "show_message", "severity": "info|warning|error", "message": {"i18nKey": "…"} },
    { "effect": "block_submit", "message": {"i18nKey": "…"} },
    { "effect": "trigger_action", "action": "<action uuid>" }
  ]
}
```

Visual rule builder ⇄ AST: the builder edits *rule groups* (`IF`, `AND`, `OR`,
nested) whose leaves are `operand · operator · operand`; it serializes directly
to AST nodes (§15) — no text parsing is involved for builder-created rules.
Operators `changed_from_to`, `in_list`, `matches_regex` etc. map to functions
(`changed(f, from, to)`, `in(x, list)`, `matches(x, pattern)`).

### 14.4 Field types (palette registry keys)

Native: `text, password, email, tel, url, search, number, range, date, time,
datetime_local, month, week, checkbox, radio, color, file, hidden, image_button,
button, submit, reset, textarea, select, select_multiple, select_grouped,
datalist, output, progress, meter` (fieldset/legend is the `fieldset` group).
Extended: `rich_text, markdown, code, currency, percentage, decimal, auto_number,
formula, calendar_date, date_range, time_range, datetime_range, duration, toggle,
checkbox_group, radio_group, button_group, dropdown_search, multi_select_chips,
tags, cascading_select, lookup, user_picker, role_picker, department_picker,
country_picker, city_picker, rating, slider, color_palette, file_multi,
image_upload, camera, signature, map_location, phone_intl, national_id, iban,
barcode, json, key_value, consent, static_html, heading, divider, spacer,
display_image, alert_box, link`. Groups: `section, fieldset, card, tabs, tab,
wizard, step, row, column, panel, accordion, repeater, subform`.
Each registry entry declares: storage mapping (§11.3), allowed validation rules,
allowed behaviors, option support, filter type, export writer formatting, client
component key, and JSON schema of its type-specific `ui` properties.

### 14.5 Options source

```json
{ "source": "static" | "collection" | "form" | "query" | "external",
  "collection": "<form uuid>", "valuePath": ["code"], "labelPath": ["name"],
  "query": { "from": "<form uuid>", "where": { "…AST…" }, "sort": [{"path": [...], "dir": "asc"}], "limit": 100 },
  "external": "<external data source uuid>",
  "dependsOn": "<field uuid>", "dependsPath": ["country"],
  "allowCustom": false, "min": 0, "max": 3, "defaults": ["…"],
  "searchable": true, "lazy": true, "pageSize": 50, "groupBy": ["region"] }
```

The visual query builder writes this structure; no SQL is ever typed.

### 14.6 Validation

```json
{ "required": true,
  "length": {"min": 2, "max": 100},
  "number": {"min": 0, "max": 100000, "step": 0.5},
  "pattern": "^[0-9]{10}$",
  "format": "email|url|phone|national_id|iban|numeric|arabic|english|alphanumeric",
  "date": {"min": {"…AST: today() + 7…"}, "max": null,
           "disabledWeekdays": [5, 6], "disabledDates": ["2026-12-01"],
           "noPast": false, "noFuture": false},
  "file": {"types": ["pdf","png"], "mimes": ["application/pdf","image/png"],
           "maxSizeKb": 5120, "maxCount": 3,
           "image": {"minWidth": 200, "maxWidth": 4000, "minHeight": 200, "maxHeight": 4000}},
  "unique": {"scope": ["<field uuid>"], "includeDeleted": false},
  "compare": [{"op": "gt|gte|lt|lte|eq|neq|before|after", "field": "<uuid>"}],
  "async": {"type": "exists_in", "collection": "<uuid>", "path": ["code"]},
  "custom": [{"when": {"…AST…"}, "messageKey": "custom_1"}],
  "messages": {"required": "validation.required", "...": "..."} }
```

Regex patterns are validated for safety on save (compiled with PCRE and the JS
engine; rejected if they fail either or exceed length/complexity limits; executed
with `pcre.backtrack_limit` lowered).

### 14.7 Behavior

```json
{ "default": {"kind": "static|current_user|current_department|now|today|url_param|field|formula|reference",
              "value": "…", "param": "ref", "field": "<uuid>", "expr": {"…AST…"},
              "reference": {"relation": "<uuid>", "path": ["…"]}},
  "formula": {"…AST…"},
  "transforms": ["trim", "uppercase"],
  "mask": "999-999-9999",
  "number": {"thousandSeparator": true, "decimals": 2, "currency": "SAR", "symbolPosition": "after"},
  "date": {"displayFormat": "dd/MM/yyyy", "calendar": "gregorian|hijri|dual",
           "firstDayOfWeek": 0, "timeStep": 15, "hourCycle": "24h", "timezone": "user|utc|fixed:Asia/Riyadh"},
  "digits": "western|arabic_indic|locale",
  "file": {"disk": "private", "folder": "{form}/{yyyy}/{MM}", "naming": "uuid|original_sanitized"},
  "autofill": [{"from": ["employee", "department"], "to": "<field uuid>", "overwrite": false}],
  "justification": {"level": "inherit", "overrides": [ "…rules (§14.10)…" ]} }
```

### 14.8 Field events

```json
[{ "on": "change|focus|blur",
   "do": [ {"type": "set_field", "target": "<uuid>", "value": {"…AST…"}},
           {"type": "reload_options", "target": "<uuid>"},
           {"type": "run_action", "action": "<uuid>"},
           {"type": "call_webhook", "webhook": "<uuid>"},
           {"type": "notify", "severity": "info", "messageKey": "…"} ],
   "when": {"…optional AST…"} }]
```

`call_webhook` and `run_action` execute **server-side** through an authorized API
call (egress gateway, permissions); the client only requests them.

### 14.9 Action steps

```json
{ "type": "update_fields", "set": [{"field": "<uuid>", "value": {"…AST…"}}] }
{ "type": "change_status", "transition": "<uuid>" }
{ "type": "send_email", "template": "<uuid>", "recipients": {"to": [], "cc": [], "bcc": []},
  "attachments": [{"documentTemplate": "<uuid>", "format": "pdf"}] }
{ "type": "call_webhook", "webhook": "<uuid>" }  // or inline {url, method, headers, body:AST}
{ "type": "generate_document", "template": "<uuid>", "format": "pdf", "attachToField": "<uuid>" }
{ "type": "create_linked_record", "form": "<uuid>", "relation": "<uuid>",
  "prefill": [{"field": "<uuid>", "value": {"…AST…"}}], "open": true }
```

### 14.10 Justification rules

As in §14.1 `justification[]`; the JSON schema enforces that exactly one target is
set for the chosen scope and that `levelWhen` is present iff `condition` is set.

### 14.11 Download profiles

```json
{ "uuid": "…", "baseForm": "<uuid>", "formats": ["xlsx", "csv", "pdf"],
  "sheetMode": "single|per_related_form",
  "columns": [
    {"kind": "field", "path": [{"rel": "<relation uuid>", "dir": "out"}, {"rel": "<uuid>", "dir": "in"}, {"field": "<uuid>"}],
     "toMany": {"mode": "aggregate", "fn": "join", "separator": ", "},
     "header": {"ar": "…", "en": "…"}, "width": 20, "format": {"type": "date", "pattern": "yyyy-MM-dd"}},
    {"kind": "calculated", "expr": {"…AST…"}, "header": {}},
    {"kind": "static", "value": "…", "header": {}},
    {"kind": "system", "column": "last_transition_at", "header": {}} ],
  "filters": [{"path": [...], "op": "between", "param": "period"}],
  "parameters": [{"key": "period", "type": "date_range", "required": true}],
  "applyUserFilters": true, "allowSelected": true, "maxRows": 100000,
  "fileName": "{form}-{date:yyyyMMdd}-{user}",
  "xlsx": {"styledHeader": true, "freezeHeader": true, "logo": true, "titleRow": true, "rtlForArabic": true},
  "csv": {"bom": true, "delimiter": ","},
  "pdf": {"orientation": "landscape", "header": true, "footer": true, "pageNumbers": true} }
```

### 14.12 Packages & blueprints

A package is a ZIP: `manifest.json` (objects with type, uuid, version hash,
dependencies), one JSON document per object in the schemas above, translation
bundles per locale, binary assets (document templates, theme assets) with
checksums, and a detached signature (HMAC with the installation's package key).
Blueprints use the same object documents.

## 15. Expression language (summary)

Full specification: [`docs/expression-language.md`](expression-language.md).

- Pure, side-effect-free, total: no I/O, loops, assignment, or user-defined
  functions. Typed values: `text`, `number` (decimal, 34-digit precision,
  never binary float), `boolean`, `date`, `datetime`, `time`, `duration`, `list`,
  `record` (reference), `null`.
- Stored as **JSON AST** (§2 of that document). The builder UI produces ASTs
  directly; a text syntax exists for the formula editor and is parsed **server-side
  only** into AST (the TS runtime also ships the parser for live editing feedback,
  but the stored artifact is always the server-produced AST).
- Two evaluators (PHP, TS) consume the same AST; the conformance corpus
  `docs/conformance/expression-corpus.json` runs in CI against both (Pest
  `tests/Conformance`, Vitest `tests/conformance`); any disagreement fails the build.
- Bounds: AST depth ≤ 32, node count ≤ 2 000, relation-path depth ≤ 4, aggregate
  rows ≤ 10 000, evaluation timeout 50 ms (server; measured by step counter, not
  wall clock, so both runtimes agree); errors produce defined values (null +
  diagnostic) surfaced as validation messages.

## 16. Permission resolution algorithm

### 16.1 Inputs

- **Permission grants** (`permission_assignments`): `allow`, `deny`, or `hard_deny`
  of catalog permissions to roles, departments (optionally with descendants), and
  users; optional condition.
- **Access rules** (`field_access_rules`): sparse rules setting Hidden/Read-only/
  Editable/Required at form, group, or field target, optionally narrowed by status
  and/or mode, for everyone/department/role/user, each with an `effect`
  (`allow`, `deny`, `hard_deny`).
- **Record rules** (`record_access_rules`): scopes per operation, same three effects.
- Defaults: system default (all fields `editable` in create/edit, `read_only` in
  view/print; nothing permitted without grants), form defaults (form-level rule rows
  with `subject_type=everyone`), group, field.

### 16.2 Precedence model (one algorithm for all three rule kinds)

Specification §4.11 (owner decision, ADR-0009):

1. **Subject tiers**, least to most specific: everyone → **department** → **role** →
   **specific user**. (Department grants with `include_descendants` belong to the
   department tier.)
2. **Within a tier, deny beats allow.**
3. **A more specific tier overrides a less specific tier, including its deny.**
4. **A hard deny overrides every tier** and cannot be overridden.

Evaluation applies tiers in order from least to most specific to a running value
`v` (starting at the default), then applies hard denies last:

```
v ← default
for tier in [everyone, department, role, user]:          # least → most specific
    rules ← applicable rules of this tier (conditions true), excluding hard_deny
    if rules has any allow:  v ← join(allow values in tier)  # override lower tiers
    if rules has any deny:   v ← meet(v, deny values in tier) # deny wins inside the tier
v ← meet(v, all hard_deny values from any tier)              # nothing overrides these
```

`join`/`meet` are "most permissive"/"most restrictive" on the value domain:

| Rule kind | Value domain | default | join (allows in a tier) | meet (deny / hard deny) |
|---|---|---|---|---|
| Permission grant | {denied < granted} | denied | granted | denied |
| Field/group access | hidden < read_only < editable < required | §16.1 defaults | highest allowed level (roles are additive) | cap at the deny's level (a `deny` with `access=read_only` means "at most read-only") |
| Record scope | set of scopes | ∅ | union of allowed scopes | remove the denied scopes (`deny all` removes every scope) |

Worked truth table for a permission (ADR-0009):

| Department | Role | User | Hard deny anywhere | Result | Why |
|---|---|---|---|---|---|
| allow | — | — | no | granted | only tier |
| allow | deny | — | no | denied | role overrides department |
| deny | allow | — | no | granted | role overrides department's deny |
| — | allow + deny (two roles) | — | no | denied | deny beats allow within a tier |
| — | deny | allow | no | granted | user overrides role's deny |
| allow | allow | deny | no | denied | user tier wins |
| — | allow | allow | yes (role) | denied | hard deny overrides every tier |
| — | — | — | no | denied | default |

Safeguards: granting `hard_deny` is a dangerous operation (2FA re-confirmation,
audited); the service refuses any hard deny that would leave no active Super Admin
holding `system.manage_permissions` (lockout guard). The matrix shows hard denies
with a distinct lock marker; *explain access* always lists them first.

### 16.3 Field & group access resolution

Each rule has a *specificity vector*. Order (general → specific): **system default →
form default → group (inherited down the group tree) → field → status override →
mode override → department → role → specific user** (specification §4.11):

```
target level:  form(0) < group(1, + depth) < field(2)
status:        any(0) < specific(1)
mode:          any(0) < specific(1)
subject:       everyone(0) < department(1) < role(2) < user(3)
```

Resolution for (user, field, status, mode):

1. Candidates = rules whose target is the form, any ancestor group of the field, or
   the field; whose status is NULL or equal; whose mode is NULL or equal; whose
   subject matches the user (everyone, their department/ancestors, roles, user).
2. Group the non-hard candidates into **tiers by full vector** (target, status,
   mode, subject) and run the §16.2 algorithm over the tiers in ascending
   lexicographic order: each tier's allows override everything less specific
   (taking the highest allowed level when several roles tie), then its denies cap
   the value within that tier.
3. Apply every `hard_deny` candidate as a final ceiling (e.g. a hard deny at
   `hidden` hides the field regardless of any user-level allow).
4. No rule matched → system default for the mode.
5. Form-level permission gate: without `form.view` the form is invisible; without
   `form.edit` every field is at most read-only in edit mode; without
   `form.create`, create mode is unavailable.
6. Conditions (§15) may further restrict at runtime (hide/read-only/require
   effects) but never grant beyond the resolved access: final = min(resolved,
   condition effect) for visibility/editability; required = resolved.required OR
   condition-required.

The explain-access screen shows every candidate with its vector and effect, the
tier walk, the hard-deny ceiling, and the winner.

### 16.4 Sparse storage & UI

Only deviations are stored. The matrix UI loads a page of (fields × subjects) for
a chosen status/mode filter, computes effective values via the resolver, marks
cells as **inherited** (no rule at that exact coordinate) or **explicit**, and
offers *reset to inherited* (deletes the row). Bulk edit writes rows only where
the new value differs from the inherited value. "Show deviating only" is default.

### 16.5 Caching & invalidation

- **Effective access snapshot** per (user, form version): resolved permissions set,
  record scopes, and a compact field-access table keyed by (status, mode) — computed
  lazily on first use per request and stored in Redis:
  `acc:{org}:{userId}:{formVersionId}:{accessEpoch}`.
- `accessEpoch` is a global counter per organization in Redis, mirrored in
  `settings` (group `access`, key `epoch`, internal and never shown in the settings
  screens) so that a counter lost from the cache resumes from the mirror instead
  of restarting and matching old snapshots; there is no separate table. Bumped
  on any `AccessChanged` event: permission assignments, access
  rules, record rules, roles, user roles, departments (tree changes), delegations,
  form publish (new version id changes key anyway), status changes in metadata.
  Old keys expire by TTL (1 h). Per-request memoization prevents repeated
  Redis reads.
- Target budget: cached lookup < 5 ms p95; cold computation < 50 ms p95 for a form
  with 200 fields and 20 roles (§20).

### 16.6 Record-level scope

For operation *op* (view/edit/delete), the user's record scope is the union of
allowed scopes from `record_access_rules` matching the user, resolved with the
§16.2 algorithm over the record-scope domain (department → role → user tiers; a
tier's allows replace the lower tiers' scope set, its denies remove scopes, hard
denies remove scopes at the end), translated to a `WHERE` clause by the Query
Planner:

| Scope | Predicate |
|---|---|
| own | `owner_user_id = :u` OR `created_by = :u` |
| own_department | `owner_department_id = :dept` |
| department_tree | `owner_department_id IN (SELECT descendant_id FROM department_closure WHERE ancestor_id = :dept)` |
| assigned | `EXISTS (active assignment to u / u's roles / departments, or delegation)` |
| custom | condition AST compiled to a parameterized predicate (only operands that are columns/relation paths/user context are allowed) |
| all | no predicate |

Delegation: a delegate acting on behalf of a delegator gets the union of their own
scope and the delegator's scope for the delegated forms, recorded as on-behalf-of.
External users: always `external_user_id = :x` plus forms exposed to their role.

Every query on record tables (lists, lookups, reports, downloads at every level of
the chain, global search, API) goes through `whereScope()`. Direct id access
uses the same scope and returns 404 when out of scope.

### 16.7 Explain access

`ExplainAccess::field(user, form, field, status, mode)` returns the candidate
rules with their vectors and effects, the tier walk, the hard-deny ceiling, the winner, and links to where each rule
is defined (form/group/field/role/user screens); the same for permissions and
record scopes.

## 17. Relation traversal design

### 17.1 Paths

A **relation path** is a list of hops ending in a field (or `*` for aggregates):
`[{rel: uuid, dir: out|in}, …, {field: uuid}]`. `out` follows a relation from its
source to its target (many-to-one/one-to-one: single; one-to-many/many-to-many:
multiple); `in` follows it backwards (parent → children). Displayed as
`Request › Employee › Department › Manager Name`.

### 17.2 Relation graph

On publish, the `RelationGraph` (adjacency list of all published relations in the
organization, with cardinality and FK columns) is rebuilt and cached
(`relgraph:{org}:{epoch}`). The download builder's tree explorer, the view/filter
builders, reports, and the expression lookup functions all walk this graph; cycles
are allowed in the graph and handled by depth limits in the UI (lazy expansion,
unlimited depth by repeated expansion) and by visited-set detection in planners.

### 17.3 Query planning per path

The planner classifies each path by cardinality:

- **To-one chains** (all hops single-valued): compiled to `LEFT JOIN`s with
  aliased tables (`t0`, `t1`…) — one query, sortable and filterable.
- **To-many hops**: never joined into the main query (row explosion). Instead:
  - *filters* become `EXISTS (SELECT 1 FROM child c WHERE c.fk = t0.id AND …)`
    (any) or `NOT EXISTS` (none/all);
  - *columns* are loaded by **batched eager loading**: for each page/chunk of base
    ids, one query per to-many hop (`WHERE fk IN (…)`) — constant query count per
    chunk regardless of rows (no N+1);
  - *aggregates* (count, sum, avg, min, max, first, last, join) computed in SQL with
    `GROUP BY fk` subqueries joined back as derived tables, or in the batch step for
    `first/last/join` with ordering.
- **Flatten mode** (downloads): the batch loader expands rows in PHP streaming
  writers, repeating parent values.
- **Separate sheet mode**: each to-many target becomes its own chunked query keyed
  by the parent reference column.

Record scope (§16.6) and field access are applied **at every hop**: each joined or
eager-loaded table gets its own `whereScope()` for the requesting user, and
columns the user cannot see at that level are dropped from the plan before SQL is
built (the admin preview shows the effective columns per role).

### 17.4 Large result sets

Lists use offset pagination up to a configurable depth, then keyset (`id >`)
pagination; exports/downloads always use keyset chunking (`chunkById`, 1 000 rows)
and streaming writers (OpenSpout-backed for xlsx/csv; mPDF in chunks for PDF with
row-limits). Indexes exist on every FK column (§11.2).

## 18. Extension model for admin-built surfaces

All admin-built surfaces follow one pattern: **metadata row(s) + JSON config
validated by a type-specific JSON schema + a registry entry on the server + a
component registered on the client.** Adding a capability in a later phase means
adding a registry entry (schema, server handler, client component) — never
changing the core renderer or the storage model.

| Surface | Metadata | Server registry | Client registry | Rendering |
|---|---|---|---|---|
| Pages | `pages`, `page_widgets` | `PageTypeRegistry`, `WidgetRegistry` (data providers enforce permissions) | `PageRenderer`, widget components | `GET /pages/{uuid}` returns resolved layout + widget data endpoints |
| Home screens | `home_screens`, `page_widgets` | same widget registry | same | resolver picks user > role > department > default |
| Dashboards | `dashboards`, `dashboard_widgets` | `WidgetRegistry` + `ReportEngine` | ECharts widget components | |
| Themes | `themes`, `theme_assets` | `ThemeCompiler` (tokens → CSS variables; sanitized custom CSS) | theme runtime applies CSS variables per app/org, light/dark | `GET /theme/{scope}.css` cached by version |
| Automations | `automations`, `automation_triggers`, `automation_steps` | `TriggerRegistry`, `StepHandlerRegistry` | builder step forms from JSON schema | queue jobs |
| External data sources | `external_data_sources` | `DataSourceDriverRegistry` (`rest`, `db_view`) implementing `OptionsProvider`, `LookupProvider`, `RowsProvider` | same option/lookup components as collections | through the egress gateway, cached per policy |
| Notification channels | `notification_channels` | `NotificationChannelRegistry` | channel config forms from JSON schema | |
| Field types | `fields.type` | `FieldTypeRegistry` | field component registry (incl. developer client fields) | |
| Menus | `menu_items` | target resolvers per `type` | sidebar renderer | |

Widget data providers, data source drivers, and step handlers receive the acting
user and always go through the Access Resolver and Record Pipeline.

## 19. Domain engine designs

### 19.1 Optimistic concurrency & conflict screen

- Every record read returns `row_version`; every write sends `expected_row_version`.
- The update is `UPDATE … SET …, row_version = row_version + 1 WHERE id = ? AND
  row_version = ?`; 0 affected rows → **409 Conflict** with
  `{current_row_version, changed_fields: [{field, your_value, their_value,
  base_value}], changed_by, changed_at}` (from the audit entries since the loaded
  version; values filtered by the user's field access).
- Conflict screen: per field, *keep mine / take theirs*; actions **Reload**,
  **Overwrite field by field** (re-submits with the new `row_version` and only the
  chosen fields), **Cancel**. Silent last-write-wins is impossible: there is no
  write path without `expected_row_version` (API included; imports/automations
  read-modify-write inside a row-locked transaction and retry on conflict).
- Child rows (repeaters) are written as part of the parent's version: any child
  change bumps the parent `row_version`.

### 19.2 Workflow engine

- `WorkflowEngine::availableTransitions(record, user)`: transitions from the
  current status (or any) whose `transition.{uuid}.perform` permission resolves
  allow and whose condition holds (server-evaluated).
- `perform(record, transition, input)`: through the Record Pipeline with
  `operation=transition`: required fields check, comment/attachment levels,
  justification, approvals (if `approval_mode != none`, creates an
  `approval_request` and assignments instead of moving the status; the move happens
  when the rule is satisfied), assignment rules, `status_history`, SLA timers
  (complete old, start new), events.
- SLA: `sla_timers.due_at` computed with `WorkingTimeCalculator` when
  `use_working_time`; the scheduler's SLA tick (every minute, `skipLocked` batch on
  `next_check_at`) issues warnings and escalations (`notify`, `reassign`,
  `transition` via the engine with `source=sla_escalation`).
- Visual designer: Vue Flow graph ⇄ statuses/transitions; positions stored in
  `diagram_position`/`diagram_edge`; validation: exactly one initial status, final
  statuses have no outgoing transitions, unreachable statuses warned.

### 19.3 Justification gate

At pipeline step 9 the `JustificationGate`:

1. Computes the change set (fields changed, operation, transition/action, status
   *before* the change, actor, bulk/import context).
2. Selects rules for the form whose scope matches (form-wide; group containing a
   changed field; changed field; status reached = current status; action;
   transition; delete/restore; import; bulk; reassign; merge) and whose subject
   matches the actor (everyone/role/department/user, same tier logic as §16.2:
   most specific subject wins per scope).
3. Evaluates each rule's condition: true → `level_when_condition`, else `level`.
4. The effective requirement is the **strictest** across matched rules
   (mandatory > optional > not_required), with merged constraints (max of mins, min
   of maxes, union of reason code requirements, attachments).
5. If `mandatory` and no justification payload → 422 `justification_required` with
   the prompt definition (title/help in user locale, change summary, reason codes,
   constraints). The client shows the prompt **only after validation passed** (the
   pipeline runs steps 1–8 first, and the same request returns either validation
   errors or the justification requirement, never both).
6. On save, the `justifications` row (immutable, hashed) is written in the same
   transaction and its id stored on every audit entry of that change; bulk/imports
   write one justification referenced by all affected entries (`affected_count`).
7. Justification text is exposed in tables/downloads/reports as virtual columns
   (`justification.last_reason`, `.last_code`, `.last_at`, `.last_by`) only to
   holders of `system.view_justifications`.

### 19.4 Assignment, queues, delegation, approvals

- `AssignmentService::onTransition` applies the first matching `assignment_rule`:
  `round_robin` uses a row-locked cursor; `least_loaded` counts active assignments
  per candidate (indexed query); `creator_manager` uses `users.manager_id`.
- My Work: union of active assignments to the user, their roles and departments
  (and to delegators for active delegations), across forms, each filtered by record
  scope; columns from `queue_forms`/views; SLA state from `sla_timers`.
- Claim: insert `queue_claims` with `active_key` (unique) — a concurrent second claim
  fails on the unique index → "already claimed by X". Release clears `active_key`.
  Writes by non-claimers on claimed records are rejected (423 Locked) unless admin.
- Delegation: `DelegationResolver::principalsFor(user, form)` returns the user plus
  active delegators; access is the union; every write records
  `on_behalf_of_user_id`; notifications to the delegator are copied to the delegate
  with "on behalf of" wording.
- Approvals: decisions recorded per approver row; `ApprovalEvaluator` checks the
  rule after each decision (`all`: all approved; `any_n`: approved ≥ n;
  `quorum`: Σweight(approved) ≥ quorum); rejection: `immediate` → transition to
  `rejection_status_id`; `wait_all` → evaluate once all decided. Reminders and
  escalation reuse SLA rules on the approval's `due_at`.

### 19.5 Actions, import, export

- `ActionRunner::run(action, records, user, input)`: permission
  `action.{uuid}.run`, availability condition per record, confirmation, max records,
  justification; synchronous if small, else `bulk_operations`/job with progress and
  completion notification. Steps run in order with per-step conditions; record
  writes go through the pipeline (one transaction per record; failures recorded per
  record).
- Import: upload (file rules + ClamAV) → parse headers (XXE off, formulas as text)
  → mapping (saved mappings) → **validation preview** (first N rows through pipeline
  steps 4–7 without writing) → dry-run (full validation, report) or run. Batches of
  500 rows, one transaction per batch; `last_committed_batch` + per-row idempotency
  keys (`import:{job}:{row}`) make retries idempotent. Error report xlsx names row
  and reason. Limits: max file size, rows, and time from settings.
- Export: visible columns ∩ field access, current filters, record scope; CSV/xlsx
  writers escape `= + - @ \t \r` leading cells; PDF via mPDF with Arabic fonts
  (shaping and bidi handled by mPDF `autoArabic`/`autoScriptToLang`).

### 19.6 Submission journal & idempotent retries

- The client generates an `idempotency_key` (UUIDv4) per submit intent and sends it
  as `Idempotency-Key`; API clients may too (else server derives one).
- Journal insert happens in its **own committed transaction** before processing.
  If the same key arrives again: `processed` → return the stored result (same
  record id); `processing` → 409 `in_progress`; `failed` → retry allowed.
- Creates store the journal's key in the record (`f_*.uuid` is derived from the
  journal row's uuid), so a retry after a crash *between* commit and journal update
  finds the existing record by uuid instead of inserting a duplicate.
- Operations Center: retry (single/bulk), edit payload then retry (original kept,
  `edited_payload` used, audited), discard with mandatory reason.

### 19.7 Operations Center

| Area | Source | Actions (all audited, `system.manage_operations`) |
|---|---|---|
| Email queue | `email_queue` (+ computed Stuck) | resend single/bulk, edit recipients & resend (new row `resent_from_id`), cancel, view rendered HTML (sanitized, in sandboxed iframe) |
| Failed submissions | `submission_journal` (failed) | retry/resync, edit payload & retry, discard with reason |
| Failed jobs | `failed_jobs` + tracking rows (imports, exports, downloads, webhooks, scheduled tasks, hooks, documents, automations, syncs) | inspect (masked payload, exception), retry, discard |
| Health | probes: DB (both engines' connectivity via configured connection), Redis, SMTP (EHLO/auth no-send), ClamAV (PING/version), queue sizes (Horizon metrics), stuck items, last scheduler run (heartbeat key), storage usage (disk + quotas) | refresh |
| Alerts | `operations_alert_rules` evaluated every minute over windows | email to recipient roles with cooldown |

### 19.8 Custom downloads engine

1. Resolve the profile (permission `download.{uuid}.use`); collect parameters;
   merge fixed filters + (optionally) the user's current table filters + selected
   ids.
2. **Effective columns** = profile columns whose every hop and final field are
   visible to the downloading user (field access at each level; sensitive fields
   masked unless `field.{uuid}.view_sensitive`). The builder offers "preview
   effective columns as role X".
3. Plan with §17 (joins for to-one, batched eager loads for to-many; flatten /
   aggregate / separate sheet), record scope at every level.
4. Count; enforce `max_rows`; ≤ sync threshold (setting, default 5 000 rows) →
   streamed response; else `download_jobs` row + `heavy` queue job with progress,
   in-app/email notification, file on private disk, **signed expiring URL** (route
   `/files/download/{uuid}?signature=…&expires=…` + policy re-check at click).
5. Writers: xlsx (multiple sheets, styled/frozen header, logo/title row, RTL sheet
   view for Arabic), csv (UTF-8 BOM), pdf (mPDF, orientation, header/footer, page
   numbers). File name from pattern (sanitized).
6. Audit: who, profile, filters, parameters, row count, duration. Failures →
   Operations Center with retry. Live preview: first 20 rows via the same plan with
   `limit 20`.
7. Scheduled downloads: `download_schedules` → `scheduled_tasks`; generated **per
   recipient** under each recipient's own permissions, emailed as attachment (size
   limit) or as an expiring link.

### 19.9 Automations

- Triggers subscribe to outbox events (record/status/field/condition), the scheduler
  (`schedule`, `date_reached` — a daily/hourly scan of the date field with offset
  using an index on the field), inbound endpoints, watched folders (polling a disk
  path with processed-file markers), or manual run.
- Loop protection: every run carries `chain_depth` and the set of automation ids in
  the causal chain (propagated through the correlation context); a run that would
  exceed `max_chain_depth` or re-enter itself is recorded `skipped_loop`.
- Concurrency limit via Redis semaphores per automation; retry policy per step;
  `max_records_per_run` with `confirm_above` → `awaiting_confirmation` (admin
  confirms in UI).
- Test run: executes against a sample record inside a transaction that is rolled
  back, with outbound steps (email, webhook) simulated and shown.
- `wait_delay`/`wait_condition`: run state persisted with `resume_at`; the scheduler
  resumes it.

### 19.10 Data quality

- Duplicate rules evaluated at pipeline step 10: exact (indexed equality),
  normalized (compare normalized forms stored in `search_text`-like shadow keys),
  fuzzy (candidate narrowing by normalized prefix/blocking key, then similarity —
  Jaro-Winkler/Levenshtein in PHP with threshold). Sweep runs as `bulk_operations`.
- Merge: choose survivor, per-field winner, repoint every relation FK and pivot to
  the survivor (all relations whose target is the form), soft-delete losers, write
  `merge_history` + audit + justification, one transaction per merge.
- Bulk update/reassign/status: preview count, dry run, max-count guard, justification.
- Repair: orphan scan (FKs enforced in the application; pivot rows; files), records
  failing current validation (runs pipeline validation in read-only mode), guided
  fix screen editing records through the pipeline.
- Recycle bin: lists soft-deleted records across permitted forms; restore through
  the pipeline (restore justification if configured); purge job after retention.

### 19.11 Theming & admin-authored content

- Tokens → CSS custom properties (`--color-primary-500`, `--radius`, `--density`,
  `--font-arabic`, `--font-latin`) compiled per theme version; PrimeVue 4 styled
  mode uses a preset bound to these variables; RTL via `dir` + logical CSS
  properties.
- Contrast checker computes WCAG 2.1 ratios for text/background token pairs and
  semantic colors in light and dark; warnings must be acknowledged before save.
- Custom CSS: parsed (sabberworm/php-css-parser), whitelist of properties, no
  `@import`/`url(http…)`/`expression`/`behavior`, selectors prefixed with
  `.app-content` and forbidden to target `.security-banner`, `.impersonation-banner`,
  `.maintenance-banner`, dialogs of 2FA/justification; served as a separate
  stylesheet after the theme.
- Rich content (pages, help, announcements, email templates, comments): HTML
  Purifier profiles (no script, no event handlers, no `javascript:`/`data:` URLs
  except images, iframes only for allowlisted hosts, forms disallowed).

### 19.12 External access

- `/x/*` routes: external guard, CSRF, CAPTCHA (self-hosted proof-of-work/ALTCHA style
  to avoid third-party egress; ADR-0013), `submission_throttles` for per-IP/email/global
  limits, time window, caps, optional password, email/SMS verification (OTP).
- External forms render a definition containing only `exposed_fields`; the pipeline
  runs with an `ExternalPrincipal` whose field access is the exposed allow-list
  intersected with the form's rules; initial status set; creator = configured
  service principal + `external_user_id`/submitter email recorded.
- External users: registration → verification → admin approval → login; role with
  `audience=external`; record scope fixed to own records; only forms granted to
  their role.
- Tokenized links: `access_tokens` (hash-only storage), validated per request (not
  expired/revoked/used up, bound record & action), scoped renderer showing only the
  section/action; every use audited.
- Signature requests: signer opens token link, reviews rendered document, draws
  signature (signature_pad), server stamps signature + metadata into the generated
  PDF, stores `signed_file_id` + hash, audit entry.

### 19.13 Developer extensions

- Stored as modules on disk under `/extensions/{key}/v{n}/` (Git-friendly), with a
  manifest; the Monaco editor in the UI writes there through the Extensions module
  (Manage Code only). Never `eval()`.
- Server hooks: PHP classes implementing `HookContract` loaded via a dedicated
  Composer PSR-4 namespace `Extensions\` after checksum verification; executed by
  `HookRunner` inside the pipeline transaction with a timeout (pcntl alarm in
  workers / max-execution guard in FPM), exceptions isolated → Error Monitoring +
  Operations Center, and the save fails cleanly (hook failure policy per extension:
  fail-closed default).
- Client extensions: TypeScript modules compiled in a sandboxed build step (esbuild)
  into ES modules served from `/ext/{key}/{version}.js` with SRI hashes; loaded only
  for forms that use them; custom fields register in the palette.
- Lifecycle: draft → test (sandbox: run the extension's tests against an ephemeral
  database transaction/rollback) → submit → approve (a different user with
  `system.approve_code`) → deploy (sets `deployed_version_id`; previous versions kept
  → rollback = redeploy an older version).

### 19.14 Notifications, email, documents

- `Notifier` resolves recipients per To/CC/BCC (users, roles, departments, field
  users, creator, linked-record users, static addresses), applies user channel
  preferences, renders the template **per recipient locale**, and creates
  `email_queue` / `in_app_notifications` / `notification_deliveries` rows; sending
  happens in jobs with retries; all tracked in the Operations Center.
- Placeholders are expression ASTs (field values, linked fields via paths, system
  values, record links); conditional blocks and repeater tables are template
  constructs evaluated with the expression engine; values are escaped; field access
  of each recipient is applied (a recipient never receives a field they cannot see).
- Preview with a real record (as recipient X) and test send (to the admin).
- Document templates: DOCX via PhpWord TemplateProcessor (placeholders, cloned rows
  for repeaters/linked rows), HTML via sanitized template → mPDF for PDF and
  PhpWord HTML import for DOCX. Generated files stored in `files`, attachable.

### 19.15 Audit & error monitoring

- `AuditWriter` appends inside the business transaction: picks
  `chain_id = record_id mod 16` (or object hash), locks `audit_chain_heads` row,
  computes `hash = SHA256(prev_hash ‖ canonical_json(entry))`, inserts, updates
  head. No update/delete code path exists; Eloquent model throws on update/delete;
  the deployment guide grants the runtime DB user `INSERT, SELECT` only on
  `audit_logs`, `justifications`, `merge_history` (migrations use a separate user).
- `ChainVerifier` (scheduled daily + on demand) walks each chain from the last
  verified point and reports breaks to Error Monitoring and alert roles.
- `ErrorReporter` (Laravel exception handler): fingerprint → `error_groups`
  upsert, `error_logs` insert, masked; **always** also writes to the secondary sink
  (daily rotated JSON file on a separate volume, optionally syslog/OTLP) first, so DB
  outages are recorded; user sees a friendly page with `reference_code`.

### 19.16 Retention & personal data

- `RetentionEngine` runs each policy: selects candidates by `date_basis` older than
  `retention_days`, excludes `legal_hold`, applies end action: archive (JSONL
  to cold disk + `archived_records`), export-then-delete, delete; all in chunks,
  audited per run. Audit logs are only archived by partition (detach/export
  partition with its chain segment and verification proof) — never deleted
  in-place.
- Partitioning: monthly partitions for `audit_logs` and `error_logs`, created ahead
  by a scheduled task (12 months), archived partitions exported then dropped
  (error logs) or exported and switched out to an archive table (audit logs).
  Restore procedure documented in the Phase 6 guide.
- Personal data: `PersonalDataLocator` searches fields flagged `is_personal_data`
  (blind indexes for encrypted ones), users, audit `changes`, email logs,
  journal payloads; produces findings; export bundle; anonymization replaces values
  with irreversible tokens, keeps audit sequence by writing a new
  `personal_data.anonymized` entry (old entries' identifying values are covered by
  the hash chain; the chain is preserved by storing personal values in audit
  `changes` **encrypted with a per-record subject key** (`subject_keys`, §10.25)
  that anonymization destroys — crypto-shredding: the ciphertext and therefore the
  chain hashes are untouched, but the values become unrecoverable, ADR-0014).
- Storage quotas measured nightly; warnings at threshold; uploads blocked at hard limit.

### 19.17 Applications & tenancy

- `TenantContext` resolved per request from the authenticated user's organization
  (platform admins can switch organization explicitly; audited). A global Eloquent
  scope adds `organization_id = :org` to every tenant-owned model and to the Query
  Planner; cross-organization reporting requires `system.cross_org_reporting` and
  is explicit.
- Applications: enablement via `app.{uuid}.access`; clone = blueprint of kind
  `application` instantiated with new keys; archive/retire hides menus and blocks
  writes (records remain readable to permitted admins); per-form `data_sharing`
  controls whether lookups from other applications may target the form.
- Maintenance mode (system-wide in `settings`, or per application in
  `applications.maintenance_mode`): the `MaintenanceMode` middleware returns 503 with
  the translatable message for everyone except holders of
  `system.enable_maintenance_mode` (and Super Admin), who keep full access and see a
  banner. Queued jobs for an application in maintenance are delayed, not dropped.
- Tenancy mode chosen in the setup wizard is stored in `settings.tenancy.mode` and is
  immutable after setup (switching modes is a migration project, not a toggle).

### 19.18 First run & setup wizard

- Seeders (idempotent): platform organization, locales `ar`/`en`, four core roles,
  permission catalog, default settings (security baseline, formats, file limits,
  password policy, operations thresholds). Nothing else.
- `/setup` wizard (SPA route + API) available only while
  `settings.setup.completed_at` is NULL: system name/logo/favicon; default and
  enabled languages; timezone, date/number formats; calendar system; tenancy mode;
  SMTP with test email (through the mailer, not the egress gateway — SMTP is not
  HTTP; host validated against blocked ranges too); Super Admin account with
  password policy and **mandatory TOTP enrollment** before completion. Completion
  sets `setup.completed_at` in the same transaction as account creation; the wizard
  then returns 404 permanently and the admin lands on the Admin Console.
- Admin Console: navigation tree registered by modules with required permissions;
  areas appear only when their module is installed (phase delivered) **and** the
  user holds the permission.

### 19.19 Reference data, calendars, numbering, currencies, units

- `WorkingTimeCalculator::add(start, minutes, calendar)` and `::between(a, b,
  calendar)` honoring working days, hours spans, holidays (Gregorian and Hijri
  recurrence via the Umm al-Qura table shipped with the Hijri adapter).
- `NumberGenerator::next(sequence)`: transaction + `lockForUpdate` on the sequence
  row; resets when `period_key` changes; pattern rendering (prefix, date parts,
  padded sequence); manual adjustment requires justification and is audited.
- Currency fields store amount + currency; conversions use the rate effective at the
  record's transaction date (historical rates preserved); scheduled rate updates via
  an external data source (egress gateway).
- Units: numeric fields may declare a unit and allowed conversions; conversion uses
  `value × factor + offset` via base unit.

### 19.20 Reports & dashboards

`ReportEngine` compiles a report definition into a §17 plan with grouping and
aggregates in SQL (`GROUP BY` on to-one paths; to-many aggregates via subqueries),
pivot computed server-side for bounded result sizes, chart series shaped for
ECharts. Record scope and field access are applied; result caching by
(report, user access epoch, filters) with TTL. Dashboards: drag-and-drop grid
(per-breakpoint layouts), per-role visibility through permissions and widget
conditions; export to Excel (data) and PDF (server-rendered chart images via ECharts
SSR SVG → mPDF).

### 19.21 Integrations

External data sources behave as `OptionsProvider`/`LookupProvider`/`RowsProvider`
(same interfaces as collections) with caching per policy; failures surface as
validation messages and Operations Center entries. Sync jobs: chunked import/export
with mapping, matching keys, conflict rule, dry run, and run log; writes go through
the pipeline as the configured service principal. Inbound endpoints: token or
signature verification, payload mapping, pipeline write with full validation,
workflow, and permissions of the bound principal. Channels: provider drivers (SMS,
messaging) registered in `NotificationChannelRegistry`, HTTP through the egress
gateway.

### 19.22 Self-service, impersonation, access policies, feature flags

- Profile preferences constrained by `settings.self_service` limits.
- Personal API tokens only when `settings.api.personal_tokens_enabled`; abilities ⊆
  user's permissions; listed/revocable.
- Access policies enforced at login and per request by `EnforceAccessPolicy`
  (IP allowlist, time window, concurrent sessions, idle/absolute lifetime,
  trusted device, 2FA); strictest across the user's roles; admin roles get the
  stricter admin policy.
- Feature flags evaluated by `Features::enabled(key, user)` (Laravel Pennant-style
  resolver backed by `feature_flags`), used by menus, forms (publish to pilot
  group), actions, pages.

### 19.23 Help, tours, adoption

Help content resolved by target and rendered as tooltip/side panel/page; tours
(steps with selectors, versioned, progress per user, reset by admin); knowledge
and changelog pages are `pages` of type `knowledge`/`changelog` with "publish to
users" creating an announcement. Usage insight: the renderer emits batched
beacon events (view, start, submit, abandon, empty fields on submit) to
`/api/usage` → aggregated daily in `usage_metrics`; admin report per form.

### 19.24 Localization runtime

- `Translator` loads translations for (object type, ids, locale) in bulk with
  fallback chain (`locale → fallback_locale → default locale → key`), cached per
  object version.
- The SPA receives definitions with labels already resolved for the user's locale
  and the fallback flag per label (so admins can spot untranslated items).
- Direction, calendar, digits, and number formats from `locales` + user preferences;
  `dir="rtl"` on `<html>`; PrimeVue RTL; Tailwind logical utilities (`ms-`, `me-`,
  `ps-`, `pe-`); ECharts mirrored axes for RTL; xlsx RTL sheets; mPDF RTL.
- Hijri: `@internationalized/date`-based adapter (Islamic Umm al-Qura calendar) on the
  client and an equivalent PHP adapter (shared conformance corpus for date
  conversions in `expression-corpus.json`).

## 20. Performance budgets & capacity assumptions

### 20.1 Reference environment (load tests in Phase 6)

- App: 2 × (4 vCPU, 8 GB) PHP-FPM containers with OPcache + JIT off; 1 × worker
  (4 vCPU, 8 GB, Horizon); Redis 7 (2 vCPU, 4 GB).
- DB: MySQL 8 or SQL Server 2019 Standard on 8 vCPU, 32 GB RAM, SSD.
- Data: 1 000 000 records in the measured form (40 stored fields, 2 repeaters with
  avg 5 rows, 3 lookups), 200 forms total, 5 000 users, 50 roles, 300 departments
  (depth 6), 20 M audit rows.

### 20.2 Budgets (server-side, measured at the API, p95 unless noted)

| Operation | Budget |
|---|---|
| Form definition load (cached CompiledDefinition + resolved access) | ≤ 150 ms |
| Form render in browser (definition received → interactive, 100 fields, mid-range laptop) | ≤ 1.5 s |
| Record view load (record + 3 related panels) | ≤ 400 ms |
| Record list: 25 rows, 10 columns incl. 2 to-one paths, 3 filters, sort, record scope, 1 M rows | ≤ 500 ms |
| Global search across 20 forms | ≤ 800 ms |
| Record save (create/update, 40 fields, 1 repeater, audit, journal) | ≤ 400 ms |
| Permission resolution: cached / cold | ≤ 5 ms / ≤ 50 ms |
| Expression evaluation (formula set of a 100-field form) | ≤ 20 ms server, ≤ 10 ms client |
| Import 100 000 rows (validation + insert) | ≤ 10 min |
| Custom download, 3-level relation, 100 000 base rows, xlsx | ≤ 5 min (background) |
| Concurrency | 500 concurrent active users (≈ 50 req/s sustained, 150 req/s peak) with error rate < 0.1 % and the above p95s |
| Publish of an additive change on a 1 M-row table (MySQL INSTANT / SQL Server metadata-only add) | ≤ 30 s |

Budgets are asserted by k6 load scripts and Playwright performance marks in Phase
6; regressions beyond 20 % fail the performance job.

### 20.3 Capacity assumptions & levers

Metadata caching (§3.1, §16.5) means a typical request does 0 metadata queries;
records use indexed filters only (filterable fields get indexes); list counts use
estimated counts above 100 000 rows (exact count on demand); heavy work is queued;
read replicas can be configured for reports/downloads (`read` connection) without
code changes.

## 21. API outline

Conventions: base `/api/v1`, JSON, Sanctum cookie (SPA) or bearer token,
`Accept-Language` for locale, `X-Correlation-ID` echoed, `Idempotency-Key` on
writes, `If-Match: <row_version>` (or body `row_version`) on record updates.
Errors: 401/403/404 (out-of-scope = 404), 409 conflict, 422 validation (+
`justification_required`), 423 locked (claim / schema inconsistent / publish lock),
429 rate limit; body `{message, code, reference, errors?}`. Lists support
`page`, `per_page` (≤ 100), `sort`, `filter[...]`, `search`. Permission keys:
`system.*` from §21.1, `form.{uuid}.{ability}`, etc. (§10.4 `permissions.key`).

### 21.1 System permission catalog (seeded)

| Key | Spec name |
|---|---|
| `system.view_errors` | View Errors |
| `system.manage_operations` | Manage Operations |
| `system.manage_code` | Manage Code |
| `system.approve_code` | approval permission for extensions (§4.19) |
| `system.manage_settings` | Manage Settings |
| `system.manage_forms` | Manage Forms (incl. collections, workflows, views, actions) |
| `system.manage_permissions` | Manage Permissions (roles, matrices) |
| `system.manage_users` | Manage Users (and departments) |
| `system.view_audit_log` | View Audit Log |
| `system.manage_download_profiles` | Manage Download Profiles |
| `system.create_personal_download_profiles` | Create Personal Download Profiles |
| `system.manage_justification_rules` | Manage Justification Rules |
| `system.view_justifications` | View Justifications |
| `system.assign_records` / `system.reassign_records` | Assign / Reassign Records |
| `system.manage_delegation` / `system.delegate_own_work` | Manage Delegation / Delegate Own Work |
| `system.manage_retention` | Manage Retention |
| `system.manage_personal_data_requests` | Manage Personal Data Requests |
| `system.apply_legal_hold` | Apply Legal Hold |
| `system.manage_applications` | Manage Applications |
| `system.manage_pages_menus` | Manage Pages & Menus |
| `system.manage_branding` | Manage Branding |
| `system.manage_blueprints` | Manage Blueprints |
| `system.manage_automations` / `system.run_automations` | Manage Automations / Run Automations Manually |
| `system.manage_integrations` | Manage Integrations |
| `system.manage_external_access` | Manage External Access |
| `system.manage_reference_data` | Manage Reference Data |
| `system.manage_numbering` / `system.manage_calendars` / `system.manage_currencies` | Manage Numbering / Calendars / Currencies |
| `system.merge_records` / `system.bulk_update` | Merge Records / Bulk Update |
| `system.access_recycle_bin` / `system.repair_data` | Access Recycle Bin / Repair Data |
| `system.impersonate_users` | Impersonate Users |
| `system.manage_access_policies` | Manage Access Policies |
| `system.manage_feature_flags` | Manage Feature Flags |
| `system.manage_help_content` | Manage Help Content |
| `system.publish_announcements` | Publish Announcements |
| `system.enable_maintenance_mode` | Enable Maintenance Mode |
| `system.manage_notifications` | (supporting) email templates & notification rules |
| `system.manage_document_templates` | (supporting) document templates |
| `system.manage_reports` | (supporting) reports & dashboards |
| `system.manage_translations` | (supporting) translations |
| `system.manage_packages` | (supporting) configuration packages & environment compare |
| `system.cross_org_reporting` | (supporting) multi-organization reporting |

Seeded role grants (ordinary `permission_assignments` rows — visible in the matrix,
no code-level bypass exists): **Super Admin** — every system permission, and every
auto-registered object permission receives a Super Admin allow when it is created;
**Admin** — every system permission except `manage_code`, `approve_code`,
`impersonate_users`, `cross_org_reporting`; **Developer** — `manage_code`,
`view_errors`; **User** — `delegate_own_work`. Business access for every other
object is configured by administrators.

### 21.2 Endpoint groups

| Group | Methods & paths | Payload (request → response) | Permission |
|---|---|---|---|
| **Setup** | `GET /setup/status`; `POST /setup/token`; `POST /setup/mail-test`; `POST /setup/two-factor`; `POST /setup/branding/{logo,favicon}` (multipart); `POST /setup/complete` | every call except status carries `X-Setup-Token` (ADR-0022); system name per locale, logo/favicon UUIDs, default and enabled locales, formats, calendar, tenancy mode, SMTP (optional), admin account, TOTP code → `{recovery_codes}` and a signed-in session | only while setup incomplete (404 after) |
| **Auth** (Fortify) | `POST /login`, `/logout`, `/two-factor-challenge`, `/forgot-password`, `/reset-password`, `/user/confirm-password`; `GET /sanctum/csrf-cookie`; `GET /auth/sso/{provider}/redirect`, `/callback`; `POST /auth/ldap` | credentials → session; TOTP; email | public (rate limited) |
| **Me** | `GET /me` (profile, roles, effective permissions, 2FA state, preferences); `PATCH /me`; `PATCH /me/preferences`; `GET /me/sessions`; `DELETE /me/sessions/{handle}`; `DELETE /me/sessions` (all others); 2FA enrollment through Fortify `/auth/user/two-factor-*`; `GET/POST/DELETE /me/tokens` (Phase 5) | profile, preferences, sessions by opaque handle | authenticated |
| **Admin console** | `GET /admin/console`; `GET /admin/health` | → areas visible to the user (built areas only); health checks | authenticated; health needs `view_errors` or `manage_operations` |
| **Organizations** | `GET/POST/PATCH /organizations`, `POST /organizations/{id}/switch` | multi-org only | Super Admin (platform) |
| **Settings** | `GET /settings/{group}`; `PATCH /settings/{group}`; `POST /settings/mail/test`; `POST /settings/branding/{logo,favicon}`; `GET/POST/PATCH/DELETE /egress-allowlist` | group key/values (secrets write-only, `{is_set}` on read) | `manage_settings` (`manage_branding` for branding) |
| **Locales & translations** | `GET/POST/PATCH /locales`; `GET /translations?object_type=&locale=&untranslated=1`; `PUT /translations` (bulk); `GET /translations/export`, `POST /translations/import` | rows `{object_type, object_uuid, field, locale, value}` | `manage_translations` |
| **Users** | `GET/POST /users`; `GET/PATCH/DELETE /users/{uuid}`; `POST /users/{uuid}/status` (active/suspended/disabled); `POST /users/{uuid}/unlock|reset-2fa|password-link`; `GET/DELETE /users/{uuid}/sessions`; `GET /role-options`; `POST /users/import` (Phase 5) | user fields, roles, department, attributes; escalation safeguards (ADR-0023) | `manage_users` |
| **Departments** | `GET /departments/tree`; `POST /departments`; `PATCH /departments/{uuid}` (moving = changing `parent`); `DELETE /departments/{uuid}` (archive) | name i18n, code, parent, manager, sort, active (calendar from Phase 2) | `manage_users` |
| **Roles & permissions** | `GET/POST/PATCH/DELETE /roles`; `POST /roles/{uuid}/copy-permissions`; `GET /permissions?scope=`; `PUT /permission-assignments` (bulk); `GET /access/explain?user=&form=&field=&status=&mode=`; `GET /access/view-as/{user}`; `GET /access/export`, `POST /access/import` | assignments `{permission_key, subject, effect, include_descendants, condition}` | `manage_permissions` |
| **Form access matrix** | `GET /forms/{uuid}/access-matrix?status=&mode=&subject=&group=&deviating_only=&page=` (→ also the form's statuses); `PUT /forms/{uuid}/access-rules` (bulk upsert/reset, `status` per change); `GET /forms/{uuid}/access-explain?status=`; `GET/PUT /forms/{uuid}/record-access-rules` (document + `base_hash`, ADR-0033); `GET /forms/{uuid}/record-access-explain?user=`; `GET /permissions?scope_type=form|transition|view&form=` (the form's own abilities, transitions and views) | cells `{target, subject, status, mode, access, effect}`; record rules `{subject, operation, scope, effect, priority, condition}` (ADR-0032) | `manage_permissions` |
| **Audit** | `GET /audit?filters…`; `GET /audit/{id}`; `GET /records/{form}/{uuid}/audit`; `POST /audit/export`; `POST /audit/verify-chain` | | `view_audit_log` (record log also needs `form.view_log`) |
| **Errors** | `GET /errors/groups`; `GET /errors/groups/{id}`; `PATCH /errors/groups/{id}` (status, assignee, notes); `GET /errors/logs?…`; `GET /errors/reference/{code}` | | `view_errors` |
| **Applications** | `GET/POST/PATCH /applications`; `POST /applications/{uuid}/clone|archive|retire|maintenance` | | `manage_applications` (+ `enable_maintenance_mode`) |
| **Menus** | `GET /applications/{uuid}/menu`; `PUT /applications/{uuid}/menu` (tree); `GET /navigation` (user sidebar, resolved) | tree nodes with type/target/icon/badge/visibility | `manage_pages_menus` / authenticated |
| **Forms & collections (builder)** | `GET/POST /forms`; `GET/PATCH/DELETE /forms/{uuid}`; `GET /forms/{uuid}/draft`; `PUT /forms/{uuid}/draft` (autosave, `draft_updated_at` precondition); `POST /forms/{uuid}/draft/validate`; `GET /forms/{uuid}/preview?as_user=&as_role=&mode=&locale=`; `POST /forms/{uuid}/impact`; `POST /forms/{uuid}/publish`; `GET /forms/{uuid}/versions`; `GET /forms/{uuid}/versions/{n}/diff/{m}`; `POST /forms/{uuid}/versions/{n}/rollback`; `POST /forms/{uuid}/unpublish|archive|republish`; `POST /forms/{uuid}/duplicate`; `GET /field-types`; `GET /form-options` (picker list, also for `manage_pages_menus`, `manage_numbering`, `manage_applications`); `GET/POST/PATCH/DELETE /field-templates`; `POST /expressions/parse|check|evaluate`; `GET/POST/PATCH /applications`, `GET/PUT /applications/{uuid}/menu`, `GET /navigation` | draft document (§14.1 minus `schema`), impact report, publish options (menu placement, application, allowed roles) → migration plan id | `manage_forms` |
| **Field library** | `GET/POST/PATCH/DELETE /field-templates` | §14 subtree | `manage_forms` |
| **Schema** | `GET /schema/tables`; `GET /schema/tables/{name}`; `GET /schema/erd?forms=`; `GET /migration-plans/{uuid}`; `POST /migration-plans/{uuid}/retry|reverse|reconcile-step|restore-snapshot`; `POST /schema/reconcile`; `GET /schema/reconciliation-reports` | | `manage_forms` |
| **Expressions** | `POST /expressions/parse`; `POST /expressions/check`; `POST /expressions/evaluate` (sandboxed preview) | text/AST + context form uuid → AST/diagnostics/value | `manage_forms` |
| **Records (runtime)** | `GET /r/{form}` (list: `view`, view filters `vf[{filter}]`, filters, search, sort, page → `meta.view`, `meta.views`, `meta.totals`, `row.linked`); `GET /r/{form}/definition?mode=&record=` (status-aware field access, `print_layouts`); `POST /r/{form}/bulk-delete|bulk-restore` (`items[{uuid, row_version}]`, one justification); `POST /r/{form}`; `GET /r/{form}/{uuid}`; `PATCH /r/{form}/{uuid}`; `DELETE /r/{form}/{uuid}`; `POST /r/{form}/{uuid}/restore`; `GET /r/{form}/{uuid}/definition?mode=`; `GET /r/{form}/options/{field}?q=&depends=`; `POST /r/{form}/validate-field` (async rules); `GET /r/{form}/{uuid}/history`; `GET/POST /r/{form}/{uuid}/comments`; `GET/POST /r/{form}/{uuid}/subforms/{group}` (inline sub-form records, parent key set at insert); `GET /r/{form}/export?format=xlsx|csv`, `GET /r/{form}/import/template`, `POST /r/{form}/import` (`commit`, `skip_invalid`); `POST /files`, `GET /files/{uuid}/url` (signed, five minutes); `POST /r/{form}/{uuid}/duplicate`; `GET /r/{form}/{uuid}/print?layout=` | values keyed by field key, `row_version`, `justification?` → record + `row_version` | `form.{uuid}.view/create/edit/delete/restore/print` + record scope + field access |
| **Comments & attachments** | `GET/POST /r/{form}/{uuid}/comments`; `DELETE …/comments/{id}`; `GET /r/{form}/{uuid}/attachments` (P3, the View Mode attachments panel; record attachments are `files` rows with the record's `form_id`/`record_id` and no `field_id`, so they need no table of their own) | | form view/edit |
| **Files** | `POST /files` (multipart, temp); `GET /files/{uuid}/url` → signed URL; `GET /files/download/{uuid}` (signed) | | owner/record policy |
| **Workflow** | `GET/PUT /forms/{uuid}/workflow` (statuses, transitions, layout; `base_hash`; → problems and warnings); `GET/POST /forms/{uuid}/workflow/status-mapping` (removed statuses with record counts, chosen targets, ADR-0031); `GET /r/{form}/{uuid}/workflow` (status, available transitions incl. delegated ones, approval, claim, assignments, SLA timers); `GET /r/{form}/{uuid}/transitions`; `POST /r/{form}/{uuid}/transitions/{transition}` (`row_version`, Idempotency-Key); `GET /r/{form}/{uuid}/status-history` | comment, attachments, required field values, justification | `manage_forms` / `transition.{uuid}.perform` + record scope (edit) |
| **SLA** | `GET/PUT /forms/{uuid}/sla-rules` | | `manage_forms` |
| **Views** | `GET/PUT /forms/{uuid}/views` (→ also the relation-path tree); `GET/POST/PATCH/DELETE /r/{form}/saved-views`; `GET/PUT /forms/{uuid}/view-panels`; `GET/PUT /forms/{uuid}/reference-previews`; `GET/PUT /forms/{uuid}/print-layouts`; `GET /r/{form}/{uuid}/panels` (visible panels with data); `GET /r/{form}/preview/{field}/{record}` (lookup card + auto-fill); `GET /r/{form}/{uuid}/print?layout=&format=html|pdf` (audited, throttled) | documents with `base_hash` (ADR-0033, ADR-0034) | `manage_forms` / authenticated for saved views; `view.{uuid}.use`; form `print` |
| **Justification** | `GET/PUT /forms/{uuid}/justification-rules`; `GET/POST/PATCH /justification-reason-codes` (PATCH carries `base_updated_at`); `GET /r/{form}/{uuid}/justifications`; any write may answer 422 `justification_required` / `justification_invalid` with the prompt (ADR-0035) | `{reason_text, reason_code, note, attachments[]}` | `manage_justification_rules` / `view_justifications` |
| **Assignment & My Work** | `GET /my-work?kind=&overdue=&form=`; `POST /r/{form}/{uuid}/assign`; `POST /r/{form}/{uuid}/claim|release`; `GET/PUT /forms/{uuid}/assignment-rules`; `GET/POST/PATCH /queues` (PATCH carries `base_updated_at`); `GET/POST /delegations?scope=mine|all`, `DELETE /delegations/{uuid}` (revoke); `POST /approvals/{uuid}/decide`; `GET /subject-options?type=&search=&uuids[]=` (people pickers, ADR-0036) | | `assign_records`, `reassign_records`, `manage_delegation`, `delegate_own_work`, approver membership; `manage_forms` for queues and rules |
| **Actions** | `GET/PUT /forms/{uuid}/actions`; `POST /r/{form}/actions/{action}/run` (`ids[]`, input, justification) → result or job | | `action.{uuid}.run` |
| **Import/Export** | `POST /r/{form}/imports` (file, mapping, mode, key, dry_run); `GET /imports/{uuid}`; `GET/POST /forms/{uuid}/import-mappings`; `POST /r/{form}/exports`; `GET /exports/{uuid}` | | `form.import` / `form.export` |
| **Bulk & data quality** | `POST /r/{form}/bulk/preview`; `POST /r/{form}/bulk`; `GET /bulk-operations/{uuid}`; `GET/PUT /forms/{uuid}/duplicate-rules`; `POST /forms/{uuid}/duplicate-sweep`; `POST /r/{form}/merge`; `GET /recycle-bin`; `POST /recycle-bin/{id}/restore`; `POST /repair/orphans`, `/repair/validation-sweep` | | `bulk_update`, `merge_records`, `access_recycle_bin`, `repair_data` |
| **Downloads** | `GET/POST/PATCH/DELETE /download-profiles`; `GET /download-profiles/{uuid}/relation-tree?node=`; `POST /download-profiles/{uuid}/preview`; `GET /download-profiles/{uuid}/effective-columns?role=`; `POST /download-profiles/{uuid}/duplicate`; `POST /r/{form}/downloads/{profile}` (params, filters, ids) → stream or job; `GET /download-jobs/{uuid}`; `GET/POST/PATCH/DELETE /download-profiles/{uuid}/schedules` | §14.11 | `manage_download_profiles` / `create_personal_download_profiles` / `download.{uuid}.use` |
| **Notifications** | `GET/POST/PATCH/DELETE /notification-rules`; `GET/POST/PATCH /email-templates`; `POST /email-templates/{uuid}/preview` (record, recipient); `POST /email-templates/{uuid}/test`; `GET /notifications` (bell); `POST /notifications/read` | | `manage_notifications` / authenticated |
| **Documents** | `GET/POST/PATCH/DELETE /document-templates`; `POST /r/{form}/{uuid}/documents/{template}` | | `manage_document_templates` / form print |
| **Automations & scheduler** | `GET/POST/PATCH/DELETE /automations`; `POST /automations/{uuid}/enable|disable|run|test`; `GET /automations/{uuid}/runs`; `POST /automation-runs/{uuid}/confirm|cancel`; `GET /scheduled-tasks` | | `manage_automations` / `run_automations` |
| **Operations** | `GET /ops/health`; `GET /ops/emails?status=`; `POST /ops/emails/resend|cancel` (bulk); `PATCH /ops/emails/{uuid}/recipients`; `GET /ops/emails/{uuid}/html`; `GET /ops/submissions`; `POST /ops/submissions/retry` (bulk); `PATCH /ops/submissions/{uuid}/payload`; `POST /ops/submissions/{uuid}/discard`; `GET /ops/jobs`; `POST /ops/jobs/{id}/retry|discard`; `GET/PUT /ops/alert-rules` | | `manage_operations` |
| **Reference data** | `/business-calendars` (+ holidays), `/number-sequences` (+ `POST …/adjust`), `/currencies`, `/exchange-rates`, `/units` — CRUD | | `manage_calendars`, `manage_numbering`, `manage_currencies`, `manage_reference_data` |
| **Blueprints** | `GET/POST /blueprints` (`source_type: form|view`; view blueprints instantiate onto a chosen form, ADR-0034); `POST /blueprints/{uuid}/instantiate` (include mode); `POST /blueprints/{uuid}/versions`; `GET /blueprints/{uuid}/propagation-preview`; `POST /blueprints/{uuid}/propagate`; `GET /blueprints/{uuid}/export`, `POST /blueprints/import`; `GET/PATCH/DELETE /blueprints/{uuid}`; `POST /blueprint-instances/{uuid}/detach` | | `manage_blueprints` |
| **Reports & dashboards** | `GET/POST/PATCH/DELETE /reports`; `POST /reports/{uuid}/run`; `POST /reports/{uuid}/export`; `GET/POST/PATCH/DELETE /dashboards`; `GET /dashboards/{uuid}/data` | | `manage_reports` / `report.{uuid}.view` |
| **Pages, home, appearance** | `GET/POST/PATCH/DELETE /pages`; `GET /p/{uuid}` (render); `GET/PUT /home-screens`; `GET /home`; `GET/POST/PATCH /themes`; `POST /themes/{uuid}/contrast-check`; `GET /themes/{uuid}.css`; `POST /themes/import`; `GET/POST/PATCH /announcements`; `POST /announcements/{uuid}/dismiss`; `GET/PUT /search-config`; `GET /search?q=` | | `manage_pages_menus`, `manage_branding`, `publish_announcements` / authenticated |
| **Help & tours** | `GET/POST/PATCH /help-content`; `GET /help?target=`; `GET/POST/PATCH /tours`; `POST /tours/{uuid}/progress`; `POST /tours/reset` | | `manage_help_content` / authenticated |
| **Usage** | `POST /usage` (beacon batch); `GET /usage/forms/{uuid}` | | authenticated / `manage_forms` |
| **External (admin)** | `GET/POST/PATCH /external-forms`; `GET/PATCH /external-users` (approve/reject/suspend); `POST /r/{form}/{uuid}/access-tokens`; `DELETE /access-tokens/{uuid}`; `POST /r/{form}/{uuid}/signature-requests` | | `manage_external_access` |
| **External (public, `/x`)** | `GET /x/f/{slug}`; `POST /x/f/{slug}` (CAPTCHA, verification); `POST /x/register`, `/x/login`, …; `GET/POST /x/t/{token}` (tokenized record action/signature); `GET /x/me/records` | | external guard / token scope |
| **Integrations** | `GET/POST/PATCH /data-sources`; `POST /data-sources/{uuid}/test`; `GET/POST/PATCH /sync-jobs`; `POST /sync-jobs/{uuid}/run` (dry_run); `GET /sync-jobs/{uuid}/runs`; `GET/POST/PATCH /notification-channels`; `GET/POST/PATCH /inbound-endpoints` | | `manage_integrations` |
| **Inbound** | `POST /api/inbound/{slug}` | mapped payload; signature or token | endpoint token/signature |
| **Auto REST API** | `GET/POST /api/v1/data/{form_key}`; `GET/PATCH/DELETE /api/v1/data/{form_key}/{uuid}`; `POST …/{uuid}/transitions/{key}`; `GET /api/v1/openapi.json` (generated per user's visible forms) | same pipeline & permissions | token abilities ∩ user permissions; rate limited |
| **Webhooks** | `GET/POST/PATCH/DELETE /webhooks`; `GET /webhooks/{uuid}/deliveries`; `POST /webhook-deliveries/{uuid}/retry` | | `manage_integrations` |
| **Extensions** | `GET/POST /extensions`; `GET/PUT /extensions/{uuid}/source`; `POST /extensions/{uuid}/test|submit|approve|reject|deploy|rollback`; `GET /extensions/{uuid}/versions` | | `manage_code` (+ `approve_code`) |
| **Packages & drift** | `POST /packages/export`; `POST /packages/import` (upload → validate); `GET /packages/{uuid}`; `POST /packages/{uuid}/resolve|apply`; `POST /environments/compare` | | `manage_packages` |
| **Retention & privacy** | `GET/POST/PATCH /retention-policies`; `POST /retention-policies/{uuid}/run`; `GET /retention-runs`; `GET/POST /legal-holds`; `POST /legal-holds/{uuid}/lift`; `GET/POST /personal-data-requests`; `POST /personal-data-requests/{uuid}/execute`; `GET/PUT /storage-quotas`; `GET /storage/report` | | `manage_retention`, `apply_legal_hold`, `manage_personal_data_requests` |
| **Self-service & admin tools** | `POST /impersonation` (user, reason); `DELETE /impersonation`; `GET/POST/PATCH /access-policies`; `GET/POST/PATCH /feature-flags` | | `impersonate_users`, `manage_access_policies`, `manage_feature_flags` |

## 22. Frontend architecture

### 22.1 State management (Pinia)

| Store | Holds |
|---|---|
| `session` | current user, organization, permissions summary, impersonation state, CSRF readiness |
| `locale` | active locale, direction, calendar, digits, formats; switches `dir` and PrimeVue locale |
| `theme` | resolved theme tokens/CSS URL for app/org, light/dark mode, density |
| `navigation` | sidebar tree, badges (polled/push), admin console tree |
| `definitions` | ClientDefinition cache by (form uuid, version, mode) — immutable entries |
| `records` | per-view query state (filters, sort, page), normalized record cache keyed by uuid + row_version |
| `notifications` | bell items, unread count |
| `builder/*` | per-builder document stores (form, workflow, view, download, page, theme, automation, report) with history |
| `jobs` | tracked background jobs (progress polling) |

Server state is fetched with a thin typed client; stores never hold authority.

### 22.2 Form builder metadata model

- The builder edits a **draft document** identical to §14.1 (minus `schema`), as a
  normalized store: `groups` and `fields` maps by uuid + `children` order arrays.
- Every mutation is a **command** (`AddElement`, `MoveElement`, `UpdateProperty`,
  `Duplicate`, `Paste`, `Delete`, `BulkUpdate`) applied by a reducer; the history
  stack stores inverse commands (undo/redo, full history in session; grouped by
  interaction).
- Clipboard: serialized subtree in §14 format (uuids/keys regenerated on paste), via
  the system clipboard (MIME `application/x-lcf-elements+json`) so copy/paste works
  between forms and tabs.
- Keyboard shortcuts: undo/redo (Ctrl/⌘+Z, Ctrl/⌘+Shift+Z), copy/cut/paste,
  duplicate (Ctrl/⌘+D), delete, select all/multi-select (Shift/Ctrl+click), move
  selection (Alt+↑/↓), save draft (Ctrl/⌘+S), toggle preview; shown in a shortcut
  help dialog (`?`), mirrored for RTL arrow semantics. Multi-select opens a
  properties panel restricted to the properties shared by the selection (bulk
  property change as one `BulkUpdate` command).
- Autosave: debounced `PUT /forms/{uuid}/draft` with `draft_updated_at`
  precondition; conflict → merge dialog.
- Palette: from `GET /field-types` (registry), searchable, categorized; drag via
  vuedraggable (Sortable.js) with nested drop zones at any depth; click-to-insert at
  selection.
- Properties panel: tabs (General, Data & Database, Options, Validation, Behavior,
  Access, Conditions, Events, Table & Export, Justification) rendered from the field
  type's JSON schema + custom editors (rule builder, formula editor with Monaco
  + expression language mode, options grid, access matrix).
- Live preview: the runtime FormRenderer in an iframe-like container at desktop/
  tablet/mobile widths, AR/EN, using `GET /forms/{uuid}/preview?as_user=…` so access
  resolution is the server's.

### 22.3 Runtime form renderer

```
FormRenderer(definition, record?, mode)
 ├─ ConditionRuntime   evaluates condition ASTs on change (TS evaluator), applies effects
 ├─ FormulaRuntime     dependency-ordered recomputation
 ├─ ValidationRuntime  client rules mirroring server (UX only); server 422 merges in
 ├─ GroupHost          renders group types (section, tabs, wizard, repeater…) recursively
 └─ FieldHost          resolves field component from registry; label/help/tooltip/RTL;
                        access state (hidden/read-only/required) from definition + effects
```

On submit: `Idempotency-Key`, `row_version`; handles 422 (field errors),
`justification_required` (prompt dialog after validation passes), 409 (conflict
screen), 423 (locked). Modes: create, edit, view, print.

### 22.4 Component library structure

- `design-system/`: tokens, `Base*` wrappers over PrimeVue 4 (Button, Input, Select,
  DataTable, Dialog, Drawer, Tabs, Stepper, Tree, Toast) enforcing RTL, a11y labels,
  density; layout primitives (Stack, Grid with 12 columns per breakpoint).
- `runtime/fields/`: one component per field type implementing `FieldComponent`
  (`modelValue`, `definition`, `access`, `errors`, `locale`), built on Base*,
  TipTap, Monaco, Leaflet, signature_pad, qrcode/JsBarcode, ECharts; Hijri date
  picker adapter.
- `builders/`: builder shells sharing a `Canvas`, `Palette`, `PropertiesPanel`
  framework and the command/history engine.
- Accessibility: WCAG 2.1 AA — labels, focus order, keyboard DnD alternatives in
  builders (move up/down/into buttons), contrast from theme checker, axe checks in
  Playwright.

## 23. Deployment, environments & CI

- **Docker Compose** services: `app` (php-fpm 8.3; its entrypoint runs
  `db:ensure`, migrations, seeders, and config caching, and stops with
  instructions when `APP_KEY` is empty), `web` (nginx with the built `public/`
  baked in from the same Dockerfile), `worker` (Horizon), `scheduler`
  (`schedule:work`), `redis`, `mysql` (8.4), `sqlserver` (2019, profile
  `sqlsrv`), `clamav`, `mailpit` (profile `dev`). `app` and `web` are built
  (`pull_policy: build`), never pulled; `worker` and `scheduler` reuse the app
  image (`pull_policy: never`). `app` is healthy when php-fpm listens (after the
  entrypoint), `web` when `/up` answers through php-fpm; `worker`, `scheduler`,
  and `web` wait for a healthy `app`, so `docker compose up -d --wait` returns
  when the stack works. `.dockerignore` keeps `.env` files, `.git`,
  dependencies, and runtime state out of every image (ADR-0026).
- **Line endings:** `.gitattributes` (`* text=auto eol=lf`) gives every checkout
  LF on every OS; the Dockerfile also strips `\r` from the entrypoint (ADR-0026).
- **Dev container** (`.devcontainer/`): its own compose file (dev image, MySQL,
  Redis, Mailpit), independent of the root stack and of any `.env`. MySQL's
  password is generated on first start into a volume shared with the dev
  container. `post-create.sh` installs dependencies, writes `backend/.env`
  (generated key; in Codespaces the forwarded URL and Sanctum stateful domain),
  creates, migrates, and seeds the database, and builds the SPA; `post-start.sh`
  serves the application on forwarded port 8000 and runs the scheduler.
- **CI** (`.github/workflows/ci.yml`): `backend-quality` (Pint, Larastan,
  composer audit), `backend-tests` (matrix `db: [mysql, sqlsrv]`, service
  containers MySQL 8.4, SQL Server 2019, Redis; Pest with `--fail-on-warning`),
  `frontend` (ESLint, Prettier, vue-tsc, Vitest, build, npm audit), `e2e` (matrix
  db; Playwright against `artisan serve`), `line-endings` (nothing committed with
  CRLF; a `core.autocrlf=true` checkout gets LF everywhere), `stack` (matrix db:
  from a Windows-style checkout, the README guide command by command, health and
  restart assertions, no `.env` in the image, Playwright through nginx),
  `devcontainer` (devcontainer CLI as Codespaces runs it; HTTP checks and the
  Pest suite inside), `docker` (image builds, compose validation). Screenshots,
  traces, and HTML reports are uploaded as artifacts. Secrets only from GitHub
  Secrets.
- Backups: a scheduled system task runs engine-native backups (MySQL
  `mysqldump --single-transaction` / SQL Server `BACKUP DATABASE … WITH COPY_ONLY`)
  plus a storage sync of the private disk, with retention from settings; status is
  shown on the health page; the restore procedure is part of the Phase 6
  deployment, backup, and restore guide. Dependency scanning (composer audit, npm
  audit) runs in CI.
- Environments: `local`, `ci`, `staging`, `production`; production runs with
  `APP_DEBUG=false` (checked by the health page), HTTPS only, separate DB users
  for migrations (DDL) and runtime (DML; runtime user also needs DDL for form
  publishing — ADR-0015 grants it a dedicated *schema* role limited to the `f_`,
  `c_`, `p_` table namespaces where the engine supports it, documented in the
  deployment guide).

## 24. Technical decisions

Each decision has a full record in `docs/decisions/`.

| ADR | Decision | Reasoning |
|---|---|---|
| 0001 | Modular monolith (one Laravel app, modules with contracts/events) | One deployable, transactional consistency for the record pipeline, clear boundaries for phased delivery |
| 0002 | Physical table per form/collection, generated by runtime migration plans (not EAV, not JSON blobs) | Spec §4.9; real types, FKs, indexes; performance budgets |
| 0003 | Stable `uuid` on every metadata object; cross-references by uuid in JSON | Packages, blueprints, environment drift, safe public identifiers |
| 0004 | `organization_id` on every tenant table with a platform root organization | Supports both tenancy modes with one schema; no NULL-tenant special cases |
| 0005 | Enums as strings + PHP backed enums + CHECK constraints | Portable across MySQL/SQL Server; readable data |
| 0006 | All datetimes UTC in `datetime(6)`/`datetime2(6)` | No 2038 limit, no engine timezone drift; display converts |
| 0007 | Translations in a single keyed table; `ar`/`en` locales seeded as system settings | Spec §3 Languages; a third language without schema change |
| 0008 | Expression language: JSON AST, decimal arithmetic, step-count bounds, shared corpus | Spec §4.7 parity; deterministic across runtimes |
| 0009 | Precedence user > role > department for grants, field access, and record scopes; deny beats allow within a tier; a more specific tier overrides a less specific one including its deny; hard deny overrides every tier | Owner decision resolving the §4.11 ordering conflict; spec §4.11 updated |
| 0010 | Transactional outbox for after-commit side effects | Notifications/automations/webhooks iff data committed |
| 0011 | Hash-chained, sharded (16 chains) audit log with monthly partitions | Tamper evidence with acceptable write contention; growth plan |
| 0012 | No outbound call during authentication (offline password list) | All egress through the gateway; login must not depend on third parties |
| 0013 | Self-hosted proof-of-work CAPTCHA for external forms | No third-party egress or tracking; works offline |
| 0014 | Crypto-shredding for personal data in immutable logs | Anonymization without breaking the audit hash chain |
| 0015 | Separate DB principals for framework migrations vs. runtime, with a scoped schema role for generated tables | Least privilege while still allowing runtime DDL for publishing |
| 0016 | Phase numbering follows specification §8 (0, 1, 2, 2.5, 3, 4, 5, 6) | CLAUDE.md says seven phases; the specification (which wins) defines eight, including the 2.5 pilot |
| 0017 | Conformance corpus location `docs/conformance/expression-corpus.json` | Shared by `/backend` and `/frontend` test suites; language-neutral data, versioned with the spec |
| 0018 | Draft metadata lives in working tables; published versions are immutable JSON snapshots | Fast editing + exact history, diff, and rollback |
| 0019 | Sparse access rules with `rule_hash` uniqueness; nullable-partial uniqueness via filtered index (SQL Server) | Engine-neutral uniqueness semantics |
| 0020 | OpenSpout streaming writers under maatwebsite/excel for large exports | Memory-bounded multi-sheet xlsx generation for downloads |
| 0021 | Foreign-key columns that point at later-phase tables are added by the phase that creates those tables | No dangling references; the ERD conformance test lists the deferred columns |
| 0022 | The first-run wizard requires a one-time console-issued setup token, stored only as a hash | An exposed fresh install cannot be claimed by whoever reaches it first |
| 0023 | Server-side escalation guard for people administration and role/department grants | Administrators cannot give or manage more access than they hold |
| 0024 | Vue 3 + PrimeVue SPA served by Laravel with a per-request CSP nonce; interface strings as translations | Strict CSP without unsafe-inline; RTL/LTR from one code base |
| 0025 | SMTP configured from System Settings through a custom `lcf` mail transport | No credentials in files; workers pick up changes without a restart |
| 0026 | Portable container stack: LF checkouts, built-not-pulled images, health-ordered start-up, no secrets in images, CI from a Windows-style checkout | The owner's Windows run of Phase 1 crash-looped on a CRLF entrypoint that Linux-only CI never exercised |

## 25. Phase map

| Phase | Builds (sections of this document) |
|---|---|
| 0 | This document, expression language spec + corpus, decisions, progress |
| 1 | §5 scaffolding, §6 driver layer, §7 security baseline, §8 queues/outbox/correlation, §10.2–10.4 + `settings`/`locales`/`translations` + audit/error tables (§10.21), §19.15, §19.18, §19.24, Users/Departments/Roles/system permissions (§16.2), Admin Console, Settings, SSO/LDAP |
| 2 | §3 runtime engine, §10.5–10.7, §10.15, §10.17 (partial), §11–§15, §16.3–16.5 & explain, §17 (lists), §19.1, §19.6 (capture), §19.19, field-level matrices, menus/publishing |
| 2.5 | Owner pilot, fixes, `docs/pilot-findings.md`, spec updates |
| 3 | §10.8, §10.9 (views), §10.11, §10.12, §16.6, §19.2–19.4, view/edit/print modes |
| 4 | §10.9 (actions/import/export), §10.10, §10.13, §10.14 (documents), §10.16, §10.18, §19.5, §19.7–19.10, §19.14, Operations Center |
| 5 | §10.14 (reports), §10.17 (complete), §10.19, §10.20, §10.22–10.24, §18, §19.11–19.13, §19.17, §19.20–19.23, REST API/OpenAPI, packages & drift |
| 6 | §10.25, §19.16, partitioning, security review, performance (§20), a11y, docs |
