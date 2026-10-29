<?php

use App\Actions\Assets\CancelRepair;
use App\Actions\Assets\CompleteRepair;
use App\Actions\Assets\RegisterMaintenance;
use App\Actions\Assets\RegisterRepair;
use App\Enums\MaintenanceKind;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetEvent;
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
 *
 * RF_11 · on a damaged asset (red condition) they also register a repair (RegisterRepair), in progress or finished,
 * linked to one of its damages; a repair in progress is finished (CompleteRepair) or cancelled (CancelRepair) from its
 * row. A repair changes the asset's status or condition, so the page reloads afterwards, as with events.
 */
new class extends Component {
    #[Locked]
    public Asset $asset;

    public string $kind = '';

    public string $performed_on = '';

    public string $performed_by = '';

    public string $description = '';

    /** RF_11: the damage the repair addresses ('' = none). */
    public string $damage_id = '';

    /** RF_11: in_progress or completed. */
    public string $repair_status = '';

    /** RF_11: the green or amber condition a repaired asset takes. */
    public string $condition_id = '';

    /** RF_11: the repair in progress being finished or cancelled. */
    #[Locked]
    public ?int $repairId = null;

    public string $completed_on = '';

    public string $cancellation_reason = '';

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

    /** @return EloquentCollection<int, AssetEvent> */
    #[Computed]
    public function damages(): EloquentCollection
    {
        return RegisterRepair::damages($this->asset);
    }

    /** @return EloquentCollection<int, AssetCondition> */
    #[Computed]
    public function repairConditions(): EloquentCollection
    {
        return RegisterRepair::conditions();
    }

    /** The user may manage this asset's maintenance and the asset may still take it. */
    public function canManage(): bool
    {
        return RegisterMaintenance::isAllowed($this->asset)
            && (auth()->user()?->can('create', [MaintenanceRecord::class, $this->asset]) ?? false);
    }

    public function canRegisterRepair(): bool
    {
        return $this->canManage() && RegisterRepair::isAllowed($this->asset);
    }

    public function open(): void
    {
        $this->authorize('create', [MaintenanceRecord::class, $this->asset]);

        $this->resetForms();
        $this->kind = MaintenanceKind::Corrective->value;
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

        $this->resetForms();
        unset($this->records);
    }

    public function openRepair(): void
    {
        $this->authorize('create', [MaintenanceRecord::class, $this->asset]);

        $this->resetForms();
        $this->damage_id = (string) ($this->damages->first()?->id ?? '');
        $this->repair_status = MaintenanceStatus::InProgress->value;
        $this->condition_id = (string) $this->repairConditions->first()?->id;
        Flux::modal('asset-repair-form')->show();
    }

    public function saveRepair(RegisterRepair $registerRepair): void
    {
        $this->authorize('create', [MaintenanceRecord::class, $this->asset]);

        $repair = $registerRepair->handle(auth()->user(), $this->asset, [
            'asset_event_id' => $this->damage_id === '' ? null : $this->damage_id,
            'performed_on' => $this->performed_on,
            'performed_by' => trim($this->performed_by),
            'description' => trim($this->description),
            'status' => $this->repair_status,
            'condition_id' => $this->condition_id,
        ]);

        Flux::toast(variant: 'success', text: __('assets.maintenance.repair.done.'.$repair->status->value, ['asset' => $this->asset->code]));

        $this->redirectRoute('assets.show', $this->asset, navigate: true);
    }

    public function openComplete(int $repairId): void
    {
        $this->authorize('update', $this->repair($repairId));

        $this->resetForms();
        $this->repairId = $repairId;
        $this->completed_on = today()->toDateString();
        $this->condition_id = (string) $this->repairConditions->first()?->id;
        Flux::modal('asset-repair-complete')->show();
    }

    public function complete(CompleteRepair $completeRepair): void
    {
        $repair = $this->repair($this->repairId);
        $this->authorize('update', $repair);

        $completeRepair->handle(auth()->user(), $repair, [
            'completed_on' => $this->completed_on,
            'condition_id' => $this->condition_id,
        ]);

        Flux::toast(variant: 'success', text: __('assets.maintenance.repair.completed', ['asset' => $this->asset->code]));

        $this->redirectRoute('assets.show', $this->asset, navigate: true);
    }

    public function openCancel(int $repairId): void
    {
        $this->authorize('update', $this->repair($repairId));

        $this->resetForms();
        $this->repairId = $repairId;
        Flux::modal('asset-repair-cancel')->show();
    }

    public function cancelRepair(CancelRepair $cancelRepair): void
    {
        $repair = $this->repair($this->repairId);
        $this->authorize('update', $repair);

        $cancelRepair->handle(auth()->user(), $repair, ['cancellation_reason' => trim($this->cancellation_reason)]);

        Flux::toast(variant: 'success', text: __('assets.maintenance.repair.cancelled', ['asset' => $this->asset->code]));

        $this->redirectRoute('assets.show', $this->asset, navigate: true);
    }

    /** A maintenance record of this asset; another asset's record answers 404. */
    private function repair(?int $id): MaintenanceRecord
    {
        return $this->asset->maintenanceRecords()->whereKey($id)->firstOrFail();
    }

    private function resetForms(): void
    {
        $this->reset('kind', 'performed_by', 'description', 'damage_id', 'repair_status', 'condition_id', 'repairId', 'completed_on', 'cancellation_reason');
        $this->resetValidation();
        $this->performed_on = today()->toDateString();
    }
}; ?>

