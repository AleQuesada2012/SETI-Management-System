<?php

use App\Models\Reservation;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Reservas (mockup "Reservas"): the monthly calendar of every space. Owner: spaces (@Dizabu, RF_45).
 * Creating (RF_46) and the reservation page (RF_51) belong to reservations (@JoseHerrera21).
 */
new #[Title('reservations.index.title')] class extends Component {
    //
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-end gap-2">
        @can('create', Reservation::class)
            <flux:button variant="primary" icon="plus" :href="route('reservations.create')" wire:navigate>{{ __('reservations.index.create') }}</flux:button>
        @endcan
    </div>

    <x-module-placeholder owner="Diego Zamora (@Dizabu)" :requirements="['RF_45' => 'Revisar calendario de espacios']">
        Datos: <code>App\Models\Reporting\ReservationRow</code> (reporting.reservations).
    </x-module-placeholder>
</div>
