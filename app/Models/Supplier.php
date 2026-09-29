<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Company an asset was acquired from (CONTEXT.md "Supplier", RF_15).
 *
 * @property int $id
 * @property string $name
 */
#[Fillable(['name', 'tax_id', 'contact_name', 'phone', 'email', 'notes'])]
class Supplier extends Model
{
    use Auditable, SoftDeletes;
}
