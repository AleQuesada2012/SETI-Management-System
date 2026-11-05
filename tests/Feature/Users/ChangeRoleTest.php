<?php

use App\Actions\Users\ChangeRole;
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
 * RF_87 Modificar Rol.
 */

function roleNamed(SystemRole $role): Role
{
    return Role::findByName($role->value);
}

test('the Superusuario swaps a user\'s only role, seeing what is gained and lost', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $user = userWithRole(SystemRole::Visualizador);

    Livewire::test('pages::users.show', ['user' => $user])
        ->call('confirmRoleChange', roleNamed(SystemRole::Visualizador)->id)
        ->assertSee(__('users.change_role.heading', ['role' => 'Visualizador']))
        ->set('newRoleId', (string) roleNamed(SystemRole::Reservante)->id)
        ->assertSee(PermissionName::ReservationsCreate->description())
        ->assertSee(PermissionName::AssetsView->description())
        ->call('changeRole')
        ->assertHasNoErrors()
        ->assertSet('roleToChange', null);

    expect($user->fresh()->getRoleNames()->all())->toBe(['Reservante']);

    $entries = AuditLog::query()->where('entity_type', 'user')->where('entity_id', $user->id)
        ->whereIn('action', [AuditAction::RoleAssigned, AuditAction::RoleRemoved])->orderBy('id')->get();
    expect($entries->map(fn (AuditLog $e): array => [$e->action, $e->new_values['role'] ?? $e->old_values['role']])->all())
        ->toBe([[AuditAction::RoleAssigned, 'Reservante'], [AuditAction::RoleRemoved, 'Visualizador']])
        ->and($entries->pluck('actor_id')->unique()->all())->toBe([$superuser->id]);
});

test('the new role\'s permissions apply at once and the old ones stop', function () {
    $user = userWithRole(SystemRole::Visualizador);
    expect($user->can(PermissionName::AssetsView))->toBeTrue();

    app(ChangeRole::class)->handle(userWithRole(SystemRole::Superusuario), $user, roleNamed(SystemRole::Visualizador), roleNamed(SystemRole::Reservante));

    expect($user->fresh()->can(PermissionName::ReservationsCreate))->toBeTrue()
        ->and($user->fresh()->can(PermissionName::AssetsView))->toBeFalse();
});

test('if removing the old role fails, the new one is not kept either', function () {
    // A custom role may hold roles.assign without being Superusuario (roles are built from the permission catalog).
    $roleManagers = Role::create(['name' => 'Gestión de roles', 'guard_name' => 'web']);
    $roleManagers->givePermissionTo([PermissionName::UsersView->value, PermissionName::RolesAssign->value]);
    $actor = User::factory()->create();
    $actor->assignRole($roleManagers);
    $onlySuperuser = userWithRole(SystemRole::Superusuario);

    expect(fn () => app(ChangeRole::class)->handle($actor, $onlySuperuser, roleNamed(SystemRole::Superusuario), roleNamed(SystemRole::Visualizador)))
        ->toThrow(ValidationException::class, __('users.remove_role.errors.last_superuser'));

    expect($onlySuperuser->fresh()->getRoleNames()->all())->toBe(['Superusuario'])
        ->and(AuditLog::query()->where('action', AuditAction::RoleAssigned)->where('entity_id', $onlySuperuser->id)->exists())->toBeFalse();
});

test('leaving Administrador removes the spaces in their charge, which the window lists', function () {
    actingAsRole(SystemRole::Superusuario);
    $admin = userWithRole(SystemRole::Administrador);
    Space::factory()->managedBy($admin)->create(['code' => 'LAB-REDES', 'name' => 'Laboratorio de Redes']);

    Livewire::test('pages::users.show', ['user' => $admin])
        ->call('confirmRoleChange', roleNamed(SystemRole::Administrador)->id)
        ->set('newRoleId', (string) roleNamed(SystemRole::Reportes)->id)
        ->assertSee(__('users.remove_role.loses_spaces'))
        ->assertSee('LAB-REDES')
        ->call('changeRole')
        ->assertHasNoErrors();

    expect($admin->fresh()->getRoleNames()->all())->toBe(['Reportes'])
        ->and($admin->managedSpaces()->exists())->toBeFalse();
});

test('a new role must be chosen, different from the current one and not already held', function () {
    actingAsRole(SystemRole::Superusuario);
    $user = userWithRole(SystemRole::Visualizador, SystemRole::Reservante);
    $superuser = userWithRole(SystemRole::Superusuario);

    Livewire::test('pages::users.show', ['user' => $user])
        ->call('confirmRoleChange', roleNamed(SystemRole::Visualizador)->id)
        ->call('changeRole')
        ->assertHasErrors(['newRoleId' => 'required']);

    expect(fn () => app(ChangeRole::class)->handle($superuser, $user, roleNamed(SystemRole::Visualizador), roleNamed(SystemRole::Visualizador)))
        ->toThrow(ValidationException::class, __('users.change_role.errors.same_role'))
        ->and(fn () => app(ChangeRole::class)->handle($superuser, $user, roleNamed(SystemRole::Visualizador), roleNamed(SystemRole::Reservante)))
        ->toThrow(ValidationException::class, __('users.assign_role.already_held', ['role' => 'Reservante']));

    expect($user->fresh()->getRoleNames()->sort()->values()->all())->toBe(['Reservante', 'Visualizador']);
});

test('the new-role select starts on a selectable empty option', function () {
    actingAsRole(SystemRole::Superusuario);
    $user = userWithRole(SystemRole::Visualizador);

    $html = Livewire::test('pages::users.show', ['user' => $user])
        ->call('confirmRoleChange', roleNamed(SystemRole::Visualizador)->id)
        ->html();

    expect($html)->toMatch('/<option[^>]*value=""[^>]*>\s*'.preg_quote(__('users.change_role.choose'), '/').'/')
        ->not->toMatch('/<option[^>]*\bdisabled\b/');
});

test('only the Superusuario changes roles: anyone else gets an authorization error', function () {
    $admin = actingAsRole(SystemRole::Administrador);
    $user = userWithRole(SystemRole::Visualizador);

    Livewire::test('pages::users.show', ['user' => $user])
        ->assertDontSee(__('users.change_role.change'))
        ->call('confirmRoleChange', roleNamed(SystemRole::Visualizador)->id)
        ->assertForbidden();

    expect(fn () => app(ChangeRole::class)->handle($admin, $user, roleNamed(SystemRole::Visualizador), roleNamed(SystemRole::Reservante)))
        ->toThrow(AuthorizationException::class)
        ->and($user->fresh()->getRoleNames()->all())->toBe(['Visualizador']);
});
