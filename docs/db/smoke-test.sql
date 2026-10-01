-- =============================================================================
-- SETI · Assets & Spaces · schema smoke tests
-- Proves that the constraints, triggers and views in the migrated database really hold,
-- and that each rejection comes from the rule we expect (SQLSTATE + constraint).
-- Run on a THROWAWAY database, after `php artisan migrate:fresh --seed`:
--   psql -v ON_ERROR_STOP=1 -f docs/db/smoke-test.sql
-- Everything runs in one transaction that is rolled back at the end.
-- Each check prints "ok ..."; the first failure aborts with "FAIL ...".
-- =============================================================================
\set ON_ERROR_STOP on
\set QUIET on
\o /dev/null
BEGIN;
SET LOCAL search_path = public;

-- -----------------------------------------------------------------------------
-- Test helpers (temporary, disappear with the session)
-- -----------------------------------------------------------------------------
CREATE FUNCTION pg_temp.ok(p_condition boolean, p_label text) RETURNS void
LANGUAGE plpgsql AS $$
BEGIN
    IF p_condition IS NOT TRUE THEN
        RAISE EXCEPTION 'FAIL  %', p_label;
    END IF;
    RAISE NOTICE 'ok    %', p_label;
END;
$$;

-- Runs p_sql and expects it to fail with SQLSTATE p_sqlstate raised by the
-- constraint p_constraint (NULL = any constraint). SQLSTATEs used here:
-- 23514 check_violation · 23505 unique_violation · 23503 foreign_key_violation
-- 23P01 exclusion_violation · 23001 restrict_violation (append-only history)
-- It also checks the Laravel error-mapping contract (Agent-DB.md §14.11): the
-- message itself must name the rule, because PDO exposes only the message.
CREATE FUNCTION pg_temp.fails(p_sql text, p_sqlstate text, p_constraint text, p_label text) RETURNS void
LANGUAGE plpgsql AS $$
DECLARE
    v_constraint text;
BEGIN
    BEGIN
        EXECUTE p_sql;
    EXCEPTION WHEN OTHERS THEN
        GET STACKED DIAGNOSTICS v_constraint = CONSTRAINT_NAME;
        IF SQLSTATE = p_sqlstate AND (p_constraint IS NULL OR v_constraint = p_constraint) THEN
            IF p_constraint IS NOT NULL
               AND substring(SQLERRM FROM '(?:constraint|rule) "([a-z0-9_]+)"') IS DISTINCT FROM p_constraint THEN
                RAISE EXCEPTION 'FAIL  %: the message does not name "%": %', p_label, p_constraint, SQLERRM;
            END IF;
            RAISE NOTICE 'ok    % [% %]', p_label, SQLSTATE, coalesce(nullif(v_constraint, ''), '-');
            RETURN;
        END IF;
        RAISE EXCEPTION 'FAIL  %: expected % %, got % % (%)',
            p_label, p_sqlstate, coalesce(p_constraint, '*'), SQLSTATE, coalesce(nullif(v_constraint, ''), '-'), SQLERRM;
    END;
    RAISE EXCEPTION 'FAIL  %: statement succeeded but % % was expected', p_label, p_sqlstate, coalesce(p_constraint, '*');
END;
$$;

-- Books a space for Beto, p_day days from today, local Costa Rica time.
CREATE FUNCTION pg_temp.book(p_space text, p_day int, p_from time, p_to time, p_title text) RETURNS bigint
LANGUAGE sql AS $$
    INSERT INTO reservations (space_id, starts_at, ends_at, title, attendees, responsible_id, created_by)
    SELECT s.id,
           (current_date + p_day + p_from) AT TIME ZONE 'America/Costa_Rica',
           (current_date + p_day + p_to)   AT TIME ZONE 'America/Costa_Rica',
           p_title, 5, u.id, u.id
    FROM spaces s, users u
    WHERE s.code = p_space AND s.deleted_at IS NULL AND u.email = 'beto@seti.test'
    RETURNING id;
$$;

-- Registers file metadata (the bytes would live on a Laravel Storage disk).
CREATE FUNCTION pg_temp.add_file(p_path text, p_mime text, p_size bigint) RETURNS bigint
LANGUAGE sql AS $$
    INSERT INTO files (disk, path, original_name, mime_type, size_bytes, sha256)
    VALUES ('local', p_path, p_path, p_mime, p_size, repeat('a', 64))
    RETURNING id;
$$;

-- -----------------------------------------------------------------------------
-- 0. Structural lints (guardrails for future edits of schema.sql)
-- -----------------------------------------------------------------------------
SELECT pg_temp.ok(NOT EXISTS (
    SELECT 1 FROM pg_class t
    WHERE t.relnamespace = 'public'::regnamespace AND t.relkind = 'r'
      AND NOT EXISTS (SELECT 1 FROM pg_constraint c WHERE c.conrelid = t.oid AND c.contype = 'p')),
    'lint: every table has a primary key');
SELECT pg_temp.ok(NOT EXISTS (
    SELECT 1 FROM pg_constraint c
    WHERE c.contype = 'f' AND c.connamespace = 'public'::regnamespace
      AND NOT EXISTS (
          SELECT 1 FROM pg_index i
          WHERE i.indrelid = c.conrelid AND i.indpred IS NULL AND i.indkey[0] = c.conkey[1])),
    'lint: every foreign key leads a (non-partial) index');
SELECT pg_temp.ok(NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema IN ('public', 'reporting') AND data_type = 'timestamp without time zone'),
    'lint: every timestamp is timestamptz');

