# ADR-0070: Retention, legal holds, storage quotas and personal data as built

- Status: Accepted (Phase 6)
- Date: 2026-10-29

## Context

Specification §4.26 asks for:
- retention policies per data class;
- partitioned audit and error logs with an archive path and a restore procedure;
- table and volume growth warnings;
- storage quotas;
- personal-data location, export, deletion and anonymization;
- legal holds.

Architecture §19.16 and ADR-0014 (crypto-shredding) sketched the design. This ADR records the decisions taken while building it, including where the build departs from the architecture's wording.

## Decisions

### Policies and runs
1. **Scheduling.** A policy covers one data class. For records, the recycle bin and attachments it may be limited to one application or one form. Each policy runs on its own schedule, a `scheduled_tasks` row of kind `retention`, on the heavy queue. One run of a policy at a time.
2. **What each data class means:**

   | Data class | What it covers |
   |---|---|
   | `email_logs` | `email_queue` rows that are sent, failed or cancelled |
   | `notification_logs` | in-app notifications, plus channel deliveries that are finished |
   | `submission_journal` | processed and discarded entries |
   | `download_files` | finished download jobs and their files |
   | `attachments` | the contents of files uploaded to records (the file row stays, marked deleted) |
   | `recycle_bin` | deleted records still in the bin, counted from their deletion |

   The date basis (created, last changed, reached a final status) applies to records only.
3. **End actions:**
   - **Archive** writes JSON to the `archive` disk (`ARCHIVE_ROOT`, a separate volume in production) and lists it in `archived_records` with its SHA-256. A record is archived as one snapshot: main row, repeater rows, link rows, and file metadata plus file contents.
   - **Export then delete** keeps one JSON Lines file per run as the run's export.
   - **Delete** removes the data.

   Every record removed or archived gets its own audit entry, and every run gets one more.
4. **Restore.** An archived record is restored under its original id, with its rows, links and files. The restore refuses when:
   - the copy does not match the hash recorded at archive time;
   - a record with the same id or uuid exists;
   - the form is no longer published.

   Columns the form no longer has are left out. SQL Server needs `IDENTITY_INSERT` for this. That is engine-specific, so it lives in the driver layer (`insertKeepingIds`).

### Audit logs are archived, never removed
5. The architecture said archived audit partitions would be "switched out to an archive table". CLAUDE.md says audit entries are immutable and that no interface may delete one. Switching a partition out removes entries from the live table, so it is not built.
   - An audit-log policy may only archive. The server refuses any other end action.
   - Each whole month past the period is copied once, as JSON Lines plus a proof file: per chain, the first and last sequence and hashes, and the file's SHA-256.
   - Every entry's hash is recomputed before the copy is written. A month with a mismatch is not archived and the run fails, naming the month.
   - Entries stay in `audit_logs`.

   This conflict is raised in the Phase 6 pull request for the owner to decide.
6. **Error logs are deleted with chunked `DELETE`, not by dropping partitions.**
   - Because the logs are partitioned by month, each delete is local to a partition.
   - No partition DDL runs during a retention run.
   - New monthly partitions are added by the daily `partitions:maintain` task, which keeps twelve months ahead.

### Growth warnings
7. **Table growth** is a measure of the Operations Center's alert rules: `largest_table_mb`, the size of the largest table. The alert names the table. **Volume growth** was already a measure there (`storage_usage`). The Retention screen's Storage tab shows:
   - log rows per month;
   - the twenty largest tables;
   - the volume.

### Storage quotas
8. A quota applies to the organization, an application or a form, with a warning threshold and a hard limit.
   - Usage is measured nightly for the state shown and the audit entry when the state changes.
   - The hard limit is checked live: at upload, against the organization and, when the field is known, the form and its application; and again when a file is attached to a record. A quota can therefore never be overrun between two measurements.

### Legal holds
9. **Placing and lifting.** A hold covers one record or the records matching a condition, using the same safe SQL subset as custom record scopes. It is placed and lifted with a reason, and both are audited.
10. **The `legal_hold` flag** is recomputed when a hold changes, before every retention, recycle-bin or personal-data deletion, and nightly (`retention:maintain`, before the recycle-bin purge). The nightly run covers records that come to match a condition.
11. **Failing safe.** A condition that cannot be evaluated holds every record of its form.

### Personal data
12. **Lifecycle.** A request names the person by e-mail, identifier, name or user account.
    - The identifiers are stored encrypted, with a keyed blind hash for lookups.
    - Locating searches every published form's personal-data fields, using the blind index for encrypted fields. Encrypted fields without one are reported as not searchable.
    - It also searches the user's account and the logs that refer to them.
    - Nothing changes until an administrator approves the request with a reason, kept as an immutable justification of context `personal_data`.
    - Deleting or anonymizing also needs a fresh two-factor code.
    - The search runs again at approval, so the request acts on what exists then.
13. **Anonymize.**
    - Personal-data field values become irreversible random tokens. The write uses optimistic concurrency like every record write.
    - Personal fields in repeater rows are cleared.
    - Journal payloads lose the values.
    - The account is anonymized: identifying columns are cleared, it is disabled, its sessions are ended and its tokens revoked. It stays, because audit entries refer to it.
    - E-mail log addresses are replaced and bodies cleared.
    - Each record's subject key is destroyed.
14. **Delete.**
    - The records found are removed, with their journal entries, and their subject keys are destroyed.
    - The person's notifications and e-mails are deleted.
    - The account is anonymized.
    - A record still linked from another record stays and is reported.
15. **Records under legal hold are never changed by a request.** They are counted in the result.

### Crypto-shredding (ADR-0014)
16. **Sealing.** Values of personal-data fields are sealed with the record's subject key wherever an immutable log keeps them:
    - audit `changes`;
    - justification `changed_fields`;
    - merge snapshots.

    The key is random per record and kept wrapped with the application key. Values are sealed with XSalsa20-Poly1305 (libsodium secretbox).
17. **Reading sealed values.**
    - The record's history shows them in clear, because it already applies field permissions.
    - The general audit-log viewer and its export show «personal».
    - Once the key is destroyed, the values read «erased» everywhere.
    - The ciphertext, and so every chain hash, stays as it was. The chain still verifies after anonymization; a test checks this on both engines.

## Consequences

- Retention never removes an audit entry, so audit storage only grows. It is planned for by monthly partitions and the growth warnings, and offloaded by archive copies.
- Restores depend on the archive volume being backed up. The deployment guide covers this.
- Values recorded in audit entries before this change are not sealed retroactively. Sealing them would change the entries and break the hash chain. Anonymizing a person replaces the live values, but values in entries written before Phase 6 stay readable.
