# Core Low-Code Framework

A low-code framework built with Laravel and Vue.js: administrators build business
systems from the UI without writing code. The contract is
[`docs/specification.md`](docs/specification.md); the design is
[`docs/architecture.md`](docs/architecture.md); status and resume point are in
[`docs/progress.md`](docs/progress.md).

## Run it with Docker

Requirements: Docker Engine 24+ with Compose v2.20+ (Docker Desktop on Windows and
macOS), Git, and about 6 GB of free disk space. Commands are shown for Linux/macOS
(bash) and Windows (PowerShell); run them from the repository folder.

**Windows:** clone with Git 2.10 or later. The repository's `.gitattributes`
checks every file out with LF line endings, which the Linux containers need. A
clone made before that file existed keeps CRLF files: delete it and clone again.

1. **Clone.**
   ```bash
   git clone https://github.com/moalmansor/Core-Low-Code-Framework.git
   cd Core-Low-Code-Framework
   ```
2. **Create the two configuration files** from their examples.
   ```bash
   cp .env.example .env                      # bash
   cp backend/.env.example backend/.env
   ```
   ```powershell
   Copy-Item .env.example .env               # PowerShell
   Copy-Item backend/.env.example backend/.env
   ```
3. **Choose passwords in `.env`** (any text editor): `DB_PASSWORD` and
   `DB_ROOT_PASSWORD`, e.g. `Lcf-Db-2026-pass` and `Lcf-Root-2026-pass`. Use
   letters, digits, and `- _ . ! @ % ^ * + =` only — not `$ # " ' \` or spaces.
   For SQL Server also set `MSSQL_SA_PASSWORD`, e.g. `Lcf-Sa-2026-Pass`: SQL
   Server requires at least 8 characters from three of upper case, lower case,
   digits, and symbols, and does not start otherwise.
4. **Put the same `DB_PASSWORD` in `backend/.env`.** Nothing else in that file
   needs changing for MySQL.
5. **Build the images** (first build: 5–15 minutes).
   ```bash
   docker compose build
   ```
6. **Generate the application key** and paste the printed value (it starts with
   `base64:`) after `APP_KEY=` in `backend/.env`.
   ```bash
   docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
   ```
7. **Start the stack.** The command returns once every service is healthy (the
   first start runs the database migrations: 1–3 minutes).
   ```bash
   docker compose up -d --wait
   ```
   Expected: every service is reported `Healthy` or `Running`. `docker compose ps`
   shows `app` and `web` as `(healthy)`.
8. **Get the one-time setup token.**
   ```bash
   docker compose exec app php artisan setup:token
   ```
   It prints a token like `3f9a1c-07be2d-4c1e88-a0b4f2`. Each run replaces the
   previous token.
9. Open **http://localhost:8080**, enter the token, and complete the setup wizard.

**SQL Server instead of MySQL:** in `backend/.env` set `DB_CONNECTION=sqlsrv`,
`DB_HOST=sqlserver`, `DB_PORT=1433`, `DB_USERNAME=sa`,
`DB_PASSWORD=<MSSQL_SA_PASSWORD>`, and `DB_TRUST_SERVER_CERTIFICATE=true` (the
container's certificate is self-signed; use a trusted certificate in production).
Then use `docker compose --profile sqlsrv up -d --wait` in step 7. The `lcf`
database is created with the `Arabic_100_CI_AI_SC` collation on first start.

**Troubleshooting:** `docker compose logs app` shows the start-up steps. `APP_KEY
is not set` means step 6 was skipped. `exec /usr/local/bin/lcf-entrypoint: no
such file or directory` means the files were checked out with CRLF: clone again
(see the Windows note). To start over with empty databases:
`docker compose --profile sqlsrv down -v`.

## Develop

- Dev container: open the repository in VS Code and "Reopen in Container".
- Backend: `cd backend && composer install && php artisan migrate --seed && php artisan serve`
- Frontend: `cd frontend && npm ci && npm run dev` (http://localhost:5173, proxies the API)
- Checks: `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `php artisan test`,
  `npm run lint`, `npm run typecheck`, `npm run test`, `npm run e2e`

Repository layout: `backend/` (Laravel, modules under `app/Modules`), `frontend/`
(Vue SPA), `docker/`, `docs/` (specification, architecture, ADRs, progress).