-- -----------------------------------------------------------------------------
-- Fixtures
-- -----------------------------------------------------------------------------
INSERT INTO user_invitation_tokens (email, token, created_at)
VALUES ('invitation@seti.test', 'hashed-test-token', now());
SELECT pg_temp.fails(
    $$INSERT INTO user_invitation_tokens (email, token) VALUES ('invitation@seti.test', 'another-hash')$$,
    '23505', 'user_invitation_tokens_pkey', 'one invitation token per email');

INSERT INTO users (name, email) VALUES
    ('Ana Administradora', 'ana@seti.test'),
    ('Beto Reservante',    'beto@seti.test'),
    ('Carla Superusuaria', 'carla@seti.test'),
    ('Dora Visualizadora', 'dora@seti.test');

INSERT INTO buildings (name) VALUES ('Edificio A');

INSERT INTO spaces (code, name, space_type_id, space_status_id, building_id, capacity)
SELECT v.code, v.name, st.id, ss.id, b.id, v.capacity
FROM (VALUES
    ('LAB-110', 'Laboratorio 110',   'Laboratorio', 30),
    ('LAB-312', 'Laboratorio 312',   'Laboratorio', 20),
    ('SAL-01',  'Sala de reuniones', 'Sala',        10),
    ('SAL-02',  'Salón Multiuso',    'Sala',       120)
) AS v(code, name, type_name, capacity)
JOIN space_types st    ON st.name = v.type_name
JOIN space_statuses ss ON ss.name = 'Disponible'
JOIN buildings b       ON b.name = 'Edificio A';

INSERT INTO space_managers (space_id, user_id, assigned_by)
SELECT s.id, u.id, su.id
FROM spaces s, users u, users su
WHERE s.code = 'LAB-110' AND u.email = 'ana@seti.test' AND su.email = 'carla@seti.test';

INSERT INTO brands (name) VALUES ('Dell'), ('Epson');
INSERT INTO suppliers (name, tax_id) VALUES ('Proveedor XYZ', '3-101-000000');

INSERT INTO assets (asset_tag, asset_type_id, brand_id, model, condition_id, supplier_id, acquired_on, responsible_user_id)
SELECT 'TEC-0001', t.id, b.id, 'Latitude 5420', c.id, s.id, DATE '2024-01-22', u.id
FROM asset_types t, brands b, asset_conditions c, suppliers s, users u
WHERE t.name = 'Laptop' AND b.name = 'Dell' AND c.name = 'Buen estado'
  AND s.name = 'Proveedor XYZ' AND u.email = 'ana@seti.test';

INSERT INTO assets (asset_tag, name, asset_type_id, brand_id, model, condition_id, warranty_starts_on, warranty_ends_on)
SELECT 'TEC-0002', 'Proyector del laboratorio', t.id, b.id, 'EB-X06', c.id, current_date - 30, current_date + 335
FROM asset_types t, brands b, asset_conditions c
WHERE t.name = 'Proyector' AND b.name = 'Epson' AND c.name = 'Buen estado';

-- -----------------------------------------------------------------------------
-- 1. Seeds
-- -----------------------------------------------------------------------------
SELECT pg_temp.ok((SELECT count(*) FROM space_types) = 3,      'seed: 3 space types');
SELECT pg_temp.ok((SELECT count(*) FROM space_statuses) = 3,   'seed: 3 space statuses');
SELECT pg_temp.ok((SELECT count(*) FROM asset_conditions) = 3, 'seed: 3 asset conditions');
SELECT pg_temp.ok((SELECT count(*) FROM asset_categories) = 5, 'seed: 5 asset categories');
SELECT pg_temp.ok((SELECT count(*) FROM asset_types) = 22,     'seed: 22 asset types');
SELECT pg_temp.ok((SELECT count(*) FROM report_types) = 13,    'seed: 13 report types (RF_63–RF_79)');
SELECT pg_temp.ok((SELECT count(*) FROM roles WHERE is_system) = 5, 'seed: 5 system roles');
SELECT pg_temp.ok(
    (SELECT count(*) FROM role_has_permissions rp JOIN roles r ON r.id = rp.role_id WHERE r.name = 'Superusuario')
    = (SELECT count(*) FROM permissions),
    'seed: Superusuario holds every permission');
SELECT pg_temp.ok(NOT EXISTS (
    SELECT 1 FROM role_has_permissions rp
    JOIN roles r ON r.id = rp.role_id
    JOIN permissions p ON p.id = rp.permission_id
    WHERE r.name = 'Visualizador' AND p.name NOT LIKE '%.view'),
    'seed: Visualizador only holds *.view permissions (RF_89)');

-- -----------------------------------------------------------------------------
-- 2. Asset code (Q5)
-- -----------------------------------------------------------------------------
SELECT pg_temp.ok((SELECT code FROM assets WHERE asset_tag = 'TEC-0001') ~ '^A-[0-9]{4}$', 'asset code has the A-0001 shape');
INSERT INTO assets (id, asset_type_id, condition_id)
SELECT 14, t.id, c.id FROM asset_types t, asset_conditions c WHERE t.name = 'Monitor' AND c.name = 'Buen estado';
INSERT INTO assets (id, asset_type_id, condition_id)
SELECT 12345, t.id, c.id FROM asset_types t, asset_conditions c WHERE t.name = 'Monitor' AND c.name = 'Buen estado';
SELECT pg_temp.ok((SELECT code FROM assets WHERE id = 14) = 'A-0014', 'asset code pads to 4 digits');
SELECT pg_temp.ok((SELECT code FROM assets WHERE id = 12345) = 'A-12345', 'asset code is not truncated past 9999');

