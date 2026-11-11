<?php

// Temporary test data for the deployed site (docs/deployment.md, issue #111).

use App\Enums\AssetStatus;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\SampleDataSeeder;

test('sample data uses the people already registered and creates no accounts', function () {
    $super = userWithRole(SystemRole::Superusuario);
    $admin = userWithRole(SystemRole::Administrador);
    $reserver = userWithRole(SystemRole::Reservante);
    $users = User::query()->count();

    $this->seed(SampleDataSeeder::class);

    expect(User::query()->count())->toBe($users)
        ->and(Space::query()->count())->toBe(8)
        ->and(Asset::query()->count())->toBe(53)
        ->and(Asset::query()->whereNull('current_space_id')->exists())->toBeFalse()
        ->and(Reservation::query()->count())->toBe(11)
        ->and(Reservation::query()->pluck('created_by')->unique()->sort()->values()->all())
        ->toBe(collect([$super->id, $admin->id, $reserver->id])->sort()->values()->all());

    $space = Space::query()->where('code', 'LAB-101')->sole();
    expect($space->building->name)->toBe('Torre de Laboratorios')
        ->and($space->notes)->toBe(SampleDataSeeder::NOTE)
        ->and($space->managers->pluck('id')->all())->toBe([$admin->id])
        ->and(Asset::query()->where('status', AssetStatus::InMaintenance)->count())->toBe(1);

    $this->assertDatabaseHas('audit_logs', ['entity_type' => 'space', 'entity_id' => $space->id, 'action' => 'created']);
});

test('loading sample data a second time changes nothing', function () {
    userWithRole(SystemRole::Superusuario);
    $this->seed(SampleDataSeeder::class);

    $this->seed(SampleDataSeeder::class);

    expect(Space::query()->count())->toBe(8)
        ->and(Asset::query()->count())->toBe(53)
        ->and(Reservation::query()->count())->toBe(11);
});

test('with only a Superusuario, sample data falls back to them for every role', function () {
    $super = userWithRole(SystemRole::Superusuario);
    User::factory()->withRole(SystemRole::Administrador)->deactivated()->create();

    $this->seed(SampleDataSeeder::class);

    expect(Reservation::query()->pluck('created_by')->unique()->all())->toBe([$super->id])
        ->and(Space::query()->where('code', 'LAB-101')->sole()->managers->pluck('id')->all())->toBe([$super->id]);
});

test('sample data needs a Superusuario first', function () {
    $this->seed(SampleDataSeeder::class);
})->throws(RuntimeException::class, 'Create the first Superusuario');
