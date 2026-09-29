<?php

use App\Models\Space;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Detalle del espacio. Owner: spaces (@Dizabu). It embeds two components owned by reservations (@JoseHerrera21):
 * the space's reservations (RF_57) and its availability (RF_55). Changes made from here call $this->authorize()
 * with SpacePolicy (update, delete, managePhotos…).
 */
new #[Title('spaces.show.title')] class extends Component {
    public Space $space;
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="lg" level="2">{{ $space->code }} · {{ $space->name }}</flux:heading>

    <x-module-placeholder owner="Diego Zamora (@Dizabu)" :requirements="[
        'RF_35' => 'Consultar información de un espacio',
        'RF_33' => 'Editar espacio',
        'RF_36' => 'Actualizar la capacidad de un espacio',
        'RF_44' => 'Asignar estado a un laboratorio',
    ]" />

    <div class="grid gap-6 lg:grid-cols-2">
        <livewire:reservations.space-reservations :space="$space" />
        <livewire:reservations.availability-check :space="$space" />
    </div>
</div>