-- -----------------------------------------------------------------------------
-- 3. Location: current_space_id is written only by movements (ADR-0003)
-- -----------------------------------------------------------------------------
SELECT pg_temp.fails($$
    INSERT INTO assets (asset_type_id, condition_id, current_space_id)
    SELECT t.id, c.id, s.id FROM asset_types t, asset_conditions c, spaces s
    WHERE t.name = 'Mouse' AND c.name = 'Buen estado' AND s.code = 'LAB-110'$$,
    '23514', 'assets_location_via_movements', 'a new asset cannot be placed without an entry movement');

INSERT INTO asset_movements (asset_id, type, to_space_id, moved_by, reason)
SELECT a.id, 'entry', s.id, u.id, 'Alta en inventario'
FROM assets a, spaces s, users u
WHERE a.asset_tag = 'TEC-0001' AND s.code = 'LAB-110' AND u.email = 'ana@seti.test';
SELECT pg_temp.ok(
    (SELECT s.code FROM assets a JOIN spaces s ON s.id = a.current_space_id WHERE a.asset_tag = 'TEC-0001') = 'LAB-110',
    'an entry movement places the asset (RF_03)');

SELECT pg_temp.fails($$
    UPDATE assets SET current_space_id = (SELECT id FROM spaces WHERE code = 'LAB-312')
    WHERE asset_tag = 'TEC-0001'$$,
    '23514', 'assets_location_via_movements', 'a direct write to assets.current_space_id is rejected');

UPDATE assets SET observations = 'Revisado' WHERE asset_tag = 'TEC-0001';
SELECT pg_temp.ok((SELECT observations FROM assets WHERE asset_tag = 'TEC-0001') = 'Revisado',
    'other asset columns stay freely editable (RF_19)');

SELECT pg_temp.fails($$
    INSERT INTO asset_movements (asset_id, type, from_space_id, to_space_id)
    SELECT a.id, 'transfer', f.id, t.id FROM assets a, spaces f, spaces t
    WHERE a.asset_tag = 'TEC-0001' AND f.code = 'LAB-312' AND t.code = 'SAL-01'$$,
    '23514', 'asset_movements_origin_matches', 'a transfer must start where the asset is');

SELECT pg_temp.fails($$
    INSERT INTO asset_movements (asset_id, type, to_space_id)
    SELECT a.id, 'entry', s.id FROM assets a, spaces s
    WHERE a.asset_tag = 'TEC-0001' AND s.code = 'SAL-01'$$,
    '23514', 'asset_movements_origin_matches', 'an entry is rejected while the asset is already placed');

SELECT pg_temp.fails($$
    INSERT INTO asset_movements (asset_id, type, from_space_id, to_space_id)
    SELECT a.id, 'transfer', s.id, s.id FROM assets a, spaces s
    WHERE a.asset_tag = 'TEC-0001' AND s.code = 'LAB-110'$$,
    '23514', 'asset_movements_endpoints_check', 'a transfer to the same space is rejected');

SELECT pg_temp.fails($$
    INSERT INTO asset_movements (asset_id, type, from_space_id, to_space_id, moved_at)
    SELECT a.id, 'transfer', f.id, t.id, now() - interval '1 day' FROM assets a, spaces f, spaces t
    WHERE a.asset_tag = 'TEC-0001' AND f.code = 'LAB-110' AND t.code = 'LAB-312'$$,
    '23514', 'asset_movements_chronological', 'a backdated movement is rejected (history stays chronological)');

INSERT INTO asset_movements (asset_id, type, from_space_id, to_space_id, moved_by)
SELECT a.id, 'transfer', f.id, t.id, u.id
FROM assets a, spaces f, spaces t, users u
WHERE a.asset_tag = 'TEC-0001' AND f.code = 'LAB-110' AND t.code = 'LAB-312' AND u.email = 'ana@seti.test';
SELECT pg_temp.ok(
    (SELECT s.code FROM assets a JOIN spaces s ON s.id = a.current_space_id WHERE a.asset_tag = 'TEC-0001') = 'LAB-312',
    'a transfer moves the asset (RF_06)');

SELECT pg_temp.fails($$UPDATE asset_movements SET reason = 'x'$$, '23001', 'asset_movements_append_only', 'movements are append-only (UPDATE)');
SELECT pg_temp.fails($$DELETE FROM asset_movements$$,           '23001', 'asset_movements_append_only', 'movements are append-only (DELETE)');
SELECT pg_temp.fails($$TRUNCATE asset_movements$$,              '23001', 'asset_movements_append_only', 'movements are append-only (TRUNCATE)');

-- -----------------------------------------------------------------------------
-- 4. Lifecycle rules (Q24/Q25; the status side effects are applied by Laravel)
-- -----------------------------------------------------------------------------
INSERT INTO asset_events (asset_id, kind, occurred_on, description, registered_by)
SELECT a.id, 'loss', current_date, 'No se encontró durante el inventario', u.id
FROM assets a, users u WHERE a.asset_tag = 'TEC-0001' AND u.email = 'ana@seti.test';
UPDATE assets SET status = 'lost' WHERE asset_tag = 'TEC-0001';
SELECT pg_temp.fails($$
    INSERT INTO asset_movements (asset_id, type, from_space_id, to_space_id)
    SELECT a.id, 'transfer', f.id, t.id FROM assets a, spaces f, spaces t
    WHERE a.asset_tag = 'TEC-0001' AND f.code = 'LAB-312' AND t.code = 'LAB-110'$$,
    '23514', 'asset_movements_asset_rules', 'a lost asset cannot move (RF_28)');

