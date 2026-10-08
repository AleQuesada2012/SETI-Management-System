<?php

use App\Actions\Users\AssignRole;
use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/*
 * RF_85 Asignar roles.
 */

test('the Superusuario assigns an existing role to a user from their page', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $user = userWithRole(SystemRole::Visualizador);

    Livewire::test('pages::users.show', ['user' => $user])
        ->assertSee(__('users.assign_role.heading'))
        ->set('roleId', Role::findByName(SystemRole::Reservante->value)->id)
        ->call('assignRole')
        ->assertHasNoErrors()
        ->assertSet('roleId', Role::findByName(SystemRole::Administrador->value)->id);

    expect($user->fresh()->getRoleNames()->sort()->values()->all())->toBe(['Reservante', 'Visualizador']);

    $entry = AuditLog::query()->where('action', AuditAction::RoleAssigned)->where('entity_id', $user->id)->sole();
    expect($entry->actor_id)->toBe($superuser->id)
        ->and($entry->new_values)->toBe(['role' => 'Reservante']);
});

test('the new role grants its permissions immediately', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $user = userWithRole(SystemRole::Visualizador);
    expect($user->can(PermissionName::ReservationsCreate))->toBeFalse();

    app(AssignRole::class)->handle($superuser, $user, Role::findByName(SystemRole::Reservante->value));

    expect($user->fresh()->can(PermissionName::ReservationsCreate))->toBeTrue();
});

test('a role the user already holds is rejected', function () {
    actingAsRole(SystemRole::Superusuario);
    $user = userWithRole(SystemRole::Visualizador);

    Livewire::test('pages::users.show', ['user' => $user])
        ->set('roleId', Role::findByName(SystemRole::Visualizador->value)->id)
        ->call('assignRole')
        ->assertHasErrors('roleId');

    expect(AuditLog::query()->where('action', AuditAction::RoleAssigned)->exists())->toBeFalse();
});

test('a role must be chosen', function () {
    actingAsRole(SystemRole::Superusuario);

    Livewire::test('pages::users.show', ['user' => userWithRole(SystemRole::Visualizador)])
        ->set('roleId', null)
        ->call('assignRole')
        ->assertHasErrors(['roleId' => 'required']);
});

test('an Administrador sees the user but cannot assign roles', function () {
    actingAsRole(SystemRole::Administrador);
    $user = userWithRole(SystemRole::Visualizador);

    Livewire::test('pages::users.show', ['user' => $user])
        ->assertDontSee(__('users.assign_role.heading'))
        ->set('roleId', Role::findByName(SystemRole::Superusuario->value)->id)
        ->call('assignRole')
        ->assertForbidden();

    expect($user->fresh()->hasRole(SystemRole::Superusuario->value))->toBeFalse();
});

test('the action refuses anyone without roles.assign', function () {
    $admin = userWithRole(SystemRole::Administrador);

    app(AssignRole::class)->handle($admin, userWithRole(SystemRole::Visualizador), Role::findByName(SystemRole::Superusuario->value));
})->throws(AuthorizationException::class);
