<?php

namespace Workbench\App\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Workbench\App\Tests\TestCase;

class RouteTest extends TestCase
{
    public function test_export_and_import_routes_are_registered_for_a_model_carrying_both_attributes(): void
    {
        $this->assertTrue(Route::has('luminix.player.export'));
        $this->assertTrue(Route::has('luminix.player.import'));

        $this->assertSame(
            '/luminix-api/players/export',
            '/'.Route::getRoutes()->getByName('luminix.player.export')->uri()
        );
    }

    /**
     * The attributes are what opts a model in. A route registered for a model
     * that cannot answer it is not harmless: it shows up in `route:list`, in
     * the frontend manifest and in generated UI, promising an action that
     * answers 404.
     */
    public function test_a_model_without_the_attributes_gets_no_sheet_route(): void
    {
        $this->assertTrue(Route::has('luminix.tag.index'), 'Tag should be a routed Luminix model');

        $this->assertFalse(Route::has('luminix.tag.export'));
        $this->assertFalse(Route::has('luminix.tag.import'));
    }

    public function test_a_model_marked_exportable_only_gets_no_import_route(): void
    {
        $this->assertTrue(Route::has('luminix.note.export'));

        $this->assertFalse(Route::has('luminix.note.import'));
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
