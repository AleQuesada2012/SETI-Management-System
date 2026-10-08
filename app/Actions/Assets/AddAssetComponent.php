<?php

namespace App\Actions\Assets;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetComponent;
use App\Models\User;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * RF_12 · Record a component of an asset: an internal part (memory, disk, power supply) kept for detail, never labeled
 * or tracked on its own (CONTEXT.md "Component", Agent-DB.md §7). RF_31 consults them on the asset detail page.
 *
 * An Administrador manages the components of assets in, or last in, a space they manage (★,
 * AssetPolicy::manageComponents). A decommissioned asset takes no more components (Agent-DB.md §8.4). The `created`
 * audit entry is written by the Auditable component.
 */
final class AddAssetComponent
{
    /**
     * @param  array<string, mixed>  $input  name, specs, serial_number, quantity, notes
     */
    public function handle(User $actor, Asset $asset, array $input): AssetComponent
    {
        Gate::forUser($actor)->authorize('manageComponents', $asset);
        self::ensureNotDecommissioned($asset);

        $data = Validator::make($input, self::rules(), attributes: __('assets.components.fields'))->validate();

        try {
            return DB::transaction(fn (): AssetComponent => $asset->components()->create($data));
        } catch (QueryException $e) {
            throw self::toValidationException($e);
        }
    }

    /**
     * The component's fields, shared with UpdateAssetComponent. The database also checks a non-blank name and a
     * positive quantity (asset_components_name_check, asset_components_quantity_check).
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'specs' => ['nullable', 'string', 'max:1000'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Components are part of an asset's service life; a decommission ends it (Agent-DB.md §8.4). */
    public static function ensureNotDecommissioned(Asset $asset): void
    {
        if ($asset->status === AssetStatus::Decommissioned) {
            throw ValidationException::withMessages(['form' => __('assets.components.errors.decommissioned')]);
        }
    }

    public static function toValidationException(QueryException $e): ValidationException
    {
        $field = match (ConstraintViolation::ruleName($e)) {
            'asset_components_name_check' => 'name',
            'asset_components_quantity_check' => 'quantity',
            default => 'form',
        };

        return ConstraintViolation::toValidationException($e, field: $field);
    }
}
