<?php

use App\Actions\Users\UpdateUser;
use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * RF_93 Editar información de usuario.
 */

beforeEach(function () {
    Notification::fake();
});

test('the Superusuario opens a user from their page and edits the name, which is audited', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $user = User::factory()->withRole(SystemRole::Visualizador)->create(['name' => 'Paula Jimenez', 'email' => 'paula@seti.test']);

    $this->get(route('users.show', $user))->assertSee(route('users.edit', $user), escape: false);
    $this->get(route('users.edit', $user))->assertOk()->assertSee('paula@seti.test');

    Livewire::test('pages::users.edit', ['user' => $user])
        ->set('name', '  Paula Jiménez  ')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Paula Jiménez')
        ->and($user->fresh()->email_verified_at)->not->toBeNull();

    $entry = AuditLog::query()->where('action', AuditAction::Updated)->where('entity_type', 'user')->where('entity_id', $user->id)->sole();
    expect($entry->actor_id)->toBe($superuser->id)
        ->and($entry->old_values)->toBe(['name' => 'Paula Jimenez'])
        ->and($entry->new_values)->toBe(['name' => 'Paula Jiménez']);

    Notification::assertNothingSent();
});

test('a new email for an active account must be verified: it is marked unverified and a link goes to it', function () {
    actingAsRole(SystemRole::Superusuario);
    $user = User::factory()->create(['email' => 'old@seti.test']);
    DB::table('password_reset_tokens')->insert(['email' => 'old@seti.test', 'token' => 'x', 'created_at' => now()]);

    Livewire::test('pages::users.edit', ['user' => $user])
        ->set('email', '  New.Address@SETI.test ')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->email)->toBe('new.address@seti.test')
        ->and($user->email_verified_at)->toBeNull()
        ->and(DB::table('password_reset_tokens')->where('email', 'old@seti.test')->exists())->toBeFalse();

    // In Spanish, like every email of the app (lang/es.json).
    Notification::assertSentTo($user, VerifyEmail::class, fn (VerifyEmail $mail): bool => $mail->toMail($user)->subject === 'Verifica tu correo electrónico');
    Notification::assertNotSentTo($user, UserInvitation::class);
});

test('a new email ends the user\'s open sessions; the Superusuario editing their own keeps theirs', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $user = User::factory()->create(['email' => 'old@seti.test']);
    $session = fn (string $id, User $owner): array => [
        'id' => $id, 'user_id' => $owner->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Pest', 'payload' => '',
        'last_activity' => now()->timestamp,
    ];
    DB::table('sessions')->insert([$session('session-of-the-user', $user), $session(session()->getId(), $superuser), $session('superuser-elsewhere', $superuser)]);

    app(UpdateUser::class)->handle($superuser, $user, ['name' => $user->name, 'email' => 'new@seti.test']);
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();

    app(UpdateUser::class)->handle($superuser, $user, ['name' => 'Otro Nombre', 'email' => 'new@seti.test']);
    app(UpdateUser::class)->handle($superuser, $superuser, ['name' => $superuser->name, 'email' => 'root.nuevo@seti.test']);
    expect(DB::table('sessions')->where('user_id', $superuser->id)->pluck('id')->all())->toBe([session()->getId()]);
});

test('a new email for a pending invitation gets the invitation again', function () {
    $user = User::factory()->invited()->create(['email' => 'typo@seti.tset']);

    app(UpdateUser::class)->handle(userWithRole(SystemRole::Superusuario), $user, ['name' => $user->name, 'email' => 'fixed@seti.test']);

    Notification::assertSentTo($user, UserInvitation::class);
    Notification::assertNotSentTo($user, VerifyEmail::class);
    expect(DB::table('user_invitation_tokens')->where('email', 'fixed@seti.test')->exists())->toBeTrue();
});

test('changed data is validated before it is stored', function (array $input, string $field) {
    actingAsRole(SystemRole::Superusuario);
    User::factory()->create(['email' => 'taken@seti.test']);
    $user = User::factory()->create(['name' => 'Original', 'email' => 'original@seti.test']);

    Livewire::test('pages::users.edit', ['user' => $user])
        ->set($input)
        ->call('save')
        ->assertHasErrors($field);

    expect($user->fresh()->only('name', 'email'))->toBe(['name' => 'Original', 'email' => 'original@seti.test']);
    Notification::assertNothingSent();
})->with([
    'empty name' => [['name' => '   '], 'name'],
    'too long name' => [['name' => str_repeat('a', 151)], 'name'],
    'empty email' => [['email' => ''], 'email'],
    'malformed email' => [['email' => 'nobody@nowhere'], 'email'],
    'email of another user, any case' => [['email' => 'TAKEN@seti.test'], 'email'],
]);

test('keeping the same email sends nothing and keeps it verified', function () {
    actingAsRole(SystemRole::Superusuario);
    $user = User::factory()->create(['email' => 'same@seti.test']);

    Livewire::test('pages::users.edit', ['user' => $user])->set('email', 'SAME@seti.test')->call('save')->assertHasNoErrors();

    expect($user->fresh()->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
});

test('only the Superusuario edits users', function () {
    $admin = actingAsRole(SystemRole::Administrador);
    $user = User::factory()->create(['name' => 'Original']);

    $this->get(route('users.show', $user))->assertOk()->assertDontSee(route('users.edit', $user), escape: false);
    $this->get(route('users.edit', $user))->assertForbidden();

    expect(fn () => app(UpdateUser::class)->handle($admin, $user, ['name' => 'Cambiado', 'email' => $user->email]))
        ->toThrow(AuthorizationException::class)
        ->and($user->fresh()->name)->toBe('Original');
});
