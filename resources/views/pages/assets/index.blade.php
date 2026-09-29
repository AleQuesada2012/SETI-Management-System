<?php

use App\Models\Asset;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Inventario de activos (mockup "Inventario de Activos"). Owner: assets.
 * List and filters come from App\Queries\Assets\AssetInventory, the same query the inventory report (RF_63) uses.
 */
new #[Title('assets.index.title')] class extends Component {
    //
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-end gap-2">
        @can('create', Asset::class)
            <flux:button variant="primary" icon="plus" disabled>{{ __('assets.index.create') }}</flux:button>
        @endcan
    </div>

    <x-module-placeholder owner="David Zamora (@DavidZamoraB)" :requirements="[
        'RF_07' => 'Búsqueda de activos',
        'RF_08' => 'Filtrado de activos por métricas',
        'RF_03' => 'Añadir activo',
    ]">
        Lista y filtros: <code>App\Queries\Assets\AssetInventory</code>. Permisos: <code>App\Policies\AssetPolicy</code>.
    </x-module-placeholder>
</div>
