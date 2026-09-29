<?php

namespace App\Models;

use App\Enums\MaintenanceKind;
use App\Enums\MaintenanceStatus;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Work done or planned on an asset: preventive or corrective. A repair is corrective maintenance
 * (CONTEXT.md "Maintenance", Q10).
 *
 * @property int $id
 * @property int $asset_id
 * @property MaintenanceKind $kind
 * @property MaintenanceStatus $status
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $started_on
 * @property Carbon|null $completed_on
 * @property string $description
 * @property string|null $performed_by
 * @property int|null $asset_event_id
 * @property int|null $registered_by
 * @property string|null $cancellation_reason
 */
#[Fillable([
    'asset_id', 'kind', 'status', 'scheduled_for', 'started_on', 'completed_on', 'description',
    'performed_by', 'asset_event_id', 'registered_by', 'cancellation_reason',
])]
class MaintenanceRecord extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MaintenanceKind::class,
            'status' => MaintenanceStatus::class,
            'scheduled_for' => 'date',
            'started_on' => 'date',
            'completed_on' => 'date',
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
     * The damage this corrective work addresses (RF_11).
     *
     * @return BelongsTo<AssetEvent, $this>
     */
    public function assetEvent(): BelongsTo
    {
        return $this->belongsTo(AssetEvent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function auditLabel(): string
    {
        $this->loadMissing('asset');

        return "{$this->kind->label()} · {$this->asset?->code}";
    }
}
