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
    /** The filter keys of RF_54, as the list page and the spaces report send them. */
    public const FILTERS = ['space_type_id', 'space_status_id', 'building_id', 'min_capacity'];

    /**
     * Every filter that has a value narrows the list, so criteria combine (RF_54); blank ones are ignored.
     *
     * @param  array<string, mixed>  $filters  the same keys in the page, the report and report_generations.filters
     * @return Builder<SpaceRow>
     */
    public static function query(array $filters = []): Builder
    {
        $query = SpaceRow::query();

        // TODO(RF_42, @Dizabu): TextSearch::apply($query, 'spaces', $filters['search'] ?? null).

        foreach (['space_type_id', 'space_status_id', 'building_id'] as $key) {
            $id = self::positiveInteger($filters[$key] ?? null);
            $query->when($id !== null, fn (Builder $q) => $q->where($key, $id));
        }

        $minCapacity = self::positiveInteger($filters['min_capacity'] ?? null);
        $query->when($minCapacity !== null, fn (Builder $q) => $q->where('capacity', '>=', $minCapacity));

        return $query->orderBy('name');
    }

    /** A filter value from a select or a URL: a positive whole number, or null when it is blank or malformed. */
    private static function positiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }
}
