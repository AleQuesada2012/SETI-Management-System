<?php

use App\Models\Space;
use App\Queries\Spaces\SpaceDirectory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Espacios (mockup "Espacios"). Owner of the page: reservations (@JoseHerrera21: list and filters, RF_53/RF_54).
 * The search (RF_42) and "Añadir espacio" (RF_32) belong to spaces (@Dizabu). Both work through
 * App\Queries\Spaces\SpaceDirectory and its filter contract, the same the spaces report (RF_64) uses.
 */
new #[Title('spaces.index.title')] class extends Component {
    use WithPagination;

    public const PER_PAGE = 15;

    /** @var array<string, mixed> The filter contract's input, e.g. ['space_type' => ['values' => ['1', '2']]]; kept in the URL so a filtered list can be shared. */
    #[Url(except: [])]
    public array $filters = [];

    /**
     * The normalized filters, or the Spanish errors that block the list when the input (usually an edited URL) breaks
     * the contract.
     *
     * @return array{filters: array<string, mixed>, errors: list<string>}
     */
    #[Computed]
    public function criteria(): array
    {
        try {
            return ['filters' => SpaceDirectory::validate($this->filters), 'errors' => []];
        } catch (ValidationException $e) {
            return ['filters' => [], 'errors' => array_values(array_unique($e->validator->errors()->all()))];
        }
    }

    /** @return array<string, list<array{id: int, label: string, removed: bool}>> */
    #[Computed]
    public function choices(): array
    {
        return SpaceDirectory::choices();
    }

    /**
     * The active criteria as users read them: "Tipo de espacio: Laboratorio, Sala" (RF_54 diagram).
     *
     * @return list<string>
     */
    #[Computed]
    public function activeCriteria(): array
    {
        return collect($this->criteria['filters'])
            ->only(array_keys(SpaceDirectory::CRITERIA))
            ->map(fn (array $criterion, string $key): string => __("spaces.filters.criteria.{$key}").': '.collect($this->choices[$key])
                ->whereIn('id', $criterion['values'])
                ->pluck('label')
                ->join(', '))
            ->values()
            ->all();
    }

    #[Computed]
    public function spaces(): ?LengthAwarePaginator
    {
        return $this->criteria['errors'] === []
            ? SpaceDirectory::query($this->criteria['filters'])->paginate(self::PER_PAGE)
            : null;
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

    <flux:card class="flex flex-col gap-5">
        @foreach (SpaceDirectory::CRITERIA as $key => $criterion)
            <flux:checkbox.group wire:model.live="filters.{{ $key }}.values" variant="pills" :label="__('spaces.filters.criteria.'.$key)" data-test="filter-{{ $key }}">
                @foreach ($this->choices[$key] as $choice)
                    <flux:checkbox :value="(string) $choice['id']" :label="$choice['label']" wire:key="{{ $key }}-{{ $choice['id'] }}" />
                @endforeach
            </flux:checkbox.group>
        @endforeach

        @if ($this->activeCriteria !== [])
            <div class="flex flex-col gap-2 border-t border-line pt-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex flex-col gap-1" data-test="active-filters">
                    <flux:text size="sm" class="font-medium text-ink">{{ __('spaces.index.active_filters') }}</flux:text>
                    @foreach ($this->activeCriteria as $description)
                        <flux:text size="sm">{{ $description }}</flux:text>
                    @endforeach
                </div>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters" data-test="clear-filters">{{ __('spaces.index.clear_filters') }}</flux:button>
            </div>
        @endif
    </flux:card>

    @if ($this->criteria['errors'] !== [])
        <flux:callout icon="exclamation-triangle" variant="danger" data-test="filters-invalid">
            <flux:callout.heading>{{ __('spaces.index.invalid_filters') }}</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc space-y-0.5 ps-5">
                    @foreach ($this->criteria['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" wire:click="clearFilters">{{ __('spaces.index.clear_filters') }}</flux:button>
            </x-slot>
        </flux:callout>
    @else
        <flux:card class="overflow-x-auto">
            <flux:text size="sm" class="mb-3">{{ trans_choice('spaces.index.total', $this->spaces->total(), ['count' => $this->spaces->total()]) }}</flux:text>

            @if ($this->spaces->isEmpty())
                <flux:text data-test="spaces-empty">
                    {{ $this->criteria['filters'] === [] ? __('spaces.index.empty') : __('spaces.index.no_matches') }}
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
    @endif
</div>
