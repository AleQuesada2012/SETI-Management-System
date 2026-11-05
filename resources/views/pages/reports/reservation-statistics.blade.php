<?php

use App\Enums\PermissionName;
use App\Enums\ReservationEffectiveStatus;
use App\Queries\Reports\ReservationStatistics;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('reports.reservation_statistics.title')] class extends Component
{
    /** @var array<string, mixed> {starts_on: {from?, to?}}; empty means every reservation. */
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
            return ReservationStatistics::validate($this->filters);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError('filters.'.$key, $messages[0]);
            }

            return null;
        }
    }

    /** @return array{total: int, statuses: list<array<string, mixed>>, spaces: list<array<string, mixed>>}|null */
    #[Computed]
    public function statistics(): ?array
    {
        $this->authorize(PermissionName::ReportsView);

        return $this->normalizedFilters === null ? null : ReservationStatistics::summary($this->normalizedFilters);
    }
}; ?>

<div class="flex flex-col gap-6" wire:poll.15s>
    <flux:button icon="arrow-left" :href="route('reports.index')" wire:navigate class="self-start">{{ __('reports.show.back') }}</flux:button>
    <div>
        <flux:heading level="2" size="xl">{{ __('reports.reservation_statistics.title') }}</flux:heading>
        <flux:text class="mt-2">{{ __('reports.reservation_statistics.description') }}</flux:text>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:max-w-2xl">
        <flux:input type="date" wire:model.live="filters.starts_on.from" :label="__('reports.reservation_statistics.from')" />
        <flux:input type="date" wire:model.live="filters.starts_on.to" :label="__('reports.reservation_statistics.to')" />
    </div>
    <flux:button wire:click="clearFilters" icon="x-mark" class="self-start">{{ __('reports.filters.clear') }}</flux:button>

    @if ($this->statistics === null)
        <flux:callout variant="danger" :heading="__('reports.filters.invalid')">
            <flux:callout.text>{{ $this->getErrorBag()->first() }}</flux:callout.text>
        </flux:callout>
    @else
        <p class="text-lg font-semibold text-ink">{{ __('reports.reservation_statistics.total', ['count' => $this->statistics['total']]) }}</p>
        @if ($this->statistics['total'] === 0)
            <flux:callout icon="information-circle" :heading="__('reports.reservation_statistics.empty')" />
        @else
            <div class="grid gap-6 lg:grid-cols-2">
                <flux:card class="min-w-0">
                    <flux:heading level="3" class="mb-4">{{ __('reports.reservation_statistics.statuses') }}</flux:heading>
                    <x-chart :series="$this->statistics['statuses']" :label="__('reports.reservation_statistics.statuses')" :key="'statuses-'.md5(json_encode($this->statistics['statuses']))" />
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <caption class="sr-only">{{ __('reports.reservation_statistics.statuses') }}</caption>
                            <thead><tr class="border-b border-line text-left"><th class="py-2">{{ __('reports.reservation_statistics.status') }}</th><th class="py-2 pl-3 text-right whitespace-nowrap">{{ __('reports.reservation_statistics.count') }}</th></tr></thead>
                            <tbody>
                                @foreach ($this->statistics['statuses'] as $row)
                                    <tr class="border-b border-line"><td class="py-3">{{ $row['label'] }}</td><td class="py-3 pl-3 text-right tabular-nums">{{ $row['count'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </flux:card>
                <flux:card class="min-w-0">
                    <flux:heading level="3" class="mb-4">{{ __('reports.reservation_statistics.spaces') }}</flux:heading>
                    <x-chart type="bar" :series="$this->statistics['spaces']" :label="__('reports.reservation_statistics.spaces')" :key="'spaces-'.md5(json_encode($this->statistics['spaces']))" />
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <caption class="sr-only">{{ __('reports.reservation_statistics.spaces') }}</caption>
                            <thead>
                                <tr class="border-b border-line text-left">
                                    <th class="py-2">{{ __('reports.reservation_statistics.space') }}</th>
                                    @foreach (ReservationEffectiveStatus::cases() as $status)
                                        <th class="py-2 pl-3 text-right whitespace-nowrap">{{ $status->label() }}</th>
                                    @endforeach
                                    <th class="py-2 pl-3 text-right whitespace-nowrap">{{ __('reports.reservation_statistics.count') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->statistics['spaces'] as $row)
                                    <tr class="border-b border-line" wire:key="space-{{ $row['id'] }}">
                                        <td class="py-3">{{ $row['label'] }}</td>
                                        @foreach ($row['by_status'] as $count)
                                            <td class="py-3 pl-3 text-right tabular-nums">{{ $count }}</td>
                                        @endforeach
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
