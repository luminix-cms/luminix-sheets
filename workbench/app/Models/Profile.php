<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Never persisted — it exists to exercise the default value formatting.
 */
class Profile extends Model
{
    protected $fillable = ['name', 'status', 'tier', 'tags', 'joined_at', 'active'];

    protected $casts = [
        'tags' => 'array',
        'joined_at' => 'datetime',
        'active' => 'boolean',
    ];
}
