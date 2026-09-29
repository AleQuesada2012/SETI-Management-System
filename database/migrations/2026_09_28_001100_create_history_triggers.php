<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Baseline schema, ported verbatim from docs/db/schema.sql (the approved design, Agent-DB.md).
 * Triggers (schema.sql §11): exactly two jobs, the asset location pointer and append-only history.
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
            -- Trigger errors mimic PostgreSQL's own wording ('... violates rule "<name>"')
            -- and set CONSTRAINT = <name>, so Laravel can map every integrity error to a
            -- UI message with one regex: /(?:constraint|rule) "([a-z0-9_]+)"/ (Agent-DB.md §14.11).

            -- (a) Asset location --------------------------------------------------------
            -- Any write to assets.current_space_id outside a movement is rejected.
            CREATE OR REPLACE FUNCTION public.assets_guard_location()
                RETURNS trigger
                LANGUAGE plpgsql
            AS $$
            BEGIN
                IF coalesce(current_setting('seti.applying_movement', true), '') <> 'on' THEN
                    IF TG_OP = 'INSERT' AND NEW.current_space_id IS NOT NULL THEN
                        RAISE EXCEPTION 'insert into assets violates rule "assets_location_via_movements": a new asset is placed by inserting an entry into asset_movements'
                            USING ERRCODE = 'check_violation', CONSTRAINT = 'assets_location_via_movements';
                    ELSIF TG_OP = 'UPDATE' AND NEW.current_space_id IS DISTINCT FROM OLD.current_space_id THEN
                        RAISE EXCEPTION 'update of assets violates rule "assets_location_via_movements": current_space_id changes only by inserting into asset_movements'
                            USING ERRCODE = 'check_violation', CONSTRAINT = 'assets_location_via_movements';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER assets_guard_location
                BEFORE INSERT OR UPDATE OF current_space_id ON assets
                FOR EACH ROW EXECUTE FUNCTION public.assets_guard_location();

            -- Validates a movement against the asset's current state, then applies it.
            CREATE OR REPLACE FUNCTION public.asset_movements_apply()
                RETURNS trigger
                LANGUAGE plpgsql
            AS $$
            DECLARE
                v_asset public.assets%ROWTYPE;
            BEGIN
                -- Lock the asset so concurrent movements of the same asset are serialized.
                SELECT * INTO v_asset FROM public.assets WHERE id = NEW.asset_id FOR UPDATE;
                IF NOT FOUND THEN
                    RETURN NEW;  -- the foreign key reports the missing asset
                END IF;

                IF v_asset.deleted_at IS NOT NULL THEN
                    RAISE EXCEPTION 'insert into asset_movements violates rule "asset_movements_asset_rules": asset % is deleted', v_asset.code
                        USING ERRCODE = 'check_violation', CONSTRAINT = 'asset_movements_asset_rules';
                END IF;
                IF v_asset.status = 'lost' THEN
                    RAISE EXCEPTION 'insert into asset_movements violates rule "asset_movements_asset_rules": asset % is lost; register a recovery first', v_asset.code
                        USING ERRCODE = 'check_violation', CONSTRAINT = 'asset_movements_asset_rules';
                END IF;
                IF v_asset.status = 'decommissioned' AND NEW.type IS DISTINCT FROM 'exit' THEN
                    RAISE EXCEPTION 'insert into asset_movements violates rule "asset_movements_asset_rules": asset % is decommissioned and can only exit', v_asset.code
                        USING ERRCODE = 'check_violation', CONSTRAINT = 'asset_movements_asset_rules';
                END IF;
                IF NEW.from_space_id IS DISTINCT FROM v_asset.current_space_id THEN
                    RAISE EXCEPTION 'insert into asset_movements violates rule "asset_movements_origin_matches": asset % is in space %, not in space %',
                            v_asset.code, coalesce(v_asset.current_space_id::text, 'none'), coalesce(NEW.from_space_id::text, 'none')
                        USING ERRCODE = 'check_violation', CONSTRAINT = 'asset_movements_origin_matches';
                END IF;
                IF NEW.to_space_id IS NOT NULL
                   AND EXISTS (SELECT 1 FROM public.spaces WHERE id = NEW.to_space_id AND deleted_at IS NOT NULL) THEN
                    RAISE EXCEPTION 'insert into asset_movements violates rule "asset_movements_destination_active": destination space % is deleted', NEW.to_space_id
                        USING ERRCODE = 'check_violation', CONSTRAINT = 'asset_movements_destination_active';
                END IF;
                IF EXISTS (SELECT 1 FROM public.asset_movements WHERE asset_id = NEW.asset_id AND moved_at > NEW.moved_at) THEN
                    RAISE EXCEPTION 'insert into asset_movements violates rule "asset_movements_chronological": asset % already has a movement after %', v_asset.code, NEW.moved_at
                        USING ERRCODE = 'check_violation', CONSTRAINT = 'asset_movements_chronological';
                END IF;

                PERFORM set_config('seti.applying_movement', 'on', true);
                UPDATE public.assets SET current_space_id = NEW.to_space_id, updated_at = now() WHERE id = NEW.asset_id;
                PERFORM set_config('seti.applying_movement', 'off', true);
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER asset_movements_apply
                BEFORE INSERT ON asset_movements
                FOR EACH ROW EXECUTE FUNCTION public.asset_movements_apply();

            -- (b) Append-only history ---------------------------------------------------
            CREATE OR REPLACE FUNCTION public.forbid_history_rewrite()
                RETURNS trigger
                LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION '% on % violates rule "%": history is append-only',
                        TG_OP, TG_TABLE_NAME, TG_TABLE_NAME || '_append_only'
                    USING ERRCODE = 'restrict_violation',
                          CONSTRAINT = TG_TABLE_NAME || '_append_only',
                          HINT = 'Correct history by inserting a new row (a compensating movement or event), never by rewriting it.';
            END;
            $$;

            CREATE TRIGGER audit_logs_append_only      BEFORE UPDATE OR DELETE ON audit_logs      FOR EACH ROW       EXECUTE FUNCTION public.forbid_history_rewrite();
            CREATE TRIGGER audit_logs_no_truncate      BEFORE TRUNCATE         ON audit_logs      FOR EACH STATEMENT EXECUTE FUNCTION public.forbid_history_rewrite();
            CREATE TRIGGER asset_movements_append_only BEFORE UPDATE OR DELETE ON asset_movements FOR EACH ROW       EXECUTE FUNCTION public.forbid_history_rewrite();
            CREATE TRIGGER asset_movements_no_truncate BEFORE TRUNCATE         ON asset_movements FOR EACH STATEMENT EXECUTE FUNCTION public.forbid_history_rewrite();
            CREATE TRIGGER asset_events_append_only    BEFORE UPDATE OR DELETE ON asset_events    FOR EACH ROW       EXECUTE FUNCTION public.forbid_history_rewrite();
            CREATE TRIGGER asset_events_no_truncate    BEFORE TRUNCATE         ON asset_events    FOR EACH STATEMENT EXECUTE FUNCTION public.forbid_history_rewrite();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS asset_events_no_truncate ON asset_events;
            DROP TRIGGER IF EXISTS asset_events_append_only ON asset_events;
            DROP TRIGGER IF EXISTS asset_movements_no_truncate ON asset_movements;
            DROP TRIGGER IF EXISTS asset_movements_append_only ON asset_movements;
            DROP TRIGGER IF EXISTS audit_logs_no_truncate ON audit_logs;
            DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs;
            DROP TRIGGER IF EXISTS asset_movements_apply ON asset_movements;
            DROP TRIGGER IF EXISTS assets_guard_location ON assets;
            DROP FUNCTION IF EXISTS public.forbid_history_rewrite();
            DROP FUNCTION IF EXISTS public.asset_movements_apply();
            DROP FUNCTION IF EXISTS public.assets_guard_location();
SQL);
    }
};
