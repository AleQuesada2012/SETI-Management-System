<?php

use App\Enums\PermissionName;
use App\Models\Reporting\AuditLogRow;
use App\Models\Reporting\DashboardKpis;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Tablero (mockup "Dashboard"): the KPI cards and, for users who may read the audit trail, the latest activity.
 * Reference page for the project's conventions: it reads only reporting.* views (ADR-0010), its route checks the
 * permission (routes/modules/dashboard.php), its strings live in lang/es/dashboard.php.
 */
new #[Title('dashboard.title')] class extends Component {
    #[Computed]
    public function kpis(): DashboardKpis
    {
        return DashboardKpis::current();
    }

    /** @return Collection<int, AuditLogRow> */
    #[Computed]
    public function activity(): Collection
    {
        if (! auth()->user()->can(PermissionName::ReportsAudit)) {
            return collect();
        }

        return AuditLogRow::query()->latest('occurred_at')->limit(8)->get();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['value' => number_format($this->kpis->assets_total), 'label' => __('dashboard.kpis.assets_total'), 'bar' => 'bg-chart-1'],
            ['value' => $this->kpis->spaces_available.' / '.$this->kpis->spaces_total, 'label' => __('dashboard.kpis.spaces_available'), 'bar' => 'bg-chart-2'],
            ['value' => number_format($this->kpis->reservations_today), 'label' => __('dashboard.kpis.reservations_today'), 'bar' => 'bg-chart-3'],
            ['value' => number_format($this->kpis->maintenance_pending), 'label' => __('dashboard.kpis.maintenance_pending'), 'bar' => 'bg-chart-4'],
        ] as $kpi)
            <flux:card size="sm" class="flex flex-col gap-1">
                <div class="mb-2 h-1 w-8 rounded-full {{ $kpi['bar'] }}"></div>
                <span class="text-2xl font-semibold text-ink">{{ $kpi['value'] }}</span>
                <flux:text size="sm">{{ $kpi['label'] }}</flux:text>
            </flux:card>
        @endforeach
    </div>

    @can(PermissionName::ReportsAudit)
        <flux:card>
            <flux:heading level="2" class="mb-2">{{ __('dashboard.activity.heading') }}</flux:heading>

            @if ($this->activity->isEmpty())
                <flux:text>{{ __('dashboard.activity.empty') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('dashboard.activity.when') }}</flux:table.column>
                        <flux:table.column>{{ __('dashboard.activity.who') }}</flux:table.column>
                        <flux:table.column>{{ __('dashboard.activity.what') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('dashboard.activity.target') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->activity as $entry)
                            <flux:table.row :key="$entry->id">
                                <flux:table.cell class="whitespace-nowrap text-ink-muted" :title="$entry->occurred_at->isoFormat('LLL')">
                                    {{ $entry->occurred_at->diffForHumans() }}
                                </flux:table.cell>
                                <flux:table.cell variant="strong">{{ $entry->actor_label ?? __('dashboard.activity.system') }}</flux:table.cell>
                                <flux:table.cell>
                                    {{ $entry->action->label() }}
                                    @if ($entry->entity_type && $entry->entity_type !== 'user')
                                        {{ __('app.entities.'.$entry->entity_type) }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="max-md:hidden">{{ $entry->entity_label }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endcan
</div>
