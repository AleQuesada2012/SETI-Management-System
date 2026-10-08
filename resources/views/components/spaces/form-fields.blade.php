{{--
    The fields of a space shared by "Añadir espacio" (RF_32) and "Editar espacio" (RF_33, RF_36). Rendered inside the
    Livewire component's form, so wire:model binds to that component's properties (code, name, space_type_id…).
--}}
@props(['spaceTypes', 'buildings'])

<div class="grid gap-4 sm:grid-cols-2">
    <flux:input wire:model="code" :label="Str::ucfirst(__('spaces.fields.code'))" :placeholder="__('spaces.create.code_placeholder')" maxlength="30" required />
    <flux:input wire:model="name" :label="Str::ucfirst(__('spaces.fields.name'))" maxlength="120" required />
</div>

<flux:select wire:model="space_type_id" :label="Str::ucfirst(__('spaces.fields.space_type_id'))" :placeholder="__('spaces.create.choose')" required>
    @foreach ($spaceTypes as $type)
        <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
    @endforeach
</flux:select>

<flux:select wire:model="building_id" :label="Str::ucfirst(__('spaces.fields.building_id'))" :placeholder="__('spaces.create.choose')" required>
    @foreach ($buildings as $building)
        <flux:select.option :value="$building->id">{{ $building->name }}</flux:select.option>
    @endforeach
</flux:select>

<div class="grid gap-4 sm:grid-cols-2">
    <flux:input wire:model="floor" :label="Str::ucfirst(__('spaces.fields.floor'))" maxlength="20" />
    <flux:input wire:model="capacity" type="number" min="1" step="1" inputmode="numeric" :label="Str::ucfirst(__('spaces.fields.capacity'))" required />
</div>

<flux:input wire:model="location_detail" :label="Str::ucfirst(__('spaces.fields.location_detail'))" :placeholder="__('spaces.create.location_placeholder')" maxlength="200" />

<flux:textarea wire:model="notes" :label="Str::ucfirst(__('spaces.fields.notes'))" rows="2" />

<flux:error name="form" />
