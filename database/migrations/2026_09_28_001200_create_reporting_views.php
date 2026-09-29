<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Baseline schema, ported verbatim from docs/db/schema.sql (the approved design, Agent-DB.md).
 * Reporting contract (schema.sql §12, ADR-0010): the views PowerBI and the in-app reports read.
 * A later migration that changes a column used here must drop and recreate the affected view.
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
            CREATE SCHEMA IF NOT EXISTS reporting;

            -- Single place that defines the institution's local time zone (Costa Rica, no DST).
            CREATE OR REPLACE FUNCTION reporting.local_tz()
                RETURNS text
                LANGUAGE sql IMMUTABLE PARALLEL SAFE
                RETURN 'America/Costa_Rica';

            CREATE VIEW reporting.assets AS
            SELECT
                a.id,
                a.code,
                a.asset_tag,
                a.serial_number,
                COALESCE(a.name, concat_ws(' ', t.name, b.name, a.model)) AS display_name,
                a.name,
                c.id            AS category_id,
                c.name          AS category,
                t.id            AS asset_type_id,
                t.name          AS asset_type,
                b.id            AS brand_id,
                b.name          AS brand,
                a.model,
                ac.id           AS condition_id,
                ac.name         AS condition,
                ac.color_group  AS condition_color,
                a.condition_note,
                a.status,
                s.id            AS space_id,
                s.code          AS space_code,
                s.name          AS space_name,
                bl.name         AS building,
                a.location_detail,
                u.id            AS responsible_user_id,
                u.name          AS responsible_name,
                u.email         AS responsible_email,
                sp.id           AS supplier_id,
                sp.name         AS supplier,
                a.acquired_on,
                a.warranty_starts_on,
                a.warranty_ends_on,
                CASE
                    WHEN a.warranty_ends_on IS NULL THEN 'none'
                    WHEN a.warranty_ends_on >= (now() AT TIME ZONE reporting.local_tz())::date THEN 'valid'
                    ELSE 'expired'
                END             AS warranty_state,
                a.observations,
                a.created_at,
                a.updated_at
            FROM public.assets a
            JOIN public.asset_types t       ON t.id = a.asset_type_id
            JOIN public.asset_categories c  ON c.id = t.asset_category_id
            JOIN public.asset_conditions ac ON ac.id = a.condition_id
            LEFT JOIN public.brands b       ON b.id = a.brand_id
            LEFT JOIN public.spaces s       ON s.id = a.current_space_id
            LEFT JOIN public.buildings bl   ON bl.id = s.building_id
            LEFT JOIN public.users u        ON u.id = a.responsible_user_id
            LEFT JOIN public.suppliers sp   ON sp.id = a.supplier_id
            WHERE a.deleted_at IS NULL;

            CREATE VIEW reporting.asset_components AS
            SELECT
                ac.id,
                ac.asset_id,
                a.code      AS asset_code,
                a.asset_tag,
                a.name      AS asset_name,
                ac.name,
                ac.specs,
                ac.serial_number,
                ac.quantity,
                ac.notes,
                ac.created_at,
                ac.updated_at
            FROM public.asset_components ac
            JOIN public.assets a ON a.id = ac.asset_id
            WHERE a.deleted_at IS NULL;

            CREATE VIEW reporting.spaces AS
            SELECT
                s.id,
                s.code,
                s.name,
                st.id            AS space_type_id,
                st.name          AS space_type,
                ss.id            AS space_status_id,
                ss.name          AS status,
                ss.color_group   AS status_color,
                (st.is_reservable AND ss.allows_reservations) AS is_reservable,
                EXISTS (
                    SELECT 1 FROM public.reservations r
                    WHERE r.space_id = s.id AND r.status = 'confirmed'
                      AND r.starts_at <= now() AND r.ends_at > now()
                )                AS is_occupied,
                b.id             AS building_id,
                b.name           AS building,
                s.floor,
                s.location_detail,
                s.capacity,
                (SELECT string_agg(u.name, ', ' ORDER BY u.name)
                   FROM public.space_managers m
                   JOIN public.users u ON u.id = m.user_id
                  WHERE m.space_id = s.id) AS managers,
                (SELECT count(*)
                   FROM public.assets a
                  WHERE a.current_space_id = s.id AND a.deleted_at IS NULL
                    AND a.status IN ('in_service', 'in_maintenance')) AS asset_count,
                s.created_at,
                s.updated_at
            FROM public.spaces s
            JOIN public.space_types st    ON st.id = s.space_type_id
            JOIN public.space_statuses ss ON ss.id = s.space_status_id
            JOIN public.buildings b       ON b.id = s.building_id
            WHERE s.deleted_at IS NULL;

            CREATE VIEW reporting.space_managers AS
            SELECT
                m.space_id,
                s.code   AS space_code,
                s.name   AS space_name,
                m.user_id,
                u.name   AS manager_name,
                u.email  AS manager_email,
                m.assigned_at
            FROM public.space_managers m
            JOIN public.spaces s ON s.id = m.space_id
            JOIN public.users u  ON u.id = m.user_id
            WHERE s.deleted_at IS NULL;

            CREATE VIEW reporting.reservations AS
            SELECT
                r.id,
                r.series_id::text AS series_id,
                s.id              AS space_id,
                s.code            AS space_code,
                s.name            AS space_name,
                (s.deleted_at IS NOT NULL) AS space_deleted,
                r.starts_at,
                r.ends_at,
                (r.starts_at AT TIME ZONE reporting.local_tz())::date AS local_date,
                r.title,
                r.notes,
                r.attendees,
                ru.id             AS responsible_id,
                ru.name           AS responsible_name,
                cu.id             AS created_by,
                cu.name           AS created_by_name,
                r.status,
                CASE
                    WHEN r.status = 'cancelled' THEN 'cancelled'
                    WHEN r.ends_at <= now()     THEN 'finished'
                    WHEN r.starts_at <= now()   THEN 'in_progress'
                    ELSE 'upcoming'
                END               AS effective_status,
                (r.version > 1)   AS was_modified,
                r.version,
                r.cancelled_at,
                r.cancelled_by,
                cb.name           AS cancelled_by_name,
                r.cancellation_reason,
                r.created_at,
                r.updated_at
            FROM public.reservations r
            JOIN public.spaces s      ON s.id = r.space_id
            JOIN public.users ru      ON ru.id = r.responsible_id
            JOIN public.users cu      ON cu.id = r.created_by
            LEFT JOIN public.users cb ON cb.id = r.cancelled_by;

            CREATE VIEW reporting.space_occupancy AS           -- RF_59, RF_79, RF_83 (a booking counts on its start date)
            SELECT
                r.space_id,
                s.code AS space_code,
                s.name AS space_name,
                (r.starts_at AT TIME ZONE reporting.local_tz())::date AS local_date,
                count(*)                                                      AS reservations,
                round(sum(EXTRACT(epoch FROM (r.ends_at - r.starts_at))) / 3600, 2) AS booked_hours,
                sum(r.attendees)                                              AS attendees
            FROM public.reservations r
            JOIN public.spaces s ON s.id = r.space_id
            WHERE r.status = 'confirmed'
            GROUP BY r.space_id, s.code, s.name, (r.starts_at AT TIME ZONE reporting.local_tz())::date;

            CREATE VIEW reporting.maintenance AS
            SELECT
                m.id,
                m.asset_id,
                a.code        AS asset_code,
                a.asset_tag,
                a.name        AS asset_name,
                m.kind,
                m.status,
                m.scheduled_for,
                m.started_on,
                m.completed_on,
                COALESCE(m.completed_on, m.started_on, m.scheduled_for,
                         (m.created_at AT TIME ZONE reporting.local_tz())::date) AS reference_date,
                m.description,
                m.performed_by,
                m.asset_event_id,
                m.registered_by,
                u.name        AS registered_by_name,
                m.cancellation_reason,
                m.created_at,
                m.updated_at
            FROM public.maintenance_records m
            JOIN public.assets a     ON a.id = m.asset_id
            LEFT JOIN public.users u ON u.id = m.registered_by;

            CREATE VIEW reporting.asset_movements AS
            SELECT
                mv.id,
                mv.asset_id,
                a.code        AS asset_code,
                a.asset_tag,
                a.name        AS asset_name,
                mv.type,
                mv.from_space_id,
                fs.code       AS from_space_code,
                fs.name       AS from_space_name,
                mv.to_space_id,
                ts.code       AS to_space_code,
                ts.name       AS to_space_name,
                mv.moved_at,
                mv.moved_by,
                u.name        AS moved_by_name,
                mv.reason
            FROM public.asset_movements mv
            JOIN public.assets a       ON a.id = mv.asset_id
            LEFT JOIN public.spaces fs ON fs.id = mv.from_space_id
            LEFT JOIN public.spaces ts ON ts.id = mv.to_space_id
            LEFT JOIN public.users u   ON u.id = mv.moved_by;

            CREATE VIEW reporting.asset_events AS
            SELECT
                e.id,
                e.asset_id,
                a.code        AS asset_code,
                a.asset_tag,
                a.name        AS asset_name,
                e.kind,
                e.occurred_on,
                e.description,
                e.registered_by,
                u.name        AS registered_by_name,
                (SELECT count(*) FROM public.asset_event_evidence ev WHERE ev.asset_event_id = e.id) AS evidence_count,
                e.created_at
            FROM public.asset_events e
            JOIN public.assets a     ON a.id = e.asset_id
            LEFT JOIN public.users u ON u.id = e.registered_by;

            CREATE VIEW reporting.users AS                      -- no password / remember_token here, ever
            SELECT
                u.id,
                u.name,
                u.email,
                CASE WHEN u.deactivated_at IS NULL THEN 'active' ELSE 'deactivated' END AS account_status,
                (u.password IS NULL) AS invitation_pending,
                (SELECT string_agg(r.name, ', ' ORDER BY r.name)
                   FROM public.model_has_roles mr
                   JOIN public.roles r ON r.id = mr.role_id
                  WHERE mr.model_type = 'user' AND mr.model_id = u.id) AS roles,
                u.last_login_at,
                u.deactivated_at,
                u.created_at,
                u.updated_at
            FROM public.users u;

            CREATE VIEW reporting.user_roles AS
            SELECT
                mr.model_id AS user_id,
                r.id        AS role_id,
                r.name      AS role
            FROM public.model_has_roles mr
            JOIN public.roles r ON r.id = mr.role_id
            WHERE mr.model_type = 'user';

            CREATE VIEW reporting.audit_logs AS
            SELECT
                l.id,
                l.occurred_at,
                l.actor_id,
                l.actor_label,
                l.action,
                l.entity_type,
                l.entity_id,
                l.entity_label,
                l.old_values::text   AS old_values,
                l.new_values::text   AS new_values,
                l.channel,
                host(l.ip_address)   AS ip_address,
                l.user_agent,
                l.request_id::text   AS request_id
            FROM public.audit_logs l;

            CREATE VIEW reporting.report_generations AS
            SELECT
                g.id,
                rt.code        AS report_code,
                rt.name        AS report_name,
                rt.category    AS report_category,
                g.format,
                g.filters::text AS filters,
                g.row_count,
                g.file_id,
                (g.file_id IS NOT NULL) AS file_available,
                g.generated_by,
                u.name         AS generated_by_name,
                g.generated_at
            FROM public.report_generations g
            JOIN public.report_types rt ON rt.id = g.report_type_id
            JOIN public.users u         ON u.id = g.generated_by;

            CREATE VIEW reporting.dashboard_kpis AS             -- the mockup's KPI cards, computed live
            WITH today AS (
                SELECT tstzrange(
                           date_trunc('day', now() AT TIME ZONE reporting.local_tz()) AT TIME ZONE reporting.local_tz(),
                           (date_trunc('day', now() AT TIME ZONE reporting.local_tz()) + interval '1 day') AT TIME ZONE reporting.local_tz(),
                           '[)') AS period
            )
            SELECT
                (SELECT count(*) FROM public.assets
                  WHERE deleted_at IS NULL AND status <> 'decommissioned')                     AS assets_total,
                (SELECT count(*) FROM public.assets
                  WHERE deleted_at IS NULL AND status = 'in_service')                          AS assets_in_service,
                (SELECT count(*) FROM public.spaces s
                   JOIN public.space_statuses ss ON ss.id = s.space_status_id
                  WHERE s.deleted_at IS NULL AND ss.allows_reservations)                       AS spaces_available,
                (SELECT count(*) FROM public.spaces WHERE deleted_at IS NULL)                  AS spaces_total,
                (SELECT count(*) FROM public.reservations r, today
                  WHERE r.status = 'confirmed'
                    AND tstzrange(r.starts_at, r.ends_at, '[)') && today.period)               AS reservations_today,
                (SELECT count(*) FROM public.maintenance_records m
                   JOIN public.assets a ON a.id = m.asset_id
                  WHERE a.deleted_at IS NULL AND m.status IN ('scheduled', 'in_progress'))     AS maintenance_pending,
                now()                                                                          AS computed_at;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP SCHEMA IF EXISTS reporting CASCADE;
SQL);
    }
};
