<?php

use App\Models\Reporting\SpaceManagerRow;
use App\Models\Reporting\SpaceRow;
use App\Queries\Users\SpaceManagers;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Encargados de espacios (RF_96): the Superusuario picks a space and sees the Administradores in charge of it.
 * Owner: users (@Rubiocai). Reads reporting.space_managers through App\Queries\Users\SpaceManagers; the route checks
 * spaces.assign_managers (Superusuario only). Assigning and removing managers is on the user page (RF_40, RF_43).
 */
new #[Title('users.space_managers.title')] class extends Component
{
    /** The chosen space, kept in the URL (?espacio=3). */
    #[Url(as: 'espacio', except: null)]
    public ?int $space = null;

    /** @return Collection<int, SpaceRow> */
    #[Computed]
    public function spaces(): Collection
    {
        return SpaceManagers::spaces();
    }

    /** The chosen space, or null when none is chosen or it no longer exists (a deleted space, an edited URL). */
    #[Computed]
    public function selectedSpace(): ?SpaceRow
    {
        return $this->space === null ? null : $this->spaces->firstWhere('id', $this->space);
    }

    /** @return Collection<int, SpaceManagerRow> */
    #[Computed]
    public function managers(): Collection
    {
        return $this->selectedSpace === null ? new Collection : SpaceManagers::ofSpace($this->selectedSpace->id);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:text>{{ __('users.space_managers.intro') }}</flux:text>
        <flux:button icon="arrow-left" :href="route('users.index')" wire:navigate>{{ __('users.space_managers.back') }}</flux:button>
    </div>

    <flux:card>
        <flux:select wire:model.live="space" :label="__('users.space_managers.space')" :placeholder="__('users.space_managers.choose')" class="sm:max-w-md">
            @foreach ($this->spaces as $option)
                <flux:select.option :value="$option->id" wire:key="space-{{ $option->id }}">
                    {{ $option->code }} · {{ $option->name }} ({{ $option->space_type }})
                </flux:select.option>
            @endforeach
        </flux:select>
    </flux:card>

    @if ($space !== null && $this->selectedSpace === null)
        <flux:callout icon="exclamation-triangle" color="amber" :text="__('users.space_managers.unknown_space')" data-test="unknown-space" />
    @elseif ($this->selectedSpace !== null)
        <flux:card class="overflow-x-auto">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <flux:heading level="2">
                    <flux:link :href="route('spaces.show', $this->selectedSpace->id)" wire:navigate variant="subtle" class="text-ink">
                        {{ $this->selectedSpace->code }} · {{ $this->selectedSpace->name }}
                    </flux:link>
                </flux:heading>
                <flux:badge size="sm" color="zinc">{{ $this->selectedSpace->space_type }}</flux:badge>
            </div>

            @if ($this->managers->isEmpty())
                <flux:text data-test="no-managers">{{ __('users.space_managers.none') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('users.fields.name') }}</flux:table.column>
                        <flux:table.column>{{ __('users.fields.email') }}</flux:table.column>
                        <flux:table.column>{{ __('users.space_managers.assigned_at') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->managers as $manager)
                            <flux:table.row :key="$manager->user_id">
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('users.show', $manager->user_id) }}" class="hover:underline" wire:navigate>{{ $manager->manager_name }}</a>
                                </flux:table.cell>
                                <flux:table.cell>{{ $manager->manager_email }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">{{ $manager->assigned_at->format('d/m/Y H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif
</div>
