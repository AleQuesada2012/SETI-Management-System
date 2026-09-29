<?php

use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Detalle del usuario. Owner: users (@Rubiocai). Role changes are audited explicitly (Audit::record with
 * AuditAction::RoleAssigned / RoleRemoved) because pivot changes fire no model events.
 */
new #[Title('users.show.title')] class extends Component {
    public User $user;
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="lg" level="2">{{ $user->name }}</flux:heading>

    <x-module-placeholder owner="Sebastián Sánchez (@Rubiocai)" :requirements="[
        'RF_94' => 'Consultar información de usuario',
        'RF_85' => 'Asignar rol',
    ]" />
</div>
