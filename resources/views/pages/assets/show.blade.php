<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Detalle del activo. Owner: assets. The route already checked `view` (routes/modules/assets.php), and an asset that
 * does not exist (or was deleted, RF_02) answers with the 404 page. Every change made from here must call
 * $this->authorize() with AssetPolicy (update, move, registerEvent…).
 *
 * RF_01 · the asset's information, read fresh from the database on every visit, with the fields registered by RF_03
 * and its companions (RF_14–RF_16, RF_18, RF_19, RF_23, RF_24). RF_17 · the warranty and whether it is still valid.
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
}; ?>

<div class="flex flex-col gap-6">
    @php
        $empty = __('assets.show.empty');
        $date = fn ($value) => $value?->format('d/m/Y') ?? $empty;
        $warranty = $this->warrantyState();
    @endphp
    <div class="flex flex-wrap items-center gap-3">
        <flux:heading size="lg" level="2">{{ $asset->code }} · {{ $asset->displayName() }}</flux:heading>
        @if ($asset->status === AssetStatus::InService)
            <flux:badge :color="$asset->condition->color_group->badgeColor()" size="sm" data-test="asset-condition">{{ $asset->condition->name }}</flux:badge>
        @else
            <flux:badge color="zinc" size="sm">{{ $asset->status->label() }}</flux:badge>
        @endif
    </div>

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
                <dd><flux:badge :color="$asset->condition->color_group->badgeColor()" size="sm">{{ $asset->condition->name }}</flux:badge></dd>
                <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.condition_note')) }}</dt>
                <dd class="text-ink">{{ $asset->condition_note ?: $empty }}</dd>
                <dt class="text-ink-muted">{{ Str::ucfirst(__('assets.fields.status')) }}</dt>
                <dd class="text-ink">{{ $asset->status->label() }}</dd>
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

    <x-module-placeholder owner="David Zamora (@DavidZamoraB)" :requirements="[
        'RF_04' => 'Editar activo',
        'RF_05' => 'Asignar estado a activo',
    ]" />
</div>
