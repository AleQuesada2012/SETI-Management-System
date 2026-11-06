<?php

namespace App\Actions\Assets;

use App\Enums\AssetEventKind;
use App\Enums\AssetStatus;
use App\Enums\ColorGroup;
use App\Enums\MaintenanceKind;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetEvent;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * RF_11 · Register a repair of a damaged asset: corrective maintenance, optionally linked to one of the asset's damage
 * events (RF_13), with the action taken, its date, who did it and the user who records it (Agent-DB.md §8.3). Its side
 * effects on the asset happen in the same transaction (§8.4):
 *
 * | registered as | maintenance_records          | asset afterwards                                        |
 * |---------------|------------------------------|---------------------------------------------------------|
 * | in progress   | corrective, in_progress      | status → in maintenance; the red condition stays         |
 * | completed     | corrective, completed        | condition → the green or amber one the user picks        |
 *
 * A repair in progress is finished by CompleteRepair or cancelled by CancelRepair. Only an asset with a red condition,
 * in service or in maintenance and without a repair in progress takes a new repair (RF_11 diagram). Who: as in
 * RegisterMaintenance (★, MaintenanceRecordPolicy::create). The `created` audit entry of the repair and the `updated`
 * ones of the asset come from the Auditable models.
 */
final class RegisterRepair
{
    /**
     * @param  array<string, mixed>  $input  asset_event_id (optional), performed_on, performed_by, description, status
     *                                       (in_progress | completed), condition_id (when completed)
     */
    public function handle(User $actor, Asset $asset, array $input): MaintenanceRecord
    {
        Gate::forUser($actor)->authorize('create', [MaintenanceRecord::class, $asset]);
        self::ensureAllowed($asset);

        /** @var array{asset_event_id?: int|string|null, performed_on: string, performed_by: string, description: string, status: string, condition_id?: int|string|null} $data */
        $data = Validator::make($input, self::rules($asset), [
            ...RegisterMaintenance::messages(),
            'asset_event_id.exists' => __('assets.maintenance.errors.damage'),
            'condition_id.exists' => __('assets.maintenance.errors.condition'),
        ], __('assets.maintenance.fields'))->validate();

        $completed = $data['status'] === MaintenanceStatus::Completed->value;

        try {
            return DB::transaction(function () use ($actor, $asset, $data, $completed): MaintenanceRecord {
                $current = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('create', [MaintenanceRecord::class, $current]);

                // The asset may have changed since the form opened (repaired, lost or decommissioned in another tab).
                self::ensureAllowed($current);

                $repair = $current->maintenanceRecords()->create([
                    'kind' => MaintenanceKind::Corrective,
                    'status' => $completed ? MaintenanceStatus::Completed : MaintenanceStatus::InProgress,
                    'started_on' => $data['performed_on'],
                    'completed_on' => $completed ? $data['performed_on'] : null,
                    'description' => $data['description'],
                    'performed_by' => $data['performed_by'],
                    'asset_event_id' => filled($data['asset_event_id'] ?? null) ? (int) $data['asset_event_id'] : null,
                    'registered_by' => $actor->id,
                ]);

                if ($completed) {
                    $current->update(['condition_id' => (int) $data['condition_id']]);
                } else {
                    $current->forceFill(['status' => AssetStatus::InMaintenance])->save();
                }

                return $repair;
            });
        } catch (QueryException $e) {
            $field = match (ConstraintViolation::ruleName($e)) {
                'maintenance_records_description_check' => 'description',
                'maintenance_records_asset_event_foreign' => 'asset_event_id',
                default => 'form',
            };

            throw ConstraintViolation::toValidationException($e, field: $field);
        }
    }

    /**
     * A damaged asset (red condition) at hand that may still change and is not already being repaired (RF_11 diagram).
     */
    public static function isAllowed(Asset $asset): bool
    {
        return RegisterMaintenance::isAllowed($asset)
            && $asset->condition->color_group === ColorGroup::Red
            && ! self::hasRepairInProgress($asset);
    }

    public static function hasRepairInProgress(Asset $asset): bool
    {
        return $asset->maintenanceRecords()
            ->where('kind', MaintenanceKind::Corrective)
            ->where('status', MaintenanceStatus::InProgress)
            ->exists();
    }

    /**
     * The asset's damage events a repair may address, newest first.
     *
     * @return Collection<int, AssetEvent>
     */
    public static function damages(Asset $asset): Collection
    {
        return $asset->events()->reorder()->where('kind', AssetEventKind::Damage)->orderByDesc('occurred_on')->orderByDesc('id')->get();
    }

    /**
     * The conditions a repaired asset may take: green or amber ("en funcionamiento"), "Buen estado" first.
     *
     * @return Collection<int, AssetCondition>
     */
    public static function conditions(): Collection
    {
        return AssetCondition::query()
            ->whereIn('color_group', [ColorGroup::Green, ColorGroup::Amber])
            ->orderByRaw('color_group = ? desc', [ColorGroup::Green->value])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Rule for the condition a repaired asset takes (shared with CompleteRepair).
     *
     * @return list<mixed>
     */
    public static function conditionRules(): array
    {
        return [
            'required',
            'integer',
            Rule::exists('asset_conditions', 'id')
                ->whereIn('color_group', [ColorGroup::Green->value, ColorGroup::Amber->value])
                ->whereNull('deleted_at'),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(Asset $asset): array
    {
        return [
            'asset_event_id' => [
                'nullable',
                'integer',
                Rule::exists('asset_events', 'id')->where('asset_id', $asset->id)->where('kind', AssetEventKind::Damage->value),
            ],
            'performed_on' => RegisterAssetEvent::occurredOnRules($asset),
            'performed_by' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:1000'],
            'status' => ['required', Rule::in([MaintenanceStatus::InProgress->value, MaintenanceStatus::Completed->value])],
            'condition_id' => ['exclude_unless:status,'.MaintenanceStatus::Completed->value, ...self::conditionRules()],
        ];
    }

    private static function ensureAllowed(Asset $asset): void
    {
        if (! self::isAllowed($asset)) {
            throw ValidationException::withMessages(['form' => __('assets.maintenance.errors.repair_not_allowed')]);
        }
    }
}
