# Core Low-Code Framework

A low-code framework built with Laravel and Vue.js: administrators build business
systems from the UI without writing code. The contract is
[`docs/specification.md`](docs/specification.md); the design is
[`docs/architecture.md`](docs/architecture.md); status and resume point are in
[`docs/progress.md`](docs/progress.md).

## Run it with Docker

```bash
cp .env.example .env                  # choose DB_PASSWORD, DB_ROOT_PASSWORD (and MSSQL_SA_PASSWORD for SQL Server)
cp backend/.env.example backend/.env  # set APP_KEY (php artisan key:generate --show) and the same DB_PASSWORD
docker compose up -d --build
docker compose logs app | grep -A1 "setup"   # the one-time setup token
```

Open http://localhost:8080, enter the setup token, and complete the setup wizard.
For SQL Server: `docker compose --profile sqlsrv up -d` and set `DB_CONNECTION=sqlsrv`,
`DB_HOST=sqlserver`, `DB_PORT=1433` in `backend/.env`.

## Develop

- Dev container: open the repository in VS Code and "Reopen in Container".
- Backend: `cd backend && composer install && php artisan migrate --seed && php artisan serve`
- Frontend: `cd frontend && npm ci && npm run dev` (http://localhost:5173, proxies the API)
- Checks: `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `php artisan test`,
  `npm run lint`, `npm run typecheck`, `npm run test`, `npm run e2e`

Repository layout: `backend/` (Laravel, modules under `app/Modules`), `frontend/`
(Vue SPA), `docker/`, `docs/` (specification, architecture, ADRs, progress).
