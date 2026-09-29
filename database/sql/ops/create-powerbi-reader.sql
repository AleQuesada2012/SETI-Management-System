-- One-time operations step (not a migration: it needs CREATEROLE). Run it as a DB admin after
-- `php artisan migrate`, and again whenever a new reporting view is added:
--   psql -v ON_ERROR_STOP=1 -h <host> -U <admin> -d <database> -f database/sql/ops/create-powerbi-reader.sql
-- Then create a LOGIN user for PowerBI that is a member of powerbi_reader (ADR-0010).
-- =============================================================================
-- 13. Operational objects: read-only role for PowerBI (Q22)
--     Ops then creates a LOGIN user that is a member of powerbi_reader.
-- =============================================================================
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'powerbi_reader') THEN
        CREATE ROLE powerbi_reader NOLOGIN;
    END IF;
END;
$$;
GRANT USAGE ON SCHEMA reporting TO powerbi_reader;
GRANT SELECT ON ALL TABLES IN SCHEMA reporting TO powerbi_reader;
ALTER DEFAULT PRIVILEGES IN SCHEMA reporting GRANT SELECT ON TABLES TO powerbi_reader;
