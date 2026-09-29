<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A change in an asset's physical location: entry, transfer or exit (CONTEXT.md "Movement", ADR-0003).
 * Append-only: inserting one is the ONLY way to change assets.current_space_id; the database
 * trigger validates it (origin matches, chronological, asset/space rules) and moves the asset.
 *
 * @property int $id
 * @property int $asset_id
 * @property MovementType $type
 * @property int|null $from_space_id
 * @property int|null $to_space_id
 * @property Carbon $moved_at
 * @property int|null $moved_by
 * @property string|null $reason
 */
#[Fillable(['asset_id', 'type', 'from_space_id', 'to_space_id', 'moved_at', 'moved_by', 'reason'])]
class AssetMovement extends Model
{
    use Auditable;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'moved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function fromSpace(): BelongsTo
    {
        return $this->belongsTo(Space::class, 'from_space_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function toSpace(): BelongsTo
    {
        return $this->belongsTo(Space::class, 'to_space_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }

    public function auditLabel(): string
    {
        $this->loadMissing('asset');

        return "{$this->type->label()} · {$this->asset?->code}";
    }
}
