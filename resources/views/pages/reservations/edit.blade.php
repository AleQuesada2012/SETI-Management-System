<?php

use App\Actions\Reservations\CreateReservation;
use App\Actions\Reservations\UpdateReservation;
use App\Enums\ReservationEffectiveStatus;
use App\Models\Reporting\SpaceRow;
use App\Models\Reservation;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Modificar reserva (RF_49). Owner: reservations (@JoseHerrera21). The route already checked `update`; the save
 * action authorizes again and UpdateReservation checks the same rules as a new booking, refusing a reservation that
 * changed since the user opened this form ($readVersion). Reservations that started or were cancelled are not
 * modified (RF_49 diagram): opening this page for one goes back to its detail.
 */
new #[Title('reservations.edit.title')] class extends Component {
    public Reservation $reservation;

    /** The version the form was loaded from: a change made by someone else meanwhile refuses the save. */
    #[Locked]
    public int $readVersion = 1;

    public string $space_id = '';

    public string $date = '';

    public string $start_time = '';

    public string $end_time = '';

    public string $title = '';

    public ?int $attendees = null;

    public string $responsible_id = '';

    public string $notes = '';

    public string $modification_reason = '';

    public function mount(): void
    {
        if ($this->reservation->effectiveStatus() !== ReservationEffectiveStatus::Upcoming) {
            $this->redirectRoute('reservations.show', $this->reservation, navigate: true);

            return;
        }

        $this->readVersion = $this->reservation->version;
        $this->space_id = (string) $this->reservation->space_id;
        $this->date = $this->reservation->starts_at->format('Y-m-d');
        $this->start_time = $this->reservation->starts_at->format('H:i');
        $this->end_time = $this->reservation->ends_at->format('H:i');
        $this->title = $this->reservation->title;
        $this->attendees = $this->reservation->attendees;
        $this->responsible_id = (string) $this->reservation->responsible_id;
        $this->notes = (string) $this->reservation->notes;
    }

    /**
     * The reservable spaces it can move to, its own included while it stays reservable.
     *
     * @return Collection<int, SpaceRow>
     */
    #[Computed]
    public function spaces(): Collection
    {
        return SpaceRow::query()->where('is_reservable', true)->orderBy('name')->get();
    }

    #[Computed]
    public function selectedSpace(): ?SpaceRow
    {
        return $this->spaces->firstWhere('id', (int) $this->space_id);
    }

    /**
     * The reservation responsible: any active user (CONTEXT.md).
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function responsibles(): Collection
    {
        return User::query()->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name', 'email']);
    }

    public function save(UpdateReservation $updateReservation): void
    {
        $this->authorize('update', $this->reservation);

        $reservation = $updateReservation->handle(auth()->user(), $this->reservation, $this->readVersion, [
            'space_id' => $this->space_id,
            'date' => $this->date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'title' => $this->title,
            'attendees' => $this->attendees,
            'responsible_id' => $this->responsible_id,
            'notes' => $this->notes,
            'modification_reason' => $this->modification_reason,
        ]);

        Flux::toast(variant: 'success', text: __('reservations.edit.done', ['space' => $reservation->space->name]));

        $this->redirectRoute('reservations.show', $reservation, navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:card class="w-full max-w-3xl">
        <form wire:submit="save" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg" level="2">{{ __('reservations.edit.heading') }}</flux:heading>
                <flux:text class="mt-1">{{ __('reservations.edit.description', ['hours' => CreateReservation::allowedHoursMessage()]) }}</flux:text>
            </div>

            <x-reservations.form-fields :spaces="$this->spaces" :selected-space="$this->selectedSpace" :responsibles="$this->responsibles" :start-time="$start_time" />

            <flux:textarea wire:model="modification_reason" :label="__('reservations.edit.reason')" :placeholder="__('reservations.edit.reason_placeholder')" rows="2" data-test="modification-reason" />

            <flux:error name="form" />

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" :href="route('reservations.show', $reservation)" wire:navigate class="w-full sm:w-auto">{{ __('reservations.edit.back') }}</flux:button>
                <flux:button type="submit" variant="primary" class="w-full sm:w-auto" data-test="reservation-save">{{ __('reservations.edit.submit') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
