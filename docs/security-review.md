# Security review against specification §5 (Phase 6)

- Date: 2026-10-29
- Scope: the whole application as of the `phase-6-hardening` branch: backend, frontend and CI.
- Method: each §5 requirement checked against the code and its tests; mechanical scans for the classes of defect a reading can miss; fixes made with tests that fail without them.

## Findings and fixes

| # | Area (§5) | Finding | Severity | Fix | Test |
|---|---|---|---|---|---|
| 1 | SSRF | **PDF rendering bypassed the egress gateway.** Rich text and document templates allow `<img src="https://…">`. mPDF fetched such images itself, with no allowlist and no blocked ranges. It would also read local paths such as `/etc/passwd` given as an image source. This affected documents, the print view and signed copies. | High | Every mPDF instance is built by `PdfFactory`, which installs `GatedHttpClient` and `DataOnlyContentLoader`. `https` GETs go through the egress gateway; `data:` URIs are decoded; everything else is refused. Stream wrappers are limited to `https` and `data`. | `tests/Feature/Security/UntrustedInputTest.php` |
| 2 | Untrusted input | **Decompression bomb in image uploads.** An upload was decoded fully before its dimensions were checked, so a small PNG could claim gigabytes of memory. The public signature endpoint had the same flaw: an unauthenticated signer sends the image. | Medium | Dimensions are read from the image header (`getimagesizefromstring`) before decoding, in `FileStore`, `Signatures` and `DocxBuilder`. | `UntrustedInputTest` (oversized PNG refused) |
| 3 | Untrusted input | **Zip bombs in XLSX imports and DOCX templates.** Nothing capped the uncompressed size. | Medium | `ZipGuard` refuses a container with more than 10,000 entries, more than 300 MB uncompressed, or an entry over 1 MB compressed more than 200:1. It reads only the zip directory and decompresses nothing. | `UntrustedInputTest` |
| 4 | Data protection | **Per-tenant key management and rotation were missing.** The `encryption_keys` table of the ERD had never been created. Encrypted fields used the application key directly, and there was no rotation procedure. | Medium | Per-organization AES-256-GCM data keys wrapped by `APP_KEY` (`DataKeys`); ciphertext `v1:{keyId}:…`. `keys:rotate` re-encrypts field values or recomputes blind indexes. `keys:rewrap` re-seals everything sealed with the master key after an `APP_KEY` change, and clears the settings cache. The procedure is in the deployment guide. A new conformance assertion checks that every ERD table exists. | `tests/Engine/KeyRotationTest.php`, `SchemaConformanceTest` |
| 5 | Operations | **New dependency advisories.** `symfony/mime` CVE-2026-45070 (e-mail header injection), vue-i18n GHSA-8rc2-428q-7ww3 and GHSA-83rm-6c8r-g728, and ECharts GHSA-fgmj-fm8m-jvvx (XSS). All were published after Phase 5 merged. | Medium–high | Updated to `symfony/mime` 7.4.22, vue-i18n 11.4.16 and echarts 6.1.0. `composer audit` and `npm audit` report nothing. | CI `composer audit`, `npm audit --audit-level=high` |
| 6 | Access control | **No test kept new routes authenticated.** Not a defect today, but a regression risk. | Low | `PublicRoutesTest` lists the 46 deliberately public API routes (sign-in, setup, signed links, API-token data API, inbound endpoints, external surfaces). Any other route without an `auth:` guard fails the build. | `tests/Feature/Security/PublicRoutesTest.php` |
| 7 | Audit integrity | **The chain was never verified over a realistic run.** | — | CI now runs `audit:verify --full` after the whole end-to-end suite on both engines. That includes the personal-data anonymization in `23-retention.spec.ts`, which must leave every chain intact. | CI step "Audit chain verification" |

## Requirements checked without findings

- **Parameterized queries.** Every raw SQL fragment was scanned (`whereRaw`, `selectRaw`, `DB::raw`, `statement`, `unprepared`). Each one either uses wrapped identifiers validated against `[A-Za-z_][A-Za-z0-9_]*` with bound values, or is integer arithmetic. No interpolated input was found.
- **Outbound HTTP.** No module calls out except through `EgressGateway`. A static test now enforces this, together with "no `new Mpdf` outside `PdfFactory`". The ClamAV daemon (`fsockopen`), SMTP and LDAP are infrastructure connections to administrator-configured hosts. They are not on a user's behalf, so they are outside the gateway by design.
- **IDOR.** A scan flagged 80 authenticated actions with no inline authorization call. Each one was traced to its authorization helper (`own`, `editable`, `viewable`, `owned`, `canRead`, or the runner and pipeline checks) or to user-scoped "me" data. None reaches another user's object.
- **XML parsing.**
  - DOCX parts are refused when they contain a `DOCTYPE` and loaded with `LIBXML_NONET`, so entities cannot be declared.
  - XLSX is read by OpenSpout, streaming, with formulas read as text.
  - HTML is sanitized by HTML Purifier before rendering.
- **XSS.** The frontend has no `v-html`. Rich text and admin content go through `HtmlSanitizer` (an allowlist). The CSP uses a per-request nonce with no `unsafe-inline` or `unsafe-eval` (Phase 1 tests).
- **CSRF, security headers, session management, lockout, password policy, 2FA, step-up, SSO and LDAP account takeover, impersonation limits, external surfaces, secrets write-only and masked.** Covered by the existing Phase 1–5 test suites (`tests/Feature/Security`, `Identity`, `Engine/ExternalAccessTest`, `Engine/SelfServiceTest`), which pass.
- **Expression safety.** The PHP and TypeScript evaluators pass the shared conformance corpus. No code path evaluates generated code.
- **Sensitive data in logs.** `Masker` and the audit writer mask sensitive and encrypted fields. Personal-data values in the audit trail are now also sealed per record (ADR-0014, ADR-0070).

## Residual risks, stated plainly

- **Blind-index rotation window.** While `keys:rotate --purpose=blind_index` runs, exact search on an encrypted field can miss rows not yet recomputed. Run it in a maintenance window; the guide says so.
- **Schema snapshots.** Pre-publish snapshots are files sealed with `APP_KEY`, and `keys:rewrap` does not re-seal them. Keep the old key in `APP_PREVIOUS_KEYS` until the snapshot retention has passed; the guide says so.
- **Audit entries written before Phase 6.** Those entries hold personal-data values in clear. Re-sealing them would break the hash chain, so anonymization cannot make those historical values unreadable (ADR-0070, Consequences).
