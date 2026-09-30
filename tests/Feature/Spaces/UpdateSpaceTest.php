<?php

// RF_33 · Editar espacio; RF_36 · Actualizar la capacidad de un espacio.

use App\Actions\Spaces\UpdateSpace;
use App\Enums\SystemRole;
use App\Models\Building;
use App\Models\Space;
use App\Models\SpaceStatus;
use App\Models\SpaceType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create(['name' => 'Laboratorio 110', 'capacity' => 30]);
});

/** A valid edit of every field the form sends. */
function spaceEdit(array $overrides = []): array
{
    return [
        'code' => 'LAB-111',
        'name' => 'Laboratorio 111',
        'space_type_id' => SpaceType::query()->firstOrFail()->id,
        'building_id' => Building::factory()->create()->id,
        'floor' => '2',
        'location_detail' => 'Ala sur',
        'capacity' => 40,
        'notes' => null,
        ...$overrides,
    ];
}

test('a manager edits their space\'s name, location, capacity and type, and it is audited', function () {
    $this->actingAs($this->admin);
    $edit = spaceEdit();

    $component = Livewire::test('spaces.edit-space', ['space' => $this->space]);
    foreach ($edit as $field => $value) {
        $component->set($field, (string) $value);
    }
    $component->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('spaces.show', $this->space));

    $space = $this->space->refresh();
    expect($space->name)->toBe('Laboratorio 111')
        ->and($space->code)->toBe('LAB-111')
        ->and($space->capacity)->toBe(40)
        ->and($space->space_type_id)->toBe($edit['space_type_id'])
        ->and($space->building_id)->toBe($edit['building_id'])
        ->and($space->location_detail)->toBe('Ala sur');

    $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'entity_id' => $space->id, 'actor_id' => $this->admin->id]);
});

test('the new capacity shows on the space page', function () {
    $this->actingAs($this->admin);

    Livewire::test('spaces.edit-space', ['space' => $this->space])
        ->set('capacity', 12)
        ->call('save')
        ->assertHasNoErrors();

    $this->get(route('spaces.show', $this->space))->assertSee('12 personas');
});

test('the form starts with the stored values', function () {
    $this->actingAs($this->admin);

    Livewire::test('spaces.edit-space', ['space' => $this->space])
        ->assertSet('name', 'Laboratorio 110')
        ->assertSet('capacity', 30)
        ->assertSet('space_type_id', (string) $this->space->space_type_id);
});

test('a capacity that is not a positive whole number is rejected', function (mixed $capacity) {
    expect(fn () => app(UpdateSpace::class)->handle($this->admin, $this->space, spaceEdit(['capacity' => $capacity])))
        ->toThrow(ValidationException::class);

    expect($this->space->refresh()->capacity)->toBe(30);
})->with([0, -5, 2.5, 'cuarenta']);

test('required fields cannot be left empty', function () {
    $this->actingAs($this->admin);

    Livewire::test('spaces.edit-space', ['space' => $this->space])
        ->set('name', '  ')
        ->set('building_id', '')
        ->set('capacity', null)
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'building_id' => 'required', 'capacity' => 'required']);

    expect($this->space->refresh()->name)->toBe('Laboratorio 110');
});

test('a name already used by another space is rejected with its Spanish message', function () {
    $this->actingAs($this->admin);
    Space::factory()->create(['name' => 'Sala de reuniones']);

    Livewire::test('spaces.edit-space', ['space' => $this->space])
        ->set('name', 'SALA DE REUNIONES')
        ->call('save')
        ->assertHasErrors('name')
        ->assertSee(__('db.spaces_name_unique'));
});

test('editing does not change the status', function () {
    $other = SpaceStatus::query()->whereKeyNot($this->space->space_status_id)->firstOrFail();

    app(UpdateSpace::class)->handle($this->admin, $this->space, spaceEdit(['space_status_id' => $other->id]));

    expect($this->space->refresh()->space_status_id)->not->toBe($other->id);
});

test('the Superusuario edits any space', function () {
    $superuser = userWithRole(SystemRole::Superusuario);

    app(UpdateSpace::class)->handle($superuser, $this->space, spaceEdit(['capacity' => 18]));

    expect($this->space->refresh()->capacity)->toBe(18);
});

test('an Administrador cannot edit a space they do not manage', function () {
    $otherAdmin = actingAsRole(SystemRole::Administrador);

    Livewire::test('spaces.edit-space', ['space' => $this->space])
        ->assertDontSee(__('spaces.edit.button'))
        ->set('capacity', 99)
        ->call('save')
        ->assertForbidden();

    expect(fn () => app(UpdateSpace::class)->handle($otherAdmin, $this->space, spaceEdit()))
        ->toThrow(AuthorizationException::class);
    expect($this->space->refresh()->capacity)->toBe(30);
});

test('read-only roles do not see "Editar" on the space page', function () {
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('spaces.show', $this->space))->assertOk()->assertDontSee(__('spaces.edit.title'));

    $this->actingAs($this->admin);

    $this->get(route('spaces.show', $this->space))->assertOk()->assertSee(__('spaces.edit.title'));
});
