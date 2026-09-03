<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Declares the property but never initialises it: reading it must fall back to
 * $hidden rather than yielding null.
 */
class Uninitialized extends Model
{
    protected $fillable = ['name', 'token'];

    protected $hidden = ['token'];

    protected array $sheetsHidden;
}
