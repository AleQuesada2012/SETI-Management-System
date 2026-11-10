<?php

use App\Actions\Users\AssignRole;
use App\Actions\Users\ChangeRole;
use App\Actions\Users\ResetTwoFactor;
use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

// SECURITY.md · The Superusuario and the Administradores use two-factor authentication; the Superusuario can reset a
// user's when they lose their device.

/** @return array<string, mixed> */
function sessionRow(string $id, User $user): array
{
    return ['id' => $id, 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Pest', 'payload' => '', 'last_activity' => now()->timestamp];
}

test('a Superusuario or Administrador without two-factor authentication is sent to set it up', function (SystemRole $role) {
    $this->actingAs(User::factory()->withRole($role)->withoutTwoFactor()->create());

    foreach (['dashboard', 'assets.index', 'profile.edit', 'start'] as $route) {
        $this->get(route($route))->assertRedirect(route('security.edit'));
    }

    $this->getJson(route('assets.index'))->assertForbidden();

    // The way out stays open: confirm the password, reach the security page, sign out.
    $this->get(route('security.edit'))->assertRedirect(route('password.confirm'));
    $this->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))->assertOk()->assertSee('data-test="two-factor-required"', false);
    $this->post(route('logout'))->assertRedirect('/');
})->with([SystemRole::Superusuario, SystemRole::Administrador]);

test('once it is set up they use the application', function () {
    $this->actingAs(User::factory()->withRole(SystemRole::Administrador)->create());

    $this->get(route('dashboard'))->assertOk();
});

test('the other roles may leave two-factor authentication off', function (SystemRole $role) {
    $this->actingAs(User::factory()->withRole($role)->create());

    $this->get(route('spaces.index'))->assertOk();
})->with([SystemRole::Reportes, SystemRole::Visualizador, SystemRole::Reservante]);

test('the requirement can be turned off for local demo data', function () {
    config(['auth.two_factor_required' => false]);
    $this->actingAs(User::factory()->withRole(SystemRole::Administrador)->withoutTwoFactor()->create());

    $this->get(route('dashboard'))->assertOk();
});

test('a user who is given a role that needs it is signed out, so no open page keeps working', function (bool $swap) {
    $superuser = userWithRole(SystemRole::Superusuario);
    $user = userWithRole(SystemRole::Reservante);
    DB::table('sessions')->insert(sessionRow('open-page', $user));
    $administrador = Role::findByName(SystemRole::Administrador->value);

    $swap
        ? app(ChangeRole::class)->handle($superuser, $user, Role::findByName(SystemRole::Reservante->value), $administrador)
        : app(AssignRole::class)->handle($superuser, $user, $administrador);

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
})->with(['added (RF_85)' => false, 'swapped in (RF_87)' => true]);

test('the Superusuario resets a user\'s two-factor authentication after confirming: sessions end and it is audited without secrets', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $user = User::factory()->withRole(SystemRole::Administrador)->create(['name' => 'Laura Vargas']);
    DB::table('sessions')->insert(sessionRow('lost-phone', $user));

    Livewire::test('pages::users.show', ['user' => $user])
        ->assertSee('data-test="two-factor-reset"', false)
        ->call('resetTwoFactor')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', slots: ['text' => __('users.two_factor.done', ['user' => 'Laura Vargas'])])
        ->assertDontSee('data-test="two-factor-reset"', false);

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->mustSetUpTwoFactor())->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();

    $entry = AuditLog::query()->where('action', AuditAction::Updated)->where('entity_type', 'user')->where('entity_id', $user->id)->sole();
    expect($entry->actor_id)->toBe($superuser->id)
        ->and($entry->old_values)->toBe(['two_factor' => 'enabled'])
        ->and($entry->new_values)->toBe(['two_factor' => 'reset']);
});

test('there is nothing to reset for a user without it, and nobody resets their own here', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $plain = userWithRole(SystemRole::Reservante);

    Livewire::test('pages::users.show', ['user' => $plain])->assertDontSee('data-test="two-factor-reset"', false);
    Livewire::test('pages::users.show', ['user' => $superuser])->assertDontSee('data-test="two-factor-reset"', false);

    expect(fn () => app(ResetTwoFactor::class)->handle($superuser, $plain))
        ->toThrow(ValidationException::class, __('users.two_factor.errors.not_enabled', ['user' => $plain->name]))
        ->and(fn () => app(ResetTwoFactor::class)->handle($superuser, $superuser))
        ->toThrow(ValidationException::class, __('users.two_factor.errors.self'));
});

test('only the Superusuario resets two-factor authentication', function () {
    $admin = actingAsRole(SystemRole::Administrador);
    $user = User::factory()->withRole(SystemRole::Administrador)->create();

    Livewire::test('pages::users.show', ['user' => $user])
        ->assertDontSee('data-test="two-factor-reset"', false)
        ->call('resetTwoFactor')
        ->assertForbidden();

    expect(fn () => app(ResetTwoFactor::class)->handle($admin, $user))->toThrow(AuthorizationException::class)
        ->and($user->refresh()->two_factor_secret)->not->toBeNull();
});
