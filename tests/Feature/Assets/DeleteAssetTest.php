<?php

// RF_02 · Eliminar activo: a soft delete of an asset registered by mistake, never a decommission (RF_29).

use App\Actions\Assets\DeleteAsset;
use App\Enums\AssetStatus;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AuditLog;
use App\Models\Space;
use App\Queries\Assets\AssetInventory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create();
    $this->asset = Asset::factory()->inSpace($this->space)->create(['asset_tag' => 'TEC-40001']);
});

test('an Administrador deletes an asset of their space, which leaves the inventory but keeps its history', function () {
    $this->actingAs($this->admin);

    $this->get(route('assets.show', $this->asset))
        ->assertOk()
        ->assertSee('data-test="delete-asset-button"', false)
        ->assertSee(__('assets.delete.description'));

    Livewire::test('assets.delete-asset', ['asset' => $this->asset])
        ->call('delete')
        ->assertDispatched('toast-show', slots: ['text' => __('assets.delete.done', ['asset' => $this->asset->code])])
        ->assertRedirect(route('assets.index'));

    $deleted = Asset::withTrashed()->findOrFail($this->asset->id);

    expect($deleted->trashed())->toBeTrue()
        ->and(Asset::query()->find($this->asset->id))->toBeNull()
        ->and(AssetInventory::query([])->pluck('code')->all())->not->toContain($this->asset->code)
        ->and(AssetMovement::query()->where('asset_id', $this->asset->id)->firstOrFail()->asset->is($deleted))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'deleted',
        'entity_type' => 'asset',
        'entity_id' => $this->asset->id,
        'actor_id' => $this->admin->id,
    ]);
    $this->get(route('assets.show', $this->asset))->assertNotFound();
});

test('cancelling the confirmation keeps the asset as the database has it', function () {
    $this->actingAs($this->admin);

    Livewire::test('assets.delete-asset', ['asset' => $this->asset])
        ->assertSee(__('assets.delete.title', ['asset' => $this->asset->code]))
        ->assertSee('data-test="cancel-delete-asset"', false)
        ->assertNoRedirect();

    expect($this->asset->refresh()->trashed())->toBeFalse();
    $this->assertDatabaseMissing('audit_logs', ['action' => 'deleted', 'entity_id' => $this->asset->id]);
    $this->get(route('assets.show', $this->asset))->assertOk()->assertSee($this->asset->code);
});

test('an asset that no longer exists is reported instead of deleted again', function () {
    $this->actingAs($this->admin);
    $component = Livewire::test('assets.delete-asset', ['asset' => $this->asset]);

    app(DeleteAsset::class)->handle($this->admin, $this->asset);

    $component->call('delete')
        ->assertDispatched('toast-show', slots: ['text' => __('assets.delete.not_found', ['asset' => $this->asset->code])])
        ->assertRedirect(route('assets.index'));

    expect(fn () => app(DeleteAsset::class)->handle($this->admin, $this->asset))->toThrow(ModelNotFoundException::class);
    expect(AuditLog::query()->where('action', 'deleted')->where('entity_id', $this->asset->id)->count())->toBe(1);
});

test('the Superusuario deletes an asset of any space', function () {
    $superuser = actingAsRole(SystemRole::Superusuario);
    $other = Asset::factory()->inSpace(Space::factory()->create())->create();

    $this->get(route('assets.show', $other))->assertSee('data-test="delete-asset-button"', false);

    app(DeleteAsset::class)->handle($superuser, $other);

    expect($other->refresh()->trashed())->toBeTrue();
});

test('an Administrador who does not manage the asset\'s space cannot delete it', function () {
    $this->actingAs($this->admin);
    $other = Asset::factory()->inSpace(Space::factory()->create())->create();

    $this->get(route('assets.show', $other))->assertOk()->assertDontSee('data-test="delete-asset-button"', false);

    Livewire::test('assets.delete-asset', ['asset' => $other])->call('delete')->assertForbidden();

    expect(fn () => app(DeleteAsset::class)->handle($this->admin, $other))->toThrow(AuthorizationException::class)
        ->and($other->refresh()->trashed())->toBeFalse();
});

test('roles without assets.delete cannot delete assets', function (SystemRole $role) {
    $user = actingAsRole($role);

    if ($user->can('view', $this->asset)) {
        $this->get(route('assets.show', $this->asset))->assertOk()->assertDontSee('data-test="delete-asset-button"', false);
    }

    Livewire::test('assets.delete-asset', ['asset' => $this->asset])->call('delete')->assertForbidden();

    expect(fn () => app(DeleteAsset::class)->handle($user, $this->asset))->toThrow(AuthorizationException::class)
        ->and($this->asset->refresh()->trashed())->toBeFalse();
})->with([SystemRole::Visualizador, SystemRole::Reportes, SystemRole::Reservante]);

test('an asset may be deleted whatever its lifecycle status', function (AssetStatus $status) {
    $this->asset->forceFill(['status' => $status])->save();

    app(DeleteAsset::class)->handle($this->admin, $this->asset);

    expect($this->asset->refresh()->trashed())->toBeTrue();
})->with(AssetStatus::cases());

test('a deleted asset frees its tag for a new asset', function () {
    app(DeleteAsset::class)->handle($this->admin, $this->asset);

    $replacement = Asset::factory()->inSpace($this->space)->create(['asset_tag' => 'tec-40001']);

    expect($replacement->asset_tag)->toBe('tec-40001');
});
