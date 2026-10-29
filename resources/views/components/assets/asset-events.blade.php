<?php

use App\Actions\Assets\RegisterAssetDamage;
use App\Actions\Assets\RegisterAssetEvent;
use App\Enums\AssetEventKind;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetEvent;
use App\Support\Files\FileRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * RF_13, RF_22, RF_28, RF_29 · the "Eventos" card on the asset page (pages/assets/show): everyone who may view the asset
 * sees its damage, losses, recoveries and decommission with their date, description and evidence. Users who may
 * register events of this asset (★ `registerEvent`) do it in a modal here, among the kinds its lifecycle status allows:
 * a damage is RegisterAssetDamage (a red condition and optional evidence), the rest RegisterAssetEvent. The page reloads
 * afterwards, because an event changes the whole page (badges, actions, read-only cards).
 */
new class extends Component {
    use WithFileUploads;

    #[Locked]
    public Asset $asset;

    public string $kind = '';

    public string $occurred_on = '';

    public string $description = '';

    /** RF_13: the red-group condition the damaged asset takes. */
    public string $condition_id = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $evidence = [];

    /** @return EloquentCollection<int, AssetEvent> */
    #[Computed]
    public function events(): EloquentCollection
    {
        return $this->asset->events()->reorder()->orderByDesc('occurred_on')->orderByDesc('id')->with(['registeredBy', 'evidence'])->get();
    }

    /** @return EloquentCollection<int, AssetCondition> */
    #[Computed]
    public function damageConditions(): EloquentCollection
    {
        return RegisterAssetDamage::conditions();
    }

    /** @return list<AssetEventKind> */
    public function kinds(): array
    {
        return [
            ...(RegisterAssetDamage::isAllowed($this->asset) ? [AssetEventKind::Damage] : []),
            ...RegisterAssetEvent::allowedKinds($this->asset),
        ];
    }

    public function canRegister(): bool
    {
        return $this->kinds() !== [] && (auth()->user()?->can('registerEvent', $this->asset) ?? false);
    }

    public function open(): void
    {
        $this->authorize('registerEvent', $this->asset);

        $this->reset('kind', 'occurred_on', 'description', 'condition_id', 'evidence');
        $this->resetValidation();
        $this->kind = $this->kinds()[0]->value ?? '';
        $this->occurred_on = today()->toDateString();
        $this->condition_id = (string) $this->damageConditions->first()?->id;
        Flux::modal('asset-event-form')->show();
    }

    public function save(RegisterAssetEvent $registerAssetEvent, RegisterAssetDamage $registerAssetDamage): void
    {
        $this->authorize('registerEvent', $this->asset);

        $input = [
            'occurred_on' => $this->occurred_on,
            'description' => trim($this->description),
        ];

        $event = $this->kind === AssetEventKind::Damage->value
            ? $registerAssetDamage->handle(auth()->user(), $this->asset, $input + [
                'condition_id' => $this->condition_id,
                'evidence' => $this->evidence,
            ])
            : $registerAssetEvent->handle(auth()->user(), $this->asset, $input + ['kind' => $this->kind]);

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
                            <flux:table.cell class="min-w-48">
                                <span class="whitespace-pre-line">{{ $event->description }}</span>
                                @if ($event->evidence->isNotEmpty())
                                    <span class="mt-1 flex flex-wrap gap-x-3 gap-y-1" data-test="event-evidence">
                                        <span class="text-ink-muted">{{ __('assets.events.evidence.label') }}:</span>
                                        @foreach ($event->evidence as $file)
                                            <flux:link :href="route('assets.files.show', [$asset, $file])" target="_blank" rel="noopener" wire:key="evidence-{{ $file->id }}">{{ $file->original_name }}</flux:link>
                                        @endforeach
                                    </span>
                                @endif
                            </flux:table.cell>
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
                    @foreach ($this->kinds() as $option)
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

                @if ($selected === AssetEventKind::Damage)
                    <flux:select wire:model="condition_id" :label="Str::ucfirst(__('assets.events.fields.condition_id'))" required data-test="event-condition">
                        @foreach ($this->damageConditions as $condition)
                            <flux:select.option :value="$condition->id">{{ $condition->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:field>
                        <flux:label>{{ __('assets.events.evidence.label') }}</flux:label>
                        <div class="flex min-w-0 flex-wrap items-center gap-3">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-line bg-surface px-3 py-2 text-sm font-medium text-ink hover:border-brand has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand">
                                <flux:icon.paper-clip variant="micro" />
                                {{ __('assets.events.evidence.pick') }}
                                <input type="file" wire:model="evidence" multiple accept="{{ implode(',', FileRules::DOCUMENT_MIME_TYPES) }}" class="sr-only" data-test="event-evidence-input" />
                            </label>
                            <flux:text class="min-w-0 truncate text-sm" data-test="event-evidence-chosen">
                                {{ $evidence === [] ? __('assets.events.evidence.nothing_chosen') : trans_choice('assets.events.evidence.chosen', count($evidence), ['count' => count($evidence)]) }}
                            </flux:text>
                        </div>
                        <flux:description>{{ __('assets.events.evidence.help', ['max' => RegisterAssetDamage::MAX_EVIDENCE]) }}</flux:description>
                        <flux:text wire:loading wire:target="evidence" class="text-sm">{{ __('assets.events.evidence.uploading') }}</flux:text>
                        <flux:error name="evidence" />
                        <flux:error name="evidence.*" />
                    </flux:field>
                @endif

                <flux:error name="form" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('assets.events.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" :variant="$selected === AssetEventKind::Decommission ? 'danger' : 'primary'" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="evidence,save" data-test="save-event">{{ __('assets.events.save') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</flux:card>
