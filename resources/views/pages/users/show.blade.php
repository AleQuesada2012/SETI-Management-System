<?php

use App\Actions\Spaces\AssignSpaceManager;
use App\Actions\Spaces\RemoveSpaceManager;
use App\Actions\Users\AssignRole;
use App\Actions\Users\ChangeRole;
use App\Actions\Users\DeactivateUser;
use App\Actions\Users\ReactivateUser;
use App\Actions\Users\RemoveRole;
use App\Enums\PermissionName;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Detalle del usuario (RF_94), its roles (assign RF_85, remove RF_86, change RF_87) and, for an Administrador, the spaces in their
 * charge: assign (RF_40) and remove (RF_43) through App\Actions\Spaces\AssignSpaceManager and RemoveSpaceManager,
 * reviewed by spaces. Owner: users (@Rubiocai). A missing user is a 404 from route model binding, rendered by
 * errors/404.blade.php. Role changes (RF_85–RF_87) go through app/Actions/Users,
 * which audit them explicitly because pivot changes fire no model events. The account status card deactivates (RF_88)
 * and reactivates (RF_88, RF_98) a user through DeactivateUser and ReactivateUser.
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

    /** The role whose removal is being confirmed (RF_86). */
    public ?int $roleToRemove = null;

    /** Open the confirmation window that explains what removing this role takes away (RF_86 criterion 2). */
    public function confirmRoleRemoval(int $roleId): void
    {
        $this->authorize('assignRoles', $this->user);

        $this->roleToRemove = $roleId;
        unset($this->roleRemoval);
        $this->resetErrorBag('roles');

        Flux::modal('remove-role')->show();
    }

    /**
     * What removing the chosen role takes away: the permissions no other role of the user still grants, and, for the
     * Administrador role, the spaces in their charge.
     *
     * @return array{role: Role, permissions: Collection<int, string>, spaces: Collection<int, Space>}|null
     */
    #[Computed]
    public function roleRemoval(): ?array
    {
        $role = $this->user->roles()->with('permissions')->find($this->roleToRemove);

        if ($role === null) {
            return null;
        }

        $kept = $this->user->roles()->whereKeyNot($role->id)->with('permissions')->get()
            ->flatMap(fn (Role $other) => $other->permissions->pluck('name'));

        return [
            'role' => $role,
            'permissions' => $role->permissions->reject(fn ($permission): bool => $kept->contains($permission->name))
                ->sortBy('name')->map(fn ($permission): string => $permission->description ?? $permission->name)->values(),
            'spaces' => $role->name === SystemRole::Administrador->value ? $this->managedSpaces : new Collection,
        ];
    }

    /** The role being changed (RF_87). */
    public ?int $roleToChange = null;

    /** The new role picked in the change window; '' while none is chosen (a real, selectable empty option). */
    public string $newRoleId = '';

    /** Open the window that swaps this role for another (RF_87). */
    public function confirmRoleChange(int $roleId): void
    {
        $this->authorize('assignRoles', $this->user);

        $this->roleToChange = $roleId;
        $this->newRoleId = '';
        unset($this->roleChange);
        $this->resetErrorBag(['roles', 'newRoleId']);

        Flux::modal('change-role')->show();
    }

    /**
     * What swapping the role changes: the permissions the user gains and loses (counting their other roles), and, when
     * leaving Administrador, the spaces in their charge.
     *
     * @return array{from: Role, to: Role|null, gains: Collection<int, string>, loses: Collection<int, string>, spaces: Collection<int, Space>}|null
     */
    #[Computed]
    public function roleChange(): ?array
    {
        $from = $this->user->roles()->with('permissions')->find($this->roleToChange);

        if ($from === null) {
            return null;
        }

        $to = ctype_digit($this->newRoleId) ? $this->assignableRoles->load('permissions')->firstWhere('id', (int) $this->newRoleId) : null;
        $kept = $this->user->roles()->whereKeyNot($from->id)->with('permissions')->get()
            ->flatMap(fn (Role $other) => $other->permissions->pluck('name'));
        $describe = fn (Collection $permissions): Collection => $permissions->sortBy('name')
            ->map(fn ($permission): string => $permission->description ?? $permission->name)->values();
        $after = $kept->merge($to?->permissions->pluck('name') ?? []);
        $before = $kept->merge($from->permissions->pluck('name'));

        return [
            'from' => $from,
            'to' => $to,
            'gains' => $to === null ? new Collection : $describe($to->permissions->reject(fn ($p): bool => $before->contains($p->name))),
            'loses' => $to === null ? new Collection : $describe($from->permissions->reject(fn ($p): bool => $after->contains($p->name))),
            'spaces' => $to !== null && $from->name === SystemRole::Administrador->value && $to->name !== SystemRole::Administrador->value
                ? $this->managedSpaces : new Collection,
        ];
    }

    public function changeRole(ChangeRole $changeRole): void
    {
        $this->authorize('assignRoles', $this->user);

        $this->validate(
            ['newRoleId' => ['required', 'integer', 'exists:roles,id']],
            attributes: ['newRoleId' => __('users.change_role.new_role')],
        );

        $change = $this->roleChange;

        if ($change === null || $change['to'] === null) {
            throw ValidationException::withMessages(['newRoleId' => __('users.change_role.errors.unavailable')]);
        }

        try {
            $changeRole->handle(auth()->user(), $this->user, $change['from'], $change['to']);
        } finally {
            Flux::modal('change-role')->close();
        }

        $this->user->refresh();
        $this->reset('roleToChange', 'newRoleId');
        unset($this->assignableRoles, $this->managedSpaces, $this->assignableSpaces, $this->roleChange, $this->roleRemoval);
        $this->roleId = $this->assignableRoles->first()?->id;

        Flux::toast(variant: 'success', text: __('users.change_role.done', [
            'from' => $change['from']->name, 'to' => $change['to']->name, 'user' => $this->user->name,
        ]));
    }

    public function removeRole(RemoveRole $removeRole): void
    {
        $this->authorize('assignRoles', $this->user);

        $role = $this->roleRemoval['role'] ?? null;

        try {
            if ($role === null) {
                throw ValidationException::withMessages(['roles' => __('users.remove_role.errors.not_held', ['role' => ''])]);
            }

            $removeRole->handle(auth()->user(), $this->user, $role);
        } finally {
            Flux::modal('remove-role')->close();
        }

        $this->user->refresh();
        $this->reset('roleToRemove');
        unset($this->assignableRoles, $this->managedSpaces, $this->assignableSpaces, $this->roleRemoval);
        $this->roleId = $this->assignableRoles->first()?->id;

        Flux::toast(variant: 'success', text: __('users.remove_role.done', ['role' => $role->name, 'user' => $this->user->name]));
    }

    /** @var list<int|string> Spaces picked in the "Asignar espacios" modal (RF_40). */
    public array $spaces = [];

    /** @var list<int|string> Spaces ticked in the list, to remove after confirming (RF_43). */
    public array $spacesToRemove = [];

    /** The spaces card shows for Administradores, and for anyone still holding assignments (so they can be removed). */
    public function showsManagedSpaces(): bool
    {
        return $this->user->hasRole(SystemRole::Administrador->value) || $this->managedSpaces->isNotEmpty();
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

    /** Spaces listed in the confirmation window. @return Collection<int, Space> */
    #[Computed]
    public function spacesPendingRemoval(): Collection
    {
        $ids = array_map(intval(...), $this->spacesToRemove);

        return $this->managedSpaces->filter(fn (Space $space): bool => in_array($space->id, $ids, true))->values();
    }

    /** Open the confirmation window for one space (its row button) or for the ticked ones. */
    public function confirmRemoval(?int $spaceId = null): void
    {
        $this->authorize(PermissionName::SpacesAssignManagers->value);

        if ($spaceId !== null) {
            $this->spacesToRemove = [$spaceId];
        }

        unset($this->spacesPendingRemoval);

        if ($this->spacesPendingRemoval->isEmpty()) {
            $this->addError('spacesToRemove', __('users.managed_spaces.choose_to_remove'));

            return;
        }

        $this->resetErrorBag('spacesToRemove');
        Flux::modal('remove-spaces')->show();
    }

    public function removeSpaces(RemoveSpaceManager $removeSpaceManager): void
    {
        $this->authorize(PermissionName::SpacesAssignManagers->value);

        try {
            $removed = $removeSpaceManager->handle(auth()->user(), $this->user, $this->spacesToRemove);
        } catch (ValidationException $exception) {
            Flux::modal('remove-spaces')->close();

            // The Action validates `spaces`, `spaces.0`…; the list's checkboxes live under `spacesToRemove`.
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => [
                    preg_replace('/^spaces/', 'spacesToRemove', $key) => $messages,
                ])->all(),
            );
        }

        $this->reset('spacesToRemove');
        unset($this->managedSpaces, $this->assignableSpaces, $this->spacesPendingRemoval);
        Flux::modal('remove-spaces')->close();
        Flux::toast(variant: 'success', text: trans_choice('users.managed_spaces.removed', $removed->count(), [
            'spaces' => $removed->pluck('code')->join(', '),
            'user' => $this->user->name,
        ]));
    }

    /** RF_88: revoke access after the confirmation window. */
    public function deactivate(DeactivateUser $deactivateUser): void
    {
        $this->authorize('deactivate', $this->user);

        try {
            $deactivateUser->handle(auth()->user(), $this->user);
        } finally {
            Flux::modal('deactivate-account')->close();
        }

        $this->user->refresh();
        Flux::toast(variant: 'success', text: __('users.account.deactivated', ['user' => $this->user->name]));
    }

    /** RF_88 criterion 4, RF_98: give access back after the confirmation window. */
    public function reactivate(ReactivateUser $reactivateUser): void
    {
        $this->authorize('deactivate', $this->user);

        try {
            $reactivateUser->handle(auth()->user(), $this->user);
        } finally {
            Flux::modal('reactivate-account')->close();
        }

        $this->user->refresh();
        Flux::toast(variant: 'success', text: __('users.account.reactivated', ['user' => $this->user->name]));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:heading size="lg" level="2">{{ $user->name }}</flux:heading>
        <div class="flex flex-wrap gap-2">
            @can('update', $user)
                <flux:button icon="pencil-square" :href="route('users.edit', $user)" wire:navigate>{{ __('users.edit.edit') }}</flux:button>
            @endcan
            <flux:button icon="arrow-left" :href="route('users.index')" wire:navigate>{{ __('users.show.back') }}</flux:button>
        </div>
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
        <flux:card class="flex flex-col gap-3" data-test="roles-card">
            <flux:heading level="3">{{ __('users.remove_role.heading') }}</flux:heading>

            <ul class="flex flex-col divide-y divide-line">
                @foreach ($user->roles->sortBy('name') as $heldRole)
                    <li wire:key="held-role-{{ $heldRole->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <flux:badge size="sm" color="blue">{{ $heldRole->name }}</flux:badge>
                        <div class="flex flex-wrap gap-1">
                            @if ($this->assignableRoles->isNotEmpty())
                                <flux:button size="sm" variant="ghost" icon="arrows-right-left" wire:click="confirmRoleChange({{ $heldRole->id }})">
                                    {{ __('users.change_role.change') }}
                                </flux:button>
                            @endif
                            @if ($user->roles->count() > 1)
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="confirmRoleRemoval({{ $heldRole->id }})">
                                    {{ __('users.remove_role.remove') }}
                                </flux:button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($user->roles->count() <= 1)
                <flux:text size="sm">{{ __('users.remove_role.only_role') }}</flux:text>
            @endif
            <flux:error name="roles" />

            <flux:heading level="3" class="mt-2">{{ __('users.assign_role.heading') }}</flux:heading>

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

        <flux:modal name="change-role" class="w-full max-w-md">
            <form wire:submit="changeRole" class="flex flex-col gap-4">
                @if ($this->roleChange !== null)
                    <div>
                        <flux:heading size="lg">{{ __('users.change_role.heading', ['role' => $this->roleChange['from']->name]) }}</flux:heading>
                        <flux:text class="mt-2">{{ __('users.change_role.help', ['user' => $user->name, 'role' => $this->roleChange['from']->name]) }}</flux:text>
                    </div>

                    <flux:select wire:model.live="newRoleId" :label="__('users.change_role.new_role')">
                        <flux:select.option value="">{{ __('users.change_role.choose') }}</flux:select.option>
                        @foreach ($this->assignableRoles as $option)
                            <flux:select.option :value="(string) $option->id" wire:key="change-to-{{ $option->id }}">{{ $option->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if ($this->roleChange['to'] !== null)
                        <div data-test="change-preview" class="flex flex-col gap-3">
                            <div>
                                <flux:text class="font-medium text-ink">{{ __('users.change_role.gains') }}</flux:text>
                                @if ($this->roleChange['gains']->isEmpty())
                                    <flux:text size="sm">{{ __('users.change_role.gains_nothing') }}</flux:text>
                                @else
                                    <ul class="mt-1 list-disc space-y-0.5 ps-5 text-sm text-ink">
                                        @foreach ($this->roleChange['gains'] as $description)
                                            <li>{{ $description }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div>
                                <flux:text class="font-medium text-ink">{{ __('users.remove_role.loses') }}</flux:text>
                                @if ($this->roleChange['loses']->isEmpty())
                                    <flux:text size="sm">{{ __('users.change_role.loses_nothing') }}</flux:text>
                                @else
                                    <ul class="mt-1 list-disc space-y-0.5 ps-5 text-sm text-ink">
                                        @foreach ($this->roleChange['loses'] as $description)
                                            <li>{{ $description }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            @if ($this->roleChange['spaces']->isNotEmpty())
                                <div>
                                    <flux:text class="font-medium text-ink">{{ __('users.remove_role.loses_spaces') }}</flux:text>
                                    <ul class="mt-1 list-disc space-y-0.5 ps-5 text-sm text-ink">
                                        @foreach ($this->roleChange['spaces'] as $space)
                                            <li>{{ $space->code }} · {{ $space->name }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('users.remove_role.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('users.change_role.submit') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="remove-role" class="w-full max-w-md">
            <form wire:submit="removeRole" class="flex flex-col gap-4">
                @if ($this->roleRemoval !== null)
                    <div>
                        <flux:heading size="lg">{{ __('users.remove_role.confirm_heading', ['role' => $this->roleRemoval['role']->name]) }}</flux:heading>
                        <flux:text class="mt-2">{{ __('users.remove_role.confirm_help', ['user' => $user->name, 'role' => $this->roleRemoval['role']->name]) }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="font-medium text-ink">{{ __('users.remove_role.loses') }}</flux:text>
                        @if ($this->roleRemoval['permissions']->isEmpty())
                            <flux:text size="sm">{{ __('users.remove_role.loses_nothing') }}</flux:text>
                        @else
                            <ul class="mt-1 list-disc space-y-0.5 ps-5 text-sm text-ink">
                                @foreach ($this->roleRemoval['permissions'] as $description)
                                    <li>{{ $description }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    @if ($this->roleRemoval['spaces']->isNotEmpty())
                        <div>
                            <flux:text class="font-medium text-ink">{{ __('users.remove_role.loses_spaces') }}</flux:text>
                            <ul class="mt-1 list-disc space-y-0.5 ps-5 text-sm text-ink">
                                @foreach ($this->roleRemoval['spaces'] as $space)
                                    <li>{{ $space->code }} · {{ $space->name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('users.remove_role.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">{{ __('users.remove_role.confirm') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

    @if ($this->showsManagedSpaces())
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
                @can(PermissionName::SpacesAssignManagers)
                    <flux:callout icon="information-circle" color="amber" :text="__('spaces.managers.none_to_remove', ['user' => $user->name])" />
                @else
                    <flux:text>{{ __('users.managed_spaces.none') }}</flux:text>
                @endcan
            @else
                <ul class="flex flex-col divide-y divide-line">
                    @foreach ($this->managedSpaces as $space)
                        <li wire:key="managed-{{ $space->id }}" class="flex items-center gap-3 py-2">
                            @can(PermissionName::SpacesAssignManagers)
                                <flux:checkbox wire:model="spacesToRemove" :value="$space->id" :aria-label="__('users.managed_spaces.select', ['space' => $space->code])" />
                            @endcan

                            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
                                <a href="{{ route('spaces.show', $space) }}" class="font-medium text-ink hover:underline" wire:navigate>
                                    {{ $space->code }} · {{ $space->name }}
                                </a>
                                <flux:badge size="sm" color="zinc">{{ $space->spaceType->name }}</flux:badge>
                            </div>

                            @can(PermissionName::SpacesAssignManagers)
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="confirmRemoval({{ $space->id }})">
                                    {{ __('users.managed_spaces.remove') }}
                                </flux:button>
                            @endcan
                        </li>
                    @endforeach
                </ul>

                @can(PermissionName::SpacesAssignManagers)
                    <flux:error name="spacesToRemove" />
                    <flux:error name="spacesToRemove.*" />

                    <div>
                        <flux:button size="sm" variant="danger" icon="trash" wire:click="confirmRemoval">
                            {{ __('users.managed_spaces.remove_selected') }}
                        </flux:button>
                    </div>
                @endcan
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

        @can(PermissionName::SpacesAssignManagers)
            <flux:modal name="remove-spaces" class="w-full max-w-md">
                <form wire:submit="removeSpaces" class="flex flex-col gap-5">
                    <div>
                        <flux:heading size="lg">{{ __('users.managed_spaces.confirm_heading') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('users.managed_spaces.confirm_help', ['user' => $user->name]) }}</flux:text>
                    </div>

                    <ul class="list-disc space-y-1 ps-5 text-sm text-ink">
                        @foreach ($this->spacesPendingRemoval as $space)
                            <li wire:key="pending-{{ $space->id }}">{{ $space->code }} · {{ $space->name }}</li>
                        @endforeach
                    </ul>

                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('users.managed_spaces.cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="danger">{{ __('users.managed_spaces.confirm_submit') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endcan
    @endif

    @can('deactivate', $user)
        <flux:card class="flex flex-col gap-3" data-test="account-status">
            <flux:heading level="3">{{ __('users.account.heading') }}</flux:heading>

            @if ($user->isActive())
                <flux:text>{{ __('users.account.active_help') }}</flux:text>

                @if (auth()->user()->is($user))
                    <flux:text class="text-ink-muted">{{ __('users.account.errors.self') }}</flux:text>
                @else
                    <div>
                        <flux:modal.trigger name="deactivate-account">
                            <flux:button variant="danger" icon="no-symbol">{{ __('users.account.deactivate') }}</flux:button>
                        </flux:modal.trigger>
                    </div>
                @endif
            @else
                <flux:text>
                    {{ __('users.account.deactivated_on', [
                        'date' => $user->deactivated_at->format('d/m/Y H:i'),
                        'by' => $user->deactivatedBy?->name ?? __('users.account.unknown_actor'),
                    ]) }}
                </flux:text>

                <div>
                    <flux:modal.trigger name="reactivate-account">
                        <flux:button variant="primary" icon="arrow-path">{{ __('users.account.reactivate') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            @endif

            <flux:error name="account" />
        </flux:card>

        <flux:modal name="deactivate-account" class="w-full max-w-md">
            <form wire:submit="deactivate" class="flex flex-col gap-5">
                <div>
                    <flux:heading size="lg">{{ __('users.account.confirm_deactivate_heading') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('users.account.confirm_deactivate_help', ['user' => $user->name]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('users.account.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">{{ __('users.account.confirm_deactivate') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="reactivate-account" class="w-full max-w-md">
            <form wire:submit="reactivate" class="flex flex-col gap-5">
                <div>
                    <flux:heading size="lg">{{ __('users.account.confirm_reactivate_heading') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('users.account.confirm_reactivate_help', ['user' => $user->name]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('users.account.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('users.account.confirm_reactivate') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
