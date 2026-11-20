<?php

use App\Actions\Assets\RegisterAsset;
use App\Models\Asset;
use App\Models\Space;
use App\Queries\Assets\AssetFormChoices;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * RF_03 · Añadir activo, with the fields that close with it: RF_14 acquisition date, RF_15 supplier, RF_16 warranty,
 * RF_18 location, RF_19 observations, RF_23 custodian, RF_24 category (through the asset type). The route checked
 * `create`; the Action checks it again for the chosen space (★). On success it confirms and opens the new asset.
 * The attribute fields are shared with RF_04 (x-assets.fields).
 */
new #[Title('assets.create.title')] class extends Component {
    /** Ids are kept as strings so each select shows its placeholder until something is chosen. */
    public string $space_id = '';

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
        if ($this->spaces->count() === 1) {
            $this->space_id = (string) $this->spaces->first()->id;
        }
    }

    #[Computed]
    public function choices(): AssetFormChoices
    {
        return new AssetFormChoices;
    }

    /** @return Collection<int, Space> */
    #[Computed]
    public function spaces(): Collection
    {
        return $this->choices->spacesFor(auth()->user());
    }

    public function save(RegisterAsset $registerAsset): void
    {
        $this->authorize('create', Asset::class);

        $asset = $registerAsset->handle(auth()->user(), [
            'space_id' => $this->space_id ?: null,
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

        Flux::toast(variant: 'success', text: __('assets.create.done', ['asset' => $asset->code]));

        $this->redirectRoute('assets.show', $asset, navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="lg" level="2">{{ __('assets.create.title') }}</flux:heading>
        <flux:text class="mt-1">{{ __('assets.create.description') }}</flux:text>
    </div>

    @if ($this->spaces->isEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('assets.create.no_spaces')" />
    @endif

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:card class="flex flex-col gap-4">
            <flux:heading level="3">{{ __('assets.create.sections.placement') }}</flux:heading>

            <flux:select wire:model="space_id" :label="Str::ucfirst(__('assets.fields.space_id'))" :placeholder="__('assets.create.choose')" :badge="__('assets.create.required')" required data-test="asset-space">
                @foreach ($this->spaces as $space)
                    <flux:select.option :value="$space->id">{{ $space->code }} · {{ $space->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="location_detail" :label="Str::ucfirst(__('assets.fields.location_detail'))" :placeholder="__('assets.create.location_placeholder')" maxlength="200" />
        </flux:card>

        <x-assets.fields :choices="$this->choices" />

        <flux:error name="form" />

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <flux:button :href="route('assets.index')" wire:navigate variant="ghost" class="w-full sm:w-auto">{{ __('assets.create.cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" class="w-full sm:w-auto" data-test="create-asset-submit">{{ __('assets.create.submit') }}</flux:button>
        </div>
    </form>
</div>
