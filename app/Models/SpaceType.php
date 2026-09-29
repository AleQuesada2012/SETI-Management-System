<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Laboratorio, Oficina or Sala; decides whether its spaces can be reserved (CONTEXT.md "Space type").
 *
 * @property int $id
 * @property string $name
 * @property bool $is_reservable
 */
#[Fillable(['name', 'is_reservable', 'description'])]
class SpaceType extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_reservable' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Space, $this>
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }
}
