<?php

use App\Actions\Users\RegisterUser;
use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * RF_91 Registrar usuarios.
 */

test('the Superusuario registers a user with name, email and roles, and the invitation is emailed', function () {
    Notification::fake();
    $superuser = actingAsRole(SystemRole::Superusuario);
    $reservante = Role::findByName(SystemRole::Reservante->value);
    $visualizador = Role::findByName(SystemRole::Visualizador->value);

    Livewire::test('pages::users.index')
        ->set('newUser.name', 'Laura Vargas')
        ->set('newUser.email', ' LVargas@TEC.ac.cr ')
        ->set('newUser.roles', [(string) $reservante->id, (string) $visualizador->id])
        ->call('registerUser')
        ->assertHasNoErrors()
        ->assertSet('newUser.name', '')
        ->assertSee('Laura Vargas');

    $user = User::query()->where('email', 'lvargas@tec.ac.cr')->sole();
    expect($user->name)->toBe('Laura Vargas')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->getRoleNames()->sort()->values()->all())->toBe(['Reservante', 'Visualizador']);

    Notification::assertSentTo($user, UserInvitation::class);

    $entries = AuditLog::query()->where('entity_id', $user->id)->where('entity_type', 'user')->get();
    expect($entries->pluck('action')->all())->toContain(AuditAction::Created)
        ->and($entries->where('action', AuditAction::RoleAssigned)->pluck('new_values.role')->sort()->values()->all())->toBe(['Reservante', 'Visualizador'])
        ->and($entries->every(fn (AuditLog $entry): bool => $entry->actor_id === $superuser->id))->toBeTrue();
});

test('following the invitation link sets the password and verifies the email', function () {
    Notification::fake();
    $superuser = userWithRole(SystemRole::Superusuario);

    $user = app(RegisterUser::class)->handle($superuser, [
        'name' => 'Laura Vargas',
        'email' => 'lvargas@tec.ac.cr',
        'roles' => [Role::findByName(SystemRole::Reservante->value)->id],
    ]);

    $token = null;
    Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->post(route('invitations.store'), [
        'token' => $token,
        'email' => 'lvargas@tec.ac.cr',
        'password' => 'una-contraseña-larga',
        'password_confirmation' => 'una-contraseña-larga',
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->password)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
});

test('an email that is already registered is rejected, whatever its case', function () {
    Notification::fake();
    actingAsRole(SystemRole::Superusuario);
    User::factory()->create(['email' => 'lvargas@tec.ac.cr']);

    Livewire::test('pages::users.index')
        ->set('newUser.name', 'Laura Vargas')
        ->set('newUser.email', 'LVARGAS@tec.ac.cr')
        ->set('newUser.roles', [Role::findByName(SystemRole::Reservante->value)->id])
        ->call('registerUser')
        ->assertHasErrors(['newUser.email' => __('validation.unique', ['attribute' => 'correo'])]);

    expect(User::query()->where('email', 'lvargas@tec.ac.cr')->count())->toBe(1);
    Notification::assertNothingSent();
});

test('missing fields and a malformed email are reported', function () {
    Notification::fake();
    actingAsRole(SystemRole::Superusuario);

    Livewire::test('pages::users.index')
        ->call('registerUser')
        ->assertHasErrors(['newUser.name', 'newUser.email', 'newUser.roles'])
        ->set('newUser.email', 'lvargas@tec')
        ->call('registerUser')
        ->assertHasErrors(['newUser.email']);

    Notification::assertNothingSent();
});

test('an Administrador cannot register users', function () {
    actingAsRole(SystemRole::Administrador);

    Livewire::test('pages::users.index')
        ->assertDontSee(__('users.index.create'))
        ->set('newUser.name', 'Laura Vargas')
        ->set('newUser.email', 'lvargas@tec.ac.cr')
        ->set('newUser.roles', [Role::findByName(SystemRole::Superusuario->value)->id])
        ->call('registerUser')
        ->assertForbidden();

    expect(User::query()->where('email', 'lvargas@tec.ac.cr')->exists())->toBeFalse();
});

test('the action refuses anyone without users.create', function () {
    app(RegisterUser::class)->handle(userWithRole(SystemRole::Administrador), [
        'name' => 'Laura Vargas',
        'email' => 'lvargas@tec.ac.cr',
        'roles' => [Role::findByName(SystemRole::Reservante->value)->id],
    ]);
})->throws(AuthorizationException::class);

test('a concurrent duplicate email is reported as a Spanish field error without partial registration', function () {
    Notification::fake();
    actingAsRole(SystemRole::Superusuario);
    $dispatcher = User::getEventDispatcher();
    User::setEventDispatcher(clone $dispatcher);
    User::creating(function (User $user): void {
        if ($user->email === 'race@tec.ac.cr') {
            DB::table('users')->insert(['name' => 'Concurrent registration', 'email' => $user->email]);
        }
    });

    try {
        Livewire::test('pages::users.index')
            ->set('newUser.name', 'Laura Vargas')
            ->set('newUser.email', 'race@tec.ac.cr')
            ->set('newUser.roles', [Role::findByName(SystemRole::Reservante->value)->id])
            ->call('registerUser')
            ->assertHasErrors(['newUser.email' => __('db.users_email_unique')]);
    } finally {
        User::setEventDispatcher($dispatcher);
    }

    $this->assertDatabaseMissing('users', ['email' => 'race@tec.ac.cr']);
    Notification::assertNothingSent();
});
