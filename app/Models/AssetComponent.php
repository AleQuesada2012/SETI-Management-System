<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An internal part of an asset (memory, disk, power supply), recorded for detail but never tracked
 * or labeled on its own (CONTEXT.md "Component", RF_12, RF_31). Deleting one is a hard delete.
 *
 * @property int $id
 * @property int $asset_id
 * @property string $name
 * @property string|null $specs
 * @property string|null $serial_number
 * @property int $quantity
 * @property string|null $notes
 */
#[Fillable(['asset_id', 'name', 'specs', 'serial_number', 'quantity', 'notes'])]
class AssetComponent extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function auditLabel(): string
    {
        $this->loadMissing('asset');

        return "{$this->name} ({$this->asset?->code})";
    }
}
