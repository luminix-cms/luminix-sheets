<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Luminix\Backend\Model\LuminixModel;

/**
 * Carries neither attribute — both sheet routes exist but must 404.
 */
class Tag extends Model
{
    use LuminixModel;

    protected $fillable = ['label'];
}
