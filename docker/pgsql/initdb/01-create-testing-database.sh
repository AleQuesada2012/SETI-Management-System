#!/usr/bin/env bash
# Runs once, when the pgsql volume is created. Creates the database the test
# suite uses (phpunit.xml → DB_DATABASE=seti_testing), with the same ICU es-CR
# locale as the main database.
set -euo pipefail

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE ${POSTGRES_DB}_testing;
EOSQL
