---
paths:
  - 'bootstrap/**'
  - 'config/**'
  - 'routes/web.php'
  - 'app/Providers/**'
  - 'app/Support/**'
  - 'docker/**'
  - 'compose.yaml'
  - 'compose.prod.yaml'
  - '.github/**'
  - 'composer.json'
  - 'composer.lock'
  - 'package.json'
  - 'package-lock.json'
  - 'scripts/**'
---

# Platform: bootstrap, config, Docker, CI · owners @AleQuesada2012, @Rubiocai (CODEOWNERS)

- Changes here affect every module: open a `platform` issue first and get a platform owner's review.
- **Docker** (ADR-0012): `compose.yaml` for development (serversideup/php, PostgreSQL 18 on host port 5433, Mailpit, Reverb, Vite); `compose.prod.yaml` for the single AWS VM behind Caddy. One Dockerfile, `development` and `production` targets.
- **AWS deployment** (`docs/deployment.md`): `compose.prod.yaml` on one EC2 instance behind Caddy; PostgreSQL is the course's Amazon RDS instance (own `seti` database and user), not a container. Backups: RDS automated backups and the `storage` volume. The `pgsql` service exists only behind `--profile local-db`, for copies of the stack.
- **Color palettes** live in `resources/css/theme.css` (platform-owned); see `.ai/rules/frontend.md`.
- **Configuration** comes from `.env` (never committed). New settings get a key in `.env.example` (and `.env.production.example` when production needs it) with a comment.
- **CI** (`.github/workflows/ci.yml`) runs Pint, Larastan (level 5), Pest on PostgreSQL 18, `smoke-test.sql`, gitleaks, `composer audit` and the asset build; `pr-title.yml` checks Conventional Commits titles. A red check blocks the merge.
- `AppServiceProvider` holds the morph map (every `Auditable` model needs an alias), `Gate::before` for the Superusuario, strict models outside production and password defaults.
- Dependencies: Dependabot proposes updates weekly; a new package is a person's decision recorded in the PR description.
