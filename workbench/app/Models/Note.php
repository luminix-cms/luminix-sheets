<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Luminix\Backend\Model\LuminixModel;
use Luminix\Sheets\Exportable;

/**
 * Exportable but not importable — POST .../notes/import must 404.
 */
#[Exportable]
class Note extends Model
{
    use LuminixModel;

    protected $fillable = ['title'];
}
