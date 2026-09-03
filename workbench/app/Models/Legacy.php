<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Declares the hidden columns as a method instead of a property, and relies on
 * $hidden for the rest. Not registered with the Finder — it exists only for the
 * resolver unit tests.
 */
class Legacy extends Model
{
    protected $fillable = ['name', 'token', 'internal'];

    protected $hidden = ['token'];

    public function sheetsHiddenForExport(): array
    {
        return ['internal'];
    }
}
