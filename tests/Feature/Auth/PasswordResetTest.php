<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

// The password broker serves both "forgot password" and invitations (Q21).

test('the reset link screen renders', function () {
    $this->get(route('password.request'))->assertOk();
});

test('a reset link can be requested', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('the reset screen renders', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) {
        $this->get(route('password.reset', $notification->token))->assertOk();

        return true;
    });
});

test('the password can be reset with a valid token, and it is audited', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'una-contrasena-larga',
            'password_confirmation' => 'una-contrasena-larga',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login', absolute: false));

        return true;
    });

    expect(Hash::check('una-contrasena-larga', $user->refresh()->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::PasswordReset)->where('entity_id', $user->id)->exists())->toBeTrue();
});

test('passwords shorter than 12 characters are rejected', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'corta123',
            'password_confirmation' => 'corta123',
        ])->assertSessionHasErrors('password');

        return true;
    });
});

test('asking for a link answers the same whether or not the email is registered', function () {
    Notification::fake();
    $user = User::factory()->create();

    foreach ([$user->email, 'nadie@tec.ac.cr', $user->email] as $email) {
        // The third request is throttled by the broker, which would also reveal that the account exists.
        $this->post(route('password.email'), ['email' => $email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));
    }

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
});

test('resetting the password ends every open session of the user', function () {
    Notification::fake();
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        'id' => 'session-of-a-thief', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest', 'payload' => '', 'last_activity' => now()->timestamp,
    ]);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'una-contrasena-larga',
            'password_confirmation' => 'una-contrasena-larga',
        ])->assertSessionHasNoErrors();

        return true;
    });

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
});
