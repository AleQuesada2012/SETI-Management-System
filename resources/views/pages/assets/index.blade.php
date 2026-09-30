<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Reporting\AssetRow;
use App\Queries\Assets\AssetInventory;
use App\Queries\Assets\InventoryFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Inventario de activos (mockup "Inventario de Activos"). Owner: assets.
 * RF_07 search, RF_08 filters (with RF_24's category, extending the contract) and RF_30 assets by custodian, all
 * through App\Queries\Assets\AssetInventory, the same query and filter contract the inventory report (RF_63) uses.
 * Each control selects one value, or "Sin …" for records without one; the contract itself accepts several.
 */
new #[Title('assets.index.title')] class extends Component {
    use WithPagination;

    /** '' = no restriction, 'none' = without a value (include_missing), otherwise an id or code. */
    public const MISSING = 'none';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $brand = '';

    #[Url(except: '')]
    public string $model = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $condition = '';

    #[Url(except: '')]
    public string $condition_color = '';

    #[Url(except: '')]
    public string $lifecycle_status = '';

    #[Url(except: '')]
    public string $space = '';

    #[Url(except: '')]
    public string $responsible_user = '';

    #[Url(except: '')]
    public string $acquired_from = '';

    #[Url(except: '')]
    public string $acquired_to = '';

    #[Url(except: false)]
    public bool $acquired_missing = false;

    /** Any change to the search or a filter starts again from the first page. */
    public function updated(string $property): void
    {
        $this->resetPage();
    }

    /** RF_08: "limpiar filtros" restores the full list. */
    public function clearFilters(): void
    {
        $this->reset();
        $this->resetPage();
    }

    /**
     * The controls as contract input (InventoryFilters).
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = ['search' => $this->search, 'model' => ['contains' => $this->model]];

        foreach (['brand', 'category', 'condition', 'condition_color', 'lifecycle_status', 'space', 'responsible_user'] as $key) {
            $value = $this->{$key};
            $filters[$key] = match (true) {
                $value === '' => [],
                $value === self::MISSING => ['include_missing' => true],
                in_array($key, ['condition_color', 'lifecycle_status'], true) => ['values' => [$value]],
                default => ['values' => [ctype_digit($value) ? (int) $value : $value]],
            };
        }

        $filters['acquired_on'] = array_filter([
            'from' => $this->acquired_from,
            'to' => $this->acquired_to,
            'include_missing' => $this->acquired_missing,
        ]);

        return $filters;
    }

    /** @return LengthAwarePaginator<int, AssetRow>|null  null when a filter is invalid */
    #[Computed]
    public function rows(): ?LengthAwarePaginator
    {
        $this->resetErrorBag();

        try {
            return AssetInventory::query($this->filters())->paginate(15);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return null;
        }
    }

    /** @return array<string, list<array{id: int|string, label: string}>> */
    #[Computed]
    public function choices(): array
    {
        return InventoryFilters::choices();
    }

    /** Which RF message explains an empty list. */
    public function emptyMessage(): string
    {
        $normalized = InventoryFilters::validate($this->filters());

        return match (true) {
            $normalized === [] => __('assets.index.empty.inventory'),
            array_keys($normalized) === ['search'] => __('assets.index.empty.search', ['term' => $normalized['search']]),
            array_keys($normalized) === ['responsible_user'] && isset($normalized['responsible_user']['values'])
                => __('assets.index.empty.responsible'),
            default => __('assets.index.empty.filters'),
        };
    }
}; ?>

