<?php

use App\Enums\PermissionName;
use App\Models\ReportType;
use App\Reports\ReportDefinition;
use App\Reports\ReportRegistry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ReportType $report;

    /** @var array<string, mixed> The preview and export share one validated filter object. */
    #[Url(except: [])]
    public array $filters = [];

    public const PREVIEW_ROWS = 50;

    public function mount(): void
    {
        $this->authorizeReport();
    }

    public function hydrate(): void
    {
        $this->authorizeReport();
    }

    protected function authorizeReport(): void
    {
        $this->authorize(PermissionName::ReportsView);
        $this->authorize(app(ReportRegistry::class)->permissionFor($this->report));
    }

    public function updatedFilters(): void
    {
        $this->authorizeReport();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->authorizeReport();
        $this->filters = [];
        $this->resetErrorBag();
        $this->resetPage();
    }

    #[Computed]
    public function definition(): ?ReportDefinition
    {
        $registry = app(ReportRegistry::class);

        return $registry->has($this->report->code) ? $registry->get($this->report->code) : null;
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function normalizedFilters(): ?array
    {
        $this->authorizeReport();
        $this->resetErrorBag();
        try {
            return $this->definition?->normalizeFilters($this->filters);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError('filters.'.$key, $messages[0]);
            }

            return null;
        }
    }

    #[Computed]
    public function preview(): LengthAwarePaginator
    {
        return $this->definition->query($this->normalizedFilters ?? [])->paginate(self::PREVIEW_ROWS);
    }

    public function render()
    {
        return $this->view()->title($this->report->name);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:button :href="route('reports.index')" wire:navigate class="self-start" icon="arrow-left">{{ __('reports.show.back') }}</flux:button>
    <flux:heading level="2">{{ $report->name }}</flux:heading>
    @if ($this->definition === null)
        <flux:callout icon="information-circle" :heading="__('reports.show.unavailable')" />
    @else
        @php($fields = $this->definition->filterFields())
        @if ($fields !== [])
            <flux:text>{{ __('reports.filters.instructions') }}</flux:text>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($fields as $key => $field)
                    <div wire:key="criterion-{{ $key }}" class="flex flex-col gap-2">
                        @if ($field['type'] === 'values')
                            <details wire:ignore.self class="rounded-lg border border-line p-3">
                                <summary class="cursor-pointer font-medium">{{ $field['label'] }}</summary>
                                <flux:checkbox.group wire:model.live="filters.{{ $key }}.values" class="mt-3 max-h-48 overflow-y-auto">
                                    @foreach ($field['options'] as $option)
                                        <flux:checkbox :value="(string) $option['id']" :label="$option['label']" wire:key="choice-{{ $key }}-{{ $option['id'] }}" />
                                    @endforeach
                                </flux:checkbox.group>
                            </details>
                        @elseif ($field['type'] === 'date')
                            <flux:label>{{ $field['label'] }}</flux:label>
                            <flux:input type="date" wire:model.live="filters.{{ $key }}.from" :label="__('reports.filters.from')" />
                            <flux:input type="date" wire:model.live="filters.{{ $key }}.to" :label="__('reports.filters.to')" />
                        @else
                            <flux:input wire:model.live.debounce.300ms="filters.{{ $key }}{{ $field['type'] === 'contains' ? '.contains' : '' }}" :label="$field['label']" clearable />
                        @endif
                        @if (isset($field['missing_label']))
                            <flux:checkbox wire:model.live="filters.{{ $key }}.include_missing" :label="$field['missing_label']" />
                        @endif
                    </div>
                @endforeach
            </div>
            <flux:button wire:click="clearFilters" icon="x-mark" class="self-start">{{ __('reports.filters.clear') }}</flux:button>
        @endif

        @if ($this->normalizedFilters === null)
            <flux:callout variant="danger" :heading="__('reports.filters.invalid')">
                <flux:callout.text>{{ $this->getErrorBag()->first() }}</flux:callout.text>
            </flux:callout>
        @else
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading level="3">{{ __('reports.show.preview') }}</flux:heading>
                <livewire:reports.export-button :report="$report->code" :filters="$this->normalizedFilters" />
            </div>
            <flux:card class="overflow-x-auto">
                @if ($this->preview->isEmpty())
                    <flux:text>{{ __('reports.document.empty') }}</flux:text>
                @else
                    <flux:table :paginate="$this->preview">
                        <flux:table.columns>
                            @foreach ($this->definition->columns() as $header)
                                <flux:table.column>{{ $header }}</flux:table.column>
                            @endforeach
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->preview as $row)
                                <flux:table.row :key="$row->getKey()">
                                    @foreach ($this->definition->cells($row) as $cell)
                                        <flux:table.cell>{{ $cell ?? '—' }}</flux:table.cell>
                                    @endforeach
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                    <flux:text size="sm" class="mt-3">{{ __('reports.show.total', ['total' => $this->preview->total()]) }}</flux:text>
                @endif
            </flux:card>
        @endif
    @endif
</div>
