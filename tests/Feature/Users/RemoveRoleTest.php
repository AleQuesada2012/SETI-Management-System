<?php

use App\Actions\Users\RemoveRole;
use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * RF_86 Eliminar Rol.
 */

function systemRole(SystemRole $role): Role
{
    return Role::findByName($role->value);
}

test('the Superusuario removes a role after a confirmation that explains what is lost', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $user = userWithRole(SystemRole::Reservante, SystemRole::Visualizador);
    $reservante = systemRole(SystemRole::Reservante);

    Livewire::test('pages::users.show', ['user' => $user])
        ->call('confirmRoleRemoval', $reservante->id)
        ->assertSee(__('users.remove_role.confirm_heading', ['role' => 'Reservante']))
        // Lost: what only Reservante grants. Kept: what Visualizador also grants (spaces.view, reservations.view).
        ->assertSee(PermissionName::ReservationsCreate->description())
        ->assertSee(PermissionName::ReservationsManageOwn->description())
        ->assertDontSee(PermissionName::SpacesView->description())
        ->call('removeRole')
        ->assertHasNoErrors()
        ->assertSet('roleToRemove', null);

    expect($user->fresh()->getRoleNames()->all())->toBe(['Visualizador']);

    $entry = AuditLog::query()->where('action', AuditAction::RoleRemoved)->where('entity_id', $user->id)->sole();
    expect($entry->actor_id)->toBe($superuser->id)
        ->and($entry->old_values)->toBe(['role' => 'Reservante']);
});

test('the user loses the removed role\'s permissions at once', function () {
    $user = userWithRole(SystemRole::Reservante, SystemRole::Visualizador);
    expect($user->can(PermissionName::ReservationsCreate))->toBeTrue();

    app(RemoveRole::class)->handle(userWithRole(SystemRole::Superusuario), $user, systemRole(SystemRole::Reservante));

    expect($user->fresh()->can(PermissionName::ReservationsCreate))->toBeFalse()
        ->and($user->fresh()->can(PermissionName::AssetsView))->toBeTrue();
});

test('removing the Administrador role also removes the spaces in their charge, after listing them', function () {
    actingAsRole(SystemRole::Superusuario);
    $admin = userWithRole(SystemRole::Administrador, SystemRole::Reservante);
    $lab = Space::factory()->managedBy($admin)->create(['code' => 'LAB-316']);

    Livewire::test('pages::users.show', ['user' => $admin])
        ->call('confirmRoleRemoval', systemRole(SystemRole::Administrador)->id)
        ->assertSee(__('users.remove_role.loses_spaces'))
        ->assertSee('LAB-316')
        ->call('removeRole')
        ->assertHasNoErrors();

    expect($admin->fresh()->hasRole(SystemRole::Administrador->value))->toBeFalse()
        ->and($admin->managedSpaces()->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', AuditAction::ManagerRemoved)->where('entity_id', $lab->id)->exists())->toBeTrue();
});

test('a user always keeps at least one role', function () {
    actingAsRole(SystemRole::Superusuario);
    $user = userWithRole(SystemRole::Visualizador);

    Livewire::test('pages::users.show', ['user' => $user])
        ->assertSee(__('users.remove_role.only_role'))
        ->assertDontSee(__('users.remove_role.remove'))
        ->call('confirmRoleRemoval', systemRole(SystemRole::Visualizador)->id)
        ->call('removeRole')
        ->assertHasErrors(['roles' => __('users.remove_role.errors.last_role', ['user' => $user->name])]);

    expect($user->fresh()->getRoleNames()->all())->toBe(['Visualizador']);
});

test('the last active Superusuario keeps that role', function () {
    // A custom role may hold roles.assign without being Superusuario (roles are built from the permission catalog).
    $roleManagers = Role::create(['name' => 'Gestión de roles', 'guard_name' => 'web']);
    $roleManagers->givePermissionTo([PermissionName::UsersView->value, PermissionName::RolesAssign->value]);
    $actor = User::factory()->create();
    $actor->assignRole($roleManagers);
    $onlySuperuser = userWithRole(SystemRole::Superusuario, SystemRole::Visualizador);

    expect(fn () => app(RemoveRole::class)->handle($actor, $onlySuperuser, systemRole(SystemRole::Superusuario)))
        ->toThrow(ValidationException::class, __('users.remove_role.errors.last_superuser'));
    expect($onlySuperuser->fresh()->isSuperuser())->toBeTrue();

    userWithRole(SystemRole::Superusuario);
    app(RemoveRole::class)->handle($actor, $onlySuperuser, systemRole(SystemRole::Superusuario));

    expect($onlySuperuser->fresh()->isSuperuser())->toBeFalse();
});

test('a role the user does not hold cannot be removed', function () {
    $user = userWithRole(SystemRole::Visualizador, SystemRole::Reservante);

    expect(fn () => app(RemoveRole::class)->handle(userWithRole(SystemRole::Superusuario), $user, systemRole(SystemRole::Reportes)))
        ->toThrow(ValidationException::class, __('users.remove_role.errors.not_held', ['role' => 'Reportes']));
});

test('an Administrador cannot remove roles', function () {
    $admin = actingAsRole(SystemRole::Administrador);
    $user = userWithRole(SystemRole::Visualizador, SystemRole::Reservante);

    Livewire::test('pages::users.show', ['user' => $user])
        ->assertDontSee(__('users.remove_role.heading'))
        ->call('confirmRoleRemoval', systemRole(SystemRole::Reservante)->id)
        ->assertForbidden();

    expect(fn () => app(RemoveRole::class)->handle($admin, $user, systemRole(SystemRole::Reservante)))
        ->toThrow(AuthorizationException::class)
        ->and($user->fresh()->roles()->count())->toBe(2);
});
