-- =============================================================================
-- SETI · Assets & Spaces · reference seed data (run after schema.sql)
-- Catalog values are the initial defaults agreed in the grilling (Agent-DB.md §20);
-- the Superusuario can edit catalogs later from the app. The role → permission
-- matrix is derived from the actor of each RF and is meant to be tuned.
-- After seeding permissions with SQL, run `php artisan permission:cache-reset`.
-- =============================================================================
\set ON_ERROR_STOP on
BEGIN;
SET LOCAL search_path = public;

-- -----------------------------------------------------------------------------
-- Space catalogs (Q12)
-- -----------------------------------------------------------------------------
INSERT INTO space_types (name, is_reservable, description) VALUES
    ('Laboratorio', true,  'Laboratorio de cómputo o de proyectos. Se puede reservar.'),
    ('Sala',        true,  'Sala de reuniones o aula. Se puede reservar.'),
    ('Oficina',     false, 'Oficina administrativa. No se reserva.');

INSERT INTO space_statuses (name, allows_reservations, color_group, sort_order) VALUES
    ('Disponible',       true,  'green', 1),
    ('En mantenimiento', false, 'amber', 2),
    ('Inhabilitado',     false, 'red',   3);

-- -----------------------------------------------------------------------------
-- Asset catalogs (Q6, Q8)
-- -----------------------------------------------------------------------------
INSERT INTO asset_conditions (name, color_group, description, sort_order) VALUES
    ('Buen estado', 'green', 'Funciona correctamente y no presenta daños.',            1),
    ('Regular',     'amber', 'Funciona, pero con desgaste o fallas menores.',         2),
    ('Dañado',      'red',   'No funciona o tiene daños que requieren reparación.',   3);

INSERT INTO asset_categories (name, description) VALUES
    ('Cómputo',     'Equipos de cómputo y periféricos.'),
    ('Redes',       'Equipos de red y comunicaciones.'),
    ('Audiovisual', 'Equipos de proyección, video y audio.'),
    ('Mobiliario',  'Muebles y almacenamiento.'),
    ('Otros',       'Activos que no encajan en las demás categorías.');

INSERT INTO asset_types (asset_category_id, name)
SELECT c.id, v.type_name
FROM (VALUES
    ('Cómputo',     'Computadora de escritorio'),
    ('Cómputo',     'Laptop'),
    ('Cómputo',     'Monitor'),
    ('Cómputo',     'Teclado'),
    ('Cómputo',     'Mouse'),
    ('Cómputo',     'Impresora'),
    ('Cómputo',     'Servidor'),
    ('Redes',       'Switch'),
    ('Redes',       'Router'),
    ('Redes',       'Access Point'),
    ('Audiovisual', 'Proyector'),
    ('Audiovisual', 'Televisor'),
    ('Audiovisual', 'Parlante'),
    ('Mobiliario',  'Escritorio'),
    ('Mobiliario',  'Silla'),
    ('Mobiliario',  'Mesa'),
    ('Mobiliario',  'Armario'),
    ('Mobiliario',  'Gavetero'),
    ('Mobiliario',  'Caja fuerte'),
    ('Otros',       'Ventilador'),
    ('Otros',       'Aire acondicionado'),
    ('Otros',       'Otro')
) AS v(category_name, type_name)
JOIN asset_categories c ON c.name = v.category_name;

-- -----------------------------------------------------------------------------
-- Report catalog (RF_60 menu categories; RF_63–RF_79)
-- -----------------------------------------------------------------------------
INSERT INTO report_types (code, name, category, description, sort_order) VALUES
    ('assets_inventory', 'Inventario de activos',            'assets', 'RF_63', 1),
    ('maintenance',      'Mantenimientos y reparaciones',    'assets', 'RF_66', 2),
    ('movements',        'Movimientos de inventario',        'assets', 'RF_67', 3),
    ('warranties',       'Garantías',                        'assets', 'RF_75', 4),
    ('disposals',        'Activos dados de baja o perdidos', 'assets', 'RF_76', 5),
    ('components',       'Componentes de equipos',           'assets', 'RF_77', 6),
    ('custodians',       'Responsables de activos',          'assets', 'RF_78', 7),
    ('spaces',           'Espacios',                         'spaces', 'RF_64', 8),
    ('space_detail',     'Reporte de un espacio',            'spaces', 'RF_74', 9),
    ('reservations',     'Reservaciones',                    'spaces', 'RF_65', 10),
    ('occupancy',        'Ocupación de espacios',            'spaces', 'RF_79', 11),
    ('audit',            'Auditoría',                        'audit',  'RF_68', 12),
    ('users',            'Usuarios',                         'users',  'RF_69', 13);

