<?php

use App\Actions\Users\AssignRole;
use App\Models\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Detalle del usuario (RF_94) and role assignment (RF_85). Owner: users (@Rubiocai). A missing user is a 404 from
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
</div>
