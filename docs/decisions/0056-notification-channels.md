# ADR-0056: SMS and messaging notification channels

- Status: Accepted (Phase 5)
- Date: 2026-10-23

## Context

Specification §4.33 asks for notification channels beyond email — in-app, SMS and messaging providers — configured as channels, selectable per notification rule, with per-user channel preferences and the same template, placeholder and logging treatment as email. Every integration must appear in the Operations Center with its failures, retries and delivery history. All outbound HTTP goes through the egress gateway.

## Decision

**Channels**
- A channel is an SMS or messaging provider with a key, a translatable name, provider settings, an optional per-minute limit and an on/off switch. Email and in-app stay built in and are not rows.
- Providers:
  - SMS: Twilio (Messages API, basic auth) or any HTTPS endpoint.
  - Messaging: Slack or Microsoft Teams incoming webhooks, or any HTTPS endpoint.
- For an HTTPS endpoint the admin writes a JSON body template. `{{to}}`, `{{title}}`, `{{text}}` and `{{link}}` stand as whole JSON values and are filled in as JSON strings, so a value can never change the template's structure. A template that is not JSON once filled is refused.
- Provider settings are stored encrypted. Secrets (auth token, webhook address, header value) are write-only: the API returns only whether one is set, and an empty field on save keeps the saved one. Addresses must be `https://` and on the egress allowlist.
- Managing channels needs "Manage integrations", because they hold credentials. People who build notification rules ("Manage notifications") can list active channels to choose them.
- A channel used by a rule cannot be deleted, and its key cannot change. A channel with a delivery log is switched off and renamed instead of removed, so the log keeps its channel.

**Sending**
- A rule's channels are `email`, `in_app` and the keys of active channels; unknown keys are refused.
- SMS goes to the mobile number of each user recipient who has one (international format). It is rendered in that person's language with their field access. People can turn SMS off in their profile; mandatory rules (those that ignore preferences) still reach them.
- A messaging channel posts once per notification to its conversation. It is rendered in the default language with the rule author's access, like a message to an outside address.
- The text is the email template's subject and preheader with the record link. Without a template, it is the rule's default title. This keeps one template, one set of placeholders and one access model for every channel.
- Built-in notices (work assigned, SLA escalations) stay in-app and email.

**Delivery and log**
- Each message is a `notification_deliveries` row sent by a queued job on the `mail` queue.
- A failure that may pass (timeout, 5xx, 429) is retried after 1, 5 and 30 minutes, four attempts in all. A refusal (4xx, egress violation) fails at once.
- When the channel's per-minute limit is reached, the message waits for the next minute without spending an attempt.
- Failed messages appear in the Operations Center ("SMS or messaging delivery") and can be sent again there or from the channel's delivery log. The log shows SMS numbers masked except for the last four digits.
- Scheduled reminders also check this log, so a rule that only uses SMS or messaging is not reminded twice.

## Consequences

- New admin area "SMS and messaging channels" (Configuration).
- The profile gains "By text message (SMS)".
- Testing a channel sends one real message with the saved settings and records the outcome in the audit log.
