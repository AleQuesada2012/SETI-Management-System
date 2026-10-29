<?php

// RF_09 · Consulta del historial de un activo: where it has been, what happened to it and which Administradores or
// Superusuarios changed it (Agent-DB.md §8.5).

use App\Actions\Assets\AttachAssetFiles;
use App\Actions\Assets\RecordAssetMovement;
use App\Actions\Assets\RegisterAssetEvent;
use App\Actions\Assets\UpdateAsset;
use App\Enums\AssetFileKind;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\Space;
use App\Queries\Assets\AssetHistory;
use App\Queries\Assets\AssetHistoryEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->superuser = userWithRole(SystemRole::Superusuario);
    $this->space = Space::factory()->managedBy($this->admin)->create(['code' => 'LAB-901', 'name' => 'Laboratorio 901']);
    $this->destination = Space::factory()->create(['code' => 'LAB-902', 'name' => 'Laboratorio 902']);

    $this->actingAs($this->admin);
    $this->asset = Asset::factory()->inSpace($this->space)->create(['acquired_on' => '2024-03-01']);
});

function historyEntry(Asset $asset, string $title): AssetHistoryEntry
{
    return AssetHistory::forAsset($asset)->sole(fn (AssetHistoryEntry $entry) => $entry->title === $title);
}

test('the history shows who changed the asset, with the old and new values', function () {
    app(UpdateAsset::class)->handle($this->admin, $this->asset, [
        'asset_type_id' => $this->asset->asset_type_id,
        'condition_id' => AssetCondition::query()->where('name', 'Regular')->value('id'),
        'model' => $this->asset->model,
        'acquired_on' => '2024-03-01',
        'observations' => 'Revisado por el técnico.',
    ]);

    $registration = historyEntry($this->asset, __('assets.history.changes.created'));
    $update = historyEntry($this->asset, __('assets.history.changes.updated'));

    expect($registration->actor)->toBe($this->admin->name)
        ->and($update->type)->toBe('change')
        ->and($update->actor)->toBe($this->admin->name)
        ->and($update->details)->toContain('Condición: Buen estado → Regular')
        ->and($update->details)->toContain('Observaciones: — → Revisado por el técnico.');
});

test('the history merges movements and events, newest first, each with the user responsible', function () {
    $this->actingAs($this->superuser);
    app(RecordAssetMovement::class)->handle($this->superuser, $this->asset, [
        'type' => 'transfer', 'to_space_id' => $this->destination->id, 'reason' => 'Reubicación del laboratorio',
    ]);
    app(RegisterAssetEvent::class)->handle($this->superuser, $this->asset->refresh(), [
        'kind' => 'loss', 'occurred_on' => today()->toDateString(), 'description' => 'No apareció en el inventario.',
    ]);

    $entries = AssetHistory::forAsset($this->asset);
    $titles = $entries->pluck('title')->all();
    $transfer = historyEntry($this->asset, 'Traslado');
    $loss = historyEntry($this->asset, 'Pérdida');

    expect(array_search('Traslado', $titles, true))->toBeLessThan(array_search('Ingreso', $titles, true))
        ->and($transfer->actor)->toBe($this->superuser->name)
        ->and($transfer->details)->toBe(['LAB-901 · Laboratorio 901 → LAB-902 · Laboratorio 902', 'Motivo: Reubicación del laboratorio'])
        ->and($loss->type)->toBe('event')
        ->and($loss->dateOnly)->toBeTrue()
        ->and($loss->details)->toBe(['No apareció en el inventario.'])
        ->and($loss->actor)->toBe($this->superuser->name);

    // The status change is the loss itself: no separate "Estado" change.
    expect($entries->flatMap->details->filter(fn (string $line) => str_starts_with($line, 'Estado:')))->toBeEmpty();
});

test('attached and removed files show in the history', function () {
    Storage::fake(config('filesystems.files_disk'));
    app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Photo, [UploadedFile::fake()->image('frente.jpg')]);

    expect(historyEntry($this->asset, __('assets.history.changes.attached'))->details)->toBe(['Fotografía: frente.jpg']);
});

test('everyone who may view the asset opens its history from the asset page', function () {
    $this->actingAs($this->admin);
    app(RegisterAssetEvent::class)->handle($this->admin, $this->asset, [
        'kind' => 'decommission', 'occurred_on' => today()->toDateString(), 'description' => 'Equipo obsoleto.',
    ]);

    actingAsRole(SystemRole::Visualizador);

    $this->get(route('assets.show', $this->asset))->assertOk()->assertSee(route('assets.history', $this->asset));
    $this->get(route('assets.history', $this->asset))
        ->assertOk()
        ->assertSee(__('assets.history.title'))
        ->assertSee('data-test="asset-history"', false)
        ->assertSeeInOrder(['Baja', 'Equipo obsoleto.', __('assets.history.by', ['user' => $this->admin->name])])
        ->assertSee(__('assets.history.changes.created'))
        ->assertDontSee(__('assets.history.empty'));
});

test('an event of today is placed at the exact time it was registered among the other entries', function () {
    $event = app(RegisterAssetEvent::class)->handle($this->admin, $this->asset, [
        'kind' => 'loss', 'occurred_on' => today()->toDateString(), 'description' => 'Extraviado.',
    ]);

    expect(historyEntry($this->asset, 'Pérdida')->at->getPreciseTimestamp())
        ->toBe($event->refresh()->created_at->getPreciseTimestamp());
});

test('an asset that has received no changes says its history is empty', function () {
    $untouched = Asset::withoutEvents(fn () => Asset::factory()->create());

    expect(AssetHistory::forAsset($untouched))->toBeEmpty();

    $this->get(route('assets.history', $untouched))
        ->assertOk()
        ->assertSee('data-test="history-empty"', false)
        ->assertSee(__('assets.history.empty'));
});

test('users who may not view assets cannot open a history', function () {
    actingAsRole(SystemRole::Reservante);

    $this->get(route('assets.history', $this->asset))->assertForbidden();
});

test('guests are sent to sign in', function () {
    auth()->logout();

    $this->get(route('assets.history', $this->asset))->assertRedirect(route('login'));
});
