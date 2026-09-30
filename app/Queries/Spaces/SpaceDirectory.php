<?php

namespace App\Queries\Spaces;

use App\Models\Reporting\SpaceRow;
use App\Queries\TextSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

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

        self::search($query, $filters['search'] ?? null);

        // TODO(RF_54, @JoseHerrera21): filters by type, status, building, capacity and reservable.

        return $query->orderBy('name');
    }

    /**
     * RF_42 · Partial, case- and accent-insensitive search over the identifier, the name and the location: code, name,
     * floor and location detail through spaces.search_text (TextSearch), and the building's name through the view's
     * `building` column (Agent-DB.md §14.7: names from other tables are matched through a join).
     *
     * @param  Builder<SpaceRow>  $query
     */
    private static function search(Builder $query, mixed $term): void
    {
        $term = trim(is_scalar($term) ? (string) $term : '');

        if ($term === '') {
            return;
        }

        $pattern = '%'.addcslashes(Str::lower($term), '%_\\').'%';

        $query->where(fn (Builder $matches) => TextSearch::apply($matches, 'spaces', $term)
            ->orWhereRaw('lower(immutable_unaccent(building)) LIKE lower(immutable_unaccent(?))', [$pattern]));
    }
}
