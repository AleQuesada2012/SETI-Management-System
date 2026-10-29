<?php

use App\Models\Reservation;
use App\Queries\Spaces\ReservationCalendar;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Reservas (mockup "Reservas"): the monthly calendar of every space. Owner: spaces (@Dizabu, RF_45).
 * Creating (RF_46) and the reservation page (RF_51) belong to reservations (@JoseHerrera21).
 * The month and the selected day live in the URL (?mes=2026-10&dia=2026-10-05), so a day can be shared or reloaded.
 */
new #[Title('reservations.index.title')] class extends Component {
    /** Y-m of the month shown. */
    #[Url(as: 'mes')]
    public string $month = '';

    /** Y-m-d of the selected day, or empty. */
    #[Url(as: 'dia')]
    public string $day = '';

    public function mount(): void
    {
        $this->month = $this->monthStart()->format('Y-m');
    }

    /** The first day of the month shown; an invalid ?mes= falls back to the current month. */
    public function monthStart(): CarbonImmutable
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) === 1) {
            return CarbonImmutable::createFromFormat('!Y-m', $this->month) ?: CarbonImmutable::now()->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
        $this->day = '';
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
        $this->day = '';
    }

    public function selectDay(string $day): void
    {
        $this->day = $day;
    }

    /** @return array<string, list<\App\Models\Reporting\ReservationRow>> */
    #[Computed]
    public function reservationsByDay(): array
    {
        return ReservationCalendar::month($this->monthStart());
    }

    /**
     * Weeks of the month, Monday first; null pads the days outside the month.
     *
     * @return list<list<CarbonImmutable|null>>
     */
    #[Computed]
    public function weeks(): array
    {
        $start = $this->monthStart();
        $cells = array_fill(0, $start->dayOfWeekIso - 1, null);

        for ($date = $start; $date->month === $start->month; $date = $date->addDay()) {
            $cells[] = $date;
        }

        return array_chunk(array_pad($cells, (int) ceil(count($cells) / 7) * 7, null), 7);
    }

    /** @return list<\App\Models\Reporting\ReservationRow> */
    #[Computed]
    public function selectedReservations(): array
    {
        return $this->reservationsByDay[$this->day] ?? [];
    }
}; ?>

<div class="flex flex-col gap-6">
    @php($today = now()->toDateString())

    <div class="flex flex-wrap items-center justify-end gap-2">
        <flux:button icon="clock" :href="route('reservations.history')" wire:navigate data-test="reservation-history">{{ __('reservations.index.history') }}</flux:button>
        @can('create', Reservation::class)
            <flux:button variant="primary" icon="plus" :href="route('reservations.create')" wire:navigate>{{ __('reservations.index.create') }}</flux:button>
        @endcan
        <flux:button icon="chart-bar" :href="route('reservations.occupancy')" wire:navigate class="order-first" data-test="space-occupancy">{{ __('reservations.occupancy.link') }}</flux:button>
    </div>

    <flux:card class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-2">
            <flux:button variant="ghost" icon="chevron-left" wire:click="previousMonth" :aria-label="__('spaces.calendar.previous')" data-test="previous-month" />
            <flux:heading size="lg" level="2" data-test="calendar-month">
                {{ Str::ucfirst($this->monthStart()->locale('es')->translatedFormat('F \d\e Y')) }}
            </flux:heading>
            <flux:button variant="ghost" icon="chevron-right" wire:click="nextMonth" :aria-label="__('spaces.calendar.next')" data-test="next-month" />
        </div>

        <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-ink-muted" aria-hidden="true">
            @foreach (__('spaces.calendar.weekdays') as $weekday)
                <div>{{ $weekday }}</div>
            @endforeach
        </div>

        <div class="flex flex-col gap-1">
            @foreach ($this->weeks as $week)
                <div class="grid grid-cols-7 gap-1">
                    @foreach ($week as $date)
                        @if ($date === null)
                            <div></div>
                        @else
                            @php($key = $date->toDateString())
                            @php($count = count($this->reservationsByDay[$key] ?? []))
                            <button type="button" wire:click="selectDay('{{ $key }}')" wire:key="day-{{ $key }}"
                                @class([
                                    'flex min-h-12 flex-col items-center justify-center gap-1 rounded-lg border text-sm transition sm:min-h-16',
                                    'border-brand bg-brand text-brand-foreground' => $key === $day,
                                    'border-line bg-surface text-ink hover:border-brand' => $key !== $day,
                                    'font-semibold' => $key === $today,
                                ])
                                aria-pressed="{{ $key === $day ? 'true' : 'false' }}"
                                aria-label="{{ $date->locale('es')->translatedFormat('j \d\e F') }}: {{ $count > 0 ? trans_choice('spaces.calendar.reservations', $count, ['count' => $count]) : __('spaces.calendar.free') }}"
                                data-test="day-{{ $key }}" data-reserved="{{ $count > 0 ? 'yes' : 'no' }}">
                                <span>{{ $date->day }}</span>
                                @if ($count > 0)
                                    <span class="flex items-center gap-1">
                                        <span class="size-2 rounded-full bg-status-warning"></span>
                                        <span class="hidden text-xs sm:inline">{{ $count }}</span>
                                    </span>
                                @else
                                    <span class="size-2 rounded-full bg-status-good opacity-60"></span>
                                @endif
                            </button>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-4 text-xs text-ink-muted">
            <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-status-warning"></span>{{ __('spaces.calendar.legend_reserved') }}</span>
            <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-status-good opacity-60"></span>{{ __('spaces.calendar.legend_free') }}</span>
        </div>
    </flux:card>

    <flux:card class="flex flex-col gap-4" data-test="day-detail">
        @if ($day === '')
            <flux:text>{{ __('spaces.calendar.pick_day') }}</flux:text>
        @else
            <flux:heading size="md" level="3">
                {{ Str::ucfirst(CarbonImmutable::parse($day)->locale('es')->translatedFormat('l j \d\e F')) }}
            </flux:heading>

            @forelse ($this->selectedReservations as $reservation)
                <div wire:key="reservation-{{ $reservation->id }}" class="flex flex-col gap-1 border-t border-line pt-3 first:border-t-0 first:pt-0 sm:flex-row sm:items-baseline sm:gap-4">
                    <span class="whitespace-nowrap font-medium text-ink">
                        {{ $reservation->starts_at->timezone(config('app.timezone'))->format('H:i') }}–{{ $reservation->ends_at->timezone(config('app.timezone'))->format('H:i') }}
                    </span>
                    <div class="flex min-w-0 flex-col">
                        <flux:link :href="route('spaces.show', $reservation->space_id)" wire:navigate>{{ $reservation->space_code }} · {{ $reservation->space_name }}</flux:link>
                        <flux:link :href="route('reservations.show', $reservation->id)" wire:navigate variant="subtle" class="text-sm text-ink">{{ $reservation->title }}</flux:link>
                        <span class="text-sm text-ink-muted">{{ __('spaces.calendar.by', ['name' => $reservation->responsible_name]) }}</span>
                    </div>
                </div>
            @empty
                <flux:text data-test="day-free">{{ __('spaces.calendar.day_free') }}</flux:text>
            @endforelse
        @endif
    </flux:card>
</div>
