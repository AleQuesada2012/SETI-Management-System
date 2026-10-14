<?php

use App\Actions\Assets\DeleteAsset;
use App\Models\Asset;
use Flux\Flux;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * RF_02 · "Eliminar" on the asset page (pages/assets/show). Only users who may delete this asset see it (★). The
 * button opens a confirmation; cancelling it changes nothing. It keeps the asset's id rather than the model, so an asset
 * deleted meanwhile (e.g. from another tab) is reported as no longer existing instead of failing to load.
 */
new class extends Component {
    #[Locked]
    public int $assetId;

    #[Locked]
    public string $assetCode;

    public function mount(Asset $asset): void
    {
        $this->assetId = $asset->id;
        $this->assetCode = $asset->code;
    }

    public function delete(): void
    {
        $asset = Asset::query()->find($this->assetId);

        if ($asset === null) {
            $this->gone();

            return;
        }

        $this->authorize('delete', $asset);

        try {
            app(DeleteAsset::class)->handle(auth()->user(), $asset);
        } catch (ModelNotFoundException) {
            $this->gone();

            return;
        }

        Flux::toast(variant: 'success', text: __('assets.delete.done', ['asset' => $this->assetCode]));

        $this->redirectRoute('assets.index', navigate: true);
    }

    private function gone(): void
    {
        Flux::toast(variant: 'danger', text: __('assets.delete.not_found', ['asset' => $this->assetCode]));

        $this->redirectRoute('assets.index', navigate: true);
    }
}; ?>

<div>
    <flux:modal.trigger name="delete-asset">
        <flux:button variant="danger" icon="trash" size="sm" data-test="delete-asset-button">{{ __('assets.delete.button') }}</flux:button>
    </flux:modal.trigger>

    <flux:modal name="delete-asset" class="w-full max-w-md">
        <div class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">{{ __('assets.delete.title', ['asset' => $assetCode]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('assets.delete.description') }}</flux:text>
            </div>

            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>{{ __('assets.delete.not_decommission') }}</flux:callout.text>
            </flux:callout>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost" class="w-full sm:w-auto" data-test="cancel-delete-asset">{{ __('assets.delete.cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="delete" variant="danger" class="w-full sm:w-auto" data-test="confirm-delete-asset">{{ __('assets.delete.confirm') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
