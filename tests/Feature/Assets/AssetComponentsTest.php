<?php

// RF_12 · Registrar componentes del equipo, and RF_31 · Consultar componentes de un activo, which close together.

use App\Actions\Assets\AddAssetComponent;
use App\Actions\Assets\RemoveAssetComponent;
use App\Actions\Assets\UpdateAssetComponent;
use App\Enums\AssetStatus;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AssetComponent;
use App\Models\Space;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create();
    $this->asset = Asset::factory()->inSpace($this->space)->create();
});

/** @param  array<string, mixed>  $attributes */
function componentOf(Asset $asset, array $attributes = []): AssetComponent
{
    return $asset->components()->create($attributes + ['name' => 'Memoria RAM', 'specs' => '8 GB DDR4', 'quantity' => 2]);
}

test('an Administrador adds a component to an asset of their space and sees it on the asset page', function () {
    $this->actingAs($this->admin);

    Livewire::test('assets.asset-components', ['asset' => $this->asset])
        ->call('add')
        ->set('name', ' Disco SSD ')
        ->set('specs', '512 GB NVMe')
        ->set('serial_number', 'S4EVNX0R')
        ->set('quantity', '1')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', slots: ['text' => __('assets.components.added', ['component' => 'Disco SSD'])])
        ->assertSee('Disco SSD');

    $component = AssetComponent::query()->where('asset_id', $this->asset->id)->sole();

    expect($component->only(['name', 'specs', 'serial_number', 'quantity', 'notes']))->toBe([
        'name' => 'Disco SSD', 'specs' => '512 GB NVMe', 'serial_number' => 'S4EVNX0R', 'quantity' => 1, 'notes' => null,
    ]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'entity_type' => 'asset_component', 'entity_id' => $component->id, 'actor_id' => $this->admin->id]);

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSeeInOrder([__('assets.components.title'), 'Disco SSD', '512 GB NVMe', 'S4EVNX0R'])
        ->assertSee('data-test="add-component-button"', false);
});

test('an Administrador modifies a component, starting from its stored values', function () {
    $this->actingAs($this->admin);
    $component = componentOf($this->asset);

    Livewire::test('assets.asset-components', ['asset' => $this->asset])
        ->call('edit', $component->id)
        ->assertSet('name', 'Memoria RAM')
        ->assertSet('specs', '8 GB DDR4')
        ->assertSet('quantity', '2')
        ->set('specs', '16 GB DDR4')
        ->set('quantity', '4')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', slots: ['text' => __('assets.components.updated', ['component' => 'Memoria RAM'])]);

    expect($component->refresh()->only(['specs', 'quantity']))->toBe(['specs' => '16 GB DDR4', 'quantity' => 4])
        ->and(AssetComponent::query()->count())->toBe(1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'entity_type' => 'asset_component', 'entity_id' => $component->id]);
});

test('an Administrador deletes a component after confirming, and the audit log keeps its values', function () {
    $this->actingAs($this->admin);
    $component = componentOf($this->asset);

    Livewire::test('assets.asset-components', ['asset' => $this->asset])
        ->call('confirmRemove', $component->id)
        ->assertSet('removingId', $component->id)
        ->assertSee(__('assets.components.remove_title', ['component' => 'Memoria RAM']))
        ->call('remove')
        ->assertDispatched('toast-show', slots: ['text' => __('assets.components.removed', ['component' => 'Memoria RAM'])])
        ->assertSee(__('assets.components.empty'));

    expect(AssetComponent::query()->find($component->id))->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'deleted', 'entity_type' => 'asset_component', 'entity_id' => $component->id]);
});

test('anyone who may view the asset consults its components without the buttons to change them', function () {
    componentOf($this->asset);
    componentOf($this->asset, ['name' => 'Fuente de poder', 'specs' => null, 'serial_number' => 'PSU-1', 'quantity' => 1]);
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSeeInOrder([__('assets.components.title'), 'Fuente de poder', 'PSU-1', 'Memoria RAM', '8 GB DDR4'])
        ->assertDontSee('data-test="add-component-button"', false)
        ->assertDontSee('data-test="edit-component-button"', false)
        ->assertDontSee('data-test="remove-component-button"', false);
});

test('an asset without components says it has none', function () {
    $this->actingAs($this->admin);

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSee(__('assets.components.empty'));
});

test('the Superusuario manages the components of an asset of any space', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $other = Asset::factory()->inSpace(Space::factory()->create())->create();

    $component = app(AddAssetComponent::class)->handle($superuser, $other, ['name' => 'Tarjeta de red', 'quantity' => 1]);
    app(UpdateAssetComponent::class)->handle($superuser, $component, ['name' => 'Tarjeta de red', 'quantity' => 2]);
    app(RemoveAssetComponent::class)->handle($superuser, $component);

    expect($other->components()->count())->toBe(0);
});

