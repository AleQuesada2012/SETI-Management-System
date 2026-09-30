<?php

use App\Models\Reporting\SpaceRow;
use App\Queries\Spaces\SpaceDirectory;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * RF_42 · "Buscar espacios" on the spaces list (pages/spaces/index), in the slot that page keeps for spaces. It matches
 * the identifier, the name and the location through SpaceDirectory's `search` filter, so the list (RF_53) and the
 * spaces report (RF_64) search the same way. Results show only while there is a term; the list itself is RF_53's.
 */
new class extends Component {
    #[Url(as: 'buscar')]
    public string $search = '';

    /** @return Collection<int, SpaceRow> */
    #[Computed]
    public function results(): Collection
    {
        return SpaceDirectory::query(['search' => $this->search])->get();
    }

    /** Tell the spaces list (RF_53/RF_54) about the new term, so the search and its filters narrow the same list. */
    public function updatedSearch(): void
    {
        $this->dispatch('space-search-updated', search: $this->search);
    }
}; ?>

<div class="flex flex-col gap-4">
    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" clearable
        :placeholder="__('spaces.search.placeholder')" :aria-label="__('spaces.search.label')" data-test="space-search" />

    @if (trim($search) !== '')
        <div wire:loading.class="opacity-60" class="flex flex-col gap-2">
            @if ($this->results->isEmpty())
                <flux:callout icon="magnifying-glass" data-test="space-search-empty">
                    <flux:callout.text>{{ __('spaces.search.none', ['term' => trim($search)]) }}</flux:callout.text>
                </flux:callout>
            @else
                <flux:text class="text-sm">{{ trans_choice('spaces.search.found', $this->results->count(), ['count' => $this->results->count()]) }}</flux:text>

                <div class="overflow-x-auto">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('spaces.search.code') }}</flux:table.column>
                            <flux:table.column>{{ __('spaces.search.name') }}</flux:table.column>
                            <flux:table.column>{{ __('spaces.search.location') }}</flux:table.column>
                            <flux:table.column>{{ __('spaces.search.type') }}</flux:table.column>
                            <flux:table.column>{{ __('spaces.search.status') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->results as $space)
                                <flux:table.row :key="$space->id">
                                    <flux:table.cell class="whitespace-nowrap">
                                        <flux:link :href="route('spaces.show', $space->id)" wire:navigate>{{ $space->code }}</flux:link>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $space->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $space->location() }}</flux:table.cell>
                                    <flux:table.cell>{{ $space->space_type }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$space->status_color->badgeColor()">{{ $space->status }}</flux:badge>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        </div>
    @endif
</div>
