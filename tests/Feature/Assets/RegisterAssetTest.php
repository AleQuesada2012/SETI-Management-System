<?php

// RF_03 · Añadir activo, with RF_14 (fecha de adquisición), RF_15 (proveedor), RF_16 (garantía), RF_18 (ubicación
// física), RF_19 (observaciones), RF_23 (responsable) and RF_24 (categoría), which close with it.

use App\Actions\Assets\RegisterAsset;
use App\Enums\AssetStatus;
use App\Enums\MovementType;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetMovement;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Space;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->type = AssetType::query()->where('name', 'Monitor')->firstOrFail();
    $this->condition = AssetCondition::query()->where('name', 'Buen estado')->firstOrFail();
});

/** @param  array<string, mixed>  $overrides */
function assetInput(Space $space, array $overrides = []): array
{
    return [
        'space_id' => $space->id,
        'asset_type_id' => test()->type->id,
        'condition_id' => test()->condition->id,
        ...$overrides,
    ];
}

test('an Administrador registers an asset in a space they manage and is taken to it', function () {
    $admin = actingAsRole(SystemRole::Administrador);
    $space = Space::factory()->managedBy($admin)->create();

    $component = Livewire::test('pages::assets.create')
        ->assertSet('space_id', (string) $space->id)
        ->set('asset_type_id', $this->type->id)
        ->set('condition_id', $this->condition->id)
        ->set('model', 'P2422H')
        ->call('save');

    $asset = Asset::query()->sole();

    $component->assertHasNoErrors()
        ->assertDispatched('toast-show')
        ->assertRedirect(route('assets.show', $asset));

    expect($asset->current_space_id)->toBe($space->id)
        ->and($asset->status)->toBe(AssetStatus::InService)
        ->and($asset->code)->toBe('A-'.str_pad((string) $asset->id, 4, '0', STR_PAD_LEFT));

    $movement = AssetMovement::query()->sole();
    expect($movement->type)->toBe(MovementType::Entry)
        ->and($movement->asset_id)->toBe($asset->id)
        ->and($movement->from_space_id)->toBeNull()
        ->and($movement->to_space_id)->toBe($space->id)
        ->and($movement->moved_by)->toBe($admin->id);

    $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'entity_type' => 'asset', 'entity_id' => $asset->id, 'actor_id' => $admin->id]);
});

test('the Superusuario registers an asset with every field of the form', function () {
    actingAsRole(SystemRole::Superusuario);
    $space = Space::factory()->create();
    $brand = Brand::query()->create(['name' => 'Dell']);
    $supplier = Supplier::query()->create(['name' => 'Distribuidora Tecnológica S.A.']);
    $custodian = userWithRole(SystemRole::Visualizador);

    Livewire::test('pages::assets.create')
        ->set('space_id', $space->id)
        ->set('location_detail', 'Rack 2')
        ->set('asset_type_id', $this->type->id)
        ->set('condition_id', $this->condition->id)
        ->set('name', 'Monitor de la recepción')
        ->set('brand_id', $brand->id)
        ->set('model', 'P2422H')
        ->set('asset_tag', 'TEC-10001')
        ->set('serial_number', 'SN-1234')
        ->set('acquired_on', '2025-03-14')
        ->set('supplier_id', $supplier->id)
        ->set('warranty_starts_on', '2025-03-14')
        ->set('warranty_ends_on', '2028-03-14')
        ->set('responsible_user_id', $custodian->id)
        ->set('observations', 'Tiene un rayón en el marco.')
        ->call('save')
        ->assertHasNoErrors();

    $asset = Asset::query()->sole();

    expect($asset->current_space_id)->toBe($space->id)
        ->and($asset->location_detail)->toBe('Rack 2')                          // RF_18
        ->and($asset->assetType->category->name)->toBe('Cómputo')              // RF_24
        ->and($asset->brand_id)->toBe($brand->id)
        ->and($asset->asset_tag)->toBe('TEC-10001')
        ->and($asset->acquired_on->toDateString())->toBe('2025-03-14')         // RF_14
        ->and($asset->supplier_id)->toBe($supplier->id)                        // RF_15
        ->and($asset->warranty_starts_on->toDateString())->toBe('2025-03-14')  // RF_16
        ->and($asset->warranty_ends_on->toDateString())->toBe('2028-03-14')
        ->and($asset->responsible_user_id)->toBe($custodian->id)               // RF_23
        ->and($asset->observations)->toBe('Tiene un rayón en el marco.');      // RF_19
});

