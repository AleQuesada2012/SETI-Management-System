<?php

namespace App\Queries\Spaces;

use App\Models\Space;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * RF_39 · A space's history, newest first, as Agent-DB.md §8.5 describes: the changes recorded in audit_logs for the
 * space (who made them: the Administrador or Superusuario), its reservations (when it was reserved, for whom and by
 * whom, from reporting.reservations) and the asset movements into or out of it (reporting.asset_movements).
 *
 * Every entry has the same shape: `kind` (change | reservation | movement), `occurred_at`, `actor` and a JSON
 * `details` object whose keys depend on the kind.
 *
 *     SpaceHistory::for($space)->items();   // [{kind: 'change', occurred_at: …, actor: 'Mario Solís', details: {…}}, …]
 */
final class SpaceHistory
{
    public const PER_PAGE = 15;

    /**
     * Whether the space has had nothing happen since it was registered: no change other than its creation, no
     * reservation and no asset movement. The page then says its history is empty (RF_39).
     */
    public static function hasNoChanges(Space $space): bool
    {
        return ! DB::table('audit_logs')->where('entity_type', 'space')->where('entity_id', $space->id)->where('action', '!=', 'created')->exists()
            && ! DB::table('reservations')->where('space_id', $space->id)->exists()
            && ! DB::table('asset_movements')->where(fn (Builder $q) => $q->where('from_space_id', $space->id)->orWhere('to_space_id', $space->id))->exists();
    }

    /**
     * @return LengthAwarePaginator<int, object{kind: string, source_id: int, occurred_at: string, actor: ?string, details: string}>
     */
    public static function for(Space $space, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $changes = DB::table('audit_logs')
            ->where('entity_type', 'space')
            ->where('entity_id', $space->id)
            ->selectRaw("'change' AS kind, id AS source_id, occurred_at, actor_label AS actor,
                jsonb_build_object('action', action, 'old', old_values, 'new', new_values) AS details");

        $reservations = DB::table('reporting.reservations')
            ->where('space_id', $space->id)
            ->selectRaw("'reservation' AS kind, id AS source_id, created_at AS occurred_at, created_by_name AS actor,
                jsonb_build_object('title', title, 'starts_at', starts_at, 'ends_at', ends_at,
                    'responsible', responsible_name, 'status', status, 'cancelled_by', cancelled_by_name) AS details");

        $movements = DB::table('reporting.asset_movements')
            ->where(fn (Builder $q) => $q->where('from_space_id', $space->id)->orWhere('to_space_id', $space->id))
            ->selectRaw("'movement' AS kind, id AS source_id, moved_at AS occurred_at, moved_by_name AS actor,
                jsonb_build_object('type', type, 'asset_id', asset_id, 'asset_code', asset_code, 'asset_name', asset_name,
                    'into', to_space_id = ?, 'from_code', from_space_code, 'to_code', to_space_code, 'reason', reason) AS details", [$space->id]);

        return DB::query()
            ->fromSub($changes->unionAll($reservations)->unionAll($movements), 'history')
            ->orderByDesc('occurred_at')
            ->orderByDesc('source_id')
            ->paginate($perPage);
    }
}
