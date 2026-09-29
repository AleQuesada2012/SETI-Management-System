<?php

namespace App\Models\Reporting;

use App\Enums\AuditAction;
use App\Enums\AuditChannel;
use Illuminate\Support\Carbon;

/**
 * A row of reporting.audit_logs: who did what, to which element, when and from where (RF_68, RF_99, dashboard
 * "Actividad reciente"). old_values/new_values arrive as JSON text.
 *
 * @property int $id
 * @property Carbon $occurred_at
 * @property int|null $actor_id
 * @property string|null $actor_label
 * @property AuditAction $action
 * @property string|null $entity_type morph alias: asset, space, user…
 * @property int|null $entity_id
 * @property string|null $entity_label
 * @property string|null $old_values
 * @property string|null $new_values
 * @property AuditChannel $channel
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $request_id
 */
class AuditLogRow extends ReportingView
{
    protected $table = 'reporting.audit_logs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'action' => AuditAction::class,
            'channel' => AuditChannel::class,
        ];
    }
}
