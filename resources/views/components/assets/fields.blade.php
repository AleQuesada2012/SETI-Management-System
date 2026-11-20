{{--
    The asset form's attribute fields, shared by "Añadir activo" (RF_03) and "Editar activo" (RF_04, RF_05). The
    enclosing Livewire page owns the properties bound with wire:model (asset_type_id, condition_id, …) and the
    validation (App\Actions\Assets\RegisterAsset::rules()). The space is not here: it is chosen on registration and
    changes only through a transfer (RF_06).
--}}
@props(['choices']) {{-- App\Queries\Assets\AssetFormChoices --}}

<flux:card class="flex flex-col gap-4">
    <flux:heading level="3">{{ __('assets.create.sections.identification') }}</flux:heading>

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:select wire:model="asset_type_id" :label="Str::ucfirst(__('assets.fields.asset_type_id'))" :placeholder="__('assets.create.choose')" :badge="__('assets.create.required')" required>
            @foreach ($choices->assetTypes() as $category => $types)
                <optgroup label="{{ $category }}">
                    @foreach ($types as $type)
                        <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                    @endforeach
                </optgroup>
            @endforeach
        </flux:select>

        <flux:select wire:model="condition_id" :label="Str::ucfirst(__('assets.fields.condition_id'))" :placeholder="__('assets.create.choose')" :badge="__('assets.create.required')" required>
            @foreach ($choices->conditions() as $condition)
                <flux:select.option :value="$condition->id">{{ $condition->name }} ({{ $condition->color_group->label() }})</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:textarea wire:model="condition_note" :label="Str::ucfirst(__('assets.fields.condition_note'))" :placeholder="__('assets.create.condition_note_placeholder')" rows="2" maxlength="2000" />

    <flux:input wire:model="name" :label="Str::ucfirst(__('assets.fields.name'))" :description:trailing="__('assets.create.name_help')" maxlength="150" />

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:select wire:model="brand_id" :label="Str::ucfirst(__('assets.fields.brand_id'))">
            <flux:select.option value="">{{ __('assets.create.none') }}</flux:select.option>
            @foreach ($choices->brands() as $brand)
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

        <flux:select wire:model="supplier_id" :label="Str::ucfirst(__('assets.fields.supplier_id'))">
            <flux:select.option value="">{{ __('assets.create.none') }}</flux:select.option>
            @foreach ($choices->suppliers() as $supplier)
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

    <flux:select wire:model="responsible_user_id" :label="Str::ucfirst(__('assets.fields.responsible_user_id'))">
        {{-- A real option, not a placeholder: Flux disables placeholders, so a value could never be cleared (DEF-15). --}}
        <flux:select.option value="">{{ __('assets.create.none') }}</flux:select.option>
        @foreach ($choices->custodians() as $custodian)
            <flux:select.option :value="$custodian->id">{{ $custodian->name }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:textarea wire:model="observations" :label="Str::ucfirst(__('assets.fields.observations'))" rows="3" maxlength="2000" />
</flux:card>
