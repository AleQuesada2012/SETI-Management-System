<?php

use App\Models\Building;
use App\Models\Space;
use App\Models\SpaceStatus;
use App\Models\SpaceType;
use App\Queries\Spaces\SpaceDirectory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Espacios (mockup "Espacios"). Owner of the page: reservations (@JoseHerrera21: list and filters, RF_53/RF_54).
 * The search (RF_42) and "Añadir espacio" (RF_32) belong to spaces (@Dizabu). Both work through
 * App\Queries\Spaces\SpaceDirectory, the same query the spaces report (RF_64) uses.
 */
new #[Title('spaces.index.title')] class extends Component {
    use WithPagination;

    public const PER_PAGE = 15;

    /** @var array<string, string> The RF_54 criteria (SpaceDirectory::FILTERS); kept in the URL so a filtered list can be shared. */
    #[Url(except: [])]
    public array $filters = [];

    /** @return Collection<int, SpaceType> */
    #[Computed]
    public function spaceTypes(): Collection
    {
        return SpaceType::query()->orderBy('name')->get();
    }

    /** @return Collection<int, SpaceStatus> */
    #[Computed]
    public function spaceStatuses(): Collection
    {
        return SpaceStatus::query()->orderBy('sort_order')->get();
    }

    /** @return Collection<int, Building> */
    #[Computed]
    public function buildings(): Collection
    {
        return Building::query()->orderBy('name')->get();
    }

    #[Computed]
    public function spaces(): LengthAwarePaginator
    {
        return SpaceDirectory::query($this->activeFilters)->paginate(self::PER_PAGE);
    }

    /** @return array<string, string> the criteria that narrow the list, without blank selects */
    #[Computed]
    public function activeFilters(): array
    {
        return array_filter(
            array_intersect_key($this->filters, array_flip(SpaceDirectory::FILTERS)),
            fn (mixed $value): bool => filled($value),
        );
    }

    public function updatedFilters(): void
    {
        $this->authorize('viewAny', Space::class);

        $this->resetPage();
    }

    /** RF_54: drop every criterion and show the whole list again. */
    public function clearFilters(): void
    {
        $this->authorize('viewAny', Space::class);

        $this->filters = [];
        $this->resetPage();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-end gap-2">
        @can('create', Space::class)
            <flux:button variant="primary" icon="plus" disabled>{{ __('spaces.index.create') }}</flux:button>
        @endcan
    </div>

    <x-module-placeholder owner="Diego Zamora (@Dizabu)" :requirements="['RF_42' => 'Búsqueda de espacios (Diego)']">
        La búsqueda se agrega en <code>App\Queries\Spaces\SpaceDirectory</code> y como un campo más junto a los filtros.
    </x-module-placeholder>

    <flux:card class="flex flex-col gap-4">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:select wire:model.live="filters.space_type_id" :label="__('spaces.index.filters.type')" data-test="filter-type">
                <flux:select.option value="">{{ __('spaces.index.filters.all') }}</flux:select.option>
                @foreach ($this->spaceTypes as $type)
                    <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filters.space_status_id" :label="__('spaces.index.filters.status')" data-test="filter-status">
                <flux:select.option value="">{{ __('spaces.index.filters.all') }}</flux:select.option>
                @foreach ($this->spaceStatuses as $status)
                    <flux:select.option :value="$status->id">{{ $status->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filters.building_id" :label="__('spaces.index.filters.building')" data-test="filter-building">
                <flux:select.option value="">{{ __('spaces.index.filters.all') }}</flux:select.option>
                @foreach ($this->buildings as $building)
                    <flux:select.option :value="$building->id">{{ $building->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model.live.debounce.500ms="filters.min_capacity" type="number" min="1" step="1" inputmode="numeric"
                :label="__('spaces.index.filters.min_capacity')" :placeholder="__('spaces.index.filters.any')" data-test="filter-capacity" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:text size="sm">{{ trans_choice('spaces.index.total', $this->spaces->total(), ['count' => $this->spaces->total()]) }}</flux:text>

            @if ($this->activeFilters !== [])
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge size="sm" color="zinc">{{ trans_choice('spaces.index.filters.active', count($this->activeFilters), ['count' => count($this->activeFilters)]) }}</flux:badge>
                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters" data-test="clear-filters">{{ __('spaces.index.filters.clear') }}</flux:button>
                </div>
            @endif
        </div>
    </flux:card>

    <flux:card class="overflow-x-auto">
        @if ($this->spaces->isEmpty())
            <flux:text data-test="spaces-empty">
                {{ $this->activeFilters === [] ? __('spaces.index.empty') : __('spaces.index.no_matches') }}
            </flux:text>
        @else
            <flux:table :paginate="$this->spaces">
                <flux:table.columns>
                    <flux:table.column>{{ __('spaces.index.columns.name') }}</flux:table.column>
                    <flux:table.column>{{ __('spaces.index.columns.location') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('spaces.index.columns.capacity') }}</flux:table.column>
                    <flux:table.column>{{ __('spaces.index.columns.type') }}</flux:table.column>
                    <flux:table.column>{{ __('spaces.index.columns.status') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->spaces as $space)
                        <flux:table.row :key="$space->id">
                            <flux:table.cell class="min-w-44">
                                <flux:link :href="route('spaces.show', $space->id)" wire:navigate variant="subtle" class="font-medium text-ink">
                                    {{ $space->name }}
                                </flux:link>
                                <div class="font-mono text-xs text-ink-muted">{{ $space->code }}</div>
                            </flux:table.cell>
                            <flux:table.cell class="min-w-40">{{ $space->location() }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $space->capacity }}</flux:table.cell>
                            <flux:table.cell>{{ $space->space_type }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    <flux:badge size="sm" :color="$space->status_color->badgeColor()">{{ $space->status }}</flux:badge>
                                    @if ($space->is_occupied)
                                        <flux:badge size="sm" color="amber">{{ __('spaces.index.occupied') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
