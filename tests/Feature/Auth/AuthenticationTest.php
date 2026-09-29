<?php

use App\Enums\AuditAction;
use App\Enums\AuditChannel;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// RF_00_1 · Inicio de sesión: email + password, audited (Q33).

test('guests are sent from / to the sign-in page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('the sign-in page renders in Spanish without "remember me"', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(__('Log in'))
        ->assertSee(__('security.login.submit'))
        ->assertDontSee('name="remember"', false);
});

test('users sign in with email and password and land on their start page', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('start', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('the email is case-insensitive', function () {
    $user = User::factory()->create(['email' => 'mario.solis@tec.ac.cr']);

    $this->post(route('login.store'), ['email' => '  Mario.Solis@TEC.ac.cr ', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('a wrong password is rejected with a Spanish message', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

test('a deactivated account cannot sign in (RF_88)', function () {
    $user = User::factory()->deactivated()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.deactivated')]);

    $this->assertGuest();
});

test('an invited user without a password yet cannot sign in', function () {
    $user = User::factory()->invited()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

test('a deactivated user loses an open session on the next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->forceFill(['deactivated_at' => now()])->save();

    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('sign-in, failed attempts and sign-out are audited', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

    $failed = AuditLog::query()->where('action', AuditAction::LoginFailed)->sole();
    expect($failed->actor_id)->toBeNull()
        ->and($failed->new_values)->toBe(['email' => $user->email])
        ->and($failed->channel)->toBe(AuditChannel::Web)
        ->and($failed->ip_address)->not->toBeNull();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $login = AuditLog::query()->where('action', AuditAction::Login)->sole();
    expect($login->actor_id)->toBe($user->id)
        ->and($login->entity_id)->toBe($user->id)
        ->and($user->refresh()->last_login_at)->not->toBeNull();

    $this->post(route('logout'))->assertRedirect('/');

    expect(AuditLog::query()->where('action', AuditAction::Logout)->where('actor_id', $user->id)->exists())->toBeTrue();
    $this->assertGuest();
});

test('passwords never reach the audit log', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'a-secret-attempt']);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $logged = AuditLog::query()->get()->map(fn (AuditLog $log) => json_encode([$log->old_values, $log->new_values]))->implode(' ');
    expect($logged)->not->toContain('a-secret-attempt')->not->toContain('$2y$');
});

test('there is no self-registration', function () {
    expect(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
});

test('users with two-factor enabled are sent to the challenge', function () {
    Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});
