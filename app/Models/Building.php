<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Building a space is in, the main part of its location (CONTEXT.md "Building"). Catalog, soft-deletable.
 *
 * @property int $id
 * @property string $name
 */
#[Fillable(['name', 'notes'])]
class Building extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return HasMany<Space, $this>
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }
}
