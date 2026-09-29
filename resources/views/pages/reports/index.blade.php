<?php

use App\Models\ReportGeneration;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Reportes (RF_60): the menu of report categories (assets, spaces, audit, users) from report_types.
 * Owner: reports (@AleQuesada2012). Each report opens pages::reports.show, which previews and exports it.
 */
new #[Title('reports.index.title')] class extends Component {
    //
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-end gap-2">
        @can('viewAny', ReportGeneration::class)
            <flux:button icon="clock" :href="route('reports.generations')" wire:navigate>{{ __('reports.index.generations') }}</flux:button>
        @endcan
    </div>

    <x-module-placeholder owner="Alejandro Quesada (@AleQuesada2012)" :requirements="[
        'RF_60' => 'Entrar al menú de reportes',
        'RF_63' => 'Generar reporte de inventario de activos',
        'RF_64' => 'Generar reporte de espacios',
    ]">
        Cada reporte es una clase en <code>app/Reports/Definitions</code> registrada en <code>App\Reports\ReportRegistry</code>;
        la exportación (RF_00_4) ya está lista: <code>&lt;livewire:reports.export-button&gt;</code>.
    </x-module-placeholder>
</div>
