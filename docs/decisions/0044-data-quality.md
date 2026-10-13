# ADR-0044: Data quality — duplicate rules, merge, bulk tools, repair and recycle-bin retention

- Status: Accepted (Phase 4)
- Date: 2026-10-13

## Context

§4.35 asks for:

- duplicate detection with admin-defined rules, applied while saving and as a sweep;
- a record merge with field-by-field choices, repointed links and an audited justification;
- bulk update, reassign and status change with a preview, a dry run, a maximum-count guard and a justification;
- data repair tools;
- a recycle bin with retention.

The ERD fixes `duplicate_rules`, `merge_history`, `bulk_operations` and `recycle_bin`. Several details were open.

## Decision

### Duplicate rules

- Each form has its own list of rules, saved whole with `base_hash` from the form's **Data quality** tab ("Manage forms").
- A rule compares one or more main-form fields of a comparable kind: text, phone, numbers, dates, choices, and links to records, users or departments. Fields inside repeating groups and file fields cannot be compared.
- Each field is compared in one of three ways:
  - **exactly**;
  - **normalized**, which ignores case, spacing, punctuation, Arabic letter forms (alef, ya, ta marbuta), diacritics and Arabic-Indic digits;
  - **fuzzy**, a character similarity from 0.50 to 1.00 (default 0.85).
- A rule matches when **all** of its fields match, or **any** of them, as configured.
- A rule applies on create, on update, or on both. It can be turned off without being deleted.

### While saving

- A **block** rule refuses the save from every source: screen, API, import, automation and action.
- A **warn** rule asks only where someone can answer, which is a save from a screen or through the API.
  - The answer is a 409 `duplicate` listing the likely duplicates the person may view. The others are only counted.
  - The save is sent again with `confirm_duplicates`.
  - Saves nobody is watching go ahead: imports, automations and actions.
- Checking while saving compares with at most `data_quality.duplicate_scan_limit` recent records (default 20,000). This is a System Settings value, not code.

### Sweeps

- A sweep checks one rule against every live record in a background job.
- It groups the matches using exact keys, blocking and union-find, and keeps the result on a `bulk_operations` row of type `duplicate_sweep`.
- Groups list only the records the viewer may view.

### Merge ("Merge records")

- A survivor and up to ten others are merged at once.
- The person must be able to:
  - view every record;
  - edit the survivor;
  - delete the others;
  - see each record at the version they saw. Otherwise the merge is refused with a 409.
- Every field that differs gets a chosen value. File fields are not chosen: files stay with the record they were uploaded to.
- A justification is always required (mandatory, at least the baseline). A stricter "merge" justification rule of the form wins.
- In one transaction, the merge:
  1. moves links from the other records (single and multiple links, including those inside repeating groups) to the survivor;
  2. deletes the others into the recycle bin through the record pipeline;
  3. saves the chosen values on the survivor through the pipeline, so validation, access and concurrency apply. A block rule still stops a merge that would duplicate a third record.
- `merge_history` keeps the snapshots, the choices and the repointed links. The snapshots are encrypted when the form has sensitive or encrypted fields. The merge is audited as `record.merged`.
- Merging starts from the records list (select 2 to 11, then "Merge selected") or from a sweep group.

### Bulk tools

- Bulk update, bulk status change, bulk delete and the new **bulk reassign** are built-in actions (§4.15). They are configured per form, with their permission, maximum count, background threshold and justification level.
- Bulk reassign assigns every record to a user, role or department. It needs "Assign records" or "Reassign records" (when the record already has an active assignment), the ability to edit the record, and any "reassign" justification rules.
- **Preview:**
  - Preparing a run returns the count and names the first 20 records.
  - A run over more records than the maximum is refused (the maximum-count guard). There is no override: a larger change is split or the maximum raised by an admin.
- **Dry run ("Try first"):**
  - The bulk built-ins can be tried without keeping anything.
  - Each of the first 200 records runs through the record pipeline (access, record scope, validation, concurrency, legal hold) in a transaction that is rolled back.
  - The answer is how many would change, how many would not, and why.
  - A record that lacks only the justification counts as one that would change, because the justification is asked once when the run starts.
  - The dry run is kept as a `bulk_operations` row with `dry_run = true` and audited as `action.dry_run`.
  - Custom actions have no dry run, because their steps can reach outside the system (e-mail, webhooks).

### Repair ("Repair data")

- Two background scans run per form from the Data quality tab.
- **Broken links** finds records linking to a record that is deleted or gone.
  - A link in a main-form field is cleared from the screen. The clear is a normal save, so it is validated, journaled and audited.
  - A broken link inside a repeating group is listed for correction by hand.
- **Records that break the rules** runs the form's current validation over up to 50,000 records. Each finding opens the record to correct it.
- Findings are capped at 1,000 per scan, and the screen says when there are more.

### Recycle bin

- Deleting a record notes it in `recycle_bin` with a title and a purge date. The purge date is the delete time plus `data_quality.recycle_bin_days` (System Settings, default 90).
- The recycle-bin list shows the purge date. Restoring the record before then is audited.
- The daily `records:purge-recycle-bin` command (03:25) removes records whose date has passed and audits `record.purged`. A record under legal hold, or one that other records still link to, is kept until the hold is lifted or the links are gone.
- Purged entries stay in `recycle_bin` as history.

### Titles

- Where a record is listed by itself (duplicates, sweep groups, merge, repair findings, previews), it is named by its number, or else by its first non-empty text value.
- Encrypted values are never used for this, and identifiers are never shown.

## Consequences

- Duplicate checks add a bounded query per save only on forms with active rules.
- A dry run holds row locks for each record only for the length of its rolled-back transaction. It is capped at 200 records so a request stays short.
- Merges cannot be undone automatically. The history and the recycle bin keep everything needed to restore the merged records by hand within the retention period.
