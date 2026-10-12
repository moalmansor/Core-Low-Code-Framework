# ADR-0039: How notifications are rendered for several recipients, and delivered

- Status: Accepted (Phase 4)
- Date: 2026-10-12

## Context

One notification rule can send to users with different field access and
different languages, in To, CC and BCC, plus addresses outside the system.
§4.16 asks for To/CC/BCC control, placeholders from fields and linked
records, conditional blocks and repeater tables, and AR/EN versions; access
rules say nobody may see a field hidden from them.

## Decision

- **One message per language.** Recipients are grouped by their language; each
  group gets one message with its To, CC and BCC.
- **A value appears only when every reader of that message may see it.** A
  field hidden from any of them reads as empty; linked records are followed
  within each reader's record scope; a conditional block shows only when its
  condition holds for each of them. Addresses outside the system are rendered
  with the rule author's access and the default language.
- **Markup comes from the template only.** Values are escaped; `**bold**` is
  read from the template text, never from filled values, and a bold span that
  fills to nothing leaves no markers.
- **The email queue** (`email_queue`) holds every message: retries with
  backoff (`2^attempts` minutes) up to `notifications.email_max_attempts`,
  then `failed` with the SMTP reason; resend creates a new row linked to the
  old one. Delayed rules queue with `next_attempt_at`.
- **Attachments** — the record's files from chosen file fields, and documents
  generated per message for its readers in its language (ADR-0040). All
  messages of a delivery and their documents are prepared before any is
  queued, so a failed document fails the delivery as a whole and its retry
  sends nobody a second copy.
- **Previews** of rendered email (designer, Operations Center) are served from
  a short-lived signed link with their own Content-Security-Policy allowing
  inline styles only, inside a sandboxed frame; the application's own CSP
  stays nonce-based.
- **In-app notifications** follow per-user channel preferences unless the rule
  is marked as must-send.
- **Deferred to Phase 5:** delivery records for extra channels
  (`notification_deliveries`), as the ERD marks; Phase 4 has email and in-app.
