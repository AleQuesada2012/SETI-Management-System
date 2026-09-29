<?php

namespace App\Enums;

/**
 * The fixed permission catalog (docs/db/seed.sql, Agent-DB.md §5). Custom roles pick from it (Q27).
 *
 * Always check permissions through this enum, never through a string literal:
 *     $user->can(PermissionName::AssetsCreate)         // PHP
 *
 *     @can(App\Enums\PermissionName::AssetsCreate)      // Blade
 *     ->middleware('can:'.PermissionName::AssetsView->value)   // routes
 *
 * A new permission is a new case here + a migration or seeder change + the role matrix in SystemRole.
 */
enum PermissionName: string
{
    case AssetsView = 'assets.view';
    case AssetsCreate = 'assets.create';
    case AssetsUpdate = 'assets.update';
    case AssetsDelete = 'assets.delete';
    case AssetsMove = 'assets.move';
    case AssetsRegisterEvents = 'assets.register_events';
    case AssetsManageComponents = 'assets.manage_components';
    case AssetsManageFiles = 'assets.manage_files';
    case MaintenanceView = 'maintenance.view';
    case MaintenanceManage = 'maintenance.manage';
    case SpacesView = 'spaces.view';
    case SpacesCreate = 'spaces.create';
    case SpacesUpdate = 'spaces.update';
    case SpacesDelete = 'spaces.delete';
    case SpacesManagePhotos = 'spaces.manage_photos';
    case SpacesAssignManagers = 'spaces.assign_managers';
    case ReservationsView = 'reservations.view';
    case ReservationsCreate = 'reservations.create';
    case ReservationsManageOwn = 'reservations.manage_own';
    case ReservationsManageAny = 'reservations.manage_any';
    case DashboardsView = 'dashboards.view';
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case ReportsAudit = 'reports.audit';
    case ReportsHistory = 'reports.history';
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDeactivate = 'users.deactivate';
    case RolesView = 'roles.view';
    case RolesAssign = 'roles.assign';
    case RolesManage = 'roles.manage';
    case CatalogsManage = 'catalogs.manage';
    case BrandsManage = 'brands.manage';
    case SuppliersManage = 'suppliers.manage';

    /** Spanish description shown by RF_100 (stored in permissions.description). */
    public function description(): string
    {
        return match ($this) {
            self::AssetsView => 'Consultar activos, su historial, componentes, garantía y archivos',
            self::AssetsCreate => 'Registrar activos',
            self::AssetsUpdate => 'Editar activos: datos, condición, responsable, categoría y garantía',
            self::AssetsDelete => 'Eliminar activos registrados por error',
            self::AssetsMove => 'Registrar movimientos y traslados de activos',
            self::AssetsRegisterEvents => 'Registrar daños, pérdidas, recuperaciones y bajas de activos',
            self::AssetsManageComponents => 'Registrar, editar y eliminar componentes de activos',
            self::AssetsManageFiles => 'Adjuntar y eliminar fotografías y documentos de activos',
            self::MaintenanceView => 'Consultar mantenimientos',
            self::MaintenanceManage => 'Registrar, programar, modificar y cancelar mantenimientos',
            self::SpacesView => 'Consultar espacios, sus activos, historial y ocupación',
            self::SpacesCreate => 'Registrar espacios',
            self::SpacesUpdate => 'Editar espacios: datos, capacidad y estado operativo',
            self::SpacesDelete => 'Eliminar espacios sin activos ni reservas vigentes',
            self::SpacesManagePhotos => 'Adjuntar y eliminar fotografías de espacios',
            self::SpacesAssignManagers => 'Asignar y retirar encargados de espacios',
            self::ReservationsView => 'Consultar el calendario y el detalle de las reservas',
            self::ReservationsCreate => 'Reservar espacios',
            self::ReservationsManageOwn => 'Modificar y cancelar las reservas propias',
            self::ReservationsManageAny => 'Modificar y cancelar reservas de otras personas',
            self::DashboardsView => 'Ver tableros e indicadores',
            self::ReportsView => 'Generar y consultar reportes',
            self::ReportsExport => 'Exportar reportes en PDF y XLSX',
            self::ReportsAudit => 'Consultar la auditoría y la actividad de los usuarios',
            self::ReportsHistory => 'Consultar los reportes generados anteriormente',
            self::UsersView => 'Consultar usuarios',
            self::UsersCreate => 'Registrar usuarios',
            self::UsersUpdate => 'Editar la información de los usuarios',
            self::UsersDeactivate => 'Desactivar y reactivar cuentas',
            self::RolesView => 'Consultar roles y sus permisos',
            self::RolesAssign => 'Asignar, retirar y cambiar roles de usuarios',
            self::RolesManage => 'Crear y editar roles personalizados',
            self::CatalogsManage => 'Gestionar catálogos: categorías, tipos, condiciones, tipos y estados de espacio, edificios',
            self::BrandsManage => 'Registrar marcas',
            self::SuppliersManage => 'Registrar y editar proveedores',
        };
    }

    /** True for read-only permissions; the Visualizador role holds only these (RF_89). */
    public function isReadOnly(): bool
    {
        return str_ends_with($this->value, '.view');
    }
}
