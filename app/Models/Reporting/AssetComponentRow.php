<?php

namespace App\Models\Reporting;

/**
 * A row of reporting.asset_components: an internal part of an asset that is not tracked on its own (RF_12, RF_31;
 * components report RF_77). Components of deleted assets are not in the view.
 *
 * @property int $id
 * @property int $asset_id
 * @property string $asset_code
 * @property string|null $asset_tag
 * @property string $asset_name
 * @property string $name
 * @property string|null $specs
 * @property string|null $serial_number
 * @property int $quantity
 * @property string|null $notes
 */
class AssetComponentRow extends ReportingView
{
    protected $table = 'reporting.asset_components';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
