<?php

namespace App\Queries\Users;

use App\Models\Reporting\UserRow;
use Illuminate\Database\Eloquent\Builder;

/**
 * Users as every list and report sees them (reporting.users, never secrets): the users list (RF_90), search (RF_95),
 * filters (RF_92, RF_101) · @Rubiocai, and the users report (RF_69 · @AleQuesada2012).
 *
 *     UserDirectory::query(['search' => 'vargas', 'account_status' => 'active'])->paginate(15);
 */
final class UserDirectory
{
    /**
     * @param  array<string, mixed>  $filters  the same keys in the page, the report and report_generations.filters
     * @return Builder<UserRow>
     */
    public static function query(array $filters = []): Builder
    {
        $query = UserRow::query();

        // TODO(RF_95, @Rubiocai): TextSearch::apply($query, 'users', $filters['search'] ?? null).
        // TODO(RF_92, RF_101, @Rubiocai): filters by role (reporting.user_roles) and account status.

        return $query->orderBy('name');
    }
}
