<?php

use App\Actions\Spaces\UpdateSpace;
use App\Models\Building;
use App\Models\Space;
use App\Models\SpaceType;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * RF_33 · "Editar espacio" and RF_36 · capacity: the button and its form, placed on the space page (pages/spaces/show).
 * Only users who may update this space see it (★). The status is changed by RF_44, not here. On success it confirms
 * with a toast and reloads the page so the details show the saved values.
 */
new class extends Component {
    #[Locked]
    public Space $space;

    public string $code = '';

    public string $name = '';

    /** Kept as a string so the select shows the stored value (see create-space). */
    public string $space_type_id = '';

    public string $building_id = '';

    public string $floor = '';

    public string $location_detail = '';

    public ?int $capacity = null;

    public string $notes = '';

    public function mount(): void
    {
        $this->fillFromSpace();
    }

    /** Puts the stored values back in the form (on mount and when the form is closed without saving). */
    public function fillFromSpace(): void
    {
        $this->resetValidation();

        $this->code = $this->space->code;
        $this->name = $this->space->name;
        $this->space_type_id = (string) $this->space->space_type_id;
        $this->building_id = (string) $this->space->building_id;
        $this->floor = (string) $this->space->floor;
        $this->location_detail = (string) $this->space->location_detail;
        $this->capacity = $this->space->capacity;
        $this->notes = (string) $this->space->notes;
    }

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

    public function save(UpdateSpace $updateSpace): void
    {
        $this->authorize('update', $this->space);

        $space = $updateSpace->handle(auth()->user(), $this->space, [
            'code' => trim($this->code),
            'name' => trim($this->name),
            'space_type_id' => $this->space_type_id ?: null,
            'building_id' => $this->building_id ?: null,
            'floor' => trim($this->floor) ?: null,
            'location_detail' => trim($this->location_detail) ?: null,
            'capacity' => $this->capacity,
            'notes' => trim($this->notes) ?: null,
        ]);

        Flux::toast(variant: 'success', text: __('spaces.edit.done', ['space' => $space->name]));

        $this->redirectRoute('spaces.show', $space, navigate: true);
    }
}; ?>

<div>
    @can('update', $space)
        <flux:modal.trigger name="edit-space">
            <flux:button icon="pencil-square" size="sm" data-test="edit-space-button">{{ __('spaces.edit.button') }}</flux:button>
        </flux:modal.trigger>

        <flux:modal name="edit-space" class="w-full max-w-lg" wire:close="fillFromSpace">
            <form wire:submit="save" class="flex flex-col gap-5">
                <div>
                    <flux:heading size="lg">{{ __('spaces.edit.title') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('spaces.edit.description') }}</flux:text>
                </div>

                <x-spaces.form-fields :space-types="$this->spaceTypes" :buildings="$this->buildings" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('spaces.create.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto" data-test="edit-space-submit">{{ __('spaces.edit.submit') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
