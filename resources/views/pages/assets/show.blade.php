<?php

use App\Enums\AssetEventKind;
use App\Enums\AssetStatus;
use App\Enums\PermissionName;
use App\Models\Asset;
use App\Models\Reporting\AssetMovementRow;
use App\Queries\Assets\AssetMovements;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Carbon\CarbonInterface;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Detalle del activo. Owner: assets. The route already checked `view` (routes/modules/assets.php), and an asset that
 * does not exist (or was deleted, RF_02) answers with the 404 page. Every change made from here must call
 * $this->authorize() with AssetPolicy (update, move, registerEvent…).
 *
 * RF_12, RF_31 · the asset's components and their management are the assets.asset-components component.
 * RF_01 · the asset's information, read fresh from the database on every visit, with the fields registered by RF_03
 * and its companions (RF_14–RF_16, RF_18, RF_19, RF_23, RF_24). RF_17 · the warranty and whether it is still valid.
 * RF_05 · the condition's color. Editing (RF_04, RF_05) happens on pages::assets.edit.
 * RF_25 · the asset's movements; registering one (RF_06, RF_25) happens on pages::assets.move.
 * RF_02 · "Eliminar" is the assets.delete-asset component (resources/views/components/assets).
 * RF_20, RF_21 · the asset's documents and photos are the assets.asset-documents and assets.asset-photos components
 * (by @Dizabu, on RF_41's upload mechanism).
 * RF_09 · "Historial" opens pages::assets.history (movements, events and audited changes, with who made them).
 * RF_13, RF_22, RF_28, RF_29 · damage, losses, recoveries and the decommission are the assets.asset-events component;
 * the date of the event behind the current status shows in "Estado". A decommissioned asset is read-only
 * (Asset::isReadOnly).
 * RF_10, RF_26 · the asset's maintenance, and registering corrective or preventive maintenance already done, are the
 * assets.asset-maintenance component (by @JoseHerrera21), shown to users who may consult maintenance.
 */
new #[Title('assets.show.title')] class extends Component {
    public Asset $asset;

    public function mount(): void
    {
        $this->asset->load(['assetType.category', 'brand', 'condition', 'currentSpace', 'responsibleUser', 'supplier']);
    }

    /** RF_17: none (no dates), valid (ends today or later) or expired; the same rule as reporting.assets.warranty_state. */
    public function warrantyState(): string
    {
        if ($this->asset->warranty_starts_on === null || $this->asset->warranty_ends_on === null) {
            return 'none';
        }

        return $this->asset->warranty_ends_on->isBefore(today()) ? 'expired' : 'valid';
    }

    /**
     * RF_25: the asset's entries, transfers and exits, newest first.
     *
     * @return EloquentCollection<int, AssetMovementRow>
     */
    #[Computed]
    public function movements(): EloquentCollection
    {
        return AssetMovements::forAsset($this->asset)->get();
    }

    /** RF_22, RF_28: when the asset was lost or decommissioned, from the latest event of that kind. */
    public function statusSince(): ?CarbonInterface
    {
        $kind = match ($this->asset->status) {
            AssetStatus::Lost => AssetEventKind::Loss,
            AssetStatus::Decommissioned => AssetEventKind::Decommission,
            default => null,
        };

        return $kind === null ? null : $this->asset->events()->where('kind', $kind)->reorder()->latest('occurred_on')->latest('id')->first()?->occurred_on;
    }

    /** RF_06: the user may move assets, but not this one, because it is outside the spaces they manage (★). */
    public function moveDeniedOutsideScope(): bool
    {
        $user = auth()->user();

        return $user->can(PermissionName::AssetsMove) && $user->cannot('move', $this->asset);
    }
}; ?>

