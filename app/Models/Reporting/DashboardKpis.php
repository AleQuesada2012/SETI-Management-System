<?php

namespace App\Models\Reporting;

/**
 * The single row of reporting.dashboard_kpis: the mockup's KPI cards, computed live.
 *
 * @property int $assets_total
 * @property int $assets_in_service
 * @property int $spaces_available
 * @property int $spaces_total
 * @property int $reservations_today
 * @property int $maintenance_pending
 */
class DashboardKpis extends ReportingView
{
    protected $table = 'reporting.dashboard_kpis';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assets_total' => 'integer',
            'assets_in_service' => 'integer',
            'spaces_available' => 'integer',
            'spaces_total' => 'integer',
            'reservations_today' => 'integer',
            'maintenance_pending' => 'integer',
            'computed_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrFail();
    }
}
