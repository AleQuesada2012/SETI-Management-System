<?php

namespace App\Actions\Assets;

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
 * RF_27 · Schedule maintenance of an asset for a later day: one `scheduled` maintenance_records row with its kind
 * (preventive by default in the form, or corrective), the planned day (`scheduled_for`, after today), its description or
 * reason and the user who scheduled it (Agent-DB.md §8.3). Scheduling changes nothing on the asset.
 *
 * A scheduled maintenance is changed (UpdateScheduledMaintenance), marked as done (CompleteScheduledMaintenance) or
 * cancelled (CancelScheduledMaintenance); the daily reminder is NotifyDueMaintenance. Only an asset in service or in
 * maintenance is scheduled. Who: as in RegisterMaintenance (★, MaintenanceRecordPolicy::create). The `created` audit
 * entry comes from the Auditable model.
 */
final class ScheduleMaintenance
{
    /**
     * @param  array<string, mixed>  $input  kind, scheduled_for, description
     */
    public function handle(User $actor, Asset $asset, array $input): MaintenanceRecord
    {
        Gate::forUser($actor)->authorize('create', [MaintenanceRecord::class, $asset]);
        self::ensureAssetAllowed($asset);

        /** @var array{kind: string, scheduled_for: string, description: string} $data */
        $data = Validator::make($input, self::rules(), self::messages(), __('assets.maintenance.fields'))->validate();

        try {
            return DB::transaction(function () use ($actor, $asset, $data): MaintenanceRecord {
                $current = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('create', [MaintenanceRecord::class, $current]);
                self::ensureAssetAllowed($current);

                return $current->maintenanceRecords()->create([
                    'kind' => MaintenanceKind::from($data['kind']),
                    'status' => MaintenanceStatus::Scheduled,
                    'scheduled_for' => $data['scheduled_for'],
                    'description' => $data['description'],
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

    /**
     * The fields of a scheduled maintenance (shared with UpdateScheduledMaintenance): the planned day is a real day
     * after today (RF_27 diagram: "posterior a la fecha actual").
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(MaintenanceKind::class)],
            'scheduled_for' => ['required', 'date_format:'.RegisterAssetEvent::OCCURRED_ON_FORMAT, 'after:today'],
            'description' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return ['scheduled_for.after' => __('assets.maintenance.errors.not_after_today')];
    }

    /**
     * The record is still scheduled; changing it or marking it as done also needs the asset in service or in maintenance
     * (cancelling does not, so the maintenance of a lost or decommissioned asset can be cleaned up).
     */
    public static function ensureScheduled(MaintenanceRecord $record, bool $assetMustBeAllowed = true): void
    {
        if ($record->status !== MaintenanceStatus::Scheduled) {
            throw ValidationException::withMessages(['form' => __('assets.maintenance.errors.not_scheduled')]);
        }

        if ($assetMustBeAllowed) {
            self::ensureAssetAllowed($record->asset);
        }
    }

    private static function ensureAssetAllowed(Asset $asset): void
    {
        if (! RegisterMaintenance::isAllowed($asset)) {
            throw ValidationException::withMessages(['form' => __('assets.maintenance.errors.status')]);
        }
    }
}
