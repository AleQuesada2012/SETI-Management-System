<?php

use App\Actions\Assets\RegisterAsset;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Space;
use App\Models\Supplier;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * RF_03 · Añadir activo, with the fields that close with it: RF_14 acquisition date, RF_15 supplier, RF_16 warranty,
 * RF_18 location, RF_19 observations, RF_23 custodian, RF_24 category (through the asset type). The route checked
 * `create`; the Action checks it again for the chosen space (★). On success it confirms and opens the new asset.
 */
new #[Title('assets.create.title')] class extends Component {
    /** Ids are kept as strings so each select shows its placeholder until something is chosen. */
    public string $space_id = '';

    public string $asset_type_id = '';

    public string $condition_id = '';

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

    /**
     * The spaces the user may register assets in: every space for the Superusuario, the managed ones otherwise.
     *
     * @return Collection<int, Space>
     */
    #[Computed]
    public function spaces(): Collection
    {
        $user = auth()->user();

        $query = $user->isSuperuser() ? Space::query() : $user->managedSpaces()->getQuery();

        return $query->orderBy('spaces.code')->get(['spaces.id', 'spaces.code', 'spaces.name']);
    }

    /**
     * Asset types grouped by their category (RF_24): the asset stores the type, the category is derived.
     *
     * @return Collection<string, Collection<int, AssetType>>
     */
    #[Computed]
    public function assetTypes(): Collection
    {
        return AssetType::query()->with('category')->orderBy('name')->get()
            ->groupBy(fn (AssetType $type): string => $type->category->name ?? '')
            ->sortKeys();
    }

    /** @return Collection<int, AssetCondition> */
    #[Computed]
    public function conditions(): Collection
    {
        return AssetCondition::query()->orderBy('sort_order')->get();
    }

    /** @return Collection<int, Brand> */
    #[Computed]
    public function brands(): Collection
    {
        return Brand::query()->orderBy('name')->get();
    }

    /** @return Collection<int, Supplier> */
    #[Computed]
    public function suppliers(): Collection
    {
        return Supplier::query()->orderBy('name')->get();
    }

    /**
     * Active users who can be the custodian (RF_23).
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function custodians(): Collection
    {
        return User::query()->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']);
    }

    public function save(RegisterAsset $registerAsset): void
    {
        $this->authorize('create', Asset::class);

        $asset = $registerAsset->handle(auth()->user(), [
            'space_id' => $this->space_id ?: null,
            'asset_type_id' => $this->asset_type_id ?: null,
            'condition_id' => $this->condition_id ?: null,
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

            <flux:select wire:model="space_id" :label="Str::ucfirst(__('assets.fields.space_id'))" :placeholder="__('assets.create.choose')" required data-test="asset-space">
                @foreach ($this->spaces as $space)
                    <flux:select.option :value="$space->id">{{ $space->code }} · {{ $space->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="location_detail" :label="Str::ucfirst(__('assets.fields.location_detail'))" :placeholder="__('assets.create.location_placeholder')" maxlength="200" />
        </flux:card>

        <flux:card class="flex flex-col gap-4">
            <flux:heading level="3">{{ __('assets.create.sections.identification') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="asset_type_id" :label="Str::ucfirst(__('assets.fields.asset_type_id'))" :placeholder="__('assets.create.choose')" required>
                    @foreach ($this->assetTypes as $category => $types)
                        <optgroup label="{{ $category }}">
                            @foreach ($types as $type)
                                <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </flux:select>

                <flux:select wire:model="condition_id" :label="Str::ucfirst(__('assets.fields.condition_id'))" :placeholder="__('assets.create.choose')" required>
                    @foreach ($this->conditions as $condition)
                        <flux:select.option :value="$condition->id">{{ $condition->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:input wire:model="name" :label="Str::ucfirst(__('assets.fields.name'))" :description:trailing="__('assets.create.name_help')" maxlength="150" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="brand_id" :label="Str::ucfirst(__('assets.fields.brand_id'))" :placeholder="__('assets.create.none')">
                    @foreach ($this->brands as $brand)
                        <flux:select.option :value="$brand->id">{{ $brand->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="model" :label="Str::ucfirst(__('assets.fields.model'))" maxlength="120" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="asset_tag" :label="Str::ucfirst(__('assets.fields.asset_tag'))" maxlength="50" />
                <flux:input wire:model="serial_number" :label="Str::ucfirst(__('assets.fields.serial_number'))" maxlength="100" />
            </div>
        </flux:card>

        <flux:card class="flex flex-col gap-4">
            <flux:heading level="3">{{ __('assets.create.sections.acquisition') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="acquired_on" type="date" :max="now()->toDateString()" :label="Str::ucfirst(__('assets.fields.acquired_on'))" />

                <flux:select wire:model="supplier_id" :label="Str::ucfirst(__('assets.fields.supplier_id'))" :placeholder="__('assets.create.none')">
                    @foreach ($this->suppliers as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="warranty_starts_on" type="date" :label="Str::ucfirst(__('assets.fields.warranty_starts_on'))" />
                <flux:input wire:model="warranty_ends_on" type="date" :label="Str::ucfirst(__('assets.fields.warranty_ends_on'))" />
            </div>
            <flux:text size="sm">{{ __('assets.create.warranty_help') }}</flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-4">
            <flux:heading level="3">{{ __('assets.create.sections.custody') }}</flux:heading>

            <flux:select wire:model="responsible_user_id" :label="Str::ucfirst(__('assets.fields.responsible_user_id'))" :placeholder="__('assets.create.none')">
                @foreach ($this->custodians as $custodian)
                    <flux:select.option :value="$custodian->id">{{ $custodian->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="observations" :label="Str::ucfirst(__('assets.fields.observations'))" rows="3" maxlength="2000" />
        </flux:card>

        <flux:error name="form" />

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <flux:button :href="route('assets.index')" wire:navigate variant="ghost" class="w-full sm:w-auto">{{ __('assets.create.cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" class="w-full sm:w-auto" data-test="create-asset-submit">{{ __('assets.create.submit') }}</flux:button>
        </div>
    </form>
</div>
