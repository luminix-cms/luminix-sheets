<?php

namespace Workbench\App\Models\Reports;

use Illuminate\Database\Eloquent\Model;
use Luminix\Sheets\Exportable;

/**
 * Marks a whole family of models exportable from the parent class.
 */
#[Exportable]
abstract class BaseReport extends Model
{
    protected $fillable = ['title'];
}
