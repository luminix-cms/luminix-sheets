<?php

namespace Workbench\App\Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Luminix\Backend\Services\RouteGenerator;
use Luminix\Sheets\LuminixSheetsServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * An application publishes `config/luminix/sheets.php` and trims it, or keeps a
 * copy from before a release that added a key.
 *
 * `LoadConfiguration` puts that file in place and the provider's register()
 * merges the package defaults underneath it — in that order, which is why this
 * runs against a bare container instead of the Testbench application, whose
 * defineEnvironment() hook only fires after every provider is registered.
 */
class PartialConfigTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $published
     * @return array<string, mixed>
     */
    private function merged(array $published): array
    {
        $app = new Container;
        $app->instance('config', new Repository([
            'luminix' => ['sheets' => $published],
        ]));

        (new LuminixSheetsServiceProvider($app))->register();

        RouteGenerator::flushReducers();

        return $app->make('config')->get('luminix.sheets');
    }

    public function test_the_published_keys_win(): void
    {
        $merged = $this->merged(['import' => ['formats' => ['xlsx', 'csv']]]);

        $this->assertSame(['xlsx', 'csv'], $merged['import']['formats']);
    }

    /**
     * The one that matters: an absent verb reads as null, and null is documented
     * as turning off both the gate and the row scope. A shallow merge would open
     * the endpoint on an application that only meant to rename the other verb.
     */
    public function test_a_verb_missing_from_a_published_block_keeps_its_default(): void
    {
        $merged = $this->merged(['permissions' => ['export' => 'update']]);

        $this->assertSame('update', $merged['permissions']['export']);
        $this->assertSame('create', $merged['permissions']['import']);
    }

    public function test_siblings_of_a_published_key_survive(): void
    {
        $merged = $this->merged(['import' => ['formats' => ['csv']]]);

        $this->assertSame(['csv'], $merged['import']['formats']);
        $this->assertSame(10240, $merged['import']['max_file_size_kb']);
    }

    /**
     * Turning an action off is still possible — it just has to be said.
     */
    public function test_an_explicit_null_verb_is_honoured(): void
    {
        $merged = $this->merged(['permissions' => ['export' => null]]);

        $this->assertNull($merged['permissions']['export']);
    }
}
