<?php

namespace App\Queries\Assets;

use App\Models\AssetCondition;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Space;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The choices offered by the asset form (RF_03 register, RF_04 edit): catalogs, custodians and the spaces a user
 * may register assets in. Deleted catalog entries and deactivated users are never offered.
 */
final class AssetFormChoices
{
    /**
     * Every space for the Superusuario, the managed ones for an Administrador (★).
     *
     * @return Collection<int, Space>
     */
    public function spacesFor(User $user): Collection
    {
        $query = $user->isSuperuser() ? Space::query() : $user->managedSpaces()->getQuery();

        return $query->orderBy('spaces.code')->get(['spaces.id', 'spaces.code', 'spaces.name']);
    }

    /**
     * Asset types grouped by their category name (RF_24): the asset stores the type, the category is derived.
     *
     * @return Collection<array-key, EloquentCollection<int, AssetType>>
     */
    public function assetTypes(): Collection
    {
        return AssetType::query()->with('category')->orderBy('name')->get()
            ->groupBy(fn (AssetType $type): string => $type->category->name ?? '')
            ->toBase()
            ->sortKeys();
    }

    /** @return Collection<int, AssetCondition> */
    public function conditions(): Collection
    {
        return AssetCondition::query()->orderBy('sort_order')->get();
    }

    /** @return Collection<int, Brand> */
    public function brands(): Collection
    {
        return Brand::query()->orderBy('name')->get();
    }

    /** @return Collection<int, Supplier> */
    public function suppliers(): Collection
    {
        return Supplier::query()->orderBy('name')->get();
    }

    /**
     * Active users, who alone can be a custodian (RF_23).
     *
     * @return Collection<int, User>
     */
    public function custodians(): Collection
    {
        return User::query()->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']);
    }
}