<div class="flex flex-col gap-6" x-data="{ filtersOpen: false }">
    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1 basis-64">
            <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :label="__('assets.index.search')" :placeholder="__('assets.index.search_placeholder')" clearable data-test="asset-search" />
        </div>

        <flux:button icon="funnel" class="md:hidden" x-on:click="filtersOpen = ! filtersOpen">{{ __('assets.index.filters') }}</flux:button>

        @can('create', Asset::class)
            <flux:button variant="primary" icon="plus" :href="route('assets.create')" wire:navigate data-test="create-asset-button">{{ __('assets.index.create') }}</flux:button>
        @endcan
    </div>

    <flux:card class="hidden flex-col gap-4 md:flex" x-bind:class="filtersOpen && 'flex!'" data-test="asset-filters">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (['category', 'brand', 'condition', 'condition_color', 'lifecycle_status', 'space', 'responsible_user'] as $key)
                <flux:select wire:model.live="{{ $key }}" :label="Str::ucfirst(__('assets.filters.fields.'.$key))">
                    <flux:select.option value="">{{ __('assets.index.all') }}</flux:select.option>
                    @if (trans()->has('assets.filters.missing.'.$key))
                        <flux:select.option :value="$this::MISSING">{{ __('assets.filters.missing.'.$key) }}</flux:select.option>
                    @endif
                    @foreach ($this->choices[$key] as $choice)
                        <flux:select.option :value="$choice['id']">{{ $choice['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endforeach

            <flux:input wire:model.live.debounce.400ms="model" :label="Str::ucfirst(__('assets.filters.fields.model'))" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input wire:model.live="acquired_from" type="date" :label="Str::ucfirst(__('assets.filters.acquired_from'))" />
            <flux:input wire:model.live="acquired_to" type="date" :label="Str::ucfirst(__('assets.filters.acquired_to'))" />
            <div class="flex items-end sm:col-span-2">
                <flux:checkbox wire:model.live="acquired_missing" :label="__('assets.filters.include_missing_date')" />
            </div>
        </div>

        <div class="flex justify-end">
            <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters" data-test="clear-filters">{{ __('assets.index.clear') }}</flux:button>
        </div>
    </flux:card>

    @if ($this->rows === null)
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('assets.index.invalid')" data-test="invalid-filters">
            <flux:callout.text>
                <ul class="list-disc ps-5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </flux:callout.text>
        </flux:callout>
    @elseif ($this->rows->isEmpty())
        <flux:callout icon="information-circle" :heading="$this->emptyMessage()" data-test="empty-inventory" />
    @else
        <flux:text size="sm">{{ trans_choice('assets.index.results', $this->rows->total(), ['count' => number_format($this->rows->total())]) }}</flux:text>

        <div class="overflow-x-auto">
            <flux:table :paginate="$this->rows">
                <flux:table.columns>
                    <flux:table.column>{{ __('assets.index.columns.code') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.index.columns.asset') }}</flux:table.column>
                    <flux:table.column class="max-lg:hidden">{{ __('assets.index.columns.category') }}</flux:table.column>
                    <flux:table.column class="max-md:hidden">{{ __('assets.index.columns.brand_model') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.index.columns.space') }}</flux:table.column>
                    <flux:table.column class="max-lg:hidden">{{ __('assets.index.columns.responsible') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.index.columns.state') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->rows as $row)
                        <flux:table.row :key="$row->id">
                            <flux:table.cell class="whitespace-nowrap">
                                <flux:link :href="route('assets.show', $row->id)" wire:navigate>{{ $row->code }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell variant="strong">
                                {{ $row->display_name }}
                                @if ($row->asset_tag)
                                    <span class="block text-xs font-normal text-ink-muted">{{ $row->asset_tag }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="max-lg:hidden">{{ $row->category }}</flux:table.cell>
                            <flux:table.cell class="max-md:hidden">{{ trim($row->brand.' '.$row->model) ?: __('assets.show.empty') }}</flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">{{ $row->space_code ?? __('assets.show.empty') }}</flux:table.cell>
                            <flux:table.cell class="max-lg:hidden">{{ $row->responsible_name ?? __('assets.show.empty') }}</flux:table.cell>
                            <flux:table.cell>
                                {{-- Agent-DB.md §7: the lifecycle status when not in service, the condition color otherwise. --}}
                                @if ($row->status === AssetStatus::InService)
                                    <flux:badge size="sm" :color="$row->condition_color->badgeColor()">{{ $row->condition }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ $row->status->label() }}</flux:badge>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</div>
