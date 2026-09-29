<?php

use App\Models\Space;
use Livewire\Component;

/*
 * Is this space free in a given time block? (RF_55), embedded in the space page (pages::spaces.show).
 * Owner: reservations (@JoseHerrera21). Use Reservation::overlapping() ([start, end) semantics, like the database).
 */
new class extends Component {
    public Space $space;
}; ?>

<flux:card>
    <flux:heading level="3" class="mb-3">{{ __('reservations.availability_check.heading') }}</flux:heading>

    <x-module-placeholder owner="Jose Herrera (@JoseHerrera21)" :requirements="['RF_55' => 'Consultar disponibilidad de un espacio']" />
</flux:card>
