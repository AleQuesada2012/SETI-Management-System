<?php

use App\Actions\Spaces\AssignSpaceManager;
use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Space;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * RF_40 Asignar laboratorio a usuarios administradores.
 */

test('the Superusuario puts an Administrador in charge of several spaces at once', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $admin = userWithRole(SystemRole::Administrador);
    $lab = Space::factory()->create(['code' => 'LAB-101']);
    $office = Space::factory()->office()->create(['code' => 'OF-201']);

    Livewire::test('pages::users.show', ['user' => $admin])
        ->assertSee(__('spaces.managers.none_to_remove', ['user' => $admin->name]))
        ->set('spaces', [(string) $lab->id, (string) $office->id])
        ->call('assignSpaces')
        ->assertHasNoErrors()
        ->assertSet('spaces', [])
        ->assertSee('LAB-101')
        ->assertSee('OF-201');

    expect($admin->managedSpaces()->pluck('spaces.id')->sort()->values()->all())->toBe(collect([$lab->id, $office->id])->sort()->values()->all());

    $entries = AuditLog::query()->where('action', AuditAction::ManagerAssigned)->where('entity_type', 'space')->get();
    expect($entries->pluck('entity_id')->sort()->values()->all())->toBe(collect([$lab->id, $office->id])->sort()->values()->all())
        ->and($entries->pluck('new_values.manager')->unique()->all())->toBe([$admin->auditLabel()])
        ->and($entries->pluck('actor_id')->unique()->all())->toBe([$superuser->id]);
});

test('the assigned Administrador may manage those spaces right away', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $admin = userWithRole(SystemRole::Administrador);
    $space = Space::factory()->create();
    expect($admin->can('update', $space))->toBeFalse();

    app(AssignSpaceManager::class)->handle($superuser, $admin, [$space->id]);

    expect($admin->can('update', $space))->toBeTrue();
});

test('spaces the Administrador already manages are skipped, not duplicated', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $admin = userWithRole(SystemRole::Administrador);
    $managed = Space::factory()->managedBy($admin)->create();
    $new = Space::factory()->create();

    $assigned = app(AssignSpaceManager::class)->handle($superuser, $admin, [$managed->id, $new->id]);

    expect($assigned->modelKeys())->toBe([$new->id])
        ->and($admin->managedSpaces()->count())->toBe(2)
        ->and(AuditLog::query()->where('action', AuditAction::ManagerAssigned)->count())->toBe(1);
});

test('only holders of the Administrador role can be put in charge of spaces', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $reservante = userWithRole(SystemRole::Reservante);
    $space = Space::factory()->create();

    expect(fn () => app(AssignSpaceManager::class)->handle($superuser, $reservante, [$space->id]))
        ->toThrow(ValidationException::class);

    expect($reservante->managedSpaces()->exists())->toBeFalse();
});

test('a space must be chosen, and deleted spaces cannot be assigned', function () {
    actingAsRole(SystemRole::Superusuario);
    $admin = userWithRole(SystemRole::Administrador);
    $deleted = Space::factory()->create();
    $deleted->delete();

    Livewire::test('pages::users.show', ['user' => $admin])
        ->call('assignSpaces')
        ->assertHasErrors('spaces')
        ->set('spaces', [$deleted->id])
        ->call('assignSpaces')
        ->assertHasErrors('spaces.0');

    expect($admin->managedSpaces()->exists())->toBeFalse();
});

test('the spaces card is only for Administradores, and only the Superusuario can assign', function () {
    actingAsRole(SystemRole::Administrador);
    $otherAdmin = userWithRole(SystemRole::Administrador);
    Space::factory()->create();

    Livewire::test('pages::users.show', ['user' => $otherAdmin])
        ->assertSee(__('users.managed_spaces.heading'))
        ->assertDontSee(__('users.managed_spaces.assign'));

    Livewire::test('pages::users.show', ['user' => userWithRole(SystemRole::Reservante)])
        ->assertDontSee(__('users.managed_spaces.heading'));
});

test('an Administrador cannot assign spaces', function () {
    $admin = actingAsRole(SystemRole::Administrador);
    $space = Space::factory()->create();

    Livewire::test('pages::users.show', ['user' => $admin])
        ->set('spaces', [$space->id])
        ->call('assignSpaces')
        ->assertForbidden();

    expect(fn () => app(AssignSpaceManager::class)->handle($admin, $admin, [$space->id]))
        ->toThrow(AuthorizationException::class);

    expect($admin->managedSpaces()->exists())->toBeFalse();
});
