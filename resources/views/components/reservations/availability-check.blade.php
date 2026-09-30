<?php

use App\Models\Reservation;
use App\Models\Space;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * Is this space free in a given time block? (RF_55), embedded in the space page (pages::spaces.show).
 * Owner: reservations (@JoseHerrera21). Use Reservation::overlapping() ([start, end) semantics, like the database):
 * a block that starts exactly when another ends is free.
 */
new class extends Component {
    public Space $space;

    public string $date = '';

    public string $start_time = '';

    public string $end_time = '';

    /** The block of the last check, [start, end) as ISO 8601; null until the user checks one. */
    #[Locked]
    public ?string $checkedStart = null;

    #[Locked]
    public ?string $checkedEnd = null;

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public function check(): void
    {
        $this->authorize('viewAny', Reservation::class);

        $this->reset('checkedStart', 'checkedEnd');

        $this->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ], [
            'end_time.after' => __('reservations.validation.end_after_start'),
        ], __('reservations.fields'));

        $this->checkedStart = Carbon::parse("{$this->date} {$this->start_time}")->toIso8601String();
        $this->checkedEnd = Carbon::parse("{$this->date} {$this->end_time}")->toIso8601String();
    }

    /** "01/10/2026, de 10:00 a 12:00": the checked block as the user reads it. */
    #[Computed]
    public function checkedRange(): string
    {
        return __('reservations.availability_check.range', [
            'date' => Carbon::parse($this->checkedStart)->format('d/m/Y'),
            'start' => Carbon::parse($this->checkedStart)->format('H:i'),
            'end' => Carbon::parse($this->checkedEnd)->format('H:i'),
        ]);
    }

    /**
     * The confirmed reservations that block the checked block (RF_55: "indicar cuando exista una reserva que impida…").
     *
     * @return Collection<int, Reservation>
     */
    #[Computed]
    public function conflicts(): Collection
    {
        if ($this->checkedStart === null || $this->checkedEnd === null) {
            return collect();
        }

        return Reservation::query()
            ->overlapping($this->space->id, Carbon::parse($this->checkedStart), Carbon::parse($this->checkedEnd))
            ->with('responsible')
            ->orderBy('starts_at')
            ->get();
    }
}; ?>

<div>
    @can('viewAny', Reservation::class)
        <flux:card>
            <flux:heading level="3" class="mb-3">{{ __('reservations.availability_check.heading') }}</flux:heading>

            @unless ($space->isReservable())
                <flux:callout icon="information-circle" color="zinc" class="mb-4" :text="__('reservations.availability_check.not_reservable')" data-test="availability-not-reservable" />
            @endunless

            <form wire:submit="check" class="flex flex-col gap-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input wire:model="date" type="date" :label="__('reservations.availability_check.date')" required />
                    <flux:input wire:model="start_time" type="time" step="900" :label="__('reservations.availability_check.start_time')" required />
                    <flux:input wire:model="end_time" type="time" step="900" :label="__('reservations.availability_check.end_time')" required />
                </div>

                <div>
                    <flux:button type="submit" variant="primary" icon="magnifying-glass" class="w-full sm:w-auto" data-test="availability-submit">
                        {{ __('reservations.availability_check.submit') }}
                    </flux:button>
                </div>
            </form>

            @if ($checkedStart !== null)
                @if ($this->conflicts->isEmpty())
                    <flux:callout icon="check-circle" variant="success" class="mt-4" data-test="availability-free">
                        <flux:callout.heading>{{ __('reservations.availability_check.available') }}</flux:callout.heading>
                        <flux:callout.text>{{ $this->checkedRange }}</flux:callout.text>

                        @if ($space->isReservable())
                            @can('create', Reservation::class)
                                <x-slot name="actions">
                                    <flux:button size="sm" variant="primary" icon="calendar-days" wire:navigate
                                        :href="route('reservations.create', ['space' => $space->id, 'date' => $date, 'start' => $start_time, 'end' => $end_time])">
                                        {{ __('reservations.availability_check.reserve') }}
                                    </flux:button>
                                </x-slot>
                            @endcan
                        @endif
                    </flux:callout>
                @else
                    <flux:callout icon="x-circle" variant="danger" class="mt-4" data-test="availability-taken">
                        <flux:callout.heading>{{ __('reservations.availability_check.unavailable') }}</flux:callout.heading>
                        <flux:callout.text>
                            <p>{{ $this->checkedRange }}</p>
                            <p class="mt-2">{{ __('reservations.availability_check.taken_by') }}</p>
                            <ul class="mt-1 list-disc space-y-0.5 ps-5">
                                @foreach ($this->conflicts as $reservation)
                                    <li wire:key="conflict-{{ $reservation->id }}">
                                        <span class="font-medium">{{ $reservation->starts_at->format('H:i') }}–{{ $reservation->ends_at->format('H:i') }}</span>
                                        · {{ $reservation->title }} · {{ $reservation->responsible->name }}
                                    </li>
                                @endforeach
                            </ul>
                        </flux:callout.text>
                    </flux:callout>
                @endif
            @endif
        </flux:card>
    @endcan
</div>
