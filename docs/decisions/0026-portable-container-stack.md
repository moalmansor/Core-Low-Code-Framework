# ADR-0026: A container stack that runs from any host's checkout

- Status: Accepted (Phase 1, from the owner's review of the Phase 1 pull request)
- Date: 2026-10-03

## Context

The owner ran the Phase 1 stack from a clean clone on Windows. The `app`,
`worker`, and `scheduler` containers crash-looped with
`exec /usr/local/bin/lcf-entrypoint: no such file or directory`. Git for Windows
checks text files out with CRLF (`core.autocrlf=true`), so the entrypoint's first
line became `#!/bin/sh\r` and the kernel could not find the interpreter `/bin/sh\r`.
A checkout made with `core.autocrlf=true` gave CRLF to 105 of the 343 tracked
files. CI ran only on Linux checkouts, and no job started the Compose stack, so
all checks passed while the stack could not start.

The same run exposed further defects in the stack and its guide:
- `docker compose up -d` tried to pull `lcf/app:local` from Docker Hub
  ("pull access denied") on Compose versions that pull before building.
- Compose refused to start without `MSSQL_SA_PASSWORD`, even for MySQL, because
  required variables are checked in every service, including disabled profiles.
- There was no `.dockerignore`, so a `backend/.env` created before the build
  (holding `APP_KEY` and passwords) was copied into the image.
- nginx served `public/` from a named volume filled once from the first image,
  so a rebuilt image would serve stale assets against a new manifest.
- A missing `APP_KEY` failed late, without saying how to create one, and the SQL
  Server path needed a manual `CREATE DATABASE` with the right collation.

## Decision

1. **Line endings:** `.gitattributes` sets `* text=auto eol=lf` (CRLF only for
   `.bat`, `.cmd`, `.ps1`; binaries marked binary), so every checkout gets LF on
   every OS. As a second layer, the Dockerfile strips `\r` from the entrypoint
   before making it executable.
2. **Images are built, never pulled:** `app` and `web` have
   `pull_policy: build`; `worker` and `scheduler` reuse the app image with
   `pull_policy: never`. The plain `docker compose up -d` works without `--build`.
3. **Health and order:** `app` is healthy once the entrypoint has prepared the
   database and php-fpm listens; `web` is healthy once `/up` answers through
   php-fpm. `worker`, `scheduler`, and `web` start only after `app` is healthy, so
   the documented `docker compose up -d --wait` returns when the stack works.
4. **No secrets in images:** `.dockerignore` excludes `.env` files, `.git`,
   dependencies, build output, and runtime state. Configuration reaches the
   containers at run time through `env_file`.
5. **Assets:** a `web` image (nginx) is built from the same Dockerfile, with the
   app stage's `public/` copied in; the shared volume is removed.
6. **Start-up checks:** the entrypoint stops with instructions when `APP_KEY` is
   empty, and `php artisan db:ensure` creates the configured database with the
   collation of architecture §9 when it is missing (never altering or dropping).
   SQL Server's password is required only when its profile is used.
7. **CI:** a `line-endings` job fails when anything is committed with CRLF or
   when a `core.autocrlf=true` checkout would give any file CRLF. A `stack` job
   (MySQL and SQL Server) starts from such a checkout, follows the README guide
   command by command, waits for health, asserts that no container restarted and
   that the image holds no `.env`, and runs the Playwright suite against the
   running stack through nginx.

## Consequences

Windows, macOS, and Linux hosts run the same stack with the same commands. GitHub
Actions has no Linux containers on Windows runners, so CI reproduces the Windows
checkout on Linux instead of running Docker Desktop; the remaining Windows-only
difference is the shell (PowerShell), which the guide covers command by command.
A clone made before `.gitattributes` keeps its CRLF files and must be re-cloned.
