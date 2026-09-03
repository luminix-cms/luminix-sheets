<?php

namespace Workbench\App\Tests\Unit;

use Generator;
use Luminix\Sheets\Support\SpreadsheetReader;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Workbench\App\Tests\TestCase;

class SpreadsheetReaderTest extends TestCase
{
    /** @var string[] */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_rows_are_keyed_by_their_number_in_the_sheet(): void
    {
        $path = $this->xlsx([
            ['Cabeçalho'],
            ['Nome'],
            ['Ana'],
        ]);

        $rows = iterator_to_array(SpreadsheetReader::rows($path, 'xlsx'));

        $this->assertSame([1, 2, 3], array_keys($rows));
    }

    public function test_it_yields_instead_of_building_the_whole_file(): void
    {
        $path = $this->xlsx([['a'], ['b']]);

        $this->assertInstanceOf(Generator::class, SpreadsheetReader::rows($path, 'xlsx'));
    }

    public function test_only_the_first_worksheet_is_read(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'reader-').'.xlsx';
        $this->files[] = $path;

        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['primeira']));
        $writer->addNewSheetAndMakeItCurrent();
        $writer->addRow(Row::fromValues(['segunda']));
        $writer->close();

        $rows = iterator_to_array(SpreadsheetReader::rows($path, 'xlsx'));

        $this->assertCount(1, $rows);
        $this->assertSame(['primeira'], $rows[1]);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'reader-').'.xlsx';
        $this->files[] = $path;

        $writer = new XlsxWriter;
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    }
}
