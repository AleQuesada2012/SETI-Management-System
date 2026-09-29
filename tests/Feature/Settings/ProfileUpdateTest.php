<?php

use App\Models\User;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;

test('the profile page renders', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk();
});

test('users can change their display name, and it is audited', function () {
    $user = User::factory()->create(['name' => 'Nombre Viejo']);
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', 'Nombre Nuevo')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->name)->toBe('Nombre Nuevo');
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'updated',
        'entity_type' => 'user',
        'entity_id' => $user->id,
        'actor_id' => $user->id,
    ]);
});

test('the email cannot be changed from the profile', function () {
    $user = User::factory()->create(['email' => 'original@tec.ac.cr']);
    $this->actingAs($user);

    expect(fn () => Livewire::test('pages::settings.profile')->set('email', 'cambiado@tec.ac.cr'))
        ->toThrow(PublicPropertyNotFoundException::class);

    expect($user->refresh()->email)->toBe('original@tec.ac.cr');
});

test('accounts cannot be deleted by their owner', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertDontSee('Delete account')
        ->assertDontSee('Eliminar cuenta');
});
