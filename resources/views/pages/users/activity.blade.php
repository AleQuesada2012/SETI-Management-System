<?php

use App\Models\Reporting\AuditLogRow;
use App\Queries\Reports\AuditTrail;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Actividad de usuarios (RF_99): the actions a user performed, with date, time and operation, filtered by a date range.
 * Owner: users (@Rubiocai). It reuses App\Queries\Reports\AuditTrail (RF_68 · @AleQuesada2012) and its filter contract
 * (`actor` + `occurred_at`, whole Costa Rica calendar days), so this page and the audit report agree. The route checks
 * reports.audit ("Consultar la auditoría y la actividad de los usuarios"): the Superusuario and Reportes.
 */
new #[Title('users.activity.title')] class extends Component
{
    use WithPagination;

    public const PER_PAGE = 20;

    /** The chosen user's id ('' while none is chosen: the select's empty option is a real, selectable one). */
    #[Url(as: 'usuario', except: '')]
    public string $user = '';

    #[Url(as: 'desde', except: '')]
    public string $from = '';

    #[Url(as: 'hasta', except: '')]
    public string $to = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['user', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearDates(): void
    {
        $this->reset('from', 'to');
        $this->resetPage();
    }

    /** @return list<array{id: int|string, label: string}> */
    #[Computed]
    public function users(): array
    {
        return AuditTrail::choices()['actor'];
    }

    /**
     * The AuditTrail filters for the chosen user and dates, or the Spanish errors when they break its contract (an
     * inverted range, an edited URL).
     *
     * @return array{filters: array<string, mixed>, errors: list<string>}
     */
    #[Computed]
    public function criteria(): array
    {
        if ($this->user === '') {
            return ['filters' => [], 'errors' => []];
        }

        try {
            return ['filters' => AuditTrail::validate([
                'actor' => ['values' => [$this->user]],
                'occurred_at' => ['from' => $this->from, 'to' => $this->to],
            ]), 'errors' => []];
        } catch (ValidationException $e) {
            return ['filters' => [], 'errors' => array_values(array_unique($e->validator->errors()->all()))];
        }
    }

    /** @return LengthAwarePaginator<int, AuditLogRow>|null */
    #[Computed]
    public function entries(): ?LengthAwarePaginator
    {
        $filters = $this->criteria['filters'];

        return isset($filters['actor']) ? AuditTrail::query($filters)->paginate(self::PER_PAGE) : null;
    }

    /** The chosen user's label, or null when none is chosen or the id is unknown. */
    public function selectedLabel(): ?string
    {
        return collect($this->users)->firstWhere('id', (int) $this->user)['label'] ?? null;
    }

    /** Whether the empty list comes from the date range (the user has activity outside it). */
    public function emptyBecauseOfDates(): bool
    {
        $filters = $this->criteria['filters'];

        return isset($filters['occurred_at']) && AuditTrail::query(['actor' => $filters['actor']])->exists();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:text>{{ __('users.activity.intro') }}</flux:text>
        @can('viewAny', App\Models\User::class)
            <flux:button icon="arrow-left" :href="route('users.index')" wire:navigate>{{ __('users.activity.back') }}</flux:button>
        @endcan
    </div>

    <flux:card class="flex flex-col gap-4">
        <flux:select wire:model.live="user" :label="__('users.activity.user')" class="sm:max-w-md">
            <flux:select.option value="">{{ __('users.activity.choose') }}</flux:select.option>
            @foreach ($this->users as $option)
                <flux:select.option :value="(string) $option['id']" wire:key="activity-user-{{ $option['id'] }}">{{ $option['label'] }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:input type="date" wire:model.live="from" :label="__('users.activity.from')" class="sm:max-w-48" />
            <flux:input type="date" wire:model.live="to" :label="__('users.activity.to')" class="sm:max-w-48" />
            @if ($from !== '' || $to !== '')
                <flux:button variant="ghost" icon="x-mark" wire:click="clearDates">{{ __('users.activity.clear_dates') }}</flux:button>
            @endif
        </div>
    </flux:card>

    @if ($this->criteria['errors'] !== [])
        <flux:callout icon="exclamation-triangle" variant="danger" data-test="activity-invalid">
            <flux:callout.heading>{{ __('users.activity.invalid') }}</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc space-y-0.5 ps-5">
                    @foreach ($this->criteria['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout.text>
        </flux:callout>
    @elseif ($this->entries === null)
        <flux:text data-test="activity-pick">{{ __('users.activity.pick_user') }}</flux:text>
    @else
        <flux:card class="overflow-x-auto">
            <flux:heading level="2" class="mb-3">{{ __('users.activity.heading', ['user' => $this->selectedLabel()]) }}</flux:heading>

            @if ($this->entries->isEmpty())
                <flux:text data-test="activity-empty">
                    {{ $this->emptyBecauseOfDates() ? __('users.activity.none_in_range') : __('users.activity.none') }}
                </flux:text>
            @else
                <flux:table :paginate="$this->entries">
                    <flux:table.columns>
                        <flux:table.column>{{ __('users.activity.date') }}</flux:table.column>
                        <flux:table.column>{{ __('users.activity.time') }}</flux:table.column>
                        <flux:table.column>{{ __('users.activity.operation') }}</flux:table.column>
                        <flux:table.column>{{ __('users.activity.record') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->entries as $entry)
                            <flux:table.row :key="$entry->id">
                                <flux:table.cell class="whitespace-nowrap">{{ $entry->occurred_at->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">{{ $entry->occurred_at->format('H:i') }}</flux:table.cell>
                                <flux:table.cell>
                                    {{ $entry->action->label() }}
                                    @if ($entry->entity_type && $entry->entity_type !== 'user')
                                        {{ __('app.entities.'.$entry->entity_type) }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-ink-muted">{{ $entry->entity_label }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif
</div>
