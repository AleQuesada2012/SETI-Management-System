<?php

use App\Models\Reservation;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Detalle de la reserva (RF_51). Owner: reservations (@JoseHerrera21). Changes call $this->authorize() with
 * ReservationPolicy (update, cancel).
 */
new #[Title('reservations.show.title')] class extends Component {
    public Reservation $reservation;
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="lg" level="2">{{ $reservation->title }}</flux:heading>

    <x-module-placeholder owner="Jose Herrera (@JoseHerrera21)" :requirements="['RF_51' => 'Examinar reserva']" />
</div>
