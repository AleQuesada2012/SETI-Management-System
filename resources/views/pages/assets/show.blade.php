<?php

use App\Models\Asset;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Detalle del activo. Owner: assets. The route already checked `view` (routes/modules/assets.php);
 * every change made from here must call $this->authorize() with AssetPolicy (update, move, registerEvent…).
 */
new #[Title('assets.show.title')] class extends Component {
    public Asset $asset;
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="lg" level="2">{{ $asset->code }} · {{ $asset->displayName() }}</flux:heading>

    <x-module-placeholder owner="David Zamora (@DavidZamoraB)" :requirements="[
        'RF_01' => 'Consultar información de activo',
        'RF_04' => 'Editar activo',
        'RF_05' => 'Asignar estado a activo',
    ]" />
</div>
