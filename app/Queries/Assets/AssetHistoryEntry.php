<?php

namespace App\Queries\Assets;

use Carbon\CarbonInterface;

/**
 * One line of an asset's history (RF_09), whatever its source: a movement, an event or an audited change.
 */
final readonly class AssetHistoryEntry
{
    /**
     * @param  'movement'|'event'|'change'  $type
     * @param  list<string>  $details  ready-to-show Spanish lines
     * @param  bool  $dateOnly  events are dated by day; movements and changes carry a time
     */
    public function __construct(
        public string $type,
        public CarbonInterface $at,
        public bool $dateOnly,
        public string $title,
        public array $details,
        public ?string $actor,
    ) {}
}
