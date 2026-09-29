<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('the two-factor challenge sends guests to sign in', function () {
    $this->get(route('two-factor.login'))->assertRedirect(route('login'));
});

test('the two-factor challenge renders after a valid password', function () {
    Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->get(route('two-factor.login'))->assertOk();
});
