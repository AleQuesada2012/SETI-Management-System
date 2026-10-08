<?php

namespace App\Actions\Assets;

use App\Enums\AssetStatus;
use App\Enums\MovementType;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\User;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * RF_25 · Register an inventory movement (entry, transfer or exit), and RF_06 · transfer an asset between spaces, which
 * is the `transfer` case. Inserting the movement is the only way to change the asset's space: the database trigger
 * checks it starts where the asset is, keeps history chronological, refuses lost assets and deleted spaces, and then
 * moves the asset (ADR-0003, Agent-DB.md §8.1). The origin is always the asset's current space, never user input.
 *
 * An Administrador moves only assets in, or last in, a space they manage, to any active space (Q26, AssetPolicy::move).
 * The `created` audit entry is written by the Auditable movement.
 */
final class RecordAssetMovement
{
    /**
     * Datetime as sent by an `<input type="datetime-local">`, in the app's time zone (America/Costa_Rica). Left empty,
     * the movement happens now, to the second: a minute-precision "now" could predate the asset's latest movement.
     */
    public const MOVED_AT_FORMAT = 'Y-m-d\TH:i';

    /**
     * @param  array<string, mixed>  $input  type, to_space_id (entry, transfer), moved_at (optional), reason (required for an exit)
     */
    public function handle(User $actor, Asset $asset, array $input): AssetMovement
    {
        Gate::forUser($actor)->authorize('move', $asset);

        $data = Validator::make($input, self::rules($asset), self::messages(), __('assets.move.fields'))->validate();
        $type = MovementType::from($data['type']);

        try {
            return DB::transaction(fn (): AssetMovement => AssetMovement::query()->create([
                'asset_id' => $asset->id,
                'type' => $type,
                'from_space_id' => $type === MovementType::Entry ? null : $asset->current_space_id,
                'to_space_id' => $type === MovementType::Exit ? null : (int) $data['to_space_id'],
                'moved_at' => isset($data['moved_at']) ? Carbon::createFromFormat(self::MOVED_AT_FORMAT, $data['moved_at']) : now(),
                'moved_by' => $actor->id,
                'reason' => $data['reason'] ?? null,
            ]));
        } catch (QueryException $e) {
            $field = match (ConstraintViolation::ruleName($e)) {
                'asset_movements_destination_active', 'asset_movements_endpoints_check' => 'to_space_id',
                'asset_movements_chronological' => 'moved_at',
                default => 'form',
            };

            throw ConstraintViolation::toValidationException($e, field: $field);
        }
    }

    /**
     * The movements the asset's state allows (Agent-DB.md §8.1, §8.4): an asset in a space is transferred or exits; one
     * outside every space enters one; a decommissioned asset may only exit; a lost one stays put until recovered.
     *
     * @return list<MovementType>
     */
    public static function allowedTypes(Asset $asset): array
    {
        return match (true) {
            $asset->status === AssetStatus::Lost => [],
            $asset->status === AssetStatus::Decommissioned => $asset->current_space_id === null ? [] : [MovementType::Exit],
            $asset->current_space_id === null => [MovementType::Entry],
            default => [MovementType::Transfer, MovementType::Exit],
        };
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(Asset $asset): array
    {
        return [
            'type' => ['required', Rule::in(array_map(fn (MovementType $type): string => $type->value, self::allowedTypes($asset)))],
            'to_space_id' => [
                'exclude_if:type,'.MovementType::Exit->value,
                'required',
                'integer',
                Rule::exists('spaces', 'id')->whereNull('deleted_at'),
                Rule::notIn(array_filter([$asset->current_space_id])),
            ],
            'moved_at' => ['nullable', 'date_format:'.self::MOVED_AT_FORMAT, 'before_or_equal:now'],
            'reason' => ['nullable', 'string', 'max:500', 'required_if:type,'.MovementType::Exit->value],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function messages(): array
    {
        return [
            'type.in' => __('assets.move.errors.type'),
            'to_space_id.not_in' => __('assets.move.errors.same_space'),
            'moved_at.before_or_equal' => __('assets.move.errors.future'),
        ];
    }
}
