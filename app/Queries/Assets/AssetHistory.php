<?php

namespace App\Queries\Assets;

use App\Enums\AssetStatus;
use App\Enums\AuditAction;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Reporting\AssetEventRow;
use App\Models\Reporting\AssetMovementRow;
use App\Models\Reporting\AuditLogRow;
use App\Models\Reporting\MaintenanceRow;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * RF_09 · An asset's history, newest first (Agent-DB.md §8.5): where it has been (reporting.asset_movements, through
 * AssetMovements), what happened to it (reporting.asset_events) and who changed its record, with the old and new values
 * (reporting.audit_logs rows of the asset), and the work done on it (reporting.maintenance: RF_10, RF_26). Every entry
 * names the user responsible. Owner: assets (@DavidZamoraB).
 *
 * A status-only update is left out: the lifecycle status changes only as a side effect of an event or maintenance
 * (§8.4), which is already an entry of its own.
 *
 *     AssetHistory::forAsset($asset);   // Collection<int, AssetHistoryEntry>
 */
final class AssetHistory
{
    /**
     * @return Collection<int, AssetHistoryEntry>
     */
    public static function forAsset(Asset $asset): Collection
    {
        /** @var Collection<int, AssetHistoryEntry> $entries */
        $entries = collect()
            ->concat(AssetMovements::forAsset($asset)->get()->map(self::movement(...)))
            ->concat(AssetEventRow::query()->where('asset_id', $asset->id)->orderByDesc('occurred_on')->orderByDesc('id')->get()->map(self::event(...)))
            ->concat(MaintenanceRow::query()->where('asset_id', $asset->id)->orderByDesc('reference_date')->orderByDesc('id')->get()->map(self::maintenance(...)))
            ->concat(self::changes($asset));

        // Each source comes newest first and the sort is stable, so entries of the same instant keep a fixed order.
        return $entries->sortByDesc(fn (AssetHistoryEntry $entry): float => $entry->at->getPreciseTimestamp())->values();
    }

    private static function movement(AssetMovementRow $row): AssetHistoryEntry
    {
        $space = fn (?string $code, ?string $name): string => $code !== null ? "{$code} · {$name}" : __('assets.history.outside');

        return new AssetHistoryEntry(
            type: 'movement',
            at: $row->moved_at,
            dateOnly: false,
            title: $row->type->label(),
            details: array_values(array_filter([
                __('assets.history.route', [
                    'from' => $space($row->from_space_code, $row->from_space_name),
                    'to' => $space($row->to_space_code, $row->to_space_name),
                ]),
                $row->reason !== null && $row->reason !== '' ? __('assets.history.reason', ['reason' => $row->reason]) : null,
            ])),
            actor: $row->moved_by_name,
        );
    }

    /** Dated by day; the time it was registered orders the events of one day among the other entries. */
    private static function event(AssetEventRow $row): AssetHistoryEntry
    {
        $registeredAt = $row->created_at->timezone(config('app.timezone'));

        return new AssetHistoryEntry(
            type: 'event',
            at: $row->occurred_on->setTimeFrom($registeredAt)->setMicrosecond($registeredAt->microsecond),
            dateOnly: true,
            title: $row->kind->label(),
            details: [$row->description],
            actor: $row->registered_by_name,
        );
    }

    /** Dated by the day of the work, like events; its kind is the title, with the status unless it is completed. */
    private static function maintenance(MaintenanceRow $row): AssetHistoryEntry
    {
        /** @var Carbon $createdAt */
        $createdAt = $row->getAttribute('created_at');
        $registeredAt = $createdAt->timezone(config('app.timezone'));

        return new AssetHistoryEntry(
            type: 'maintenance',
            at: $row->reference_date->setTimeFrom($registeredAt)->setMicrosecond($registeredAt->microsecond),
            dateOnly: true,
            title: $row->status === MaintenanceStatus::Completed ? $row->kind->label() : "{$row->kind->label()} · {$row->status->label()}",
            details: array_values(array_filter([
                $row->description,
                $row->performed_by !== null && $row->performed_by !== '' ? __('assets.history.performed_by', ['name' => $row->performed_by]) : null,
            ])),
            actor: $row->registered_by_name,
        );
    }

    /**
     * @return Collection<int, AssetHistoryEntry>
     */
    private static function changes(Asset $asset): Collection
    {
        return AuditLogRow::query()
            ->where('entity_type', $asset->getMorphClass())
            ->where('entity_id', $asset->id)
            ->orderByDesc('id')
            ->get()
            ->map(function (AuditLogRow $row): ?AssetHistoryEntry {
                $old = self::decode($row->old_values);
                $new = self::decode($row->new_values);

                if ($row->action === AuditAction::Updated && array_keys($new) === ['status']) {
                    return null;
                }

                return new AssetHistoryEntry(
                    type: 'change',
                    at: $row->occurred_at,
                    dateOnly: false,
                    title: __('assets.history.changes.'.$row->action->value),
                    details: match ($row->action) {
                        AuditAction::Updated => array_map(fn (string $field): string => __('assets.history.field_change', [
                            'field' => self::fieldLabel($field),
                            'old' => self::value($field, $old[$field] ?? null),
                            'new' => self::value($field, $new[$field] ?? null),
                        ]), array_keys($new)),
                        AuditAction::Attached, AuditAction::Detached => array_map(
                            fn (string $kind, mixed $name): string => __('assets.history.file', ['kind' => __("assets.history.file_kinds.{$kind}"), 'file' => (string) $name]),
                            array_keys($new + $old),
                            array_values($new + $old),
                        ),
                        default => [],
                    },
                    actor: $row->actor_label,
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(?string $json): array
    {
        $values = $json === null ? null : json_decode($json, true);

        return is_array($values) ? $values : [];
    }

    private static function fieldLabel(string $field): string
    {
        $key = "assets.fields.{$field}";

        return trans()->has($key) ? ucfirst(__($key)) : $field;
    }

    /** A Spanish rendering of an audited value: names for references, labels for the status, d/m/Y for dates. */
    private static function value(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('assets.show.empty');
        }

        if (is_numeric($value)) {
            $id = (int) $value;
            $name = match ($field) {
                'asset_type_id' => AssetType::withTrashed()->find($id)?->name,
                'brand_id' => Brand::withTrashed()->find($id)?->name,
                'condition_id' => AssetCondition::withTrashed()->find($id)?->name,
                'supplier_id' => Supplier::withTrashed()->find($id)?->name,
                'responsible_user_id' => User::query()->find($id)?->name,
                default => false,
            };

            if ($name !== false) {
                return $name ?? "#{$id}";
            }
        }

        if ($field === 'status' && is_string($value) && ($status = AssetStatus::tryFrom($value)) !== null) {
            return $status->label();
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return Carbon::parse($value)->format('d/m/Y');
        }

        return is_scalar($value) ? (string) $value : (string) json_encode($value);
    }
}
