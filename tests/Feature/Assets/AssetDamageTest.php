<?php

// RF_13 · Registrar daños de activos: a damage event with its date, description and optional evidence, which sets the
// asset's condition to the red-group condition the user picks (Agent-DB.md §8.4).

use App\Actions\Assets\RegisterAssetDamage;
use App\Actions\Assets\RegisterAssetEvent;
use App\Enums\AssetEventKind;
use App\Enums\AssetStatus;
use App\Enums\ColorGroup;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetEvent;
use App\Models\AuditLog;
use App\Models\Space;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(config('filesystems.files_disk'));
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create();
    $this->asset = Asset::factory()->inSpace($this->space)->create(['acquired_on' => '2024-03-01']);
    $this->damaged = AssetCondition::query()->where('name', 'Dañado')->sole();
});

/** @param  array<string, mixed>  $input */
function registerDamage(Asset $asset, array $input = [], $actor = null): AssetEvent
{
    return app(RegisterAssetDamage::class)->handle($actor ?? test()->admin, $asset, $input + [
        'occurred_on' => today()->toDateString(),
        'description' => 'La pantalla se quebró al caer del escritorio.',
        'condition_id' => test()->damaged->id,
    ]);
}

/** @return array<string, list<string>> */
function damageErrors(Asset $asset, array $input): array
{
    try {
        registerDamage($asset, $input);
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

test('an Administrador registers a damage with evidence, and the asset takes the damaged condition', function () {
    $this->actingAs($this->admin);

    Livewire::test('assets.asset-events', ['asset' => $this->asset])
        ->call('open')
        ->assertSet('kind', AssetEventKind::Damage->value)
        ->assertSet('condition_id', (string) $this->damaged->id)
        ->assertSee('data-test="event-evidence-input"', false)
        ->set('occurred_on', '2026-10-10')
        ->set('description', 'La pantalla se quebró al caer del escritorio.')
        ->set('evidence', [UploadedFile::fake()->image('pantalla.jpg', 800, 600), UploadedFile::fake()->create('reporte.pdf', 40, 'application/pdf')])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', slots: ['text' => __('assets.events.done.damage', ['asset' => $this->asset->code])])
        ->assertRedirect(route('assets.show', $this->asset));

    $event = AssetEvent::query()->where('asset_id', $this->asset->id)->sole();
    $asset = $this->asset->refresh();

    expect($event->kind)->toBe(AssetEventKind::Damage)
        ->and($event->occurred_on->toDateString())->toBe('2026-10-10')
        ->and($event->registered_by)->toBe($this->admin->id)
        ->and($event->evidence()->pluck('original_name')->sort()->values()->all())->toBe(['pantalla.jpg', 'reporte.pdf'])
        ->and($asset->condition_id)->toBe($this->damaged->id)
        ->and($asset->status)->toBe(AssetStatus::InService);
    Storage::disk(config('filesystems.files_disk'))->assertExists($event->evidence->pluck('path')->all());

    $this->get(route('assets.show', $asset))
        ->assertOk()
        ->assertSee('La pantalla se quebró al caer del escritorio.')
        ->assertSee('data-test="event-evidence"', false)
        ->assertSee('pantalla.jpg');
});

test('a damage, its evidence and the condition change are audited', function () {
    $this->actingAs($this->admin);
    $event = registerDamage($this->asset, ['evidence' => [UploadedFile::fake()->image('golpe.png')]]);

    $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'entity_type' => 'asset_event', 'entity_id' => $event->id, 'actor_id' => $this->admin->id]);
    expect(AuditLog::query()->where(['action' => 'attached', 'entity_type' => 'asset_event', 'entity_id' => $event->id])->sole()->new_values)
        ->toBe(['evidence' => 'golpe.png'])
        ->and(AuditLog::query()->where(['action' => 'updated', 'entity_type' => 'asset', 'entity_id' => $this->asset->id])->sole()->new_values)
        ->toBe(['condition_id' => $this->damaged->id]);
});

