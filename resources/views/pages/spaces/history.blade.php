<?php

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Models\Building;
use App\Models\Space;
use App\Models\SpaceStatus;
use App\Models\SpaceType;
use App\Queries\Spaces\SpaceHistory;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Historial del espacio (RF_39). Owner: spaces (@Dizabu). Reached from "Ver historial" on the space page; the route
 * checks `view` on the space. Entries come from App\Queries\Spaces\SpaceHistory: changes made by Administradores and
 * Superusuarios (audit_logs), reservations (when, for whom and by whom) and asset movements into or out of the space.
 */
new #[Title('spaces.history.title')] class extends Component {
    use WithPagination;

    public Space $space;

    /** @return LengthAwarePaginator<int, object> */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return SpaceHistory::for($this->space);
    }

    /**
     * Names for the lookup ids an edit may change (type, building, status), including deleted lookups.
     *
     * @return array<string, array<int, string>>
     */
    #[Computed]
    public function lookups(): array
    {
        return [
            'space_type_id' => SpaceType::withTrashed()->pluck('name', 'id')->all(),
            'building_id' => Building::withTrashed()->pluck('name', 'id')->all(),
            'space_status_id' => SpaceStatus::withTrashed()->pluck('name', 'id')->all(),
        ];
    }

    /**
     * One entry as the page shows it: an icon, a sentence and optional detail lines.
     *
     * @return array{icon: string, text: string, lines: list<string>, badge: ?string}
     */
    public function describe(object $entry): array
    {
        $details = json_decode((string) $entry->details, true) ?: [];
        $actor = $entry->actor ?? __('spaces.history.system');

        return match ($entry->kind) {
            'reservation' => $this->describeReservation($details, $actor),
            'movement' => $this->describeMovement($details, $actor),
            default => $this->describeChange($details, $actor),
        };
    }

    /** @param array<string, mixed> $details */
    private function describeChange(array $details, string $actor): array
    {
        $action = AuditAction::tryFrom((string) ($details['action'] ?? ''));
        $old = (array) ($details['old'] ?? []);
        $new = (array) ($details['new'] ?? []);

        $text = match ($action) {
            AuditAction::Created => __('spaces.history.created', ['actor' => $actor]),
            AuditAction::Updated => __('spaces.history.updated', ['actor' => $actor]),
            AuditAction::Deleted => __('spaces.history.deleted', ['actor' => $actor]),
            AuditAction::Restored => __('spaces.history.restored', ['actor' => $actor]),
            AuditAction::Attached => __('spaces.history.photo_added', ['actor' => $actor, 'photo' => $new['photo'] ?? '']),
            AuditAction::Detached => __('spaces.history.photo_removed', ['actor' => $actor, 'photo' => $old['photo'] ?? '']),
            AuditAction::ManagerAssigned => __('spaces.history.manager_assigned', ['actor' => $actor, 'manager' => $this->personName($new['manager'] ?? '')]),
            AuditAction::ManagerRemoved => __('spaces.history.manager_removed', ['actor' => $actor, 'manager' => $this->personName($old['manager'] ?? '')]),
            default => __('spaces.history.other', ['actor' => $actor, 'action' => $action?->label() ?? ($details['action'] ?? '')]),
        };

        $lines = [];

        if ($action === AuditAction::Updated) {
            foreach ($new as $field => $value) {
                $lines[] = __('spaces.history.field_change', [
                    'field' => Str::ucfirst(__("spaces.history.fields.{$field}")),
                    'old' => $this->value($field, $old[$field] ?? null),
                    'new' => $this->value($field, $value),
                ]);
            }
        }

        return ['icon' => $action === AuditAction::Updated ? 'pencil-square' : 'clipboard-document-list', 'text' => $text, 'lines' => $lines, 'badge' => null];
    }

    /** @param array<string, mixed> $details */
    private function describeReservation(array $details, string $actor): array
    {
        $timezone = (string) config('app.timezone');
        $starts = CarbonImmutable::parse((string) $details['starts_at'])->setTimezone($timezone);
        $ends = CarbonImmutable::parse((string) $details['ends_at'])->setTimezone($timezone);

        return [
            'icon' => 'calendar-days',
            'text' => __('spaces.history.reserved', ['actor' => $actor, 'title' => $details['title'] ?? '']),
            'lines' => [
                __('spaces.history.reservation_when', ['date' => $starts->format('d/m/Y'), 'from' => $starts->format('H:i'), 'to' => $ends->format('H:i')]),
                __('spaces.history.reservation_for', ['responsible' => $details['responsible'] ?? '']),
            ],
            'badge' => ($details['status'] ?? null) === 'cancelled' ? __('spaces.history.cancelled') : null,
        ];
    }

    /** @param array<string, mixed> $details */
    private function describeMovement(array $details, string $actor): array
    {
        $type = MovementType::tryFrom((string) ($details['type'] ?? ''));
        $asset = trim(($details['asset_code'] ?? '').' · '.($details['asset_name'] ?? ''), ' ·');
        $into = (bool) ($details['into'] ?? false);

        $text = match (true) {
            $type === MovementType::Entry => __('spaces.history.asset_entered', ['actor' => $actor, 'asset' => $asset]),
            $type === MovementType::Exit => __('spaces.history.asset_left', ['actor' => $actor, 'asset' => $asset]),
            $into => __('spaces.history.asset_transferred_in', ['actor' => $actor, 'asset' => $asset, 'space' => $details['from_code'] ?? '']),
            default => __('spaces.history.asset_transferred_out', ['actor' => $actor, 'asset' => $asset, 'space' => $details['to_code'] ?? '']),
        };

        return ['icon' => 'arrows-right-left', 'text' => $text, 'lines' => array_values(array_filter([$details['reason'] ?? null])), 'badge' => null];
    }

    /** "Ana Rojas <ana@tec.ac.cr>" → "Ana Rojas" (manager entries store the audit label). */
    private function personName(string $label): string
    {
        return trim(Str::before($label, '<'));
    }

    private function value(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('spaces.history.empty_value');
        }

        return (string) ($this->lookups[$field][(int) $value] ?? $value);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center gap-2">
        <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('spaces.show', $space)" wire:navigate>{{ __('spaces.history.back') }}</flux:button>
        <flux:heading size="lg" level="2">{{ __('spaces.history.heading', ['space' => $space->code.' · '.$space->name]) }}</flux:heading>
    </div>

    <flux:card class="flex flex-col gap-4">
        @if (SpaceHistory::hasNoChanges($space))
            <flux:callout icon="information-circle" data-test="space-history-empty">
                <flux:callout.text>{{ __('spaces.history.empty') }}</flux:callout.text>
            </flux:callout>
        @endif

        @if ($this->entries->isNotEmpty())
            <ol class="flex flex-col divide-y divide-line" data-test="space-history">
                @foreach ($this->entries as $entry)
                    @php($item = $this->describe($entry))
                    <li wire:key="{{ $entry->kind }}-{{ $entry->source_id }}" class="flex gap-3 py-3 first:pt-0 last:pb-0">
                        <flux:icon :name="$item['icon']" class="mt-0.5 size-5 shrink-0 text-ink-muted" />
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                <span class="text-ink">{{ $item['text'] }}</span>
                                @if ($item['badge'])
                                    <flux:badge size="sm" color="zinc">{{ $item['badge'] }}</flux:badge>
                                @endif
                            </div>
                            @foreach ($item['lines'] as $line)
                                <span class="break-words text-sm text-ink-muted">{{ $line }}</span>
                            @endforeach
                            <time class="text-xs text-ink-muted" datetime="{{ CarbonImmutable::parse($entry->occurred_at)->toIso8601String() }}">
                                {{ CarbonImmutable::parse($entry->occurred_at)->setTimezone(config('app.timezone'))->format('d/m/Y H:i') }}
                            </time>
                        </div>
                    </li>
                @endforeach
            </ol>

            {{ $this->entries->links() }}
        @endif
    </flux:card>
</div>
