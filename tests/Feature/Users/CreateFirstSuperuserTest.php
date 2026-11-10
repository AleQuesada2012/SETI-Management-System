<?php

use App\Enums\AuditAction;
use App\Enums\AuditChannel;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Notification;

// RF_91 on a fresh database: `users:create-first-superuser` is the only way to create the first Superusuario.

test('a fresh database gets its first Superusuario, invited by email and audited from the CLI', function () {
    Notification::fake();

    $this->artisan('users:create-first-superuser', ['email' => ' Directora@TEC.ac.cr ', 'name' => 'Marta Solís'])
        ->expectsOutputToContain('directora@tec.ac.cr')
        ->assertSuccessful();

    $user = User::query()->where('email', 'directora@tec.ac.cr')->sole();
    expect($user->name)->toBe('Marta Solís')
        ->and($user->password)->toBeNull()
        ->and($user->getRoleNames()->all())->toBe([SystemRole::Superusuario->value])
        ->and($user->mustSetUpTwoFactor())->toBeTrue();

    Notification::assertSentTo($user, UserInvitation::class);

    $entry = AuditLog::query()->where('action', AuditAction::RoleAssigned)->where('entity_id', $user->id)->sole();
    expect($entry->actor_id)->toBeNull()
        ->and($entry->channel)->toBe(AuditChannel::Cli)
        ->and($entry->new_values)->toBe(['role' => SystemRole::Superusuario->value]);
});

test('once a Superusuario exists it refuses, so it is never a second way in', function () {
    Notification::fake();
    userWithRole(SystemRole::Superusuario);

    $this->artisan('users:create-first-superuser', ['email' => 'otra@tec.ac.cr', 'name' => 'Otra Persona'])
        ->expectsOutputToContain(__('users.first_superuser.exists'))
        ->assertFailed();

    expect(User::query()->where('email', 'otra@tec.ac.cr')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

test('an invalid email is rejected', function () {
    $this->artisan('users:create-first-superuser', ['email' => 'no-es-un-correo', 'name' => 'Marta Solís'])->assertFailed();

    expect(User::query()->count())->toBe(0);
});
