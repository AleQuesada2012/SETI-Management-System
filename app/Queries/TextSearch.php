<?php

namespace App\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;

/**
 * The search of RF_07, RF_42 and RF_95 (Agent-DB.md §14.7): partial, case- and accent-insensitive ("salon" finds
 * "Salón"), over the `search_text` column of assets, spaces and users, using its trigram index.
 */
final class TextSearch
{
    /**
     * Keep only rows whose id belongs to a row of $table whose search_text contains $term. Works on the table's
     * own model and on its reporting view (both share the id). A blank term leaves the query unchanged.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  'assets'|'spaces'|'users'  $table
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, string $table, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $pattern = '%'.addcslashes(Str::lower($term), '%_\\').'%';

        return $query->whereIn($query->qualifyColumn('id'), fn (QueryBuilder $sub) => $sub
            ->select('id')
            ->from($table)
            ->whereRaw('search_text LIKE lower(immutable_unaccent(?))', [$pattern]));
    }
}
