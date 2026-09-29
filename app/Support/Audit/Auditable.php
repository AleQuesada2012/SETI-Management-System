<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Records created / updated / deleted / restored in audit_logs for every business model
 * (RF_00_2, RF_00_3). Add `use Auditable;` to the model; nothing else is needed.
 *
 * - The model needs a morph-map alias (AppServiceProvider): entity_type stores the alias.
 * - Override auditLabel() for a readable snapshot, e.g. "A-0014 · Proyector Epson · LAB-110".
 * - Override auditExclude() to keep extra columns out of the log; secrets and $hidden are
 *   always excluded.
 * - Pivot changes (attach/detach/sync) fire no model events: log them with Audit::record().
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => app(Auditor::class)->modelCreated($model));
        static::updated(fn (Model $model) => app(Auditor::class)->modelUpdated($model));
        static::deleted(fn (Model $model) => app(Auditor::class)->modelDeleted($model));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => app(Auditor::class)->modelRestored($model));
        }
    }

    /** Readable snapshot stored in audit_logs.entity_label. Override it per model. */
    public function auditLabel(): string
    {
        $name = $this->getAttribute('name') ?? $this->getAttribute('code');

        return is_scalar($name) && (string) $name !== '' ? (string) $name : class_basename($this).' #'.$this->getKey();
    }

    /**
     * Extra attributes never written to the audit log for this model.
     *
     * @return list<string>
     */
    public function auditExclude(): array
    {
        return [];
    }
}
