<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
});

test('the security page renders after confirming the password', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee(__('Enable 2FA'));
});

test('the security page asks for the password first', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));
});

test('an abandoned two-factor setup is discarded', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => null,
    ])->save();
    $this->actingAs($user);

    Livewire::test('pages::settings.security')->assertSet('twoFactorEnabled', false);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'two_factor_secret' => null, 'two_factor_recovery_codes' => null]);
});

test('the password can be updated', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $this->actingAs($user);

    Livewire::test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'una-contrasena-nueva')
        ->set('password_confirmation', 'una-contrasena-nueva')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('una-contrasena-nueva', $user->refresh()->password))->toBeTrue();
});

test('the current password is required to update it', function () {
    $this->actingAs(User::factory()->create(['password' => Hash::make('password')]));

    Livewire::test('pages::settings.security')
        ->set('current_password', 'wrong-password')
        ->set('password', 'una-contrasena-nueva')
        ->set('password_confirmation', 'una-contrasena-nueva')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);
});

test('passwords shorter than 12 characters are rejected', function () {
    $this->actingAs(User::factory()->create(['password' => Hash::make('password')]));

    Livewire::test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'corta123')
        ->set('password_confirmation', 'corta123')
        ->call('updatePassword')
        ->assertHasErrors(['password']);
});
