<?php

use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Reservar espacio (RF_46). Owner: reservations (@JoseHerrera21). The route already checked `create`; the save
 * action must authorize again, run in one DB::transaction() and let reservations_no_overlap be the final judge
 * (App\Support\Database\ConstraintViolation turns it into a Spanish message).
 */
new #[Title('reservations.create.title')] class extends Component {
    //
}; ?>

<div class="flex flex-col gap-6">
    <x-module-placeholder owner="Jose Herrera (@JoseHerrera21)" :requirements="['RF_46' => 'Reservar espacio']" />
</div>
