<?php

use App\Models\Asset;
use App\Models\Reporting\AssetRow;
use App\Models\Reporting\SpaceRow;
use App\Models\Space;
use App\Queries\Assets\AssetInventory;
use App\Queries\Spaces\SpaceDirectory;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Detalle del espacio (RF_35). Owner: spaces (@Dizabu). It embeds two components owned by reservations (@JoseHerrera21):
 * the space's reservations (RF_57) and its availability (RF_55). Changes made from here call $this->authorize()
 * with SpacePolicy (update, delete, managePhotos…). A missing or deleted space never reaches this page: the route's
 * model binding answers 404.
 */
new #[Title('spaces.show.title')] class extends Component {
    public Space $space;

    /** The space as lists and reports see it: type, status, building, managers and live occupancy. */
    #[Computed]
    public function details(): SpaceRow
    {
        return SpaceDirectory::query()->whereKey($this->space->id)->firstOrFail();
    }

    /** @return Collection<int, AssetRow> */
    #[Computed]
    public function assets(): Collection
    {
        return AssetInventory::query(['space' => ['values' => [$this->space->id]]])->get();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center gap-2">
        <flux:heading size="lg" level="2">{{ $this->details->code }} · {{ $this->details->name }}</flux:heading>
        <flux:badge size="sm" :color="$this->details->status_color->badgeColor()" data-test="space-status">{{ $this->details->status }}</flux:badge>
        @if ($this->details->is_occupied)
            <flux:badge size="sm" color="amber">{{ __('spaces.show.occupied') }}</flux:badge>
        @endif
        <div class="ms-auto flex gap-2">
            <livewire:spaces.change-status :space="$space" />
            <livewire:spaces.edit-space :space="$space" />
        </div>
    </div>

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="md" level="3">{{ __('spaces.show.details') }}</flux:heading>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-ink-muted">{{ __('spaces.show.type') }}</dt>
                <dd class="text-ink">{{ $this->details->space_type }}</dd>
            </div>
            <div>
                <dt class="text-sm text-ink-muted">{{ __('spaces.show.capacity') }}</dt>
                <dd class="text-ink" data-test="space-capacity">{{ trans_choice('spaces.show.people', $this->details->capacity, ['count' => $this->details->capacity]) }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-sm text-ink-muted">{{ __('spaces.show.location') }}</dt>
                <dd class="text-ink">{{ $this->details->location() }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-sm text-ink-muted">{{ __('spaces.show.managers') }}</dt>
                <dd class="text-ink">{{ $this->details->managers ?? __('spaces.show.no_managers') }}</dd>
            </div>
            @if (filled($space->notes))
                <div class="sm:col-span-2">
                    <dt class="text-sm text-ink-muted">{{ __('spaces.show.notes') }}</dt>
                    <dd class="whitespace-pre-line text-ink">{{ $space->notes }}</dd>
                </div>
            @endif
        </dl>
    </flux:card>

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="md" level="3">{{ __('spaces.show.assets', ['count' => $this->assets->count()]) }}</flux:heading>

        @if ($this->assets->isEmpty())
            <flux:text data-test="space-no-assets">{{ __('spaces.show.no_assets') }}</flux:text>
        @else
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('spaces.show.asset_code') }}</flux:table.column>
                        <flux:table.column>{{ __('spaces.show.asset_name') }}</flux:table.column>
                        <flux:table.column>{{ __('spaces.show.asset_type') }}</flux:table.column>
                        <flux:table.column>{{ __('spaces.show.asset_condition') }}</flux:table.column>
                        <flux:table.column>{{ __('spaces.show.asset_status') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->assets as $asset)
                            <flux:table.row :key="$asset->id">
                                <flux:table.cell class="whitespace-nowrap">
                                    @can('viewAny', Asset::class)
                                        <flux:link :href="route('assets.show', $asset->id)" wire:navigate>{{ $asset->code }}</flux:link>
                                    @else
                                        {{ $asset->code }}
                                    @endcan
                                </flux:table.cell>
                                <flux:table.cell>{{ $asset->display_name }}</flux:table.cell>
                                <flux:table.cell>{{ $asset->asset_type }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$asset->condition_color->badgeColor()">{{ $asset->condition }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">{{ $asset->status->label() }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </flux:card>

    <div class="grid gap-6 lg:grid-cols-2">
        <livewire:reservations.space-reservations :space="$space" />
        <livewire:reservations.availability-check :space="$space" />
    </div>
</div>