INSERT INTO asset_events (asset_id, kind, occurred_on, description)
SELECT id, 'recovery', current_date, 'Apareció en la bodega' FROM assets WHERE asset_tag = 'TEC-0001';
UPDATE assets SET status = 'in_service' WHERE asset_tag = 'TEC-0001';
INSERT INTO asset_movements (asset_id, type, from_space_id, to_space_id)
SELECT a.id, 'transfer', f.id, t.id FROM assets a, spaces f, spaces t
WHERE a.asset_tag = 'TEC-0001' AND f.code = 'LAB-312' AND t.code = 'LAB-110';
SELECT pg_temp.ok(
    (SELECT s.code FROM assets a JOIN spaces s ON s.id = a.current_space_id WHERE a.asset_tag = 'TEC-0001') = 'LAB-110',
    'a recovered asset can move again');

INSERT INTO asset_events (asset_id, kind, occurred_on, description)
SELECT id, 'decommission', current_date, 'Equipo obsoleto' FROM assets WHERE asset_tag = 'TEC-0001';
UPDATE assets SET status = 'decommissioned' WHERE asset_tag = 'TEC-0001';
SELECT pg_temp.fails($$
    INSERT INTO asset_movements (asset_id, type, from_space_id, to_space_id)
    SELECT a.id, 'transfer', f.id, t.id FROM assets a, spaces f, spaces t
    WHERE a.asset_tag = 'TEC-0001' AND f.code = 'LAB-110' AND t.code = 'LAB-312'$$,
    '23514', 'asset_movements_asset_rules', 'a decommissioned asset cannot be transferred (RF_29)');
INSERT INTO asset_movements (asset_id, type, from_space_id, reason)
SELECT a.id, 'exit', s.id, 'Retiro para desecho' FROM assets a, spaces s
WHERE a.asset_tag = 'TEC-0001' AND s.code = 'LAB-110';
SELECT pg_temp.ok((SELECT current_space_id FROM assets WHERE asset_tag = 'TEC-0001') IS NULL,
    'a decommissioned asset can still exit, and the exit clears its location');

SELECT pg_temp.fails($$
    INSERT INTO asset_events (asset_id, kind, occurred_on, description)
    SELECT id, 'decommission', current_date, 'Otra vez' FROM assets WHERE asset_tag = 'TEC-0001'$$,
    '23505', 'asset_events_one_decommission', 'an asset is decommissioned at most once');
SELECT pg_temp.fails($$UPDATE asset_events SET description = 'x'$$, '23001', 'asset_events_append_only', 'asset events are append-only');
SELECT pg_temp.fails($$
    INSERT INTO asset_events (asset_id, kind, occurred_on, description)
    SELECT id, 'damage', current_date, '   ' FROM assets WHERE asset_tag = 'TEC-0002'$$,
    '23514', 'asset_events_description_check', 'an asset event needs a description (RF_13)');

-- -----------------------------------------------------------------------------
-- 5. Maintenance (Q10)
-- -----------------------------------------------------------------------------
INSERT INTO asset_events (asset_id, kind, occurred_on, description)
SELECT id, 'damage', current_date, 'Lámpara quemada' FROM assets WHERE asset_tag = 'TEC-0002';

SELECT pg_temp.fails($$
    INSERT INTO maintenance_records (asset_id, kind, status, description)
    SELECT id, 'preventive', 'completed', 'Limpieza' FROM assets WHERE asset_tag = 'TEC-0002'$$,
    '23514', 'maintenance_records_completed_check', 'a completed maintenance needs completed_on (RF_10/RF_26)');
SELECT pg_temp.fails($$
    INSERT INTO maintenance_records (asset_id, kind, status, description)
    SELECT id, 'preventive', 'scheduled', 'Revisión' FROM assets WHERE asset_tag = 'TEC-0002'$$,
    '23514', 'maintenance_records_scheduled_check', 'a scheduled maintenance needs scheduled_for (RF_27)');
SELECT pg_temp.fails($$
    INSERT INTO maintenance_records (asset_id, kind, status, started_on, description, asset_event_id)
    SELECT a.id, 'preventive', 'in_progress', current_date, 'x', e.id
    FROM assets a JOIN asset_events e ON e.asset_id = a.id AND e.kind = 'damage'
    WHERE a.asset_tag = 'TEC-0002'$$,
    '23514', 'maintenance_records_event_kind_check', 'only corrective maintenance can reference a damage');
SELECT pg_temp.fails($$
    INSERT INTO maintenance_records (asset_id, kind, status, started_on, description, asset_event_id)
    SELECT 12345, 'corrective', 'in_progress', current_date, 'x', e.id
    FROM asset_events e JOIN assets a ON a.id = e.asset_id
    WHERE a.asset_tag = 'TEC-0002' AND e.kind = 'damage'$$,
    '23503', 'maintenance_records_asset_event_foreign', 'a repair must reference a damage of the same asset (RF_11)');

INSERT INTO maintenance_records (asset_id, kind, status, started_on, description, performed_by, asset_event_id, registered_by)
SELECT a.id, 'corrective', 'in_progress', current_date, 'Cambio de lámpara', 'Técnico externo', e.id, u.id
FROM assets a JOIN asset_events e ON e.asset_id = a.id AND e.kind = 'damage', users u
WHERE a.asset_tag = 'TEC-0002' AND u.email = 'ana@seti.test';
UPDATE maintenance_records SET status = 'completed', completed_on = current_date WHERE description = 'Cambio de lámpara';
SELECT pg_temp.ok((SELECT status FROM maintenance_records WHERE description = 'Cambio de lámpara') = 'completed',
    'a repair goes from in_progress to completed (RF_11)');
