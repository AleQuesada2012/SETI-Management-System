<?php

use App\Models\Reporting\SpaceManagerRow;
use App\Models\Reporting\SpaceRow;
use App\Models\Reporting\UserRow;
use App\Queries\Users\SpaceManagers;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Encargados de espacios: the Superusuario picks a space and sees the Administradores in charge of it (RF_96), or
 * picks an Administrador and sees the spaces in their charge (RF_97). Owner: users (@Rubiocai). Reads
 * reporting.space_managers through App\Queries\Users\SpaceManagers; the route checks spaces.assign_managers
 * (Superusuario only). Assigning and removing managers is on the user page (RF_40, RF_43).
 *
 * Both selects start on a real, selectable empty option and bind to strings ('' while none is chosen): Flux's
 * disabled placeholder cannot stay selected, so the browser showed the first option while none was chosen.
 */
new #[Title('users.space_managers.title')] class extends Component
{
    /** The chosen space id, kept in the URL (?espacio=3). */
    #[Url(as: 'espacio', except: '')]
    public string $space = '';

    /** The chosen Administrador's user id, kept in the URL (?administrador=3). */
    #[Url(as: 'administrador', except: '')]
    public string $administrator = '';

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
        return ctype_digit($this->space) ? $this->spaces->firstWhere('id', (int) $this->space) : null;
    }

    /** @return Collection<int, SpaceManagerRow> */
    #[Computed]
    public function managers(): Collection
    {
        return $this->selectedSpace === null ? new Collection : SpaceManagers::ofSpace($this->selectedSpace->id);
    }

    /** @return Collection<int, UserRow> */
    #[Computed]
    public function administrators(): Collection
    {
        return SpaceManagers::administrators();
    }

    /** The chosen Administrador, or null when none is chosen or the id is not an Administrador's (an edited URL). */
    #[Computed]
    public function selectedAdministrator(): ?UserRow
    {
        return ctype_digit($this->administrator) ? $this->administrators->firstWhere('id', (int) $this->administrator) : null;
    }

    /** @return Collection<int, SpaceManagerRow> */
    #[Computed]
    public function managedSpaces(): Collection
    {
        return $this->selectedAdministrator === null ? new Collection : SpaceManagers::ofManager($this->selectedAdministrator->id);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:text>{{ __('users.space_managers.intro') }}</flux:text>
        <flux:button icon="arrow-left" :href="route('users.index')" wire:navigate>{{ __('users.space_managers.back') }}</flux:button>
    </div>

    {{-- RF_96: by space --}}
    <section class="flex flex-col gap-4" data-test="by-space">
        <flux:heading level="2" size="lg">{{ __('users.space_managers.by_space') }}</flux:heading>

        <flux:card>
            <flux:select wire:model.live="space" :label="__('users.space_managers.space')" class="sm:max-w-md">
                <flux:select.option value="">{{ __('users.space_managers.choose') }}</flux:select.option>
                @foreach ($this->spaces as $option)
                    <flux:select.option :value="(string) $option->id" wire:key="space-{{ $option->id }}">
                        {{ $option->code }} · {{ $option->name }} ({{ $option->space_type }})
                    </flux:select.option>
                @endforeach
            </flux:select>
        </flux:card>

        @if ($space !== '' && $this->selectedSpace === null)
            <flux:callout icon="exclamation-triangle" color="amber" :text="__('users.space_managers.unknown_space')" data-test="unknown-space" />
        @elseif ($this->selectedSpace !== null)
            <flux:card class="overflow-x-auto">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <flux:heading level="3">
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
    </section>

    {{-- RF_97: by Administrador --}}
    <section class="flex flex-col gap-4 border-t border-line pt-6" data-test="by-administrator">
        <flux:heading level="2" size="lg">{{ __('users.space_managers.by_administrator') }}</flux:heading>

        <flux:card>
            <flux:select wire:model.live="administrator" :label="__('users.space_managers.administrator')" class="sm:max-w-md">
                <flux:select.option value="">{{ __('users.space_managers.choose_administrator') }}</flux:select.option>
                @foreach ($this->administrators as $option)
                    <flux:select.option :value="(string) $option->id" wire:key="administrator-{{ $option->id }}">
                        {{ $option->name }} · {{ $option->email }}{{ $option->account_status === 'deactivated' ? ' ('.__('users.status.deactivated').')' : '' }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </flux:card>

        @if ($administrator !== '' && $this->selectedAdministrator === null)
            <flux:callout icon="exclamation-triangle" color="amber" :text="__('users.space_managers.unknown_administrator')" data-test="unknown-administrator" />
        @elseif ($this->selectedAdministrator !== null)
            <flux:card class="overflow-x-auto">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <flux:heading level="3">
                        <flux:link :href="route('users.show', $this->selectedAdministrator->id)" wire:navigate variant="subtle" class="text-ink">
                            {{ $this->selectedAdministrator->name }}
                        </flux:link>
                    </flux:heading>
                    @if ($this->selectedAdministrator->account_status === 'deactivated')
                        <flux:badge size="sm" color="red">{{ __('users.status.deactivated') }}</flux:badge>
                    @endif
                </div>

                @if ($this->managedSpaces->isEmpty())
                    <flux:text data-test="no-spaces">{{ __('users.space_managers.no_spaces') }}</flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('users.space_managers.space') }}</flux:table.column>
                            <flux:table.column>{{ __('users.space_managers.type') }}</flux:table.column>
                            <flux:table.column>{{ __('users.space_managers.assigned_at') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->managedSpaces as $assignment)
                                <flux:table.row :key="$assignment->space_id">
                                    <flux:table.cell variant="strong">
                                        <a href="{{ route('spaces.show', $assignment->space_id) }}" class="hover:underline" wire:navigate>
                                            {{ $assignment->space_code }} · {{ $assignment->space_name }}
                                        </a>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $assignment->space_type }}</flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap">{{ $assignment->assigned_at->format('d/m/Y H:i') }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        @endif
    </section>
</div>
