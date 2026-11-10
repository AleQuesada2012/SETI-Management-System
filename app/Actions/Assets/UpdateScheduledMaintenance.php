<?php

namespace App\Actions\Assets;

use App\Enums\MaintenanceKind;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * RF_27 · Change a scheduled maintenance: its kind, planned day (still after today) or description. A plain update of
 * the `scheduled` row (Agent-DB.md §8.3); the Auditable model records the change with the old and new values. Who:
 * MaintenanceRecordPolicy::update (★).
 */
final class UpdateScheduledMaintenance
{
    /**
     * @param  array<string, mixed>  $input  kind, scheduled_for, description
     */
    public function handle(User $actor, MaintenanceRecord $record, array $input): MaintenanceRecord
    {
        Gate::forUser($actor)->authorize('update', $record);
        ScheduleMaintenance::ensureScheduled($record);

        /** @var array{kind: string, scheduled_for: string, description: string} $data */
        $data = Validator::make($input, ScheduleMaintenance::rules(), ScheduleMaintenance::messages(), __('assets.maintenance.fields'))->validate();

        try {
            return DB::transaction(function () use ($actor, $record, $data): MaintenanceRecord {
                $current = MaintenanceRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
                // Locked as well: a loss or decommission registered meanwhile (RegisterAssetEvent) waits or is seen.
                $current->setRelation('asset', Asset::withTrashed()->whereKey($current->asset_id)->lockForUpdate()->firstOrFail());
                Gate::forUser($actor)->authorize('update', $current);
                ScheduleMaintenance::ensureScheduled($current);

                $current->update([
                    'kind' => MaintenanceKind::from($data['kind']),
                    'scheduled_for' => $data['scheduled_for'],
                    'description' => $data['description'],
                ]);

                return $current;
            });
        } catch (QueryException $e) {
            $field = match (ConstraintViolation::ruleName($e)) {
                'maintenance_records_description_check' => 'description',
                'maintenance_records_event_kind_check' => 'kind',
                default => 'form',
            };

            throw ConstraintViolation::toValidationException($e, field: $field);
        }
    }
}
