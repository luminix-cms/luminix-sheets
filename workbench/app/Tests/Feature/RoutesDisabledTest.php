<?php

namespace Workbench\App\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Workbench\App\Tests\TestCase;

/**
 * An application that wants to register the endpoints itself turns the
 * package's own registration off.
 */
class RoutesDisabledTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('luminix.sheets.routes.enabled', false);
    }

    public function test_no_sheet_routes_are_registered(): void
    {
        $this->assertFalse(Route::has('luminix.player.export'));
        $this->assertFalse(Route::has('luminix.player.import'));

        // The model's own routes are untouched.
        $this->assertTrue(Route::has('luminix.player.index'));
    }
}
