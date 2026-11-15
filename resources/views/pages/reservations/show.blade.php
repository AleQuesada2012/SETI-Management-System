<?php

use App\Actions\Reservations\CancelReservation;
use App\Enums\ReservationEffectiveStatus;
use App\Models\Reporting\ReservationRow;
use App\Models\Reservation;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Detalle de la reserva (RF_51). Owner: reservations (@JoseHerrera21). Changes call $this->authorize() with
 * ReservationPolicy (update, cancel). Reservations are never deleted: a cancelled one still opens here and says so
 * (RF_51 diagram); an id that never existed answers 404. "Cancelar" (RF_50) asks for confirmation and an optional
 * reason, then cancels it with CancelReservation and returns to the calendar on that day. "Modificar" opens the RF_49
 * form, and the reason of the last modification shows next to the other details.
 */
new #[Title('reservations.show.title')] class extends Component {
    public Reservation $reservation;

    /** The version the user is looking at: a change made by someone else meanwhile refuses the cancellation. */
    #[Locked]
    public int $readVersion = 1;

    public string $cancellation_reason = '';

    public function mount(): void
    {
        $this->readVersion = $this->reservation->version;
    }

    /** The reservation as lists and reports see it: names, derived status and the "modificada" label. */
    #[Computed]
    public function details(): ReservationRow
    {
        return ReservationRow::query()->whereKey($this->reservation->id)->firstOrFail();
    }

    /** Modifying (RF_49) and cancelling (RF_50) apply only to reservations that have not started. */
    #[Computed]
    public function canBeChanged(): bool
    {
        return $this->details->effective_status === ReservationEffectiveStatus::Upcoming;
    }

    /** RF_50 · "Cancelar reserva" once the user confirms it. */
    public function cancel(CancelReservation $cancelReservation): void
    {
        $this->authorize('cancel', $this->reservation);

        $reservation = $cancelReservation->handle(auth()->user(), $this->reservation, $this->readVersion, $this->cancellation_reason);

        Flux::toast(variant: 'success', text: __('reservations.cancel.done', ['space' => "{$this->details->space_code} · {$this->details->space_name}"]));

        $this->redirectRoute('reservations.index', [
            'mes' => $reservation->starts_at->format('Y-m'),
            'dia' => $reservation->starts_at->format('Y-m-d'),
        ], navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center gap-2">
        <flux:heading size="lg" level="2">{{ $this->details->title }}</flux:heading>
        <x-reservations.status-badge :status="$this->details->effective_status" :modified="$this->details->was_modified" data-test="reservation-status" />

        @if ($this->canBeChanged)
            <div class="flex w-full gap-2 sm:ms-auto sm:w-auto">
                @can('update', $reservation)
                    <flux:button icon="pencil-square" :href="route('reservations.edit', $reservation)" wire:navigate class="flex-1 sm:flex-none" data-test="reservation-update">
                        {{ __('reservations.show.update') }}
                    </flux:button>
                @endcan
                @can('cancel', $reservation)
                    <flux:modal.trigger name="cancel-reservation">
                        <flux:button variant="danger" icon="x-circle" class="flex-1 sm:flex-none" data-test="reservation-cancel">
                            {{ __('reservations.show.cancel') }}
                        </flux:button>
                    </flux:modal.trigger>
                @endcan
            </div>

            @can('cancel', $reservation)
                <flux:modal name="cancel-reservation" class="w-full max-w-md">
                    <form wire:submit="cancel" class="flex flex-col gap-5">
                        <div class="flex flex-col gap-2">
                            <flux:heading size="lg">{{ __('reservations.cancel.heading') }}</flux:heading>
                            <flux:text>
                                {{ __('reservations.cancel.description', [
                                    'space' => "{$this->details->space_code} · {$this->details->space_name}",
                                    'date' => $this->details->starts_at->format('d/m/Y'),
                                    'start' => $this->details->starts_at->format('H:i'),
                                    'end' => $this->details->ends_at->format('H:i'),
                                ]) }}
                            </flux:text>
                        </div>

                        <flux:textarea wire:model="cancellation_reason" :label="__('reservations.cancel.reason')" :placeholder="__('reservations.cancel.reason_placeholder')" rows="3" />

                        <flux:error name="reservation" />

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <flux:modal.close>
                                <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('reservations.cancel.back') }}</flux:button>
                            </flux:modal.close>
                            <flux:button type="submit" variant="danger" class="w-full sm:w-auto" data-test="confirm-cancel">{{ __('reservations.cancel.confirm') }}</flux:button>
                        </div>
                    </form>
                </flux:modal>
            @endcan
        @endif
    </div>

    @if ($this->details->effective_status === ReservationEffectiveStatus::Cancelled)
        <flux:callout icon="x-circle" variant="danger" data-test="reservation-cancelled">
            <flux:callout.heading>
                {{ __('reservations.show.cancelled', ['date' => $this->details->cancelled_at?->format('d/m/Y H:i'), 'by' => $this->details->cancelled_by_name]) }}
            </flux:callout.heading>
            @if (filled($this->details->cancellation_reason))
                <flux:callout.text>{{ __('reservations.show.cancellation_reason', ['reason' => $this->details->cancellation_reason]) }}</flux:callout.text>
            @endif
        </flux:callout>
    @endif

    <flux:card>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-ink-muted">{{ __('reservations.show.space') }}</dt>
                <dd class="text-ink">
                    @if ($this->details->space_deleted)
                        {{ $this->details->space_code }} · {{ $this->details->space_name }} {{ __('reservations.show.deleted_space') }}
                    @else
                        <flux:link :href="route('spaces.show', $this->details->space_id)" wire:navigate>{{ $this->details->space_code }} · {{ $this->details->space_name }}</flux:link>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm text-ink-muted">{{ __('reservations.show.status') }}</dt>
                <dd><x-reservations.status-badge :status="$this->details->effective_status" :modified="$this->details->was_modified" /></dd>
            </div>
            <div>
                <dt class="text-sm text-ink-muted">{{ __('reservations.show.date') }}</dt>
                <dd class="text-ink">{{ $this->details->starts_at->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-muted">{{ __('reservations.show.time') }}</dt>
                <dd class="text-ink">{{ $this->details->starts_at->format('H:i') }}–{{ $this->details->ends_at->format('H:i') }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-sm text-ink-muted">{{ __('reservations.show.reason') }}</dt>
                <dd class="text-ink">{{ $this->details->title }}</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-muted">{{ __('reservations.show.attendees') }}</dt>
                <dd class="text-ink">{{ $this->details->attendees }}</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-muted">{{ __('reservations.show.responsible') }}</dt>
                <dd class="text-ink">{{ $this->details->responsible_name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-muted">{{ __('reservations.show.created_by') }}</dt>
                <dd class="text-ink">{{ $this->details->created_by_name }}</dd>
            </div>
            @if ($this->details->was_modified && filled($reservation->modification_reason))
                <div class="sm:col-span-2">
                    <dt class="text-sm text-ink-muted">{{ __('reservations.show.modification_reason') }}</dt>
                    <dd class="whitespace-pre-line text-ink" data-test="modification-reason">{{ $reservation->modification_reason }}</dd>
                </div>
            @endif
            @if (filled($this->details->notes))
                <div class="sm:col-span-2">
                    <dt class="text-sm text-ink-muted">{{ __('reservations.show.notes') }}</dt>
                    <dd class="whitespace-pre-line text-ink">{{ $this->details->notes }}</dd>
                </div>
            @endif
        </dl>
    </flux:card>
</div>
