<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Baseline schema, ported verbatim from docs/db/schema.sql (the approved design, Agent-DB.md).
 * Notifications (schema.sql §2, ADR-0006).
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
            CREATE TABLE notifications (
                id              uuid PRIMARY KEY,
                type            varchar(255) NOT NULL,    -- short stable code via databaseType(), e.g. 'reservation.created'
                notifiable_type varchar(255) NOT NULL,
                notifiable_id   bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,       -- added FK: only users are notified
                data            jsonb NOT NULL,           -- snapshot payload (space, dates, actor) + ids for links
                read_at         timestamptz,
                created_at      timestamptz,
                updated_at      timestamptz,
                CONSTRAINT notifications_notifiable_type_check CHECK (notifiable_type = 'user'),
                CONSTRAINT notifications_data_check CHECK (jsonb_typeof(data) = 'object')
            );
            CREATE INDEX notifications_notifiable_type_notifiable_id_index ON notifications (notifiable_type, notifiable_id);
            CREATE INDEX notifications_notifiable_id_created_at_index ON notifications (notifiable_id, created_at DESC);  -- inbox + unread badge
            COMMENT ON TABLE notifications IS 'In-app inbox, one row per recipient (fan-out on write). JSON payload is a deliberate snapshot (ADR-0006). Not audited.';
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS notifications CASCADE;
SQL);
    }
};
