<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Baseline schema, ported verbatim from docs/db/schema.sql (the approved design, Agent-DB.md).
 * Extensions and helper functions (schema.sql §0).
 *
 * Baseline migrations are raw SQL on purpose: they keep PostgreSQL's constraint names, which the
 * UI error mapping and docs/db/smoke-test.sql rely on (ADR-0013). New migrations may use Blueprint.
 * Never edit a migration that is already on main: add a new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE EXTENSION IF NOT EXISTS btree_gist WITH SCHEMA public;  -- EXCLUDE (space_id WITH =, period WITH &&)
            CREATE EXTENSION IF NOT EXISTS pg_trgm    WITH SCHEMA public;  -- partial-match search (RF_07, RF_42, RF_95)
            CREATE EXTENSION IF NOT EXISTS unaccent   WITH SCHEMA public;  -- accent-insensitive search ("salon" finds "Salón")

            -- unaccent() is only STABLE; generated columns and index expressions need an
            -- IMMUTABLE function. Pinning the dictionary by schema-qualified name makes this
            -- wrapper deterministic (the standard, well-known workaround).
            CREATE OR REPLACE FUNCTION public.immutable_unaccent(text)
                RETURNS text
                LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
                RETURN public.unaccent('public.unaccent'::regdictionary, $1);
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS public.immutable_unaccent(text);
            DROP EXTENSION IF EXISTS unaccent;
            DROP EXTENSION IF EXISTS pg_trgm;
            DROP EXTENSION IF EXISTS btree_gist;
SQL);
    }
};