test('the optional fields may be left empty', function () {
    $superuser = userWithRole(SystemRole::Superusuario);

    $asset = app(RegisterAsset::class)->handle($superuser, assetInput(Space::factory()->create()));

    expect($asset->name)->toBeNull()
        ->and($asset->observations)->toBeNull()
        ->and($asset->responsible_user_id)->toBeNull()
        ->and($asset->warranty_starts_on)->toBeNull();
});

test('an Administrador cannot register an asset in a space they do not manage', function () {
    $admin = actingAsRole(SystemRole::Administrador);
    Space::factory()->managedBy($admin)->create();
    $otherSpace = Space::factory()->create();

    Livewire::test('pages::assets.create')
        ->set('space_id', $otherSpace->id)
        ->set('asset_type_id', $this->type->id)
        ->set('condition_id', $this->condition->id)
        ->call('save')
        ->assertForbidden();

    expect(Asset::query()->count())->toBe(0);
});

test('roles without assets.create cannot register assets', function (SystemRole $role) {
    $user = actingAsRole($role);
    $space = Space::factory()->create();

    $this->get(route('assets.create'))->assertForbidden();
    $this->get(route('assets.index'))->assertOk()->assertDontSee(__('assets.index.create'));

    expect(fn () => app(RegisterAsset::class)->handle($user, assetInput($space)))
        ->toThrow(AuthorizationException::class)
        ->and(Asset::query()->count())->toBe(0);
})->with([SystemRole::Visualizador, SystemRole::Reportes]);

test('an Administrador who manages no space cannot register assets', function () {
    actingAsRole(SystemRole::Administrador);

    $this->get(route('assets.create'))->assertForbidden();
});

test('the inventory offers "Añadir activo" to those who may register', function () {
    $admin = actingAsRole(SystemRole::Administrador);
    Space::factory()->managedBy($admin)->create();

    $this->get(route('assets.index'))->assertOk()->assertSee(__('assets.index.create'));
    $this->get(route('assets.create'))->assertOk()->assertSee(__('assets.create.submit'));
});

test('the space, type and condition are required', function () {
    $admin = actingAsRole(SystemRole::Administrador);
    Space::factory()->count(2)->managedBy($admin)->create();

    Livewire::test('pages::assets.create')
        ->call('save')
        ->assertHasErrors(['space_id' => 'required', 'asset_type_id' => 'required', 'condition_id' => 'required']);

    expect(Asset::query()->count())->toBe(0);
});

test('badly formatted data is rejected with a message on its field', function (array $input, string $field) {
    $superuser = userWithRole(SystemRole::Superusuario);

    try {
        app(RegisterAsset::class)->handle($superuser, assetInput(Space::factory()->create(), $input));
        $this->fail('The asset was registered.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }

    expect(Asset::query()->count())->toBe(0);
})->with([
    'an acquisition date that is not a date' => [['acquired_on' => '31/02/2025'], 'acquired_on'],
    'an acquisition date in the future' => [['acquired_on' => now()->addDay()->toDateString()], 'acquired_on'],
    'a warranty that ends before it starts' => [['warranty_starts_on' => '2025-05-01', 'warranty_ends_on' => '2025-04-01'], 'warranty_ends_on'],
    'a warranty without an end' => [['warranty_starts_on' => '2025-05-01'], 'warranty_ends_on'],
    'a name longer than 150 characters' => [['name' => str_repeat('a', 151)], 'name'],
    'a type that does not exist' => [['asset_type_id' => 999999], 'asset_type_id'],
]);

test('a tag already in use is rejected with its Spanish message', function () {
    $admin = actingAsRole(SystemRole::Administrador);
    $space = Space::factory()->managedBy($admin)->create();
    Asset::factory()->inSpace($space)->create(['asset_tag' => 'TEC-20001']);

    Livewire::test('pages::assets.create')
        ->set('asset_type_id', $this->type->id)
        ->set('condition_id', $this->condition->id)
        ->set('asset_tag', 'tec-20001')
        ->call('save')
        ->assertHasErrors('asset_tag')
        ->assertSee(__('db.assets_asset_tag_unique'));

    expect(Asset::query()->count())->toBe(1);
});

test('a deactivated user cannot be the custodian', function () {
    $superuser = userWithRole(SystemRole::Superusuario);
    $former = User::factory()->deactivated()->create();

    expect(fn () => app(RegisterAsset::class)->handle($superuser, assetInput(Space::factory()->create(), ['responsible_user_id' => $former->id])))
        ->toThrow(ValidationException::class);
});
