<?php

namespace Workbench\App\Tests;

use Illuminate\Http\UploadedFile;
use Luminix\Backend\BackendServiceProvider;
use Luminix\Backend\Services\ModelFinder;
use Luminix\Backend\Services\RouteGenerator;
use Luminix\Frontend\FrontendServiceProvider;
use Luminix\Frontend\Services\BootService;
use Luminix\Frontend\Services\ManifestService;
use Luminix\Sheets\LuminixSheetsServiceProvider;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as TestbenchTestCase;
use Workbench\App\Models\Broken;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\Note;
use Workbench\App\Models\Player;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;
use Workbench\App\Providers\WorkbenchServiceProvider;
use Workbench\App\Sheets\InvoiceExport;
use Workbench\App\Sheets\InvoiceImport;

use function Orchestra\Testbench\artisan;

abstract class TestCase extends TestbenchTestCase
{
    use WithWorkbench;

    /** @var string[] Temp files created by the sheet helpers. */
    private array $scratch = [];

    protected function getPackageProviders($app)
    {
        return [
            BackendServiceProvider::class,
            FrontendServiceProvider::class,
            LuminixSheetsServiceProvider::class,
            WorkbenchServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('app.debug', true);

        // 'web' instead of the default ['api', 'auth']: actingAs() is enough to
        // authenticate, and an unauthenticated call reaches the gate — which is
        // what these tests are asserting — instead of the auth middleware.
        $app['config']->set('luminix.backend.security.middleware', ['web']);

        // The workbench models live outside app/Models, so the filesystem scan
        // never sees them.
        $app['config']->set('luminix.backend.models.include', [
            Player::class,
            Note::class,
            Tag::class,
            Invoice::class,
            Broken::class,
        ]);

        $app['config']->set('auth', require __DIR__.'/../../config/auth.ci.php');
    }

    protected function setUp(): void
    {
        // Reducers are static, so every boot in the run would otherwise stack
        // another copy of the package's registrations on top of the last.
        RouteGenerator::flushReducers();
        ManifestService::flushReducers();
        ModelFinder::flushReducers();
        BootService::flushReducers();

        parent::setUp();

        WorkbenchServiceProvider::reset();
        InvoiceExport::reset();
        InvoiceImport::reset();
    }

    protected function tearDown(): void
    {
        foreach ($this->scratch as $path) {
            @unlink($path);
        }

        $this->scratch = [];

        parent::tearDown();
    }

    protected function defineDatabaseMigrations(): void
    {
        artisan($this, 'migrate', ['--database' => 'testing']);

        $this->beforeApplicationDestroyed(
            fn () => artisan($this, 'migrate:rollback', ['--database' => 'testing'])
        );
    }

    // Helpers

    protected function user(string $email = 'tester@example.com'): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Tester', 'password' => bcrypt('password')]
        );
    }

    /**
     * Builds a spreadsheet with OpenSpout directly — not through the package's
     * own writer — so an import failure cannot be masked by a matching bug in
     * the export.
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function makeSheet(array $rows, string $format = 'xlsx'): UploadedFile
    {
        $path = $this->scratchPath($format);

        $writer = $format === 'csv' ? new CsvWriter : new XlsxWriter;
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return new UploadedFile($path, 'sheet.'.$format, null, null, true);
    }

    /**
     * Reads a raw xlsx payload back into a plain array of rows.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function readSheet(string $contents): array
    {
        $path = $this->scratchPath('xlsx');
        file_put_contents($path, $contents);

        $reader = new XlsxReader;
        $reader->open($path);

        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * The worksheet tab name of a raw xlsx payload.
     */
    protected function readSheetName(string $contents): string
    {
        $path = $this->scratchPath('xlsx');
        file_put_contents($path, $contents);

        $reader = new XlsxReader;
        $reader->open($path);

        $name = '';

        foreach ($reader->getSheetIterator() as $sheet) {
            $name = $sheet->getName();

            break;
        }

        $reader->close();

        return $name;
    }

    private function scratchPath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'luminix-sheets-test-').'.'.$extension;

        $this->scratch[] = $path;

        return $path;
    }
}
