<?php

use App\Models\Reporting\ReservationRow;
use App\Models\Reservation;
use App\Queries\Reservations\ReservationSchedule;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Historial de reservas (RF_52). Owner: reservations (@JoseHerrera21). Past, current and future reservations, also
 * cancelled and modified ones, with their effective status. The reach depends on the role (RF_52 diagram): everyone
 * sees the reservations they made or are responsible for, an Administrador also those of the spaces they manage,
 * the Superusuario all of them (ReservationSchedule::history). Filters follow the reservations filter contract, the
 * same the reservations report (RF_65) uses; each title opens the reservation's detail (RF_51).
 */
new #[Title('reservations.history.title')] class extends Component {
    use WithPagination;

    public const PER_PAGE = 15;

    /** @var array<string, mixed> The criteria as the filter contract writes them: {"status": {"values": ["cancelled"]}}. */
    #[Url(except: [])]
    public array $filters = [];

    /** `desc`: newest first; `asc`: oldest first. */
    #[Url(except: 'desc')]
    public string $order = 'desc';

    /** A new filter or order starts again on the first page. */
    public function updated(string $property): void
    {
        if ($property === 'order' || str_starts_with($property, 'filters')) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->authorize('viewAny', Reservation::class);

        $this->reset('filters');
        $this->resetPage();
    }

    /**
     * The normalized filters, or the Spanish errors that block the list when the input (an inverted range, an edited
     * URL) breaks the filter contract.
     *
     * @return array{filters: array<string, mixed>, errors: list<string>}
     */
    #[Computed]
    public function criteria(): array
    {
        try {
            return ['filters' => ReservationSchedule::validate($this->filters), 'errors' => []];
        } catch (ValidationException $e) {
            return ['filters' => [], 'errors' => array_values(array_unique($e->validator->errors()->all()))];
        }
    }

    /** @return LengthAwarePaginator<int, ReservationRow>|null */
    #[Computed]
    public function reservations(): ?LengthAwarePaginator
    {
        return $this->criteria['errors'] === []
            ? ReservationSchedule::history(auth()->user(), $this->criteria['filters'], $this->order)->paginate(self::PER_PAGE)
            : null;
    }

    public function hasFilters(): bool
    {
        return $this->criteria['filters'] !== [] || $this->criteria['errors'] !== [];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-end gap-2">
        <flux:button icon="calendar-days" :href="route('reservations.index')" wire:navigate>{{ __('reservations.index.title') }}</flux:button>
    </div>

    <flux:card class="flex flex-col gap-5">
        <flux:checkbox.group wire:model.live="filters.status.values" variant="pills" :label="__('reservations.filters.criteria.status')" data-test="filter-status">
            @foreach (ReservationSchedule::statusChoices() as $choice)
                <flux:checkbox :value="$choice['id']" :label="$choice['label']" wire:key="status-{{ $choice['id'] }}" />
            @endforeach
        </flux:checkbox.group>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:input wire:model.live="filters.date.from" type="date" :label="__('reservations.history.from')" data-test="filter-from" />
            <flux:input wire:model.live="filters.date.to" type="date" :label="__('reservations.history.to')" data-test="filter-to" />
            <flux:select wire:model.live="order" :label="__('reservations.history.order')" data-test="order">
                <flux:select.option value="desc">{{ __('reservations.history.newest_first') }}</flux:select.option>
                <flux:select.option value="asc">{{ __('reservations.history.oldest_first') }}</flux:select.option>
            </flux:select>
        </div>

        @if ($this->hasFilters())
            <div class="border-t border-line pt-4">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">{{ __('reservations.history.clear') }}</flux:button>
            </div>
        @endif
    </flux:card>

    @if ($this->criteria['errors'] !== [])
        <flux:callout icon="exclamation-triangle" variant="danger" data-test="filters-invalid">
            <flux:callout.heading>{{ __('reservations.history.invalid_filters') }}</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc space-y-0.5 ps-5">
                    @foreach ($this->criteria['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" wire:click="clearFilters">{{ __('reservations.history.clear') }}</flux:button>
            </x-slot>
        </flux:callout>
    @else
        <flux:card class="flex flex-col gap-4">
            @if ($this->reservations->isEmpty())
                <flux:text data-test="history-empty">
                    {{ $this->criteria['filters'] === [] ? __('reservations.history.empty') : __('reservations.history.no_results') }}
                </flux:text>
            @else
                <flux:text size="sm">
                    {{ trans_choice('reservations.history.total', $this->reservations->total(), ['count' => $this->reservations->total()]) }}
                </flux:text>

                <div class="overflow-x-auto">
                    <flux:table :paginate="$this->reservations">
                        <flux:table.columns>
                            <flux:table.column>{{ __('reservations.history.columns.date') }}</flux:table.column>
                            <flux:table.column>{{ __('reservations.history.columns.time') }}</flux:table.column>
                            <flux:table.column>{{ __('reservations.history.columns.space') }}</flux:table.column>
                            <flux:table.column>{{ __('reservations.history.columns.title') }}</flux:table.column>
                            <flux:table.column>{{ __('reservations.history.columns.responsible') }}</flux:table.column>
                            <flux:table.column>{{ __('reservations.history.columns.status') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->reservations as $reservation)
                                <flux:table.row :key="$reservation->id">
                                    <flux:table.cell class="whitespace-nowrap">{{ $reservation->starts_at->format('d/m/Y') }}</flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap">{{ $reservation->starts_at->format('H:i') }}–{{ $reservation->ends_at->format('H:i') }}</flux:table.cell>
                                    <flux:table.cell class="min-w-40">{{ $reservation->space_code }} · {{ $reservation->space_name }}</flux:table.cell>
                                    <flux:table.cell class="min-w-40">
                                        <flux:link :href="route('reservations.show', $reservation->id)" wire:navigate variant="subtle" class="text-ink">{{ $reservation->title }}</flux:link>
                                    </flux:table.cell>
                                    <flux:table.cell class="min-w-32">{{ $reservation->responsible_name }}</flux:table.cell>
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
    @endif
</div>
