<?php

namespace App\Queries\Spaces;

use App\Models\Reporting\SpaceRow;
use Illuminate\Database\Eloquent\Builder;

/**
 * Spaces as every list and report sees them (reporting.spaces): the spaces list (RF_53, RF_54 · @JoseHerrera21),
 * the space search (RF_42 · @Dizabu) and the spaces report (RF_64 · @AleQuesada2012).
 *
 *     SpaceDirectory::query(['search' => 'lab', 'space_type_id' => 1])->get();
 */
final class SpaceDirectory
{
    /**
     * @param  array<string, mixed>  $filters  the same keys in the page, the report and report_generations.filters
     * @return Builder<SpaceRow>
     */
    public static function query(array $filters = []): Builder
    {
        $query = SpaceRow::query();

        // TODO(RF_42, @Dizabu): TextSearch::apply($query, 'spaces', $filters['search'] ?? null).
        // TODO(RF_54, @JoseHerrera21): filters by type, status, building, capacity and reservable.

        return $query->orderBy('name');
    }
}
