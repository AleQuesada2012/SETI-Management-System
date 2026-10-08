{{--
    The fields of a reservation, shared by "Reservar espacio" (RF_46) and "Modificar reserva" (RF_49). The Livewire
    page that renders them owns the bound properties: space_id, date, start_time, end_time, title, attendees,
    responsible_id and notes.

    <x-reservations.form-fields :spaces="$this->spaces" :selected-space="$this->selectedSpace" :responsibles="$this->responsibles" />
--}}
@props([
    'spaces',
    'selectedSpace' => null,
    'responsibles',
])

<div>
    <flux:select wire:model.live="space_id" :label="Str::ucfirst(__('reservations.fields.space_id'))" :placeholder="__('reservations.create.choose_space')" required data-test="reservation-space">
        @foreach ($spaces as $space)
            <flux:select.option :value="$space->id">{{ $space->code }} · {{ $space->name }}</flux:select.option>
        @endforeach
    </flux:select>

    @if ($selectedSpace)
        <flux:text size="sm" class="mt-2">
            {{ __('reservations.create.space_hint', ['location' => $selectedSpace->location(), 'capacity' => $selectedSpace->capacity]) }}
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
        @foreach ($responsibles as $user)
            <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
        @endforeach
    </flux:select>
</div>

<flux:textarea wire:model="notes" :label="Str::ucfirst(__('reservations.fields.notes'))" rows="2" />
