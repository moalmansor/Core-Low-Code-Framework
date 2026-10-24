# ADR-0051: Developer extensions

- Status: Accepted (Phase 5)
- Date: 2026-10-16

## Context

Specification §4.19 asks for developer extensions for the rare cases the builder cannot cover. It lists server hooks at nine points, custom API endpoints, and client validators, field components and form scripts. The code lives on disk in versions, is written in a Monaco editor and is never run through `eval()`. Each version is tested in a sandbox, approved by a holder of Approve code, deployed, and can be rolled back. Hooks run inside transactions with time limits and isolated errors, and their failures appear in Error Monitoring and the Operations Center.

Running code that administrators write is the most dangerous thing the platform does. The design keeps several safeguards between that code and the data.

## Decision

**Storage and lifecycle**
- Each version is a folder `{LCF_EXTENSIONS_PATH}/{organization}/{key}/v{n}/`. It holds `source.php` or `source.ts` as written, `build.php` or `build.js` (what runs), and `manifest.json`. These are plain files a team can keep in Git. The default location is `storage/app/extensions`, and the Docker image creates that directory.
- The SHA-256 of the build is stored with the version. It is checked every time the build is loaded, so a file changed after approval never runs and the save that needed it fails.
- The lifecycle is draft → tested → waiting for approval → approved → deployed.
  - Saving a version that is not yet approved replaces it and clears its test.
  - Saving after approval starts the next version.
  - Submitting needs a passed test of exactly the stored code.
  - Approval needs Approve code and someone other than the version's author and the extension's last editor.
  - Deploying needs an approved version. Deploying an earlier approved version is a rollback.
  - Turning an extension off stops it at once.
  - A deployed extension cannot be deleted, only turned off, so its history is kept.
  - Every step is audited. Approval, deployment and rollback are audited as security events.

**Server code (hooks and API endpoints)**
- The author writes one class, `Hook implements ServerHook` or `Endpoint implements ApiEndpoint`, with no namespace and no imports. The build adds a namespace unique to the version (from its uuid), the SDK imports, `declare(strict_types=1)` and `declare(ticks=1)`. The syntax is checked by `php -l` in a separate process.
- Before storing, `CodeGuard` reads the tokens and refuses:
  - any function not on an allow-list (strings, arrays, maths, dates, JSON, regular expressions);
  - any class other than the SDK's, a few value classes and the extension's own;
  - a callback given by name rather than as an inline closure;
  - eval, include, exit, goto, declare, namespace, global, echo and print;
  - backticks, superglobals and variable variables;
  - calling a variable, a string or an expression; `new` on a variable;
  - `fromCallable`, `__invoke`, `call` and `bind`;
  - inline HTML.
- `CodeGuard` is a guard rail. The real safeguards are the Manage code permission, a second person's approval, the transaction and the time limit.
- Through the SDK, a hook reads and (where its point allows) sets values, adds field errors, refuses with a message, logs, and reads other forms' records as its user. An endpoint reads the request and records as the caller. Outbound calls go through the egress gateway; in a test run nothing is sent and the call returns 599.
- Hooks of a form run in key order at their point:
  - onLoad changes what is shown, never the stored record.
  - beforeValidate runs before formulas and validation.
  - afterValidate adds field errors.
  - beforeSave changes values, which are validated again before writing.
  - afterSave, beforeDelete and afterDelete run inside the save or delete.
  - onStatusChange runs inside every status move, including approvals.
  - onAction runs after an action's work on each record.
- The time limit is enforced on every statement through ticks, so a loop is stopped in PHP-FPM and in workers alike.
- `fail()` refuses with the hook's message. Any other error, or the time limit, refuses with a general message and rolls everything back (fail-closed). The error is written to Error Monitoring and to the audit log (`extension.failed`, listed in the Operations Center under "Developer extension") once the transaction is over, so the record of the failure is not rolled back with the save.
- A custom API endpoint answers `GET` or `POST /api/v1/ext-api/{key}` for signed-in people who hold the permission chosen in its manifest. It runs in a transaction that is always rolled back, so it reads and never writes.
- The sandbox test runs the stored version against sample values or a real record (with a sample transition or action where the point needs one), inside a transaction that is rolled back. It reports changed values, field errors, a refusal or the error, the log and the time taken.

**Client code (validators, field components, form scripts)**
- TypeScript is compiled when saved by esbuild's standalone program (`LCF_ESBUILD`), from an empty folder so an import can resolve nothing. The one exception is `vue`, which the frame provides from Vue's runtime build (render functions only).
- Each extension runs in an `<iframe sandbox="allow-scripts">`. Its origin is opaque, so it has no cookies, no session and no access to the page.
  - The frame document is `/ext/frame` with its own policy: scripts only from `/ext/`, no network, framed only by this application.
  - It loads `/ext/runtime.js` and the build `/ext/b/{version}.js`, with an integrity hash.
  - The page and the frame talk only by messages, and the page reads only messages from that frame.
- Validators check the form before it is sent. Form scripts may set editable fields and show messages. A field component draws the input of a text or long-text field, chosen with the field's developer hook property (specification §4.6: `hook = {extension, "component"}`, which only Manage code can change). The server validates every save as usual.
- Client builds and the runtime are served without a session: they are code, not data, and the opaque-origin frame cannot send one.

**Editor**
- The editor is Monaco. Monaco positions its lines with style attributes, so the pages under `/admin/extensions` add `style-src-attr 'unsafe-inline'` (style attributes only, never style elements). The SPA loads those pages, and the first page after them, as full documents, so every other screen keeps the strict policy. Monaco's own `<style>` elements carry the page's nonce.

## Consequences

- Extensions run only after two people have seen them. They cannot outlast a change to their files or run past their time limit, and a failure never half-writes a record.
- The guard refuses some legitimate PHP, such as a callback passed by name. Authors write closures instead.
- Client extensions can only advise. They cannot read the session or call the API.
