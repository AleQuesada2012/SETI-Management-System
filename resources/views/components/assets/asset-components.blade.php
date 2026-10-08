<?php

use App\Actions\Assets\AddAssetComponent;
use App\Actions\Assets\RemoveAssetComponent;
use App\Actions\Assets\UpdateAssetComponent;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetComponent;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * RF_31 · the "Componentes" card on the asset page (pages/assets/show): everyone who may view the asset sees its
 * components, or a notice that it has none. RF_12 · users who may manage this asset's components (★) add, modify and
 * delete them in modals here; a decommissioned asset shows them read-only.
 */
new class extends Component {
    #[Locked]
    public Asset $asset;

    /** The component being modified, or null while adding one. */
    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $removingId = null;

    public string $name = '';

    public string $specs = '';

    public string $serial_number = '';

    public string $quantity = '1';

    public string $notes = '';

    /** @return EloquentCollection<int, AssetComponent> */
    #[Computed]
    public function components(): EloquentCollection
    {
        return $this->asset->components()->orderBy('name')->orderBy('id')->get();
    }

    public function canManage(): bool
    {
        return $this->asset->status !== AssetStatus::Decommissioned && (auth()->user()?->can('manageComponents', $this->asset) ?? false);
    }

    public function add(): void
    {
        $this->authorize('manageComponents', $this->asset);

        $this->resetForm();
        Flux::modal('component-form')->show();
    }

    public function edit(int $componentId): void
    {
        $this->authorize('manageComponents', $this->asset);

        $component = $this->component($componentId);

        $this->resetForm();
        $this->editingId = $component->id;
        $this->name = $component->name;
        $this->specs = $component->specs ?? '';
        $this->serial_number = $component->serial_number ?? '';
        $this->quantity = (string) $component->quantity;
        $this->notes = $component->notes ?? '';
        Flux::modal('component-form')->show();
    }

    public function save(AddAssetComponent $addComponent, UpdateAssetComponent $updateComponent): void
    {
        $this->authorize('manageComponents', $this->asset);

        $input = [
            'name' => trim($this->name),
            'specs' => trim($this->specs) ?: null,
            'serial_number' => trim($this->serial_number) ?: null,
            'quantity' => trim($this->quantity),
            'notes' => trim($this->notes) ?: null,
        ];

        $component = $this->editingId === null
            ? $addComponent->handle(auth()->user(), $this->asset, $input)
            : $updateComponent->handle(auth()->user(), $this->component($this->editingId), $input);

        Flux::toast(variant: 'success', text: __($this->editingId === null ? 'assets.components.added' : 'assets.components.updated', ['component' => $component->name]));
        Flux::modal('component-form')->close();
        $this->resetForm();
        unset($this->components);
    }

    public function confirmRemove(int $componentId): void
    {
        $this->authorize('manageComponents', $this->asset);

        $this->removingId = $this->component($componentId)->id;
        Flux::modal('remove-component')->show();
    }

    public function remove(RemoveAssetComponent $removeComponent): void
    {
        $this->authorize('manageComponents', $this->asset);

        $component = $this->component((int) $this->removingId);
        $removeComponent->handle(auth()->user(), $component);

        Flux::toast(variant: 'success', text: __('assets.components.removed', ['component' => $component->name]));
        Flux::modal('remove-component')->close();
        $this->removingId = null;
        unset($this->components);
    }

    #[Computed]
    public function removing(): ?AssetComponent
    {
        return $this->removingId === null ? null : $this->asset->components()->find($this->removingId);
    }

    /** A component of this asset only: another asset's id answers 404. */
    private function component(int $componentId): AssetComponent
    {
        return $this->asset->components()->findOrFail($componentId);
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'specs', 'serial_number', 'quantity', 'notes');
        $this->resetValidation();
    }
}; ?>

