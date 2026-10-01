<?php

use App\Actions\Users\RegisterUser;
use App\Models\Reporting\UserRow;
use App\Models\Role;
use App\Models\User;
use App\Queries\Users\UserDirectory;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Usuarios y roles (mockup "Usuarios y Roles"): list (RF_90), search (RF_95) and the role and account status filters.
 * The "Añadir usuario" modal registers a user and sends an invitation (RF_91).
 * Owner: users (@Rubiocai). List, search and filters come from App\Queries\Users\UserDirectory, the same query the
 * users report (RF_69) uses.
 */
new #[Title('users.index.title')] class extends Component
{
    use WithPagination;

    public const PER_PAGE = 15;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $account_status = '';

    /** A new search or filter starts again on the first page. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'account_status'], true)) {
            $this->resetPage();
        }
    }

    /** @var array{name: string, email: string, roles: list<int|string>} The "Añadir usuario" form (RF_91). */
    public array $newUser = ['name' => '', 'email' => '', 'roles' => []];

    public function registerUser(RegisterUser $registerUser): void
    {
        $this->authorize('create', User::class);

        try {
            $user = $registerUser->handle(auth()->user(), $this->newUser);
        } catch (ValidationException $exception) {
            // The Action validates `name`, `email`, `roles.0`…; the form fields live under `newUser.`.
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ['newUser.'.$key => $messages])->all(),
            );
        }

        $this->reset('newUser');
        unset($this->users);
        Flux::modal('register-user')->close();
        Flux::toast(variant: 'success', text: __('users.register.done', ['user' => $user->name, 'email' => $user->email]));
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'role', 'account_status');
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, UserRow> */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return UserDirectory::query([
            'search' => $this->search,
            'role' => $this->role,
            'account_status' => $this->account_status,
        ])->paginate(self::PER_PAGE);
    }

    /** @return Collection<int, string> */
    #[Computed]
    public function roles(): Collection
    {
        return Role::query()->orderBy('name')->pluck('name');
    }

    /** Roles offered by the "Añadir usuario" form. @return Collection<int, Role> */
    #[Computed]
    public function roleOptions(): Collection
    {
        return Role::query()->orderBy('name')->get(['id', 'name', 'description']);
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->role !== '' || $this->account_status !== '';
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-end gap-2">
        @can('viewAny', Role::class)
            <flux:button icon="shield-check" :href="route('roles.index')" wire:navigate>{{ __('users.index.roles') }}</flux:button>
        @endcan
        @can('create', User::class)
            <flux:modal.trigger name="register-user">
                <flux:button variant="primary" icon="plus">{{ __('users.index.create') }}</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    <div class="flex flex-col gap-3 md:flex-row md:items-end">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :label="__('users.index.search')"
            :placeholder="__('users.index.search_placeholder')"
            clearable
            class="md:flex-1"
        />

        <flux:select wire:model.live="role" :label="__('users.index.role')" class="md:max-w-52">
            <flux:select.option value="">{{ __('users.index.all_roles') }}</flux:select.option>
            @foreach ($this->roles as $roleName)
                <flux:select.option :value="$roleName">{{ $roleName }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="account_status" :label="__('users.index.account_status')" class="md:max-w-52">
            <flux:select.option value="">{{ __('users.index.all_statuses') }}</flux:select.option>
            @foreach (UserDirectory::ACCOUNT_STATUSES as $status)
                <flux:select.option :value="$status">{{ __('users.status.'.$status) }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($this->hasFilters())
            <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters">{{ __('users.index.clear') }}</flux:button>
        @endif
    </div>

    <flux:card class="overflow-x-auto">
        @if ($this->users->isEmpty())
            <flux:text>{{ $this->hasFilters() ? __('users.index.no_results') : __('users.index.empty') }}</flux:text>
        @else
            <flux:table :paginate="$this->users">
                <flux:table.columns>
                    <flux:table.column>{{ __('users.fields.name') }}</flux:table.column>
                    <flux:table.column>{{ __('users.fields.email') }}</flux:table.column>
                    <flux:table.column>{{ __('users.fields.roles') }}</flux:table.column>
                    <flux:table.column>{{ __('users.fields.status') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell variant="strong">
                                <a href="{{ route('users.show', $user->id) }}" class="hover:underline" wire:navigate>{{ $user->name }}</a>
                            </flux:table.cell>
                            <flux:table.cell>{{ $user->email }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @forelse (array_filter(explode(', ', (string) $user->roles)) as $roleName)
                                        <flux:badge size="sm" color="blue">{{ $roleName }}</flux:badge>
                                    @empty
                                        <span class="text-ink-muted">{{ __('users.fields.no_roles') }}</span>
                                    @endforelse
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    <flux:badge size="sm" :color="$user->account_status === 'active' ? 'green' : 'red'">
                                        {{ __('users.status.'.$user->account_status) }}
                                    </flux:badge>
                                    @if ($user->invitation_pending)
                                        <flux:badge size="sm" color="amber">{{ __('users.status.invitation_pending') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    @can('create', User::class)
        <flux:modal name="register-user" class="w-full max-w-md">
            <form wire:submit="registerUser" class="flex flex-col gap-5">
                <flux:error name="newUser.form" />
                <div>
                    <flux:heading size="lg">{{ __('users.register.heading') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('users.register.help') }}</flux:text>
                </div>

                <flux:input wire:model="newUser.name" :label="__('users.fields.name')" required autocomplete="off" />

                <flux:input wire:model="newUser.email" type="email" :label="__('users.fields.email')" required autocomplete="off" />

                <flux:checkbox.group wire:model="newUser.roles" :label="__('users.fields.roles')">
                    @foreach ($this->roleOptions as $option)
                        <flux:checkbox :value="$option->id" :label="$option->name" :description="$option->description" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="newUser.roles.*" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('users.register.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('users.register.submit') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</div>
