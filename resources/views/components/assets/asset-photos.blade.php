<?php

use App\Actions\Assets\AttachAssetFiles;
use App\Actions\Assets\RemoveAssetFile;
use App\Enums\AssetFileKind;
use App\Models\Asset;
use App\Models\File;
use App\Support\Files\FileRules;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * RF_21 · "Fotografías" on the asset page (pages/assets/show): phone thumbnails that open the full photo, to identify
 * the asset and see its physical state. Users who may manage the asset's files (★ `manageFiles`) add one or more
 * photos and remove one after confirming. Built on RF_41's upload mechanism and RF_20's asset file Actions. A
 * decommissioned asset shows its photos read-only.
 */
new class extends Component {
    use WithFileUploads;

    #[Locked]
    public Asset $asset;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $photos = [];

    /** The photo waiting for the user to confirm its removal. */
    public ?int $pendingRemovalId = null;

    /** @return Collection<int, File> */
    #[Computed]
    public function gallery(): Collection
    {
        return $this->asset->files()->wherePivot('kind', AssetFileKind::Photo->value)->get();
    }

    /** ★ `manageFiles`, and the asset is not decommissioned (read-only, Asset::isReadOnly). */
    public function canManage(): bool
    {
        return ! $this->asset->isReadOnly() && (auth()->user()?->can('manageFiles', $this->asset) ?? false);
    }

    #[Computed]
    public function pendingRemoval(): ?File
    {
        return $this->pendingRemovalId === null ? null : $this->gallery->firstWhere('id', $this->pendingRemovalId);
    }

    /** Not called `upload`: `$wire.upload()` is Livewire's own JavaScript upload helper. */
    public function savePhotos(AttachAssetFiles $attachAssetFiles): void
    {
        $this->authorize('manageFiles', $this->asset);

        try {
            $attached = $attachAssetFiles->handle(auth()->user(), $this->asset, AssetFileKind::Photo, $this->photos);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => [
                    preg_replace('/^files/', 'photos', $key) => $messages,
                ])->all(),
            );
        }

        $this->reset('photos');
        unset($this->gallery);
        Flux::toast(variant: 'success', text: trans_choice('assets.files.photo.attached', $attached->count(), ['count' => $attached->count()]));
    }

    public function confirmRemoval(int $fileId): void
    {
        $this->authorize('manageFiles', $this->asset);

        $this->pendingRemovalId = $fileId;
        Flux::modal('remove-asset-photo')->show();
    }

    public function remove(RemoveAssetFile $removeAssetFile): void
    {
        $this->authorize('manageFiles', $this->asset);

        $photo = $this->pendingRemoval ?? abort(404);
        $removeAssetFile->handle(auth()->user(), $this->asset, $photo);

        $this->pendingRemovalId = null;
        unset($this->gallery, $this->pendingRemoval);
        Flux::modal('remove-asset-photo')->close();
        Flux::toast(variant: 'success', text: __('assets.files.photo.removed', ['file' => $photo->original_name]));
    }
}; ?>

<flux:card class="flex flex-col gap-4" data-test="asset-photos">
    <flux:heading level="3">{{ __('assets.files.photo.heading', ['count' => $this->gallery->count()]) }}</flux:heading>

    @if ($this->canManage())
        <form wire:submit="savePhotos" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:field class="min-w-0 flex-1">
                <flux:label>{{ __('assets.files.photo.choose') }}</flux:label>
                <div class="flex min-w-0 flex-wrap items-center gap-3">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-line bg-surface px-3 py-2 text-sm font-medium text-ink hover:border-brand has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand">
                        <flux:icon.photo variant="micro" />
                        {{ __('assets.files.photo.pick') }}
                        <input type="file" wire:model="photos" multiple accept="{{ implode(',', FileRules::IMAGE_MIME_TYPES) }}" class="sr-only" data-test="asset-photos-input" />
                    </label>
                    <flux:text class="min-w-0 truncate text-sm" data-test="asset-photos-chosen">
                        {{ $photos === [] ? __('assets.files.photo.nothing_chosen') : trans_choice('assets.files.photo.chosen', count($photos), ['count' => count($photos)]) }}
                    </flux:text>
                </div>
                <flux:description>{{ __('assets.files.photo.help', ['max' => AttachAssetFiles::MAX_PER_UPLOAD]) }}</flux:description>
                <flux:error name="photos" />
                <flux:error name="photos.*" />
            </flux:field>
            <flux:button type="submit" variant="primary" icon="arrow-up-tray" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="photos,savePhotos" data-test="asset-photos-upload">
                {{ __('assets.files.upload') }}
            </flux:button>
        </form>
        <flux:text wire:loading wire:target="photos" class="text-sm">{{ __('assets.files.uploading') }}</flux:text>
    @endif

    @if ($this->gallery->isEmpty())
        <flux:text data-test="asset-no-photos">{{ __('assets.files.photo.none') }}</flux:text>
    @else
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($this->gallery as $photo)
                <li wire:key="photo-{{ $photo->id }}" class="flex min-w-0 flex-col gap-2">
                    <a href="{{ route('assets.files.show', [$asset, $photo]) }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-line bg-canvas">
                        <img src="{{ route('assets.files.show', [$asset, $photo, 'miniatura' => 1]) }}" alt="{{ $photo->original_name }}" loading="lazy"
                            class="aspect-[4/3] w-full object-cover" @if ($photo->width && $photo->height) width="{{ $photo->width }}" height="{{ $photo->height }}" @endif />
                    </a>
                    @if ($this->canManage())
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmRemoval({{ $photo->id }})" :aria-label="__('assets.files.remove_label', ['file' => $photo->original_name])">
                            {{ __('assets.files.remove') }}
                        </flux:button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($this->canManage())
        <flux:modal name="remove-asset-photo" class="w-full max-w-md">
            <form wire:submit="remove" class="flex flex-col gap-5">
                <flux:heading size="lg">{{ __('assets.files.photo.confirm_heading') }}</flux:heading>
                @if ($this->pendingRemoval)
                    <img src="{{ route('assets.files.show', [$asset, $this->pendingRemoval, 'miniatura' => 1]) }}" alt="{{ $this->pendingRemoval->original_name }}" class="max-h-48 w-full rounded-lg object-contain" />
                    <flux:text>{{ __('assets.files.confirm_help', ['file' => $this->pendingRemoval->original_name]) }}</flux:text>
                @endif
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('assets.files.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger" class="w-full sm:w-auto" data-test="asset-photo-confirm-remove">{{ __('assets.files.confirm_submit') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</flux:card>
