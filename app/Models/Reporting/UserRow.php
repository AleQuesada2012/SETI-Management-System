<?php

namespace App\Models\Reporting;

/**
 * A row of reporting.users: account status and roles, never secrets (RF_69, RF_90, RF_92, RF_101).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $account_status active | deactivated
 * @property bool $invitation_pending
 * @property string|null $roles "Reservante, Visualizador"
 */
class UserRow extends ReportingView
{
    protected $table = 'reporting.users';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invitation_pending' => 'boolean',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
