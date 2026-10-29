<?php

namespace App\Reports\Definitions;

use App\Models\Reporting\UserRow;
use App\Queries\Users\UserDirectory;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** RF_69: current, secret-free users and the directory's shared filters. */
final class UsersReport extends ReportDefinition
{
    public function code(): string
    {
        return 'users';
    }

    public function columns(): array
    {
        return [
            'name' => __('reports.users.name'), 'email' => __('reports.users.email'),
            'roles' => __('reports.users.roles'), 'account_status' => __('reports.users.account_status'),
            'invitation_pending' => __('reports.users.invitation_pending'),
        ];
    }

    /** @return Builder<UserRow> */
    public function query(array $filters): Builder
    {
        return UserDirectory::query($filters);
    }

    public function filterKeys(): array
    {
        return ['search', ...UserDirectory::CRITERIA];
    }

    public function normalizeFilters(array $filters): array
    {
        return UserDirectory::validate($filters);
    }

    public function filterFields(): array
    {
        $choices = UserDirectory::choices();
        $fields = ['search' => ['type' => 'text', 'label' => __('reports.filters.labels.search')]];
        foreach (UserDirectory::CRITERIA as $key) {
            $fields[$key] = ['type' => 'values', 'label' => __('users.filters.criteria.'.$key), 'options' => $choices[$key]];
        }

        return $fields;
    }

    protected function cell(Model $row, string $key): string|int|float|null
    {
        return $key === 'account_status' ? __('users.status.'.$row->getAttribute($key)) : parent::cell($row, $key);
    }
}
