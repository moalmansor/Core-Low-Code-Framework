# Deployment, backup and restore guide

This guide is for the people who run the framework: installing it, sizing it,
backing it up, restoring it, rotating its keys and upgrading it. Building
business systems is in the [administrator guide](admin.md). Extending the
framework with code is in the [developer extension guide](developer-extensions.md).

## 1. What runs

The Docker stack (`docker-compose.yml`) has these services:

| Service | Image | Role |
|---|---|---|
| `web` | `lcf/web:local` (nginx) | Serves the single-page app and passes `/api` to `app`. Port 8080. |
| `app` | `lcf/app:local` (PHP-FPM 8.3) | The API. On start it migrates the database and caches the configuration. |
| `worker` | same image | Horizon: queued jobs (imports, downloads, publishing, mail, webhooks, automations). |
| `scheduler` | same image | `schedule:work`: every recurring task (§3 below). |
| `mysql` or `sqlserver` | MySQL 8.4 / SQL Server 2019 | The database. SQL Server is started with `--profile sqlsrv`. |
| `redis` | Redis 7 | Cache, sessions' rate limiters, queues. |
| `clamav` | ClamAV | Virus scanning of uploads. |

The first installation follows the README ("Run it with Docker"). This guide
assumes the stack is installed and the setup wizard completed.

## 2. Production configuration

Set these in `backend/.env`. Settings an administrator can change (mail,
security, retention and so on) live in **System Settings**, never here.

- **`APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`.** The public address. Sign-in only works from the domains listed in `SANCTUM_STATEFUL_DOMAINS`.
- **HTTPS.** Put a TLS-terminating proxy in front of `web`. Set `APP_URL` to the `https://` address so session cookies are marked secure. Set `TRUSTED_PROXIES` to the proxy's address so client addresses (rate limits, access policies, audit) are read from `X-Forwarded-For`. Never use `*`.
- **`APP_KEY`.** The master key: it wraps every organization's data keys and seals secret settings and two-factor secrets. Keep a copy in your secrets store. Without it, nothing encrypted can be read again, from the database or from a backup.
- **`HORIZON_HEAVY_PROCESSES`.** Worker processes for long jobs (default 4). A large import spreads its 500-row batches over them (ADR-0072). Size it to the worker's CPUs.
- **`PHP_FPM_MAX_CHILDREN`, `PHP_FPM_START_SERVERS`, `PHP_FPM_MIN_SPARE`, `PHP_FPM_MAX_SPARE`, `PHP_FPM_MAX_REQUESTS`.** These size the PHP-FPM pool. Defaults: 32, 8, 4, 16 and 1000. Each child needs about 60–100 MB.
- **`ARCHIVE_ROOT`.** Where retention archives are written. The default is `storage/app/archive`, inside the `files` volume. Point it at separate, durable storage if archives must outlive the server.
- **OPcache JIT.** Keep it off (`docker/php/php.ini`, ADR-0069).

### Sizing

Architecture §20.1 is the reference environment the performance budgets are
set for:
- 2 application containers (4 vCPU, 8 GB each);
- 1 worker (4 vCPU, 8 GB);
- Redis (2 vCPU, 4 GB);
- a database server with 8 vCPU, 32 GB and SSD storage.

That environment holds 1 000 000 records in the largest form, 200 forms and
5 000 users, and serves 500 concurrent active users. The measured results are in
[`docs/performance.md`](../performance.md).

Give the database its memory:
- **MySQL**: set `MYSQL_BUFFER_POOL_SIZE` (root `.env`, default `1G`; the stack
  passes it as `--innodb-buffer-pool-size`) to about half to two thirds of the
  memory the database host can spare: `20G` on the reference server. MySQL's own
  default, 128 MB, sends most reads of a large form to disk.
- **SQL Server** takes memory as it needs it; on a host shared with other
  services, cap it with `max server memory`.

## 3. Recurring tasks

The `scheduler` service runs them; only one scheduler may run. Notable ones:

| When | Task |
|---|---|
| every minute | SLA timers, work items, scheduled automations, the outbox relay, due e-mail |
| hourly | expiry of temporary files, exports, documents and downloads |
| 00:20 daily | `partitions:maintain`: adds the next months' partitions to `audit_logs` and `error_logs` |
| 01:40 daily | `schema:reconcile`: compares every form's tables with its definition |
| 02:10 daily, Sunday 03:10 | `audit:verify` (new entries), `audit:verify --full` (every chain from the start) |
| 03:05 daily | `retention:maintain`: retention policies, legal-hold recomputation |
| 03:25 daily | recycle-bin purge |

The Operations Center shows the scheduler's lag. An alert rule can watch it.

## 4. Backup

A complete backup has four parts. Without all four, a restore is incomplete.

1. **The database.**
2. **The `files` volume:** uploads, generated documents, schema snapshots and retention archives under `ARCHIVE_ROOT`.
3. **`backend/.env`**, above all `APP_KEY` and `APP_PREVIOUS_KEYS`. Keep it in your secrets store, separate from the data backups.
4. **The `.env`** of the compose folder (database passwords).

Redis holds caches, rate limiters and queues only. It does not need a backup;
jobs still queued are recovered by their retry and the scheduled outbox relay.

