<?php

use App\Actions\Assets\UpdateAsset;
use App\Models\Asset;
use App\Queries\Assets\AssetFormChoices;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * RF_04 · Editar activo, and RF_05 · Asignar estado (the condition and its color). The route checked `update`
 * (★ for an Administrador); `save` checks it again. The space is shown but not editable: it changes through a
 * transfer (RF_06, pages::assets.move). Shares its fields with RF_03 (x-assets.fields). A decommissioned asset is
 * read-only (RF_29, Asset::isReadOnly): the page says so instead of showing the form, and UpdateAsset refuses it.
 */
new #[Title('assets.edit.title')] class extends Component {
    public Asset $asset;

    /** Ids are kept as strings, as in the registration form. */
    public string $asset_type_id = '';

    public string $condition_id = '';

    public string $condition_note = '';

    public string $name = '';

    public string $asset_tag = '';

    public string $serial_number = '';

    public string $brand_id = '';

    public string $model = '';

    public string $location_detail = '';

    public string $acquired_on = '';

    public string $supplier_id = '';

    public string $warranty_starts_on = '';

    public string $warranty_ends_on = '';

    public string $responsible_user_id = '';

    public string $observations = '';

    public function mount(): void
    {
        $this->asset->load('currentSpace');

        $this->asset_type_id = (string) $this->asset->asset_type_id;
        $this->condition_id = (string) $this->asset->condition_id;
        $this->condition_note = (string) $this->asset->condition_note;
        $this->name = (string) $this->asset->name;
        $this->asset_tag = (string) $this->asset->asset_tag;
        $this->serial_number = (string) $this->asset->serial_number;
        $this->brand_id = (string) $this->asset->brand_id;
        $this->model = (string) $this->asset->model;
        $this->location_detail = (string) $this->asset->location_detail;
        $this->acquired_on = (string) $this->asset->acquired_on?->toDateString();
        $this->supplier_id = (string) $this->asset->supplier_id;
        $this->warranty_starts_on = (string) $this->asset->warranty_starts_on?->toDateString();
        $this->warranty_ends_on = (string) $this->asset->warranty_ends_on?->toDateString();
        $this->responsible_user_id = (string) $this->asset->responsible_user_id;
        $this->observations = (string) $this->asset->observations;
    }

    #[Computed]
    public function choices(): AssetFormChoices
    {
        return new AssetFormChoices;
    }

    public function save(UpdateAsset $updateAsset): void
    {
        $this->authorize('update', $this->asset);

        $previousCondition = $this->asset->condition_id;

        $asset = $updateAsset->handle(auth()->user(), $this->asset, [
            'asset_type_id' => $this->asset_type_id ?: null,
            'condition_id' => $this->condition_id ?: null,
            'condition_note' => trim($this->condition_note) ?: null,
            'name' => trim($this->name) ?: null,
            'asset_tag' => trim($this->asset_tag) ?: null,
            'serial_number' => trim($this->serial_number) ?: null,
            'brand_id' => $this->brand_id ?: null,
            'model' => trim($this->model) ?: null,
            'location_detail' => trim($this->location_detail) ?: null,
            'acquired_on' => $this->acquired_on ?: null,
            'supplier_id' => $this->supplier_id ?: null,
            'warranty_starts_on' => $this->warranty_starts_on ?: null,
            'warranty_ends_on' => $this->warranty_ends_on ?: null,
            'responsible_user_id' => $this->responsible_user_id ?: null,
            'observations' => trim($this->observations) ?: null,
        ]);

        // RF_05: assigning a condition (green, amber or red) is confirmed by name.
        $message = $asset->condition_id !== $previousCondition
            ? __('assets.edit.condition_done', ['asset' => $asset->code, 'condition' => $asset->condition->name])
            : __('assets.edit.done', ['asset' => $asset->code]);

        Flux::toast(variant: 'success', text: $message);

        $this->redirectRoute('assets.show', $asset, navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="lg" level="2">{{ __('assets.edit.title') }}</flux:heading>
        <flux:text class="mt-1">{{ __('assets.edit.description', ['asset' => $asset->code.' · '.$asset->displayName()]) }}</flux:text>
    </div>

    @if ($asset->isReadOnly())
        <flux:callout variant="warning" icon="archive-box-x-mark" :heading="__('assets.edit.read_only', ['asset' => $asset->code])" data-test="edit-read-only" />
        <div>
            <flux:button :href="route('assets.show', $asset)" wire:navigate icon="arrow-left" class="w-full sm:w-auto">{{ __('assets.show.title') }}</flux:button>
        </div>
    @else
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:card class="flex flex-col gap-4">
                <flux:heading level="3">{{ __('assets.create.sections.placement') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ Str::ucfirst(__('assets.fields.space_id')) }}</flux:label>
                    <flux:text class="text-ink" data-test="asset-space">
                        {{ $asset->currentSpace ? $asset->currentSpace->code.' · '.$asset->currentSpace->name : __('assets.show.empty') }}
                    </flux:text>
                    <flux:description>
                        {{ __('assets.edit.space_help') }}
                        @can('move', $asset)
                            <flux:link :href="route('assets.move', $asset)" wire:navigate data-test="edit-move-link">{{ __('assets.edit.move') }}</flux:link>
                        @endcan
                    </flux:description>
                </flux:field>

                <flux:input wire:model="location_detail" :label="Str::ucfirst(__('assets.fields.location_detail'))" :placeholder="__('assets.create.location_placeholder')" maxlength="200" />
            </flux:card>

            <x-assets.fields :choices="$this->choices" />

            <flux:error name="form" />

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button :href="route('assets.show', $asset)" wire:navigate variant="ghost" class="w-full sm:w-auto">{{ __('assets.create.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" class="w-full sm:w-auto" data-test="edit-asset-submit">{{ __('assets.edit.submit') }}</flux:button>
            </div>
        </form>
    @endif
</div>
