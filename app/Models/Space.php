<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A bounded room SETI administers (lab, office, meeting room) that holds assets and, if reservable,
 * can be booked (CONTEXT.md "Space"). Deleting is a soft delete (RF_34, Q3).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $space_type_id
 * @property int $space_status_id
 * @property int $building_id
 * @property string|null $floor
 * @property string|null $location_detail
 * @property int $capacity
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['code', 'name', 'space_type_id', 'space_status_id', 'building_id', 'floor', 'location_detail', 'capacity', 'notes'])]
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $hidden = ['search_text'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    /**
     * @return BelongsTo<SpaceType, $this>
     */
    public function spaceType(): BelongsTo
    {
        return $this->belongsTo(SpaceType::class)->withTrashed();
    }

    /**
     * Operational status (Disponible, En mantenimiento, Inhabilitado). "Ocupado" is derived.
     *
     * @return BelongsTo<SpaceStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(SpaceStatus::class, 'space_status_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Building, $this>
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class)->withTrashed();
    }

    /**
     * Space managers: the "responsable" and "administrador asignado" (ADR-0009).
     *
     * @return BelongsToMany<User, $this>
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'space_managers')
            ->withPivot(['assigned_by', 'assigned_at']);
    }

    /**
     * @return BelongsToMany<File, $this>
     */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'space_photos')
            ->withPivot(['sort_order', 'caption', 'created_at'])
            ->orderByPivot('sort_order');
    }

    /**
     * Assets currently in this space (the pointer maintained by asset movements, ADR-0003).
     *
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'current_space_id');
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** Can be booked: its type is reservable, its status allows it, and it is not deleted (Agent-DB.md §6). */
    public function isReservable(): bool
    {
        return ! $this->trashed()
            && $this->spaceType->is_reservable
            && $this->status->allows_reservations;
    }

    public function auditLabel(): string
    {
        return "{$this->code} · {$this->name}";
    }
}
