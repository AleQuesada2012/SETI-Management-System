<?php

namespace Tests\Support;

use App\Models\Reporting\UserRow;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;

/**
 * A minimal report used to test the export pipeline (RF_00_4) independently of the real report definitions.
 * It also shows the shape of a definition: code, columns, filters and a query over a reporting view.
 */
final class UsersTestReport extends ReportDefinition
{
    public function code(): string
    {
        return 'users';
    }

    public function columns(): array
    {
        return ['name' => 'Nombre', 'email' => 'Correo', 'account_status' => 'Estado', 'created_at' => 'Creado'];
    }

    public function filterKeys(): array
    {
        return ['account_status'];
    }

    public function describeFilters(array $filters): array
    {
        return isset($filters['account_status']) ? ['Estado de cuenta' => $filters['account_status']] : [];
    }

    public function query(array $filters): Builder
    {
        return UserRow::query()
            ->when($filters['account_status'] ?? null, fn (Builder $query, string $status) => $query->where('account_status', $status))
            ->orderBy('name');
    }
}
