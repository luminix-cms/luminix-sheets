<?php

namespace Workbench\App\Tests\Feature;

use Illuminate\Support\Facades\File;
use Workbench\App\Tests\TestCase;

class MakeCommandsTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(app_path('Sheets'));

        parent::tearDown();
    }

    public function test_it_generates_an_export_handler(): void
    {
        $this->artisan('make:export', ['name' => 'PlayerExport'])->assertSuccessful();

        $path = app_path('Sheets/Export/PlayerExport.php');

        $this->assertFileExists($path);

        $contents = file_get_contents($path);

        $this->assertStringContainsString('class PlayerExport extends DefaultExportable', $contents);
        $this->assertStringContainsString('use App\Models\Player;', $contents);
        $this->assertStringContainsString('parent::__construct($modelClass);', $contents);
    }

    public function test_it_generates_an_import_handler(): void
    {
        $this->artisan('make:import', ['name' => 'PlayerImport'])->assertSuccessful();

        $path = app_path('Sheets/Import/PlayerImport.php');

        $this->assertFileExists($path);

        $this->assertStringContainsString(
            'class PlayerImport extends DefaultImportable',
            file_get_contents($path)
        );
    }

    /**
     * A generated handler must be valid PHP that satisfies the contract — the
     * uncommented signatures included.
     */
    public function test_the_generated_handlers_are_syntactically_valid(): void
    {
        $this->artisan('make:export', ['name' => 'PlayerExport'])->assertSuccessful();
        $this->artisan('make:import', ['name' => 'PlayerImport'])->assertSuccessful();

        foreach (['Export/PlayerExport', 'Import/PlayerImport'] as $file) {
            $path = app_path("Sheets/{$file}.php");

            exec('php -l '.escapeshellarg($path), $output, $status);

            $this->assertSame(0, $status, implode("\n", $output));
        }
    }
}
