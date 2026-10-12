# ADR-0042: Custom downloads — paths, one-to-many handling, security and scheduling

- Status: Accepted (Phase 4)
- Date: 2026-10-12

## Context

§4.23 asks for download profiles that reach linked forms "at unlimited
depth", in both directions, with flattening, aggregates or separate sheets
for one-to-many paths, field- and record-level security at every level,
masked sensitive fields, parameters, Excel/CSV/PDF output, background runs,
personal profiles and scheduled downloads. Several details were open.

## Decision

- **Paths.** A column path is a list of steps: a reference field key
  (to one record, or many through a multi-reference); `~{form uuid}.{field
  key}` for the records of another form that point back (child records);
  `#{repeater key}` for a repeating section's rows (last hop only); and a
  last step that is a field key or a system value (`@record_number`,
  `@status`, `@created_at`, `@created_by`, `@updated_at`, `@updated_by`,
  `@last_transition_at`). "Unlimited depth" is bounded by the setting
  `downloads.max_depth` (default 5, up to 10) so a cyclic link cannot make a
  tree or a query unbounded; the builder opens one level at a time.
- **One-to-many.** A path is "several" from its first multi-reference,
  child-record or repeater hop. Each such column chooses: one row per
  related record (all such columns of a profile must share that first hop,
  so rows line up), one value (count, sum, average, min, max, first, last, or
  all values joined with a separator), or a separate sheet (columns with the
  same sheet name share the hop; each sheet row starts with the base
  record's number). CSV has one sheet, so separate sheets are written to
  Excel and PDF only; the builder says so.
- **Security at every level.** A column is kept only when every hop is a
  form the user may view and a field they can see; values come only from
  records within the user's record scope at each level. Sensitive and
  encrypted fields are masked unless the user holds the new permission
  `field.{uuid}.view_sensitive` (registered for such fields when the draft
  is saved; granting it needs step-up). A calculated column is omitted for
  a user who cannot see every record field its formula reads, since
  formulas are evaluated on the whole record. Personal profiles may not
  contain formulas (their owner builds them from what they can see; the
  server refuses formulas there). A fixed filter the user cannot apply (a
  hidden field) excludes everything rather than widening the result.
- **Runs.** Every run is a `download_jobs` row, planned again under the
  user's permissions when it runs. Up to `downloads.sync_rows` (default 5
  000) base records it is produced in the request; larger runs go to the
  `heavy` queue with progress and a notification. Rows are read in id order
  in batches; values per hop are loaded in batches, never per row. The row
  limit is `downloads.max_rows` (default 100 000) or the profile's lower
  limit. Files are personal and expire after `records.job_file_hours`;
  every run is audited with who, profile, format, filters, parameters, row
  count and duration.
- **Excel details.** OpenSpout does not write images, so the logo is added
  to the finished workbook as a drawing; numeric columns with a number,
  currency or percent format in Latin digits stay numbers under an Excel
  number format, others are written as formatted text.
- **Scheduler.** A generic scheduler runs `scheduled_tasks` (cron and time
  zone, atomic claim with `claimed_until`, last run, status, duration and
  error), driven by `scheduler:run` every minute; modules register a handler
  per kind. Automations will use the same scheduler. A download schedule is
  daily, weekly or monthly at a time (stored as a cron expression), or a
  cron expression. It runs once per recipient (users and members of roles)
  under that recipient's own permissions; a recipient who may not use the
  profile is skipped and the skip audited. The file is attached when it is
  at most `downloads.email_attachment_mb` (default 10 MB); otherwise the
  email links to the run inside the application, which needs sign-in.
  Personal profiles are only ever sent to their owner. Required parameters
  must be given values when a schedule is saved.
- **Actions.** The `run_download` step produces a shared profile's download
  for the record (record-page profiles only) under the runner's
  permissions.

## Consequences

- Downloads never show more than the downloading user could see on screen,
  at any depth, and scheduled ones never run under a shared identity.
- Very deep or very wide profiles stay bounded by settings an administrator
  controls.
