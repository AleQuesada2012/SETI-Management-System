<?php

namespace App\Enums;

use App\Enums\PermissionName as P;

/**
 * The five seeded, protected roles (roles.is_system = true; Q27/Q28, ADR-0008) and their
 * initial permission matrix (docs/db/seed.sql, Agent-DB.md §5). The Superusuario may create
 * custom roles from the PermissionName catalog; system roles cannot be renamed or deleted.
 *
 * Administrador's mutating permissions only apply to the spaces they manage (the ★ rule,
 * enforced in the policies through User::managesSpace()).
 */
enum SystemRole: string
{
    case Superusuario = 'Superusuario';
    case Administrador = 'Administrador';
    case Reportes = 'Reportes';
    case Visualizador = 'Visualizador';
    case Reservante = 'Reservante';

    /** Spanish description shown by RF_100 (stored in roles.description). */
    public function description(): string
    {
        return match ($this) {
            self::Superusuario => 'Acceso total: usuarios, roles, encargados de espacios, catálogos y todos los módulos.',
            self::Administrador => 'Gestiona los activos, datos y reservas de los espacios que tiene asignados.',
            self::Reportes => 'Consulta tableros y genera y exporta reportes, incluida la auditoría.',
            self::Visualizador => 'Solo consulta: no puede crear, modificar ni eliminar información.',
            self::Reservante => 'Puede reservar espacios y modificar o cancelar sus propias reservas.',
        };
    }

    /**
     * Initial permissions of the role (seed.sql). Superusuario holds every permission.
     *
     * @return list<P>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Superusuario => P::cases(),
            self::Administrador => [
                P::AssetsView, P::AssetsCreate, P::AssetsUpdate, P::AssetsDelete, P::AssetsMove,
                P::AssetsRegisterEvents, P::AssetsManageComponents, P::AssetsManageFiles,
                P::MaintenanceView, P::MaintenanceManage,
                P::SpacesView, P::SpacesCreate, P::SpacesUpdate, P::SpacesDelete, P::SpacesManagePhotos,
                P::ReservationsView, P::ReservationsCreate, P::ReservationsManageOwn, P::ReservationsManageAny,
                P::DashboardsView, P::UsersView, P::BrandsManage, P::SuppliersManage,
            ],
            self::Reportes => [
                P::DashboardsView, P::ReportsView, P::ReportsExport, P::ReportsAudit,
                P::AssetsView, P::MaintenanceView, P::SpacesView, P::ReservationsView,
            ],
            self::Visualizador => [
                P::AssetsView, P::MaintenanceView, P::SpacesView, P::ReservationsView,
            ],
            self::Reservante => [
                P::SpacesView, P::ReservationsView, P::ReservationsCreate, P::ReservationsManageOwn,
            ],
        };
    }
}
