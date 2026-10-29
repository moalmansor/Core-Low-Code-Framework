# ADR-0058: External access — external forms, portal accounts, record links and signatures

- Status: Accepted (Phase 5)
- Date: 2026-10-23

## Context

Specification §4.32 asks for forms published outside with their own address, theme and status on submission, protected by CAPTCHA, limits, a time window, caps, email or SMS verification and a password; external accounts with registration, approval, their own role and access to their own records only; tokenized single-record links (approve, reject, complete a section, sign); and signature requests with a stored signed output. Everything external stays governed by the permissions, conditions, justification and audit of the rest of the system. The architecture (§7.6, §10.19, §19.12) fixes the tables and asks for a self-hosted CAPTCHA (ADR-0013), hash-only tokens and a separate external surface.

## Decision

**External forms** (`/x/f/{slug}`, administered under External access)
- The administrator picks a published form, a unique address, a title, introduction and success message per language, a theme and the status a submission starts in.
- Shown fields are an allow-list. Only fields that need no internal data can be shown. These are excluded:
  - files;
  - references to records, users, roles or departments;
  - choices loaded from the server, and custom field components;
  - passwords, hidden fields, signatures, maps, JSON, barcodes and buttons;
  - anything inside a repeating group or sub-form.
- A required stored field without a default must be shown, or saving is refused.
- Submissions go through the record pipeline as the form's **run-as user**, with source `external`. The run-as user must be able to create records, and their field access applies on top of the allow-list. Values of fields the form does not show are ignored.
- The client definition hides every other field and every group left empty, carries no internal identity in its expression context, and drops relation data. Errors about fields the visitor cannot see become one general message.
- Who may open it:
  - **anyone**; or
  - **signed-in portal users**, optionally only those with chosen external roles (new columns `audience`, `role_ids`).
- Protections:
  - an optional password (hashed; 10 wrong tries per address per 10 minutes);
  - a **proof-of-work CAPTCHA** — HMAC-signed SHA-256 challenge, up to 50,000 tries in the browser, expires after 10 minutes, accepted once;
  - submissions per visitor address per hour, counted in `submission_throttles` by hash. With verification, the verified address is counted too.
  - opening and closing times;
  - hours of the day and days of the week in the organization's time zone (a window may run past midnight);
  - a cap, reserved atomically before the record is written and given back if it is not;
  - email or SMS verification with a one-time code. The code has 6 digits, lasts 10 minutes, allows 5 tries and is kept only as an HMAC in the cache. Sending is limited per address and per visitor.
  - Emailed codes go through the email queue. SMS codes go straight to the default SMS channel (ADR-0056), so no delivery log keeps them.
- All `/x` endpoints share a limit of 60 requests per minute per address.

**Portal accounts** (`/x/portal`)
- Registration is off until an administrator opens it and chooses the external role new accounts get.
- New accounts verify their email address with a code. They are then approved at once or wait for an administrator, as configured (settings group `external`).
- Answers never reveal whether an address has an account.
- Sign-in uses the internal lockout policy and idle timeout.
- The portal session is kept in the same session store under its own key (`external_user_id`) instead of a second guard and session cookie, which would collide with the single CSRF cookie. No internal guard, route or permission sees an external user, and the session id is regenerated on sign-in and sign-out.
- An administrator can approve, reject, suspend, reactivate, change the role of, or delete an account. Deleting anonymizes the account; its records stay. Suspending or deleting ends its sessions.
- **External roles** are roles with `audience = external`. They are managed only under External access; internal role lists, user role assignment and the access matrix ignore them.
- A signed-in external user sees:
  - the forms open to them;
  - their own records only (`external_user_id`), with the fields of that form's first external form open to them and the status.

**External actor**
- While a portal user submits, the external user's id is recorded on the record (`external_user_id`), the audit entries, the submission journal and the status history.

**Record links** (`/x/t/{token}`)
- Anyone who can see a record can send a link to it:
  - **view** it;
  - **approve** or **reject** through a workflow step they may take now (new column `access_tokens.transition_id`);
  - **complete a section** they may edit.
- The token has 256 random bits; only its SHA-256 is stored, and the link is shown once. It expires after 1–30 days, allows 1–100 uses, and can be withdrawn.
- What the recipient does is done **as the sender**, through the pipeline, at the moment of use. Permissions, record scope, field access, rules, the step's requirements and justification rules all apply, and the record's version is checked.
- A refused attempt gives the use back.
- Openings and uses are audited with the recipient's address.

**Signature requests** (`/x/s/{token}`)
- Someone who can view and print a record sends one of the form's document templates that produce PDF to an email address, an internal user or an external user.
- The PDF is generated then and kept, so the signer signs exactly what was sent.
- The signer downloads it, confirms having read it, and draws a signature or declines with a reason.
- The server:
  - re-encodes the drawing;
  - appends a signature page with the drawing, the signer, the time, the address and the original's SHA-256 (mPDF with FPDI);
  - stores the signed PDF and its SHA-256;
  - writes an audit entry.
- Requests can be cancelled until signed and expire on their date (`external:maintain`, hourly).

**Rate limits** (found while building this)
- `throttle:N,M` now counts per person or address **and per route**. Laravel's default counted every such route under one key, so strict limits were used up by unrelated requests.

## Consequences

- External surfaces need no new permission: external users hold none, and links act within their sender's.
- A form whose required fields need internal data cannot be published outside until those fields get defaults.
- A signed document is a PDF with a signature page, not a cryptographic PDF signature. Its integrity rests on the stored hashes and the audit chain.