-- -----------------------------------------------------------------------------
-- Permission catalog (fixed; custom roles pick from it, Q27)
-- -----------------------------------------------------------------------------
INSERT INTO permissions (name, guard_name, description, created_at, updated_at)
SELECT v.name, 'web', v.description, now(), now()
FROM (VALUES
    ('assets.view',               'Consultar activos, su historial, componentes, garantía y archivos'),
    ('assets.create',             'Registrar activos'),
    ('assets.update',             'Editar activos: datos, condición, responsable, categoría y garantía'),
    ('assets.delete',             'Eliminar activos registrados por error'),
    ('assets.move',               'Registrar movimientos y traslados de activos'),
    ('assets.register_events',    'Registrar daños, pérdidas, recuperaciones y bajas de activos'),
    ('assets.manage_components',  'Registrar, editar y eliminar componentes de activos'),
    ('assets.manage_files',       'Adjuntar y eliminar fotografías y documentos de activos'),
    ('maintenance.view',          'Consultar mantenimientos'),
    ('maintenance.manage',        'Registrar, programar, modificar y cancelar mantenimientos'),
    ('spaces.view',               'Consultar espacios, sus activos, historial y ocupación'),
    ('spaces.create',             'Registrar espacios'),
    ('spaces.update',             'Editar espacios: datos, capacidad y estado operativo'),
    ('spaces.delete',             'Eliminar espacios sin activos ni reservas vigentes'),
    ('spaces.manage_photos',      'Adjuntar y eliminar fotografías de espacios'),
    ('spaces.assign_managers',    'Asignar y retirar encargados de espacios'),
    ('reservations.view',         'Consultar el calendario y el detalle de las reservas'),
    ('reservations.create',       'Reservar espacios'),
    ('reservations.manage_own',   'Modificar y cancelar las reservas propias'),
    ('reservations.manage_any',   'Modificar y cancelar reservas de otras personas'),
    ('dashboards.view',           'Ver tableros e indicadores'),
    ('reports.view',              'Generar y consultar reportes'),
    ('reports.export',            'Exportar reportes en PDF y XLSX'),
    ('reports.audit',             'Consultar la auditoría y la actividad de los usuarios'),
    ('reports.history',           'Consultar los reportes generados anteriormente'),
    ('users.view',                'Consultar usuarios'),
    ('users.create',              'Registrar usuarios'),
    ('users.update',              'Editar la información de los usuarios'),
    ('users.deactivate',          'Desactivar y reactivar cuentas'),
    ('roles.view',                'Consultar roles y sus permisos'),
    ('roles.assign',              'Asignar, retirar y cambiar roles de usuarios'),
    ('roles.manage',              'Crear y editar roles personalizados'),
    ('catalogs.manage',           'Gestionar catálogos: categorías, tipos, condiciones, tipos y estados de espacio, edificios'),
    ('brands.manage',             'Registrar marcas'),
    ('suppliers.manage',          'Registrar y editar proveedores')
) AS v(name, description);

-- -----------------------------------------------------------------------------
-- System roles (is_system = true: cannot be renamed or deleted, Q27/Q28)
-- -----------------------------------------------------------------------------
INSERT INTO roles (name, guard_name, description, is_system, created_at, updated_at) VALUES
    ('Superusuario',  'web', 'Acceso total: usuarios, roles, encargados de espacios, catálogos y todos los módulos.', true, now(), now()),
    ('Administrador', 'web', 'Gestiona los activos, datos y reservas de los espacios que tiene asignados.',            true, now(), now()),
    ('Reportes',      'web', 'Consulta tableros y genera y exporta reportes, incluida la auditoría.',                    true, now(), now()),
    ('Visualizador',  'web', 'Solo consulta: no puede crear, modificar ni eliminar información.',                        true, now(), now()),
    ('Reservante',    'web', 'Puede reservar espacios y modificar o cancelar sus propias reservas.',                     true, now(), now());

-- Superusuario holds every permission.
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
CROSS JOIN roles r
WHERE r.name = 'Superusuario' AND r.guard_name = 'web' AND p.guard_name = 'web';

-- The other system roles.
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM (VALUES
    ('Administrador', 'assets.view'),
    ('Administrador', 'assets.create'),
    ('Administrador', 'assets.update'),
    ('Administrador', 'assets.delete'),
    ('Administrador', 'assets.move'),
    ('Administrador', 'assets.register_events'),
    ('Administrador', 'assets.manage_components'),
    ('Administrador', 'assets.manage_files'),
    ('Administrador', 'maintenance.view'),
    ('Administrador', 'maintenance.manage'),
    ('Administrador', 'spaces.view'),
    ('Administrador', 'spaces.create'),
    ('Administrador', 'spaces.update'),
    ('Administrador', 'spaces.delete'),
    ('Administrador', 'spaces.manage_photos'),
    ('Administrador', 'reservations.view'),
    ('Administrador', 'reservations.create'),
    ('Administrador', 'reservations.manage_own'),
    ('Administrador', 'reservations.manage_any'),
    ('Administrador', 'dashboards.view'),
    ('Administrador', 'users.view'),
    ('Administrador', 'brands.manage'),
    ('Administrador', 'suppliers.manage'),
    ('Reportes',      'dashboards.view'),
    ('Reportes',      'reports.view'),
    ('Reportes',      'reports.export'),
    ('Reportes',      'reports.audit'),
    ('Reportes',      'assets.view'),
    ('Reportes',      'maintenance.view'),
    ('Reportes',      'spaces.view'),
    ('Reportes',      'reservations.view'),
    ('Visualizador',  'assets.view'),
    ('Visualizador',  'maintenance.view'),
    ('Visualizador',  'spaces.view'),
    ('Visualizador',  'reservations.view'),
    ('Reservante',    'spaces.view'),
    ('Reservante',    'reservations.view'),
    ('Reservante',    'reservations.create'),
    ('Reservante',    'reservations.manage_own')
) AS v(role_name, permission_name)
JOIN roles r       ON r.name = v.role_name AND r.guard_name = 'web'
JOIN permissions p ON p.name = v.permission_name AND p.guard_name = 'web';

COMMIT;
