<?php

use App\Actions\Users\RegisterUser;
use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/* RF_91 / Q21: invitations are separate from ordinary password resets. */

test('the invitation email links to a Spanish password form and states its 48-hour lifetime', function () {
    $user = User::factory()->invited()->create();
    $notification = new UserInvitation('invitation-token');
    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe('Tu invitación a SETI Activos');
    expect((string) $mail->render())->toContain('48 horas')->not->toContain('password reset');
    $this->get($mail->actionUrl)
        ->assertOk()
        ->assertSee('Define una contraseña para activar tu cuenta.')
        ->assertSee(route('invitations.store'));
});

test('an invitation works until its 48-hour limit and expires afterwards', function (int $seconds, bool $valid) {
    $this->freezeTime();
    $user = User::factory()->invited()->create();
    $token = Password::broker('invitations')->createToken($user);
    expect(DB::table('user_invitation_tokens')->where('email', $user->email)->value('token'))->not->toBe($token);
    $this->travel($seconds)->seconds();

    $response = $this->post(route('invitations.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'una-contrasena-larga',
        'password_confirmation' => 'una-contrasena-larga',
    ]);

    if ($valid) {
        $response->assertSessionHasNoErrors()->assertRedirect(route('login'));
        expect(Hash::check('una-contrasena-larga', $user->refresh()->password))->toBeTrue();
        expect($user->email_verified_at)->not->toBeNull();
        $entry = AuditLog::query()->where('action', AuditAction::PasswordSet)->where('entity_id', $user->id)->sole();
        expect($entry->actor_id)->toBe($user->id);
        expect($entry->new_values)->toBeNull();
    } else {
        $response->assertSessionHasErrors(['email' => __('passwords.token')]);
        expect($user->refresh()->password)->toBeNull();
        expect($user->email_verified_at)->toBeNull();
    }
})->with([
    'one second before expiry' => [48 * 3600 - 1, true],
    'one second after expiry' => [48 * 3600 + 1, false],
]);

test('invitation and password reset tokens cannot be used in each other’s endpoints', function () {
    $user = User::factory()->invited()->create();
    $invitation = Password::broker('invitations')->createToken($user);
    $reset = Password::broker('users')->createToken($user);
    $payload = [
        'email' => $user->email,
        'password' => 'una-contrasena-larga',
        'password_confirmation' => 'una-contrasena-larga',
    ];

    $this->post(route('password.update'), ['token' => $invitation, ...$payload])
        ->assertSessionHasErrors(['email' => __('passwords.token')]);
    $this->post(route('invitations.store'), ['token' => $reset, ...$payload])
        ->assertSessionHasErrors(['email' => __('passwords.token')]);
    expect($user->refresh()->password)->toBeNull();

    $this->post(route('invitations.store'), ['token' => $invitation, ...$payload])->assertSessionHasNoErrors();
    expect(Hash::check('una-contrasena-larga', $user->refresh()->password))->toBeTrue();
});

test('ordinary password reset links still expire after 60 minutes', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $token = Password::broker('users')->createToken($user);
    $original = $user->password;
    $this->travel(3601)->seconds();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'una-contrasena-larga',
        'password_confirmation' => 'una-contrasena-larga',
    ])->assertSessionHasErrors(['email' => __('passwords.token')]);
    expect($user->refresh()->password)->toBe($original);
});

test('an accepted invitation is single-use', function () {
    $user = User::factory()->invited()->create();
    $token = Password::broker('invitations')->createToken($user);
    $payload = ['token' => $token, 'email' => $user->email, 'password' => 'primera-contrasena', 'password_confirmation' => 'primera-contrasena'];
    $this->post(route('invitations.store'), $payload)->assertSessionHasNoErrors();
    $this->post(route('invitations.store'), [...$payload, 'password' => 'segunda-contrasena', 'password_confirmation' => 'segunda-contrasena'])
        ->assertSessionHasErrors('email');
    expect(Hash::check('primera-contrasena', $user->refresh()->password))->toBeTrue();
    $this->assertDatabaseMissing('user_invitation_tokens', ['email' => $user->email]);
});

test('an invitation cannot change an established or deactivated account', function (bool $deactivated) {
    $user = $deactivated ? User::factory()->invited()->deactivated()->create() : User::factory()->create();
    $original = $user->password;
    $token = Password::broker('invitations')->createToken($user);
    $this->post(route('invitations.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'una-contrasena-larga',
        'password_confirmation' => 'una-contrasena-larga',
    ])->assertSessionHasErrors('email');
    expect($user->refresh()->password)->toBe($original);
})->with(['established' => false, 'deactivated' => true]);

test('invalid invitation passwords are rejected without consuming the token', function (string $password, string $confirmation) {
    $user = User::factory()->invited()->create();
    $token = Password::broker('invitations')->createToken($user);
    $this->post(route('invitations.store'), [
        'token' => $token, 'email' => $user->email, 'password' => $password, 'password_confirmation' => $confirmation,
    ])->assertSessionHasErrors('password');
    expect($user->refresh()->password)->toBeNull();
    expect(Password::broker('invitations')->tokenExists($user, $token))->toBeTrue();
})->with([
    'short password' => ['corta123', 'corta123'],
    'confirmation mismatch' => ['una-contrasena-larga', 'otra-contrasena-larga'],
]);

test('a rolled back registration sends no invitation', function () {
    Notification::fake();
    $actor = userWithRole(SystemRole::Superusuario);
    DB::beginTransaction();
    try {
        app(RegisterUser::class)->handle($actor, [
            'name' => 'Laura Vargas', 'email' => 'rollback@tec.ac.cr',
            'roles' => [Role::findByName(SystemRole::Reservante->value)->id],
        ]);
        Notification::assertNothingSent();
    } finally {
        DB::rollBack();
    }
    $this->assertDatabaseMissing('users', ['email' => 'rollback@tec.ac.cr']);
    Notification::assertNothingSent();
});
