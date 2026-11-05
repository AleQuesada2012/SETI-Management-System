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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * RF_10 · Register corrective maintenance done on an asset, and RF_26 · preventive maintenance (a periodic check): one
 * `completed` maintenance_records row with its kind, the day it was done, its description, who did the work
 * (`performed_by`, free text: often an external technician) and the user who recorded it (`registered_by`), in one
 * transaction (Agent-DB.md §8.3). The work is already done, so the lifecycle status does not change: a repair in
 * progress (status → in maintenance → in service) is RF_11 and maintenance planned for a later day is RF_27.
 *
 * Only an asset at hand that may still change takes maintenance: in service or in maintenance, not lost or
 * decommissioned (the same rule as RF_13's damage). An Administrador registers maintenance of assets in, or last in, a
 * space they manage (★, MaintenanceRecordPolicy::create). The `created` audit entry comes from the Auditable model.
 */
final class RegisterMaintenance
{
    /**
     * @param  array<string, mixed>  $input  kind, performed_on, performed_by, description
     */
    public function handle(User $actor, Asset $asset, array $input): MaintenanceRecord
    {
        Gate::forUser($actor)->authorize('create', [MaintenanceRecord::class, $asset]);
        self::ensureAllowed($asset);

        /** @var array{kind: string, performed_on: string, performed_by: string, description: string} $data */
        $data = Validator::make($input, self::rules($asset), self::messages(), __('assets.maintenance.fields'))->validate();

        try {
            return DB::transaction(function () use ($actor, $asset, $data): MaintenanceRecord {
                $current = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('create', [MaintenanceRecord::class, $current]);

                // The status may have changed since the form opened (a loss or decommission in another tab).
                self::ensureAllowed($current);

                return $current->maintenanceRecords()->create([
                    'kind' => MaintenanceKind::from($data['kind']),
                    'status' => MaintenanceStatus::Completed,
                    'started_on' => $data['performed_on'],
                    'completed_on' => $data['performed_on'],
                    'description' => $data['description'],
                    'performed_by' => $data['performed_by'],
                    'registered_by' => $actor->id,
                ]);
            });
        } catch (QueryException $e) {
            $field = match (ConstraintViolation::ruleName($e)) {
                'maintenance_records_description_check' => 'description',
                default => 'form',
            };

            throw ConstraintViolation::toValidationException($e, field: $field);
        }
    }

    /** Maintenance is registered on an asset at hand that may still change (Agent-DB.md §8.4). */
    public static function isAllowed(Asset $asset): bool
    {
        return in_array($asset->status, [AssetStatus::InService, AssetStatus::InMaintenance], true);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(Asset $asset): array
    {
        return [
            'kind' => ['required', Rule::enum(MaintenanceKind::class)],
            'performed_on' => RegisterAssetEvent::occurredOnRules($asset),
            'performed_by' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'performed_on.before_or_equal' => __('assets.maintenance.errors.future'),
            'performed_on.after_or_equal' => __('assets.maintenance.errors.before_acquisition'),
        ];
    }

    private static function ensureAllowed(Asset $asset): void
    {
        if (! self::isAllowed($asset)) {
            throw ValidationException::withMessages(['form' => __('assets.maintenance.errors.status')]);
        }
    }
}
