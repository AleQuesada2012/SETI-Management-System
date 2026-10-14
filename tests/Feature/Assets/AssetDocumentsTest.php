<?php

// RF_20 · Adjuntar documentos a los activos (on RF_41's upload mechanism).

use App\Actions\Assets\AttachAssetFiles;
use App\Actions\Assets\RemoveAssetFile;
use App\Enums\AssetFileKind;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\Space;
use App\Models\User;
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

function invoice(string $name = 'factura.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 200, 'application/pdf');
}

test('an Administrador of the asset\'s space attaches documents from the asset page, and it is audited', function () {
    $this->actingAs($this->admin);

    Livewire::test('assets.asset-documents', ['asset' => $this->asset])
        ->set('documents', [invoice('factura-2026.pdf'), invoice('manual.pdf')])
        ->call('saveDocuments')
        ->assertHasNoErrors()
        ->assertSet('documents', [])
        ->assertSee('factura-2026.pdf')
        ->assertSee('manual.pdf');

    $documents = $this->asset->files()->get();
    expect($documents->pluck('pivot.kind')->unique()->all())->toBe(['document'])
        ->and($documents->first()->mime_type)->toBe('application/pdf')
        ->and($documents->first()->thumbnail_path)->toBeNull();
    Storage::disk(config('filesystems.files_disk'))->assertExists($documents->pluck('path')->all());

    expect(AuditLog::query()->where(['action' => 'attached', 'entity_type' => 'asset', 'entity_id' => $this->asset->id])->pluck('new_values')->pluck('document')->all())
        ->toBe(['factura-2026.pdf', 'manual.pdf']);
});

test('the Superusuario attaches documents to any asset', function () {
    app(AttachAssetFiles::class)->handle(userWithRole(SystemRole::Superusuario), $this->asset, AssetFileKind::Document, [invoice()]);

    expect($this->asset->files()->count())->toBe(1);
});

test('documents can be consulted from the asset by anyone who can view it', function () {
    [$document] = app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Document, [invoice('garantia.pdf')]);
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('assets.show', $this->asset))->assertOk()->assertSee('garantia.pdf');
    $this->get(route('assets.files.show', [$this->asset, $document]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=garantia.pdf');
});

test('a document is only served through its own asset', function () {
    [$document] = app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Document, [invoice()]);
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('assets.files.show', [Asset::factory()->create(), $document]))->assertNotFound();
});

test('common formats are accepted and others are rejected with a Spanish message', function () {
    $this->actingAs($this->admin);

    app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Document, [UploadedFile::fake()->image('recibo.jpg')]);
    expect($this->asset->files()->count())->toBe(1);

    Livewire::test('assets.asset-documents', ['asset' => $this->asset])
        ->set('documents', [UploadedFile::fake()->createWithContent('macro.exe', 'MZ')])
        ->call('saveDocuments')
        ->assertHasErrors('documents.0');

    expect(fn () => app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Document, [UploadedFile::fake()->create('enorme.pdf', 20 * 1024 + 1, 'application/pdf')]))
        ->toThrow(ValidationException::class);
    expect($this->asset->files()->count())->toBe(1);
});

test('a user with permission removes a document after confirming: row and bytes go, and it is audited', function () {
    [$document] = app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Document, [invoice('vieja.pdf')]);
    $this->actingAs($this->admin);

    Livewire::test('assets.asset-documents', ['asset' => $this->asset])
        ->call('confirmRemoval', $document->id)
        ->call('remove')
        ->assertHasNoErrors()
        ->assertSee(__('assets.files.document.none'));

    expect(File::query()->whereKey($document->id)->exists())->toBeFalse();
    Storage::disk(config('filesystems.files_disk'))->assertMissing($document->path);
    $this->assertDatabaseHas('audit_logs', ['action' => 'detached', 'entity_type' => 'asset', 'entity_id' => $this->asset->id]);
});

test('users without the needed role are blocked with an authorization error', function (SystemRole $role) {
    [$document] = app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Document, [invoice()]);
    $user = actingAsRole($role);

    Livewire::test('assets.asset-documents', ['asset' => $this->asset])
        ->assertDontSee(__('assets.files.document.pick'))
        ->set('documents', [invoice('otra.pdf')])
        ->call('saveDocuments')
        ->assertForbidden();

    expect(fn () => app(RemoveAssetFile::class)->handle($user, $this->asset, $document))->toThrow(AuthorizationException::class);
    expect($this->asset->files()->count())->toBe(1);
})->with([SystemRole::Visualizador, SystemRole::Reservante, SystemRole::Reportes, SystemRole::Administrador]);

test('a user who cannot view assets cannot open a document', function () {
    [$document] = app(AttachAssetFiles::class)->handle($this->admin, $this->asset, AssetFileKind::Document, [invoice()]);
    $this->actingAs(User::factory()->create());

    $this->get(route('assets.files.show', [$this->asset, $document]))->assertForbidden();
});