test('an Administrador who does not manage the asset\'s space only consults its components', function () {
    $this->actingAs($this->admin);
    $other = Asset::factory()->inSpace(Space::factory()->create())->create();
    $component = componentOf($other);

    $this->get(route('assets.show', $other))
        ->assertOk()
        ->assertSee('Memoria RAM')
        ->assertDontSee('data-test="add-component-button"', false)
        ->assertDontSee('data-test="edit-component-button"', false);

    Livewire::test('assets.asset-components', ['asset' => $other])->call('add')->assertForbidden();

    expect(fn () => app(AddAssetComponent::class)->handle($this->admin, $other, ['name' => 'Disco', 'quantity' => 1]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateAssetComponent::class)->handle($this->admin, $component, ['name' => 'Disco', 'quantity' => 1]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RemoveAssetComponent::class)->handle($this->admin, $component))->toThrow(AuthorizationException::class)
        ->and($component->refresh()->name)->toBe('Memoria RAM');
});

test('roles without assets.manage_components cannot change components', function (SystemRole $role) {
    $user = actingAsRole($role);
    $component = componentOf($this->asset);

    Livewire::test('assets.asset-components', ['asset' => $this->asset])->call('edit', $component->id)->assertForbidden();

    expect(fn () => app(AddAssetComponent::class)->handle($user, $this->asset, ['name' => 'Disco', 'quantity' => 1]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RemoveAssetComponent::class)->handle($user, $component))->toThrow(AuthorizationException::class)
        ->and($this->asset->components()->count())->toBe(1);
})->with([SystemRole::Visualizador, SystemRole::Reportes, SystemRole::Reservante]);

test('a component is validated with a message on its field', function (array $input, string $field) {
    $this->actingAs($this->admin);

    Livewire::test('assets.asset-components', ['asset' => $this->asset])
        ->call('add')
        ->set($input + ['name' => 'Disco SSD', 'quantity' => '1'])
        ->call('save')
        ->assertHasErrors($field);

    expect($this->asset->components()->count())->toBe(0);
})->with([
    'no name' => [['name' => '   '], 'name'],
    'a name over 120 characters' => [['name' => str_repeat('a', 121)], 'name'],
    'a serial number over 100 characters' => [['serial_number' => str_repeat('1', 101)], 'serial_number'],
    'no quantity' => [['quantity' => ''], 'quantity'],
    'a quantity of zero' => [['quantity' => '0'], 'quantity'],
    'a fractional quantity' => [['quantity' => '1.5'], 'quantity'],
    'a quantity over 9999' => [['quantity' => '10000'], 'quantity'],
]);

test('the database requires a non-blank name and a positive quantity, reported in Spanish on their field', function (array $attributes, string $rule, string $field) {
    try {
        componentOf($this->asset, $attributes);
        $this->fail('The database accepted the component.');
    } catch (QueryException $e) {
        expect(AddAssetComponent::toValidationException($e)->errors())->toBe([$field => [__('db.'.$rule)]]);
    }
})->with([
    'blank name' => [['name' => '  '], 'asset_components_name_check', 'name'],
    'zero quantity' => [['quantity' => 0], 'asset_components_quantity_check', 'quantity'],
]);

test('a component of another asset cannot be changed through this asset', function () {
    $this->actingAs($this->admin);
    $foreign = componentOf(Asset::factory()->inSpace($this->space)->create());

    Livewire::test('assets.asset-components', ['asset' => $this->asset])->call('edit', $foreign->id)->assertNotFound();
    Livewire::test('assets.asset-components', ['asset' => $this->asset])->call('confirmRemove', $foreign->id)->assertNotFound();

    expect($foreign->refresh()->exists)->toBeTrue();
});

test('a decommissioned asset shows its components read-only and refuses changes', function () {
    $this->actingAs($this->admin);
    $component = componentOf($this->asset);
    $this->asset->forceFill(['status' => AssetStatus::Decommissioned])->save();

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSee('Memoria RAM')
        ->assertDontSee('data-test="add-component-button"', false)
        ->assertDontSee('data-test="edit-component-button"', false);

    foreach ([
        fn () => app(AddAssetComponent::class)->handle($this->admin, $this->asset, ['name' => 'Disco', 'quantity' => 1]),
        fn () => app(UpdateAssetComponent::class)->handle($this->admin, $component, ['name' => 'Disco', 'quantity' => 1]),
        fn () => app(RemoveAssetComponent::class)->handle($this->admin, $component),
    ] as $change) {
        try {
            $change();
            $this->fail('The decommissioned asset accepted a component change.');
        } catch (ValidationException $e) {
            expect($e->errors())->toBe(['form' => [__('assets.components.errors.decommissioned')]]);
        }
    }

    expect($this->asset->components()->sole()->name)->toBe('Memoria RAM');
});
