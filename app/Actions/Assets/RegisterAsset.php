<?php

namespace App\Actions\Assets;

use App\Enums\MovementType;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Space;
use App\Models\User;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * RF_03 · Register an asset in a space, with the fields of RF_14–RF_16, RF_18, RF_19, RF_23 and RF_24.
 * In one transaction it creates the asset without a location and inserts its `entry` movement: the database trigger
 * then places it in the space (ADR-0003). The lifecycle status starts as `in_service` (the column default).
 * A duplicate tag and the warranty rules are left to the database and come back as Spanish validation messages.
 */
final class RegisterAsset
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): Asset
    {
        Gate::forUser($actor)->authorize('create', Asset::class);

        $data = Validator::make($input, self::rules(), attributes: __('assets.fields'))->validate();

        $space = Space::query()->findOrFail($data['space_id']);
        Gate::forUser($actor)->authorize('create', [Asset::class, $space]);

        try {
            return DB::transaction(function () use ($actor, $space, $data): Asset {
                $asset = Asset::query()->create(Arr::except($data, 'space_id'));

                AssetMovement::query()->create([
                    'asset_id' => $asset->id,
                    'type' => MovementType::Entry,
                    'to_space_id' => $space->id,
                    'moved_at' => now(),
                    'moved_by' => $actor->id,
                    'reason' => __('assets.create.entry_reason'),
                ]);

                return $asset->refresh();
            });
        } catch (QueryException $e) {
            $field = match (ConstraintViolation::ruleName($e)) {
                'assets_asset_tag_unique' => 'asset_tag',
                'assets_warranty_pair_check', 'assets_warranty_range_check' => 'warranty_ends_on',
                'asset_movements_destination_active' => 'space_id',
                default => 'form',
            };

            throw ConstraintViolation::toValidationException($e, field: $field);
        }
    }

    /**
     * The asset form's rules; RF_04 (edit) reuses them without `space_id`, since location changes are movements.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'space_id' => ['required', 'integer', Rule::exists('spaces', 'id')->whereNull('deleted_at')],
            'asset_type_id' => ['required', 'integer', Rule::exists('asset_types', 'id')->whereNull('deleted_at')],
            'condition_id' => ['required', 'integer', Rule::exists('asset_conditions', 'id')->whereNull('deleted_at')],
            'name' => ['nullable', 'string', 'max:150'],
            'asset_tag' => ['nullable', 'string', 'max:50'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'model' => ['nullable', 'string', 'max:120'],
            'location_detail' => ['nullable', 'string', 'max:200'],
            'acquired_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'warranty_starts_on' => ['nullable', 'date_format:Y-m-d', 'required_with:warranty_ends_on'],
            'warranty_ends_on' => ['nullable', 'date_format:Y-m-d', 'required_with:warranty_starts_on', 'after_or_equal:warranty_starts_on'],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deactivated_at')],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
