<?php

namespace App\Enums;

/**
 * What an audit entry records (audit_logs.action, fixed list checked by the database).
 */
enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Attached = 'attached';
    case Detached = 'detached';
    case Generated = 'generated';
    case RoleAssigned = 'role_assigned';
    case RoleRemoved = 'role_removed';
    case ManagerAssigned = 'manager_assigned';
    case ManagerRemoved = 'manager_removed';
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case PasswordSet = 'password_set';
    case PasswordReset = 'password_reset';
    case Deactivated = 'deactivated';
    case Reactivated = 'reactivated';

    /** Spanish label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Creó',
            self::Updated => 'Editó',
            self::Deleted => 'Eliminó',
            self::Restored => 'Restauró',
            self::Attached => 'Adjuntó',
            self::Detached => 'Retiró un archivo de',
            self::Generated => 'Generó',
            self::RoleAssigned => 'Asignó un rol a',
            self::RoleRemoved => 'Retiró un rol a',
            self::ManagerAssigned => 'Asignó un encargado a',
            self::ManagerRemoved => 'Retiró un encargado de',
            self::Login => 'Inició sesión',
            self::Logout => 'Cerró sesión',
            self::LoginFailed => 'Intento fallido de inicio de sesión',
            self::PasswordSet => 'Definió su contraseña',
            self::PasswordReset => 'Restableció su contraseña',
            self::Deactivated => 'Desactivó',
            self::Reactivated => 'Reactivó',
        };
    }
}