INSERT INTO maintenance_records (asset_id, kind, status, scheduled_for, description)
SELECT id, 'preventive', 'scheduled', current_date + 7, 'Limpieza de filtros' FROM assets WHERE asset_tag = 'TEC-0002';

-- -----------------------------------------------------------------------------
-- 6. Reservations (ADR-0005)
-- -----------------------------------------------------------------------------
SELECT pg_temp.book('LAB-110', 1, '10:00', '12:00', 'Tutoría grupal');
SELECT pg_temp.fails($$SELECT pg_temp.book('LAB-110', 1, '11:00', '13:00', 'Choque')$$,
    '23P01', 'reservations_no_overlap', 'overlapping confirmed reservations of one space are rejected (RF_46)');
SELECT pg_temp.book('LAB-110', 1, '12:00', '13:00', 'Consecutiva');
SELECT pg_temp.book('LAB-312', 1, '10:00', '12:00', 'Otro espacio');
UPDATE reservations
   SET status = 'cancelled', cancelled_at = now(), cancelled_by = created_by, cancellation_reason = 'Ya no se necesita'
 WHERE title = 'Tutoría grupal';
SELECT pg_temp.book('LAB-110', 1, '10:30', '11:30', 'Reemplazo');
SELECT pg_temp.ok((SELECT count(*) FROM reservations WHERE title IN ('Consecutiva', 'Otro espacio', 'Reemplazo')) = 3,
    'back-to-back, other-space and after-cancellation bookings are accepted');
SELECT pg_temp.fails($$
    UPDATE reservations SET status = 'confirmed', cancelled_at = NULL, cancelled_by = NULL, cancellation_reason = NULL
    WHERE title = 'Tutoría grupal'$$,
    '23P01', 'reservations_no_overlap', 'un-cancelling cannot re-create an overlap');
SELECT pg_temp.fails($$SELECT pg_temp.book('LAB-312', 2, '10:00', '10:00', 'Vacía')$$,
    '23514', 'reservations_period_check', 'a reservation must end after it starts');
SELECT pg_temp.fails($$UPDATE reservations SET attendees = 0 WHERE title = 'Reemplazo'$$,
    '23514', 'reservations_attendees_check', 'attendees must be positive');
SELECT pg_temp.fails($$UPDATE reservations SET status = 'cancelled' WHERE title = 'Reemplazo'$$,
    '23514', 'reservations_cancellation_check', 'a cancellation needs cancelled_at');
UPDATE reservations SET ends_at = ends_at + interval '30 minutes', version = version + 1 WHERE title = 'Otro espacio';
SELECT pg_temp.book('SAL-01', -1, '08:00', '09:00', 'Pasada');

-- -----------------------------------------------------------------------------
-- 7. Spaces, assets, catalogs: uniqueness and CHECKs
-- -----------------------------------------------------------------------------
SELECT pg_temp.fails($$UPDATE spaces SET capacity = 0 WHERE code = 'SAL-01'$$,
    '23514', 'spaces_capacity_check', 'space capacity must be a positive integer (RF_36)');
SELECT pg_temp.fails($$
    INSERT INTO spaces (code, name, space_type_id, space_status_id, building_id, capacity)
    SELECT 'LAB-999', 'LABORATORIO 110', space_type_id, space_status_id, building_id, 10
    FROM spaces WHERE code = 'LAB-110'$$,
    '23505', 'spaces_name_unique', 'space names are unique, case-insensitively (RF_32)');
UPDATE spaces SET deleted_at = now() WHERE code = 'SAL-01';
INSERT INTO spaces (code, name, space_type_id, space_status_id, building_id, capacity)
SELECT 'SAL-01', 'Sala de reuniones', space_type_id, space_status_id, building_id, 8 FROM spaces WHERE code = 'SAL-02';
SELECT pg_temp.ok((SELECT count(*) FROM spaces WHERE code = 'SAL-01') = 2,
    'a soft-deleted space frees its code and name (Q3)');
SELECT pg_temp.fails($$
    INSERT INTO asset_movements (asset_id, type, to_space_id)
    SELECT 12345, 'entry', id FROM spaces WHERE code = 'SAL-01' AND deleted_at IS NOT NULL$$,
    '23514', 'asset_movements_destination_active', 'assets cannot move into a deleted space');

SELECT pg_temp.fails($$UPDATE assets SET warranty_starts_on = current_date, warranty_ends_on = NULL WHERE id = 12345$$,
    '23514', 'assets_warranty_pair_check', 'warranty dates come in pairs (RF_16)');
SELECT pg_temp.fails($$UPDATE assets SET warranty_starts_on = current_date, warranty_ends_on = current_date - 1 WHERE id = 12345$$,
    '23514', 'assets_warranty_range_check', 'a warranty cannot end before it starts');

SELECT pg_temp.fails($$
    INSERT INTO assets (asset_tag, asset_type_id, condition_id)
    SELECT 'tec-0002', asset_type_id, condition_id FROM assets WHERE asset_tag = 'TEC-0002'$$,
    '23505', 'assets_asset_tag_unique', 'asset tags are unique, case-insensitively');
