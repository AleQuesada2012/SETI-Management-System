<?php

namespace App\Reports;

use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The CSV feed that Power BI Service refreshes from (RF_61, RF_73): one dataset per `reporting` view (ADR-0010),
 * served over the site's HTTPS so the scheduled refresh needs no gateway (docs/decisions.md § Power BI feed).
 *
 * Each dataset lists its columns and their types; that list is the contract the semantic model in docs/powerbi
 * types its columns from. Emails, free-text notes and the audit log are left out on purpose.
 */
class PowerBiFeed
{
    /** Cache key of the moment Power BI last read the feed, shown on the dashboards page (RF_73). */
    public const LAST_PULLED_KEY = 'reports.powerbi_feed.last_pulled_at';

    /**
     * Dataset => [view, order column, [column => type]]. Types: int, decimal, text, bool, date, datetime.
     *
     * @var array<string, array{view: string, order: string, columns: array<string, string>}>
     */
    public const DATASETS = [
        'assets' => ['view' => 'reporting.assets', 'order' => 'id', 'columns' => [
            'id' => 'int', 'code' => 'text', 'asset_tag' => 'text', 'display_name' => 'text', 'name' => 'text',
            'category' => 'text', 'asset_type' => 'text', 'brand' => 'text', 'model' => 'text',
            'condition' => 'text', 'condition_color' => 'text', 'status' => 'text',
            'space_id' => 'int', 'space_code' => 'text', 'space_name' => 'text', 'building' => 'text',
            'responsible_user_id' => 'int', 'responsible_name' => 'text', 'supplier' => 'text',
            'acquired_on' => 'date', 'warranty_starts_on' => 'date', 'warranty_ends_on' => 'date',
            'warranty_state' => 'text', 'created_at' => 'datetime', 'updated_at' => 'datetime',
        ]],
        'spaces' => ['view' => 'reporting.spaces', 'order' => 'id', 'columns' => [
            'id' => 'int', 'code' => 'text', 'name' => 'text', 'space_type' => 'text', 'status' => 'text',
            'status_color' => 'text', 'is_reservable' => 'bool', 'is_occupied' => 'bool', 'building' => 'text',
            'floor' => 'text', 'capacity' => 'int', 'managers' => 'text', 'asset_count' => 'int',
            'created_at' => 'datetime',
        ]],
        'reservations' => ['view' => 'reporting.reservations', 'order' => 'id', 'columns' => [
            'id' => 'int', 'series_id' => 'text', 'space_id' => 'int', 'space_code' => 'text', 'space_name' => 'text',
            'space_deleted' => 'bool', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'local_date' => 'date',
            'attendees' => 'int', 'responsible_id' => 'int', 'responsible_name' => 'text',
            'created_by_name' => 'text', 'status' => 'text', 'effective_status' => 'text',
            'was_modified' => 'bool', 'cancelled_at' => 'datetime', 'created_at' => 'datetime',
        ]],
        'space_occupancy' => ['view' => 'reporting.space_occupancy', 'order' => 'local_date', 'columns' => [
            'space_id' => 'int', 'space_code' => 'text', 'space_name' => 'text', 'local_date' => 'date',
            'reservations' => 'int', 'booked_hours' => 'decimal', 'attendees' => 'int',
        ]],
        'maintenance' => ['view' => 'reporting.maintenance', 'order' => 'id', 'columns' => [
            'id' => 'int', 'asset_id' => 'int', 'asset_code' => 'text', 'asset_name' => 'text', 'kind' => 'text',
            'status' => 'text', 'scheduled_for' => 'date', 'started_on' => 'date', 'completed_on' => 'date',
            'reference_date' => 'date', 'performed_by' => 'text', 'registered_by_name' => 'text',
            'created_at' => 'datetime',
        ]],
        'asset_events' => ['view' => 'reporting.asset_events', 'order' => 'id', 'columns' => [
            'id' => 'int', 'asset_id' => 'int', 'asset_code' => 'text', 'asset_name' => 'text', 'kind' => 'text',
            'occurred_on' => 'date', 'registered_by_name' => 'text', 'evidence_count' => 'int',
            'created_at' => 'datetime',
        ]],
        'asset_movements' => ['view' => 'reporting.asset_movements', 'order' => 'id', 'columns' => [
            'id' => 'int', 'asset_id' => 'int', 'asset_code' => 'text', 'asset_name' => 'text', 'type' => 'text',
            'from_space_id' => 'int', 'from_space_name' => 'text', 'to_space_id' => 'int', 'to_space_name' => 'text',
            'moved_at' => 'datetime', 'moved_by_name' => 'text',
        ]],
        'users' => ['view' => 'reporting.users', 'order' => 'id', 'columns' => [
            'id' => 'int', 'name' => 'text', 'account_status' => 'text', 'invitation_pending' => 'bool',
            'roles' => 'text', 'last_login_at' => 'datetime', 'deactivated_at' => 'datetime',
            'created_at' => 'datetime',
        ]],
        'user_roles' => ['view' => 'reporting.user_roles', 'order' => 'user_id', 'columns' => [
            'user_id' => 'int', 'role_id' => 'int', 'role' => 'text',
        ]],
        'report_generations' => ['view' => 'reporting.report_generations', 'order' => 'id', 'columns' => [
            'id' => 'int', 'report_code' => 'text', 'report_name' => 'text', 'report_category' => 'text',
            'format' => 'text', 'row_count' => 'int', 'generated_by' => 'int', 'generated_by_name' => 'text',
            'generated_at' => 'datetime',
        ]],
    ];

    public static function exists(string $dataset): bool
    {
        return array_key_exists($dataset, self::DATASETS);
    }

    /**
     * The dataset's rows in view order, read through the feed's connection: the read-only `powerbi_reader` login
     * when POWERBI_DB_USERNAME is set (ADR-0010), otherwise the application's own.
     */
    public function query(string $dataset): Builder
    {
        $definition = self::DATASETS[$dataset];

        return DB::connection(config('reports.powerbi_feed.connection'))
            ->table($definition['view'])
            ->select(array_keys($definition['columns']))
            ->orderBy($definition['order']);
    }

    /**
     * Writes the dataset as RFC 4180 CSV in UTF-8: a header row, dates as Y-m-d and timestamps as Costa Rica local
     * time (Y-m-d H:i:s), booleans as true/false and missing values as empty fields.
     *
     * @param  resource  $output
     */
    public function write(string $dataset, $output): void
    {
        $columns = self::DATASETS[$dataset]['columns'];
        fputcsv($output, array_keys($columns), escape: '');

        foreach ($this->query($dataset)->cursor() as $row) {
            $values = [];
            foreach ($columns as $column => $type) {
                $values[] = $this->format($row->{$column}, $type);
            }
            fputcsv($output, $values, escape: '');
        }
    }

    public function recordPull(): void
    {
        Cache::forever(self::LAST_PULLED_KEY, now()->toIso8601String());
    }

    public static function lastPulledAt(): ?Carbon
    {
        $value = Cache::get(self::LAST_PULLED_KEY);

        return is_string($value) ? Carbon::parse($value)->setTimezone(config('app.timezone')) : null;
    }

    private function format(mixed $value, string $type): string
    {
        if ($value === null) {
            return '';
        }

        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'date' => Carbon::parse($value)->format('Y-m-d'),
            'datetime' => ($value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value))
                ->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            default => (string) $value,
        };
    }
}
