<?php

// RF_04 · Editar activo, and RF_05 · Asignar estado a activo (the condition and its color), which closes with it.

use App\Actions\Assets\UpdateAsset;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetMovement;
use App\Models\Brand;
use App\Models\Space;
use App\Models\Supplier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create();
    $this->asset = Asset::factory()->inSpace($this->space)->create([
        'name' => 'Monitor de la recepción',
        'model' => 'P2422H',
        'acquired_on' => '2024-01-10',
    ]);
});

test('an Administrador edits the attributes of an asset in their space', function () {
    $this->actingAs($this->admin);
    $brand = Brand::query()->create(['name' => 'Dell']);

    Livewire::test('pages::assets.edit', ['asset' => $this->asset])
        ->assertSet('name', 'Monitor de la recepción')
        ->assertSet('acquired_on', '2024-01-10')
        ->set('name', 'Monitor del laboratorio')
        ->set('brand_id', $brand->id)
        ->set('model', 'P2723D')
        ->set('acquired_on', '2024-02-01')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show')
        ->assertRedirect(route('assets.show', $this->asset));

    $asset = $this->asset->refresh();

    expect($asset->name)->toBe('Monitor del laboratorio')
        ->and($asset->brand_id)->toBe($brand->id)
        ->and($asset->model)->toBe('P2723D')
        ->and($asset->acquired_on->toDateString())->toBe('2024-02-01')
        ->and($asset->current_space_id)->toBe($this->space->id);

    $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'entity_type' => 'asset', 'entity_id' => $asset->id, 'actor_id' => $this->admin->id]);
});

test('the space is shown but does not change from the edit form', function () {
    $this->actingAs($this->admin);

    $this->get(route('assets.edit', $this->asset))
        ->assertOk()
        ->assertSee($this->space->code)
        ->assertSee(__('assets.edit.space_help'));

    Livewire::test('pages::assets.edit', ['asset' => $this->asset])->set('model', 'X1')->call('save');

    expect($this->asset->refresh()->current_space_id)->toBe($this->space->id)
        ->and(AssetMovement::query()->where('asset_id', $this->asset->id)->count())->toBe(1);
});

test('the "Registrar traslado" link is not inside the space help, which screen readers skip', function () {
    $this->actingAs($this->admin);

    $html = $this->get(route('assets.edit', $this->asset))
        ->assertOk()
        ->assertSee('data-test="edit-move-link"', false)
        ->getContent();

    // Flux hides every <ui-description> with aria-hidden: a link in one cannot be reached by a screen reader (DEF-16).
    expect($html)->not->toMatch('/<ui-description\b(?:(?!<\/ui-description>).)*data-test="edit-move-link"/s');
});

test('the brand, the supplier and the custodian can go back to "Ninguno" (CP-15 b, CP-23 b)', function () {
    $this->actingAs($this->admin);
    $brand = Brand::query()->create(['name' => 'Dell']);
    $supplier = Supplier::query()->create(['name' => 'Distribuidora Tecnológica S.A.']);
    $this->asset->update([
        'brand_id' => $brand->id,
        'supplier_id' => $supplier->id,
        'responsible_user_id' => $this->admin->id,
    ]);

    $html = $this->get(route('assets.edit', $this->asset))->assertOk()->getContent();
    $none = preg_quote(__('assets.create.none'), '/');

    // A Flux placeholder is a disabled option: "Ninguno" must be a real, selectable one (DEF-15).
    expect(preg_match_all('/<option\b[^>]*\bvalue=""[^>]*>\s*'.$none.'\s*<\/option>/', $html, $options))->toBe(3)
        ->and(collect($options[0])->filter(fn (string $option) => str_contains($option, 'disabled'))->all())->toBe([]);

    Livewire::test('pages::assets.edit', ['asset' => $this->asset])
        ->set('brand_id', '')
        ->set('supplier_id', '')
        ->set('responsible_user_id', '')
        ->call('save')
        ->assertHasNoErrors();

    $this->asset->refresh();
    expect($this->asset->brand_id)->toBeNull()
        ->and($this->asset->supplier_id)->toBeNull()
        ->and($this->asset->responsible_user_id)->toBeNull();
    $this->get(route('assets.show', $this->asset))->assertOk()->assertDontSee($supplier->name);
});

