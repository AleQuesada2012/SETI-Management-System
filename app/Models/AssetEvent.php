<?php

namespace App\Models;

use App\Enums\AssetEventKind;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A dated fact that changes an asset's condition or lifecycle status: damage, loss, recovery or
 * decommission (CONTEXT.md "Asset event"). Append-only: correct a mistake with a new event.
 *
 * @property int $id
 * @property int $asset_id
 * @property AssetEventKind $kind
 * @property Carbon $occurred_on
 * @property string $description
 * @property int|null $registered_by
 */
#[Fillable(['asset_id', 'kind', 'occurred_on', 'description', 'registered_by'])]
class AssetEvent extends Model
{
    use Auditable;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AssetEventKind::class,
            'occurred_on' => 'date',
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
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * Evidence photos/documents (RF_13).
     *
     * @return BelongsToMany<File, $this>
     */
    public function evidence(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'asset_event_evidence')->withPivot('created_at');
    }

    /**
     * Repairs addressing this damage (RF_11).
     *
     * @return HasMany<MaintenanceRecord, $this>
     */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function auditLabel(): string
    {
        $this->loadMissing('asset');

        return "{$this->kind->label()} · {$this->asset?->code}";
    }
}
