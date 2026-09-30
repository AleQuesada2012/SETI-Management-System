<?php

// RF_07 · Búsqueda de activos, RF_08 · Filtrado de activos (with RF_24's category and RF_30's custodian): the
// inventory filter contract (App\Queries\Assets\InventoryFilters) and its query (AssetInventory).

use App\Enums\AssetStatus;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Brand;
use App\Models\Space;
use App\Models\User;
use App\Queries\Assets\AssetInventory;
use App\Queries\Assets\InventoryFilters;
use Illuminate\Validation\ValidationException;

/** @return list<string> the codes the inventory returns for these filters */
function inventoryCodes(array $filters = []): array
{
    return AssetInventory::query($filters)->pluck('code')->all();
}

beforeEach(function () {
    $this->lab = Space::factory()->create(['code' => 'LAB-1']);
    $this->office = Space::factory()->create(['code' => 'OF-1']);
    $this->dell = Brand::query()->create(['name' => 'Dell']);
    $this->epson = Brand::query()->create(['name' => 'Epson']);
});

test('search matches name, brand, model, code, tag and serial, ignoring case and accents', function () {
    $named = Asset::factory()->inSpace($this->lab)->create(['name' => 'Cámara del salón', 'model' => 'X1']);
    $branded = Asset::factory()->inSpace($this->lab)->create(['brand_id' => $this->epson->id, 'model' => 'X2']);
    $modeled = Asset::factory()->inSpace($this->lab)->create(['model' => 'Latitude 5420']);
    $tagged = Asset::factory()->inSpace($this->lab)->create(['asset_tag' => 'TEC-77777', 'model' => 'X3']);
    $serial = Asset::factory()->inSpace($this->lab)->create(['serial_number' => 'SN-ABC-999', 'model' => 'X4']);

    expect(inventoryCodes(['search' => 'CAMARA']))->toBe([$named->code])
        ->and(inventoryCodes(['search' => 'epso']))->toBe([$branded->code])
        ->and(inventoryCodes(['search' => 'latitude']))->toBe([$modeled->code])
        ->and(inventoryCodes(['search' => '  tec-777 ']))->toBe([$tagged->code])
        ->and(inventoryCodes(['search' => 'abc-999']))->toBe([$serial->code])
        ->and(inventoryCodes(['search' => $modeled->code]))->toBe([$modeled->code])
        ->and(inventoryCodes(['search' => 'no existe']))->toBe([]);
});

test('search treats % and _ literally', function () {
    Asset::factory()->inSpace($this->lab)->create(['model' => 'Modelo 100']);

    expect(inventoryCodes(['search' => '%']))->toBe([])
        ->and(inventoryCodes(['search' => '_']))->toBe([]);
});

test('each criterion keeps only the assets that meet it', function () {
    $computo = AssetCategory::query()->where('name', 'Cómputo')->firstOrFail();
    $custodian = userWithRole(SystemRole::Visualizador);

    $monitor = Asset::factory()->inSpace($this->lab)->ofType('Monitor')->create([
        'brand_id' => $this->dell->id, 'model' => 'P2422H', 'responsible_user_id' => $custodian->id, 'acquired_on' => '2026-09-01',
    ]);
    $damaged = Asset::factory()->inSpace($this->office)->ofType('Proyector')->inCondition('Dañado')->create([
        'brand_id' => $this->epson->id, 'model' => 'PowerLite', 'acquired_on' => '2025-01-15',
    ]);
    $lost = Asset::factory()->inSpace($this->office)->ofType('Escritorio')->inCondition('Regular')->create(['model' => null, 'acquired_on' => null]);
    $lost->forceFill(['status' => AssetStatus::Lost])->save();

    expect(inventoryCodes(['brand' => ['values' => [$this->dell->id]]]))->toBe([$monitor->code])
        ->and(inventoryCodes(['model' => ['contains' => 'powér']]))->toBe([$damaged->code])
        ->and(inventoryCodes(['category' => ['values' => [$computo->id]]]))->toBe([$monitor->code])
        ->and(inventoryCodes(['condition' => ['values' => [$damaged->condition_id]]]))->toBe([$damaged->code])
        ->and(inventoryCodes(['condition_color' => ['values' => ['amber']]]))->toBe([$lost->code])
        ->and(inventoryCodes(['lifecycle_status' => ['values' => ['lost']]]))->toBe([$lost->code])
        ->and(inventoryCodes(['space' => ['values' => [$this->lab->id]]]))->toBe([$monitor->code])
        ->and(inventoryCodes(['responsible_user' => ['values' => [$custodian->id]]]))->toBe([$monitor->code])
        ->and(inventoryCodes(['acquired_on' => ['from' => '2026-01-01']]))->toBe([$monitor->code]);
});

