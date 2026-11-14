<?php

// RF_41 · Adjuntar fotografías a un espacio (and the shared upload infrastructure in App\Support\Files).

use App\Actions\Spaces\AttachSpacePhotos;
use App\Actions\Spaces\RemoveSpacePhoto;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\Space;
use App\Models\User;
use App\Support\Files\FileRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    Storage::fake(config('filesystems.files_disk'));

    $this->admin = userWithRole(SystemRole::Administrador);
    $this->space = Space::factory()->managedBy($this->admin)->create();
});

function spacePhotosDisk(): Filesystem
{
    return Storage::disk(config('filesystems.files_disk'));
}

test('a manager uploads several photos: stored privately with a phone thumbnail, linked and audited', function () {
    $this->actingAs($this->admin);

    Livewire::test('spaces.space-photos', ['space' => $this->space])
        ->set('photos', [UploadedFile::fake()->image('aula.jpg', 1600, 1200), UploadedFile::fake()->image('pizarra.png', 300, 200)])
        ->call('savePhotos')
        ->assertHasNoErrors()
        ->assertSet('photos', [])
        ->assertSee('aula.jpg');

    $photos = $this->space->photos()->get();
    expect($photos->pluck('original_name')->all())->toBe(['aula.jpg', 'pizarra.png'])
        ->and($photos->pluck('pivot.sort_order')->all())->toBe([1, 2]);

    $photo = $photos->first();
    expect($photo->mime_type)->toBe('image/jpeg')
        ->and([$photo->width, $photo->height])->toBe([1600, 1200])
        ->and($photo->sha256)->toMatch('/^[0-9a-f]{64}$/')
        ->and($photo->uploaded_by)->toBe($this->admin->id);
    spacePhotosDisk()->assertExists([$photo->path, $photo->thumbnail_path]);
    expect(getimagesizefromstring(spacePhotosDisk()->get($photo->thumbnail_path))[0])->toBe(400);

    expect($photos->last()->thumbnail_path)->not->toBeNull()
        ->and(getimagesizefromstring(spacePhotosDisk()->get($photos->last()->thumbnail_path))[0])->toBe(300);

    expect(AuditLog::query()->where(['action' => 'attached', 'entity_type' => 'space', 'entity_id' => $this->space->id, 'actor_id' => $this->admin->id])->orderBy('id')->pluck('new_values')->pluck('photo')->all())
        ->toBe(['aula.jpg', 'pizarra.png']);
});

test('files that are not images are rejected', function (UploadedFile $upload) {
    expect(fn () => app(AttachSpacePhotos::class)->handle($this->admin, $this->space, [$upload]))
        ->toThrow(ValidationException::class);

    expect(File::query()->count())->toBe(0);
    expect(spacePhotosDisk()->allFiles())->toBe([]);
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('plano.pdf', 100, 'application/pdf'),
    'text' => fn () => UploadedFile::fake()->createWithContent('notas.txt', 'hola'),
    'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
]);

test('an image larger than 10 MB is rejected', function () {
    $big = UploadedFile::fake()->image('enorme.jpg')->size(10 * 1024 + 1);

    expect(fn () => app(AttachSpacePhotos::class)->handle($this->admin, $this->space, [$big]))
        ->toThrow(ValidationException::class);
});

test('at least one photo is required, and no more than ten at once', function () {
    expect(fn () => app(AttachSpacePhotos::class)->handle($this->admin, $this->space, []))->toThrow(ValidationException::class);

    $eleven = array_map(fn (int $i) => UploadedFile::fake()->image("foto{$i}.jpg"), range(1, 11));
    expect(fn () => app(AttachSpacePhotos::class)->handle($this->admin, $this->space, $eleven))->toThrow(ValidationException::class);
});

