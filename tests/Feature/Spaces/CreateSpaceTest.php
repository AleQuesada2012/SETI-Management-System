<?php

// RF_32 · Crear espacio.

use App\Actions\Spaces\CreateSpace;
use App\Enums\SystemRole;
use App\Models\Building;
use App\Models\Space;
use App\Models\SpaceType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->type = SpaceType::query()->firstOrFail();
    $this->building = Building::factory()->create();
});

function fillSpaceForm(mixed $test, array $overrides = []): mixed
{
    $values = [
        'code' => 'LAB-501',
        'name' => 'Laboratorio 501',
        'space_type_id' => test()->type->id,
        'building_id' => test()->building->id,
        'floor' => '5',
        'capacity' => 30,
        ...$overrides,
    ];

    foreach ($values as $field => $value) {
        $test->set($field, $value);
    }

    return $test;
}

test('an Administrador creates a space, becomes its manager and is taken to it', function () {
    $admin = actingAsRole(SystemRole::Administrador);

    $component = fillSpaceForm(Livewire::test('spaces.create-space'))->call('save');

    $space = Space::query()->where('code', 'LAB-501')->firstOrFail();

    $component->assertHasNoErrors()->assertRedirect(route('spaces.show', $space));
    expect($space->name)->toBe('Laboratorio 501')
        ->and($space->capacity)->toBe(30)
        ->and($space->status->name)->toBe('Disponible')
        ->and($space->managers->pluck('id')->all())->toBe([$admin->id])
        ->and($admin->can('update', $space))->toBeTrue();

    $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'entity_id' => $space->id, 'actor_id' => $admin->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'manager_assigned', 'entity_id' => $space->id, 'actor_id' => $admin->id]);
});

test('the Superusuario creates a space without becoming its manager', function () {
    $superuser = userWithRole(SystemRole::Superusuario);

    $space = app(CreateSpace::class)->handle($superuser, [
        'code' => 'SALA-2', 'name' => 'Sala 2', 'space_type_id' => $this->type->id,
        'building_id' => $this->building->id, 'capacity' => 12,
    ]);

    expect($space->managers)->toBeEmpty();
});

test('name, location, capacity and type are required', function () {
    actingAsRole(SystemRole::Administrador);

    Livewire::test('spaces.create-space')
        ->call('save')
        ->assertHasErrors(['code' => 'required', 'name' => 'required', 'space_type_id' => 'required',
            'building_id' => 'required', 'capacity' => 'required']);

    expect(Space::query()->count())->toBe(0);
});

test('a blank name is rejected', function () {
    actingAsRole(SystemRole::Administrador);

    fillSpaceForm(Livewire::test('spaces.create-space'), ['name' => '   '])
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('a capacity that is not a positive whole number is rejected', function (mixed $capacity) {
    $admin = userWithRole(SystemRole::Administrador);

    expect(fn () => app(CreateSpace::class)->handle($admin, [
        'code' => 'LAB-9', 'name' => 'Laboratorio 9', 'space_type_id' => $this->type->id,
        'building_id' => $this->building->id, 'capacity' => $capacity,
    ]))->toThrow(ValidationException::class);
})->with([0, -3, 2.5, 'treinta']);

test('a name already in use is rejected with its Spanish message', function () {
    actingAsRole(SystemRole::Administrador);
    Space::factory()->create(['name' => 'Laboratorio 501']);

    fillSpaceForm(Livewire::test('spaces.create-space'), ['name' => 'laboratorio 501'])
        ->call('save')
        ->assertHasErrors('name')
        ->assertSee(__('db.spaces_name_unique'));
});

test('a Visualizador cannot create spaces', function () {
    $viewer = actingAsRole(SystemRole::Visualizador);

    fillSpaceForm(Livewire::test('spaces.create-space'))
        ->assertDontSee(__('spaces.index.create'))
        ->call('save')
        ->assertForbidden();

    expect(fn () => app(CreateSpace::class)->handle($viewer, ['name' => 'X']))
        ->toThrow(AuthorizationException::class);
    expect(Space::query()->count())->toBe(0);
});

test('the spaces list shows "Añadir espacio" only to those who may create', function () {
    actingAsRole(SystemRole::Administrador);
    $this->get(route('spaces.index'))->assertOk()->assertSee(__('spaces.index.create'));

    actingAsRole(SystemRole::Reservante);
    $this->get(route('spaces.index'))->assertOk()->assertDontSee(__('spaces.index.create'));
});