test('criteria combine with AND and the values inside one criterion with OR', function () {
    $a = Asset::factory()->inSpace($this->lab)->create(['brand_id' => $this->dell->id]);
    $b = Asset::factory()->inSpace($this->office)->create(['brand_id' => $this->dell->id]);
    $c = Asset::factory()->inSpace($this->office)->create(['brand_id' => $this->epson->id]);

    expect(inventoryCodes(['brand' => ['values' => [$this->dell->id, $this->epson->id]]]))->toBe([$a->code, $b->code, $c->code])
        ->and(inventoryCodes(['brand' => ['values' => [$this->dell->id]], 'space' => ['values' => [$this->office->id]]]))->toBe([$b->code]);
});

test('a selected missing value is one more alternative, and alone selects only the missing', function () {
    $placed = Asset::factory()->inSpace($this->lab)->create(['brand_id' => $this->dell->id, 'acquired_on' => '2026-09-10']);
    $unplaced = Asset::factory()->create(['brand_id' => null, 'acquired_on' => null, 'model' => null]);
    $elsewhere = Asset::factory()->inSpace($this->office)->create(['brand_id' => $this->epson->id, 'acquired_on' => '2020-01-01']);

    expect(inventoryCodes(['space' => ['values' => [$this->lab->id], 'include_missing' => true]]))->toBe([$placed->code, $unplaced->code])
        ->and(inventoryCodes(['space' => ['include_missing' => true]]))->toBe([$unplaced->code])
        ->and(inventoryCodes(['brand' => ['include_missing' => true]]))->toBe([$unplaced->code])
        ->and(inventoryCodes(['model' => ['include_missing' => true]]))->toBe([$unplaced->code])
        ->and(inventoryCodes(['responsible_user' => ['include_missing' => true]]))->toBe([$placed->code, $unplaced->code, $elsewhere->code])
        ->and(inventoryCodes(['acquired_on' => ['from' => '2026-09-01', 'to' => '2026-09-30', 'include_missing' => true]]))->toBe([$placed->code, $unplaced->code])
        ->and(inventoryCodes(['space' => ['values' => []]]))->toHaveCount(3);
});

test('date bounds are inclusive and each one is optional', function () {
    $first = Asset::factory()->inSpace($this->lab)->create(['acquired_on' => '2026-09-01']);
    $last = Asset::factory()->inSpace($this->lab)->create(['acquired_on' => '2026-09-30']);
    $outside = Asset::factory()->inSpace($this->lab)->create(['acquired_on' => '2026-10-01']);

    expect(inventoryCodes(['acquired_on' => ['from' => '2026-09-01', 'to' => '2026-09-30']]))->toBe([$first->code, $last->code])
        ->and(inventoryCodes(['acquired_on' => ['to' => '2026-09-01']]))->toBe([$first->code])
        ->and(inventoryCodes(['acquired_on' => ['from' => '2026-10-01']]))->toBe([$outside->code]);
});

test('references to removed brands and deactivated custodians stay valid', function () {
    $former = User::factory()->deactivated()->create();
    $asset = Asset::factory()->inSpace($this->lab)->create(['brand_id' => $this->epson->id, 'responsible_user_id' => $former->id]);
    $this->epson->delete();

    expect(inventoryCodes(['brand' => ['values' => [$this->epson->id]]]))->toBe([$asset->code])
        ->and(inventoryCodes(['responsible_user' => ['values' => [$former->id]]]))->toBe([$asset->code]);
});