### MySQL

```bash
docker compose exec -T mysql sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --set-gtid-purged=OFF "$MYSQL_DATABASE"' | gzip > lcf-$(date +%F).sql.gz
```

`--single-transaction` gives a consistent copy without stopping the
application. The monthly partitions of `audit_logs` and `error_logs` are
included.

### SQL Server

```bash
docker compose exec sqlserver bash -c '/opt/mssql-tools18/bin/sqlcmd -C -S localhost -U sa -P "$MSSQL_SA_PASSWORD" -Q "BACKUP DATABASE lcf TO DISK = N'"'"'/var/opt/mssql/lcf.bak'"'"' WITH COMPRESSION, INIT, CHECKSUM"'
docker compose cp sqlserver:/var/opt/mssql/lcf.bak lcf-$(date +%F).bak
```

(Older SQL Server images have `sqlcmd` under `/opt/mssql-tools/bin` instead.)

### Files

```bash
docker compose run --rm --no-deps -T --entrypoint tar app czf - -C /var/www/backend/storage/app . > lcf-files-$(date +%F).tgz
```

Take the files copy right after the database copy. A file written in between
is harmless: it is an orphan the data-repair scan reports, not a broken
reference.

### Checking a backup

Restore it regularly into a separate stack (§5) and run
`php artisan audit:verify --full` there. A backup you have never restored is
not known to work.

## 5. Restore

1. **Stop everything that writes:** `docker compose stop app worker scheduler web`.
2. **Restore the database.**
   - MySQL:
     ```bash
     gunzip -c lcf-2026-10-29.sql.gz | docker compose exec -T mysql sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
     ```
   - SQL Server: copy the `.bak` file into the container with `docker compose cp`, then run `RESTORE DATABASE lcf FROM DISK = N'/var/opt/mssql/lcf.bak' WITH REPLACE` through `sqlcmd`.
3. **Restore the files:**
   ```bash
   docker compose run --rm --no-deps -T --entrypoint tar app xzf - -C /var/www/backend/storage/app < lcf-files-2026-10-29.tgz
   ```
4. **Put back `backend/.env`** with the `APP_KEY` (and `APP_PREVIOUS_KEYS`) of the backup.
5. **Start:** `docker compose up -d --wait`. The app migrates the database forward if the backup is from an older release.
6. **Verify:**
   - `docker compose exec app php artisan audit:verify --full`: every chain intact.
   - `docker compose exec app php artisan schema:reconcile`: every form's tables match its definition.
   - Then open the Operations Center (health, failed jobs).

### Restoring one record

Restoring a whole backup to bring back one record is never needed:

- **Recycle bin.** A deleted record stays in the form's recycle bin until its retention period ends.
- **Archive.** An archived record is restored from **Admin Console → Retention and personal data → Archive**, under its original id (§4.26).

## 6. Key rotation

Run every command in the `app` container (`docker compose exec app php artisan …`).

### An organization's data key (yearly, or when a key may be exposed)

```bash
php artisan keys:rotate --organization=<id>                       # encrypted field values
php artisan keys:rotate --organization=<id> --purpose=blind_index  # exact-search index of encrypted fields
```

- Without `--organization` the command rotates every organization.
- Values are moved to the new key compare-and-swap, so a record saved during the rotation is never overwritten; the next run picks it up.
- The old key is retired, but kept: backups taken before the rotation still need it.
- Run the `blind_index` rotation in a maintenance window. Until it finishes, an exact search on an encrypted field can miss records not yet moved.

### The master key (`APP_KEY`)

1. Generate a new key: `php artisan key:generate --show`.
2. In `backend/.env`, set `APP_KEY` to the new key. Move the old key into `APP_PREVIOUS_KEYS` (comma-separated, newest first).
3. `docker compose up -d --wait`: restart app, worker and scheduler with the new configuration.
4. `php artisan keys:rewrap`. This re-seals everything sealed with the master key: data keys, subject keys, secret settings, two-factor secrets, integration and webhook secrets, journal payloads and merge snapshots. It then clears the settings cache.
5. Keep the old key in `APP_PREVIOUS_KEYS` until the schema-snapshot retention has passed. Snapshots are files sealed with the key that was current when they were made, and `keys:rewrap` does not re-seal them. Backups taken before step 4 also need the old key.

Both commands write an audit entry (`security.key_rotated`, `security.master_key_rewrapped`).

## 7. Upgrades

1. Take a backup (§4).
2. `git pull`, then `docker compose build`.
3. `docker compose up -d --wait`. The `app` container runs the new migrations before it reports healthy. Additive schema changes are online on both engines.
4. Read the release's CHANGELOG entry for anything to do by hand.

## 8. Monitoring

- **Health:** `GET /up` (used by the container health checks) and the Operations Center's health panel.
- **Alerts:** Operations Center → Alerts. Rules can watch:
  - failed submissions, jobs, e-mail, webhooks and integrations;
  - queue size;
  - error rate;
  - storage use;
  - the largest table;
  - scheduler lag.
- **Errors:** Error Monitoring lists every unexpected error with its reference. A person who sees an error is shown only that reference.
- **Performance:** the `performance.yml` workflow reproduces the §20 budgets against the reference dataset. Run it after changing the infrastructure.
