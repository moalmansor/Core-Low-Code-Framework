# ADR-0050: API tokens, webhooks and inbound endpoints

- Status: Accepted (Phase 5)
- Date: 2026-10-16

## Context

Specification §4.22 asks for an automatic REST API with scoped tokens, rate limits and OpenAPI, and for incoming and outgoing webhooks with signing and delivery logs. §4.33 adds inbound API endpoints per form and incoming webhooks with payload mapping. All of it must keep the permission, workflow and audit rules of the application, and no outbound call may bypass the egress gateway.

## Decision

**Tokens**
- A personal API token is `lcf_` plus 48 random characters. Only its SHA-256 is stored. It is shown once.
- Each ability names one form and `read` or `write`. An ability is accepted only when the owner holds it at issue time: view for read; create, edit or delete for write. There is no wildcard.
- A token has a lifetime of at most `api.token_max_days`. It may have an IP allow-list and its own rate limit. Otherwise `api.token_rate_limit_per_minute` applies.
- A token is refused when it is revoked or expired, when its owner is inactive, from an address outside its list, or when `api.enabled` is off.
- Tokens use their own guard (`api-token`). That guard covers only `/api/v1/data/*`, `/api/v1/openapi.json` and API-kind inbound endpoints.
- Sanctum is told never to read a bearer token. The application's own endpoints take the session alone. A bearer header there answers 401, never a lookup.
- Every data API call goes through the same record and workflow controllers as the browser. The owner's permissions, record scope, field access, status rules, justification and optimistic concurrency all apply. Writes are journalled with source `api`.
- The OpenAPI 3.1 document is generated per token. It lists only the forms the token reaches, with only the fields the owner can see.

**Outgoing webhooks**
- A webhook subscribes to record created, updated, deleted and status-changed events, for one form or every form.
- Committed outbox events become deliveries. The payload is built as the webhook's owner (the last person who saved it) sees the record: their form permission, record scope and field access apply.
- If the owner cannot see the record, the delivery is logged as "blocked" and nothing is sent.
- Requests go through the egress gateway. They are signed as `X-LCF-Signature: sha256=HMAC(secret, X-LCF-Timestamp + "." + body)`.
- Network errors, 5xx and 429 are retried after 1, 5, 30, 120 and 360 minutes, up to the webhook's retry count.
- The log keeps a masked copy of each request: signature and custom header values are masked and sensitive fields are hidden. The full payload travels only in the encrypted queued job.
- Resending from the log rebuilds the payload from the current record.
- Failed and blocked deliveries appear in the Operations Center as "Webhook delivery". Log rows older than `api.webhook_delivery_days` are purged daily.

**Inbound endpoints**
- An endpoint lives at `POST /api/inbound/{slug}`. The address carries no organization, so the slug is unique across the installation.
- Kind `webhook` checks an HMAC (SHA-256 or SHA-512) of the raw body in a chosen header and acts as its run-as user. Choosing a run-as user other than oneself needs Manage permissions, as for automations.
- Kind `api` takes a personal API token with write access to the form and acts as the token's owner.
- The payload mapping takes each field's value from a dot path in the body. A path the body does not carry leaves the field unchanged.
- Operation `update` or `upsert` finds the record by a single-value text or number field, which must be mapped. More than one match is refused (409).
- The update uses the version read on arrival. A change saved in between is a conflict, never overwritten.
- Records go through the pipeline with journal source `inbound_webhook`. A created record starts in the workflow's initial status. The `initial_status_id` column stays unused: allowing another start status would bypass the workflow.
- Every call is logged as an incoming delivery, with the token and signature masked and sensitive fields hidden.
- Automations can listen to an endpoint through the new "inbound webhook" trigger. An endpoint with a listening automation cannot be deleted.
- Webhooks send the framework's JSON payload only. The `body_template` column stays unused until a template language for payloads is specified.

## Consequences

- The API cannot do more than its owner can do in the browser. Revoking a token or removing a permission takes effect on the next call.
- Calls that cannot be made, such as an address off the allowlist, fail visibly in the log and the Operations Center instead of silently.
