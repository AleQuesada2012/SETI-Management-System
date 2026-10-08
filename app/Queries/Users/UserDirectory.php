<?php

namespace App\Queries\Users;

use App\Models\Reporting\UserRow;
use App\Queries\TextSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Users as every list and report sees them (reporting.users, never secrets): the users list (RF_90), search (RF_95),
 * filters (RF_92, RF_101) · @Rubiocai, and the users report (RF_69 · @AleQuesada2012).
 *
 *     UserDirectory::query(['search' => 'vargas', 'account_status' => 'active'])->paginate(15);
 */
final class UserDirectory
{
    /** Values of the account_status filter (reporting.users.account_status). */
    public const array ACCOUNT_STATUSES = ['active', 'deactivated'];

    /**
     * The same filter keys in the page, the report and report_generations.filters.
     *
     * @param  array{search?: string|null, role?: string|null, account_status?: string|null}  $filters
     * @return Builder<UserRow>
     */
    public static function query(array $filters = []): Builder
    {
        $query = UserRow::query();

        // RF_95: partial, accent-insensitive match on name, email or id (users.search_text).
        TextSearch::apply($query, 'users', $filters['search'] ?? null);

        $role = $filters['role'] ?? null;

        if (filled($role)) {
            $query->whereIn('id', fn (QueryBuilder $sub) => $sub
                ->select('user_id')
                ->from('reporting.user_roles')
                ->where('role', $role));
        }

        $status = $filters['account_status'] ?? null;

        if (in_array($status, self::ACCOUNT_STATUSES, true)) {
            $query->where('account_status', $status);
        }

        return $query->orderBy('name')->orderBy('id');
    }
}
