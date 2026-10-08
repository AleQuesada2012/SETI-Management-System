<?php

use App\Actions\Assets\RecordAssetMovement;
use App\Enums\AssetStatus;
use App\Enums\MovementType;
use App\Models\Asset;
use App\Models\Space;
use App\Queries\Assets\AssetFormChoices;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * RF_25 · Registrar movimientos de inventario (entry, transfer, exit) and RF_06 · Traslado de activo entre espacios
 * (the transfer). The route checked `move` (★: an Administrador moves assets of the spaces they manage only); `save`
 * checks it again. The types offered follow the asset's state (RecordAssetMovement::allowedTypes), and the database
 * trigger is the final judge of where the asset is (ADR-0003).
 */
new #[Title('assets.move.title')] class extends Component {
    public Asset $asset;

    public string $type = '';

    /** Kept as a string, like the ids of the asset form. */
    public string $to_space_id = '';

    /** Empty means "now" (RecordAssetMovement::MOVED_AT_FORMAT). */
    public string $moved_at = '';

    public string $reason = '';

    public function mount(): void
    {
        $this->asset->load('currentSpace');

        $this->type = $this->types[0]->value ?? '';
    }

    /**
     * @return list<MovementType>
     */
    #[Computed]
    public function types(): array
    {
        return RecordAssetMovement::allowedTypes($this->asset);
    }

    /**
     * @return Collection<int, Space>
     */
    #[Computed]
    public function destinations(): Collection
    {
        return (new AssetFormChoices)->movementDestinations($this->asset);
    }

    public function save(RecordAssetMovement $recordMovement): void
    {
        $this->authorize('move', $this->asset);

        $movement = $recordMovement->handle(auth()->user(), $this->asset, [
            'type' => $this->type ?: null,
            'to_space_id' => $this->to_space_id ?: null,
            'moved_at' => $this->moved_at ?: null,
            'reason' => trim($this->reason) ?: null,
        ]);

        // RF_06: a non-invasive confirmation naming the space the asset was moved to (or left, for an exit).
        $space = $movement->type === MovementType::Exit ? $movement->fromSpace : $movement->toSpace;

        Flux::toast(variant: 'success', text: __('assets.move.done.'.$movement->type->value, [
            'asset' => $this->asset->code,
            'space' => $space ? "{$space->code} · {$space->name}" : '',
        ]));

        $this->redirectRoute('assets.show', $this->asset, navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
    <div>
        <flux:heading size="lg" level="2">{{ __('assets.move.title') }}</flux:heading>
        <flux:text class="mt-1">{{ __('assets.move.description', ['asset' => $asset->code.' · '.$asset->displayName()]) }}</flux:text>
    </div>

    @if ($this->types === [])
        <flux:callout variant="warning" icon="exclamation-triangle" data-test="cannot-move"
            :heading="__('assets.move.cannot_move.'.($asset->status === AssetStatus::Lost ? 'lost' : 'decommissioned'))" />

        <div>
            <flux:button :href="route('assets.show', $asset)" wire:navigate variant="ghost" icon="arrow-left">{{ __('assets.create.cancel') }}</flux:button>
        </div>
    @else
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:card class="flex flex-col gap-4">
                <flux:field>
                    <flux:label>{{ __('assets.move.current_space') }}</flux:label>
                    <flux:text class="text-ink" data-test="current-space">
                        {{ $asset->currentSpace ? $asset->currentSpace->code.' · '.$asset->currentSpace->name : __('assets.move.outside') }}
                    </flux:text>
                </flux:field>

                <flux:select wire:model.live="type" :label="Str::ucfirst(__('assets.move.fields.type'))" required data-test="movement-type">
                    @foreach ($this->types as $option)
                        <flux:select.option :value="$option->value">{{ __('assets.move.types.'.$option->value) }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($type !== MovementType::Exit->value)
                    <flux:select wire:model="to_space_id" :label="Str::ucfirst(__('assets.move.fields.to_space_id'))" :placeholder="__('assets.create.choose')" required data-test="movement-destination">
                        @foreach ($this->destinations as $space)
                            <flux:select.option :value="$space->id" wire:key="destination-{{ $space->id }}">{{ $space->code }} · {{ $space->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:input type="datetime-local" wire:model="moved_at" :label="Str::ucfirst(__('assets.move.fields.moved_at'))"
                    :description:trailing="__('assets.move.moved_at_help')" :max="now()->format(RecordAssetMovement::MOVED_AT_FORMAT)" />

                <flux:textarea wire:model="reason" :label="Str::ucfirst(__('assets.move.fields.reason'))" :placeholder="__('assets.move.reason_placeholder')"
                    :description:trailing="__('assets.move.reason_help')" rows="3" maxlength="500" :required="$type === MovementType::Exit->value" />
            </flux:card>

            <flux:error name="form" />

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button :href="route('assets.show', $asset)" wire:navigate variant="ghost" class="w-full sm:w-auto">{{ __('assets.create.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" class="w-full sm:w-auto" data-test="move-asset-submit">{{ __('assets.move.submit') }}</flux:button>
            </div>
        </form>
    @endif
</div>
