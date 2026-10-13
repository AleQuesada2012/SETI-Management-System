<?php

use App\Actions\Spaces\RemoveSpaceManager;
use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Space;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * RF_43 Eliminar asignación de laboratorio a usuarios administradores.
 */

test('the Superusuario removes several spaces from an Administrador after confirming', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $admin = userWithRole(SystemRole::Administrador);
    $lab = Space::factory()->managedBy($admin)->create(['code' => 'LAB-101']);
    $room = Space::factory()->managedBy($admin)->create(['code' => 'SAL-01']);
    $kept = Space::factory()->managedBy($admin)->create(['code' => 'LAB-202']);

    Livewire::test('pages::users.show', ['user' => $admin])
        ->set('spacesToRemove', [(string) $lab->id, (string) $room->id])
        ->call('confirmRemoval')
        ->assertHasNoErrors()
        ->assertSee(__('users.managed_spaces.confirm_heading'))
        ->call('removeSpaces')
        ->assertHasNoErrors()
        ->assertSet('spacesToRemove', []);

    expect($admin->managedSpaces()->pluck('spaces.id')->all())->toBe([$kept->id]);

    $entries = AuditLog::query()->where('action', AuditAction::ManagerRemoved)->get();
    expect($entries->pluck('entity_id')->sort()->values()->all())->toBe(collect([$lab->id, $room->id])->sort()->values()->all())
        ->and($entries->pluck('old_values.manager')->unique()->all())->toBe([$admin->auditLabel()])
        ->and($entries->pluck('actor_id')->unique()->all())->toBe([$superuser->id]);
});

test('a row button asks to confirm the removal of that space only', function () {
    actingAsRole(SystemRole::Superusuario);
    $admin = userWithRole(SystemRole::Administrador);
    $lab = Space::factory()->managedBy($admin)->create();
    $other = Space::factory()->managedBy($admin)->create();

    Livewire::test('pages::users.show', ['user' => $admin])
        ->set('spacesToRemove', [$other->id])
        ->call('confirmRemoval', $lab->id)
        ->assertSet('spacesToRemove', [$lab->id])
        ->call('removeSpaces');

    expect($admin->managedSpaces()->pluck('spaces.id')->all())->toBe([$other->id]);
});

test('nothing is removed until a space is chosen', function () {
    actingAsRole(SystemRole::Superusuario);
    $admin = userWithRole(SystemRole::Administrador);
    Space::factory()->managedBy($admin)->create();

    Livewire::test('pages::users.show', ['user' => $admin])
        ->call('confirmRemoval')
        ->assertHasErrors(['spacesToRemove' => __('users.managed_spaces.choose_to_remove')]);

    expect($admin->managedSpaces()->count())->toBe(1);
});

test('the removed Administrador can no longer manage that space', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $admin = userWithRole(SystemRole::Administrador);
    $space = Space::factory()->managedBy($admin)->create();
    expect($admin->can('update', $space))->toBeTrue();

    app(RemoveSpaceManager::class)->handle($superuser, $admin, [$space->id]);

    expect($admin->can('update', $space))->toBeFalse();
});

test('a user without assigned spaces gets an error, and the page says so', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $admin = userWithRole(SystemRole::Administrador);
    $message = __('spaces.managers.none_to_remove', ['user' => $admin->name]);

    expect(fn () => app(RemoveSpaceManager::class)->handle($superuser, $admin, [Space::factory()->create()->id]))
        ->toThrow(ValidationException::class, $message);

    Livewire::test('pages::users.show', ['user' => $admin])->assertSee($message);
});

test('a space the user does not manage cannot be removed from them', function () {
    actingAsRole(SystemRole::Superusuario);
    $admin = userWithRole(SystemRole::Administrador);
    $managed = Space::factory()->managedBy($admin)->create();
    $foreign = Space::factory()->create();

    Livewire::test('pages::users.show', ['user' => $admin])
        ->set('spacesToRemove', [$foreign->id])
        ->call('removeSpaces')
        ->assertHasErrors('spacesToRemove.0');

    expect($admin->managedSpaces()->pluck('spaces.id')->all())->toBe([$managed->id])
        ->and(AuditLog::query()->where('action', AuditAction::ManagerRemoved)->exists())->toBeFalse();
});

test('assignments of someone who lost the Administrador role are still listed so they can be removed', function () {
    actingAsRole(SystemRole::Superusuario);
    $former = userWithRole(SystemRole::Reservante);
    Space::factory()->managedBy($former)->create(['code' => 'LAB-909']);

    Livewire::test('pages::users.show', ['user' => $former])
        ->assertSee(__('users.managed_spaces.heading'))
        ->assertSee('LAB-909');
});

test('an Administrador cannot remove space managers', function () {
    $admin = actingAsRole(SystemRole::Administrador);
    $space = Space::factory()->managedBy($admin)->create();

    Livewire::test('pages::users.show', ['user' => $admin])
        ->assertDontSee(__('users.managed_spaces.remove_selected'))
        ->set('spacesToRemove', [$space->id])
        ->call('removeSpaces')
        ->assertForbidden();

    expect(fn () => app(RemoveSpaceManager::class)->handle($admin, $admin, [$space->id]))
        ->toThrow(AuthorizationException::class);

    expect($admin->managedSpaces()->count())->toBe(1);
});