test('assigning a condition saves it, confirms it and shows its color', function (string $condition, string $color) {
    $this->actingAs($this->admin);
    $this->asset->update(['condition_id' => AssetCondition::query()->where('name', 'Regular')->value('id')]);
    $target = AssetCondition::query()->where('name', $condition)->firstOrFail();

    Livewire::test('pages::assets.edit', ['asset' => $this->asset])
        ->set('condition_id', $target->id)
        ->set('condition_note', 'Revisado por el encargado.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', slots: ['text' => __('assets.edit.condition_done', ['asset' => $this->asset->code, 'condition' => $condition])]);

    expect($this->asset->refresh()->condition_id)->toBe($target->id)
        ->and($this->asset->condition_note)->toBe('Revisado por el encargado.');

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSee('data-color="'.$color.'"', escape: false)
        ->assertSee($condition);
})->with([
    'green: in perfect condition' => ['Buen estado', 'green'],
    'red: damaged' => ['Dañado', 'red'],
]);

test('edited data is validated with the same formats as registration', function (array $input, string $field) {
    try {
        app(UpdateAsset::class)->handle($this->admin, $this->asset, [
            'asset_type_id' => $this->asset->asset_type_id,
            'condition_id' => $this->asset->condition_id,
            ...$input,
        ]);
        $this->fail('The asset was updated.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }

    expect($this->asset->refresh()->model)->toBe('P2422H');
})->with([
    'an acquisition date that is not a date' => [['acquired_on' => 'ayer', 'model' => 'X'], 'acquired_on'],
    'a warranty that ends before it starts' => [['warranty_starts_on' => '2025-05-01', 'warranty_ends_on' => '2025-04-01', 'model' => 'X'], 'warranty_ends_on'],
    'a model longer than 120 characters' => [['model' => str_repeat('m', 121)], 'model'],
    'no condition' => [['condition_id' => null, 'model' => 'X'], 'condition_id'],
]);

test('a tag already used by another asset is rejected', function () {
    $this->actingAs($this->admin);
    Asset::factory()->inSpace($this->space)->create(['asset_tag' => 'TEC-30001']);

    Livewire::test('pages::assets.edit', ['asset' => $this->asset])
        ->set('asset_tag', 'TEC-30001')
        ->call('save')
        ->assertHasErrors('asset_tag')
        ->assertSee(__('db.assets_asset_tag_unique'));
});

test('an Administrador cannot edit an asset outside the spaces they manage', function () {
    $this->actingAs($this->admin);
    $other = Asset::factory()->inSpace(Space::factory()->create())->create();

    $this->get(route('assets.edit', $other))->assertForbidden();
    $this->get(route('assets.show', $other))->assertOk()->assertDontSee(__('assets.show.edit'));

    expect(fn () => app(UpdateAsset::class)->handle($this->admin, $other, ['model' => 'X']))
        ->toThrow(AuthorizationException::class);
});

test('roles without assets.update cannot edit assets', function (SystemRole $role) {
    $user = actingAsRole($role);

    $this->get(route('assets.edit', $this->asset))->assertForbidden();
    $this->get(route('assets.show', $this->asset))->assertOk()->assertDontSee(__('assets.show.edit'));

    expect(fn () => app(UpdateAsset::class)->handle($user, $this->asset, ['model' => 'X']))
        ->toThrow(AuthorizationException::class)
        ->and($this->asset->refresh()->model)->toBe('P2422H');
})->with([SystemRole::Visualizador, SystemRole::Reportes]);

test('the Superusuario edits any asset', function () {
    actingAsRole(SystemRole::Superusuario);

    $this->get(route('assets.show', $this->asset))->assertSee(__('assets.show.edit'));

    Livewire::test('pages::assets.edit', ['asset' => $this->asset])
        ->set('observations', 'Revisado en inventario anual.')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->asset->refresh()->observations)->toBe('Revisado en inventario anual.');
});
