<?php

use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\Reservation;
use App\Models\Space;

/*
 * The ★ rule (Agent-DB.md §5): an Administrador changes only the spaces they manage and the assets and
 * reservations in them. The Superusuario changes everything; read-only roles change nothing.
 */

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->ownSpace = Space::factory()->managedBy($this->admin)->create();
    $this->otherSpace = Space::factory()->create();
});

test('an Administrador changes their own spaces only', function () {
    expect($this->admin->can('update', $this->ownSpace))->toBeTrue()
        ->and($this->admin->can('managePhotos', $this->ownSpace))->toBeTrue()
        ->and($this->admin->can('delete', $this->ownSpace))->toBeTrue()
        ->and($this->admin->can('update', $this->otherSpace))->toBeFalse()
        ->and($this->admin->can('delete', $this->otherSpace))->toBeFalse()
        ->and($this->admin->can('assignManagers', $this->ownSpace))->toBeFalse();
});

test('an Administrador changes the assets of their spaces only', function () {
    $ownAsset = Asset::factory()->inSpace($this->ownSpace)->create();
    $otherAsset = Asset::factory()->inSpace($this->otherSpace)->create();

    foreach (['update', 'delete', 'move', 'registerEvent', 'manageComponents', 'manageFiles'] as $ability) {
        expect($this->admin->can($ability, $ownAsset))->toBeTrue("{$ability} on own asset")
            ->and($this->admin->can($ability, $otherAsset))->toBeFalse("{$ability} on another space's asset");
    }

    expect($this->admin->can('create', [Asset::class, $this->ownSpace]))->toBeTrue()
        ->and($this->admin->can('create', [Asset::class, $this->otherSpace]))->toBeFalse()
        ->and($this->admin->can('create', Asset::class))->toBeTrue();
});

test('an Administrador manages maintenance of their spaces\' assets only', function () {
    $ownAsset = Asset::factory()->inSpace($this->ownSpace)->create();
    $otherAsset = Asset::factory()->inSpace($this->otherSpace)->create();

    expect($this->admin->can('create', [MaintenanceRecord::class, $ownAsset]))->toBeTrue()
        ->and($this->admin->can('create', [MaintenanceRecord::class, $otherAsset]))->toBeFalse();
});

test('an Administrador without spaces cannot register assets', function () {
    $newAdmin = userWithRole(SystemRole::Administrador);

    expect($newAdmin->can('create', Asset::class))->toBeFalse();
});

test('an Administrador changes any reservation of their spaces, and their own elsewhere', function () {
    $inOwnSpace = Reservation::factory()->create(['space_id' => $this->ownSpace->id]);
    $inOtherSpace = Reservation::factory()->create(['space_id' => $this->otherSpace->id]);
    $ownElsewhere = Reservation::factory()->create(['space_id' => $this->otherSpace->id, 'responsible_id' => $this->admin->id,
        'starts_at' => now()->addDays(2)->setTime(8, 0), 'ends_at' => now()->addDays(2)->setTime(9, 0)]);

    expect($this->admin->can('cancel', $inOwnSpace))->toBeTrue()
        ->and($this->admin->can('cancel', $inOtherSpace))->toBeFalse()
        ->and($this->admin->can('update', $ownElsewhere))->toBeTrue();
});

test('a Reservante changes only the reservations they made or are responsible for', function () {
    $reservante = userWithRole(SystemRole::Reservante);
    $own = Reservation::factory()->create(['responsible_id' => $reservante->id]);
    $someoneElses = Reservation::factory()->create();

    expect($reservante->can('update', $own))->toBeTrue()
        ->and($reservante->can('cancel', $own))->toBeTrue()
        ->and($reservante->can('cancel', $someoneElses))->toBeFalse()
        ->and($reservante->can('create', Reservation::class))->toBeTrue();
});

test('the Superusuario changes everything without managing spaces', function () {
    $super = userWithRole(SystemRole::Superusuario);
    $asset = Asset::factory()->inSpace($this->otherSpace)->create();

    expect($super->can('update', $this->otherSpace))->toBeTrue()
        ->and($super->can('assignManagers', $this->otherSpace))->toBeTrue()
        ->and($super->can('registerEvent', $asset))->toBeTrue();
});

test('read-only roles change nothing (RF_89: Visualizador)', function (SystemRole $role) {
    $user = userWithRole($role);
    $asset = Asset::factory()->inSpace($this->otherSpace)->create();

    expect($user->can('create', Asset::class))->toBeFalse()
        ->and($user->can('update', $asset))->toBeFalse()
        ->and($user->can('create', Space::class))->toBeFalse()
        ->and($user->can('update', $this->otherSpace))->toBeFalse()
        ->and($user->can('create', Reservation::class))->toBeFalse();
})->with([SystemRole::Visualizador, SystemRole::Reportes]);
