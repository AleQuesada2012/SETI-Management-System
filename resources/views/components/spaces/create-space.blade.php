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

                <x-spaces.form-fields :space-types="$this->spaceTypes" :buildings="$this->buildings" />

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
