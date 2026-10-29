<?php

use App\Actions\Assets\RegisterMaintenance;
use App\Enums\MaintenanceKind;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\Reporting\MaintenanceRow;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * RF_10, RF_26 · the "Mantenimientos" card on the asset page (pages/assets/show): everyone who may consult maintenance
 * sees the asset's maintenance with its date, kind, description, who did the work and who recorded it. Users who may
 * manage this asset's maintenance (★ MaintenanceRecordPolicy::create) register corrective or preventive maintenance
 * already done in a modal here (RegisterMaintenance), while the asset is in service or in maintenance.
 */
new class extends Component {
    #[Locked]
    public Asset $asset;

    public string $kind = '';

    public string $performed_on = '';

    public string $performed_by = '';

    public string $description = '';

    /**
     * Newest first, by the day the work was done (or is planned for: reporting.maintenance.reference_date).
     *
     * @return EloquentCollection<int, MaintenanceRow>
     */
    #[Computed]
    public function records(): EloquentCollection
    {
        return MaintenanceRow::query()->where('asset_id', $this->asset->id)->orderByDesc('reference_date')->orderByDesc('id')->get();
    }

    public function canRegister(): bool
    {
        return RegisterMaintenance::isAllowed($this->asset)
            && (auth()->user()?->can('create', [MaintenanceRecord::class, $this->asset]) ?? false);
    }

    public function open(): void
    {
        $this->authorize('create', [MaintenanceRecord::class, $this->asset]);

        $this->reset('kind', 'performed_on', 'performed_by', 'description');
        $this->resetValidation();
        $this->kind = MaintenanceKind::Corrective->value;
        $this->performed_on = today()->toDateString();
        Flux::modal('asset-maintenance-form')->show();
    }

    public function save(RegisterMaintenance $registerMaintenance): void
    {
        $this->authorize('create', [MaintenanceRecord::class, $this->asset]);

        $registerMaintenance->handle(auth()->user(), $this->asset, [
            'kind' => $this->kind,
            'performed_on' => $this->performed_on,
            'performed_by' => trim($this->performed_by),
            'description' => trim($this->description),
        ]);

        Flux::modal('asset-maintenance-form')->close();
        Flux::toast(variant: 'success', text: __('assets.maintenance.done', ['asset' => $this->asset->code]));

        $this->reset('kind', 'performed_on', 'performed_by', 'description');
        unset($this->records);
    }
}; ?>

<flux:card class="flex flex-col gap-3" data-test="asset-maintenance">
    @php
        $canRegister = $this->canRegister();
        $selected = MaintenanceKind::tryFrom($kind);
    @endphp
    <div class="flex flex-wrap items-center gap-3">
        <flux:heading level="3">{{ __('assets.maintenance.title') }}</flux:heading>
        @if ($canRegister)
            <flux:button wire:click="open" icon="wrench-screwdriver" size="sm" class="ms-auto" data-test="register-maintenance-button">{{ __('assets.maintenance.register') }}</flux:button>
        @endif
    </div>

    @if ($this->records->isEmpty())
        <flux:text data-test="no-maintenance">{{ __('assets.maintenance.empty') }}</flux:text>
    @else
        <div class="relative overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('assets.maintenance.columns.performed_on') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.maintenance.columns.kind') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.maintenance.columns.description') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.maintenance.columns.performed_by') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.maintenance.columns.registered_by') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->records as $record)
                        <flux:table.row :key="$record->id" data-test="maintenance-row">
                            <flux:table.cell class="whitespace-nowrap">{{ $record->reference_date->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell variant="strong" class="whitespace-nowrap">
                                {{ $record->kind->label() }}
                                @if ($record->status !== MaintenanceStatus::Completed)
                                    <flux:badge size="sm" color="zinc" class="ms-1">{{ $record->status->label() }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="min-w-48"><span class="whitespace-pre-line">{{ $record->description }}</span></flux:table.cell>
                            <flux:table.cell class="min-w-32 whitespace-normal">{{ $record->performed_by ?: __('assets.show.empty') }}</flux:table.cell>
                            <flux:table.cell class="min-w-28 whitespace-normal">{{ $record->registered_by_name ?? __('assets.maintenance.system') }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    @if ($canRegister)
        <flux:modal name="asset-maintenance-form" class="w-full max-w-lg">
            <form wire:submit="save" class="flex flex-col gap-5">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('assets.maintenance.form_title', ['asset' => $asset->code]) }}</flux:heading>
                    <flux:text>{{ __('assets.maintenance.form_help') }}</flux:text>
                </div>

                <flux:select wire:model.live="kind" :label="Str::ucfirst(__('assets.maintenance.fields.kind'))" required data-test="maintenance-kind">
                    @foreach (MaintenanceKind::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($selected)
                    <flux:callout variant="secondary" icon="information-circle" data-test="maintenance-kind-help">
                        <flux:callout.text>{{ __('assets.maintenance.kind_help.'.$selected->value) }}</flux:callout.text>
                    </flux:callout>
                @endif

                <flux:input type="date" wire:model="performed_on" :label="Str::ucfirst(__('assets.maintenance.fields.performed_on'))" :max="today()->toDateString()" :min="$asset->acquired_on?->toDateString()" required data-test="maintenance-performed-on" />
                <flux:input wire:model="performed_by" :label="Str::ucfirst(__('assets.maintenance.fields.performed_by'))" :placeholder="__('assets.maintenance.performed_by_placeholder')" maxlength="150" required data-test="maintenance-performed-by" />
                <flux:textarea wire:model="description" :label="Str::ucfirst(__('assets.maintenance.fields.description'))" :placeholder="__('assets.maintenance.description_placeholder')" rows="3" maxlength="1000" required data-test="maintenance-description" />

                <flux:error name="form" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('assets.maintenance.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save" data-test="save-maintenance">{{ __('assets.maintenance.save') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</flux:card>
