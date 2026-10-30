# Architecture Decision Records

One short record per significant decision (specification §8.1). New records are numbered sequentially, and records are never deleted. A superseded record is marked `Superseded by ADR-NNNN`.

| ADR | Title |
|---|---|
| [0001](0001-modular-monolith.md) | Modular monolith |
| [0002](0002-physical-tables-per-form.md) | One physical table per form and collection |
| [0003](0003-uuid-identity.md) | Stable UUID identity for metadata and records |
| [0004](0004-tenancy-platform-root.md) | Organization column everywhere, with a platform root |
| [0005](0005-enums-as-strings.md) | Enumerations stored as strings with CHECK constraints |
| [0006](0006-utc-datetime.md) | All timestamps in UTC with microsecond datetime types |
| [0007](0007-translations-table.md) | Translatable content in one keyed table; shipped locales seeded |
| [0008](0008-expression-language.md) | Expression language: JSON AST, decimal arithmetic, step bounds, shared corpus |
| [0009](0009-permission-precedence.md) | Permission and access precedence |
| [0010](0010-transactional-outbox.md) | Transactional outbox for after-commit side effects |
| [0011](0011-audit-hash-chain.md) | Sharded hash-chained audit log with time partitions |
| [0012](0012-no-egress-at-login.md) | No outbound calls during authentication |
| [0013](0013-self-hosted-captcha.md) | Self-hosted proof-of-work CAPTCHA |
| [0014](0014-crypto-shredding.md) | Crypto-shredding for personal data in immutable logs |
| [0015](0015-db-principals.md) | Separate database principals for framework migrations and runtime |
| [0016](0016-phase-numbering.md) | Phase numbering follows the specification |
| [0017](0017-corpus-location.md) | Conformance corpus location |
| [0018](0018-draft-working-tables.md) | Drafts in working tables; published versions as immutable snapshots |
| [0019](0019-nullable-uniqueness.md) | Engine-neutral uniqueness for nullable and conditional keys |
| [0020](0020-streaming-writers.md) | Streaming spreadsheet writers for large exports |
| [0021](0021-deferred-foreign-key-columns.md) | Deferred foreign-key columns |
| [0022](0022-setup-token.md) | One-time setup token for the first-run wizard |
| [0023](0023-escalation-guard.md) | Privilege-escalation safeguards for people management |
| [0024](0024-frontend-stack-and-csp.md) | SPA stack, interface strings, and a strict CSP |
| [0025](0025-settings-driven-mail.md) | SMTP configured from System Settings |
| [0026](0026-portable-container-stack.md) | A container stack that runs from any host's checkout |
| [0027](0027-expression-runtime-semantics.md) | Expression runtime details left open by the language specification |
| [0028](0028-phase-2-data-engine.md) | Data engine decisions made while building Phase 2 |
| [0029](0029-user-facing-validation-and-verbatim-expressions.md) | User-facing validation messages and verbatim expressions |
| [0030](0030-interface-design-system.md) | One token-based design system for the interface |
| [0031](0031-versioned-workflow-and-status-mapping.md) | The workflow is versioned with the form; removed statuses are mapped at publish |
| [0032](0032-record-level-security.md) | Record-level rules — default "all", tier walk, compiled custom scopes |
| [0033](0033-per-form-configuration-documents.md) | Per-form configuration documents saved whole with a concurrency hash |
| [0034](0034-table-views-and-view-blueprints.md) | Table views as relation paths; view blueprints apply directly |
| [0035](0035-justification-scope-in-phase-3.md) | Justification scopes delivered in Phase 3, and how rules combine |
| [0036](0036-assignment-delegation-and-approvals.md) | Assignment, queues, delegation and approvals |
| [0037](0037-record-pages-and-labels.md) | Record page layout, labels and sidebar entries |
| [0038](0038-background-import-and-export.md) | Imports and large exports run as background jobs |
| [0039](0039-notification-rendering-and-delivery.md) | How notifications are rendered for several recipients, and delivered |
| [0040](0040-document-templates.md) | Document templates — Word files, designed templates, and conversion |
| [0041](0041-actions.md) | Actions — built-ins, custom steps, runs and permissions |
| [0042](0042-custom-downloads.md) | Custom downloads — paths, one-to-many handling, security and scheduling |
| [0043](0043-automations.md) | Automations — runs, run-as user, loop protection, waits and test runs |
| [0044](0044-data-quality.md) | Data quality — duplicate rules, merge, bulk tools, repair and recycle-bin retention |
| [0045](0045-operations-center.md) | Operations Center — failures, retries, alerts and queue workers |
| [0046](0046-document-template-builder-and-blueprints.md) | Document template builder — values, formats, blocks and blueprints |
| [0047](0047-notification-recipients-at-send-time.md) | Notification recipients from the record, resolved at send time |
| [0048](0048-design-checklist-enforced-by-tests.md) | The screen checklist is enforced by tests |
| [0049](0049-fixed-transition-bulk-status.md) | Bulk status change with a fixed transition |
| [0050](0050-api-tokens-webhooks-inbound.md) | API tokens, webhooks and inbound endpoints |
| [0051](0051-developer-extensions.md) | Developer extensions |
| [0052](0052-configuration-packages.md) | Configuration packages and the environment drift report |
| [0053](0053-reports-and-dashboards.md) | Reports and dashboards |
| [0054](0054-pages-home-navigation-announcements-search.md) | Pages, home screens, navigation, announcements, maintenance mode and global search |
| [0055](0055-themes.md) | Themes per organization and per application |
| [0056](0056-notification-channels.md) | SMS and messaging notification channels |
| [0057](0057-data-sources-and-sync-jobs.md) | External data sources and sync jobs |
| [0058](0058-external-access.md) | External access: external forms, portal accounts, record links and signatures |
| [0059](0059-application-and-part-blueprints.md) | Blueprints of applications, workflows, notification rules, actions and dashboards; cloning applications |
| [0060](0060-access-policies-and-trusted-devices.md) | Access policies per role and trusted devices |
| [0061](0061-impersonation.md) | Impersonation |
| [0062](0062-feature-flags.md) | Feature flags |
| [0063](0063-self-service.md) | Self-service preferences, pinned records, shortcuts and digests |
| [0064](0064-help-content-knowledge-and-changelog.md) | Help content, knowledge pages and changelogs |
| [0065](0065-guided-tours.md) | Guided tours and first-use hints |
| [0066](0066-usage-insight.md) | Usage insight |
| [0067](0067-review-safeguards.md) | Unreachable-screen guard and four safeguards found in review |
| [0068](0068-report-cites-a-finished-run.md) | The verification report can only cite a finished run on the head commit |
| [0069](0069-carried-forward-jit-crash-and-repair-scan.md) | The two items carried into Phase 6: the PHP JIT crash and the run 152 repair scan |
| [0070](0070-retention-legal-holds-personal-data.md) | Retention, legal holds, storage quotas and personal data as built |
| [0071](0071-data-keys-and-rotation.md) | Per-organization data keys and the key rotation procedure |
| [0072](0072-bulk-work-throughput.md) | Bulk-work throughput: parallel import batches, batch memo, one audit chain per batch |
| [0073](0073-lists-and-imports-at-full-scale.md) | Lists and imports at full scale: bounded counts, id tie-breaks, fewer round trips per imported row |
| [0074](0074-request-budgets-on-the-sustained-stage.md) | CI checks the request budgets on the sustained stage of the scaled load, and reports regressions without failing |