test('evidence is optional', function () {
    $event = registerDamage($this->asset);

    expect($event->evidence()->count())->toBe(0)
        ->and($this->asset->refresh()->condition_id)->toBe($this->damaged->id);
});

test('an asset cannot enter the damage form without a damaged condition and a description', function () {
    $good = AssetCondition::query()->where('color_group', ColorGroup::Green)->firstOrFail();

    expect(damageErrors($this->asset, ['condition_id' => $good->id]))->toBe(['condition_id' => [__('assets.events.errors.condition')]])
        ->and(damageErrors($this->asset, ['condition_id' => '']))->toHaveKey('condition_id')
        ->and(damageErrors($this->asset, ['description' => '']))->toHaveKey('description')
        ->and(damageErrors($this->asset, ['occurred_on' => today()->addDay()->toDateString()]))->toBe(['occurred_on' => [__('assets.events.errors.future')]]);

    expect(AssetEvent::query()->count())->toBe(0)
        ->and($this->asset->refresh()->condition->name)->toBe('Buen estado');
});

test('evidence must be photos or PDFs, at most ten at a time', function () {
    expect(damageErrors($this->asset, ['evidence' => [UploadedFile::fake()->create('notas.txt', 2, 'text/plain')]]))->toHaveKey('evidence.0')
        ->and(damageErrors($this->asset, ['evidence' => array_map(fn (int $i) => UploadedFile::fake()->image("foto{$i}.jpg"), range(1, 11))]))->toHaveKey('evidence');

    expect(AssetEvent::query()->count())->toBe(0);
    Storage::disk(config('filesystems.files_disk'))->assertDirectoryEmpty('/');
});

test('only an asset in service or in maintenance can be damaged', function (AssetEventKind $event) {
    app(RegisterAssetEvent::class)->handle($this->admin, $this->asset, [
        'kind' => $event->value, 'occurred_on' => today()->toDateString(), 'description' => 'Evento previo',
    ]);
    $asset = $this->asset->refresh();

    expect(RegisterAssetDamage::isAllowed($asset))->toBeFalse()
        ->and(damageErrors($asset, []))->toBe(['kind' => [__('assets.events.errors.kind')]])
        ->and($asset->events()->where('kind', 'damage')->count())->toBe(0);
})->with([AssetEventKind::Loss, AssetEventKind::Decommission]);

test('an asset in maintenance can be damaged', function () {
    $this->asset->forceFill(['status' => AssetStatus::InMaintenance])->save();

    registerDamage($this->asset);

    expect($this->asset->refresh()->status)->toBe(AssetStatus::InMaintenance);
});

test('the evidence opens only through its own asset', function () {
    $event = registerDamage($this->asset, ['evidence' => [UploadedFile::fake()->image('golpe.jpg')]]);
    $file = $event->evidence()->sole();
    $other = Asset::factory()->inSpace(Space::factory()->create())->create();
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('assets.files.show', [$this->asset, $file]))->assertOk();
    $this->get(route('assets.files.show', [$other, $file]))->assertNotFound();
});

test('an Administrador who does not manage the asset\'s space cannot register its damage', function () {
    $this->actingAs($this->admin);
    $other = Asset::factory()->inSpace(Space::factory()->create())->create();

    expect(fn () => registerDamage($other))->toThrow(AuthorizationException::class)
        ->and($other->refresh()->condition->name)->toBe('Buen estado');
});

test('roles without assets.register_events cannot register damage', function (SystemRole $role) {
    $user = actingAsRole($role);

    Livewire::test('assets.asset-events', ['asset' => $this->asset])
        ->set('kind', AssetEventKind::Damage->value)
        ->call('save')->assertForbidden();

    expect(fn () => registerDamage($this->asset, [], $user))->toThrow(AuthorizationException::class)
        ->and(AssetEvent::query()->count())->toBe(0);
})->with([SystemRole::Visualizador, SystemRole::Reportes, SystemRole::Reservante]);
