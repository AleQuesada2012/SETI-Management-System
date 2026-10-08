<?php

use App\Actions\Reservations\CreateReservation;
use App\Models\Reporting\SpaceRow;
use App\Models\Reservation;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Reservar espacio (RF_46). Owner: reservations (@JoseHerrera21). The route already checked `create`; the save
 * action must authorize again, run in one DB::transaction() and let reservations_no_overlap be the final judge
 * (App\Support\Database\ConstraintViolation turns it into a Spanish message).
 *
 * The availability check of a space (RF_55) links here with ?space=&date=&start=&end= to book the block it found free.
 */
new #[Title('reservations.create.title')] class extends Component {
    public string $space_id = '';

    public string $date = '';

    public string $start_time = '';

    public string $end_time = '';

    public string $title = '';

    public ?int $attendees = null;

    public string $responsible_id = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->space_id = (string) request()->integer('space') ?: '';
        $this->date = (string) request()->string('date') ?: now()->toDateString();
        $this->start_time = (string) request()->string('start');
        $this->end_time = (string) request()->string('end');
        $this->responsible_id = (string) auth()->id();
    }

    /**
     * Only reservable spaces are offered (RF_46 diagram: "el espacio no se ofrece dentro de la lista de espacios
     * reservables").
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
     * The reservation responsible: any active user; it starts as the person booking (CONTEXT.md).
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function responsibles(): Collection
    {
        return User::query()->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name', 'email']);
    }

    public function save(CreateReservation $createReservation): void
    {
        $this->authorize('create', Reservation::class);

        $reservation = $createReservation->handle(auth()->user(), [
            'space_id' => $this->space_id,
            'date' => $this->date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'title' => $this->title,
            'attendees' => $this->attendees,
            'responsible_id' => $this->responsible_id,
            'notes' => $this->notes,
        ]);

        Flux::toast(variant: 'success', text: __('reservations.create.done', ['space' => $reservation->space->name]));

        $this->redirectRoute('reservations.show', $reservation, navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:card class="w-full max-w-3xl">
        <form wire:submit="save" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg" level="2">{{ __('reservations.create.heading') }}</flux:heading>
                <flux:text class="mt-1">{{ __('reservations.create.description', ['hours' => CreateReservation::allowedHoursMessage()]) }}</flux:text>
            </div>

            <div>
                <flux:select wire:model.live="space_id" :label="Str::ucfirst(__('reservations.fields.space_id'))" :placeholder="__('reservations.create.choose_space')" required data-test="reservation-space">
                    @foreach ($this->spaces as $space)
                        <flux:select.option :value="$space->id">{{ $space->code }} · {{ $space->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($this->selectedSpace)
                    <flux:text size="sm" class="mt-2">
                        {{ __('reservations.create.space_hint', ['location' => $this->selectedSpace->location(), 'capacity' => $this->selectedSpace->capacity]) }}
                    </flux:text>
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="date" type="date" :label="Str::ucfirst(__('reservations.fields.date'))" required />
                <flux:input wire:model="start_time" type="time" step="900" :label="Str::ucfirst(__('reservations.fields.start_time'))" required />
                <flux:input wire:model="end_time" type="time" step="900" :label="Str::ucfirst(__('reservations.fields.end_time'))" required />
            </div>

            <flux:input wire:model="title" :label="Str::ucfirst(__('reservations.fields.title'))" :placeholder="__('reservations.create.title_placeholder')" maxlength="150" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="attendees" type="number" min="1" step="1" inputmode="numeric" :label="Str::ucfirst(__('reservations.fields.attendees'))" required />

                <flux:select wire:model="responsible_id" :label="Str::ucfirst(__('reservations.fields.responsible_id'))" required>
                    @foreach ($this->responsibles as $user)
                        <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:textarea wire:model="notes" :label="Str::ucfirst(__('reservations.fields.notes'))" rows="2" />

            <flux:error name="form" />

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" :href="route('reservations.index')" wire:navigate class="w-full sm:w-auto">{{ __('reservations.create.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" class="w-full sm:w-auto" data-test="reservation-submit">{{ __('reservations.create.submit') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
