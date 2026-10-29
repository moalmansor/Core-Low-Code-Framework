# ADR-0057: External data sources and sync jobs

- Status: Accepted (Phase 5)
- Date: 2026-10-23

## Context

Specification §4.33 asks for external data sources defined in the UI — a REST endpoint or a database view, with authentication, headers, a response mapping and a cache policy — that feed select options, lookups, validation and read-only panels exactly like a collection. It also asks for sync jobs: scheduled import from, or export to, an external source, with field mapping, matching keys, conflict rules, dry run and a run log. The architecture (§10.20, §19.21) fixes the tables and says a database connection is defined in the environment, so no credentials are kept in the database.

## Decision

**Data sources**
- REST:
  - an `https://` address on the egress allowlist, called through the egress gateway;
  - GET or POST, query parameters, a JSON body template for POST;
  - the path to the list of records in the response.
  - `{q}` and `{depends}` in the address or parameters (and `{{q}}`/`{{depends}}` in the body) carry the search text and the parent value, for APIs that filter on their side.
- Authentication: none, API key in a header, bearer token, basic, or OAuth 2.0 client credentials. The token is fetched through the gateway and cached until shortly before it expires.
- Credentials and extra headers are encrypted and write-only, as for notification channels.
- Database view:
  - each name in `LCF_EXTERNAL_DB_CONNECTIONS` becomes a connection `external_<name>`, configured by `LCF_EXT_<NAME>_*` environment variables (MySQL, SQL Server or PostgreSQL);
  - the admin picks the connection and names the view; the view and every mapped column are checked against the connection's catalogue when saved;
  - queries use the query builder only, so nothing typed reaches SQL as text.
  - The installation is told to use an account that may only read those views.
- Mapping: each record maps to a value (stored), a label (shown), an optional parent (for cascading) and up to 20 extra columns with translatable labels. REST paths are dotted paths into each record; view mappings are column names.
- Cache:
  - none — every call goes to the source;
  - for a while — TTL;
  - stale-while-revalidate — expired rows are still served while a queued job refreshes them.
  - At most 5,000 rows are read from a source.
- "Test" calls the source now, without the cache, and shows the first mapped rows and the first record as received.
- Failures are recorded for the Operations Center ("External data source") at most once every five minutes per source, so a search typed against a source that is down does not flood the audit log.

**Using a source**
- Choice fields can take their options from a source (option source `external`). The options endpoint searches, cascades by the parent and pages, as for collections.
- On save, a value that is not one of the source's is refused. A source that cannot be read blocks only a new value; a value the record already had is not checked again.
- Records show the source's labels for stored values, read from the cache. A source that is down shows the stored values as they are.
- A View Mode panel "External data" lists the source's rows whose chosen column equals a field of the record. The field is read with the viewer's field access, so a field they cannot see shows nothing. This adds `external_table` to the panel types (migration `allow_external_table_panels`).
- A source used by a form, a panel or a sync job cannot be deleted, and its key cannot change.

**Sync jobs**
- Import writes rows into a form through the record pipeline as the job's run-as user, journaled with source `sync`. Permissions, field access, rules, validation, workflow and audit apply as in the interface.
- Each form field takes the source's value, label, parent or a column. Ticked fields identify the record.
- Conflict rules for a row that matches an existing record:
  - the source wins;
  - the record wins — only empty fields are filled;
  - the newest wins — needs a source column with the row's last change, compared with the record's;
  - skip — existing records are left alone.
- A row that changes nothing is skipped, so no new version is written.
- Export sends each record the run-as user may see, changed since the last successful run, as JSON to an address on the egress allowlist, with the source's authentication. Each field is sent under a property name the admin chooses (dots make nested objects), and only fields the run-as user may see are sent. A database view cannot receive records.
- A dry run of an import does everything inside a transaction that is rolled back, so its counts and errors are those a real run would have. A dry run of an export sends nothing.
- Every run keeps a CSV log of every row, with formula-like cells neutralized. The log can be downloaded from the job.
- Jobs run on a cron schedule through the scheduler (kind `sync`) or on demand, one real run at a time.
- Failed and partly failed runs appear in the Operations Center and can be run again there.
- The export target and the matching settings are kept in the job's `field_mapping` and `match_keys` JSON columns (`{fields, export}` and `{fields, updated_from}`), so the ERD's columns need no change.

## Consequences

- Integrations gains the tabs "Data sources" and "Sync jobs"; the options editor gains "External data source"; the panel editor gains "External data".
- `.env.example` documents `LCF_EXTERNAL_DB_CONNECTIONS`.
