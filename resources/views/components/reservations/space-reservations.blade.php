<?php

use App\Models\Reservation;
use App\Models\Space;
use App\Queries\Reservations\ReservationSchedule;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * The reservations of one space (RF_57), embedded in the space page (pages::spaces.show).
 * Owner: reservations (@JoseHerrera21). Read reservations through reporting.reservations (ReservationRow), with
 * App\Queries\Reservations\ReservationSchedule. The period starts as the current month.
 */
new class extends Component {
    use WithPagination;

    public const PER_PAGE = 10;

    public Space $space;

    public string $from = '';

    public string $to = '';

    /** The period on screen; it changes only when a valid period is submitted. */
    #[Locked]
    public string $shownFrom = '';

    #[Locked]
    public string $shownTo = '';

    public function mount(): void
    {
        $this->from = $this->shownFrom = now()->startOfMonth()->toDateString();
        $this->to = $this->shownTo = now()->endOfMonth()->toDateString();
    }

    /** RF_57: "consultar reservas correspondientes a un período determinado". */
    public function search(): void
    {
        $this->authorize('viewAny', Reservation::class);

        $this->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'to.after_or_equal' => __('reservations.validation.to_after_from'),
        ], __('reservations.fields'));

        $this->shownFrom = $this->from;
        $this->shownTo = $this->to;
        $this->resetPage(pageName: 'reservas');
    }

    #[Computed]
    public function reservations(): LengthAwarePaginator
    {
        return ReservationSchedule::query([
            'space_id' => $this->space->id,
            'from' => $this->shownFrom,
            'to' => $this->shownTo,
        ])->paginate(self::PER_PAGE, pageName: 'reservas');
    }
}; ?>

<div>
    @can('viewAny', Reservation::class)
        <flux:card class="flex flex-col gap-4">
            <flux:heading level="3">{{ __('reservations.space_reservations.heading') }}</flux:heading>

            <form wire:submit="search" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <flux:input wire:model="from" type="date" :label="__('reservations.space_reservations.from')" required />
                <flux:input wire:model="to" type="date" :label="__('reservations.space_reservations.to')" required />
                <flux:button type="submit" icon="funnel" class="w-full sm:w-auto" data-test="space-reservations-submit">
                    {{ __('reservations.space_reservations.submit') }}
                </flux:button>
            </form>

            @if ($this->reservations->isEmpty())
                <flux:text data-test="space-reservations-empty">{{ __('reservations.space_reservations.empty') }}</flux:text>
            @else
                <flux:text size="sm">
                    {{ trans_choice('reservations.space_reservations.total', $this->reservations->total(), [
                        'count' => $this->reservations->total(),
                        'from' => Carbon::parse($shownFrom)->format('d/m/Y'),
                        'to' => Carbon::parse($shownTo)->format('d/m/Y'),
                    ]) }}
                </flux:text>

                <div class="overflow-x-auto">
                    <flux:table :paginate="$this->reservations">
                        <flux:table.columns>
                            <flux:table.column>{{ __('reservations.space_reservations.columns.date') }}</flux:table.column>
                            <flux:table.column>{{ __('reservations.space_reservations.columns.time') }}</flux:table.column>
                            <flux:table.column>{{ __('reservations.space_reservations.columns.title') }}</flux:table.column>
                            <flux:table.column>{{ __('reservations.space_reservations.columns.responsible') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('reservations.space_reservations.columns.attendees') }}</flux:table.column>
                            <flux:table.column>{{ __('reservations.space_reservations.columns.status') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->reservations as $reservation)
                                <flux:table.row :key="$reservation->id">
                                    <flux:table.cell class="whitespace-nowrap">{{ $reservation->starts_at->format('d/m/Y') }}</flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap">{{ $reservation->starts_at->format('H:i') }}–{{ $reservation->ends_at->format('H:i') }}</flux:table.cell>
                                    <flux:table.cell class="min-w-40">
                                        <flux:link :href="route('reservations.show', $reservation->id)" wire:navigate variant="subtle" class="text-ink">{{ $reservation->title }}</flux:link>
                                    </flux:table.cell>
                                    <flux:table.cell class="min-w-32">{{ $reservation->responsible_name }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ $reservation->attendees }}</flux:table.cell>
                                    <flux:table.cell>
                                        <x-reservations.status-badge :status="$reservation->effective_status" :modified="$reservation->was_modified" />
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        </flux:card>
    @endcan
</div>
