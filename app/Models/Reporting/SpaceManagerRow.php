<?php

namespace App\Models\Reporting;

use Illuminate\Support\Carbon;

/**
 * A row of reporting.space_managers: one space manager of one non-deleted space (RF_96, RF_97).
 *
 * @property int $space_id
 * @property string $space_code
 * @property string $space_name
 * @property int $user_id
 * @property string $manager_name
 * @property string $manager_email
 * @property Carbon $assigned_at
 * @property string|null $space_type only when read through SpaceManagers::ofManager() (joined from reporting.spaces)
 */
class SpaceManagerRow extends ReportingView
{
    protected $table = 'reporting.space_managers';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }
}
