<?php

namespace App\Actions\Assets;

use App\Enums\AssetEventKind;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\User;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * RF_28 · Register the loss of an asset, its recovery when it turns up, and RF_29 · decommission an asset with the date
 * of RF_22. Each is an append-only AssetEvent with its date and description (the decommission reason), plus its side
 * effect on the asset's lifecycle status in the same transaction (Agent-DB.md §8.2, §8.4):
 *
 * | kind         | allowed when the asset is        | status afterwards |
 * |--------------|----------------------------------|-------------------|
 * | loss         | in service or in maintenance     | lost              |
 * | recovery     | lost                             | in service (*)    |
 * | decommission | in service, in maintenance, lost | decommissioned    |
 *
 * (*) in maintenance when the asset was lost with a repair in progress: the repair is still open, so the asset comes
 * back as it left. Finishing or cancelling that repair (RF_11) is what puts it in service.
 *
 * A decommission is terminal: the asset stays listed with its history, may only exit its space, and is read-only
 * (decision of 2026-10-16). PostgreSQL allows one decommission per asset (asset_events_one_decommission).
 *
 * An Administrador registers events of assets in, or last in, a space they manage (★, AssetPolicy::registerEvent). The
 * `created` audit entry of the event and the `updated` one of the asset's status are written by the Auditable models.
 */
final class RegisterAssetEvent
{
    /** Date as sent by an `<input type="date">`. */
    public const OCCURRED_ON_FORMAT = 'Y-m-d';

    /**
     * @param  array<string, mixed>  $input  kind, occurred_on, description
     */
    public function handle(User $actor, Asset $asset, array $input): AssetEvent
    {
        Gate::forUser($actor)->authorize('registerEvent', $asset);

        $data = Validator::make($input, self::rules($asset), self::messages(), __('assets.events.fields'))->validate();
        $kind = AssetEventKind::from($data['kind']);

        try {
            return DB::transaction(function () use ($actor, $asset, $kind, $data): AssetEvent {
                $current = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('registerEvent', $current);

                // The status may have changed since the form was validated (another tab, another user).
                if (! in_array($kind, self::allowedKinds($current), true)) {
                    throw ValidationException::withMessages(['kind' => __('assets.events.errors.kind')]);
                }

                $event = $current->events()->create([
                    'kind' => $kind,
                    'occurred_on' => $data['occurred_on'],
                    'description' => $data['description'],
                    'registered_by' => $actor->id,
                ]);

                $current->transitionTo(self::statusAfter($kind, $current));

                return $event;
            });
        } catch (QueryException $e) {
            $field = match (ConstraintViolation::ruleName($e)) {
                'asset_events_one_decommission' => 'kind',
                'asset_events_description_check' => 'description',
                default => 'form',
            };

            throw ConstraintViolation::toValidationException($e, field: $field);
        }
    }

    /**
     * The events the asset's lifecycle status allows (Agent-DB.md §8.4). A damage (RF_13) is RegisterAssetDamage.
     *
     * @return list<AssetEventKind>
     */
    public static function allowedKinds(Asset $asset): array
    {
        return match ($asset->status) {
            AssetStatus::InService, AssetStatus::InMaintenance => [AssetEventKind::Loss, AssetEventKind::Decommission],
            AssetStatus::Lost => [AssetEventKind::Recovery, AssetEventKind::Decommission],
            AssetStatus::Decommissioned => [],
        };
    }

    private static function statusAfter(AssetEventKind $kind, Asset $asset): AssetStatus
    {
        return match ($kind) {
            AssetEventKind::Loss => AssetStatus::Lost,
            AssetEventKind::Recovery => $asset->hasRepairInProgress() ? AssetStatus::InMaintenance : AssetStatus::InService,
            AssetEventKind::Decommission => AssetStatus::Decommissioned,
            AssetEventKind::Damage => throw new LogicException('A damage changes the condition, not the status (RF_13).'),
        };
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(Asset $asset): array
    {
        return [
            'kind' => ['required', Rule::in(array_map(fn (AssetEventKind $kind): string => $kind->value, self::allowedKinds($asset)))],
            'occurred_on' => self::occurredOnRules($asset),
            'description' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Every event's date (RF_22, shared with RF_13's damage): a real calendar day, not in the future and not before the
     * asset was acquired.
     *
     * @return list<string>
     */
    public static function occurredOnRules(Asset $asset): array
    {
        $rules = ['required', 'date_format:'.self::OCCURRED_ON_FORMAT, 'before_or_equal:today'];

        if ($asset->acquired_on !== null) {
            $rules[] = 'after_or_equal:'.$asset->acquired_on->toDateString();
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'kind.in' => __('assets.events.errors.kind'),
            'occurred_on.before_or_equal' => __('assets.events.errors.future'),
            'occurred_on.after_or_equal' => __('assets.events.errors.before_acquisition'),
        ];
    }
}
