<?php

namespace App\Actions\Assets;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceKind;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * RF_11 · Finish a repair in progress (Agent-DB.md §8.4): the repair becomes `completed` on the day it ended, the asset
 * takes the green or amber condition the user picks ("en funcionamiento") and goes back in service, in one transaction.
 * The end date is a real day, not in the future and not before the repair started. Only while the asset is in service
 * or in maintenance: a lost or decommissioned asset keeps its status. Who: MaintenanceRecordPolicy::update (★).
 */
final class CompleteRepair
{
    /**
     * @param  array<string, mixed>  $input  completed_on, condition_id
     */
    public function handle(User $actor, MaintenanceRecord $repair, array $input): MaintenanceRecord
    {
        Gate::forUser($actor)->authorize('update', $repair);
        self::ensureInProgress($repair);

        /** @var array{completed_on: string, condition_id: int|string} $data */
        $data = Validator::make($input, [
            'completed_on' => [
                'required',
                'date_format:'.RegisterAssetEvent::OCCURRED_ON_FORMAT,
                'before_or_equal:today',
                'after_or_equal:'.$repair->started_on?->toDateString(),
            ],
            'condition_id' => RegisterRepair::conditionRules(),
        ], [
            'completed_on.before_or_equal' => __('assets.maintenance.errors.future'),
            'completed_on.after_or_equal' => __('assets.maintenance.errors.before_start'),
            'condition_id.exists' => __('assets.maintenance.errors.condition'),
        ], __('assets.maintenance.fields'))->validate();

        try {
            return DB::transaction(function () use ($actor, $repair, $data): MaintenanceRecord {
                $current = MaintenanceRecord::query()->whereKey($repair->id)->lockForUpdate()->firstOrFail();
                $asset = Asset::query()->whereKey($current->asset_id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $current);
                self::ensureInProgress($current);

                $current->update(['status' => MaintenanceStatus::Completed, 'completed_on' => $data['completed_on']]);

                $asset->update(['condition_id' => (int) $data['condition_id']]);
                if ($asset->status === AssetStatus::InMaintenance) {
                    $asset->transitionTo(AssetStatus::InService);
                }

                return $current;
            });
        } catch (QueryException $e) {
            throw ConstraintViolation::toValidationException($e, field: 'form');
        }
    }

    /**
     * A corrective job in progress. Finishing it also needs the asset at hand (in service or in maintenance), since it
     * sets the asset's condition; cancelling it does not (CancelRepair), so the repair of an asset lost or
     * decommissioned meanwhile never stays open for ever.
     */
    public static function isInProgress(MaintenanceRecord $repair, bool $assetMustBeAllowed = true): bool
    {
        return $repair->kind === MaintenanceKind::Corrective
            && $repair->status === MaintenanceStatus::InProgress
            && (! $assetMustBeAllowed || RegisterMaintenance::isAllowed($repair->asset));
    }

    public static function ensureInProgress(MaintenanceRecord $repair, bool $assetMustBeAllowed = true): void
    {
        if (! self::isInProgress($repair, $assetMustBeAllowed)) {
            throw ValidationException::withMessages(['form' => __('assets.maintenance.errors.not_in_progress')]);
        }
    }
}