INSERT INTO assets (asset_tag, asset_type_id, condition_id)
SELECT 'TEC-0099', asset_type_id, condition_id FROM assets WHERE asset_tag = 'TEC-0002';
UPDATE assets SET deleted_at = now() WHERE asset_tag = 'TEC-0099';
INSERT INTO assets (asset_tag, asset_type_id, condition_id)
SELECT 'tec-0099', asset_type_id, condition_id FROM assets WHERE asset_tag = 'TEC-0002';
SELECT pg_temp.ok((SELECT count(*) FROM assets WHERE lower(asset_tag) = 'tec-0099') = 2,
    'a soft-deleted asset frees its tag (RF_02 delete is not RF_29 baja)');
SELECT pg_temp.fails($$
    INSERT INTO asset_movements (asset_id, type, to_space_id)
    SELECT a.id, 'entry', s.id FROM assets a, spaces s
    WHERE a.asset_tag = 'TEC-0099' AND s.code = 'LAB-110'$$,
    '23514', 'asset_movements_asset_rules', 'a deleted asset cannot move');

SELECT pg_temp.fails($$INSERT INTO brands (name) VALUES ('DELL')$$,
    '23505', 'brands_name_unique', 'brands are unique, case-insensitively (Q7)');
SELECT pg_temp.fails($$INSERT INTO asset_categories (name) VALUES ('CÓMPUTO')$$,
    '23505', 'asset_categories_name_unique', 'case-insensitive uniqueness folds accented capitals (needs a UTF-8/ICU locale)');

-- -----------------------------------------------------------------------------
-- 8. Users, roles, notifications
-- -----------------------------------------------------------------------------
SELECT pg_temp.fails($$INSERT INTO users (name, email) VALUES ('Eva', 'Eva@seti.test')$$,
    '23514', 'users_email_lowercase_check', 'emails are stored lowercase (Q32)');
SELECT pg_temp.fails($$INSERT INTO users (name, email) VALUES ('Ana 2', 'ana@seti.test')$$,
    '23505', 'users_email_unique', 'emails are unique (RF_91)');
SELECT pg_temp.fails($$INSERT INTO users (name, email) VALUES ('Sin arroba', 'no-es-un-correo')$$,
    '23514', 'users_email_format_check', 'emails must look like an email address');
SELECT pg_temp.fails($$UPDATE users SET deactivated_by = id WHERE email = 'dora@seti.test'$$,
    '23514', 'users_deactivation_check', 'deactivated_by requires deactivated_at');
UPDATE users
   SET deactivated_at = now(), deactivated_by = (SELECT id FROM users WHERE email = 'carla@seti.test')
 WHERE email = 'dora@seti.test';

INSERT INTO model_has_roles (role_id, model_type, model_id)
SELECT r.id, 'user', u.id FROM roles r, users u WHERE r.name = 'Administrador' AND u.email = 'ana@seti.test';
INSERT INTO model_has_roles (role_id, model_type, model_id)
SELECT r.id, 'user', u.id FROM roles r, users u WHERE r.name IN ('Visualizador', 'Reservante') AND u.email = 'beto@seti.test';
INSERT INTO model_has_roles (role_id, model_type, model_id)
SELECT r.id, 'user', u.id FROM roles r, users u WHERE r.name = 'Superusuario' AND u.email = 'carla@seti.test';
SELECT pg_temp.fails($$
    INSERT INTO model_has_roles (role_id, model_type, model_id)
    SELECT id, 'user', 999999 FROM roles WHERE name = 'Reportes'$$,
    '23503', 'model_has_roles_model_id_fkey', 'roles can only be held by existing users (Q20)');
SELECT pg_temp.fails($$
    INSERT INTO model_has_roles (role_id, model_type, model_id)
    SELECT r.id, 'App\Models\User', u.id FROM roles r, users u
    WHERE r.name = 'Reportes' AND u.email = 'carla@seti.test'$$,
    '23514', 'model_has_roles_model_type_check', 'role holders are stored with the morph alias "user"');

INSERT INTO notifications (id, type, notifiable_type, notifiable_id, data, created_at)
SELECT gen_random_uuid(), 'reservation.created', 'user', u.id,
       jsonb_build_object('reservation_id', 1, 'space', 'LAB-110', 'dates', jsonb_build_array('2026-10-01')), now()
FROM users u WHERE u.email = 'ana@seti.test';
SELECT pg_temp.fails($$
    INSERT INTO notifications (id, type, notifiable_type, notifiable_id, data)
    VALUES (gen_random_uuid(), 'reservation.created', 'user', 999999, '{}')$$,
    '23503', 'notifications_notifiable_id_fkey', 'notifications go to existing users (RF_47/RF_48)');
SELECT pg_temp.fails($$
    INSERT INTO notifications (id, type, notifiable_type, notifiable_id, data)
    SELECT gen_random_uuid(), 'reservation.created', 'user', id, '[]' FROM users LIMIT 1$$,
    '23514', 'notifications_data_check', 'a notification payload is a JSON object');

-- -----------------------------------------------------------------------------
-- 9. Files and report generations (Q17, Q18, Q31)
-- -----------------------------------------------------------------------------
SELECT pg_temp.fails($$SELECT pg_temp.add_file('x.bmp', 'image/bmp', 1000)$$,
    '23514', 'files_mime_type_check', 'only whitelisted MIME types are accepted');
SELECT pg_temp.fails($$SELECT pg_temp.add_file('grande.jpg', 'image/jpeg', 11 * 1024 * 1024)$$,
    '23514', 'files_size_check', 'images are limited to 10 MB');
