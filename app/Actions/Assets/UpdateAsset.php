<?php

namespace App\Actions\Assets;

use App\Models\Asset;
use App\Models\User;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * RF_04 · Edit an asset's attributes, and RF_05 · assign its condition (the green/amber/red "estado") with a
 * description. It validates with the same rules as registration (RF_03). The space is not editable here: it changes
 * only through a transfer movement (RF_06, ADR-0003). The lifecycle status is not either: it follows events (§8.4).
 * The `updated` audit entry, with old and new values, is written by the Auditable model.
 */
final class UpdateAsset
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Asset $asset, array $input): Asset
    {
        Gate::forUser($actor)->authorize('update', $asset);

        $data = Validator::make($input, RegisterAsset::attributeRules(), attributes: __('assets.fields'))->validate();

        try {
            return DB::transaction(function () use ($asset, $data): Asset {
                $asset->update($data);

                return $asset->refresh();
            });
        } catch (QueryException $e) {
            $field = match (ConstraintViolation::ruleName($e)) {
                'assets_asset_tag_unique' => 'asset_tag',
                'assets_warranty_pair_check', 'assets_warranty_range_check' => 'warranty_ends_on',
                default => 'form',
            };

            throw ConstraintViolation::toValidationException($e, field: $field);
        }
    }
}
