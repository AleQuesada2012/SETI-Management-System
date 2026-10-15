<?php

use App\Actions\Assets\AttachAssetFiles;
use App\Actions\Assets\RemoveAssetFile;
use App\Enums\AssetFileKind;
use App\Models\Asset;
use App\Models\File;
use App\Support\Files\FileRules;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

/*
 * RF_20 · "Documentos" on the asset page (pages/assets/show): invoices, manuals and warranties, each opening in the
 * browser through the asset's authorized file route. Users who may manage the asset's files (★ `manageFiles`) attach
 * one or more documents and remove one after confirming. Built on RF_41's upload mechanism (App\Support\Files).
 */
new class extends Component {
    use WithFileUploads;

    #[Locked]
    public Asset $asset;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $documents = [];

    /** The document waiting for the user to confirm its removal. */
    public ?int $pendingRemovalId = null;

    /** @return Collection<int, File> */
    #[Computed]
    public function attached(): Collection
    {
        return $this->asset->files()->wherePivot('kind', AssetFileKind::Document->value)->with('uploader')->get();
    }

    #[Computed]
    public function pendingRemoval(): ?File
    {
        return $this->pendingRemovalId === null ? null : $this->attached->firstWhere('id', $this->pendingRemovalId);
    }

    /** Not called `upload`: `$wire.upload()` is Livewire's own JavaScript upload helper. */
    public function saveDocuments(AttachAssetFiles $attachAssetFiles): void
    {
        $this->authorize('manageFiles', $this->asset);

        try {
            $attached = $attachAssetFiles->handle(auth()->user(), $this->asset, AssetFileKind::Document, $this->documents);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => [
                    preg_replace('/^files/', 'documents', $key) => $messages,
                ])->all(),
            );
        }

        $this->reset('documents');
        unset($this->attached);
        Flux::toast(variant: 'success', text: trans_choice('assets.files.document.attached', $attached->count(), ['count' => $attached->count()]));
    }

    public function confirmRemoval(int $fileId): void
    {
        $this->authorize('manageFiles', $this->asset);

        $this->pendingRemovalId = $fileId;
        Flux::modal('remove-asset-document')->show();
    }

    public function remove(RemoveAssetFile $removeAssetFile): void
    {
        $this->authorize('manageFiles', $this->asset);

        $document = $this->pendingRemoval ?? abort(404);
        $removeAssetFile->handle(auth()->user(), $this->asset, $document);

        $this->pendingRemovalId = null;
        unset($this->attached, $this->pendingRemoval);
        Flux::modal('remove-asset-document')->close();
        Flux::toast(variant: 'success', text: __('assets.files.document.removed', ['file' => $document->original_name]));
    }
}; ?>

<flux:card class="flex flex-col gap-4" data-test="asset-documents">
    <flux:heading level="3">{{ __('assets.files.document.heading', ['count' => $this->attached->count()]) }}</flux:heading>

    @can('manageFiles', $asset)
        <form wire:submit="saveDocuments" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:field class="min-w-0 flex-1">
                <flux:label>{{ __('assets.files.document.choose') }}</flux:label>
                <div class="flex min-w-0 flex-wrap items-center gap-3">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-line bg-surface px-3 py-2 text-sm font-medium text-ink hover:border-brand has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand">
                        <flux:icon.document-text variant="micro" />
                        {{ __('assets.files.document.pick') }}
                        <input type="file" wire:model="documents" multiple accept="{{ implode(',', FileRules::DOCUMENT_MIME_TYPES) }}" class="sr-only" data-test="asset-documents-input" />
                    </label>
                    <flux:text class="min-w-0 truncate text-sm" data-test="asset-documents-chosen">
                        {{ $documents === [] ? __('assets.files.document.nothing_chosen') : trans_choice('assets.files.document.chosen', count($documents), ['count' => count($documents)]) }}
                    </flux:text>
                </div>
                <flux:description>{{ __('assets.files.document.help', ['max' => AttachAssetFiles::MAX_PER_UPLOAD]) }}</flux:description>
                <flux:error name="documents" />
                <flux:error name="documents.*" />
            </flux:field>
            <flux:button type="submit" variant="primary" icon="arrow-up-tray" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="documents,saveDocuments" data-test="asset-documents-upload">
                {{ __('assets.files.upload') }}
            </flux:button>
        </form>
        <flux:text wire:loading wire:target="documents" class="text-sm">{{ __('assets.files.uploading') }}</flux:text>
    @endcan

    @if ($this->attached->isEmpty())
        <flux:text data-test="asset-no-documents">{{ __('assets.files.document.none') }}</flux:text>
    @else
        <ul class="flex flex-col divide-y divide-line">
            @foreach ($this->attached as $document)
                <li wire:key="document-{{ $document->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2">
                    <flux:icon.document-text class="size-5 shrink-0 text-ink-muted" />
                    <div class="flex min-w-0 flex-1 flex-col">
                        <a href="{{ route('assets.files.show', [$asset, $document]) }}" target="_blank" rel="noopener" class="truncate font-medium text-ink hover:underline">{{ $document->original_name }}</a>
                        <span class="text-xs text-ink-muted">
                            {{ __('assets.files.meta', ['size' => Number::fileSize($document->size_bytes, maxPrecision: 1), 'date' => $document->created_at?->timezone(config('app.timezone'))->format('d/m/Y'), 'user' => $document->uploader?->name]) }}
                        </span>
                    </div>
                    @can('manageFiles', $asset)
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmRemoval({{ $document->id }})" :aria-label="__('assets.files.remove_label', ['file' => $document->original_name])">
                            {{ __('assets.files.remove') }}
                        </flux:button>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif

    @can('manageFiles', $asset)
        <flux:modal name="remove-asset-document" class="w-full max-w-md">
            <form wire:submit="remove" class="flex flex-col gap-5">
                <flux:heading size="lg">{{ __('assets.files.document.confirm_heading') }}</flux:heading>
                @if ($this->pendingRemoval)
                    <flux:text>{{ __('assets.files.confirm_help', ['file' => $this->pendingRemoval->original_name]) }}</flux:text>
                @endif
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('assets.files.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger" class="w-full sm:w-auto" data-test="asset-document-confirm-remove">{{ __('assets.files.confirm_submit') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</flux:card>
