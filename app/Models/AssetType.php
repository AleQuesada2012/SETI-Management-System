<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * What an asset is (Monitor, Laptop, Silla…); belongs to exactly one category (CONTEXT.md "Asset type").
 *
 * @property int $id
 * @property int $asset_category_id
 * @property string $name
 */
#[Fillable(['asset_category_id', 'name', 'description'])]
class AssetType extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @return BelongsTo<AssetCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }
}
