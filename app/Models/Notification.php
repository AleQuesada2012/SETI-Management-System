<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A row of the in-app inbox (ADR-0006). Users read theirs through `$user->notifications()`; this model only adds
 * the retention rule: read notifications older than 180 days are pruned (`model:prune`, scheduled daily).
 */
class Notification extends DatabaseNotification
{
    use MassPrunable;

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('read_at', '<', now()->subDays(180));
    }
}
