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
 * RF_27 · Cancel a scheduled maintenance, with an optional reason: the row becomes `cancelled` and stays for the history
 * and the reports (Agent-DB.md §8.3). Allowed whatever the asset's status, so the maintenance of an asset lost or
 * decommissioned meanwhile can be cleaned up. Who: MaintenanceRecordPolicy::update (★).
 */
final class CancelScheduledMaintenance
{
    /**
     * @param  array<string, mixed>  $input  cancellation_reason (optional)
     */
    public function handle(User $actor, MaintenanceRecord $record, array $input = []): MaintenanceRecord
    {
        Gate::forUser($actor)->authorize('update', $record);
        ScheduleMaintenance::ensureScheduled($record, assetMustBeAllowed: false);

        /** @var array{cancellation_reason?: string|null} $data */
        $data = Validator::make($input, [
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ], [], __('assets.maintenance.fields'))->validate();

        try {
            return DB::transaction(function () use ($actor, $record, $data): MaintenanceRecord {
                $current = MaintenanceRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $current);
                ScheduleMaintenance::ensureScheduled($current, assetMustBeAllowed: false);

                $current->update([
                    'status' => MaintenanceStatus::Cancelled,
                    'cancellation_reason' => filled($data['cancellation_reason'] ?? null) ? $data['cancellation_reason'] : null,
                ]);

                return $current;
            });
        } catch (QueryException $e) {
            throw ConstraintViolation::toValidationException($e, field: 'form');
        }
    }
}