test('anyone who can view the space sees its photos and their thumbnails', function () {
    [$photo] = app(AttachSpacePhotos::class)->handle($this->admin, $this->space, [UploadedFile::fake()->image('aula.jpg', 800, 600)]);
    actingAsRole(SystemRole::Reservante);

    $this->get(route('spaces.show', $this->space))->assertOk()->assertSee('aula.jpg');
    $this->get(route('spaces.photos.show', [$this->space, $photo]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->get(route('spaces.photos.show', [$this->space, $photo, 'miniatura' => 1]))->assertOk()->assertHeader('Content-Type', 'image/webp');
});

test('a photo is only served through its own space', function () {
    [$photo] = app(AttachSpacePhotos::class)->handle($this->admin, $this->space, [UploadedFile::fake()->image('aula.jpg')]);
    actingAsRole(SystemRole::Visualizador);

    $this->get(route('spaces.photos.show', [Space::factory()->create(), $photo]))->assertNotFound();
});

test('a user who cannot view spaces cannot open a photo', function () {
    [$photo] = app(AttachSpacePhotos::class)->handle($this->admin, $this->space, [UploadedFile::fake()->image('aula.jpg')]);
    $this->actingAs(User::factory()->create());

    $this->get(route('spaces.photos.show', [$this->space, $photo]))->assertForbidden();
});

test('a manager removes a photo after confirming: row, bytes and thumbnail go, and it is audited', function () {
    [$photo] = app(AttachSpacePhotos::class)->handle($this->admin, $this->space, [UploadedFile::fake()->image('aula.jpg', 900, 600)]);
    $this->actingAs($this->admin);

    Livewire::test('spaces.space-photos', ['space' => $this->space])
        ->call('confirmRemoval', $photo->id)
        ->assertSet('pendingRemovalId', $photo->id)
        ->call('remove')
        ->assertHasNoErrors()
        ->assertSee(__('spaces.photos.none'));

    expect(File::query()->whereKey($photo->id)->exists())->toBeFalse()
        ->and($this->space->photos()->count())->toBe(0);
    spacePhotosDisk()->assertMissing([$photo->path, $photo->thumbnail_path]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'detached', 'entity_type' => 'space', 'entity_id' => $this->space->id]);
});

test('a photo of another space cannot be removed through this one', function () {
    $otherSpace = Space::factory()->managedBy($this->admin)->create();
    [$photo] = app(AttachSpacePhotos::class)->handle($this->admin, $otherSpace, [UploadedFile::fake()->image('aula.jpg')]);

    expect(fn () => app(RemoveSpacePhoto::class)->handle($this->admin, $this->space, $photo))
        ->toThrow(NotFoundHttpException::class);
    expect(File::query()->whereKey($photo->id)->exists())->toBeTrue();
});

test('an Administrador of another space and read-only roles cannot upload or remove photos', function () {
    [$photo] = app(AttachSpacePhotos::class)->handle($this->admin, $this->space, [UploadedFile::fake()->image('aula.jpg')]);

    foreach ([userWithRole(SystemRole::Administrador), userWithRole(SystemRole::Visualizador)] as $user) {
        $this->actingAs($user);

        Livewire::test('spaces.space-photos', ['space' => $this->space])
            ->assertDontSee(__('spaces.photos.upload'))
            ->set('photos', [UploadedFile::fake()->image('otra.jpg')])
            ->call('savePhotos')
            ->assertForbidden();

        expect(fn () => app(RemoveSpacePhoto::class)->handle($user, $this->space, $photo))->toThrow(AuthorizationException::class);
    }

    expect($this->space->photos()->count())->toBe(1);
});

test('shared document validation applies the image limit before any bytes are stored', function () {
    $validator = Validator::make(['document' => UploadedFile::fake()->image('recibo.jpg')->size(10 * 1024 + 1)],
        ['document' => FileRules::document()]);
    expect($validator->fails())->toBeTrue()->and($validator->errors()->first('document'))->toContain('10240');
    expect(File::query()->exists())->toBeFalse();
});
