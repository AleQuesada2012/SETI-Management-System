<?php

namespace App\Models\Reporting;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Read-only model over a view of the `reporting` schema: the contract that lists, reports and
 * PowerBI read (ADR-0010). Writes go through the base models and Actions, never through these.
 */
abstract class ReportingView extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = ['*'];

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        throw new LogicException(static::class.' reads the reporting schema and cannot be saved.');
    }

    public function delete(): ?bool
    {
        throw new LogicException(static::class.' reads the reporting schema and cannot be deleted.');
    }
}
