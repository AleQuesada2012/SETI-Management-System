<?php

use App\Actions\Spaces\AttachSpacePhotos;
use App\Actions\Spaces\RemoveSpacePhoto;
use App\Models\File;
use App\Models\Space;
use App\Support\Files\FileRules;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * RF_41 · "Fotografías" on the space page (pages/spaces/show): a grid of phone thumbnails that open the full photo,
 * and, for users who may manage the space's photos (★), uploading one or more photos and removing one after confirming.
 */
new class extends Component {
    use WithFileUploads;

    #[Locked]
    public Space $space;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $photos = [];

    /** The photo waiting for the user to confirm its removal. */
    public ?int $pendingRemovalId = null;

    /** @return Collection<int, File> */
    #[Computed]
    public function gallery(): Collection
    {
        return $this->space->photos()->get();
    }

    #[Computed]
    public function pendingRemoval(): ?File
    {
        return $this->pendingRemovalId === null ? null : $this->gallery->firstWhere('id', $this->pendingRemovalId);
    }

    public function savePhotos(AttachSpacePhotos $attachSpacePhotos): void
    {
        $this->authorize('managePhotos', $this->space);

        $attached = $attachSpacePhotos->handle(auth()->user(), $this->space, $this->photos);

        $this->reset('photos');
        unset($this->gallery);
        Flux::toast(variant: 'success', text: trans_choice('spaces.photos.attached', $attached->count(), ['count' => $attached->count()]));
    }

    public function confirmRemoval(int $fileId): void
    {
        $this->authorize('managePhotos', $this->space);

        $this->pendingRemovalId = $fileId;
        Flux::modal('remove-space-photo')->show();
    }

    public function remove(RemoveSpacePhoto $removeSpacePhoto): void
    {
        $this->authorize('managePhotos', $this->space);

        $photo = $this->pendingRemoval ?? abort(404);
        $removeSpacePhoto->handle(auth()->user(), $this->space, $photo);

        $this->pendingRemovalId = null;
        unset($this->gallery, $this->pendingRemoval);
        Flux::modal('remove-space-photo')->close();
        Flux::toast(variant: 'success', text: __('spaces.photos.removed', ['photo' => $photo->original_name]));
    }
}; ?>

<flux:card class="flex flex-col gap-4">
    <flux:heading size="md" level="3">{{ __('spaces.photos.heading', ['count' => $this->gallery->count()]) }}</flux:heading>

    @can('managePhotos', $space)
        <form wire:submit="savePhotos" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:field class="min-w-0 flex-1">
                <flux:label>{{ __('spaces.photos.choose') }}</flux:label>
                <div class="flex min-w-0 flex-wrap items-center gap-3">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-line bg-surface px-3 py-2 text-sm font-medium text-ink hover:border-brand has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand">
                        <flux:icon.photo variant="micro" />
                        {{ __('spaces.photos.pick') }}
                        <input type="file" wire:model="photos" multiple accept="{{ implode(',', FileRules::IMAGE_MIME_TYPES) }}" class="sr-only" data-test="space-photos-input" />
                    </label>
                    <flux:text class="min-w-0 truncate text-sm" data-test="space-photos-chosen">
                        {{ $photos === [] ? __('spaces.photos.nothing_chosen') : trans_choice('spaces.photos.chosen', count($photos), ['count' => count($photos)]) }}
                    </flux:text>
                </div>
                <flux:description>{{ __('spaces.photos.help', ['max' => AttachSpacePhotos::MAX_PER_UPLOAD]) }}</flux:description>
                <flux:error name="photos" />
                <flux:error name="photos.*" />
            </flux:field>
            <flux:button type="submit" variant="primary" icon="arrow-up-tray" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="photos,savePhotos" data-test="space-photos-upload">
                {{ __('spaces.photos.upload') }}
            </flux:button>
        </form>
        <flux:text wire:loading wire:target="photos" class="text-sm">{{ __('spaces.photos.uploading') }}</flux:text>
    @endcan

    @if ($this->gallery->isEmpty())
        <flux:text data-test="space-no-photos">{{ __('spaces.photos.none') }}</flux:text>
    @else
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($this->gallery as $photo)
                <li wire:key="photo-{{ $photo->id }}" class="flex min-w-0 flex-col gap-2">
                    <a href="{{ route('spaces.photos.show', [$space, $photo]) }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-line bg-canvas">
                        <img src="{{ route('spaces.photos.show', [$space, $photo, 'miniatura' => 1]) }}" alt="{{ $photo->original_name }}" loading="lazy"
                            class="aspect-[4/3] w-full object-cover" @if ($photo->width && $photo->height) width="{{ $photo->width }}" height="{{ $photo->height }}" @endif />
                    </a>
                    @can('managePhotos', $space)
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmRemoval({{ $photo->id }})" :aria-label="__('spaces.photos.remove_label', ['photo' => $photo->original_name])">
                            {{ __('spaces.photos.remove') }}
                        </flux:button>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif

    @can('managePhotos', $space)
        <flux:modal name="remove-space-photo" class="w-full max-w-md">
            <form wire:submit="remove" class="flex flex-col gap-5">
                <flux:heading size="lg">{{ __('spaces.photos.confirm_heading') }}</flux:heading>
                @if ($this->pendingRemoval)
                    <img src="{{ route('spaces.photos.show', [$space, $this->pendingRemoval, 'miniatura' => 1]) }}" alt="{{ $this->pendingRemoval->original_name }}" class="max-h-48 w-full rounded-lg object-contain" />
                    <flux:text>{{ __('spaces.photos.confirm_help', ['photo' => $this->pendingRemoval->original_name]) }}</flux:text>
                @endif
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('spaces.create.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger" class="w-full sm:w-auto" data-test="space-photo-confirm-remove">{{ __('spaces.photos.confirm_submit') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</flux:card>
