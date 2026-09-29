# Development and production run the same Docker image family (serversideup/php), not Laravel Sail

`docker/php/Dockerfile` builds one image family on `serversideup/php` (PHP 8.5 FPM + NGINX on Debian, unprivileged user) with two targets: `development` (code bind-mounted, runs as the host user) used by `compose.yaml`, and `production` (code, vendor and built assets baked in) used by `compose.prod.yaml` on the single AWS VM. Development adds PostgreSQL 18 (host port 5433, because 5432 is often taken by a local install), Mailpit, Reverb, the queue worker, the scheduler and a Vite dev server. Production adds Caddy for automatic HTTPS and keeps PostgreSQL as a container on the same VM, reachable only from localhost.

## Considered Options

- **Laravel Sail**: the official path, but its images are meant for development only, so production would need a second, different setup.
- **Native PHP on each laptop**: possible (the README documents it), but five machines with three operating systems drift quickly; Docker is the reference environment for CI and demos.
- **A managed database (RDS) in production**: less to operate, but extra cost and network setup the course budget does not need yet; the database stays portable (PostgreSQL ≥ 16 + contrib extensions).

## Consequences

Commands run inside the `app` container (`docker compose exec app php artisan …`). Linux and WSL2 users set `UID`/`GID` in `.env` so files created in containers stay theirs. Vite bakes `VITE_*` values at image build time, so the production build receives them as build arguments. Backups cover two Docker volumes on the VM: `pgsql-data` and `storage`.
