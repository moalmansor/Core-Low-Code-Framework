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
