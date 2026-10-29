<?php

namespace App\Notifications\Maintenance;

use App\Enums\MaintenanceKind;
use App\Models\MaintenanceRecord;
use App\Notifications\ShowsInInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * RF_27 · `maintenance.due` (Agent-DB.md §10): a scheduled maintenance is due soon (`upcoming`, within
 * NotifyDueMaintenance::DAYS_AHEAD days) or its day has passed (`overdue`). Sent by the daily NotifyDueMaintenance to the
 * managers of the asset's space. The snapshot keeps the asset, the kind, the planned day and the description as they
 * were, so the message stays true after the maintenance changes; the link opens the asset.
 *
 * @phpstan-type Snapshot array{
 *     maintenance_id: int,
 *     asset: array{id: int, code: string, name: string},
 *     kind: string,
 *     scheduled_for: string,
 *     description: string,
 *     stage: 'upcoming'|'overdue',
 * }
 */
final class MaintenanceDue extends Notification implements ShouldQueueAfterCommit, ShowsInInbox
{
    use Queueable;

    /**
     * @param  Snapshot  $snapshot
     */
    public function __construct(public readonly array $snapshot) {}

    /**
     * @param  'upcoming'|'overdue'  $stage
     */
    public static function of(MaintenanceRecord $record, string $stage): self
    {
        return new self([
            'maintenance_id' => $record->id,
            'asset' => ['id' => $record->asset->id, 'code' => $record->asset->code, 'name' => $record->asset->displayName()],
            'kind' => $record->kind->value,
            'scheduled_for' => (string) $record->scheduled_for?->toDateString(),
            'description' => $record->description,
            'stage' => $stage,
        ]);
    }

    public static function type(): string
    {
        return 'maintenance.due';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function databaseType(object $notifiable): string
    {
        return self::type();
    }

    public function broadcastType(): string
    {
        return self::type();
    }

    /**
     * @return Snapshot
     */
    public function toArray(object $notifiable): array
    {
        return $this->snapshot;
    }

    public static function inboxMessage(array $data): array
    {
        $stage = ($data['stage'] ?? 'upcoming') === 'overdue' ? 'overdue' : 'upcoming';
        $kind = MaintenanceKind::tryFrom((string) ($data['kind'] ?? ''));
        $assetId = $data['asset']['id'] ?? null;

        return [
            'title' => __("assets.maintenance.notifications.{$stage}.title", ['asset' => (string) ($data['asset']['code'] ?? '')]),
            'body' => __("assets.maintenance.notifications.{$stage}.body", [
                'kind' => $kind?->label() ?? '',
                'asset' => (string) ($data['asset']['name'] ?? ''),
                'date' => isset($data['scheduled_for']) ? Carbon::parse((string) $data['scheduled_for'])->format('d/m/Y') : '',
                'description' => (string) ($data['description'] ?? ''),
            ]),
            'url' => $assetId === null ? null : route('assets.show', $assetId),
        ];
    }
}
