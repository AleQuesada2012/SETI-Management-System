<?php

namespace App\Support\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes audit_logs rows (RF_00_2, RF_00_3, RF_00_4; ADR-0002).
 *
 * Model changes are recorded automatically by the Auditable trait. Everything that is not a
 * model create/update/delete/restore (role and manager changes, exports, logins, password
 * set, deactivations, file attach/detach) is recorded explicitly with Audit::record().
 *
 * It writes on the current database connection, so an entry commits or rolls back together
 * with the change it describes when the caller wraps the operation in DB::transaction().
 */
final class Auditor
{
    /** Never written into old_values/new_values, on any model (Agent-DB.md §12). */
    private const ALWAYS_EXCLUDED = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'search_text',
        'created_at',
        'updated_at',
    ];

    public function __construct(private readonly AuditContext $context) {}

    /**
     * @param  array<string, mixed>|null  $old  changed fields before the action
     * @param  array<string, mixed>|null  $new  changed fields after the action
     */
    public function record(
        AuditAction $action,
        ?Model $entity = null,
        ?array $old = null,
        ?array $new = null,
        ?string $entityLabel = null,
        ?User $actor = null,
    ): AuditLog {
        $actor ??= $this->context->actor();

        $log = new AuditLog;
        $log->forceFill([
            'actor_id' => $actor?->getKey(),
            'actor_label' => $actor === null ? null : Str::limit($actor->name, 250),
            'action' => $action,
            'entity_type' => $entity?->getMorphClass(),
            'entity_id' => $entity?->getKey(),
            'entity_label' => $entity === null ? $entityLabel : Str::limit($entityLabel ?? $this->labelFor($entity), 250),
            'old_values' => $old === [] ? null : $old,
            'new_values' => $new === [] ? null : $new,
            'channel' => $this->context->channel(),
            'ip_address' => $this->context->ipAddress(),
            'user_agent' => $this->context->userAgent(),
            'request_id' => $this->context->requestId(),
        ])->save();

        return $log;
    }

    public function modelCreated(Model $model): void
    {
        $this->record(AuditAction::Created, $model, null, $this->snapshot($model));
    }

    public function modelUpdated(Model $model): void
    {
        $new = $this->auditable($model, $model->getChanges());

        if ($new === []) {
            return; // only excluded columns changed (timestamps, secrets): nothing worth auditing
        }

        $old = array_intersect_key($model->getRawOriginal(), $new);

        $this->record(AuditAction::Updated, $model, $old, $new);
    }

    public function modelDeleted(Model $model): void
    {
        $this->record(AuditAction::Deleted, $model, $this->snapshot($model), null);
    }

    public function modelRestored(Model $model): void
    {
        $this->record(AuditAction::Restored, $model, null, $this->snapshot($model));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Model $model): array
    {
        return array_filter(
            $this->auditable($model, $model->getAttributes()),
            fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function auditable(Model $model, array $attributes): array
    {
        $excluded = [...self::ALWAYS_EXCLUDED, ...$model->getHidden()];

        if (method_exists($model, 'auditExclude')) {
            $excluded = [...$excluded, ...$model->auditExclude()];
        }

        return array_diff_key($attributes, array_flip($excluded));
    }

    private function labelFor(Model $model): string
    {
        if (method_exists($model, 'auditLabel')) {
            return (string) $model->auditLabel();
        }

        return class_basename($model).' #'.$model->getKey();
    }
}
