<?php

use App\Enums\PermissionName;
use App\Queries\Reports\AssetStatistics;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('reports.statistics.title')] class extends Component
{
    public function mount(): void
    {
        $this->authorize(PermissionName::ReportsView);
    }

    public function hydrate(): void
    {
        $this->authorize(PermissionName::ReportsView);
    }

    /** @return array<string, list<array{id: int|string, label: string, count: int, color?: int}>> */
    #[Computed]
    public function series(): array
    {
        $this->authorize(PermissionName::ReportsView);
        return AssetStatistics::current();
    }
}; ?>

<div class="flex flex-col gap-6" wire:poll.15s>
    <flux:button icon="arrow-left" :href="route('reports.index')" wire:navigate class="self-start">{{ __('reports.show.back') }}</flux:button>
    <div>
        <flux:heading level="2" size="xl">{{ __('reports.statistics.title') }}</flux:heading>
        <flux:text class="mt-2">{{ __('reports.statistics.refresh') }}</flux:text>
        <p class="mt-2 text-lg font-semibold text-ink">{{ __('reports.statistics.total', ['count' => array_sum(array_column($this->series['lifecycle'], 'count'))]) }}</p>
    </div>
    @if (array_sum(array_column($this->series['lifecycle'], 'count')) === 0)
        <flux:callout icon="information-circle" :heading="__('reports.statistics.empty')" />
    @endif
    <div class="grid gap-6 lg:grid-cols-2">
        @foreach (['condition', 'lifecycle', 'category'] as $kind)
            @php($rows = $this->series[$kind])
            <flux:card class="min-w-0">
                <flux:heading level="3" class="mb-4">{{ __('reports.statistics.'.$kind) }}</flux:heading>
                <x-chart :series="$rows" :label="__('reports.statistics.'.$kind)" :key="$kind.'-'.md5(json_encode($rows))" />
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ __('reports.statistics.'.$kind) }}</caption>
                        <thead><tr class="border-b border-line text-left"><th class="py-2">{{ __('reports.statistics.group') }}</th><th class="py-2 text-right">{{ __('reports.statistics.count') }}</th></tr></thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="border-b border-line">
                                    <td class="py-3">
                                        @if ($kind === 'category')
                                            <a class="text-brand-ink underline" wire:navigate href="{{ route('reports.show', ['report' => 'assets_inventory', 'filters' => ['category' => ['values' => [$row['id']]]]]) }}">{{ $row['label'] }}</a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>
                                    <td class="py-3 text-right tabular-nums">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @endforeach
    </div>
</div>