SELECT pg_temp.add_file('factura.pdf', 'application/pdf', 15 * 1024 * 1024);
SELECT pg_temp.fails($$
    INSERT INTO files (disk, path, original_name, mime_type, size_bytes, sha256)
    VALUES ('local', 'y.png', 'y.png', 'image/png', 10, 'XYZ')$$,
    '23514', 'files_sha256_check', 'sha256 must be 64 lowercase hex characters');
INSERT INTO asset_files (asset_id, file_id, kind)
SELECT a.id, pg_temp.add_file('activos/foto1.jpg', 'image/jpeg', 2000000), 'photo'
FROM assets a WHERE a.asset_tag = 'TEC-0002';
SELECT pg_temp.fails($$INSERT INTO asset_files (asset_id, file_id, kind) SELECT 12345, file_id, 'photo' FROM asset_files LIMIT 1$$,
    '23505', 'asset_files_file_id_unique', 'a file belongs to a single asset');
SELECT pg_temp.fails($$
    INSERT INTO asset_files (asset_id, file_id, kind)
    SELECT 12345, pg_temp.add_file('video.pdf', 'application/pdf', 100), 'video'$$,
    '23514', 'asset_files_kind_check', 'an asset file is a photo or a document');

INSERT INTO report_generations (report_type_id, format, filters, row_count, generated_by, file_id)
SELECT rt.id, 'pdf', '{"space_id": 1}', 3, u.id, pg_temp.add_file('reportes/inventario.pdf', 'application/pdf', 5000)
FROM report_types rt, users u WHERE rt.code = 'assets_inventory' AND u.email = 'carla@seti.test';
SELECT pg_temp.fails($$
    INSERT INTO report_generations (report_type_id, format, row_count, generated_by)
    SELECT rt.id, 'csv', 0, u.id FROM report_types rt, users u
    WHERE rt.code = 'spaces' AND u.email = 'carla@seti.test'$$,
    '23514', 'report_generations_format_check', 'exports are PDF or XLSX (RF_70/RF_71)');
SELECT pg_temp.fails($$
    INSERT INTO report_generations (report_type_id, format, filters, row_count, generated_by)
    SELECT rt.id, 'xlsx', '[]', 0, u.id FROM report_types rt, users u
    WHERE rt.code = 'spaces' AND u.email = 'carla@seti.test'$$,
    '23514', 'report_generations_filters_check', 'report filters are a JSON object (RF_72)');
DELETE FROM files WHERE path = 'reportes/inventario.pdf';
SELECT pg_temp.ok((SELECT file_id FROM report_generations WHERE format = 'pdf') IS NULL,
    'pruning an exported file keeps its generation record (RF_84)');

-- -----------------------------------------------------------------------------
-- 10. Audit trail (ADR-0002)
-- -----------------------------------------------------------------------------
INSERT INTO audit_logs (actor_id, actor_label, action, entity_type, entity_id, entity_label,
                        old_values, new_values, channel, ip_address, user_agent, request_id)
SELECT u.id, u.name, 'updated', 'asset', a.id, a.code || ' · ' || a.name,
       '{"observations": null}', '{"observations": "Revisado"}', 'web', '192.168.1.20', 'Mozilla/5.0', gen_random_uuid()
FROM users u, assets a WHERE u.email = 'ana@seti.test' AND a.asset_tag = 'TEC-0002';
INSERT INTO audit_logs (action, channel, ip_address, new_values)
VALUES ('login_failed', 'web', '10.0.0.7', '{"email": "intruso@seti.test"}');
SELECT pg_temp.fails($$UPDATE audit_logs SET action = 'deleted'$$, '23001', 'audit_logs_append_only', 'audit entries cannot be edited (RF_68)');
SELECT pg_temp.fails($$DELETE FROM audit_logs$$,                    '23001', 'audit_logs_append_only', 'audit entries cannot be deleted');
SELECT pg_temp.fails($$TRUNCATE audit_logs$$,                       '23001', 'audit_logs_append_only', 'the audit table cannot be truncated');
SELECT pg_temp.fails($$INSERT INTO audit_logs (action, channel) VALUES ('hacked', 'web')$$,
    '23514', 'audit_logs_action_check', 'audit actions come from a fixed list');
SELECT pg_temp.fails($$INSERT INTO audit_logs (action, channel, entity_type) VALUES ('created', 'web', 'asset')$$,
    '23514', 'audit_logs_entity_check', 'entity_type and entity_id come together');

-- -----------------------------------------------------------------------------
-- 11. Search (Q34)
-- -----------------------------------------------------------------------------
SELECT pg_temp.ok(EXISTS (
    SELECT 1 FROM spaces WHERE code = 'SAL-02'
      AND search_text LIKE '%' || lower(immutable_unaccent('salon')) || '%'),
    'accent-insensitive search: "salon" finds "Salón Multiuso" (RF_42)');
SELECT pg_temp.ok(EXISTS (SELECT 1 FROM assets WHERE asset_tag = 'TEC-0001' AND search_text LIKE '%latitude%'),
    'asset search by model (RF_07)');
SELECT pg_temp.ok(EXISTS (SELECT 1 FROM assets WHERE id = 14 AND search_text LIKE '%a-0014%'),
    'asset search by system code (RF_07)');
SELECT pg_temp.ok(EXISTS (SELECT 1 FROM users WHERE email = 'ana@seti.test' AND search_text LIKE '%administradora%'),
    'user search by name (RF_95)');

