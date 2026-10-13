<?php

use App\Actions\Spaces\AssignSpaceManager;
use App\Actions\Users\AssignRole;
use App\Enums\PermissionName;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Detalle del usuario (RF_94), role assignment (RF_85) and, for an Administrador, the spaces in their charge (RF_40,
 * through App\Actions\Spaces\AssignSpaceManager, reviewed by spaces). Owner: users (@Rubiocai). A missing user is a 404 from
 * route model binding, rendered by errors/404.blade.php. Role changes (RF_85–RF_87) go through app/Actions/Users,
 * which audit them explicitly because pivot changes fire no model events.
 */
new #[Title('users.show.title')] class extends Component
{
    public User $user;

    /** The role picked to assign; always one the user does not hold yet, so the select and the state agree. */
    public ?int $roleId = null;

    public function mount(): void
    {
        $this->roleId = $this->assignableRoles->first()?->id;
    }

    /** Roles the user does not hold yet (RF_85). @return Collection<int, Role> */
    #[Computed]
    public function assignableRoles(): Collection
    {
        return Role::query()
            ->whereNotIn('id', $this->user->roles()->pluck('roles.id'))
            ->orderBy('name')
            ->get();
    }

    public function assignRole(AssignRole $assignRole): void
    {
        $this->authorize('assignRoles', $this->user);

        $this->validate(
            ['roleId' => ['required', 'integer', 'exists:roles,id']],
            attributes: ['roleId' => __('users.assign_role.role')],
        );

        $role = Role::query()->findOrFail($this->roleId);

        $assignRole->handle(auth()->user(), $this->user, $role);

        $this->user->refresh();
        unset($this->assignableRoles);
        $this->roleId = $this->assignableRoles->first()?->id;

        Flux::toast(variant: 'success', text: __('users.assign_role.done', ['role' => $role->name, 'user' => $this->user->name]));
    }

    /** @var list<int|string> Spaces picked in the "Asignar espacios" modal (RF_40). */
    public array $spaces = [];

    public function isAdministrador(): bool
    {
        return $this->user->hasRole(SystemRole::Administrador->value);
    }

    /** Spaces this user is in charge of (space_managers). @return Collection<int, Space> */
    #[Computed]
    public function managedSpaces(): Collection
    {
        return $this->user->managedSpaces()->with('spaceType')->orderBy('code')->get();
    }

    /** Spaces this user is not in charge of yet. @return Collection<int, Space> */
    #[Computed]
    public function assignableSpaces(): Collection
    {
        return Space::query()
            ->whereNotIn('id', $this->managedSpaces->modelKeys())
            ->with('spaceType')
            ->orderBy('code')
            ->get();
    }

    public function assignSpaces(AssignSpaceManager $assignSpaceManager): void
    {
        $this->authorize(PermissionName::SpacesAssignManagers->value);

        $assigned = $assignSpaceManager->handle(auth()->user(), $this->user, $this->spaces);

        $this->reset('spaces');
        unset($this->managedSpaces, $this->assignableSpaces);
        Flux::modal('assign-spaces')->close();
        Flux::toast(variant: 'success', text: trans_choice('users.managed_spaces.done', $assigned->count(), [
            'spaces' => $assigned->pluck('code')->join(', '),
            'user' => $this->user->name,
        ]));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:heading size="lg" level="2">{{ $user->name }}</flux:heading>
        <flux:button icon="arrow-left" :href="route('users.index')" wire:navigate>{{ __('users.show.back') }}</flux:button>
    </div>

    <flux:card>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-ink-muted">{{ __('users.fields.name') }}</dt>
                <dd class="font-medium text-ink">{{ $user->name }}</dd>
            </div>

            <div>
                <dt class="text-sm text-ink-muted">{{ __('users.fields.email') }}</dt>
                <dd class="break-all font-medium text-ink">{{ $user->email }}</dd>
            </div>

            <div>
                <dt class="text-sm text-ink-muted">{{ __('users.fields.roles') }}</dt>
                <dd class="mt-1 flex flex-wrap gap-1">
                    @forelse ($user->getRoleNames()->sort() as $roleName)
                        <flux:badge size="sm" color="blue">{{ $roleName }}</flux:badge>
                    @empty
                        <span class="text-ink-muted">{{ __('users.fields.no_roles') }}</span>
                    @endforelse
                </dd>
            </div>

            <div>
                <dt class="text-sm text-ink-muted">{{ __('users.fields.status') }}</dt>
                <dd class="mt-1 flex flex-wrap gap-1">
                    <flux:badge size="sm" :color="$user->isActive() ? 'green' : 'red'">
                        {{ __('users.status.'.($user->isActive() ? 'active' : 'deactivated')) }}
                    </flux:badge>
                    @if ($user->password === null)
                        <flux:badge size="sm" color="amber">{{ __('users.status.invitation_pending') }}</flux:badge>
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-sm text-ink-muted">{{ __('users.fields.last_login_at') }}</dt>
                <dd class="text-ink">{{ $user->last_login_at?->format('d/m/Y H:i') ?? __('users.show.never') }}</dd>
            </div>

            <div>
                <dt class="text-sm text-ink-muted">{{ __('users.fields.created_at') }}</dt>
                <dd class="text-ink">{{ $user->created_at?->format('d/m/Y H:i') }}</dd>
            </div>

            @unless ($user->isActive())
                <div>
                    <dt class="text-sm text-ink-muted">{{ __('users.fields.deactivated_at') }}</dt>
                    <dd class="text-ink">{{ $user->deactivated_at->format('d/m/Y H:i') }}</dd>
                </div>
            @endunless
        </dl>
    </flux:card>

    @can('assignRoles', $user)
        <flux:card>
            <flux:heading level="3" class="mb-3">{{ __('users.assign_role.heading') }}</flux:heading>

            @if ($this->assignableRoles->isEmpty())
                <flux:text>{{ __('users.assign_role.none_left') }}</flux:text>
            @else
                <form wire:submit="assignRole" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <flux:select wire:model="roleId" :label="__('users.assign_role.role')" class="sm:max-w-64">
                        @foreach ($this->assignableRoles as $role)
                            <flux:select.option :value="$role->id">{{ $role->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:button type="submit" variant="primary" icon="plus">{{ __('users.assign_role.submit') }}</flux:button>
                </form>
            @endif
        </flux:card>
    @endcan

    @if ($this->isAdministrador())
        <flux:card class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading level="3">{{ __('users.managed_spaces.heading') }}</flux:heading>
                @can(PermissionName::SpacesAssignManagers)
                    @if ($this->assignableSpaces->isNotEmpty())
                        <flux:modal.trigger name="assign-spaces">
                            <flux:button size="sm" variant="primary" icon="plus">{{ __('users.managed_spaces.assign') }}</flux:button>
                        </flux:modal.trigger>
                    @endif
                @endcan
            </div>

            @if ($this->managedSpaces->isEmpty())
                <flux:text>{{ __('users.managed_spaces.none') }}</flux:text>
            @else
                <ul class="flex flex-col divide-y divide-line">
                    @foreach ($this->managedSpaces as $space)
                        <li wire:key="managed-{{ $space->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <a href="{{ route('spaces.show', $space) }}" class="font-medium text-ink hover:underline" wire:navigate>
                                {{ $space->code }} · {{ $space->name }}
                            </a>
                            <flux:badge size="sm" color="zinc">{{ $space->spaceType->name }}</flux:badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>

        @can(PermissionName::SpacesAssignManagers)
            <flux:modal name="assign-spaces" class="w-full max-w-md">
                <form wire:submit="assignSpaces" class="flex flex-col gap-5">
                    <div>
                        <flux:heading size="lg">{{ __('users.managed_spaces.assign') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('users.managed_spaces.help', ['user' => $user->name]) }}</flux:text>
                    </div>

                    <flux:checkbox.group wire:model="spaces" :label="__('users.managed_spaces.spaces')">
                        @foreach ($this->assignableSpaces as $space)
                            <flux:checkbox wire:key="assignable-{{ $space->id }}" :value="$space->id" :label="$space->code.' · '.$space->name" :description="$space->spaceType->name" />
                        @endforeach
                    </flux:checkbox.group>
                    <flux:error name="spaces.*" />

                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('users.managed_spaces.cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary">{{ __('users.managed_spaces.submit') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endcan
    @endif
</div>
