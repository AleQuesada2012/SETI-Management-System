<?php

namespace App\Reports\Definitions;

use App\Enums\PermissionName;
use App\Models\Reporting\AuditLogRow;
use App\Queries\Reports\AuditTrail;
use App\Reports\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** RF_68: the original audit labels and values, rather than current entity information. */
final class AuditReport extends ReportDefinition
{
    public function code(): string
    {
        return 'audit';
    }

    public function permission(): PermissionName
    {
        return PermissionName::ReportsAudit;
    }

    public function columns(): array
    {
        $columns = [];
        foreach (['actor_label', 'occurred_at', 'action', 'entity_label', 'old_values', 'new_values'] as $key) {
            $columns[$key] = __('reports.audit.columns.'.$key);
        }

        return $columns;
    }

    /** @return Builder<AuditLogRow> */
    public function query(array $filters): Builder
    {
        return AuditTrail::query($filters);
    }

    public function filterKeys(): array
    {
        return ['actor', 'action', 'occurred_at'];
    }

    public function landscape(): bool
    {
        return true;
    }

    public function normalizeFilters(array $filters): array
    {
        return AuditTrail::validate($filters);
    }

    public function filterFields(): array
    {
        $choices = AuditTrail::choices();

        return [
            'actor' => ['type' => 'values', 'label' => __('reports.audit.actor'), 'options' => $choices['actor']],
            'action' => ['type' => 'values', 'label' => __('reports.audit.action'), 'options' => $choices['action']],
            'occurred_at' => ['type' => 'date', 'label' => __('reports.audit.occurred_at')],
        ];
    }

    protected function cell(Model $row, string $key): string|int|float|null
    {
        if ($key === 'occurred_at') {
            return $row->getAttribute($key)->timezone('America/Costa_Rica')->format('d/m/Y H:i:s');
        }

        return parent::cell($row, $key);
    }
}
