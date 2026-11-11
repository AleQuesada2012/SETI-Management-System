<?php

namespace App\Actions\Assets;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\SystemRole;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Notifications\Maintenance\MaintenanceDue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * RF_27 · The daily reminder of scheduled maintenance (`maintenance:notify-due`, routes/console.php; Agent-DB.md §10):
 * each scheduled maintenance of an asset in service or in maintenance is announced once when it comes within
 * DAYS_AHEAD days (`upcoming`) and once more when its day has passed (`overdue`). Changing the planned day starts over,
 * so the new day is announced too. A run that was missed catches up the next day.
 *
 * Recipients: the active managers of the asset's space (where it is, or last was); when that space has none, or the
 * asset has no space, the active Superusuarios (Q34), so a reminder never goes unseen.
 */
final class NotifyDueMaintenance
{
    public const int DAYS_AHEAD = 3;

    /** @return int how many reminders were sent (one per maintenance and stage, whatever the number of recipients) */
    public function handle(): int
    {
        $today = today();
        $sent = 0;

        $due = MaintenanceRecord::query()
            ->where('status', MaintenanceStatus::Scheduled)
            ->whereDate('scheduled_for', '<=', $today->copy()->addDays(self::DAYS_AHEAD))
            ->whereIn('asset_id', Asset::query()->whereIn('status', AssetStatus::available())->select('id'))
            ->with('asset')
            ->orderBy('scheduled_for')
            ->orderBy('id')
            ->get();

        foreach ($due as $record) {
            $stage = $record->scheduled_for?->lt($today) ? 'overdue' : 'upcoming';

            if (self::alreadySent($record, $stage)) {
                continue;
            }

            $recipients = self::recipients($record->asset);
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, MaintenanceDue::of($record, $stage));
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @return Collection<int, User>
     */
    public static function recipients(Asset $asset): Collection
    {
        $spaceId = $asset->responsibleSpaceId();
        $managers = $spaceId === null
            ? new Collection
            : User::query()->whereNull('deactivated_at')->whereHas('managedSpaces', fn (Builder $spaces) => $spaces->whereKey($spaceId))->get();

        return $managers->isNotEmpty()
            ? $managers
            : User::query()->whereNull('deactivated_at')->whereHas('roles', fn (Builder $roles) => $roles->where('name', SystemRole::Superusuario->value))->get();
    }

    /** One reminder per maintenance, planned day and stage. */
    private static function alreadySent(MaintenanceRecord $record, string $stage): bool
    {
        return DB::table('notifications')
            ->where('type', MaintenanceDue::type())
            ->where('data->maintenance_id', $record->id)
            ->where('data->scheduled_for', $record->scheduled_for?->toDateString())
            ->where('data->stage', $stage)
            ->exists();
    }
}
