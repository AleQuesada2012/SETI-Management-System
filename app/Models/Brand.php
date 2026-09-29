<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Manufacturer of an asset (CONTEXT.md "Brand").
 *
 * @property int $id
 * @property string $name
 */
#[Fillable(['name'])]
class Brand extends Model
{
    use Auditable, HasFactory, SoftDeletes;
}
