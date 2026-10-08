<?php

namespace App\Actions\Assets;

use App\Models\AssetComponent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * RF_12 · Modify a component of an asset, with the same rules as adding one (AddAssetComponent). The `updated` audit
 * entry, with old and new values, is written by the Auditable component.
 */
final class UpdateAssetComponent
{
    /**
     * @param  array<string, mixed>  $input  name, specs, serial_number, quantity, notes
     */
    public function handle(User $actor, AssetComponent $component, array $input): AssetComponent
    {
        $asset = $component->asset()->firstOrFail(); // fresh: a loaded relation may predate a decommission

        Gate::forUser($actor)->authorize('manageComponents', $asset);
        AddAssetComponent::ensureNotDecommissioned($asset);

        $data = Validator::make($input, AddAssetComponent::rules(), attributes: __('assets.components.fields'))->validate();

        try {
            return DB::transaction(function () use ($component, $data): AssetComponent {
                $component->update($data);

                return $component->refresh();
            });
        } catch (QueryException $e) {
            throw AddAssetComponent::toValidationException($e);
        }
    }
}