<flux:card class="flex flex-col gap-3" data-test="asset-maintenance">
    @php
        $canManage = $this->canManage();
        $canRegisterRepair = $canManage && $this->canRegisterRepair();
        $selected = MaintenanceKind::tryFrom($kind);
        $damageDates = $this->damages->mapWithKeys(fn (AssetEvent $damage) => [$damage->id => $damage->occurred_on->format('d/m/Y')]);
        $hasActions = $canManage && $this->records->contains(fn (MaintenanceRow $record) => $record->kind === MaintenanceKind::Corrective && $record->status === MaintenanceStatus::InProgress);
    @endphp
    <div class="flex flex-wrap items-center gap-3">
        <flux:heading level="3">{{ __('assets.maintenance.title') }}</flux:heading>
        @if ($canManage)
            <div class="flex flex-wrap gap-2 sm:ms-auto">
                @if ($canRegisterRepair)
                    <flux:button wire:click="openRepair" icon="wrench" size="sm" data-test="register-repair-button">{{ __('assets.maintenance.repair.register') }}</flux:button>
                @endif
                <flux:button wire:click="open" icon="wrench-screwdriver" size="sm" data-test="register-maintenance-button">{{ __('assets.maintenance.register') }}</flux:button>
            </div>
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
                    @if ($hasActions)
                        <flux:table.column>{{ __('assets.maintenance.repair.actions') }}</flux:table.column>
                    @endif
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->records as $record)
                        @php
                            $inProgress = $record->kind === MaintenanceKind::Corrective && $record->status === MaintenanceStatus::InProgress;
                        @endphp
                        <flux:table.row :key="$record->id" data-test="maintenance-row">
                            <flux:table.cell class="whitespace-nowrap">{{ $record->reference_date->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell variant="strong" class="whitespace-nowrap">
                                {{ $record->kind->label() }}
                                @if ($record->status !== MaintenanceStatus::Completed)
                                    <flux:badge size="sm" :color="$inProgress ? 'amber' : 'zinc'" class="ms-1" data-test="maintenance-status">{{ $record->status->label() }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="min-w-48">
                                <span class="whitespace-pre-line">{{ $record->description }}</span>
                                @if ($record->asset_event_id !== null && $damageDates->has($record->asset_event_id))
                                    <span class="mt-1 block text-ink-muted" data-test="repair-damage">{{ __('assets.maintenance.repair.repairs_damage', ['date' => $damageDates[$record->asset_event_id]]) }}</span>
                                @endif
                                @if ($record->cancellation_reason)
                                    <span class="mt-1 block whitespace-pre-line text-ink-muted">{{ __('assets.maintenance.repair.cancellation_reason', ['reason' => $record->cancellation_reason]) }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="min-w-32 whitespace-normal">{{ $record->performed_by ?: __('assets.show.empty') }}</flux:table.cell>
                            <flux:table.cell class="min-w-28 whitespace-normal">{{ $record->registered_by_name ?? __('assets.maintenance.system') }}</flux:table.cell>
                            @if ($hasActions)
                                <flux:table.cell class="whitespace-nowrap">
                                    @if ($inProgress)
                                        <div class="flex gap-2">
                                            <flux:button wire:click="openComplete({{ $record->id }})" size="xs" variant="primary" icon="check" data-test="complete-repair-button">{{ __('assets.maintenance.repair.complete') }}</flux:button>
                                            <flux:button wire:click="openCancel({{ $record->id }})" size="xs" variant="ghost" icon="x-mark" data-test="cancel-repair-button">{{ __('assets.maintenance.repair.cancel') }}</flux:button>
                                        </div>
                                    @endif
                                </flux:table.cell>
                            @endif
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    @if ($canManage)
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

        <flux:modal name="asset-repair-form" class="w-full max-w-lg">
            <form wire:submit="saveRepair" class="flex flex-col gap-5">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('assets.maintenance.repair.form_title', ['asset' => $asset->code]) }}</flux:heading>
                    <flux:text>{{ __('assets.maintenance.repair.form_help') }}</flux:text>
                </div>

                <flux:select wire:model="damage_id" :label="Str::ucfirst(__('assets.maintenance.fields.asset_event_id'))" data-test="repair-damage-select">
                    @foreach ($this->damages as $damage)
                        <flux:select.option :value="$damage->id">{{ __('assets.maintenance.repair.damage_option', ['date' => $damage->occurred_on->format('d/m/Y'), 'description' => Str::limit($damage->description, 60)]) }}</flux:select.option>
                    @endforeach
                    <flux:select.option value="">{{ __('assets.maintenance.repair.no_damage') }}</flux:select.option>
                </flux:select>

                <flux:input type="date" wire:model="performed_on" :label="Str::ucfirst(__('assets.maintenance.fields.performed_on'))" :max="today()->toDateString()" :min="$asset->acquired_on?->toDateString()" required data-test="repair-performed-on" />
                <flux:input wire:model="performed_by" :label="Str::ucfirst(__('assets.maintenance.fields.performed_by'))" :placeholder="__('assets.maintenance.performed_by_placeholder')" maxlength="150" required data-test="repair-performed-by" />
                <flux:textarea wire:model="description" :label="Str::ucfirst(__('assets.maintenance.fields.description'))" :placeholder="__('assets.maintenance.repair.description_placeholder')" rows="3" maxlength="1000" required data-test="repair-description" />

                <flux:radio.group wire:model.live="repair_status" :label="Str::ucfirst(__('assets.maintenance.fields.status'))" data-test="repair-status">
                    @foreach (['in_progress', 'completed'] as $status)
                        <flux:radio :value="$status" :label="__('assets.maintenance.repair.statuses.'.$status)" />
                    @endforeach
                </flux:radio.group>

                @if ($repair_status === MaintenanceStatus::Completed->value)
                    <flux:select wire:model="condition_id" :label="Str::ucfirst(__('assets.maintenance.fields.condition_id'))" required data-test="repair-condition">
                        @foreach ($this->repairConditions as $condition)
                            <flux:select.option :value="$condition->id">{{ $condition->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:error name="form" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('assets.maintenance.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="saveRepair" data-test="save-repair">{{ __('assets.maintenance.save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="asset-repair-complete" class="w-full max-w-lg">
            <form wire:submit="complete" class="flex flex-col gap-5">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('assets.maintenance.repair.complete_title', ['asset' => $asset->code]) }}</flux:heading>
                    <flux:text>{{ __('assets.maintenance.repair.complete_help') }}</flux:text>
                </div>

                <flux:input type="date" wire:model="completed_on" :label="Str::ucfirst(__('assets.maintenance.fields.completed_on'))" :max="today()->toDateString()" required data-test="repair-completed-on" />
                <flux:select wire:model="condition_id" :label="Str::ucfirst(__('assets.maintenance.fields.condition_id'))" required data-test="complete-condition">
                    @foreach ($this->repairConditions as $condition)
                        <flux:select.option :value="$condition->id">{{ $condition->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:error name="form" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('assets.maintenance.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="complete" data-test="save-complete">{{ __('assets.maintenance.repair.complete') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="asset-repair-cancel" class="w-full max-w-lg">
            <form wire:submit="cancelRepair" class="flex flex-col gap-5">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('assets.maintenance.repair.cancel_title', ['asset' => $asset->code]) }}</flux:heading>
                    <flux:text>{{ __('assets.maintenance.repair.cancel_help') }}</flux:text>
                </div>

                <flux:textarea wire:model="cancellation_reason" :label="Str::ucfirst(__('assets.maintenance.fields.cancellation_reason'))" rows="2" maxlength="1000" data-test="repair-cancellation-reason" />

                <flux:error name="form" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('assets.maintenance.repair.keep') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="cancelRepair" data-test="confirm-cancel-repair">{{ __('assets.maintenance.repair.confirm_cancel') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</flux:card>
