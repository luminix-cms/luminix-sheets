<?php

namespace Workbench\App\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Workbench\App\Tests\TestCase;

class RouteTest extends TestCase
{
    public function test_export_and_import_routes_are_registered_for_every_model(): void
    {
        $this->assertTrue(Route::has('luminix.player.export'));
        $this->assertTrue(Route::has('luminix.player.import'));

        $this->assertSame(
            '/luminix-api/players/export',
            '/'.Route::getRoutes()->getByName('luminix.player.export')->uri()
        );
    }

    /**
     * `players/{id}` is registered by luminix/backend for `show`. If the sheet
     * routes came after it, `players/export` would match `{id}` first and the
     * export would never run.
     */
    public function test_export_route_is_registered_before_the_show_route(): void
    {
        $names = [];

        foreach (Route::getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'luminix.player.')) {
                $names[] = $route->getName();
            }
        }

        $this->assertLessThan(
            array_search('luminix.player.show', $names, true),
            array_search('luminix.player.export', $names, true),
        );

        $this->assertLessThan(
            array_search('luminix.player.update', $names, true),
            array_search('luminix.player.import', $names, true),
        );
    }
}
