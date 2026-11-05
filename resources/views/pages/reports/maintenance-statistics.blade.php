<?php

use App\Enums\PermissionName;
use App\Queries\Reports\MaintenanceStatistics;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('reports.maintenance_statistics.title')] class extends Component
{
    /** @var array<string, mixed> {completed_on: {from?, to?}}; empty means every performed record. */
    #[Url(except: [])]
    public array $filters = [];

    public function mount(): void
    {
        $this->authorize(PermissionName::ReportsView);
    }

    public function hydrate(): void
    {
        $this->authorize(PermissionName::ReportsView);
    }

    public function clearFilters(): void
    {
        $this->authorize(PermissionName::ReportsView);
        $this->filters = [];
        $this->resetErrorBag();
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function normalizedFilters(): ?array
    {
        $this->resetErrorBag();
        try {
            return MaintenanceStatistics::validate($this->filters);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError('filters.'.$key, $messages[0]);
            }

            return null;
        }
    }

    /** @return array{total: int, kinds: list<array<string, mixed>>, assets: list<array<string, mixed>>}|null */
    #[Computed]
    public function statistics(): ?array
    {
        $this->authorize(PermissionName::ReportsView);

        return $this->normalizedFilters === null ? null : MaintenanceStatistics::summary($this->normalizedFilters);
    }
}; ?>

<div class="flex flex-col gap-6" wire:poll.15s>
    <flux:button icon="arrow-left" :href="route('reports.index')" wire:navigate class="self-start">{{ __('reports.show.back') }}</flux:button>
    <div>
        <flux:heading level="2" size="xl">{{ __('reports.maintenance_statistics.title') }}</flux:heading>
        <flux:text class="mt-2">{{ __('reports.maintenance_statistics.description') }}</flux:text>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:max-w-2xl">
        <flux:input type="date" wire:model.live="filters.completed_on.from" :label="__('reports.maintenance_statistics.from')" />
        <flux:input type="date" wire:model.live="filters.completed_on.to" :label="__('reports.maintenance_statistics.to')" />
    </div>
    <flux:button wire:click="clearFilters" icon="x-mark" class="self-start">{{ __('reports.filters.clear') }}</flux:button>

    @if ($this->statistics === null)
        <flux:callout variant="danger" :heading="__('reports.filters.invalid')">
            <flux:callout.text>{{ $this->getErrorBag()->first() }}</flux:callout.text>
        </flux:callout>
    @else
        <p class="text-lg font-semibold text-ink">{{ __('reports.maintenance_statistics.total', ['count' => $this->statistics['total']]) }}</p>
        @if ($this->statistics['total'] === 0)
            <flux:callout icon="information-circle" :heading="__('reports.maintenance_statistics.empty')" />
        @else
            <div class="grid gap-6 lg:grid-cols-2">
                <flux:card class="min-w-0">
                    <flux:heading level="3" class="mb-4">{{ __('reports.maintenance_statistics.kinds') }}</flux:heading>
                    <x-chart :series="$this->statistics['kinds']" :label="__('reports.maintenance_statistics.kinds')" :key="'kinds-'.md5(json_encode($this->statistics['kinds']))" />
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <caption class="sr-only">{{ __('reports.maintenance_statistics.kinds') }}</caption>
                            <thead><tr class="border-b border-line text-left"><th class="py-2">{{ __('reports.maintenance.kind') }}</th><th class="py-2 pl-3 text-right whitespace-nowrap">{{ __('reports.maintenance_statistics.count') }}</th></tr></thead>
                            <tbody>
                                @foreach ($this->statistics['kinds'] as $row)
                                    <tr class="border-b border-line"><td class="py-3">{{ $row['label'] }}</td><td class="py-3 pl-3 text-right tabular-nums">{{ $row['count'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </flux:card>
                <flux:card class="min-w-0">
                    <flux:heading level="3" class="mb-4">{{ __('reports.maintenance_statistics.assets', ['count' => MaintenanceStatistics::TOP_ASSETS]) }}</flux:heading>
                    <x-chart type="bar" :series="$this->statistics['assets']" :label="__('reports.maintenance_statistics.assets', ['count' => MaintenanceStatistics::TOP_ASSETS])" :key="'assets-'.md5(json_encode($this->statistics['assets']))" />
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <caption class="sr-only">{{ __('reports.maintenance_statistics.assets', ['count' => MaintenanceStatistics::TOP_ASSETS]) }}</caption>
                            <thead>
                                <tr class="border-b border-line text-left">
                                    <th class="py-2">{{ __('reports.columns.asset') }}</th>
                                    <th class="py-2 pl-3 text-right whitespace-nowrap">{{ __('reports.maintenance.kinds.preventive') }}</th>
                                    <th class="py-2 pl-3 text-right whitespace-nowrap">{{ __('reports.maintenance.kinds.corrective') }}</th>
                                    <th class="py-2 pl-3 text-right whitespace-nowrap">{{ __('reports.maintenance_statistics.count') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->statistics['assets'] as $row)
                                    <tr class="border-b border-line">
                                        <td class="py-3">
                                            <a class="text-brand-ink underline" wire:navigate href="{{ route('reports.show', ['report' => 'maintenance', 'filters' => ['asset' => ['values' => [$row['id']]], ...$this->normalizedFilters]]) }}">{{ $row['label'] }}</a>
                                        </td>
                                        <td class="py-3 pl-3 text-right tabular-nums">{{ $row['preventive'] }}</td>
                                        <td class="py-3 pl-3 text-right tabular-nums">{{ $row['corrective'] }}</td>
                                        <td class="py-3 pl-3 text-right tabular-nums">{{ $row['count'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            </div>
        @endif
    @endif
</div>
