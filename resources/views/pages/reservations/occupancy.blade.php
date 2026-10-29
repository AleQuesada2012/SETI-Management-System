<?php

use App\Models\Reporting\SpaceOccupancyRow;
use App\Models\Reservation;
use App\Queries\Reservations\SpaceOccupancy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Ocupación de espacios por período (RF_59). Owner: reservations (@JoseHerrera21). The spaces with confirmed
 * reservations between two dates, with how many each had and the hours they booked, ordered by either measure, most
 * or fewest first (RF_59 diagram). Opens on the current month; the arrows move by whole months and the dates choose
 * any other period. Everyone who sees the calendar may open it: it counts the same reservations. The occupancy report
 * (RF_79) reuses SpaceOccupancy with the same filters.
 */
new #[Title('reservations.occupancy.title')] class extends Component {
    use WithPagination;

    public const PER_PAGE = 15;

    /** @var array<string, mixed> The period as the filter contract writes it: {"date": {"from": "2026-10-01", "to": "2026-10-31"}}. */
    #[Url]
    public array $filters = [];

    /** One of SpaceOccupancy::ORDERS: by number of reservations or by booked hours, most or fewest first. */
    #[Url(except: 'most_reservations')]
    public string $order = 'most_reservations';

    public function mount(): void
    {
        if ($this->filters === []) {
            $this->filters = SpaceOccupancy::month(now());
        }
    }

    public function previousMonth(): void
    {
        $this->showMonth($this->shownMonth()->subMonthNoOverflow());
    }

    public function nextMonth(): void
    {
        $this->showMonth($this->shownMonth()->addMonthNoOverflow());
    }

    public function thisMonth(): void
    {
        $this->showMonth(now());
    }

    /** The month the arrows move from: the one the period starts in, or the current one with no initial date. */
    public function shownMonth(): CarbonImmutable
    {
        $period = SpaceOccupancy::normalize($this->filters)['date'] ?? [];
        $day = $period['from'] ?? $period['to'] ?? null;

        return ($day === null ? CarbonImmutable::now() : CarbonImmutable::parse($day))->startOfMonth();
    }

    /** "Octubre de 2026" when the period is exactly one calendar month; null for a period chosen by hand. */
    public function monthLabel(): ?string
    {
        $month = $this->shownMonth();

        return SpaceOccupancy::normalize($this->filters) === SpaceOccupancy::month($month)
            ? SpaceOccupancy::monthName($month)
            : null;
    }

    public function isCurrentMonth(): bool
    {
        return SpaceOccupancy::normalize($this->filters) === SpaceOccupancy::month(now());
    }

    private function showMonth(CarbonInterface $day): void
    {
        $this->authorize('viewAny', Reservation::class);

        $this->filters = SpaceOccupancy::month($day);
        $this->resetPage();
    }

    /** A new period or order starts again on the first page. */
    public function updated(string $property): void
    {
        if ($property === 'order' || str_starts_with($property, 'filters')) {
            $this->resetPage();
        }
    }

    /**
     * The normalized period, or the Spanish errors that block the list when it is not valid (a final date before the
     * initial one, an edited URL).
     *
     * @return array{filters: array<string, mixed>, errors: list<string>}
     */
    #[Computed]
    public function criteria(): array
    {
        try {
            return ['filters' => SpaceOccupancy::validate($this->filters), 'errors' => []];
        } catch (ValidationException $e) {
            return ['filters' => [], 'errors' => array_values(array_unique($e->validator->errors()->all()))];
        }
    }

    /** @return LengthAwarePaginator<int, SpaceOccupancyRow>|null */
    #[Computed]
    public function spaces(): ?LengthAwarePaginator
    {
        return $this->criteria['errors'] === []
            ? SpaceOccupancy::query($this->criteria['filters'], $this->order)->paginate(self::PER_PAGE)
            : null;
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-end gap-2">
        <flux:button icon="calendar-days" :href="route('reservations.index')" wire:navigate>{{ __('reservations.index.title') }}</flux:button>
    </div>

    <flux:card class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-2">
            <flux:button variant="ghost" icon="chevron-left" wire:click="previousMonth" :aria-label="__('reservations.occupancy.previous_month')" data-test="previous-month" />
            <div class="flex flex-col items-center gap-1 text-center">
                <flux:heading size="lg" level="2" data-test="occupancy-month">{{ $this->monthLabel() ?? __('reservations.occupancy.custom_period') }}</flux:heading>
                @unless ($this->isCurrentMonth())
                    <flux:button size="xs" variant="subtle" wire:click="thisMonth" data-test="this-month">{{ __('reservations.occupancy.this_month') }}</flux:button>
                @endunless
            </div>
            <flux:button variant="ghost" icon="chevron-right" wire:click="nextMonth" :aria-label="__('reservations.occupancy.next_month')" data-test="next-month" />
        </div>

        <div class="flex flex-col gap-3 border-t border-line pt-4 sm:flex-row sm:items-end">
            <flux:input wire:model.live="filters.date.from" type="date" :label="__('reservations.occupancy.from')" data-test="filter-from" />
            <flux:input wire:model.live="filters.date.to" type="date" :label="__('reservations.occupancy.to')" data-test="filter-to" />
            <flux:select wire:model.live="order" :label="__('reservations.occupancy.order')" data-test="order">
                @foreach (array_keys(SpaceOccupancy::ORDERS) as $code)
                    <flux:select.option :value="$code">{{ __("reservations.occupancy.orders.{$code}") }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    @if ($this->criteria['errors'] !== [])
        <flux:callout icon="exclamation-triangle" variant="danger" data-test="period-invalid">
            <flux:callout.heading>{{ __('reservations.occupancy.invalid_period') }}</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc space-y-0.5 ps-5">
                    @foreach ($this->criteria['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:card class="flex flex-col gap-4">
            @if ($this->spaces->isEmpty())
                <flux:text data-test="occupancy-empty">{{ __('reservations.occupancy.empty') }}</flux:text>
            @else
                <flux:text size="sm">
                    {{ trans_choice('reservations.occupancy.total', $this->spaces->total(), ['count' => $this->spaces->total()]) }}
                </flux:text>

                <div class="overflow-x-auto">
                    <flux:table :paginate="$this->spaces">
                        <flux:table.columns>
                            <flux:table.column>{{ __('reservations.occupancy.columns.space') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('reservations.occupancy.columns.reservations') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('reservations.occupancy.columns.booked_hours') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->spaces as $space)
                                <flux:table.row :key="$space->space_id">
                                    <flux:table.cell class="min-w-48">{{ $space->space_code }} · {{ $space->space_name }}</flux:table.cell>
                                    <flux:table.cell align="end" class="tabular-nums">{{ $space->reservations }}</flux:table.cell>
                                    <flux:table.cell align="end" class="whitespace-nowrap tabular-nums">{{ SpaceOccupancy::duration($space->booked_hours) }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        </flux:card>
    @endif
</div>
