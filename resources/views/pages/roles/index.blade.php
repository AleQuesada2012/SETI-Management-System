<?php

use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Roles (RF_100). Owner: users (@Rubiocai). Descriptions come from roles.description and permissions.description
 * (seeded from App\Enums\SystemRole and App\Enums\PermissionName).
 */
new #[Title('users.roles.title')] class extends Component {
    //
}; ?>

<div class="flex flex-col gap-6">
    <x-module-placeholder owner="Sebastián Sánchez (@Rubiocai)" :requirements="['RF_100' => 'Consultar roles disponibles']" />
</div>
