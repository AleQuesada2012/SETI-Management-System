-- Empties the application's database for the handover (docs/deployment.md § 12): drops the `seti` database and
-- creates it again, empty, as create-app-database.sql did. EVERYTHING in it is lost: users, assets, spaces,
-- reservations, the audit log. The `seti` user and its password stay.
--
-- The application blocks `php artisan migrate:fresh` in production on purpose, so this is the deliberate way. Stop
-- the stack first (no open connections), then, as the RDS master user, from the repository folder:
--   docker compose -f compose.prod.yaml down
--   psql -v ON_ERROR_STOP=1 -h <rds-endpoint> -U postgres -d postgres -f database/sql/ops/reset-app-database.sql

DROP DATABASE seti;

CREATE DATABASE seti OWNER seti TEMPLATE template0 ENCODING 'UTF8' LOCALE_PROVIDER icu ICU_LOCALE 'es-CR';
REVOKE ALL ON DATABASE seti FROM PUBLIC;

\connect seti
CREATE EXTENSION IF NOT EXISTS btree_gist WITH SCHEMA public;
CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;
CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA public;
