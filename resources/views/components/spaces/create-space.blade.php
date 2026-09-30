<?php

use App\Actions\Spaces\CreateSpace;
use App\Models\Building;
use App\Models\Space;
use App\Models\SpaceType;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * RF_32 · "Añadir espacio": the button and its form, placed on the spaces list (pages/spaces/index) through the
 * slot that page keeps for spaces. On success it confirms with a toast and opens the new space.
 */
new class extends Component {
    public string $code = '';

    public string $name = '';

    /** Kept as a string so the select's placeholder shows until a type is chosen. */
    public string $space_type_id = '';

    public string $building_id = '';

    public string $floor = '';

    public string $location_detail = '';

    public ?int $capacity = null;

    public string $notes = '';

    /** @return Collection<int, SpaceType> */
    #[Computed]
    public function spaceTypes(): Collection
    {
        return SpaceType::query()->orderBy('name')->get();
    }

    /** @return Collection<int, Building> */
    #[Computed]
    public function buildings(): Collection
    {
        return Building::query()->orderBy('name')->get();
    }

    public function save(CreateSpace $createSpace): void
    {
        $this->authorize('create', Space::class);

        $space = $createSpace->handle(auth()->user(), [
            'code' => trim($this->code),
            'name' => trim($this->name),
            'space_type_id' => $this->space_type_id ?: null,
            'building_id' => $this->building_id ?: null,
            'floor' => trim($this->floor) ?: null,
            'location_detail' => trim($this->location_detail) ?: null,
            'capacity' => $this->capacity,
            'notes' => trim($this->notes) ?: null,
        ]);

        Flux::toast(variant: 'success', text: __('spaces.create.done', ['space' => $space->name]));

        $this->redirectRoute('spaces.show', $space, navigate: true);
    }
}; ?>

<div>
    @can('create', Space::class)
        <flux:modal.trigger name="create-space">
            <flux:button variant="primary" icon="plus" data-test="create-space-button">{{ __('spaces.index.create') }}</flux:button>
        </flux:modal.trigger>

        <flux:modal name="create-space" class="w-full max-w-lg">
            <form wire:submit="save" class="flex flex-col gap-5">
                <div>
                    <flux:heading size="lg">{{ __('spaces.create.title') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('spaces.create.description') }}</flux:text>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="code" :label="Str::ucfirst(__('spaces.fields.code'))" :placeholder="__('spaces.create.code_placeholder')" maxlength="30" required />
                    <flux:input wire:model="name" :label="Str::ucfirst(__('spaces.fields.name'))" maxlength="120" required />
                </div>

                <flux:select wire:model="space_type_id" :label="Str::ucfirst(__('spaces.fields.space_type_id'))" :placeholder="__('spaces.create.choose')" required>
                    @foreach ($this->spaceTypes as $type)
                        <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="building_id" :label="Str::ucfirst(__('spaces.fields.building_id'))" :placeholder="__('spaces.create.choose')" required>
                    @foreach ($this->buildings as $building)
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

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('spaces.create.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto" data-test="create-space-submit">{{ __('spaces.create.submit') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
