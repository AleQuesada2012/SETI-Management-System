<?php

use App\Models\Asset;
use App\Queries\Assets\AssetHistory;
use App\Queries\Assets\AssetHistoryEntry;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * RF_09 · Consulta del historial de un activo. Owner: assets. Everyone who may view the asset (the route checked `view`)
 * sees, newest first, its movements, events and the changes made to its record, each with the user responsible
 * (App\Queries\Assets\AssetHistory), or a notice that the history is empty. Read-only: nothing changes from here.
 */
new #[Title('assets.history.title')] class extends Component {
    public Asset $asset;

    /** @return Collection<int, AssetHistoryEntry> */
    #[Computed]
    public function entries(): Collection
    {
        return AssetHistory::forAsset($this->asset);
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="lg" level="2">{{ __('assets.history.title') }}</flux:heading>
            <flux:text class="mt-1">{{ __('assets.history.description', ['asset' => $asset->code.' · '.$asset->displayName()]) }}</flux:text>
        </div>
        <flux:button :href="route('assets.show', $asset)" wire:navigate icon="arrow-left" size="sm" class="shrink-0">{{ __('assets.history.back') }}</flux:button>
    </div>

    @if ($this->entries->isEmpty())
        <flux:callout icon="clock" :heading="__('assets.history.empty')" data-test="history-empty" />
    @else
        <ol class="flex flex-col gap-3" data-test="asset-history">
            @foreach ($this->entries as $entry)
                <li wire:key="history-{{ $loop->index }}" data-test="history-entry" data-type="{{ $entry->type }}">
                    <flux:card class="flex flex-col gap-2 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" :color="match ($entry->type) { 'movement' => 'blue', 'event' => 'amber', default => 'zinc' }">{{ __('assets.history.types.'.$entry->type) }}</flux:badge>
                            <flux:heading level="3" class="min-w-0">{{ $entry->title }}</flux:heading>
                            <flux:text class="ms-auto whitespace-nowrap text-sm">{{ $entry->at->format($entry->dateOnly ? 'd/m/Y' : 'd/m/Y H:i') }}</flux:text>
                        </div>
                        @foreach ($entry->details as $detail)
                            <flux:text class="whitespace-pre-line break-words text-ink">{{ $detail }}</flux:text>
                        @endforeach
                        <flux:text class="text-sm">{{ __('assets.history.by', ['user' => $entry->actor ?? __('assets.history.system')]) }}</flux:text>
                    </flux:card>
                </li>
            @endforeach
        </ol>
    @endif
</div>