<flux:card class="flex flex-col gap-3" data-test="asset-components">
    @php
        $empty = __('assets.show.empty');
        $canManage = $this->canManage();
    @endphp
    <div class="flex flex-wrap items-center gap-3">
        <flux:heading level="3">{{ __('assets.components.title') }}</flux:heading>
        @if ($canManage)
            <flux:button wire:click="add" icon="plus" size="sm" class="ms-auto" data-test="add-component-button">{{ __('assets.components.add') }}</flux:button>
        @endif
    </div>

    @if ($this->components->isEmpty())
        <flux:text data-test="no-components">{{ __('assets.components.empty') }}</flux:text>
    @else
        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ Str::ucfirst(__('assets.components.fields.name')) }}</flux:table.column>
                    <flux:table.column>{{ Str::ucfirst(__('assets.components.fields.specs')) }}</flux:table.column>
                    <flux:table.column>{{ Str::ucfirst(__('assets.components.fields.serial_number')) }}</flux:table.column>
                    <flux:table.column>{{ Str::ucfirst(__('assets.components.fields.quantity')) }}</flux:table.column>
                    <flux:table.column>{{ Str::ucfirst(__('assets.components.fields.notes')) }}</flux:table.column>
                    @if ($canManage)
                        <flux:table.column><span class="sr-only">{{ __('assets.components.actions') }}</span></flux:table.column>
                    @endif
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->components as $component)
                        <flux:table.row :key="$component->id" data-test="component-row">
                            <flux:table.cell variant="strong" class="whitespace-nowrap">{{ $component->name }}</flux:table.cell>
                            <flux:table.cell class="min-w-40 whitespace-pre-line">{{ $component->specs ?: $empty }}</flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">{{ $component->serial_number ?: $empty }}</flux:table.cell>
                            <flux:table.cell>{{ $component->quantity }}</flux:table.cell>
                            <flux:table.cell class="min-w-40 whitespace-pre-line">{{ $component->notes ?: $empty }}</flux:table.cell>
                            @if ($canManage)
                                <flux:table.cell>
                                    <div class="flex justify-end gap-1">
                                        <flux:button wire:click="edit({{ $component->id }})" icon="pencil-square" size="sm" variant="ghost" :aria-label="__('assets.components.edit', ['component' => $component->name])" data-test="edit-component-button" />
                                        <flux:button wire:click="confirmRemove({{ $component->id }})" icon="trash" size="sm" variant="ghost" :aria-label="__('assets.components.remove', ['component' => $component->name])" data-test="remove-component-button" />
                                    </div>
                                </flux:table.cell>
                            @endif
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    @if ($canManage)
        <flux:modal name="component-form" class="w-full max-w-lg">
            <form wire:submit="save" class="flex flex-col gap-5">
                <flux:heading size="lg">{{ $editingId === null ? __('assets.components.add_title') : __('assets.components.edit_title') }}</flux:heading>

                <flux:input wire:model="name" :label="Str::ucfirst(__('assets.components.fields.name'))" :placeholder="__('assets.components.name_placeholder')" maxlength="120" required data-test="component-name" />
                <flux:textarea wire:model="specs" :label="Str::ucfirst(__('assets.components.fields.specs'))" :placeholder="__('assets.components.specs_placeholder')" rows="2" maxlength="1000" />
                <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
                    <flux:input wire:model="serial_number" :label="Str::ucfirst(__('assets.components.fields.serial_number'))" maxlength="100" />
                    <flux:input type="number" wire:model="quantity" :label="Str::ucfirst(__('assets.components.fields.quantity'))" min="1" max="9999" step="1" required data-test="component-quantity" />
                </div>
                <flux:textarea wire:model="notes" :label="Str::ucfirst(__('assets.components.fields.notes'))" rows="2" maxlength="1000" />

                <flux:error name="form" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('assets.components.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto" data-test="save-component">{{ __('assets.components.save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="remove-component" class="w-full max-w-md">
            <div class="flex flex-col gap-5">
                <flux:heading size="lg">{{ __('assets.components.remove_title', ['component' => $this->removing?->name]) }}</flux:heading>
                <flux:text>{{ __('assets.components.remove_description') }}</flux:text>

                <flux:error name="form" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('assets.components.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="remove" variant="danger" class="w-full sm:w-auto" data-test="confirm-remove-component">{{ __('assets.components.confirm_remove') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</flux:card>
