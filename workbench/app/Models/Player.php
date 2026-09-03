<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Luminix\Backend\Model\LuminixModel;
use Luminix\Sheets\Exportable;
use Luminix\Sheets\Importable;

/**
 * The default-handler fixture.
 *
 * `secret_note` is declared in $sheetsHidden but NOT in $hidden: the column is
 * only ever excluded if HiddenColumns reads the protected property. A reader
 * that falls through Eloquent's __get sees null, falls back to $hidden — empty
 * here — and leaks the column, which is exactly what the tests catch.
 */
#[Exportable]
#[Importable]
class Player extends Model
{
    use LuminixModel;

    protected $fillable = [
        'name',
        'registration',
        'score',
        'active',
        'joined_at',
        'secret_note',
    ];

    protected array $sheetsHidden = ['secret_note'];

    protected $casts = [
        'score' => 'integer',
        'active' => 'boolean',
        'joined_at' => 'datetime',
    ];

    /**
     * Row-level permission: `banned` players are invisible to `read`.
     * The export must honour it exactly as the listing does.
     */
    public function scopeAllowed(Builder $query, string $permission)
    {
        if ($permission === 'read') {
            $query->where('banned', false);
        }
    }
}
