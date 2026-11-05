<?php

use App\Actions\Assets\CancelRepair;
use App\Actions\Assets\CancelScheduledMaintenance;
use App\Actions\Assets\CompleteRepair;
use App\Actions\Assets\CompleteScheduledMaintenance;
use App\Actions\Assets\NotifyDueMaintenance;
use App\Actions\Assets\RegisterMaintenance;
use App\Actions\Assets\RegisterRepair;
use App\Actions\Assets\ScheduleMaintenance;
use App\Actions\Assets\UpdateScheduledMaintenance;
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
 *
 * RF_27 · they schedule maintenance for a later day (ScheduleMaintenance); a scheduled one is changed
 * (UpdateScheduledMaintenance), marked as done (CompleteScheduledMaintenance) or cancelled (CancelScheduledMaintenance)
 * from its row. Cancelling is offered even when the asset was lost or decommissioned meanwhile, to clean it up.
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

    /** RF_11, RF_27: the repair in progress or scheduled maintenance being changed, finished or cancelled. */
    #[Locked]
    public ?int $recordId = null;

    /** RF_27: the planned day. */
    public string $scheduled_for = '';

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

    /** The user may manage this asset's maintenance, whatever its status (to cancel what was scheduled). */
    public function canEdit(): bool
    {
        return auth()->user()?->can('create', [MaintenanceRecord::class, $this->asset]) ?? false;
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
        $this->authorize('update', $this->record($repairId));

        $this->resetForms();
        $this->recordId = $repairId;
        $this->completed_on = today()->toDateString();
        $this->condition_id = (string) $this->repairConditions->first()?->id;
        Flux::modal('asset-repair-complete')->show();
    }

    public function complete(CompleteRepair $completeRepair): void
    {
        $repair = $this->record($this->recordId);
        $this->authorize('update', $repair);

        $completeRepair->handle(auth()->user(), $repair, [
            'completed_on' => $this->completed_on,
            'condition_id' => $this->condition_id,
        ]);

        Flux::toast(variant: 'success', text: __('assets.maintenance.repair.completed', ['asset' => $this->asset->code]));

        $this->redirectRoute('assets.show', $this->asset, navigate: true);
    }

    /** RF_11, RF_27: cancel a repair in progress or a scheduled maintenance (the modal adapts to which). */
    public function openCancel(int $recordId): void
    {
        $this->authorize('update', $this->record($recordId));

        $this->resetForms();
        $this->recordId = $recordId;
        Flux::modal('asset-maintenance-cancel')->show();
    }

    public function cancelRecord(CancelRepair $cancelRepair, CancelScheduledMaintenance $cancelScheduled): void
    {
        $record = $this->record($this->recordId);
        $this->authorize('update', $record);
        $input = ['cancellation_reason' => trim($this->cancellation_reason)];

        if ($record->status === MaintenanceStatus::Scheduled) {
            $cancelScheduled->handle(auth()->user(), $record, $input);
            Flux::modal('asset-maintenance-cancel')->close();
            Flux::toast(variant: 'success', text: __('assets.maintenance.scheduled.done.cancelled', ['asset' => $this->asset->code]));
            $this->resetForms();
            unset($this->records);

            return;
        }

        $cancelRepair->handle(auth()->user(), $record, $input);

        Flux::toast(variant: 'success', text: __('assets.maintenance.repair.cancelled', ['asset' => $this->asset->code]));

        $this->redirectRoute('assets.show', $this->asset, navigate: true);
    }

    /** RF_27: schedule (no record) or change (record) a scheduled maintenance; preventive by default. */
    public function openSchedule(?int $recordId = null): void
    {
        $record = $recordId === null ? null : $this->record($recordId);
        if ($record === null) {
            $this->authorize('create', [MaintenanceRecord::class, $this->asset]);
        } else {
            $this->authorize('update', $record);
        }

        $this->resetForms();
        $this->recordId = $record?->id;
        $this->kind = $record?->kind->value ?? MaintenanceKind::Preventive->value;
        $this->scheduled_for = $record?->scheduled_for?->toDateString() ?? today()->addDay()->toDateString();
        $this->description = $record->description ?? '';
        Flux::modal('asset-scheduled-form')->show();
    }

    public function saveSchedule(ScheduleMaintenance $scheduleMaintenance, UpdateScheduledMaintenance $updateScheduled): void
    {
        $input = ['kind' => $this->kind, 'scheduled_for' => $this->scheduled_for, 'description' => trim($this->description)];

        if ($this->recordId === null) {
            $this->authorize('create', [MaintenanceRecord::class, $this->asset]);
            $record = $scheduleMaintenance->handle(auth()->user(), $this->asset, $input);
            $message = __('assets.maintenance.scheduled.done.created', ['asset' => $this->asset->code, 'date' => $record->scheduled_for?->format('d/m/Y')]);
        } else {
            $record = $this->record($this->recordId);
            $this->authorize('update', $record);
            $updateScheduled->handle(auth()->user(), $record, $input);
            $message = __('assets.maintenance.scheduled.done.updated', ['asset' => $this->asset->code]);
        }

        Flux::modal('asset-scheduled-form')->close();
        Flux::toast(variant: 'success', text: $message);
        $this->resetForms();
        unset($this->records);
    }

    /** RF_27: mark a scheduled maintenance as done, with its description to adjust. */
    public function openCompleteScheduled(int $recordId): void
    {
        $record = $this->record($recordId);
        $this->authorize('update', $record);

        $this->resetForms();
        $this->recordId = $record->id;
        $this->description = $record->description;
        Flux::modal('asset-scheduled-complete')->show();
    }

    public function completeScheduled(CompleteScheduledMaintenance $completeScheduled): void
    {
        $record = $this->record($this->recordId);
        $this->authorize('update', $record);

        $completeScheduled->handle(auth()->user(), $record, [
            'performed_on' => $this->performed_on,
            'performed_by' => trim($this->performed_by),
            'description' => trim($this->description),
        ]);

        Flux::modal('asset-scheduled-complete')->close();
        Flux::toast(variant: 'success', text: __('assets.maintenance.scheduled.done.completed', ['asset' => $this->asset->code]));
        $this->resetForms();
        unset($this->records);
    }

    /** A maintenance record of this asset; another asset's record answers 404. */
    private function record(?int $id): MaintenanceRecord
    {
        return $this->asset->maintenanceRecords()->whereKey($id)->firstOrFail();
    }

    private function resetForms(): void
    {
        $this->reset('kind', 'performed_by', 'description', 'damage_id', 'repair_status', 'condition_id', 'recordId', 'completed_on', 'cancellation_reason', 'scheduled_for');
        $this->resetValidation();
        $this->performed_on = today()->toDateString();
    }
}; ?>