test('input is normalized: trimmed, without duplicates or empty restrictions', function () {
    expect(InventoryFilters::validate([]))->toBe([])
        ->and(InventoryFilters::validate(['search' => '  ', 'brand' => ['values' => []], 'model' => ['contains' => ' '], 'acquired_on' => []]))->toBe([])
        ->and(InventoryFilters::validate([
            'search' => ' Dell ',
            'brand' => ['values' => [$this->epson->id, (string) $this->dell->id, $this->epson->id], 'include_missing' => false],
            'lifecycle_status' => ['values' => ['lost', 'in_service', 'lost']],
        ]))->toBe([
            'search' => 'Dell',
            'brand' => ['values' => [$this->dell->id, $this->epson->id]],
            'lifecycle_status' => ['values' => ['in_service', 'lost']],
        ]);
});

test('invalid filters are rejected with a short Spanish message before querying', function (array $filters, string $key, string $message) {
    try {
        InventoryFilters::validate($filters);
        $this->fail('The filters were accepted.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($key)
            ->and($e->errors()[$key][0])->toBe(__('assets.filters.errors.'.$message, ['key' => $key]));
    }
})->with([
    'an unsupported key' => [['space_id' => 3], 'space_id', 'unknown'],
    'a criterion that is not an object' => [['brand' => 5], 'brand', 'shape'],
    'an unsupported member' => [['condition' => ['values' => [1], 'include_missing' => true]], 'condition', 'shape'],
    'values that are not a list' => [['brand' => ['values' => ['a' => 1]]], 'brand.values', 'shape'],
    'a search that is not text' => [['search' => ['x']], 'search', 'text'],
    'a brand that does not exist' => [['brand' => ['values' => [999999]]], 'brand.values.0', 'value'],
    'a color outside the three groups' => [['condition_color' => ['values' => ['gray']]], 'condition_color.values.0', 'value'],
    'an unknown lifecycle status' => [['lifecycle_status' => ['values' => ['stolen']]], 'lifecycle_status.values.0', 'value'],
    'a missing selection that is not true or false' => [['brand' => ['include_missing' => 'maybe']], 'brand.include_missing', 'boolean'],
    'an invalid date' => [['acquired_on' => ['from' => '2026-02-30']], 'acquired_on.from', 'date'],
    'an inverted range' => [['acquired_on' => ['from' => '2026-09-30', 'to' => '2026-09-01']], 'acquired_on.to', 'range'],
]);

test('the supported keys are the contract plus category', function () {
    expect(InventoryFilters::keys())->toBe([
        'search', 'brand', 'model', 'category', 'condition', 'condition_color', 'lifecycle_status', 'space',
        'responsible_user', 'acquired_on',
    ]);
});

test('choices are stable and label removed and deactivated references', function () {
    $former = User::factory()->deactivated()->create(['name' => 'Jorge Ramírez']);
    Asset::factory()->inSpace($this->lab)->create(['brand_id' => $this->epson->id, 'responsible_user_id' => $former->id]);
    $this->epson->delete();
    $gone = Brand::query()->create(['name' => 'Marca sin uso']);
    $gone->delete();

    $choices = InventoryFilters::choices();
    $labels = fn (string $key): array => array_column($choices[$key], 'label', 'id');

    expect($labels('brand'))->toContain('Dell', 'Epson (eliminado)')
        ->not->toContain('Marca sin uso (eliminado)')
        ->and($labels('brand')[$this->epson->id])->toBe('Epson (eliminado)')
        ->and($labels('responsible_user')[$former->id])->toBe('Jorge Ramírez (cuenta desactivada)')
        ->and(array_column($choices['condition_color'], 'id'))->toBe(['green', 'amber', 'red'])
        ->and(array_column($choices['lifecycle_status'], 'id'))->toBe(['in_service', 'in_maintenance', 'lost', 'decommissioned']);
});
