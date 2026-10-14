<?php

use App\Models\Reporting\UserRow;
use App\Models\Role;
use App\Queries\Users\UserDirectory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Roles (RF_100). Owner: users (@Rubiocai). Each role with its description and what it allows (roles.description and
 * permissions.description, seeded from App\Enums\SystemRole and App\Enums\PermissionName), and the users who hold
 * the selected role, from App\Queries\Users\UserDirectory (the same role filter as the users list).
 */
new #[Title('users.roles.title')] class extends Component
{
    use WithPagination;

    public const PER_PAGE = 15;

    /** Name of the role whose users are listed. */
    #[Url(as: 'rol', except: '')]
    public string $selected = '';

    /** Show the users of a role, or hide them when it was already selected. */
    public function select(int $roleId): void
    {
        $this->authorize('viewAny', Role::class);

        $roleName = (string) $this->roles->firstWhere('id', $roleId)?->name;

        $this->selected = $this->selected === $roleName ? '' : $roleName;
        $this->resetPage();
    }

    /** @return Collection<int, Role> */
    #[Computed]
    public function roles(): Collection
    {
        return Role::query()
            ->with(['permissions' => fn ($query) => $query->orderBy('name')])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    /** Users holding each role, by role id (reporting.user_roles). @return Collection<int, int> */
    #[Computed]
    public function userCounts(): Collection
    {
        return DB::table('reporting.user_roles')
            ->selectRaw('role_id, count(*) AS total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->map(fn ($total): int => (int) $total);
    }

    /** @return LengthAwarePaginator<int, UserRow>|null */
    #[Computed]
    public function holders(): ?LengthAwarePaginator
    {
        $role = $this->roles->firstWhere('name', $this->selected);

        if ($role === null) {
            return null;
        }

        return UserDirectory::query(['role' => ['values' => [$role->id]]])->paginate(self::PER_PAGE);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:text>{{ __('users.roles.intro') }}</flux:text>
        <flux:button icon="arrow-left" :href="route('users.index')" wire:navigate>{{ __('users.roles.back') }}</flux:button>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($this->roles as $role)
            <flux:card wire:key="role-{{ $role->id }}" @class(['flex flex-col gap-3', 'ring-2 ring-brand' => $selected === $role->name])>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading level="2" size="lg">{{ $role->name }}</flux:heading>
                        @if ($role->is_system)
                            <flux:badge size="sm" color="zinc">{{ __('users.roles.system') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text size="sm">{{ trans_choice('users.roles.holders_count', $this->userCounts->get($role->id, 0)) }}</flux:text>
                </div>

                @if ($role->description)
                    <flux:text>{{ $role->description }}</flux:text>
                @endif

                <details class="group">
                    <summary class="cursor-pointer text-sm font-medium text-brand-ink">
                        {{ trans_choice('users.roles.permissions_count', $role->permissions->count()) }}
                    </summary>
                    <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-ink">
                        @forelse ($role->permissions as $permission)
                            <li>{{ $permission->description ?? $permission->name }}</li>
                        @empty
                            <li class="list-none text-ink-muted">{{ __('users.roles.no_permissions') }}</li>
                        @endforelse
                    </ul>
                </details>

                <div>
                    <flux:button size="sm" :variant="$selected === $role->name ? 'primary' : 'filled'" icon="users" wire:click="select({{ $role->id }})">
                        {{ $selected === $role->name ? __('users.roles.hide_holders') : __('users.roles.show_holders') }}
                    </flux:button>
                </div>
            </flux:card>
        @endforeach
    </div>

    @if ($this->holders !== null)
        <flux:card class="overflow-x-auto">
            <flux:heading level="2" class="mb-3">{{ __('users.roles.holders_heading', ['role' => $selected]) }}</flux:heading>

            @if ($this->holders->isEmpty())
                <flux:text>{{ __('users.roles.no_holders') }}</flux:text>
            @else
                <flux:table :paginate="$this->holders">
                    <flux:table.columns>
                        <flux:table.column>{{ __('users.fields.name') }}</flux:table.column>
                        <flux:table.column>{{ __('users.fields.email') }}</flux:table.column>
                        <flux:table.column>{{ __('users.fields.status') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->holders as $holder)
                            <flux:table.row :key="$holder->id">
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('users.show', $holder->id) }}" class="hover:underline" wire:navigate>{{ $holder->name }}</a>
                                </flux:table.cell>
                                <flux:table.cell>{{ $holder->email }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$holder->account_status === 'active' ? 'green' : 'red'">
                                        {{ __('users.status.'.$holder->account_status) }}
                                    </flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif
</div>
