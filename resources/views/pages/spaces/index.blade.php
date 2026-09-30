<?php

use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Espacios (mockup "Espacios"). Owner of the page: reservations (@JoseHerrera21: list and filters, RF_53/RF_54).
 * The search (RF_42) and "Añadir espacio" (RF_32) belong to spaces (@Dizabu). Both work through
 * App\Queries\Spaces\SpaceDirectory, the same query the spaces report (RF_64) uses.
 */
new #[Title('spaces.index.title')] class extends Component {
    //
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-end gap-2">
        <livewire:spaces.create-space />
    </div>

    <x-module-placeholder owner="Jose Herrera (@JoseHerrera21) · Diego Zamora (@Dizabu)" :requirements="[
        'RF_53' => 'Consultar listado de espacios (Jose)',
        'RF_54' => 'Filtrar espacios por atributos (Jose)',
        'RF_42' => 'Búsqueda de espacios (Diego)',
    ]">
        Lista, búsqueda y filtros: <code>App\Queries\Spaces\SpaceDirectory</code>. Permisos: <code>App\Policies\SpacePolicy</code>.
    </x-module-placeholder>
</div>
