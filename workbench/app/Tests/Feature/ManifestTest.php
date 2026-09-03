<?php

namespace Workbench\App\Tests\Feature;

use Luminix\Frontend\Services\ManifestService;
use Workbench\App\Tests\TestCase;

class ManifestTest extends TestCase
{
    public function test_the_manifest_flags_which_models_offer_each_action(): void
    {
        $this->actingAs($this->user());

        $models = (new ManifestService($this->app))->make(true)->get()['models'];

        $this->assertTrue($models['player']['exportable']);
        $this->assertTrue($models['player']['importable']);

        $this->assertTrue($models['note']['exportable']);
        $this->assertFalse($models['note']['importable']);

        $this->assertFalse($models['tag']['exportable']);
        $this->assertFalse($models['tag']['importable']);
    }

    public function test_the_sheet_routes_reach_the_manifest(): void
    {
        $this->actingAs($this->user());

        $routes = (new ManifestService($this->app))->make(true)->get()['routes'];

        $this->assertSame(
            ['luminix-api/players/export', 'get'],
            $routes['luminix']['player']['export']
        );

        $this->assertSame(
            ['luminix-api/players/import', 'post'],
            $routes['luminix']['player']['import']
        );
    }
}