<div class="flex flex-col gap-6">
    @php
        $empty = __('assets.show.empty');
        $date = fn ($value) => $value?->format('d/m/Y') ?? $empty;
        $warranty = $this->warrantyState();
        $statusSince = $this->statusSince();
    @endphp
    <div class="flex flex-wrap items-center gap-3">
        <flux:heading size="lg" level="2">{{ $asset->code }} · {{ $asset->displayName() }}</flux:heading>
        @if ($asset->status === AssetStatus::InService)
            <flux:badge :color="$asset->condition->color_group->badgeColor()" size="sm" data-test="asset-condition">{{ $asset->condition->name }}</flux:badge>
        @else
            <flux:badge color="zinc" size="sm">{{ $asset->status->label() }}</flux:badge>
        @endif

        <div class="ms-auto flex flex-wrap gap-2">
            <flux:button :href="route('assets.history', $asset)" wire:navigate icon="clock" size="sm" data-test="asset-history-button">{{ __('assets.history.link') }}</flux:button>
            @can('move', $asset)
                <flux:button :href="route('assets.move', $asset)" wire:navigate icon="arrows-right-left" size="sm" data-test="move-asset-button">{{ __('assets.show.move') }}</flux:button>
            @endcan
            @if (! $asset->isReadOnly())
                @can('update', $asset)
                    <flux:button :href="route('assets.edit', $asset)" wire:navigate icon="pencil-square" size="sm" data-test="edit-asset-button">{{ __('assets.show.edit') }}</flux:button>
                @endcan
            @endif
            @can('delete', $asset)
                <livewire:assets.delete-asset :asset="$asset" />
            @endcan
        </div>
    </div>

    @if ($asset->isReadOnly())
        <flux:callout variant="warning" icon="archive-box-x-mark" :heading="__('assets.show.decommissioned_notice', ['date' => $date($statusSince)])" data-test="decommissioned-notice" />
    @endif

    @if ($this->moveDeniedOutsideScope())
        <flux:callout variant="warning" icon="lock-closed" :heading="__('assets.move.not_manager')" data-test="move-denied" />
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card class="flex flex-col gap-3">
            <flux:heading level="3">{{ __('assets.show.sections.identification') }}</flux:heading>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                @foreach ([
                    'code' => $asset->code,
                    'name' => $asset->name ?: $empty,
                    'asset_type_id' => $asset->assetType->name,
                    'category' => $asset->assetType->category->name ?? $empty,
                    'brand_id' => $asset->brand->name ?? $empty,
                    'model' => $asset->model ?: $empty,
                    'asset_tag' => $asset->asset_tag ?: $empty,
                    'serial_number' => $asset->serial_number ?: $empty,
                ] as $field => $value)
                    <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.'.$field)) }}</dt>
                    <dd class="text-ink">{{ $value }}</dd>
                @endforeach
            </dl>
        </flux:card>

        <flux:card class="flex flex-col gap-3">
            <flux:heading level="3">{{ __('assets.show.sections.state') }}</flux:heading>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.condition_id')) }}</dt>
                <dd><flux:badge :color="$asset->condition->color_group->badgeColor()" size="sm" data-test="condition-badge" :data-color="$asset->condition->color_group->value">{{ $asset->condition->name }}</flux:badge></dd>
                <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.condition_note')) }}</dt>
                <dd class="text-ink">{{ $asset->condition_note ?: $empty }}</dd>
                <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.status')) }}</dt>
                <dd class="text-ink" data-test="asset-status">{{ $asset->status->label() }}</dd>
                @if ($statusSince)
                    <dt class="text-ink-muted">{{ __('assets.show.status_since.'.$asset->status->value) }}</dt>
                    <dd class="text-ink" data-test="asset-status-since">{{ $date($statusSince) }}</dd>
                @endif
            </dl>
        </flux:card>

        <flux:card class="flex flex-col gap-3">
            <flux:heading level="3">{{ __('assets.show.sections.location') }}</flux:heading>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.space_id')) }}</dt>
                <dd class="text-ink">
                    @if ($asset->currentSpace)
                        @can('view', $asset->currentSpace)
                            <flux:link :href="route('spaces.show', $asset->currentSpace)" wire:navigate>{{ $asset->currentSpace->code }} · {{ $asset->currentSpace->name }}</flux:link>
                        @else
                            {{ $asset->currentSpace->code }} · {{ $asset->currentSpace->name }}
                        @endcan
                    @else
                        {{ $empty }}
                    @endif
                </dd>
                <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.location_detail')) }}</dt>
                <dd class="text-ink">{{ $asset->location_detail ?: $empty }}</dd>
                <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.responsible_user_id')) }}</dt>
                <dd class="text-ink">{{ $asset->responsibleUser->name ?? $empty }}</dd>
            </dl>
        </flux:card>

        <flux:card class="flex flex-col gap-3">
            <flux:heading level="3">{{ __('assets.show.sections.acquisition') }}</flux:heading>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.acquired_on')) }}</dt>
                <dd class="text-ink">{{ $date($asset->acquired_on) }}</dd>
                <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.supplier_id')) }}</dt>
                <dd class="text-ink">{{ $asset->supplier->name ?? $empty }}</dd>
            </dl>

            <flux:heading level="3" class="mt-2">{{ __('assets.show.sections.warranty') }}</flux:heading>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm" data-test="asset-warranty">
                <dt class="text-ink-muted">{{ __('assets.show.has_warranty') }}</dt>
                <dd class="text-ink">{{ $warranty === 'none' ? __('assets.show.no') : __('assets.show.yes') }}</dd>
                @if ($warranty !== 'none')
                    <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.warranty_starts_on')) }}</dt>
                    <dd class="text-ink">{{ $date($asset->warranty_starts_on) }}</dd>
                    <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.warranty_ends_on')) }}</dt>
                    <dd class="text-ink">{{ $date($asset->warranty_ends_on) }}</dd>
                @endif
                <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.status')) }}</dt>
                <dd>
                    <flux:badge size="sm" :color="match ($warranty) { 'valid' => 'green', 'expired' => 'red', default => 'zinc' }">
                        {{ __('assets.show.warranty_states.'.$warranty) }}
                    </flux:badge>
                </dd>
            </dl>
        </flux:card>
    </div>

    <flux:card class="flex flex-col gap-3">
        <flux:heading level="3">{{ __('assets.show.sections.observations') }}</flux:heading>
        <flux:text class="whitespace-pre-line">{{ $asset->observations ?: __('assets.show.no_observations') }}</flux:text>
    </flux:card>

    <livewire:assets.asset-components :asset="$asset" />

    <livewire:assets.asset-photos :asset="$asset" />

    <livewire:assets.asset-documents :asset="$asset" />

    <livewire:assets.asset-events :asset="$asset" />

    @can('viewAny', App\Models\MaintenanceRecord::class)
        <livewire:assets.asset-maintenance :asset="$asset" />
    @endcan

    <flux:card class="flex flex-col gap-3" data-test="asset-movements">
        <flux:heading level="3">{{ __('assets.show.sections.movements') }}</flux:heading>
        @if ($this->movements->isEmpty())
            <flux:text>{{ __('assets.show.movements.empty') }}</flux:text>
        @else
            @php
                $space = fn (?string $code, ?string $name) => $code !== null ? "{$code} · {$name}" : __('assets.show.movements.outside');
            @endphp
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('assets.show.movements.moved_at') }}</flux:table.column>
                        <flux:table.column>{{ __('assets.show.movements.type') }}</flux:table.column>
                        <flux:table.column>{{ __('assets.show.movements.from') }}</flux:table.column>
                        <flux:table.column>{{ __('assets.show.movements.to') }}</flux:table.column>
                        <flux:table.column>{{ __('assets.show.movements.moved_by') }}</flux:table.column>
                        <flux:table.column>{{ __('assets.show.movements.reason') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->movements as $movement)
                            <flux:table.row :key="$movement->id">
                                <flux:table.cell class="whitespace-nowrap">{{ $movement->moved_at->format('d/m/Y H:i') }}</flux:table.cell>
                                <flux:table.cell variant="strong">{{ $movement->type->label() }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">{{ $space($movement->from_space_code, $movement->from_space_name) }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">{{ $space($movement->to_space_code, $movement->to_space_name) }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">{{ $movement->moved_by_name ?? __('assets.show.movements.system') }}</flux:table.cell>
                                <flux:table.cell class="min-w-48">{{ $movement->reason ?: $empty }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </flux:card>
</div>