<flux:card class="flex flex-col gap-3" data-test="asset-maintenance">
    @php
        $canEdit = $this->canEdit();
        $canManage = $canEdit && $this->canManage();
        $canRegisterRepair = $canManage && $this->canRegisterRepair();
        $selected = MaintenanceKind::tryFrom($kind);
        $damageDates = $this->damages->mapWithKeys(fn (AssetEvent $damage) => [$damage->id => $damage->occurred_on->format('d/m/Y')]);
        $isRepairInProgress = fn (MaintenanceRow $record): bool => $record->kind === MaintenanceKind::Corrective && $record->status === MaintenanceStatus::InProgress;
        $hasActions = $canEdit && $this->records->contains(fn (MaintenanceRow $record) => ($canManage && $isRepairInProgress($record)) || $record->status === MaintenanceStatus::Scheduled);
        $cancellingScheduled = $recordId !== null && $this->records->firstWhere('id', $recordId)?->status === MaintenanceStatus::Scheduled;
    @endphp
    <div class="flex flex-wrap items-center gap-3">
        <flux:heading level="3">{{ __('assets.maintenance.title') }}</flux:heading>
        @if ($canManage)
            <div class="flex flex-wrap gap-2 sm:ms-auto">
                @if ($canRegisterRepair)
                    <flux:button wire:click="openRepair" icon="wrench" size="sm" data-test="register-repair-button">{{ __('assets.maintenance.repair.register') }}</flux:button>
                @endif
                <flux:button wire:click="open" icon="wrench-screwdriver" size="sm" data-test="register-maintenance-button">{{ __('assets.maintenance.register') }}</flux:button>
                <flux:button wire:click="openSchedule" icon="calendar" size="sm" data-test="schedule-maintenance-button">{{ __('assets.maintenance.scheduled.register') }}</flux:button>
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
                            $inProgress = $isRepairInProgress($record);
                            $scheduled = $record->status === MaintenanceStatus::Scheduled;
                        @endphp
                        <flux:table.row :key="$record->id" data-test="maintenance-row">
                            <flux:table.cell class="whitespace-nowrap">{{ $record->reference_date->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell variant="strong" class="whitespace-nowrap">
                                {{ $record->kind->label() }}
                                @if ($scheduled && $record->reference_date->isBefore(today()))
                                    <flux:badge size="sm" color="red" class="ms-1" data-test="maintenance-status">{{ __('assets.maintenance.scheduled.overdue') }}</flux:badge>
                                @elseif ($record->status !== MaintenanceStatus::Completed)
                                    <flux:badge size="sm" :color="$inProgress ? 'amber' : ($scheduled ? 'blue' : 'zinc')" class="ms-1" data-test="maintenance-status">{{ $record->status->label() }}</flux:badge>
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
                                <flux:table.cell>
                                    @if ($inProgress && $canManage)
                                        <div class="flex flex-col items-start gap-2">
                                            <flux:button wire:click="openComplete({{ $record->id }})" size="xs" variant="primary" icon="check" data-test="complete-repair-button">{{ __('assets.maintenance.repair.complete') }}</flux:button>
                                            <flux:button wire:click="openCancel({{ $record->id }})" size="xs" variant="ghost" icon="x-mark" data-test="cancel-repair-button">{{ __('assets.maintenance.repair.cancel') }}</flux:button>
                                        </div>
                                    @elseif ($scheduled)
                                        <div class="flex flex-col items-start gap-2">
                                            @if ($canManage)
                                                <flux:button wire:click="openCompleteScheduled({{ $record->id }})" size="xs" variant="primary" icon="check" data-test="complete-scheduled-button">{{ __('assets.maintenance.scheduled.complete') }}</flux:button>
                                                <flux:button wire:click="openSchedule({{ $record->id }})" size="xs" icon="pencil-square" data-test="edit-scheduled-button">{{ __('assets.maintenance.scheduled.edit') }}</flux:button>
                                            @endif
                                            <flux:button wire:click="openCancel({{ $record->id }})" size="xs" variant="ghost" icon="x-mark" data-test="cancel-scheduled-button">{{ __('assets.maintenance.repair.cancel') }}</flux:button>
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

    @if ($canEdit)
        {{-- One flex item for every modal, so the hidden modals add no gaps under the table. --}}
        <div>
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

        <flux:modal name="asset-scheduled-form" class="w-full max-w-lg">
            <form wire:submit="saveSchedule" class="flex flex-col gap-5">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __($recordId === null ? 'assets.maintenance.scheduled.form_title' : 'assets.maintenance.scheduled.edit_title', ['asset' => $asset->code]) }}</flux:heading>
                    <flux:text>{{ __('assets.maintenance.scheduled.form_help', ['days' => NotifyDueMaintenance::DAYS_AHEAD]) }}</flux:text>
                </div>

                <flux:select wire:model="kind" :label="Str::ucfirst(__('assets.maintenance.fields.kind'))" required data-test="scheduled-kind">
                    @foreach (MaintenanceKind::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="date" wire:model="scheduled_for" :label="Str::ucfirst(__('assets.maintenance.fields.scheduled_for'))" :min="today()->addDay()->toDateString()" required data-test="scheduled-for" />
                <flux:textarea wire:model="description" :label="Str::ucfirst(__('assets.maintenance.fields.description'))" :placeholder="__('assets.maintenance.scheduled.description_placeholder')" rows="3" maxlength="1000" required data-test="scheduled-description" />

                <flux:error name="form" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('assets.maintenance.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="saveSchedule" data-test="save-scheduled">{{ __('assets.maintenance.save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="asset-scheduled-complete" class="w-full max-w-lg">
            <form wire:submit="completeScheduled" class="flex flex-col gap-5">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('assets.maintenance.scheduled.complete_title', ['asset' => $asset->code]) }}</flux:heading>
                    <flux:text>{{ __('assets.maintenance.scheduled.complete_help') }}</flux:text>
                </div>

                <flux:input type="date" wire:model="performed_on" :label="Str::ucfirst(__('assets.maintenance.fields.performed_on'))" :max="today()->toDateString()" :min="$asset->acquired_on?->toDateString()" required data-test="scheduled-performed-on" />
                <flux:input wire:model="performed_by" :label="Str::ucfirst(__('assets.maintenance.fields.performed_by'))" :placeholder="__('assets.maintenance.performed_by_placeholder')" maxlength="150" required data-test="scheduled-performed-by" />
                <flux:textarea wire:model="description" :label="Str::ucfirst(__('assets.maintenance.fields.description'))" rows="3" maxlength="1000" required data-test="scheduled-complete-description" />

                <flux:error name="form" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('assets.maintenance.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="completeScheduled" data-test="save-complete-scheduled">{{ __('assets.maintenance.save') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="asset-maintenance-cancel" class="w-full max-w-lg">
            <form wire:submit="cancelRecord" class="flex flex-col gap-5">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __($cancellingScheduled ? 'assets.maintenance.scheduled.cancel_title' : 'assets.maintenance.repair.cancel_title', ['asset' => $asset->code]) }}</flux:heading>
                    <flux:text>{{ __($cancellingScheduled ? 'assets.maintenance.scheduled.cancel_help' : 'assets.maintenance.repair.cancel_help') }}</flux:text>
                </div>

                <flux:textarea wire:model="cancellation_reason" :label="Str::ucfirst(__('assets.maintenance.fields.cancellation_reason'))" rows="2" maxlength="1000" data-test="repair-cancellation-reason" />

                <flux:error name="form" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('assets.maintenance.repair.keep') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="cancelRecord" data-test="confirm-cancel">{{ __($cancellingScheduled ? 'assets.maintenance.scheduled.confirm_cancel' : 'assets.maintenance.repair.confirm_cancel') }}</flux:button>
                </div>
            </form>
        </flux:modal>
        </div>
    @endif
</flux:card>
