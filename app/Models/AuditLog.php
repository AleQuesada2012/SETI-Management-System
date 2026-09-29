<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\AuditChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An unchangeable record of who did what, to which element, when and from where
 * (CONTEXT.md "Audit entry", ADR-0002). The database rejects UPDATE, DELETE and TRUNCATE.
 * Write entries through App\Support\Audit\Audit (or the Auditable trait), never directly.
 *
 * @property int $id
 * @property Carbon $occurred_at
 * @property int|null $actor_id
 * @property string|null $actor_label
 * @property AuditAction $action
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property string|null $entity_label
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property AuditChannel $channel
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $request_id
 */
class AuditLog extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'action' => AuditAction::class,
            'channel' => AuditChannel::class,
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
