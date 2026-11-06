<?php

namespace App\Actions\Assets;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * RF_11 · Cancel a repair in progress that was registered by mistake or will not be done (Agent-DB.md §8.4): the repair
 * becomes `cancelled` with an optional reason, and the asset goes back in service with its condition unchanged, in one
 * transaction. The row stays for the history and the reports. Who: MaintenanceRecordPolicy::update (★).
 */
final class CancelRepair
{
    /**
     * @param  array<string, mixed>  $input  cancellation_reason (optional)
     */
    public function handle(User $actor, MaintenanceRecord $repair, array $input = []): MaintenanceRecord
    {
        Gate::forUser($actor)->authorize('update', $repair);
        CompleteRepair::ensureInProgress($repair);

        /** @var array{cancellation_reason?: string|null} $data */
        $data = Validator::make($input, [
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ], [], __('assets.maintenance.fields'))->validate();

        try {
            return DB::transaction(function () use ($actor, $repair, $data): MaintenanceRecord {
                $current = MaintenanceRecord::query()->whereKey($repair->id)->lockForUpdate()->firstOrFail();
                $asset = Asset::query()->whereKey($current->asset_id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $current);
                CompleteRepair::ensureInProgress($current);

                $current->update([
                    'status' => MaintenanceStatus::Cancelled,
                    'cancellation_reason' => filled($data['cancellation_reason'] ?? null) ? $data['cancellation_reason'] : null,
                ]);

                if ($asset->status === AssetStatus::InMaintenance) {
                    $asset->forceFill(['status' => AssetStatus::InService])->save();
                }

                return $current;
            });
        } catch (QueryException $e) {
            throw ConstraintViolation::toValidationException($e, field: 'form');
        }
    }
}
