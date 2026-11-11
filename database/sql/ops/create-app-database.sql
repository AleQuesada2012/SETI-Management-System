-- One-time operations step on the Amazon RDS instance (docs/deployment.md § 3). Run it once, from the VM, as the
-- RDS master user, before the first `docker compose up`:
--   psql -v ON_ERROR_STOP=1 -h <rds-endpoint> -U postgres -d postgres -f database/sql/ops/create-app-database.sql
-- It asks twice for the password of the new `seti` user: use a long random one (openssl rand -hex 32) and put the
-- same value in DB_PASSWORD of the VM's .env.
--
-- The application gets its own database and its own user, not the master user and the `postgres` database: the
-- app's user owns only its database and cannot create roles or touch other databases on the instance.

CREATE ROLE seti LOGIN;
\password seti

-- On RDS the master user is not a superuser: it can create a database owned by `seti` only as a member of it.
-- The membership also lets it run create-powerbi-reader.sql on the `reporting` schema that the migrations create.
GRANT seti TO CURRENT_USER;

-- Same encoding and collation as the PostgreSQL container used in development and CI (compose.yaml).
CREATE DATABASE seti OWNER seti TEMPLATE template0 ENCODING 'UTF8' LOCALE_PROVIDER icu ICU_LOCALE 'es-CR';
REVOKE ALL ON DATABASE seti FROM PUBLIC;

-- The first migration creates these as well (IF NOT EXISTS); creating them here as the master user means the
-- migration does not depend on which extensions RDS lets a database owner create.
\connect seti
CREATE EXTENSION IF NOT EXISTS btree_gist WITH SCHEMA public;
CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;
CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA public;
