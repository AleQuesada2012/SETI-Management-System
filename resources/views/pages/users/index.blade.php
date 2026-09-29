<?php

use App\Models\Role;
use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Usuarios y roles (mockup "Usuarios y Roles"). Owner: users (@Rubiocai).
 * List, search and filters come from App\Queries\Users\UserDirectory, the same query the users report (RF_69) uses.
 */
new #[Title('users.index.title')] class extends Component {
    //
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-end gap-2">
        @can('viewAny', Role::class)
            <flux:button icon="shield-check" :href="route('roles.index')" wire:navigate>{{ __('users.index.roles') }}</flux:button>
        @endcan
        @can('create', User::class)
            <flux:button variant="primary" icon="plus" disabled>{{ __('users.index.create') }}</flux:button>
        @endcan
    </div>

    <x-module-placeholder owner="Sebastián Sánchez (@Rubiocai)" :requirements="[
        'RF_90' => 'Listar usuarios',
        'RF_95' => 'Buscar usuarios',
        'RF_91' => 'Registrar usuarios',
    ]">
        Lista, búsqueda y filtros: <code>App\Queries\Users\UserDirectory</code>. Permisos: <code>App\Policies\UserPolicy</code>.
    </x-module-placeholder>
</div>
