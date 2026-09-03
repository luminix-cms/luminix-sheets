<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Luminix\Backend\Model\LuminixModel;
use Luminix\Sheets\Exportable;

/**
 * Declares a handler that does not implement the contract. Resolving it must
 * fail loudly instead of producing an unusable object.
 */
#[Exportable(handler: \stdClass::class)]
class Broken extends Model
{
    use LuminixModel;

    protected $fillable = ['name'];
}
