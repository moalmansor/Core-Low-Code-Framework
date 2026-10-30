# ADR-0071: Per-organization data keys and the key rotation procedure

- Status: Accepted (Phase 6)
- Date: 2026-10-29

## Context

Specification §5 asks for "per-tenant and per-field key management with a documented key rotation procedure". Architecture §7.5 designed an envelope scheme over the `encryption_keys` table. The Phase 6 security review found that the table had never been created, that encrypted fields used the application key directly, and that no rotation procedure existed.

## Decision

1. **Data keys** (`App\Infrastructure\Crypto\DataKeys`):
   - Each organization has one active data key per purpose. The purposes in use are `fields` (encrypted field values) and `blind_index` (their exact-match search index).
   - A key is 32 random bytes, stored wrapped with `APP_KEY`.
   - Values are AES-256-GCM, stored as `v1:{keyId}:{base64(iv ‖ tag ‖ ciphertext)}`, so every ciphertext names its key.
   - Keys have three states: `active`, then `retiring` while data still uses them, then `retired`. A retired key stays in the table because backups may need it.
2. **Compatibility.** A value written before data keys existed (a plain Laravel ciphertext) still opens; `keys:rotate` moves it. Blind indexes keep the application-key HMAC until the organization's first `blind_index` rotation, so existing searches keep working on upgrade.
3. **`keys:rotate [--organization=] [--purpose=fields|blind_index]`:**
   - It creates a new active key and re-encrypts every encrypted value (or recomputes every blind index) of the organization's published forms, main and repeater tables alike.
   - It then retires the old key.
   - Updates are compare-and-swap on the old ciphertext, so a value saved meanwhile is never overwritten.
   - Each rotation is audited (`security.key_rotated`).
4. **`keys:rewrap`.** This is the master-key rotation: after the old `APP_KEY` is moved to `APP_PREVIOUS_KEYS`, it re-seals under the new key everything sealed with the master key:
   - wrapped data keys and subject keys;
   - secret settings and two-factor secrets;
   - webhook, inbound, channel, data source and action-step secrets;
   - journal payloads, merge snapshots and personal data request subjects.

   It also clears the settings cache, which holds secret settings still sealed. The old key can be removed once it has run, except for pre-publish schema snapshots, which expire on their own. It is audited (`security.master_key_rewrapped`).
5. **External KMS.** `kms_key_ref` is kept, unused, for an external KMS. Wrapping with `APP_KEY` is the supported mode. A KMS would replace only the wrap and unwrap of data keys.

## Consequences

- Compromising one organization's data key exposes only that organization's encrypted values, and keys can be rotated per organization without downtime.
- Rotating blind indexes briefly makes exact search on encrypted fields miss rows not yet recomputed. The guide says to run it in a maintenance window.
- The procedure is documented in `docs/guides/deployment.md` and tested on both engines (`tests/Engine/KeyRotationTest.php`).
