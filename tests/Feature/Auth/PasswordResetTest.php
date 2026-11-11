<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;

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

test('a deactivated account gets no link, and the form answers as for any email (RF_88)', function () {
    Notification::fake();
    $user = User::factory()->deactivated()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertNothingSent();
});

test('a link sent before the account was deactivated no longer changes its password', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);
    $user->forceFill(['deactivated_at' => now()])->save();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'una-contrasena-larga',
        'password_confirmation' => 'una-contrasena-larga',
    ])->assertSessionHasErrors(['email' => __('auth.deactivated')]);

    expect(Hash::check('una-contrasena-larga', $user->refresh()->password))->toBeFalse()
        ->and(AuditLog::query()->where('action', AuditAction::PasswordReset)->exists())->toBeFalse();
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

test('one address may ask for five links a minute', function () {
    Notification::fake();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.email'), ['email' => "nadie{$attempt}@tec.ac.cr"])->assertSessionHas('status');
    }

    $this->post(route('password.email'), ['email' => 'nadie6@tec.ac.cr'])->assertTooManyRequests();
});

test('the reset email is sent by the queue, with its token encrypted', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job) => $job->notification instanceof ResetPassword
        && $job->shouldBeEncrypted);
});
