<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Models\User;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Gates the tests flip to prove export/import are permission-checked.
     * A null user (guest) never passes.
     */
    public static array $denied = [];

    public static function reset(): void
    {
        static::$denied = [];
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach (['player', 'note', 'tag', 'invoice', 'broken', 'user'] as $alias) {
            foreach (['read', 'create', 'update', 'delete'] as $verb) {
                Gate::define(
                    "{$verb}-{$alias}",
                    fn (?User $user, $item = null) => $user !== null
                        && ! in_array("{$verb}-{$alias}", static::$denied, true)
                );
            }
        }
    }
}
