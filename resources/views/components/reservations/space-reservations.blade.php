<?php

use App\Models\Space;
use Livewire\Component;

/*
 * The reservations of one space (RF_57), embedded in the space page (pages::spaces.show).
 * Owner: reservations (@JoseHerrera21). Read reservations through reporting.reservations (ReservationRow).
 */
new class extends Component {
    public Space $space;
}; ?>

<flux:card>
    <flux:heading level="3" class="mb-3">{{ __('reservations.space_reservations.heading') }}</flux:heading>

    <x-module-placeholder owner="Jose Herrera (@JoseHerrera21)" :requirements="['RF_57' => 'Consultar reservas de un espacio']" />
</flux:card>