-- -----------------------------------------------------------------------------
-- 12. Reporting contract (ADR-0010)
-- -----------------------------------------------------------------------------
SELECT pg_temp.ok((SELECT count(*) FROM reporting.assets) > 0,             'view reporting.assets');
SELECT pg_temp.ok((SELECT count(*) FROM reporting.asset_components) = 0,   'view reporting.asset_components');
SELECT pg_temp.ok((SELECT count(*) FROM reporting.spaces) > 0,             'view reporting.spaces');
SELECT pg_temp.ok((SELECT count(*) FROM reporting.space_managers) = 1,     'view reporting.space_managers');
SELECT pg_temp.ok((SELECT count(*) FROM reporting.reservations) > 0,       'view reporting.reservations');
SELECT pg_temp.ok((SELECT count(*) FROM reporting.space_occupancy) > 0,    'view reporting.space_occupancy');
SELECT pg_temp.ok((SELECT count(*) FROM reporting.maintenance) = 2,        'view reporting.maintenance');
SELECT pg_temp.ok((SELECT count(*) FROM reporting.asset_movements) = 4,    'view reporting.asset_movements');
SELECT pg_temp.ok((SELECT count(*) FROM reporting.asset_events) = 4,       'view reporting.asset_events');
SELECT pg_temp.ok((SELECT count(*) FROM reporting.users) = 4,              'view reporting.users');
SELECT pg_temp.ok((SELECT count(*) FROM reporting.user_roles) = 4,         'view reporting.user_roles');
SELECT pg_temp.ok((SELECT count(*) FROM reporting.audit_logs) = 2,         'view reporting.audit_logs');
SELECT pg_temp.ok((SELECT count(*) FROM reporting.report_generations) = 1, 'view reporting.report_generations');
SELECT pg_temp.ok((SELECT count(*) FROM reporting.dashboard_kpis) = 1,     'view reporting.dashboard_kpis');

SELECT pg_temp.ok((SELECT display_name FROM reporting.assets WHERE asset_tag = 'TEC-0001') = 'Laptop Dell Latitude 5420',
    'display name falls back to type + brand + model (Q6)');
SELECT pg_temp.ok((SELECT warranty_state FROM reporting.assets WHERE asset_tag = 'TEC-0002') = 'valid',
    'warranty state is derived (RF_17/RF_75)');
SELECT pg_temp.ok((SELECT effective_status FROM reporting.reservations WHERE title = 'Pasada') = 'finished',
    'a past reservation is reported as finished, with no job involved (Q14)');
SELECT pg_temp.ok((SELECT effective_status FROM reporting.reservations WHERE title = 'Tutoría grupal') = 'cancelled',
    'cancelled reservations stay in history (RF_50/RF_52)');
SELECT pg_temp.ok((SELECT effective_status FROM reporting.reservations WHERE title = 'Reemplazo') = 'upcoming',
    'a future reservation is reported as upcoming');
SELECT pg_temp.ok((SELECT was_modified FROM reporting.reservations WHERE title = 'Otro espacio'),
    'modified is derived from the version counter (Q14)');
SELECT pg_temp.ok(
    (SELECT reservations = 2 AND booked_hours = 2.00 FROM reporting.space_occupancy WHERE space_code = 'LAB-110'),
    'occupancy counts confirmed bookings and hours per space and day (RF_59/RF_79)');
SELECT pg_temp.ok((SELECT managers FROM reporting.spaces WHERE code = 'LAB-110') = 'Ana Administradora',
    'a space lists its managers (RF_35/RF_96)');
SELECT pg_temp.ok((SELECT roles FROM reporting.users WHERE email = 'beto@seti.test') = 'Reservante, Visualizador',
    'a user can hold several roles (Q20)');
SELECT pg_temp.ok((SELECT account_status FROM reporting.users WHERE email = 'dora@seti.test') = 'deactivated',
    'deactivated accounts are reported (RF_101)');
SELECT pg_temp.ok((SELECT maintenance_pending FROM reporting.dashboard_kpis) = 1,
    'KPI: pending maintenance = scheduled + in progress');
SELECT pg_temp.ok(NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'reporting' AND column_name IN ('password', 'remember_token', 'token', 'payload')),
    'no secrets are exposed in the reporting schema');

-- PowerBI's role reads the views and nothing else.
SELECT pg_temp.ok(has_schema_privilege('powerbi_reader', 'reporting', 'USAGE'),
    'powerbi_reader can use the reporting schema');
SELECT pg_temp.ok(has_table_privilege('powerbi_reader', 'reporting.users', 'SELECT'),
    'powerbi_reader can read the reporting views');
SELECT pg_temp.ok(NOT has_table_privilege('powerbi_reader', 'public.users', 'SELECT'),
    'powerbi_reader cannot read base tables (no password hashes)');
SET LOCAL ROLE powerbi_reader;
SELECT count(*) FROM reporting.assets;
SELECT count(*) FROM reporting.asset_components;
SELECT count(*) FROM reporting.spaces;
SELECT count(*) FROM reporting.space_managers;
SELECT count(*) FROM reporting.reservations;
SELECT count(*) FROM reporting.space_occupancy;
SELECT count(*) FROM reporting.maintenance;
SELECT count(*) FROM reporting.asset_movements;
SELECT count(*) FROM reporting.asset_events;
SELECT count(*) FROM reporting.users;
SELECT count(*) FROM reporting.user_roles;
SELECT count(*) FROM reporting.audit_logs;
SELECT count(*) FROM reporting.report_generations;
SELECT count(*) FROM reporting.dashboard_kpis;
RESET ROLE;
SELECT pg_temp.ok(true, 'powerbi_reader queried every reporting view');

DO $$ BEGIN RAISE NOTICE 'ALL SMOKE TESTS PASSED'; END $$;
ROLLBACK;
