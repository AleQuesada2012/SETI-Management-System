<?php

// RF_21 · Adjuntar fotografías a los activos (on RF_41's upload mechanism and RF_20's asset file Actions).

use App\Actions\Assets\AttachAssetFiles;
use App\Actions\Assets\RemoveAssetFile;
use App\Enums\AssetFileKind;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\Space;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(config('filesystems.files_disk'));

    $this->admin = userWithRole(SystemRole::Administrador);
    $this->asset = Asset::factory()->inSpace(Space::factory()->managedBy($this->admin)->create())->create();
});

test('an Administrador of the asset\'s space adds several photos, with phone thumbnails, and it is audited', function () {
    $this->actingAs($this->admin);

    Livewire::test('assets.asset-photos', ['asset' => $this->asset])
        ->set('photos', [UploadedFile::fake()->image('frente.jpg', 1600, 1200), UploadedFile::fake()->image('placa.png', 600, 400)])
        ->call('savePhotos')
        ->assertHasNoErrors()
        ->assertSet('photos', [])
        ->assertSee('frente.jpg');

    $photos = $this->asset->files()->get();
    expect($photos->pluck('pivot.kind')->unique()->all())->toBe(['photo'])
        ->and($photos->pluck('thumbnail_path')->filter()->count())->toBe(2);
    Storage::disk(config('filesystems.files_disk'))->assertExists($photos->pluck('thumbnail_path')->all());

    expect(AuditLog::query()->where(['action' => 'attached', 'entity_type' => 'asset', 'entity_id' => $this->asset->id])->pluck('new_values')->pluck('photo')->all())
        ->toBe(['frente.jpg', 'placa.png']);
});

test('photos and documents of the same asset stay apart', function () {
    app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Document, [UploadedFile::fake()->create('factura.pdf', 50, 'application/pdf')]);
    app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Photo, [UploadedFile::fake()->image('frente.jpg')]);
    $this->actingAs($this->admin);

    Livewire::test('assets.asset-photos', ['asset' => $this->asset])->assertSee('frente.jpg')->assertDontSee('factura.pdf');
    Livewire::test('assets.asset-documents', ['asset' => $this->asset])->assertSee('factura.pdf')->assertDontSee('frente.jpg');
});

test('photos can be seen from the asset by anyone who can view it', function () {
    [$photo] = app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Photo, [UploadedFile::fake()->image('frente.jpg', 900, 600)]);
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('assets.show', $this->asset))->assertOk()->assertSee('frente.jpg');
    $this->get(route('assets.files.show', [$this->asset, $photo]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->get(route('assets.files.show', [$this->asset, $photo, 'miniatura' => 1]))->assertOk()->assertHeader('Content-Type', 'image/webp');
});

test('only allowed image formats are accepted', function (UploadedFile $upload) {
    expect(fn () => app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Photo, [$upload]))
        ->toThrow(ValidationException::class);

    expect(File::query()->count())->toBe(0);
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('foto.pdf', 50, 'application/pdf'),
    'svg' => fn () => UploadedFile::fake()->createWithContent('foto.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
    'over 10 MB' => fn () => UploadedFile::fake()->image('enorme.jpg')->size(10 * 1024 + 1),
]);

test('the format error is shown under the photos field', function () {
    $this->actingAs($this->admin);

    Livewire::test('assets.asset-photos', ['asset' => $this->asset])
        ->set('photos', [UploadedFile::fake()->create('foto.pdf', 50, 'application/pdf')])
        ->call('savePhotos')
        ->assertHasErrors('photos.0');
});

test('a user with permission removes a photo after confirming: row, bytes and thumbnail go, and it is audited', function () {
    [$photo] = app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Photo, [UploadedFile::fake()->image('vieja.jpg', 900, 600)]);
    $this->actingAs($this->admin);

    Livewire::test('assets.asset-photos', ['asset' => $this->asset])
        ->call('confirmRemoval', $photo->id)
        ->call('remove')
        ->assertHasNoErrors()
        ->assertSee(__('assets.files.photo.none'));

    expect(File::query()->whereKey($photo->id)->exists())->toBeFalse();
    Storage::disk(config('filesystems.files_disk'))->assertMissing([$photo->path, $photo->thumbnail_path]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'detached', 'entity_type' => 'asset', 'entity_id' => $this->asset->id]);
});

test('users without the needed role are blocked with an authorization error', function (SystemRole $role) {
    [$photo] = app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Photo, [UploadedFile::fake()->image('frente.jpg')]);
    $user = actingAsRole($role);

    Livewire::test('assets.asset-photos', ['asset' => $this->asset])
        ->assertDontSee(__('assets.files.photo.pick'))
        ->set('photos', [UploadedFile::fake()->image('otra.jpg')])
        ->call('savePhotos')
        ->assertForbidden();

    expect(fn () => app(RemoveAssetFile::class)->handle($user, $this->asset, $photo))->toThrow(AuthorizationException::class);
    expect($this->asset->files()->count())->toBe(1);
})->with([SystemRole::Visualizador, SystemRole::Reservante, SystemRole::Reportes, SystemRole::Administrador]);
