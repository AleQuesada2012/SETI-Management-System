<?php

use App\Actions\Users\DeactivateUser;
use App\Actions\Users\ReactivateUser;
use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * RF_88 Desactivación de cuenta, and RF_98 Reactivar cuenta de usuario (it closes with RF_88: its reactivation is
 * RF_88's fourth criterion).
 */

test('the Superusuario deactivates an account after confirming, and it is audited once', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $user = userWithRole(SystemRole::Reservante);

    Livewire::test('pages::users.show', ['user' => $user])
        ->assertSee(__('users.account.deactivate'))
        ->assertSee(__('users.account.confirm_deactivate_heading'))
        ->call('deactivate')
        ->assertHasNoErrors()
        ->assertSee(__('users.account.reactivate'));

    $user->refresh();
    expect($user->isActive())->toBeFalse()
        ->and($user->deactivated_by)->toBe($superuser->id);

    $entries = AuditLog::query()->where('entity_type', 'user')->where('entity_id', $user->id)->get();
    // One `deactivated` entry, and no generic `updated` one for the same change (User::auditExclude).
    expect($entries->where('action', AuditAction::Deactivated)->pluck('actor_id')->all())->toBe([$superuser->id])
        ->and($entries->where('action', AuditAction::Updated))->toBeEmpty();
});

test('deactivating ends the user\'s open sessions and they can no longer sign in', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $user = userWithRole(SystemRole::Reservante);
    DB::table('sessions')->insert([
        'id' => 'session-of-the-user', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest', 'payload' => '', 'last_activity' => now()->timestamp,
    ]);
    $rememberToken = $user->remember_token;

    app(DeactivateUser::class)->handle($superuser, $user);

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($user->fresh()->remember_token)->not->toBe($rememberToken);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.deactivated')]);
    $this->assertGuest();
});

test('a deactivated account keeps its history and audit trail', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $user = userWithRole(SystemRole::Administrador);
    Audit::record(AuditAction::Login, $user, actor: $user);
    $before = AuditLog::query()->where('actor_id', $user->id)->count();

    app(DeactivateUser::class)->handle($superuser, $user);

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and($user->fresh()->hasRole(SystemRole::Administrador->value))->toBeTrue()
        ->and(AuditLog::query()->where('actor_id', $user->id)->count())->toBe($before);
});

test('the deactivated accounts can be consulted and reactivated after confirming (RF_98)', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $user = User::factory()->deactivated()->withRole(SystemRole::Visualizador)->create(['name' => 'Jorge Ramírez']);
    User::factory()->withRole(SystemRole::Visualizador)->create(['name' => 'Ana Activa']);

    Livewire::test('pages::users.index')
        ->set('filters.account_status.values', ['deactivated'])
        ->assertSee('Jorge Ramírez')
        ->assertDontSee('Ana Activa');

    Livewire::test('pages::users.show', ['user' => $user])
        ->assertSee(__('users.account.confirm_reactivate_heading'))
        ->call('reactivate')
        ->assertHasNoErrors()
        ->assertSee(__('users.account.deactivate'));

    $user->refresh();
    expect($user->isActive())->toBeTrue()
        ->and($user->deactivated_by)->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::Reactivated)->where('entity_id', $user->id)->value('actor_id'))->toBe($superuser->id);
});

test('a reactivated account can sign in again (RF_98)', function () {
    $user = User::factory()->deactivated()->withRole(SystemRole::Visualizador)->create();

    app(ReactivateUser::class)->handle(userWithRole(SystemRole::Superusuario), $user);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user->fresh());
});

test('nobody deactivates their own account', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);

    Livewire::test('pages::users.show', ['user' => $superuser])
        ->assertDontSee(__('users.account.deactivate'))
        ->assertSee(__('users.account.errors.self'))
        ->call('deactivate')
        ->assertHasErrors(['account' => __('users.account.errors.self')]);

    expect($superuser->fresh()->isActive())->toBeTrue();
});

test('the last active Superusuario cannot be deactivated', function () {
    // A custom role may hold users.deactivate without being Superusuario (roles are built from the permission catalog).
    $accountManagers = Role::create(['name' => 'Gestión de cuentas', 'guard_name' => 'web']);
    $accountManagers->givePermissionTo([PermissionName::UsersView->value, PermissionName::UsersDeactivate->value]);
    $actor = User::factory()->create();
    $actor->assignRole($accountManagers);
    $onlySuperuser = userWithRole(SystemRole::Superusuario);
    User::factory()->deactivated()->withRole(SystemRole::Superusuario)->create();

    expect(fn () => app(DeactivateUser::class)->handle($actor, $onlySuperuser))
        ->toThrow(ValidationException::class, __('users.account.errors.last_superuser'));

    expect($onlySuperuser->fresh()->isActive())->toBeTrue();

    $anotherSuperuser = userWithRole(SystemRole::Superusuario);
    app(DeactivateUser::class)->handle($actor, $onlySuperuser);

    expect($onlySuperuser->fresh()->isActive())->toBeFalse()
        ->and($anotherSuperuser->fresh()->isActive())->toBeTrue();
});

test('an account cannot be deactivated or reactivated twice', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $user = User::factory()->deactivated()->create(['name' => 'Jorge Ramírez']);

    expect(fn () => app(DeactivateUser::class)->handle($superuser, $user))
        ->toThrow(ValidationException::class, __('users.account.errors.already_deactivated', ['user' => 'Jorge Ramírez']));

    app(ReactivateUser::class)->handle($superuser, $user);

    expect(fn () => app(ReactivateUser::class)->handle($superuser, $user->fresh()))
        ->toThrow(ValidationException::class, __('users.account.errors.already_active', ['user' => 'Jorge Ramírez']));
});

test('an Administrador cannot deactivate or reactivate accounts', function () {
    $admin = actingAsRole(SystemRole::Administrador);
    $user = userWithRole(SystemRole::Reservante);

    Livewire::test('pages::users.show', ['user' => $user])
        ->assertDontSee(__('users.account.heading'))
        ->call('deactivate')
        ->assertForbidden();

    expect(fn () => app(DeactivateUser::class)->handle($admin, $user))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ReactivateUser::class)->handle($admin, $user))->toThrow(AuthorizationException::class)
        ->and($user->fresh()->isActive())->toBeTrue();
});
