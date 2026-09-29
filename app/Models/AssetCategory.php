<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Broad class of assets for statistics: Cómputo, Mobiliario, Audiovisual, Redes, Otros (CONTEXT.md "Asset category").
 *
 * @property int $id
 * @property string $name
 */
#[Fillable(['name', 'description'])]
class AssetCategory extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @return HasMany<AssetType, $this>
     */
    public function assetTypes(): HasMany
    {
        return $this->hasMany(AssetType::class);
    }
}
