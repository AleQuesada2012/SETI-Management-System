<?php

namespace App\Models;

use App\Enums\ReportCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Seeded, read-only catalog of the reports in RF_63–RF_79. `category` drives the RF_60 menu.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property ReportCategory $category
 * @property string|null $description
 * @property int $sort_order
 */
class ReportType extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ReportCategory::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<ReportGeneration, $this>
     */
    public function generations(): HasMany
    {
        return $this->hasMany(ReportGeneration::class);
    }
}
