<?php

namespace App\Models;

use App\Enums\ReservationEffectiveStatus;
use App\Enums\ReservationStatus;
use App\Support\Audit\Auditable;
use Carbon\CarbonInterface;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A confirmed claim on one space for one continuous block of time (CONTEXT.md "Reservation", ADR-0005).
 *
 * - Only `confirmed` and `cancelled` are stored; finished / in progress are derived from time.
 * - PostgreSQL rejects overlapping confirmed reservations (reservations_no_overlap), even under
 *   concurrency. Always compare times with [start, end) semantics, like the constraint.
 * - "Eliminar reserva" in the UI = cancel. The row stays in history.
 * - A modification bumps `version` (optimistic locking); `version > 1` means "modificada".
 *
 * @property int $id
 * @property int $space_id
 * @property string|null $series_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $title
 * @property string|null $notes
 * @property int $attendees
 * @property int $responsible_id
 * @property int $created_by
 * @property ReservationStatus $status
 * @property int $version
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property string|null $modification_reason
 */
#[Fillable(['space_id', 'series_id', 'starts_at', 'ends_at', 'title', 'notes', 'attendees', 'responsible_id', 'created_by'])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'attendees' => 'integer',
            'status' => ReservationStatus::class,
            'version' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class)->withTrashed();
    }

    /**
     * The user the space is booked for (CONTEXT.md "Reservation responsible").
     *
     * @return BelongsTo<User, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    #[Scope]
    protected function confirmed(Builder $query): void
    {
        $query->where('status', ReservationStatus::Confirmed);
    }

    /**
     * Confirmed reservations of the space that overlap [$startsAt, $endsAt), the same rule as
     * the reservations_no_overlap constraint (RF_55). Pass the reservation being modified to ignore it.
     *
     * @param  Builder<Reservation>  $query
     */
    #[Scope]
    protected function overlapping(Builder $query, int $spaceId, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $ignoreId = null): void
    {
        $query->where('space_id', $spaceId)
            ->where('status', ReservationStatus::Confirmed)
            ->whereRaw("tstzrange(starts_at, ends_at, '[)') && tstzrange(?::timestamptz, ?::timestamptz, '[)')", [
                $startsAt->toIso8601String(),
                $endsAt->toIso8601String(),
            ])
            ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId));
    }

    /** Same derivation as reporting.reservations.effective_status. */
    public function effectiveStatus(?CarbonInterface $now = null): ReservationEffectiveStatus
    {
        $now ??= now();

        return match (true) {
            $this->status === ReservationStatus::Cancelled => ReservationEffectiveStatus::Cancelled,
            $this->ends_at->lessThanOrEqualTo($now) => ReservationEffectiveStatus::Finished,
            $this->starts_at->lessThanOrEqualTo($now) => ReservationEffectiveStatus::InProgress,
            default => ReservationEffectiveStatus::Upcoming,
        };
    }

    public function wasModified(): bool
    {
        return $this->version > 1;
    }

    public function auditLabel(): string
    {
        $this->loadMissing('space');

        return implode(' · ', array_filter([
            $this->space?->code,
            $this->title,
            $this->starts_at->format('Y-m-d H:i'),
        ]));
    }
}
