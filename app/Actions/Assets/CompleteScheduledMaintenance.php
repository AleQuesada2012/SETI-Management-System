<?php

namespace App\Actions\Assets;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * RF_27 · Mark a scheduled maintenance as done: it becomes `completed` on the day the work was done (any real day up to
 * today, not before the acquisition, as in RF_10/RF_26), with who did it and the description of what was done. The
 * planned day stays for the record. Like RF_10/RF_26, the asset's status and condition do not change; a repair that
 * fixes a damage is RF_11. Who: MaintenanceRecordPolicy::update (★).
 */
final class CompleteScheduledMaintenance
{
    /**
     * @param  array<string, mixed>  $input  performed_on, performed_by, description
     */
    public function handle(User $actor, MaintenanceRecord $record, array $input): MaintenanceRecord
    {
        Gate::forUser($actor)->authorize('update', $record);
        ScheduleMaintenance::ensureScheduled($record);

        $rules = RegisterMaintenance::rules($record->asset);
        unset($rules['kind']);

        /** @var array{performed_on: string, performed_by: string, description: string} $data */
        $data = Validator::make($input, $rules, RegisterMaintenance::messages(), __('assets.maintenance.fields'))->validate();

        try {
            return DB::transaction(function () use ($actor, $record, $data): MaintenanceRecord {
                $current = MaintenanceRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $current);
                ScheduleMaintenance::ensureScheduled($current);

                $current->update([
                    'status' => MaintenanceStatus::Completed,
                    'started_on' => $data['performed_on'],
                    'completed_on' => $data['performed_on'],
                    'performed_by' => $data['performed_by'],
                    'description' => $data['description'],
                ]);

                return $current;
            });
        } catch (QueryException $e) {
            $field = match (ConstraintViolation::ruleName($e)) {
                'maintenance_records_description_check' => 'description',
                default => 'form',
            };

            throw ConstraintViolation::toValidationException($e, field: $field);
        }
    }
}
