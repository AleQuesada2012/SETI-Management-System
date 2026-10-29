<?php

use App\Enums\AssetStatus;
use App\Models\Reporting\MaintenanceRow;
use App\Queries\Assets\ScheduledMaintenance;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * RF_27 · Mantenimientos programados. Owner: assets (maintenance by @JoseHerrera21). Everyone who may consult
 * maintenance (the route checked MaintenanceRecordPolicy::viewAny) sees the scheduled maintenance of every asset, the
 * soonest or most overdue first (App\Queries\Assets\ScheduledMaintenance), and filters it by when it is due. Read-only:
 * scheduling, changing, marking as done and cancelling happen on each asset's "Mantenimientos" card, linked from here.
 */
new #[Title('assets.scheduled.title')] class extends Component {
    use WithPagination;

    private const int PER_PAGE = 25;

    #[Url(as: 'cuando', except: 'all')]
    public string $when = 'all';

    /** @return LengthAwarePaginator<int, MaintenanceRow> */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        $when = in_array($this->when, ScheduledMaintenance::WHEN, true) ? $this->when : 'all';

        return ScheduledMaintenance::query($when)->paginate(self::PER_PAGE);
    }

    public function updatedWhen(): void
    {
        $this->resetPage();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="lg" level="2">{{ __('assets.scheduled.title') }}</flux:heading>
        <flux:text class="mt-1">{{ __('assets.scheduled.description') }}</flux:text>
    </div>

    <div class="w-full sm:max-w-xs">
        <flux:select wire:model.live="when" :label="__('assets.scheduled.when')" data-test="scheduled-when">
            @foreach (ScheduledMaintenance::WHEN as $option)
                <flux:select.option :value="$option">{{ __('assets.scheduled.when_options.'.$option) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->records->isEmpty())
        <flux:callout icon="calendar" :heading="__($when === 'all' ? 'assets.scheduled.empty' : 'assets.scheduled.empty_filtered')" data-test="scheduled-empty" />
    @else
        <div class="relative overflow-x-auto">
            <flux:table :paginate="$this->records">
                <flux:table.columns>
                    <flux:table.column>{{ __('assets.scheduled.columns.scheduled_for') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.scheduled.columns.asset') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.scheduled.columns.space') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.scheduled.columns.kind') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.scheduled.columns.description') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.scheduled.columns.registered_by') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->records as $record)
                        @php
                            $overdue = $record->scheduled_for?->isBefore(today());
                            $assetStatus = AssetStatus::tryFrom((string) $record->getAttribute('asset_status'));
                        @endphp
                        <flux:table.row :key="$record->id" data-test="scheduled-row">
                            <flux:table.cell class="whitespace-nowrap">
                                {{ $record->scheduled_for?->format('d/m/Y') }}
                                @if ($overdue)
                                    <flux:badge size="sm" color="red" class="ms-1" data-test="scheduled-overdue">{{ __('assets.maintenance.scheduled.overdue') }}</flux:badge>
                                @elseif ($record->scheduled_for?->isToday())
                                    <flux:badge size="sm" color="amber" class="ms-1">{{ __('assets.maintenance.scheduled.today') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="min-w-48">
                                <flux:link :href="route('assets.show', $record->asset_id)" wire:navigate>{{ $record->asset_code }} · {{ $record->getAttribute('asset_display_name') }}</flux:link>
                                @if ($assetStatus && $assetStatus !== AssetStatus::InService)
                                    <flux:badge size="sm" color="zinc" class="ms-1">{{ $assetStatus->label() }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                {{ $record->getAttribute('asset_space_code') ? $record->getAttribute('asset_space_code').' · '.$record->getAttribute('asset_space_name') : __('assets.show.empty') }}
                            </flux:table.cell>
                            <flux:table.cell variant="strong" class="whitespace-nowrap">{{ $record->kind->label() }}</flux:table.cell>
                            <flux:table.cell class="min-w-48"><span class="whitespace-pre-line">{{ $record->description }}</span></flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">{{ $record->registered_by_name ?? __('assets.maintenance.system') }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</div>
