<?php

use App\Actions\Assets\RegisterAssetEvent;
use App\Enums\AssetEventKind;
use App\Models\Asset;
use App\Models\AssetEvent;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * RF_22, RF_28, RF_29 · the "Eventos" card on the asset page (pages/assets/show): everyone who may view the asset sees
 * its losses, recoveries and decommission with their date and description. Users who may register events of this asset
 * (★ `registerEvent`) do it in a modal here, among the kinds its lifecycle status allows (RegisterAssetEvent). The page
 * reloads afterwards, because the status changes the whole page (badge, actions, read-only cards).
 */
new class extends Component {
    #[Locked]
    public Asset $asset;

    public string $kind = '';

    public string $occurred_on = '';

    public string $description = '';

    /** @return EloquentCollection<int, AssetEvent> */
    #[Computed]
    public function events(): EloquentCollection
    {
        return $this->asset->events()->reorder()->orderByDesc('occurred_on')->orderByDesc('id')->with('registeredBy')->get();
    }

    /** @return list<AssetEventKind> */
    public function allowedKinds(): array
    {
        return RegisterAssetEvent::allowedKinds($this->asset);
    }

    public function canRegister(): bool
    {
        return $this->allowedKinds() !== [] && (auth()->user()?->can('registerEvent', $this->asset) ?? false);
    }

    public function open(): void
    {
        $this->authorize('registerEvent', $this->asset);

        $this->reset('kind', 'occurred_on', 'description');
        $this->resetValidation();
        $this->kind = $this->allowedKinds()[0]->value ?? '';
        $this->occurred_on = today()->toDateString();
        Flux::modal('asset-event-form')->show();
    }

    public function save(RegisterAssetEvent $registerAssetEvent): void
    {
        $this->authorize('registerEvent', $this->asset);

        $event = $registerAssetEvent->handle(auth()->user(), $this->asset, [
            'kind' => $this->kind,
            'occurred_on' => $this->occurred_on,
            'description' => trim($this->description),
        ]);

        Flux::toast(variant: 'success', text: __('assets.events.done.'.$event->kind->value, ['asset' => $this->asset->code]));

        $this->redirectRoute('assets.show', $this->asset, navigate: true);
    }
}; ?>

<flux:card class="flex flex-col gap-3" data-test="asset-events">
    @php
        $canRegister = $this->canRegister();
        $selected = AssetEventKind::tryFrom($kind);
    @endphp
    <div class="flex flex-wrap items-center gap-3">
        <flux:heading level="3">{{ __('assets.events.title') }}</flux:heading>
        @if ($canRegister)
            <flux:button wire:click="open" icon="clipboard-document-list" size="sm" class="ms-auto" data-test="register-event-button">{{ __('assets.events.register') }}</flux:button>
        @endif
    </div>

    @if ($this->events->isEmpty())
        <flux:text data-test="no-events">{{ __('assets.events.empty') }}</flux:text>
    @else
        <div class="relative overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('assets.events.columns.occurred_on') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.events.columns.kind') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.events.columns.description') }}</flux:table.column>
                    <flux:table.column>{{ __('assets.events.columns.registered_by') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->events as $event)
                        <flux:table.row :key="$event->id" data-test="event-row">
                            <flux:table.cell class="whitespace-nowrap">{{ $event->occurred_on->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell variant="strong" class="whitespace-nowrap">{{ $event->kind->label() }}</flux:table.cell>
                            <flux:table.cell class="min-w-48"><span class="whitespace-pre-line">{{ $event->description }}</span></flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">{{ $event->registeredBy->name ?? __('assets.events.system') }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    @if ($canRegister)
        <flux:modal name="asset-event-form" class="w-full max-w-lg">
            <form wire:submit="save" class="flex flex-col gap-5">
                <flux:heading size="lg">{{ __('assets.events.form_title', ['asset' => $asset->code]) }}</flux:heading>

                <flux:select wire:model.live="kind" :label="Str::ucfirst(__('assets.events.fields.kind'))" required data-test="event-kind">
                    @foreach ($this->allowedKinds() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($selected)
                    <flux:callout :variant="$selected === AssetEventKind::Decommission ? 'warning' : 'secondary'" icon="information-circle" data-test="event-kind-help">
                        <flux:callout.text>{{ __('assets.events.kind_help.'.$selected->value) }}</flux:callout.text>
                    </flux:callout>
                @endif

                <flux:input type="date" wire:model="occurred_on" :label="Str::ucfirst(__('assets.events.fields.occurred_on'))" :max="today()->toDateString()" :min="$asset->acquired_on?->toDateString()" required data-test="event-occurred-on" />
                <flux:textarea wire:model="description" :label="Str::ucfirst(__('assets.events.fields.description'))" :placeholder="$selected ? __('assets.events.description_placeholder.'.$selected->value) : null" rows="3" maxlength="1000" required data-test="event-description" />

                <flux:error name="form" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('assets.events.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" :variant="$selected === AssetEventKind::Decommission ? 'danger' : 'primary'" class="w-full sm:w-auto" data-test="save-event">{{ __('assets.events.save') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</flux:card>
